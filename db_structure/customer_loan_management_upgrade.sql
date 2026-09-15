-- Upgrade an existing installation for customer loan audit history.
-- Run this once against the active application database.

ALTER TABLE `db_audit_logs`
  ADD COLUMN IF NOT EXISTS `new_value` longtext DEFAULT NULL AFTER `old_value`;