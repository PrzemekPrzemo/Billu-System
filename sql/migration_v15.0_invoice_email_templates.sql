-- Migration v15.0: Invoice email sending, client SMTP, email templates, office email branding
-- Run this migration after v14.0

-- 1. Client SMTP configuration (per-client custom SMTP for sending invoices)
CREATE TABLE IF NOT EXISTS client_smtp_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    smtp_user VARCHAR(255) NOT NULL DEFAULT '',
    smtp_pass_encrypted TEXT DEFAULT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Flag: can client send invoices via email
ALTER TABLE clients ADD COLUMN can_send_invoices TINYINT(1) NOT NULL DEFAULT 0;

-- 3. Track invoice email sending
ALTER TABLE issued_invoices ADD COLUMN email_sent_at DATETIME DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN email_sent_to VARCHAR(255) DEFAULT NULL;

-- 4. System email templates (editable by admin)
CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    subject_pl TEXT NOT NULL,
    body_pl TEXT NOT NULL,
    subject_en TEXT NOT NULL,
    body_en TEXT NOT NULL,
    available_placeholders TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Office email branding settings
CREATE TABLE IF NOT EXISTS office_email_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL UNIQUE,
    header_color VARCHAR(7) DEFAULT '#0B7285',
    logo_in_emails TINYINT(1) NOT NULL DEFAULT 1,
    footer_text TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Client invoice email template (default subject/body for sending invoices)
CREATE TABLE IF NOT EXISTS client_invoice_email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    subject_template VARCHAR(500) NOT NULL DEFAULT 'Faktura {{invoice_number}}',
    body_template TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Seed default system email templates
INSERT INTO email_templates (template_key, name, subject_pl, body_pl, subject_en, body_en, available_placeholders) VALUES
('new_invoices_notification', 'Powiadomienie o nowych fakturach',
 'Nowe faktury do weryfikacji - {{period}}',
 '<p>Szanowni Państwo <strong>{{company_name}}</strong>,</p><p>W systemie dostępnych jest <strong>{{invoice_count}}</strong> nowych faktur za okres <strong>{{period}}</strong> do weryfikacji.</p><p>Prosimy o zalogowanie się i zaakceptowanie lub odrzucenie faktur przed <strong>{{deadline}}</strong>.</p>',
 'New invoices to verify - {{period}}',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>There are <strong>{{invoice_count}}</strong> new invoices for period <strong>{{period}}</strong> awaiting your verification.</p><p>Please log in and accept or reject invoices before <strong>{{deadline}}</strong>.</p>',
 'company_name,invoice_count,period,deadline,login_url'),

('deadline_reminder', 'Przypomnienie o terminie',
 'Przypomnienie: termin weryfikacji faktur - {{deadline}}',
 '<p>Szanowni Państwo <strong>{{company_name}}</strong>,</p><p>Pozostało <strong>{{pending_count}}</strong> faktur oczekujących na weryfikację.</p><p>Termin: <strong>{{deadline}}</strong>. Niezweryfikowane faktury zostaną automatycznie zaakceptowane.</p>',
 'Reminder: Invoice verification deadline - {{deadline}}',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>You still have <strong>{{pending_count}}</strong> invoices pending verification.</p><p>The deadline is <strong>{{deadline}}</strong>. Unverified invoices will be automatically accepted.</p>',
 'company_name,pending_count,deadline,login_url'),

('password_reset', 'Reset hasła',
 'Reset hasła - FaktuPilot',
 '<p>Witaj <strong>{{name}}</strong>,</p><p>Kliknij poniższy link, aby zresetować hasło:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Zresetuj hasło</a></p><p>Link wygasa za 1 godzinę.</p>',
 'Password Reset - FaktuPilot',
 '<p>Hello <strong>{{name}}</strong>,</p><p>Click the link below to reset your password:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Reset Password</a></p><p>This link expires in 1 hour.</p>',
 'name,reset_url'),

('initial_credentials', 'Dane logowania',
 'Dane logowania do systemu FaktuPilot',
 '<p>Szanowni Państwo <strong>{{company_name}}</strong>,</p><p>Utworzono konto w systemie FaktuPilot.</p><p>NIP: <strong>{{nip}}</strong><br>Hasło: <strong>{{password}}</strong></p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Zaloguj się</a></p>',
 'Your FaktuPilot account credentials',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>An account has been created for you in FaktuPilot.</p><p>NIP: <strong>{{nip}}</strong><br>Password: <strong>{{password}}</strong></p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Log in</a></p>',
 'company_name,nip,password,login_url'),

('certificate_expiry', 'Wygasanie certyfikatu KSeF',
 'Ostrzeżenie: certyfikat KSeF wygasa za {{days_left}} dni',
 '<p>Szanowni Państwo <strong>{{company_name}}</strong>,</p><p>Certyfikat <strong>{{cert_type}}</strong> wygasa <strong>{{expiry_date}}</strong> (za {{days_left}} dni).</p><p>Prosimy o odnowienie certyfikatu w ustawieniach KSeF.</p>',
 'Warning: KSeF certificate expires in {{days_left}} days',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>Your <strong>{{cert_type}}</strong> certificate expires on <strong>{{expiry_date}}</strong> (in {{days_left}} days).</p><p>Please renew the certificate in your KSeF settings.</p>',
 'company_name,cert_type,expiry_date,days_left'),

('password_expiry', 'Wygasanie hasła',
 'Twoje hasło wygasa za {{days_left}} dni',
 '<p>Witaj <strong>{{company_name}}</strong>,</p><p>Twoje hasło wygaśnie za <strong>{{days_left}}</strong> dni.</p><p>Zmień hasło, aby uniknąć problemów z logowaniem.</p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Zmień hasło</a></p>',
 'Your password expires in {{days_left}} days',
 '<p>Hello <strong>{{company_name}}</strong>,</p><p>Your password will expire in <strong>{{days_left}}</strong> days.</p><p>Please change your password to avoid login issues.</p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#0B7285;color:white;text-decoration:none;border-radius:6px;">Change Password</a></p>',
 'company_name,days_left,login_url');
