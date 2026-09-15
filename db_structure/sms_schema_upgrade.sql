-- Upgrade existing SMS settings with the Sales Invoice trigger option.

ALTER TABLE `db_sms_settings`
  ADD COLUMN IF NOT EXISTS `sales_invoice_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `enabled`;