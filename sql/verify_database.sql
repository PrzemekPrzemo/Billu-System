-- =============================================
-- BiLLU: WERYFIKACJA KOMPLETNOŚCI BAZY DANYCH
-- Wklej w phpMyAdmin → zakładka SQL → Wykonaj
-- Sprawdza: tabele, kolumny, indeksy
-- =============================================

-- ══════════════════════════════════════════════
-- 1. SPRAWDZENIE WYMAGANYCH TABEL
-- ══════════════════════════════════════════════
SELECT '=== BRAKUJĄCE TABELE ===' AS '';

SELECT t.table_name AS `Brakująca tabela`
FROM (
    SELECT 'users' AS table_name UNION ALL
    SELECT 'offices' UNION ALL
    SELECT 'office_employees' UNION ALL
    SELECT 'office_employee_clients' UNION ALL
    SELECT 'clients' UNION ALL
    SELECT 'client_cost_centers' UNION ALL
    SELECT 'client_internal_notes' UNION ALL
    SELECT 'client_monthly_status' UNION ALL
    SELECT 'invoice_batches' UNION ALL
    SELECT 'invoices' UNION ALL
    SELECT 'issued_invoices' UNION ALL
    SELECT 'invoice_comments' UNION ALL
    SELECT 'reports' UNION ALL
    SELECT 'duplicate_candidates' UNION ALL
    SELECT 'client_ksef_configs' UNION ALL
    SELECT 'ksef_operations_log' UNION ALL
    SELECT 'company_profiles' UNION ALL
    SELECT 'company_bank_accounts' UNION ALL
    SELECT 'company_services' UNION ALL
    SELECT 'contractors' UNION ALL
    SELECT 'messages' UNION ALL
    SELECT 'message_notification_prefs' UNION ALL
    SELECT 'notifications' UNION ALL
    SELECT 'client_tasks' UNION ALL
    SELECT 'client_tax_config' UNION ALL
    SELECT 'tax_calendar_alerts' UNION ALL
    SELECT 'tax_payments' UNION ALL
    SELECT 'tax_custom_events' UNION ALL
    SELECT 'tax_simulations' UNION ALL
    SELECT 'email_templates' UNION ALL
    SELECT 'office_email_settings' UNION ALL
    SELECT 'client_smtp_configs' UNION ALL
    SELECT 'office_smtp_configs' UNION ALL
    SELECT 'client_invoice_email_templates' UNION ALL
    SELECT 'export_templates' UNION ALL
    SELECT 'import_templates' UNION ALL
    SELECT 'scheduled_exports' UNION ALL
    SELECT 'webhooks' UNION ALL
    SELECT 'webhook_logs' UNION ALL
    SELECT 'api_tokens' UNION ALL
    SELECT 'client_fcm_tokens' UNION ALL
    SELECT 'password_resets' UNION ALL
    SELECT 'user_sessions' UNION ALL
    SELECT 'login_history' UNION ALL
    SELECT 'settings' UNION ALL
    SELECT 'audit_log' UNION ALL
    SELECT 'schema_migrations'
) t
LEFT JOIN information_schema.TABLES ist
    ON ist.TABLE_NAME = t.table_name AND ist.TABLE_SCHEMA = DATABASE()
WHERE ist.TABLE_NAME IS NULL;

-- ══════════════════════════════════════════════
-- 2. SPRAWDZENIE WYMAGANYCH KOLUMN
-- ══════════════════════════════════════════════
SELECT '=== BRAKUJĄCE KOLUMNY ===' AS '';

SELECT c.tbl AS `Tabela`, c.col AS `Brakująca kolumna`
FROM (
    -- clients
    SELECT 'clients' AS tbl, 'has_cost_centers' AS col UNION ALL
    SELECT 'clients', 'ksef_enabled' UNION ALL
    SELECT 'clients', 'two_factor_secret' UNION ALL
    SELECT 'clients', 'two_factor_enabled' UNION ALL
    SELECT 'clients', 'two_factor_recovery_codes' UNION ALL
    SELECT 'clients', 'is_demo' UNION ALL
    SELECT 'clients', 'can_send_invoices' UNION ALL
    SELECT 'clients', 'mobile_app_enabled' UNION ALL
    SELECT 'clients', 'language' UNION ALL
    SELECT 'clients', 'privacy_accepted' UNION ALL
    SELECT 'clients', 'force_password_change' UNION ALL

    -- offices
    SELECT 'offices', 'two_factor_secret' UNION ALL
    SELECT 'offices', 'two_factor_enabled' UNION ALL
    SELECT 'offices', 'two_factor_recovery_codes' UNION ALL
    SELECT 'offices', 'logo_path' UNION ALL
    SELECT 'offices', 'is_demo' UNION ALL
    SELECT 'offices', 'max_employees' UNION ALL
    SELECT 'offices', 'max_clients' UNION ALL
    SELECT 'offices', 'mobile_app_enabled' UNION ALL
    SELECT 'offices', 'language' UNION ALL

    -- users
    SELECT 'users', 'two_factor_secret' UNION ALL
    SELECT 'users', 'two_factor_enabled' UNION ALL
    SELECT 'users', 'two_factor_recovery_codes' UNION ALL

    -- office_employees
    SELECT 'office_employees', 'name' UNION ALL
    SELECT 'office_employees', 'email' UNION ALL
    SELECT 'office_employees', 'password_hash' UNION ALL
    SELECT 'office_employees', 'password_changed_at' UNION ALL
    SELECT 'office_employees', 'force_password_change' UNION ALL
    SELECT 'office_employees', 'last_login_at' UNION ALL

    -- reports
    SELECT 'reports', 'cost_center_name' UNION ALL
    SELECT 'reports', 'report_format' UNION ALL
    SELECT 'reports', 'xml_path' UNION ALL

    -- invoices (purchase)
    SELECT 'invoices', 'cost_center_id' UNION ALL
    SELECT 'invoices', 'is_paid' UNION ALL
    SELECT 'invoices', 'payment_due_date' UNION ALL
    SELECT 'invoices', 'payment_method_detected' UNION ALL
    SELECT 'invoices', 'whitelist_failed' UNION ALL
    SELECT 'invoices', 'ksef_xml' UNION ALL
    SELECT 'invoices', 'invoice_type' UNION ALL
    SELECT 'invoices', 'corrected_invoice_number' UNION ALL
    SELECT 'invoices', 'corrected_invoice_date' UNION ALL
    SELECT 'invoices', 'corrected_ksef_number' UNION ALL
    SELECT 'invoices', 'correction_reason' UNION ALL
    SELECT 'invoices', 'exchange_rate' UNION ALL
    SELECT 'invoices', 'exchange_rate_date' UNION ALL
    SELECT 'invoices', 'exchange_rate_table' UNION ALL

    -- issued_invoices (sales)
    SELECT 'issued_invoices', 'ksef_upo_path' UNION ALL
    SELECT 'issued_invoices', 'ksef_session_ref' UNION ALL
    SELECT 'issued_invoices', 'ksef_element_ref' UNION ALL
    SELECT 'issued_invoices', 'duplicate_acknowledged' UNION ALL
    SELECT 'issued_invoices', 'email_sent_at' UNION ALL
    SELECT 'issued_invoices', 'email_sent_to' UNION ALL
    SELECT 'issued_invoices', 'exchange_rate' UNION ALL
    SELECT 'issued_invoices', 'exchange_rate_date' UNION ALL
    SELECT 'issued_invoices', 'exchange_rate_table' UNION ALL
    SELECT 'issued_invoices', 'original_line_items' UNION ALL
    SELECT 'issued_invoices', 'original_net_amount' UNION ALL
    SELECT 'issued_invoices', 'original_vat_amount' UNION ALL
    SELECT 'issued_invoices', 'original_gross_amount' UNION ALL
    SELECT 'issued_invoices', 'correction_type' UNION ALL
    SELECT 'issued_invoices', 'is_split_payment' UNION ALL
    SELECT 'issued_invoices', 'payer_data' UNION ALL
    SELECT 'issued_invoices', 'vat_amount_pln' UNION ALL
    SELECT 'issued_invoices', 'net_amount_pln' UNION ALL

    -- client_ksef_configs
    SELECT 'client_ksef_configs', 'cert_ksef_private_key_encrypted' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_pem' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_serial_number' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_name' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_valid_from' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_valid_to' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_status' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_enrollment_ref' UNION ALL
    SELECT 'client_ksef_configs', 'cert_ksef_type' UNION ALL
    SELECT 'client_ksef_configs', 'ksef_connection_status' UNION ALL
    SELECT 'client_ksef_configs', 'ksef_connection_checked_at' UNION ALL
    SELECT 'client_ksef_configs', 'ksef_connection_error' UNION ALL
    SELECT 'client_ksef_configs', 'upo_enabled' UNION ALL

    -- company_profiles
    SELECT 'company_profiles', 'next_correction_number' UNION ALL
    SELECT 'company_profiles', 'next_duplicate_number' UNION ALL

    -- contractors
    SELECT 'contractors', 'logo_path' UNION ALL
    SELECT 'contractors', 'short_name' UNION ALL

    -- messages
    SELECT 'messages', 'attachment_path' UNION ALL
    SELECT 'messages', 'attachment_name' UNION ALL

    -- client_tasks
    SELECT 'client_tasks', 'attachment_path' UNION ALL
    SELECT 'client_tasks', 'attachment_name' UNION ALL

    -- client_invoice_email_templates (v20.0 + v20.1)
    SELECT 'client_invoice_email_templates', 'subject_template_pl' UNION ALL
    SELECT 'client_invoice_email_templates', 'body_template_pl' UNION ALL
    SELECT 'client_invoice_email_templates', 'subject_template_en' UNION ALL
    SELECT 'client_invoice_email_templates', 'body_template_en' UNION ALL
    SELECT 'client_invoice_email_templates', 'header_color' UNION ALL
    SELECT 'client_invoice_email_templates', 'logo_in_emails' UNION ALL
    SELECT 'client_invoice_email_templates', 'logo_path' UNION ALL
    SELECT 'client_invoice_email_templates', 'footer_text' UNION ALL

    -- tax_custom_events (v22.2)
    SELECT 'tax_custom_events', 'employee_id'

) c
LEFT JOIN information_schema.COLUMNS isc
    ON isc.TABLE_SCHEMA = DATABASE()
    AND isc.TABLE_NAME = c.tbl
    AND isc.COLUMN_NAME = c.col
WHERE isc.COLUMN_NAME IS NULL
  AND EXISTS (
      SELECT 1 FROM information_schema.TABLES ist
      WHERE ist.TABLE_SCHEMA = DATABASE() AND ist.TABLE_NAME = c.tbl
  )
ORDER BY c.tbl, c.col;

-- ══════════════════════════════════════════════
-- 3. SPRAWDZENIE MIGRACJI
-- ══════════════════════════════════════════════
SELECT '=== STATUS MIGRACJI ===' AS '';

SELECT m.filename AS `Wymagana migracja`,
       CASE WHEN sm.filename IS NOT NULL THEN 'OK' ELSE 'BRAK' END AS `Status`
FROM (
    SELECT 'migration_v17.0_exchange_rate.sql' AS filename UNION ALL
    SELECT 'migration_v18.0_client_notes.sql' UNION ALL
    SELECT 'migration_v18.1_client_workflow.sql' UNION ALL
    SELECT 'migration_v19.0_vat_pln.sql' UNION ALL
    SELECT 'migration_v19.1_ksef_batch.sql' UNION ALL
    SELECT 'migration_v20.0_client_email_branding.sql' UNION ALL
    SELECT 'migration_v20.1_bilingual_email_templates.sql' UNION ALL
    SELECT 'migration_v21.0_currency_improvements.sql' UNION ALL
    SELECT 'migration_v22.0_tax_custom_events.sql' UNION ALL
    SELECT 'migration_v22.1_upo_enabled.sql' UNION ALL
    SELECT 'migration_v22.2_event_employee.sql' UNION ALL
    SELECT 'migration_v23.0_tax_simulations.sql'
) m
LEFT JOIN schema_migrations sm ON sm.filename = m.filename
ORDER BY m.filename;

-- ══════════════════════════════════════════════
-- 4. PODSUMOWANIE
-- ══════════════════════════════════════════════
SELECT '=== PODSUMOWANIE ===' AS '';

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()) AS `Tabel w bazie`,
    47 AS `Tabel wymaganych`,
    (SELECT COUNT(*) FROM schema_migrations) AS `Migracji zastosowanych`;
