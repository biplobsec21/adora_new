-- Upgrade an existing EOD installation to audit adjustment additions.
ALTER TABLE `db_audit_logs`
  MODIFY `action` enum('INSERT','UPDATE','DELETE') NOT NULL;
