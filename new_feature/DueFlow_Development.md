# Canteen Due Generation Development Steps

## Product name

The due generation workflow is named **Canteen Due Generation**. Existing manual Cost Cutting remains available for exceptional or historical uploads.

## Installation

1. Apply `db_structure/due_generation_schema.sql` after the base Pioneer schema and `db_structure/cost_cutting_schema.sql`.
   For an existing DueFlow installation, apply `db_structure/due_generation_schema_upgrade.sql` to create the pending-refund register and reconcile historical completed Cost Cutting overpayments. The backfill creates missing pending refunds, marks their Due Generation items fully cut, and recalculates affected generation totals. It can be safely rerun.
2. Confirm the application can write to `uploads/csv/due-generation/`. The module creates this directory when the first generation runs; the web server user must have write permission.
3. Sign in with `payment_management_view` to inspect DueFlow and with `site_edit` plus `payment_management_record` to change the date, generate a cycle, or reconcile a result.
4. Open **Settings > Site Settings > Due Generation**, set the Cost Cutting Date, and save the settings. The default is day `25`.

## First-time activation

The first DueFlow generation is an **Initial Due Setup**, not a historical monthly cycle. Internally it is stored as `Opening Balance` for database compatibility. It uses each customer's current outstanding balance as `New Due`, stores `Previous Outstanding` as zero, and does not calculate a historical billing period. The initial due is sent through the same Back Office cutting and carry-forward process.

After the Opening Balance is created, the first regular Monthly Cycle is generated on the configured Cost Cutting Date in the following month. Each monthly cycle starts on the previous Due Generation's date and ends immediately before the current Due Generation date. For example, an Opening Balance created on 7 September with Cost Cutting Date 28 has its first regular cycle from 7 September through 27 October, generated on 28 October.

## Monthly operation

1. Select the month containing the configured Cost Cutting Date. The date is maintained from **Settings > Site Settings > Due Generation**.
2. Click **Generate DueFlow** on or after that date. The system stores the input rule, the immutable cycle boundaries, every customer amount, the operator, and the generation timestamp.
3. Download the generated CSV and send it to the Back Office. Downloading changes the generation status to `Sent to Back Office`.
4. Import the successful-cutting CSV returned by the Back Office. It must contain `Customer Number`; `Actual Cut Amount` is optional.
5. A customer present in the result is treated as successfully cut. Without an amount column, the full requested amount is used. A customer missing from the result is treated as unpaid and the full amount is carried forward.
6. Review the generation details page. It shows previous outstanding, new due, total due, actual cut, remaining due, and customer status.
7. When a completed Cost Cutting batch has a requested amount greater than the amount applied, Cost Cutting automatically creates a pending refund record linked to the customer, DueFlow generation/item, and Cost Cutting batch/item. The Due Generation item retains the remaining due after the capped payment, and the overpayment details show the refund as `Pending`.
8. The **Customers > Customer Refunds** page lists refund records newest first. Roles with **View customer refunds** can access the register; **Confirm refund payment** controls the payment action separately. Confirm only after the refund has actually been issued; this records the operator, time, and optional payment reference and changes the refund status from `Pending` to `Paid`.
9. Due Generation CSV downloads can be exported for all customers or filtered by company/address.

## Cost Cutting upload customer matching

Cost Cutting uploads require a `Cutting Amount` column and either `Customer Number` or `Customer Name`. If a `Customer Name` column is present, its value takes precedence over the number column and must match exactly one active customer after case and whitespace normalization. A leading numeric or numeric-code prefix is also ignored when matching names. Rejected CSV rows are stored in `db_cost_cutting_batch_errors` and displayed separately from processable payment items. Unmatched or ambiguous names do not block processing of uniquely matched rows, while other validation errors do. Repeated names that resolve to the same customer remain validation errors.

## Billing rules implemented

- The cycle starts at `00:00:00` on the previous Due Generation date and ends one second before the current Due Generation date.
- Transaction selection uses `sales_date >= cycle_start` and `sales_date < next_cycle_start`.
- Actual generation time does not change the cycle.
- A cycle is unique by `due_cycle_date`; duplicate generation is rejected.
- `total_due = previous_outstanding + current_cycle_new_due`.
- `remaining_due = total_due - actually_cut` and becomes the next cycle's previous outstanding amount.
- Day values above a month's last day are clamped to that month's last calendar day.
- The first generation is `Opening Balance`: current total outstanding due, no historical billing dates, and zero previous outstanding.
- The first regular monthly generation is on the configured Cost Cutting Date in the month after Opening Balance initialization; its billing cycle begins on the Opening Balance generation date.

## Result CSV contract

Minimum header:

```csv
Customer Number
```

Optional amount header:

```csv
Customer Number,Actual Cut Amount
```

Only successful customers should be returned. Do not add failed customers as zero rows; omission is the unpaid signal.

## Verification checklist

- Generate a cycle on the configured date and confirm its start/end dates.
- Try the same month twice and confirm duplicate rejection.
- Try a future month and confirm early-generation rejection.
- Reconcile a result containing only some customers and confirm omitted customers carry the full amount.
- Generate the next month and confirm carried-forward amounts are included.
- Confirm the CSV remains in `uploads/csv/due-generation/` and the details page is an audit record.

## Audit history

Open **Canteen Audit History** from the Payment Management menu. Select a month to view a combined timeline of Due Generation and Cost Cutting activity. Each row includes the event type, period, reference number, event date, operator, record count, due total, cut total, remaining amount, status, and a link to the source details page.

Use **Download CSV** to export the filtered month. The export uses the same normalized columns as the screen and is intended for operational review or audit handover.