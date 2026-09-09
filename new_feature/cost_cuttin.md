1. Module Name

Canteen Cost Cutting
The module is used to process monthly customer due payments based on the payment amount returned by the back office.

The basic workflow is:

Payment Management
       ↓
Generate Due List CSV
       ↓
Send CSV to Back Office
       ↓
Back Office Manually Processes/Checks
       ↓
Back Office Returns XLSX/CSV
       ↓
Upload Cost Cutting File
       ↓
Review
       ↓
Process Cost Cutting
       ↓
Customer Payment/Due Status Automatically Updated
       ↓
Store Cost Cutting Record

2. Purpose

The purpose of the Cost Cutting module is to eliminate the need for the inventory manager to manually update each customer's payment status after receiving the monthly payment result from the back office.

The back office determines the actual Cutting Amount for each customer.

The system uses:

Customer ID + Cutting Amount

to determine and update the customer's payment information.

3. Input From Payment Management

The existing Payment Management screen generates the customer due list.

Example:

Customer ID	Customer Name	Due Amount
CUST-001	Rahim	৳5,000
CUST-002	Karim	৳3,000
CUST-003	Hasan	৳7,000

This CSV is provided to the back office.

The Cost Cutting module does not need to regenerate this information.

4. Back Office Return File

After manually checking/processsing the due list, the back office returns an XLSX or CSV file.

Required information:

Customer ID	Customer Name	Cutting Amount
CUST-001	Rahim	5,000
CUST-002	Karim	0
CUST-003	Hasan	4,000

Required fields

The system should primarily depend on:

Customer ID
Cutting Amount

Customer Name is useful for display and verification, but should not be the primary matching field.

5. Cutting Amount

The Cutting Amount represents the amount actually deducted/paid for that customer by the back office.

Example:

Full payment
Customer Due:      ৳5,000
Cutting Amount:    ৳5,000

Status: Paid
No payment
Customer Due:      ৳5,000
Cutting Amount:    ৳0

Status: Unpaid
Partial payment
Customer Due:      ৳5,000
Cutting Amount:    ৳3,000

Status: Partial Paid

Therefore:

Cutting Amount = 0
        ↓
Unpaid

Cutting Amount < Due Amount
        ↓
Partial Paid

Cutting Amount >= Due Amount
        ↓
Paid

The exact comparison should use the customer's due amount for the relevant payment period.

6. Cost Cutting Processing

After uploading the back-office file, the manager should be able to review the records before applying them.
Example:

| Customer ID | Customer |   Current Due | Cutting Amount | Result       |
| ----------- | -------- | ---------   : | -------------: | ------------ |
| CUST-001    | Rahim    | 5,000  |          5,000 | Paid         |
| CUST-002    | Karim    | 3,000  |              0 | Unpaid       |
| CUST-003    | Hasan    | 7,000  |          4,000 | Partial Paid |

 Current Due will be generate dynamically.
 The manager then clicks:

Process Cost Cutting

The system loops through the uploaded records and processes each Customer ID.

7. Processing Logic

For every row:
Read Customer ID
        ↓
Find customer
        ↓
Find relevant due/payment record
        ↓
Read Cutting Amount
        ↓
Compare Cutting Amount with Due Amount
        ↓
Determine Payment Status
        ↓
Update Payment/Due Record

Customer ID = CUST-003

Due Amount = ৳7,000
Cutting Amount = ৳4,000

Payment Status = Partial Paid

9. User-Friendly Cost Cutting Panel

I recommend a very simple 3-step interface.
Step 1 — Select Payment Period
Canteen Cost Cutting

Payment Period
[ September 2026 ▼ ]

[ Upload Cutting File ]
Step 2 — Upload File
Upload Back Office Cost Cutting File

Payment Period:
September 2026

File:
[ Choose XLSX / CSV ]

[ Upload & Review ]
After upload, the system reads and validates the file.
10. Review Screen

Before making any changes, show a preview.

Summary
Total Records       500
Valid Records       495
Errors                5

Paid                420
Partial Paid         35
Unpaid               40

Total Due       ৳2,000,000
Total Cutting   ৳1,850,000
Then show the records:
| Customer ID | Customer |   Due | Cutting | Status       |
| ----------- | -------- | ----: | ------: | ------------ |
| CUST-001    | Rahim    | 5,000 |   5,000 | Paid         |
| CUST-002    | Karim    | 3,000 |       0 | Unpaid       |
| CUST-003    | Hasan    | 7,000 |   4,000 | Partial Paid |

At this stage nothing has been permanently updated yet.

At this stage nothing has been permanently updated yet.

11. Validation

The system should perform only the necessary validation.

Customer ID exists

If:

CUST-999

doesn't exist:

Customer Not Found
Cutting Amount is numeric

Invalid:

ABC
Cutting Amount cannot be negative

Invalid:

-500
Duplicate Customer ID

If the file contains:

CUST-001
CUST-001

the system should flag it instead of guessing which amount is correct.

Payment period

The uploaded file must be associated with the selected payment period.

12. Exceptions

Keep exception handling simple.

Example:

5 records could not be processed.
Row	Customer ID	Problem
12	CUST-999	Customer Not Found
31	CUST-015	Duplicate Customer ID
72	CUST-044	Invalid Cutting Amount

The manager should be able to fix the source file and upload again, or resolve the specific issue if you choose to support manual resolution.

For the first version, re-uploading a corrected file is perfectly acceptable.

13. Process Confirmation

After review:

You are about to process:

500 customers
420 Paid
35 Partial Paid
40 Unpaid

Total Cutting Amount:
৳1,850,000

This will update the payment status of the selected payment period.

[ Cancel ]     [ Process Cost Cutting ]

The manager must explicitly confirm.

14. Successful Processing

After completion:

Cost Cutting Completed

Payment Period:
September 2026

Processed:
495 customers

Paid:
420

Partial Paid:
35

Unpaid:
40

Total Cutting:
৳1,850,000

[ View Details ]
15. Store Cost Cutting Records

Every processed file should be stored as a Cost Cutting Batch.

For example:

CC-2026-09-0001

Information to store:

Cost Cutting ID
Payment Period
Uploaded File Name
Uploaded By
Upload Date
Total Records
Total Cutting Amount
Paid Count
Partial Paid Count
Unpaid Count
Processing Status
Processed Date
Reverted Date, if applicable
Reverted By, if applicable
16. Cost Cutting History

Create a simple history page:

Cost Cutting ID	Period	Records	Cutting Amount	Status	Date
CC-2026-09-0001	Sep 2026	500	৳1,850,000	Completed	Sep 5
CC-2026-08-0001	Aug 2026	492	৳1,790,000	Completed	Aug 5

Manager can click a record to view its details.

17. Cost Cutting Details

When opening a processed batch:

Cost Cutting: CC-2026-09-0001

Payment Period: September 2026
File: cutting_september.xlsx
Processed By: Manager
Processed At: 05 Sep 2026

Total Records: 500
Total Cutting: ৳1,850,000

Then:

Customer ID	Customer	Due	Cutting	Status
CUST-001	Rahim	5,000	5,000	Paid
CUST-002	Karim	3,000	0	Unpaid
CUST-003	Hasan	7,000	4,000	Partial
18. Revert Feature

Yes, I recommend having Revert.

Because this operation can update hundreds of customers at once.

For example, the manager accidentally uploads the wrong September file.

Without Revert, correcting 500 records manually could become another major problem.

Revert workflow
Cost Cutting History
        ↓
Open CC-2026-09-0001
        ↓
[ Revert Cost Cutting ]
        ↓
Confirmation
        ↓
Restore previous payment state
        ↓
Mark batch as Reverted

Confirmation:

Revert this cost cutting batch?

This will reverse the changes made by this cost-cutting operation.

This action will be recorded in the history.

Cancel | Revert

19. Revert Should NOT Delete History

This is important.

Don't do:

DELETE cost_cutting

Instead:

Status:
Completed → Reverted

and retain:

original file
original records
original changes
who processed it
when it was processed
who reverted it
when it was reverted

This gives you a proper historical record.

20. Revert Implementation Concept

Before updating a payment record, store the previous state.

Example:

Customer: CUST-001

Before:
Due Status = Pending
Paid Amount = 0

Cost cutting:

Cutting Amount = 5,000

After:

Due Status = Paid
Paid Amount = 5,000

If reverted:

Due Status = Pending
Paid Amount = 0

The system restores the previous state, rather than assuming what the previous state should have been.

This is particularly important if managers can manually modify payment records.

21. Interaction With Manual Payment Management

This is where your two features need to work together.

You already have:

Payment Management

Manager can:

Single → Mark Paid/Unpaid
Bulk → Mark Paid/Unpaid

And now:

Canteen Cost Cutting
Back-office file
      ↓
Cutting Amount
      ↓
Automatic Paid/Partial/Unpaid

So the architecture becomes:

                PAYMENT MANAGEMENT
                       │
          ┌────────────┴────────────┐
          │                         │
    Manual Updates            Canteen Cost Cutting
          │                         │
   Single / Bulk              XLSX / CSV
          │                         │
          └────────────┬────────────┘
                       ↓
                Payment/Due Record
                       ↓
                 Status Updated

Both methods ultimately update the same underlying payment/due records.

22. One Important Rule for Manual Updates

Because the manager can manually update records, the system needs to preserve the previous value when Cost Cutting runs.

For example:

Before Cost Cutting:

Rahim
Status: Paid
Paid Amount: 5,000
Source: Manual

Then the Cost Cutting file says:

Cutting Amount: 0

If processed, it becomes:

Status: Unpaid
Paid Amount: 0
Source: Cost Cutting

If later reverted, it should return to:

Status: Paid
Paid Amount: 5,000
Source: Manual

not simply "Pending."

That is the key reason the Revert feature needs to store the previous state.

23. Recommended Database Design

Keep this simple.

cost_cutting_batches
id
batch_number
payment_period
file_name
total_records
total_cutting_amount
paid_count
partial_paid_count
unpaid_count
status
uploaded_by
processed_by
processed_at
reverted_by
reverted_at
created_at
updated_at
cost_cutting_items
id
cost_cutting_batch_id
customer_id
customer_name
due_amount
cutting_amount
previous_status
previous_paid_amount
new_status
new_paid_amount
created_at
updated_at

The most important fields for Revert are:

previous_status
previous_paid_amount

You can add other previous values if your existing payment model has more fields that will be modified.

24. Recommended Statuses for Cost Cutting Batch

Keep this simple:

Uploaded
Reviewed
Completed
Reverted
Failed

You probably don't need a dozen workflow states.

25. Pitfalls to Avoid
1. Don't use Customer Name for matching

Use:

Customer ID

The name is only for display/verification.

2. Don't update immediately after upload

Use:

Upload → Review → Confirm → Process
3. Don't store only the new value

For Revert, store the previous value too.

4. Don't allow duplicate Customer IDs in one file

Flag them.

5. Don't allow negative Cutting Amount

Reject it.

6. Don't silently process unknown customers

Show them as errors.

7. Don't delete a processed Cost Cutting batch

Mark it Reverted.

8. Don't let Revert destroy subsequent manual changes

This is a subtle but important issue.

If Cost Cutting changes a record and then the manager manually changes that same record afterward, blindly reverting the old Cost Cutting batch could overwrite the manager's newer change.

Therefore, when implementing Revert, the system should ideally check whether the record was modified after the Cost Cutting operation.

If yes:

⚠ This payment was changed after Cost Cutting. Review required.

For your first version, you can alternatively restrict Revert to the latest batch and warn if subsequent changes exist.

26. Final Simplified User Experience

The manager's experience should ultimately be only:

1. Generate Due List

From Payment Management:

Generate Due List → CSV

2. Back Office

Back office manually processes it.

3. Receive File

Manager gets:

cutting_september_2026.xlsx
4. Open

Canteen Cost Cutting

5. Select Period
September 2026
6. Upload
[ Upload XLSX / CSV ]
7. Review

System shows:

500 records

420 Paid
35 Partial Paid
40 Unpaid
5 Errors
8. Confirm
[ Process Cost Cutting ]
9. Done

All valid customer payment records are updated automatically.

10. Later if necessary
Cost Cutting History
        ↓
Open Batch
        ↓
Revert
=====
