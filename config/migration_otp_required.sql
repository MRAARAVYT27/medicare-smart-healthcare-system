-- =====================================================
--  MIGRATION: Add otp_required column to users table
--  Run this ONCE in phpMyAdmin if your database already
--  exists from before this update.
--  If you re-import the full schema.sql, you do NOT need this.
-- =====================================================

USE healthcare_db;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS otp_required TINYINT(1) DEFAULT 1
  COMMENT 'If 0, user can login without OTP verification'
  AFTER is_active;

-- Optional: turn OFF OTP for the admin account so you can log in easily.
-- Replace the email below with your admin email.
-- UPDATE users SET otp_required = 0 WHERE email = 'your-admin-email@example.com';
