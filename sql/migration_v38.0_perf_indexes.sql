-- Migration v38.0: Performance indexes for reports, F2 duplicates, audit log, sessions
-- Composite indexes targeted at common WHERE/GROUP BY patterns identified in reports
-- and ImportService duplicate-checks. All idempotent via IF NOT EXISTS-like pattern
-- (executed inside a stored procedure for portability across MySQL/MariaDB).

DELIMITER $$

DROP PROCEDURE IF EXISTS billu_add_index $$
CREATE PROCEDURE billu_add_index(
    IN p_table  VARCHAR(64),
    IN p_index  VARCHAR(64),
    IN p_cols   VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
          AND index_name   = p_index
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', p_index, ' ON ', p_table, ' (', p_cols, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- invoices (purchase / cost)
CALL billu_add_index('invoices',         'idx_inv_client_status_created', 'client_id, status, created_at');
CALL billu_add_index('invoices',         'idx_inv_client_issue_status',   'client_id, issue_date, status');
CALL billu_add_index('invoices',         'idx_inv_batch_status',          'batch_id, status');
CALL billu_add_index('invoices',         'idx_inv_cost_center',           'cost_center_id');
CALL billu_add_index('invoices',         'idx_inv_invnum_seller',         'invoice_number, seller_nip');

-- issued_invoices (sales)
CALL billu_add_index('issued_invoices',  'idx_iss_client_created',        'client_id, created_at');
CALL billu_add_index('issued_invoices',  'idx_iss_client_issue_status',   'client_id, issue_date, status');
CALL billu_add_index('issued_invoices',  'idx_iss_client_invnum',         'client_id, invoice_number');
CALL billu_add_index('issued_invoices',  'idx_iss_contractor',            'contractor_id');
CALL billu_add_index('issued_invoices',  'idx_iss_buyer_nip',             'client_id, buyer_nip');

-- audit_log (search + paginated listing + activity stats)
CALL billu_add_index('audit_log',        'idx_audit_action_created',      'action, created_at');
CALL billu_add_index('audit_log',        'idx_audit_user_created',        'user_type, user_id, created_at');

-- duplicate_candidates (target lookups in F2)
CALL billu_add_index('duplicate_candidates', 'idx_dup_target',            'duplicate_of_id');

-- user_sessions (sessions stay in MySQL; speed up validation + cleanup)
CALL billu_add_index('user_sessions',    'idx_sess_user_active',          'user_type, user_id, last_activity');
CALL billu_add_index('user_sessions',    'idx_sess_expires',              'last_activity');

DROP PROCEDURE IF EXISTS billu_add_index;
