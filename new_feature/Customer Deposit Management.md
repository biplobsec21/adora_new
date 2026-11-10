# Customer Deposit Management

Customer Deposit Management records money held on behalf of a customer. Deposits and withdrawals are immutable transactions; a withdrawal is allowed only up to the customer's available deposit balance. Correct mistakes with a compensating transaction rather than editing history.

The module is available under Customers and uses `customer_deposit_view`, `customer_deposit_add`, and `customer_deposit_withdraw` permissions. Transactions include the customer, type, amount, date, payment method, note, and creator, and are recorded in the audit log.

Deposits appear as credits and withdrawals as debits in the existing Customer Ledger. The ledger balance due reflects deposits as customer credit and withdrawals as a reduction of that credit. Customer deposits are not applied automatically to sales invoices or DueFlow balances.

EOD displays deposits as cash inflow and withdrawals as cash outflow, includes transaction detail lists, and incorporates both amounts into final cash in hand. Backdated deposit transactions are included in pending-date checks and saved-closing mismatch/reconciliation.

For an existing installation, run `db_structure/customer_deposit_management_schema_upgrade.sql`. Fresh installations should include `db_structure/customer_deposit_schema.sql` and the updated `db_structure/eod_schema.sql`.
