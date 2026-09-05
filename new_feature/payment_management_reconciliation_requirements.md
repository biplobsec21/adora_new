# Payment Management & Reconciliation — Software Requirements Specification

**Version:** 1.0  
**Date:** 2026-09-05  
**Document Type:** Product & Functional Requirements  
**Scope:** Inventory software customer-dues/payment workflow

---

## 1. Purpose

The system currently requires an inventory manager to manually update the payment status of customers after the back office processes a monthly customer due list.

The objective is to replace repetitive one-by-one updates with a controlled payment-management and reconciliation workflow that supports:

1. Single-customer manual status updates.
2. Bulk manual status updates.
3. Monthly payment-batch management.
4. Uploading back-office Excel/CSV payment reports.
5. Mapping uploaded columns to system fields.
6. Automatic matching of payment records.
7. Automatic calculation of payment status.
8. Exception and conflict handling.
9. Review and confirmation before database changes.
10. Full audit history and import history.

---

# 2. Current Business Process

The current process is:

```text
Inventory System
      ↓
Generate Customer Due List
      ↓
Send Due List to Back Office
      ↓
Back Office Processes Payments
      ↓
Back Office Returns Payment Status Report
      ↓
Inventory Manager Receives Report
      ↓
Manager Manually Updates Each Customer
```

The manual update step is time-consuming and error-prone.

---

# 3. Proposed Business Process

The proposed process is:

```text
Inventory System
      ↓
Create Monthly Payment Batch
      ↓
Send Due List to Back Office
      ↓
Back Office Processes Payments
      ↓
Back Office Returns Excel/CSV Report
      ↓
Upload Report
      ↓
Column Mapping
      ↓
Validation
      ↓
Automatic Customer/Payment Matching
      ↓
Reconciliation Preview
      ↓
Review Exceptions / Conflicts
      ↓
Manager Confirms
      ↓
Bulk Database Update
      ↓
Audit Log + Reconciliation History
```

Manual updates remain available as a separate workflow.

---

# 4. Product Terminology

| Term | Definition |
|---|---|
| Customer | A customer whose dues are managed by the inventory system |
| Due | Amount currently owed by a customer |
| Payment | Money received/processed against a due |
| Payment Status | Current state of a payment |
| Payment Batch | A group of customer dues processed for a particular payment period |
| Reconciliation | Comparing system dues/payment records with an external back-office payment report |
| Import | An uploaded Excel/CSV payment report |
| Import Row | One row from an uploaded report |
| Exception | A row that cannot be safely reconciled automatically |
| Manual Override | A manager-initiated status change that does not originate from the back-office import |
| Payment Reference | Unique identifier used to identify a payment |
| Audit Log | Historical record of who changed what, when, and why |

---

# 5. Functional Modules

The system should contain the following modules:

1. Payment Batch Management
2. Customer Payment List
3. Single Manual Payment/Status Update
4. Bulk Manual Status Update
5. Payment Reconciliation
6. Payment Report Upload
7. Column Mapping
8. Automatic Matching
9. Reconciliation Preview
10. Exception Management
11. Conflict Management
12. Payment Transactions
13. Import History
14. Audit Trail
15. Permissions and Access Control

---

# 6. Payment Batch Management

## 6.1 Purpose

A payment batch represents a specific monthly payment cycle.

Example:

```text
Batch Number: PAY-2026-08
Period: August 2026
```

## 6.2 Required Fields

`payment_batches`

- id
- batch_number
- payment_period
- start_date
- end_date
- status
- total_customers
- total_expected_amount
- total_paid_amount
- total_unpaid_amount
- total_partial_amount
- created_by
- created_at
- updated_at

## 6.3 Batch Status

Recommended statuses:

```text
Draft
Submitted to Back Office
Processing
Report Received
Reconciling
Review Required
Approved
Completed
Cancelled
```

## 6.4 Requirements

- Batch number must be unique.
- A batch must have a defined payment period.
- A completed batch should not be freely editable.
- A batch must be associated with its payment records.
- A batch should retain its historical results permanently unless explicitly archived.

---

# 7. Customer Payment List

The manager needs a central screen for viewing payment records.

## 7.1 Recommended Columns

- Checkbox
- Customer ID
- Customer Name
- Payment/Due ID
- Payment Period
- Expected Amount
- Paid Amount
- Remaining Amount
- Payment Status
- Payment Source
- Payment Date
- Last Updated
- Actions

## 7.2 Filters

Required filters:

- Payment Period
- Payment Batch
- Payment Status
- Payment Source
- Customer
- Customer ID
- Date
- Exception status

## 7.3 Search

Search should support:

- Customer name
- Customer ID
- Payment ID
- Payment reference
- Mobile/account number where applicable

---

# 8. Payment Status Model

The system should not be restricted to only Paid/Unpaid.

Recommended statuses:

```text
Pending
Paid
Partially Paid
Unpaid
Failed
Cancelled
Overpaid
Under Review
```

## 8.1 Status Calculation

The system should calculate status primarily from expected and paid amounts.

### Paid

```text
Expected Amount = 5,000
Paid Amount = 5,000
```

Result:

```text
Paid
```

### Unpaid

```text
Expected Amount = 5,000
Paid Amount = 0
```

Result:

```text
Unpaid
```

### Partial

```text
Expected Amount = 5,000
Paid Amount = 3,000
```

Result:

```text
Partially Paid
```

### Overpaid

```text
Expected Amount = 5,000
Paid Amount = 5,500
```

Result:

```text
Overpaid
```

A configurable payment tolerance may be introduced if the business requires it.

---

# 9. Single Manual Status Update

## 9.1 Purpose

The manager must be able to manually change a single customer's payment status.

## 9.2 Example

Customer:

```text
Rahim
Expected: ৳5,000
Current Status: Pending
```

Manager can select:

```text
Paid
Unpaid
```

and save.

## 9.3 Requirements

- Only authorized users can perform manual updates.
- Confirmation should be displayed before changing the status.
- The system must record the previous status.
- The system must record the new status.
- The system must record the user.
- The system must record date/time.
- The system must identify the change as `Manual`.
- If a manual status change creates an accounting inconsistency, the UI must warn the manager.

---

# 10. Bulk Manual Status Update

## 10.1 Purpose

The manager can select multiple customers and update their payment status in one operation.

## 10.2 Example

```text
☑ Rahim      Pending
☑ Karim      Pending
☐ Hasan      Paid
☑ Jamal      Pending
```

Manager selects:

```text
Bulk Actions
    ├── Mark as Paid
    └── Mark as Unpaid
```

## 10.3 Confirmation

Before applying:

```text
You are about to update 37 payment records.

New Status: Paid

This is a manual status update and will be recorded in the audit history.

[Cancel] [Confirm]
```

## 10.4 Requirements

- Bulk action must work only on selected eligible records.
- The system must show selected record count.
- The system must show the target status.
- The system must require confirmation.
- Every changed record must have an audit entry.
- The bulk operation itself should have a batch/action ID.
- Unauthorized users must not see or execute the action.
- Completed/reconciled records may be protected depending on business rules.

## 10.5 Important Business Rule

"Bulk Status Update" must be clearly distinguished from "Payment Reconciliation."

A manual status change does not necessarily mean that money was actually received.

---

# 11. Manual Payment vs Manual Status Override

The product should distinguish between:

### A. Record Manual Payment

Used when the manager is actually recording money received.

Possible fields:

- Payment amount
- Payment date
- Payment method
- Payment reference
- Notes

This should create a payment transaction.

### B. Manual Status Override

Used when the manager intentionally changes the status without creating a financial transaction.

Example:

```text
Status: Paid
Source: Manual Override
```

The product owner must decide whether managers are allowed to use status-only overrides.

If financial/accounting accuracy is important, recording an actual payment transaction is preferred.

---

# 12. Payment Reconciliation

## 12.1 Purpose

Payment reconciliation is the automated workflow for processing the back-office payment report.

It must be separate from manual bulk status changes.

## 12.2 Workflow

```text
Upload
 ↓
Parse
 ↓
Map Columns
 ↓
Validate
 ↓
Match
 ↓
Calculate Results
 ↓
Preview
 ↓
Resolve Exceptions
 ↓
Confirm
 ↓
Apply Changes
```

The upload must NOT automatically update live payment records.

---

# 13. Payment Report Upload

## 13.1 Supported Formats

Initial MVP:

- `.xlsx`
- `.csv`

Optional later:

- `.xls`

## 13.2 Upload Screen

Fields:

```text
Payment Period
[ August 2026 ]

Payment Batch
[ PAY-2026-08 ]

Payment Report
[ Choose File ]

[ Upload & Analyze ]
```

## 13.3 File Validation

The system must validate:

- File type
- File size
- Required fields
- Row count
- Amount format
- Customer/payment identifiers
- Duplicate rows
- Payment period
- File integrity

Invalid files should not be processed.

---

# 14. Column Mapping

Because external reports may have different column names, the system should support column mapping.

Example uploaded columns:

```text
Employee No
Employee Name
Amount Paid
Transaction Date
Payment Status
```

Mapped to:

```text
Employee No       → Customer ID
Employee Name     → Customer Name
Amount Paid       → Paid Amount
Transaction Date  → Payment Date
Payment Status    → External Status
```

## 14.1 Mapping Requirements

- Display all uploaded columns.
- Allow each column to be mapped to a system field.
- Identify required mappings.
- Prevent continuation if mandatory fields are missing.
- Save mapping templates for future uploads.
- Allow the manager to reuse a previous mapping.
- Allow multiple report formats if different back offices use different templates.

---

# 15. Recommended Matching Strategy

Matching should use deterministic identifiers first.

Priority:

1. Payment ID
2. Unique Customer ID
3. Payment Reference
4. Invoice/Due ID
5. Account number/mobile number where appropriate
6. Name matching as a last-resort review mechanism

## 15.1 Strong Recommendation

The inventory system should generate a unique payment identifier for every payment record.

Example:

```text
PMT-2026-08-000001
PMT-2026-08-000002
PMT-2026-08-000003
```

The same identifier should be included in the report sent to the back office and returned in the payment report.

This is significantly safer than matching by customer name.

---

# 16. Automatic Matching

Each import row should receive a matching result.

Possible results:

```text
Exact Match
Matched by Payment Reference
Matched by Customer ID
Possible Match
Not Found
Duplicate
Invalid
```

The system should record the matching method.

Example:

```text
match_type = customer_id_exact
```

---

# 17. Fuzzy/Name Matching

Name-based matching should never automatically update financial records unless business rules explicitly permit it.

Example:

```text
Uploaded:
Md Abdul Rahim

System:
Mohammad Abdul Rahim
```

The system may display:

```text
Possible Match
Confidence: 96%
```

but should require manager confirmation.

Recommended categories:

```text
High Confidence → Auto Match
Medium Confidence → Manual Review
Low Confidence → Reject/Manual Mapping
```

---

# 18. Reconciliation Result

After analysis, display a summary before changes are applied.

Example:

```text
Payment Reconciliation — August 2026

Total Rows             500
Matched                487
Paid                   430
Unpaid                  49
Partial                  8
Exceptions              13

Expected Amount     ৳2,000,000
Reported Paid       ৳1,850,000
```

## 18.1 Summary Cards

Recommended cards:

- Total Records
- Matched
- Paid
- Unpaid
- Partial
- Exceptions
- Duplicate
- Total Expected
- Total Paid
- Total Remaining

---

# 19. Reconciliation Preview

Before applying changes, show the exact changes.

Example:

| Customer | Expected | Reported Paid | Current Status | New Status |
|---|---:|---:|---|---|
| Rahim | 5,000 | 5,000 | Pending | Paid |
| Karim | 3,500 | 0 | Pending | Unpaid |
| Hasan | 7,000 | 4,000 | Pending | Partial |

The manager must be able to inspect the records before confirmation.

---

# 20. Exception Management

Exceptions must not silently disappear.

Recommended exception types:

```text
Customer Not Found
Payment Not Found
Duplicate Record
Duplicate Payment Reference
Amount Mismatch
Missing Amount
Invalid Amount
Invalid Customer ID
Wrong Payment Period
Already Processed
Multiple Payment Records
Overpayment
Underpayment
Possible Match
Conflict With Manual Override
```

## 20.1 Exception Screen

| Row | Customer | Expected | Reported | Problem | Action |
|---:|---|---:|---:|---|---|
| 12 | Rahim | 5,000 | — | Not Found | Resolve |
| 34 | Karim | 4,000 | 2,000 | Partial | Review |
| 51 | Hasan | 7,000 | 7,000 | Duplicate | Review |

Actions:

```text
Resolve
Map Customer
Ignore
Reject Row
```

---

# 21. Conflict Management

A conflict occurs when a manager has manually changed a payment and a later import reports a different result.

Example:

```text
Current system:
Rahim → Paid
Source:
Manual Override
```

Imported report:

```text
Rahim → Unpaid
Reason:
Insufficient Balance
```

The system must not silently overwrite the manual decision.

It should flag:

```text
Conflict Detected
```

and allow the manager to choose:

```text
Use Imported Result
Keep Manual Status
Review Later
```

The decision must be recorded in the audit log.

---

# 22. Payment Transactions

Where actual payment amounts are being recorded, payment transactions should be separate from status fields.

Recommended relationship:

```text
Customer
   ↓
Due / Invoice
   ↓
Payment Transaction
```

A customer may have multiple dues and multiple payments.

The system should not rely solely on a single customer-level `payment_status`.

---

# 23. Partial Payments

Partial payments must be supported.

Example:

```text
Due: ৳10,000
Paid: ৳7,000
Remaining: ৳3,000
Status: Partially Paid
```

The remaining balance must be calculated consistently.

If multiple payments are allowed:

```text
Payment 1 = ৳3,000
Payment 2 = ৳4,000
Payment 3 = ৳3,000
```

Total:

```text
৳10,000
```

Status:

```text
Paid
```

---

# 24. Import Idempotency / Duplicate Protection

Uploading the same report twice must not create duplicate payments.

The system should maintain:

- Import ID
- File name
- File hash
- Payment batch
- Payment reference
- Processed timestamp

If the same file is uploaded again:

```text
This payment report has already been processed.
```

If individual rows were previously processed:

```text
87 records have already been processed.
```

The system must not double-count payments.

---

# 25. Database Transaction / Safe Application

When applying a confirmed reconciliation, the system must use a safe database transaction strategy.

Conceptually:

```text
BEGIN

Validate final records
Update payment records
Create/update payment transactions
Update due balances
Create audit logs
Mark import as completed

COMMIT
```

If a critical error occurs:

```text
ROLLBACK
```

For large imports, process records in controlled chunks while maintaining reliable import state and idempotency.

---

# 26. Audit Trail

Every financial or status change must be traceable.

Recommended audit fields:

```text
id
entity_type
entity_id
action
old_value
new_value
source
import_id
batch_id
performed_by
performed_at
notes
```

Example:

```text
Customer: CUST-001

Previous Status: Pending
New Status: Paid

Source: Payment Import
Import ID: IMP-2026-00023
Batch: PAY-2026-08

Updated By: Admin
Updated At: 05 Sep 2026 17:30
```

Manual changes should show:

```text
Source: Manual
```

Bulk manual changes:

```text
Source: Bulk Manual
```

Imported changes:

```text
Source: Payment Import
```

---

# 27. Import History

Create a history screen.

| Batch | File | Records | Matched | Exceptions | Status | Uploaded By | Date |
|---|---|---:|---:|---:|---|---|---|
| Aug 2026 | payment_aug.xlsx | 500 | 487 | 13 | Completed | Manager | Sep 5 |
| Jul 2026 | payment_jul.xlsx | 492 | 480 | 12 | Completed | Manager | Aug 5 |

Clicking an import should display:

- File information
- Mapping used
- Summary
- All rows
- Exceptions
- Changes applied
- Audit information

---

# 28. Reconciliation Lifecycle

Recommended import states:

```text
Uploaded
 ↓
Analyzing
 ↓
Analysis Complete
 ↓
Review Required
 ↓
Ready to Apply
 ↓
Applying
 ↓
Completed
```

Failure state:

```text
Failed
```

Cancelled state:

```text
Cancelled
```

---

# 29. Rollback / Reversal

The system should support reversing an incorrectly applied reconciliation.

Important:

Do not delete historical records.

Instead create a reversal operation.

Example:

```text
Original Import:
IMP-2026-00023

Action:
Reversed

Reversed By:
Admin

Reason:
Incorrect payment report uploaded
```

The reversal must itself be audited.

---

# 30. Permissions

Recommended roles:

## Back Office

Can:

- View payment batches
- View payment lists
- Process payments
- Produce payment reports

Cannot:

- Arbitrarily modify inventory payment status unless explicitly authorized.

## Inventory Manager

Can:

- View payment list
- Single manual update
- Bulk manual update
- Upload reports
- Reconcile reports
- Resolve exceptions
- Confirm reconciliation

## Administrator

Can:

- All manager functions
- Configure mappings
- Reverse reconciliation
- View complete audit history
- Configure payment rules
- Manage permissions

---

# 31. UI Navigation

Recommended navigation:

```text
Payments
│
├── Payment List
│
├── Payment Batches
│
├── Reconciliation
│   ├── Upload Report
│   ├── Pending Reviews
│   └── Import History
│
└── Audit History
```

---

# 32. Payment List Actions

Normal list:

```text
Payment Management

[Search]
[Period]
[Batch]
[Status]

☐ Customer
☐ Customer
☐ Customer

Bulk Actions
    Mark as Paid
    Mark as Unpaid
    Record Payment
```

Single-row actions:

```text
View
Record Payment
Change Status
View History
```

---

# 33. Reconciliation UI

Recommended flow:

### Screen 1 — Select Batch

```text
Payment Period
[ August 2026 ]

Payment Batch
[ PAY-2026-08 ]

[Continue]
```

### Screen 2 — Upload

```text
Payment Report
[ Choose Excel/CSV ]

[Upload & Analyze]
```

### Screen 3 — Mapping

```text
Uploaded Column      System Field
Employee No          Customer ID
Amount Paid          Paid Amount
Transaction Date     Payment Date
```

### Screen 4 — Analysis

```text
500 rows
487 matched
13 exceptions
```

### Screen 5 — Review

Show exceptions and conflicts.

### Screen 6 — Confirmation

```text
430 Paid
49 Unpaid
8 Partial
13 Exceptions

[Cancel] [Confirm & Apply]
```

### Screen 7 — Completion

```text
Reconciliation Completed

487 records processed successfully.
13 records remain unresolved.
```

---

# 34. Recommended Data Model

The following tables are recommended, subject to the existing inventory database design.

## `payment_batches`

```text
id
batch_number
payment_period
start_date
end_date
status
total_customers
total_expected_amount
total_paid_amount
created_by
created_at
updated_at
```

## `payment_batch_items`

```text
id
payment_batch_id
customer_id
due_id
expected_amount
paid_amount
remaining_amount
status
failure_reason
external_reference
payment_date
payment_source
created_at
updated_at
```

## `payment_imports`

```text
id
payment_batch_id
file_name
file_hash
total_rows
matched_rows
unmatched_rows
duplicate_rows
exception_rows
status
uploaded_by
uploaded_at
completed_at
```

## `payment_import_rows`

```text
id
payment_import_id
row_number
external_customer_id
external_name
external_amount
external_status
external_reference
payment_date
matched_customer_id
matched_payment_id
match_type
match_confidence
processing_status
error_message
```

## `payment_transactions`

```text
id
customer_id
payment_batch_id
due_id
amount
payment_date
payment_method
reference
source
status
created_by
created_at
```

## `payment_audit_logs`

```text
id
entity_type
entity_id
action
old_value
new_value
source
import_id
batch_id
performed_by
performed_at
notes
```

---

# 35. Critical Business Rules

## Rule 1 — Unique Matching

Customer name must not be the primary identifier.

## Rule 2 — No Automatic Database Update on Upload

Upload must first produce an analysis/preview.

## Rule 3 — Explicit Confirmation

Manager must confirm before applying reconciliation results.

## Rule 4 — Duplicate Protection

The same transaction must never be counted twice.

## Rule 5 — Partial Payments

Expected and paid amounts must be independently stored.

## Rule 6 — Auditability

Every manual or automated change must be traceable.

## Rule 7 — Manual Overrides

Imported results must not silently overwrite manual decisions.

## Rule 8 — Payment Period

A payment must belong to the correct batch/payment period.

## Rule 9 — Exceptions

Ambiguous or invalid records must be isolated rather than silently processed.

## Rule 10 — Financial Integrity

Status changes and payment transactions should be distinguished.

---

# 36. Major Pitfalls

## 36.1 Matching by Name

Names can be duplicated or inconsistent.

**Solution:** Use a unique Customer ID or Payment ID.

## 36.2 Direct Import-to-Database Updates

A malformed Excel file could change hundreds of records incorrectly.

**Solution:** Upload → Analyze → Preview → Confirm → Apply.

## 36.3 Duplicate Upload

The same report can be uploaded twice.

**Solution:** File hash + import ID + unique payment references.

## 36.4 Only Paid/Unpaid Status

This loses partial-payment information.

**Solution:** Support Partial and transaction amounts.

## 36.5 No Audit Trail

You will not know who changed a financial record.

**Solution:** Immutable audit history.

## 36.6 Manual Override Conflicts

A manual update can conflict with a later imported result.

**Solution:** Detect and explicitly resolve conflicts.

## 36.7 Wrong Payment Period

An August report could accidentally update July records.

**Solution:** Validate batch and period.

## 36.8 One Invalid Row Blocks Everything

One bad row should not force the manager to restart the entire process.

**Solution:** Isolate exceptions.

## 36.9 Trusting External Status

External status can disagree with amounts.

**Solution:** Validate amounts and calculate the internal status according to business rules.

## 36.10 No Reversal

Incorrect imports can become difficult to correct.

**Solution:** Support audited reversal/reconciliation reversal.

---

# 37. MVP Scope

The first implementation should include:

### Required

- Payment batches
- Payment list
- Single manual status update
- Bulk manual status update
- Excel/CSV upload
- Column mapping
- Customer/payment ID matching
- Amount validation
- Paid/Unpaid/Partial calculation
- Reconciliation preview
- Exception list
- Conflict detection
- Confirmation before applying
- Duplicate protection
- Import history
- Audit logs
- Role permissions

### Defer to Phase 2

- Fuzzy matching
- API integration with back office
- Automatic scheduled imports
- Bank/payment gateway integration
- Advanced analytics
- Automatic notifications
- Multiple external report providers
- AI-assisted matching

---

# 38. Acceptance Criteria

The feature is considered successful when:

1. A manager can update one payment status manually.
2. A manager can select multiple payment records and mark them Paid/Unpaid.
3. Bulk manual updates require confirmation.
4. Manual updates are audited.
5. A monthly payment batch can be created.
6. An Excel/CSV payment report can be uploaded.
7. The system can map external columns to internal fields.
8. The system can match payment records using unique identifiers.
9. The system calculates payment status from amounts.
10. Partial payments are supported.
11. Duplicate records are detected.
12. Unknown customers are isolated as exceptions.
13. Import does not directly change production data before confirmation.
14. Manager can review proposed changes.
15. Manager can resolve exceptions.
16. Conflicts with manual overrides are detected.
17. A confirmed import updates the appropriate payment records.
18. Every update is auditable.
19. The same report cannot create duplicate payments.
20. Import history remains available after completion.
21. Incorrect reconciliations can be reversed by authorized users.
22. Payment-period mismatches are prevented.

---

# 39. Recommended Final Product Concept

The most important UX distinction is:

```text
PAYMENT MANAGEMENT
│
├── Manual Management
│   ├── Single Status Update
│   ├── Bulk Status Update
│   └── Record Manual Payment
│
└── Payment Reconciliation
    ├── Upload Report
    ├── Map Columns
    ├── Match Records
    ├── Review Exceptions
    ├── Resolve Conflicts
    └── Confirm & Apply
```

This keeps the manager's everyday manual operations simple while making the monthly back-office reconciliation safe and automated.

---

# 40. Recommended Long-Term Architecture

The strongest long-term design is to make the **Payment ID** the common identifier between the inventory system and back office.

```text
Inventory System
      │
      │ Payment ID
      ▼
Back Office
      │
      │ Payment ID + Result + Amount
      ▼
Inventory System
      │
      ▼
Automatic Reconciliation
```

Example:

```text
PMT-2026-08-000001
PMT-2026-08-000002
PMT-2026-08-000003
```

This eliminates most ambiguity and makes the reconciliation process deterministic.

The core product principle should be:

> **Automate deterministic records, isolate ambiguous records for human review, and never hide or overwrite financial changes.**
