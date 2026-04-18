-- Migration v40.0: Extend messages table for HR context
-- Allows client-office communication about HR topics (employees, contracts, payroll)

ALTER TABLE messages
    ADD COLUMN hr_employee_id INT UNSIGNED DEFAULT NULL AFTER batch_id,
    ADD COLUMN hr_context ENUM('employee','contract','payroll','leave','general') DEFAULT NULL AFTER hr_employee_id,
    ADD INDEX idx_hr_context (hr_context, client_id);
