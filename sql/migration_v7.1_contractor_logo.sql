-- Migration: Add logo_path column to contractors table
ALTER TABLE contractors ADD COLUMN logo_path VARCHAR(500) DEFAULT NULL AFTER notes;
