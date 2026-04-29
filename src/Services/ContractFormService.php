<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\ContractForm;
use App\Models\ContractTemplate;

/**
 * Orchestrates the public-facing flow:
 *   issueShareLink → public form → submitForm → SIGNIUS package
 *
 * Both methods are tenant-aware; callers (controllers) still apply the
 * outer Auth + ModuleAccess gates. This service is the single place that
 * touches ContractTemplate + ContractForm + filesystem + SigniusApiService.
 */
final class ContractFormService
{
    /**
     * Office user creates a share link for a template + (optional) target client.
     *
     * @return array{url:string,token:string,form_id:int,expires_at:string}
     */
    public static function issueShareLink(int $officeId, int $templateId, ?int $clientId, ?string $email, ?string $name, string $createdByType, int $createdById): array
    {
        $template = ContractTemplate::findByIdForOffice($templateId, $officeId);
        if (!$template || empty($template['is_active'])) {
            throw new \RuntimeException('Template not found or inactive');
        }
        if ($clientId !== null) {
            $client = Client::findById($clientId);
            if (!$client || (int) ($client['office_id'] ?? 0) !== $officeId) {
                throw new \RuntimeException('Client does not belong to this office');
            }
        }

        $token     = bin2hex(random_bytes(32));
        $cfg       = require dirname(__DIR__, 2) . '/config/contracts.php';
        $ttlHours  = (int) ($cfg['form_token_ttl_hours'] ?? 336);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlHours * 3600);

        $formId = ContractForm::create(
            $templateId, $officeId, $clientId, $token, $expiresAt,
            ['recipient_email' => $email, 'recipient_name' => $name, 'expires_at' => $expiresAt],
            $createdByType, $createdById
        );

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $baseUrl = rtrim((string) ($appConfig['url'] ?? ''), '/');
        $url = $baseUrl . '/contracts/form/' . $token;

        return ['url' => $url, 'token' => $token, 'form_id' => $formId, 'expires_at' => $expiresAt];
    }

    /**
     * Client submits the public form. Validates, fills the PDF locally, then
     * dispatches the package to SIGNIUS. Caller (PublicContractFormController)
     * is responsible for CSRF + per-IP rate limit; this method assumes both
     * have already passed.
     *
     * @return array{form_id:int,package_id:?string}
     */
    public static function submitForm(string $token, array $rawValues): array
    {
        $form = ContractForm::findByToken($token);
        if (!$form) {
            throw new \RuntimeException('Form not found');
        }
        if ($form['status'] !== 'pending') {
            throw new \RuntimeException('Form is not pending (status=' . $form['status'] . ')');
        }
        if (ContractForm::isExpired($form)) {
            ContractForm::setStatus((int) $form['id'], 'expired');
            throw new \RuntimeException('Form expired');
        }

        $template = ContractTemplate::findById((int) $form['template_id']);
        if (!$template) {
            throw new \RuntimeException('Template missing');
        }

        $fields = ContractTemplate::decodeFields($template);
        $cleanValues = self::validateAndFilter($fields, $rawValues);

        // Persist what the client typed BEFORE we touch pdftk / SIGNIUS — so a
        // failure in either of those leaves a recoverable record we can retry.
        ContractForm::markFilled((int) $form['id'], $cleanValues);

        $cfg = require dirname(__DIR__, 2) . '/config/contracts.php';
        $storageBase = dirname(__DIR__, 2) . '/' . trim((string) $cfg['template_storage_dir'], '/');
        $filledDir = $storageBase . '/' . (int) $template['office_id'] . '/filled';
        if (!is_dir($filledDir)) mkdir($filledDir, 0750, true);
        $filledRel = trim((string) $cfg['template_storage_dir'], '/')
            . '/' . (int) $template['office_id'] . '/filled/form_' . (int) $form['id'] . '.pdf';
        $filledAbs = dirname(__DIR__, 2) . '/' . $filledRel;

        ContractPdfService::fillForm(
            dirname(__DIR__, 2) . '/' . ltrim((string) $template['stored_path'], '/'),
            $cleanValues,
            $filledAbs
        );

        // Dispatch to SIGNIUS. Failures here keep the form in 'filled' state
        // so the office can retry from the UI without losing the client's data.
        $packageId = null;
        try {
            $signers = self::buildSigners($template, $form, $cleanValues);
            $result  = SigniusApiService::createPackage($filledAbs, $signers, [
                'form_id'   => (int) $form['id'],
                'office_id' => (int) $form['office_id'],
            ]);
            $packageId = $result['package_id'];
        } catch (\Throwable $e) {
            // Re-throw so the controller can flash the error; status stays 'filled'.
            throw new \RuntimeException('Form filled, SIGNIUS dispatch failed: ' . $e->getMessage(), 0, $e);
        }

        ContractForm::markSubmitted((int) $form['id'], $filledRel, $packageId);
        return ['form_id' => (int) $form['id'], 'package_id' => $packageId];
    }

    // ─────────────────────────────────────────────

    /**
     * Resolve template signers_json into a concrete list with email addresses.
     * Each signer either references a 'email_field' from the form (e.g. the
     * client typed their email into that field), or has a static email
     * provided when the template was uploaded.
     *
     * @return list<array{role:string,label:string,email:string,order:int}>
     */
    private static function buildSigners(array $template, array $form, array $values): array
    {
        $defs = ContractTemplate::decodeSigners($template);
        $resolved = [];
        $defaultEmail = (string) ($form['recipient_email'] ?? '');
        foreach ($defs as $i => $def) {
            $emailField = (string) ($def['email_field'] ?? '');
            $email = $emailField !== '' && isset($values[$emailField])
                ? (string) $values[$emailField]
                : (string) ($def['email'] ?? '');
            if ($email === '' && $def['role'] === 'client') {
                $email = $defaultEmail;
            }
            if ($email === '') {
                continue; // skip incomplete entries; SIGNIUS will reject if required
            }
            $resolved[] = [
                'role'  => (string) ($def['role'] ?? 'client'),
                'label' => (string) ($def['label'] ?? $def['role'] ?? 'Signer'),
                'email' => $email,
                'order' => (int)    ($def['order'] ?? $i + 1),
            ];
        }
        usort($resolved, fn($a, $b) => $a['order'] <=> $b['order']);
        return $resolved;
    }

    /**
     * Filter raw POST values down to declared template fields and enforce 'required'.
     * Throws on missing required field; trims long strings to a safe length.
     */
    private static function validateAndFilter(array $fields, array $raw): array
    {
        $clean = [];
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '') continue;

            $v = $raw[$name] ?? null;
            if ($f['type'] === 'checkbox') {
                $clean[$name] = !empty($v);
                continue;
            }
            $stringValue = is_scalar($v) ? trim((string) $v) : '';
            if (!empty($f['required']) && $stringValue === '') {
                throw new \RuntimeException('Required field missing: ' . $name);
            }
            // Cap each value at 4 KiB — pdftk handles big text but we don't
            // want to push 10 MiB through SIGNIUS metadata accidentally.
            if (strlen($stringValue) > 4096) {
                $stringValue = substr($stringValue, 0, 4096);
            }
            $clean[$name] = $stringValue;
        }
        return $clean;
    }
}
