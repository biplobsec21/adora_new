-- Upgrade an existing EOD installation to support late-entry audit tracking and cash-flow categories.
-- ALTER TABLE `db_audit_logs`
--   MODIFY `action` enum('INSERT','UPDATE','DELETE','LATE_ENTRY') NOT NULL;

-- ALTER TABLE `db_daily_closing`
--   ADD COLUMN `late_entry_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `final_cash_in_hand`;

ALTER TABLE `db_daily_closing`
  ADD COLUMN `total_purchase_paid` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_cash_collected`,
  ADD COLUMN `total_loan_given` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_purchase_paid`,
  ADD COLUMN `total_loan_repayments_received` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_loan_given`;

ALTER TABLE `db_daily_closing`
  ADD COLUMN `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN `created_time` VARCHAR(50) NULL AFTER `created_date`;

ALTER TABLE `db_cash_adjustments`
  ADD COLUMN `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN `created_time` VARCHAR(50) NULL AFTER `created_date`;

ALTER TABLE `db_audit_logs`
  ADD COLUMN `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN `created_time` VARCHAR(50) NULL AFTER `created_date`;
