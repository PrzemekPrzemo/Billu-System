<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ContractForm;
use App\Models\ContractTemplate;
use App\Services\ContractFormService;

/**
 * Public token-based access to a contract form. The URL is
 * /contracts/form/{token} — anyone with the link (and a non-expired,
 * pending form) can fill it. When a logged-in client matches the form's
 * client_id, we prefill known company-level fields.
 */
class PublicContractFormController extends Controller
{
    private const RATE_LIMIT_PER_HOUR = 5;

    public function formView(string $token): void
    {
        $form = ContractForm::findByToken($token);
        if (!$form) {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => 'not_found']);
            return;
        }
        if (ContractForm::isExpired($form) && $form['status'] === 'pending') {
            ContractForm::setStatus((int) $form['id'], 'expired');
            $form['status'] = 'expired';
        }
        if ($form['status'] !== 'pending') {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => $form['status']]);
            return;
        }

        $template = ContractTemplate::findById((int) $form['template_id']);
        if (!$template || empty($template['is_active'])) {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => 'template_inactive']);
            return;
        }

        $fields = ContractTemplate::decodeFields($template);
        $prefill = self::buildPrefill($form, $fields);

        $this->renderWithoutLayout('public/contract_form', [
            'form'     => $form,
            'template' => $template,
            'fields'   => $fields,
            'prefill'  => $prefill,
            'token'    => $token,
        ]);
    }

    public function formSubmit(): void
    {
        if (!$this->validateCsrf()) {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => 'csrf']);
            return;
        }
        $token = (string) ($_POST['token'] ?? '');
        if (strlen($token) !== 64) {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => 'not_found']);
            return;
        }

        // Rate limit: max 5 submits / hour per (IP, token). Cache fail-open
        // when Redis is null — we still rely on DB-level status guard below.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip !== '' && self::isRateLimited($ip, $token)) {
            $this->renderWithoutLayout('public/contract_invalid', ['reason' => 'rate_limited']);
            return;
        }

        try {
            $result = ContractFormService::submitForm($token, $_POST['fields'] ?? []);
        } catch (\Throwable $e) {
            // Log + show generic error; don't surface internal details to public form.
            error_log('Contract form submit failed: ' . $e->getMessage());
            $form = ContractForm::findByToken($token);
            $this->renderWithoutLayout('public/contract_invalid', [
                'reason' => 'submit_failed',
                'detail' => $form ? 'Status: ' . $form['status'] : null,
            ]);
            return;
        }

        AuditLog::log('public', 0, 'contract_form_submitted',
            "Form id {$result['form_id']} submitted via public token",
            'contract_form', $result['form_id']);

        $this->renderWithoutLayout('public/contract_thanks', [
            'form_id' => $result['form_id'],
        ]);
    }

    // ─────────────────────────────────────────────

    private static function buildPrefill(array $form, array $fields): array
    {
        $prefill = [];
        // Logged-in client matching the form gets company-level prefill.
        if (Auth::isClient() && $form['client_id'] !== null
            && (int) Session::get('client_id') === (int) $form['client_id']) {
            $client = Client::findById((int) $form['client_id']);
            if ($client) {
                foreach ($fields as $f) {
                    $name = strtolower($f['name']);
                    $prefill[$f['name']] = match (true) {
                        $name === 'nip'           => (string) ($client['nip'] ?? ''),
                        $name === 'company_name'
                        || $name === 'firma'
                        || $name === 'nazwa_firmy' => (string) ($client['company_name'] ?? ''),
                        $name === 'email'
                        || $name === 'e_mail'      => (string) ($client['email'] ?? ''),
                        $name === 'phone'
                        || $name === 'telefon'     => (string) ($client['phone'] ?? ''),
                        $name === 'address'
                        || $name === 'adres'       => (string) ($client['address'] ?? ''),
                        default                     => '',
                    };
                }
            }
        }
        // Recipient defaults from the share-link metadata, only for fields with no client prefill.
        foreach ($fields as $f) {
            if (!empty($prefill[$f['name']])) continue;
            $name = strtolower($f['name']);
            if ($name === 'email' || $name === 'e_mail') {
                $prefill[$f['name']] = (string) ($form['recipient_email'] ?? '');
            } elseif (in_array($name, ['name', 'imie', 'imie_nazwisko'], true)) {
                $prefill[$f['name']] = (string) ($form['recipient_name'] ?? '');
            }
        }
        return $prefill;
    }

    private static function isRateLimited(string $ip, string $token): bool
    {
        $cache = Cache::getInstance();
        $key = 'contract_form_throttle:' . sha1($ip . '|' . $token);
        $attempts = (int) ($cache->get($key) ?? 0);
        if ($attempts >= self::RATE_LIMIT_PER_HOUR) {
            return true;
        }
        $cache->set($key, $attempts + 1, 3600);
        return false;
    }
}
