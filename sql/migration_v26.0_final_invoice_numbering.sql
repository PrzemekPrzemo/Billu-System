-- Migration v26.0: Add final invoice (FV_KON) numbering counter
-- Date: 2026-04-12

-- Final invoice numbering counter (used by CompanyProfile::getTypeConfig for FV_KON)
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS next_final_number INT NOT NULL DEFAULT 1;
