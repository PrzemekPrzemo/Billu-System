<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Models\HrClientSettings;
use App\Models\HrEmployee;
use App\Services\HrEncryptionService;
use App\Services\HrPayslipPdfService;
use App\Services\MailService;

class HrPayslipEmailService
{
    private static array $monthNames = [
        1=>'styczeń', 2=>'luty', 3=>'marzec', 4=>'kwiecień',
        5=>'maj', 6=>'czerwiec', 7=>'lipiec', 8=>'sierpień',
        9=>'wrzesień', 10=>'październik', 11=>'listopad', 12=>'grudzień',
    ];

    public static function sendForRun(int $runId): array
    {
        $run = HrPayrollRun::findById($runId);
        if (!$run) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => ['Payroll run not found']];
        }

        $clientId = (int) $run['client_id'];
        $settings = HrClientSettings::getOrCreate($clientId);

        if (empty($settings['payslip_email_enabled'])) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        }

        $month    = (int) $run['period_month'];
        $year     = (int) $run['period_year'];
        $company  = $run['company_name'] ?? '';

        $subjectTemplate = $settings['payslip_email_subject_template']
            ?: 'Odcinek płacowy za {month} {year} — {company}';
        $subject = self::renderTemplate($subjectTemplate, $month, $year, $company);

        $items = HrDatabase::getInstance()->fetchAll(
            "SELECT pi.employee_id,
                    e.receive_payslip_email, e.email_payslip, e.email,
                    e.first_name, e.last_name
             FROM hr_payroll_items pi
             JOIN hr_employees e ON e.id = pi.employee_id
             WHERE pi.payroll_run_id = ?",
            [$runId]
        );

        $sent = 0; $skipped = 0; $failed = 0; $errors = [];

        foreach ($items as $item) {
            if (!(bool) $item['receive_payslip_email']) { $skipped++; continue; }

            $item = HrEncryptionService::decryptFields($item, ['email_payslip', 'email']);
            $toEmail = !empty($item['email_payslip']) ? $item['email_payslip'] : $item['email'];

            if (empty($toEmail)) { $skipped++; continue; }

            $employeeId = (int) $item['employee_id'];
            $empName    = $item['first_name'] . ' ' . $item['last_name'];

            try {
                $pdfPath = HrPayslipPdfService::generate($runId, $employeeId);
                $htmlBody = self::buildEmailBody($empName, $month, $year, $company);

                $mailer = MailService::createMailerForClient($clientId);
                $mailer->addAddress($toEmail, $empName);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body    = $htmlBody;
                $mailer->AltBody = strip_tags($htmlBody);

                if (file_exists($pdfPath)) {
                    $mailer->addAttachment($pdfPath, sprintf('Odcinek_%d_%02d.pdf', $year, $month));
                }

                $mailer->send();
                self::log($runId, $employeeId, $clientId, $toEmail, 'sent');
                $sent++;
            } catch (\Throwable $e) {
                $msg = "Błąd wysyłki do {$empName} ({$toEmail}): " . $e->getMessage();
                $errors[] = $msg;
                error_log("[HrPayslipEmail] {$msg}");
                self::log($runId, $employeeId, $clientId, $toEmail, 'failed', $e->getMessage());
                $failed++;
            }
        }

        return compact('sent', 'skipped', 'failed', 'errors');
    }

    public static function isEnabledForClient(int $clientId): bool
    {
        $settings = HrClientSettings::findByClient($clientId);
        return (bool) ($settings['payslip_email_enabled'] ?? false);
    }

    public static function getLogForRun(int $runId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT l.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_payslip_email_log l
             JOIN hr_employees e ON l.employee_id = e.id
             WHERE l.payroll_run_id = ?
             ORDER BY l.sent_at DESC",
            [$runId]
        );
    }

    private static function renderTemplate(string $tpl, int $month, int $year, string $company): string
    {
        return str_replace(
            ['{month}', '{year}', '{company}'],
            [self::$monthNames[$month] ?? $month, $year, $company],
            $tpl
        );
    }

    private static function buildEmailBody(string $empName, int $month, int $year, string $company): string
    {
        $monthName = self::$monthNames[$month] ?? $month;
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return "
            <p>Szanowna/y <strong>{$esc($empName)}</strong>,</p>
            <p>W załączniku przesyłamy odcinek płacowy za <strong>{$esc($monthName)} {$year}</strong>.</p>
            <p>Pracodawca: <em>{$esc($company)}</em></p>
            <p style='font-size:12px;color:#666;margin-top:24px;'>
                Wiadomość wygenerowana automatycznie przez system Billu HR.<br>
                Prosimy nie odpowiadać na tę wiadomość.
            </p>
        ";
    }

    private static function log(int $runId, int $employeeId, int $clientId, string $recipientEmail, string $status, string $errorMessage = null): void
    {
        try {
            $row = [
                'payroll_run_id'  => $runId,
                'employee_id'     => $employeeId,
                'client_id'       => $clientId,
                'recipient_email' => substr($recipientEmail, 0, 512),
                'status'          => $status,
            ];
            if ($errorMessage !== null) {
                $row['error_message'] = $errorMessage;
            }
            HrDatabase::getInstance()->insert('hr_payslip_email_log', $row);
        } catch (\Throwable $e) {
            error_log('[HrPayslipEmail] Log write failed: ' . $e->getMessage());
        }
    }
}
