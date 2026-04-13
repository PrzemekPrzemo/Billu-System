-- Migration v2.3: Office login by email + unique email constraint
-- Safe to run multiple times

-- Add unique index on email for offices (login by email)
-- Will fail silently if already exists
ALTER TABLE offices ADD UNIQUE INDEX idx_offices_email_unique (email);
