-- Upgrade existing SMS installations for sales invoice notifications.

ALTER TABLE `db_sms_settings`
  ADD COLUMN IF NOT EXISTS `sales_invoice_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `enabled`;

ALTER TABLE `db_sms_templates`
  MODIFY `event_key` enum('sales_invoice','due_generation','cost_cutting') NOT NULL;

INSERT INTO `db_sms_templates` (`event_key`, `template_name`, `message_body`)
VALUES ('sales_invoice', 'Sales Invoice', 'Hello {customer_name}, your invoice {sales_code} for {sales_amount} has been created. Paid: {paid_amount}. Invoice due: {invoice_due}. Thank you, {site_name}.')
ON DUPLICATE KEY UPDATE `event_key` = `event_key`;

UPDATE `db_sms_templates`
SET `message_body` = 'Hello {customer_name}, your invoice {sales_code} for {sales_amount} has been created. Paid: {paid_amount}. Invoice due: {invoice_due}. Thank you, {site_name}.'
WHERE `event_key` = 'sales_invoice'
  AND `message_body` = 'Invoice {customer_number} for {total_due}. Current due: {new_due}. Ledger: {ledger_url}';