-- Migration v9.1: Allow client-initiated KSeF imports
ALTER TABLE invoice_batches MODIFY COLUMN imported_by_type ENUM('admin', 'office', 'client') NOT NULL DEFAULT 'admin';
