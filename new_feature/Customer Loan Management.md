# Customer Loan Management

## 1. Module Overview

This module is for customer cash lending where the cashier or manager gives money to a customer whenever needed, and the payment is later recovered from the customer.

This is not a traditional bank-style loan module with complex amortization rules. It is a customer finance feature focused on tracking money given to customers and money repaid by them.

The module should be implemented as a separate financial module, but it must connect with the existing customer master and customer profile. The existing Customer Ledger should remain dedicated to the current sales/inventory/customer activity and should not be overloaded with customer loan entries.

A separate Customer Loan Ledger / Customer Loan Statement should be introduced for loan-specific transactions, outstanding balances, and repayment history.

---

## 2. Business Goal

The system should allow the business to:

- record money given to a customer
- record repayments received from the customer
- track the outstanding balance for each customer
- show customer loan history and repayment history
- generate reports for outstanding customer loans
- maintain audit records for all customer loan transactions

---

## 3. Core Business Rules

### 3.1 Loan entry
- A cashier or manager can create a customer loan entry.
- The entry must include the customer, amount, date, and note/reason.
- The system should store who created the entry and from which IP/device.

### 3.2 Repayment entry
- A repayment can be recorded against an existing loan.
- Repayment reduces the customer’s outstanding balance.
- Repayment should be tracked separately from normal sales payment entries.

### 3.3 Outstanding balance
- Outstanding balance is calculated as:
  - Total loan amount given
  - minus total repayments received
  - minus any approved adjustments

### 3.4 Separate calculations and separate ledger
- Sales payment calculations must remain separate from customer loan calculations.
- Customer loan balance must not be mixed with sales due or sales payment totals.
- The existing Customer Ledger should continue to show current sales/inventory/customer financial history only.
- Customer loan activity must be displayed in a separate Customer Loan Ledger / Customer Loan Statement.

### 3.5 Separate impact on due generation
- Customer loan entries must not be added into invoice due generation logic.
- Customer loan outstanding should be shown as a separate customer financial summary, not as part of the invoice due engine.

### 3.5 Audit trail
- Every loan entry, repayment, and adjustment must be logged.
- The system should capture who made the change, when, and from which IP.

---

## 4. Suggested Module Scope

### Main screens
1. Customer Loan List
2. Add Customer Loan
3. Record Customer Loan Repayment
4. Customer Loan Statement
5. Outstanding Customer Loan Report
6. Customer Loan Ledger / Customer Statement tab in the customer profile

### Optional future screens
- Loan approval flow
- Edit/delete loan payment history
- Loan reminder / overdue list
- Customer loan aging report

---

## 5. Suggested Database Structure

### Table: `db_customer_loans`
Stores each loan record given to a customer.

Columns:
- `id` - primary key
- `customer_id` - customer who received the amount
- `loan_amount` - total amount given
- `loan_date` - date the loan was given
- `loan_status` - enum: `Open`, `Closed`, `Cancelled`
- `note` - optional reason/details
- `approved_by` - who approved or entered the loan
- `created_by` - username who created the record
- `system_ip` - IP address
- `created_at` - timestamp

### Table: `db_customer_loan_transactions`
Stores every financial movement related to the loan.

Columns:
- `id` - primary key
- `loan_id` - linked customer loan record
- `customer_id` - customer record
- `transaction_type` - enum: `Loan Given`, `Repayment`, `Adjustment`
- `amount` - transaction amount
- `transaction_date` - date of the transaction
- `payment_method` - optional cash, bank, mobile, etc.
- `note` - optional note
- `created_by` - username
- `system_ip` - IP address
- `created_at` - timestamp

### Table: `db_audit_logs`
Existing audit table can be reused for loan-related changes.

Recommended audit action values:
- `INSERT`
- `UPDATE`
- `DELETE`
- `LOAN_ENTRY`
- `LOAN_REPAYMENT`
- `LOAN_ADJUSTMENT`

---

## 6. Suggested Workflow

### Loan given
1. Choose customer
2. Enter loan amount
3. Enter date and note
4. Save
5. System creates a loan record
6. System creates a loan transaction record of type `Loan Given`

### Repayment received
1. Choose customer loan
2. Enter repayment amount
3. Enter date and payment method
4. Save
5. System creates a repayment transaction
6. System updates the outstanding balance in calculations

### Adjustment
1. Optional manual adjustment to correct a mismatch
2. Stored separately as `Adjustment`
3. Logged in audit history

---

## 7. Impact on Existing System

### Existing modules affected
- Customer profile
- Customer ledger/reporting (existing sales/inventory/customer financial history remains unchanged)
- Cash collection reporting
- Audit logs

### New module components to add
- Customer Loan Ledger
- Customer Loan Statement
- Outstanding Customer Loan Report
- Customer Loan repayment history

### Existing modules not affected
- Inventory stock data
- Purchase module
- Sales payment module
- Sales due calculations
- Existing Customer Ledger data structure

### New module-specific additions
- Customer Loan Ledger
- Customer Loan Statement
- Outstanding Customer Loan Report
- Customer Loan repayment history

### Important rule
Customer loan records must not be added into `db_salespayments` or treated as sales payment receipts.

---

## 8. Recommended Reports

1. Customer Loan List
   - customer name
   - loan amount
   - outstanding balance
   - status

2. Customer Loan Statement
   - loan date
   - amount given
   - repayments
   - outstanding

3. Outstanding Customer Loan Report
   - all customers with unpaid balances
   - due amount
   - loan date

4. Daily customer loan summary
   - total amount given daily
   - total repaid daily
   - net customer lending summary

---

## 9. Suggested Naming in UI

Use customer-facing labels such as:

- Customer Loan Management
- Customer Loan
- Loan Amount
- Loan Date
- Repayment
- Outstanding Balance
- Loan Statement

This keeps the feature understandable for the client even though the business process is simpler than a traditional loan product.

---

## 10. Recommended Implementation Order

1. Create SQL tables for customer loans and loan transactions
2. Build customer loan list and add form
3. Build repayment entry
4. Add outstanding balance calculations
5. Add customer loan statement/report
6. Add audit logs and permissions

---

## 11. Final Recommendation

For this application, the best approach is to implement a separate Customer Loan Management module that is focused on customer lending and repayment tracking, without mixing it into the existing sales or inventory logic.

This gives the client a practical and understandable solution while preserving clean accounting boundaries in the system.
