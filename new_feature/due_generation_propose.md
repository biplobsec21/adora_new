# Canteen Cost Cutting – Due Generation & Reconciliation Specification

## 1. Purpose

The Canteen Cost Cutting module is responsible for generating monthly customer due lists, sending those due lists to the Back Office for cost cutting, importing the actual cutting results returned by the Back Office, and carrying forward any amount that was not actually cut.

The system must maintain a clear billing-cycle history for every customer.

---

# 2. Core Billing Cycle Rule

A customer’s billing cycle is a recurring monthly period anchored to the customer’s due-generation date.

Each cycle begins on the day immediately following the previous cycle’s end date and ends on the corresponding day of the next month.

Any amount not actually cut by the Back Office remains outstanding and is automatically carried forward to the next cycle.

### Example

If the configured Cost Cutting Date is the **28th of every month**:

```text
Billing Cycle:
28 July 00:00:00 → 27 August 23:59:59

Due Generation Date:
28 August
```

The next cycle will be:

```text
28 August 00:00:00 → 27 September 23:59:59

Due Generation Date:
28 September
```

Then:

```text
28 September 00:00:00 → 27 October 23:59:59

Due Generation Date:
28 October
```

The actual time at which the user clicks **Generate Due** does not change the billing period.

---

# 3. Cost Cutting Date

The system must have a configured **Cost Cutting Date**.

Example:

```text
Cost Cutting Date = 28
```

This means the system expects the monthly due generation to take place on the **28th of every month**.

The Cost Cutting Date is a calendar day, not a timestamp.

The billing cycle is determined from this date.

---

# 4. Billing Period Calculation

For a Cost Cutting Date of the 28th:

### August Due

```text
Period Start:
28 July 00:00:00

Period End:
27 August 23:59:59

Due Generation:
28 August
```

### September Due

```text
Period Start:
28 August 00:00:00

Period End:
27 September 23:59:59

Due Generation:
28 September
```

### October Due

```text
Period Start:
28 September 00:00:00

Period End:
27 October 23:59:59

Due Generation:
28 October
```

The system must not use the actual generation time to determine the billing period.

---

# 5. Why the Billing Period Ends at 23:59:59

The complete day before the Cost Cutting Date must belong to the previous cycle.

For example, if the Cost Cutting Date is 28 August:

```text
27 August 23:59:58 → Current cycle
27 August 23:59:59 → Current cycle
28 August 00:00:00 → New cycle
```

Therefore, there is no missing period between cycles.

---

# 6. Due Generation

On the Cost Cutting Date, the system generates the due for the completed billing period.

For example:

```text
Cost Cutting Date = 28 September

Due Period:
28 August 00:00:00
to
27 September 23:59:59
```

The system calculates the customer's new due from all applicable transactions/charges within this period.

The user may generate the due manually from the Cost Cutting module.

The system should also support scheduled/automatic generation if required.

---

# 7. Actual Generation Time

The system should store the actual date and time when the due list was generated.

Example:

```text
Cost Cutting Date:
28 September 2026

Actual Generated At:
28 September 2026 10:15 PM
```

However, `Actual Generated At` is only an audit/reference field.

It must NOT change the billing period.

The billing period remains:

```text
28 August 00:00:00
→
27 September 23:59:59
```

And the next billing period remains:

```text
28 September 00:00:00
→
27 October 23:59:59
```

---

# 8. Customer Due Calculation

Every customer may have a new due for the current billing cycle.

The system must also consider any outstanding amount from previous cycles.

The calculation is:

```text
Previous Outstanding Due
+
Current Cycle New Due
=
Total Due
```

### Example

Previous outstanding amount:

```text
৳500
```

Current month's new due:

```text
৳100
```

Therefore:

```text
Previous Outstanding = ৳500
Current New Due     = ৳100
--------------------------------
Total Due           = ৳600
```

The amount sent to the Back Office for cost cutting should therefore be:

```text
৳600
```

unless there is a configured maximum cutting limit.

---

# 9. Back Office Cost Cutting

After due generation, the system generates a Cost Cutting list for the Back Office.

The list may be exported as:

* CSV
* XLSX

The exported file should contain the necessary customer and cutting information.

Example:

| Customer ID | Customer Name | Customer Number | Cutting Amount |
| ----------- | ------------- | --------------- | -------------: |
| 1001        | Customer A    | 017XXXXXXXX     |           ৳600 |
| 1002        | Customer B    | 018XXXXXXXX     |           ৳350 |
| 1003        | Customer C    | 019XXXXXXXX     |           ৳800 |

The Back Office processes the cutting externally.

---

# 10. Back Office Result

The Back Office may not be able to cut the full amount.

Therefore, the system must distinguish between:

```text
Amount Sent for Cutting
```

and:

```text
Actually Cut Amount
```

Example:

```text
Total Due:
৳600

Sent to Back Office:
৳600

Actually Cut:
৳400
```

The remaining amount is:

```text
৳600 - ৳400 = ৳200
```

Therefore:

```text
Remaining Outstanding Due = ৳200
```

The ৳200 must be carried forward to the next billing cycle.

---

# 11. Carry-Forward Rule

Any amount that is not actually cut by the Back Office must remain outstanding.

It must automatically be carried forward to the next cycle.

### Example

#### August Cycle

```text
New Due             = ৳500
Previous Due        = ৳0
Total Due           = ৳500

Sent to Back Office = ৳500
Actually Cut        = ৳0

Remaining Due       = ৳500
```

The ৳500 remains outstanding.

#### September Cycle

New September due:

```text
৳100
```

Previous outstanding:

```text
৳500
```

Therefore:

```text
Previous Outstanding = ৳500
New Due              = ৳100
--------------------------------
Total Due            = ৳600
```

The September Cost Cutting list should therefore show:

```text
Customer Total Due = ৳600
```

---

# 12. Partial Cutting Example

Suppose:

```text
Previous Outstanding = ৳500
Current New Due      = ৳100
Total Due            = ৳600
```

The Back Office cuts:

```text
৳400
```

Then:

```text
Total Due            = ৳600
Actually Cut         = ৳400
--------------------------------
Remaining Due        = ৳200
```

The next cycle will start with:

```text
Previous Outstanding = ৳200
```

If the next month's new due is:

```text
৳150
```

then:

```text
Previous Outstanding = ৳200
New Due              = ৳150
--------------------------------
Total Due            = ৳350
```

---

# 13. Fully Cut Example

If:

```text
Total Due = ৳600
Actually Cut = ৳600
```

then:

```text
Remaining Due = ৳0
```

No amount should be carried forward.

The next cycle only contains the new due.

Example:

```text
Previous Outstanding = ৳0
New Due              = ৳150
Total Due            = ৳150
```

---

# 14. No Cutting Example

If:

```text
Total Due = ৳600
Actually Cut = ৳0
```

then:

```text
Remaining Due = ৳600
```

The full ৳600 must be carried forward.

If the next cycle has a new due of ৳200:

```text
Previous Outstanding = ৳600
New Due              = ৳200
--------------------------------
Total Due            = ৳800
```

---

# 15. Due Status

Each due record should have a status.

Recommended statuses:

### Draft

Due calculation has been created but not finalized.

### Generated

Due has been finalized and is ready for Back Office processing.

### Sent to Back Office

Due list has been exported/submitted to the Back Office.

### Partially Cut

The Back Office cut less than the total due.

### Fully Cut

The Back Office cut the complete amount.

### Not Cut

The Back Office did not cut any amount.

### Carried Forward

An outstanding amount remains and has been carried into the next cycle.

---

# 16. Duplicate Generation Prevention

The system must prevent generating the same billing cycle multiple times.

For example, if the September cycle is:

```text
28 August 00:00:00
→
27 September 23:59:59
```

and it has already been generated, attempting to generate it again should be rejected.

Example message:

```text
The due for the billing cycle
28 August 2026 to 27 September 2026
has already been generated.
```

This prevents duplicate due amounts and duplicate Back Office cutting requests.

---

# 17. Early Generation Restriction

If the Cost Cutting Date is the 28th, the system should normally prevent generating the cycle before the Cost Cutting Date.

Example:

```text
Today: 25 September
Cost Cutting Date: 28
```

The system should not normally allow the September due to be finalized yet.

Message:

```text
The due generation date is 28 September.
The current cycle cannot be finalized before the due generation date.
```

However, the system may provide a **Preview** option if the business requires users to review the expected due before the actual generation date.

---

# 18. Late Generation

If the user forgets to generate the due on the Cost Cutting Date, the system should allow an authorized user to generate the missed cycle later.

Example:

```text
Scheduled Cost Cutting Date:
28 September

Actual Generation:
30 September
```

The billing period remains:

```text
28 August → 27 September
```

It must NOT become:

```text
30 August → 29 September
```

The system should record the actual generation timestamp for audit purposes.

Example:

```text
Due Cycle Date:
28 September 2026

Actual Generated At:
30 September 2026 11:20 AM

Generation Status:
Generated Late
```

---

# 19. Transactions Around the Cycle Boundary

The system must use the billing period boundaries consistently.

For a cycle:

```text
Start:
28 August 00:00:00

End:
27 September 23:59:59
```

Examples:

```text
27 Sep 11:59:59 PM
→ Included in current cycle

28 Sep 12:00:00 AM
→ Included in next cycle
```

A transaction exactly at the start boundary belongs to the new cycle.

Recommended technical condition:

```text
transaction_time >= cycle_start
AND
transaction_time < next_cycle_start
```

This is preferable to relying on `23:59:59` calculations because it avoids precision problems.

---

# 20. Recommended Database Information

Each customer due record should maintain at least:

```text
id
customer_id

billing_cycle_start
billing_cycle_end

new_due_amount
previous_outstanding_amount
total_due_amount

sent_to_backoffice_amount
actually_cut_amount
remaining_due_amount

status

due_cycle_date
generated_at

created_at
updated_at
```

### Example Record

```text
Customer ID:
1001

Due Cycle Date:
28 September 2026

Billing Cycle Start:
28 August 2026 00:00:00

Billing Cycle End:
27 September 2026 23:59:59

Previous Outstanding:
৳500

Current New Due:
৳100

Total Due:
৳600

Sent to Back Office:
৳600

Actually Cut:
৳400

Remaining Due:
৳200

Status:
Partially Cut

Generated At:
28 September 2026 10:15 PM
```

---

# 21. Important Business Rules

The implementation must follow these rules:

1. **The Cost Cutting Date determines the monthly billing-cycle boundary.**

2. **The billing cycle starts at 00:00:00 on the Cost Cutting Date.**

3. **The billing cycle ends immediately before the next Cost Cutting Date.**

4. For a Cost Cutting Date of the 28th:

   ```text
   28 Aug → 27 Sep
   28 Sep → 27 Oct
   28 Oct → 27 Nov
   ```

5. **Actual generation time does not change the billing period.**

6. **Due generation should normally be allowed only on or after the configured Cost Cutting Date.**

7. **Late generation is allowed for missed cycles, but the original billing period must be preserved.**

8. **The same billing cycle cannot be generated more than once.**

9. **The amount actually cut by the Back Office must be recorded separately from the amount sent to the Back Office.**

10. **Any amount not actually cut remains outstanding.**

11. **Outstanding amounts are automatically carried forward to the next cycle.**

12. **Current total due = Previous Outstanding Due + Current Cycle New Due.**

13. **A fully cut amount produces zero outstanding balance.**

14. **A partially cut amount carries only the remaining balance forward.**

15. **A zero-cut amount carries the entire outstanding amount forward.**

16. **Billing-cycle boundaries must be based on date/time boundaries, not the time when the operator generates the due list.**

---

# 22. Complete Example

Assume:

```text
Cost Cutting Date = 28th of every month
```

### August

Billing cycle:

```text
28 July → 27 August
```

New customer due:

```text
৳500
```

Back Office cuts:

```text
৳0
```

Outstanding:

```text
৳500
```

---

### September

Billing cycle:

```text
28 August → 27 September
```

New September due:

```text
৳100
```

Previous outstanding:

```text
৳500
```

Total:

```text
৳500 + ৳100 = ৳600
```

Back Office cuts:

```text
৳400
```

Remaining:

```text
৳600 - ৳400 = ৳200
```

---

### October

Billing cycle:

```text
28 September → 27 October
```

New October due:

```text
৳150
```

Previous outstanding:

```text
৳200
```

Total:

```text
৳200 + ৳150 = ৳350
```

If Back Office cuts the full:

```text
৳350
```

Remaining:

```text
৳0
```

---

# 23. Final Calculation Formula

For every customer:

```text
Total Due
=
Previous Outstanding Due
+
Current Billing Cycle Due
```

After Back Office reconciliation:

```text
Remaining Due
=
Total Due
-
Actually Cut Amount
```

The `Remaining Due` becomes the `Previous Outstanding Due` for the next billing cycle.

Therefore:

```text
Previous Outstanding
        ↓
Current Cycle New Due
        ↓
      Total Due
        ↓
Back Office Cutting
        ↓
Actually Cut
        ↓
Remaining Outstanding
        ↓
Carried Forward
        ↓
Next Billing Cycle
```

This creates a continuous and auditable monthly cost-cutting cycle without losing any customer due amount or transaction period.
