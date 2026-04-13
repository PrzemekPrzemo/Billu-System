-- Migration: Mobile App Access Control
-- Adds per-client and per-office flags to control Android app login.
-- DEFAULT 1 ensures all existing records retain full access (non-breaking).
-- Run after: migration_api_jwt_tokens.sql

ALTER TABLE clients
    ADD COLUMN mobile_app_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = client cannot log in to the Android app';

ALTER TABLE offices
    ADD COLUMN mobile_app_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = all clients of this office cannot log in to the Android app';
