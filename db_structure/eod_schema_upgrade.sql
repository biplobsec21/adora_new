-- Upgrade an existing EOD installation to support late-entry audit tracking.
ALTER TABLE `db_audit_logs`
  MODIFY `action` enum('INSERT','UPDATE','DELETE','LATE_ENTRY') NOT NULL;

ALTER TABLE `db_daily_closing`
  ADD COLUMN `late_entry_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `final_cash_in_hand`;
