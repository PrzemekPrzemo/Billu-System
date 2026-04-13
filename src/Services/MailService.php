<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailTemplate;
use App\Models\OfficeEmailSettings;
use App\Models\ClientSmtpConfig;

class MailService
{
    /**
     * Create mailer using system SMTP settings (DB first, config/mail.php fallback).
     */
    private static function createMailer(): PHPMailer
    {
        $dbHost = \App\Models\Setting::get('smtp_host', '');
        if (!empty($dbHost)) {
            return self::buildMailer(
                $dbHost,
                (int) \App\Models\Setting::get('smtp_port', '587'),
                \App\Models\Setting::get('smtp_encryption', 'tls'),
                \App\Models\Setting::get('smtp_user', ''),
                \App\Models\Setting::get('smtp_pass', ''),
                \App\Models\Setting::get('smtp_from_email', 'noreply@example.com'),
                \App\Models\Setting::get('smtp_from_name', 'BiLLU')
            );
        }

        // Fallback to config file
        $config = require __DIR__ . '/../../config/mail.php';
        return self::buildMailer(
            $config['host'], $config['port'], $config['encryption'],
            $config['username'], $config['password'],
            $config['from_email'], $config['from_name']
        );
    }

    /**
     * Create mailer for sending to a specific client — uses office SMTP if configured.
     */
    public static function createMailerForClient(int $clientId): PHPMailer
    {
        $smtpConfig = \App\Models\OfficeSmtpConfig::findEnabledByClientId($clientId);
        if ($smtpConfig) {
            $pass = !empty($smtpConfig['smtp_pass_encrypted'])
                ? base64_decode($smtpConfig['smtp_pass_encrypted'])
                : '';
            return self::buildMailer(
                $smtpConfig['smtp_host'],
                (int) $smtpConfig['smtp_port'],
                $smtpConfig['smtp_encryption'],
                $smtpConfig['smtp_user'],
                $pass,
                $smtpConfig['from_email'],
                $smtpConfig['from_name'] ?? ''
            );
        }
        return self::createMailer();
    }

    /**
     * Create mailer for invoice sending — uses client's own SMTP if configured, otherwise system.
     */
    public static function createMailerForInvoiceSending(int $clientId): PHPMailer
    {
        $smtpConfig = ClientSmtpConfig::findEnabledByClientId($clientId);
        if ($smtpConfig) {
            $pass = !empty($smtpConfig['smtp_pass_encrypted'])
                ? base64_decode($smtpConfig['smtp_pass_encrypted'])
                : '';
            return self::buildMailer(
                $smtpConfig['smtp_host'],
                (int) $smtpConfig['smtp_port'],
                $smtpConfig['smtp_encryption'],
                $smtpConfig['smtp_user'],
                $pass,
                $smtpConfig['from_email'],
                $smtpConfig['from_name'] ?? ''
            );
        }
        return self::createMailer();
    }

    private static function buildMailer(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPSecure = ($encryption === 'none') ? '' : $encryption;
        $mail->SMTPAuth   = !empty($user);
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        return $mail;
    }

    /**
     * Test SMTP connection by sending a test email to the from_email address.
     */
    public static function testSmtpConnection(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName): array
    {
        try {
            $mail = self::buildMailer($host, $port, $encryption, $user, $pass, $fromEmail, $fromName);
            $mail->addAddress($fromEmail);
            $mail->Subject = 'BiLLU - Test SMTP';
            $mail->Body = self::buildHtmlEmail('Test SMTP', '<p>Połączenie SMTP działa poprawnie.</p><p>Data testu: ' . date('Y-m-d H:i:s') . '</p>');
            $mail->isHTML(true);
            $mail->send();
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an invoice email with PDF attachment.
     */
    public static function sendInvoiceEmail(int $clientId, int $invoiceId, string $toEmail, string $subject, string $htmlBody, string $pdfPath): bool
    {
        try {
            $mail = self::createMailerForInvoiceSending($clientId);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $branding = self::getClientBranding($clientId);
            $mail->Body = self::buildHtmlEmail($subject, $htmlBody, $branding);
            $mail->AltBody = strip_tags($htmlBody);

            if (file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath);
            }

            $mail->send();

            // Track sending
            \App\Models\IssuedInvoice::update($invoiceId, [
                'email_sent_at' => date('Y-m-d H:i:s'),
                'email_sent_to' => $toEmail,
            ]);

            return true;
        } catch (\Throwable $e) {
            error_log("Invoice email error to {$toEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to client about new invoices to verify.
     */
    public static function sendNewInvoicesNotification(
        string $email,
        string $companyName,
        int $invoiceCount,
        string $periodLabel,
        string $deadline,
        string $language = 'pl',
        ?int $clientId = null
    ): bool {
        try {
            $mail = $clientId ? self::createMailerForClient($clientId) : self::createMailer();
            $mail->addAddress($email);

            $vars = [
                'company_name' => $companyName,
                'invoice_count' => $invoiceCount,
                'period' => $periodLabel,
                'deadline' => $deadline,
                'login_url' => (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . '/login',
            ];

            $tplSubject = EmailTemplate::renderSubject('new_invoices_notification', $language, $vars);
            $tplBody = EmailTemplate::renderBody('new_invoices_notification', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $branding = $clientId ? self::getOfficeBrandingForClient($clientId) : null;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody, $branding);
            } else {
                // Fallback to hardcoded
                $branding = $clientId ? self::getOfficeBrandingForClient($clientId) : null;
                if ($language === 'en') {
                    $mail->Subject = "New invoices to verify - {$periodLabel}";
                    $mail->Body = self::buildHtmlEmail(
                        "New invoices to verify",
                        "<p>Dear <strong>{$companyName}</strong>,</p>
                         <p>There are <strong>{$invoiceCount}</strong> new invoices for period <strong>{$periodLabel}</strong> awaiting your verification.</p>
                         <p>Please log in to the system and accept or reject the invoices before <strong>{$deadline}</strong>.</p>
                         <p>Invoices not verified by the deadline will be automatically accepted.</p>",
                        $branding
                    );
                } else {
                    $mail->Subject = "Nowe faktury do weryfikacji - {$periodLabel}";
                    $mail->Body = self::buildHtmlEmail(
                        "Nowe faktury do weryfikacji",
                        "<p>Szanowni Państwo, <strong>{$companyName}</strong>,</p>
                         <p>W systemie znajduje się <strong>{$invoiceCount}</strong> nowych faktur za okres <strong>{$periodLabel}</strong> oczekujących na weryfikację.</p>
                         <p>Prosimy o zalogowanie się i zaakceptowanie lub odrzucenie faktur do dnia <strong>{$deadline}</strong>.</p>
                         <p>Faktury niezweryfikowane do terminu zostaną automatycznie zaakceptowane.</p>",
                        $branding
                    );
                }
            }

            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send report with accepted invoices to accounting office.
     */
    public static function sendReport(
        string $reportEmail,
        string $companyName,
        string $nip,
        string $periodLabel,
        string $pdfPath,
        string $xlsPath
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($reportEmail);

            $mail->Subject = "Raport faktur - {$companyName} (NIP: {$nip}) - {$periodLabel}";
            $mail->Body = self::buildHtmlEmail(
                "Raport zaakceptowanych faktur",
                "<p>W załączniku znajduje się raport zaakceptowanych faktur dla:</p>
                 <ul>
                    <li><strong>Klient:</strong> {$companyName}</li>
                    <li><strong>NIP:</strong> {$nip}</li>
                    <li><strong>Okres:</strong> {$periodLabel}</li>
                 </ul>
                 <p>Raport zawiera zestawienie w formacie PDF oraz XLSX.</p>"
            );
            $mail->isHTML(true);

            if (file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath);
            }
            if (file_exists($xlsPath)) {
                $mail->addAttachment($xlsPath);
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Report mail send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send reminder before deadline.
     */
    public static function sendDeadlineReminder(
        string $email,
        string $companyName,
        int $pendingCount,
        string $deadline,
        string $language = 'pl',
        ?int $clientId = null
    ): bool {
        try {
            $mail = $clientId ? self::createMailerForClient($clientId) : self::createMailer();
            $mail->addAddress($email);

            $vars = [
                'company_name' => $companyName,
                'pending_count' => $pendingCount,
                'deadline' => $deadline,
                'login_url' => (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . '/login',
            ];

            $tplSubject = EmailTemplate::renderSubject('deadline_reminder', $language, $vars);
            $tplBody = EmailTemplate::renderBody('deadline_reminder', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $branding = $clientId ? self::getOfficeBrandingForClient($clientId) : null;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody, $branding);
            } else {
                $branding = $clientId ? self::getOfficeBrandingForClient($clientId) : null;
                if ($language === 'en') {
                    $mail->Subject = "Reminder: Invoice verification deadline - {$deadline}";
                    $mail->Body = self::buildHtmlEmail(
                        "Verification deadline reminder",
                        "<p>Dear <strong>{$companyName}</strong>,</p>
                         <p>You still have <strong>{$pendingCount}</strong> invoices pending verification.</p>
                         <p>The deadline is <strong>{$deadline}</strong>. Unverified invoices will be automatically accepted.</p>",
                        $branding
                    );
                } else {
                    $mail->Subject = "Przypomnienie: Termin weryfikacji faktur - {$deadline}";
                    $mail->Body = self::buildHtmlEmail(
                        "Przypomnienie o terminie weryfikacji",
                        "<p>Szanowni Państwo, <strong>{$companyName}</strong>,</p>
                         <p>Pozostało <strong>{$pendingCount}</strong> faktur oczekujących na weryfikację.</p>
                         <p>Termin weryfikacji upływa <strong>{$deadline}</strong>. Niezweryfikowane faktury zostaną automatycznie zaakceptowane.</p>",
                        $branding
                    );
                }
            }

            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Reminder mail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email.
     */
    public static function sendPasswordReset(
        string $email,
        string $name,
        string $resetUrl,
        string $language = 'pl'
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($email);

            $vars = ['name' => $name, 'reset_url' => $resetUrl];
            $tplSubject = EmailTemplate::renderSubject('password_reset', $language, $vars);
            $tplBody = EmailTemplate::renderBody('password_reset', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody);
            } else {
                if ($language === 'en') {
                    $mail->Subject = "Password reset - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Password Reset",
                        "<p>Dear <strong>{$name}</strong>,</p>
                         <p>A password reset has been requested for your account.</p>
                         <p><a href=\"{$resetUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Reset Password</a></p>
                         <p>This link is valid for 1 hour. If you didn't request this, please ignore this email.</p>
                         <p style=\"font-size:12px;color:#666;\">Link: {$resetUrl}</p>"
                    );
                } else {
                    $mail->Subject = "Reset hasła - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Reset hasła",
                        "<p>Szanowni Państwo, <strong>{$name}</strong>,</p>
                         <p>Otrzymaliśmy prośbę o zresetowanie hasła do konta w systemie.</p>
                         <p><a href=\"{$resetUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Resetuj hasło</a></p>
                         <p>Link jest ważny przez 1 godzinę. Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.</p>
                         <p style=\"font-size:12px;color:#666;\">Link: {$resetUrl}</p>"
                    );
                }
            }

            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Password reset mail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send initial credentials to bulk-imported client.
     */
    public static function sendInitialCredentials(
        string $email,
        string $companyName,
        string $nip,
        string $password,
        string $loginUrl,
        string $language = 'pl'
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($email);

            $vars = ['company_name' => $companyName, 'nip' => $nip, 'password' => $password, 'login_url' => $loginUrl];
            $tplSubject = EmailTemplate::renderSubject('initial_credentials', $language, $vars);
            $tplBody = EmailTemplate::renderBody('initial_credentials', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody);
            } else {
                if ($language === 'en') {
                    $mail->Subject = "Account created - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Your account has been created",
                        "<p>Dear <strong>{$companyName}</strong>,</p>
                         <p>An account has been created for you in the invoice verification system.</p>
                         <p><strong>Login (NIP):</strong> {$nip}<br><strong>Temporary password:</strong> {$password}</p>
                         <p>You will be required to change this password on first login.</p>
                         <p><a href=\"{$loginUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Log in</a></p>"
                    );
                } else {
                    $mail->Subject = "Konto utworzone - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Konto zostało utworzone",
                        "<p>Szanowni Państwo, <strong>{$companyName}</strong>,</p>
                         <p>W systemie weryfikacji faktur zostało utworzone konto dla Państwa firmy.</p>
                         <p><strong>Login (NIP):</strong> {$nip}<br><strong>Hasło tymczasowe:</strong> {$password}</p>
                         <p>Przy pierwszym logowaniu konieczna będzie zmiana hasła.</p>
                         <p><a href=\"{$loginUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Zaloguj się</a></p>"
                    );
                }
            }

            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Initial credentials mail error: " . $e->getMessage());
            return false;
        }
    }

    public static function sendReportMultiple(string $to, string $companyName, string $nip, string $period, array $attachmentPaths): bool
    {
        $settings = \App\Models\Setting::getAll();
        $values = [];
        foreach ($settings as $s) $values[$s['setting_key']] = $s['setting_value'];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $values['smtp_host'] ?? 'localhost';
            $mail->SMTPAuth = !empty($values['smtp_user']);
            $mail->Username = $values['smtp_user'] ?? '';
            $mail->Password = $values['smtp_pass'] ?? '';
            $mail->SMTPSecure = $values['smtp_encryption'] ?? 'tls';
            $mail->Port = (int) ($values['smtp_port'] ?? 587);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($values['company_email'] ?? 'noreply@example.com', $values['company_name'] ?? 'BiLLU');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = "Raporty faktur - {$companyName} (NIP: {$nip}) - okres {$period}";
            $mail->Body = "<h2>Raporty faktur</h2>
                <p>W załączeniu raporty z weryfikacji faktur za okres <strong>{$period}</strong>.</p>
                <p>Firma: <strong>{$companyName}</strong> (NIP: {$nip})</p>
                <p>Liczba załączników: " . count($attachmentPaths) . "</p>";

            foreach ($attachmentPaths as $path) {
                if ($path && file_exists($path)) {
                    $mail->addAttachment($path);
                }
            }

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification about expiring KSeF certificate.
     */
    public static function sendCertificateExpiryWarning(
        string $email,
        string $companyName,
        string $certType,
        string $expiryDate,
        int $daysLeft,
        string $language = 'pl'
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($email);
            $mail->isHTML(true);

            $certLabel = $certType === 'ksef_cert' ? 'KSeF' : 'kwalifikowany';

            $vars = ['company_name' => $companyName, 'cert_type' => $certLabel, 'expiry_date' => $expiryDate, 'days_left' => $daysLeft];
            $tplSubject = EmailTemplate::renderSubject('certificate_expiry', $language, $vars);
            $tplBody = EmailTemplate::renderBody('certificate_expiry', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody);
            } else {
                if ($language === 'en') {
                    $mail->Subject = "KSeF certificate expiring in {$daysLeft} days - {$companyName}";
                    $mail->Body = self::buildHtmlEmail(
                        "KSeF Certificate Expiring Soon",
                        "<p>Dear <strong>{$companyName}</strong>,</p>
                         <p>Your <strong>{$certLabel}</strong> certificate used for KSeF integration expires on <strong>{$expiryDate}</strong> ({$daysLeft} days remaining).</p>
                         <p>Please log in to the system and update your certificate to ensure uninterrupted KSeF integration.</p>
                         <p>After expiry, automatic invoice import from KSeF will stop working.</p>"
                    );
                } else {
                    $mail->Subject = "Certyfikat KSeF wygasa za {$daysLeft} dni - {$companyName}";
                    $mail->Body = self::buildHtmlEmail(
                        "Certyfikat KSeF wygasa wkrotce",
                        "<p>Szanowni Panstwo, <strong>{$companyName}</strong>,</p>
                         <p>Certyfikat <strong>{$certLabel}</strong> uzywany do integracji z KSeF wygasa <strong>{$expiryDate}</strong> (pozostalo {$daysLeft} dni).</p>
                         <p>Prosimy o zalogowanie sie do systemu i aktualizacje certyfikatu, aby zapewnic ciaglosc integracji z KSeF.</p>
                         <p>Po wygasnieciu certyfikatu automatyczny import faktur z KSeF przestanie dzialac.</p>"
                    );
                }
            }

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Certificate expiry mail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password expiry warning email (14 days before expiry).
     */
    public static function sendPasswordExpiryWarning(
        string $email,
        string $companyName,
        int $daysLeft,
        string $loginUrl,
        string $language = 'pl'
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($email);

            $vars = ['company_name' => $companyName, 'days_left' => $daysLeft, 'login_url' => $loginUrl];
            $tplSubject = EmailTemplate::renderSubject('password_expiry', $language, $vars);
            $tplBody = EmailTemplate::renderBody('password_expiry', $language, $vars);

            if ($tplSubject && $tplBody) {
                $mail->Subject = $tplSubject;
                $mail->Body = self::buildHtmlEmail($tplSubject, $tplBody);
            } else {
                if ($language === 'en') {
                    $mail->Subject = "Password expiring soon - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Password Expiry Warning",
                        "<p>Dear <strong>{$companyName}</strong>,</p>
                         <p>Your password in the BiLLU system will expire in <strong>{$daysLeft} days</strong>.</p>
                         <p>Please log in and change your password to avoid being locked out.</p>
                         <p><a href=\"{$loginUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Log in &amp; change password</a></p>
                         <p style=\"font-size:12px;color:#666;\">Password requirements: minimum 12 characters, uppercase, lowercase, digit, and special character.</p>"
                    );
                } else {
                    $mail->Subject = "Hasło wygasa wkrótce - BiLLU";
                    $mail->Body = self::buildHtmlEmail(
                        "Ostrzeżenie o wygaśnięciu hasła",
                        "<p>Szanowni Państwo, <strong>{$companyName}</strong>,</p>
                         <p>Hasło do systemu BiLLU wygasa za <strong>{$daysLeft} dni</strong>.</p>
                         <p>Prosimy o zalogowanie się i zmianę hasła, aby uniknąć zablokowania dostępu.</p>
                         <p><a href=\"{$loginUrl}\" style=\"display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;\">Zaloguj się i zmień hasło</a></p>
                         <p style=\"font-size:12px;color:#666;\">Wymagania: minimum 12 znaków, wielka litera, mała litera, cyfra i znak specjalny.</p>"
                    );
                }
            }

            $mail->isHTML(true);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Password expiry warning mail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a simple HTML email (used by messaging/tasks).
     */
    public static function createSimpleMail(string $to, string $subject, string $htmlBody, ?int $clientId = null): bool
    {
        try {
            $mail = $clientId ? self::createMailerForClient($clientId) : self::createMailer();
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $branding = $clientId ? self::getOfficeBrandingForClient($clientId) : null;
            $mail->Body = self::buildHtmlEmail($subject, $htmlBody, $branding);
            $mail->AltBody = strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Simple mail error to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get client-specific branding for invoice emails.
     * Falls back to office branding if client has no custom settings.
     */
    private static function getClientBranding(int $clientId): ?array
    {
        $tpl = \App\Models\ClientInvoiceEmailTemplate::findByClientId($clientId);
        if ($tpl && (!empty($tpl['header_color']) || !empty($tpl['logo_path']) || !empty($tpl['footer_text']))) {
            return [
                'header_color' => $tpl['header_color'] ?? '#008F8F',
                'logo_url' => !empty($tpl['logo_in_emails']) && !empty($tpl['logo_path']) ? $tpl['logo_path'] : null,
                'footer_text' => $tpl['footer_text'] ?? null,
            ];
        }
        return self::getOfficeBrandingForClient($clientId);
    }

    /**
     * Get office branding for a given client (for email styling).
     */
    private static function getOfficeBrandingForClient(int $clientId): ?array
    {
        $client = \App\Models\Client::findById($clientId);
        if (!$client || empty($client['office_id'])) return null;

        $settings = OfficeEmailSettings::findByOfficeId((int) $client['office_id']);
        if (!$settings) return null;

        // Get office logo if enabled
        $logoUrl = null;
        if (!empty($settings['logo_in_emails'])) {
            $office = \App\Models\Office::findById((int) $client['office_id']);
            if ($office && !empty($office['logo_path'])) {
                $logoUrl = $office['logo_path'];
            }
        }

        return [
            'header_color' => $settings['header_color'] ?? '#008F8F',
            'logo_url' => $logoUrl,
            'footer_text' => $settings['footer_text'] ?? null,
        ];
    }

    /**
     * Build styled HTML email with optional office branding.
     */
    private static function buildHtmlEmail(string $title, string $body, ?array $branding = null): string
    {
        $headerColor = $branding['header_color'] ?? '#008F8F';
        $footerText = $branding['footer_text'] ?? 'BiLLU Financial Solutions - System weryfikacji faktur';
        $logoHtml = '';
        if (!empty($branding['logo_url'])) {
            $logoUrl = htmlspecialchars($branding['logo_url']);
            $logoHtml = "<img src=\"{$logoUrl}\" alt=\"Logo\" style=\"max-height:40px; max-width:180px; margin-bottom:8px; display:block;\">";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
            <div style="background: {$headerColor}; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                {$logoHtml}
                <h2 style="margin: 0;">{$title}</h2>
            </div>
            <div style="padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
                {$body}
            </div>
            <div style="padding: 10px; text-align: center; color: #9ca3af; font-size: 12px;">
                {$footerText}
            </div>
        </body>
        </html>
        HTML;
    }
}
