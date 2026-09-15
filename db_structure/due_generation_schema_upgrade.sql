-- Upgrade existing DueFlow installations to store the loan component per customer.

ALTER TABLE `db_due_generation_items`
  ADD COLUMN IF NOT EXISTS `loan_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00 AFTER `previous_outstanding_amount`;