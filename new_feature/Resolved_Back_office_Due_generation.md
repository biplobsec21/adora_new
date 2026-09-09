# Canteen Cost Cutting – Corrected Due Generation & Reconciliation Specification

## 1. Core Billing Cycle Rule

A customer’s billing cycle is a recurring monthly period anchored to the customer’s due-generation date.

Each cycle begins on the Cost Cutting Date and ends immediately before the next Cost Cutting Date.

For example, if the Cost Cutting Date is the **28th of every month**:

```text
28 August 00:00:00 → 27 September 23:59:59
28 September 00:00:00 → 27 October 23:59:59
28 October 00:00:00 → 27 November 23:59:59
```

The due for a cycle is generated on its corresponding Cost Cutting Date.

The actual time when the due-generation process is executed does not change the billing period.

---

# 2. Cost Cutting Date

The system must have a configured Cost Cutting Date.

Example:

```text
Cost Cutting Date = 28
```

This means the system generates the due list on the 28th of every month.

The billing period for the due generated on 28 September is:

```text
28 August 00:00:00
→
27 September 23:59:59
```

The next billing period begins:

```text
28 September 00:00:00
```

---

# 3. First Cycle

The first cycle must also follow the configured Cost Cutting Date.

The system should establish the first billing-cycle start date based on the initial Cost Cutting Date configuration.

For example, if the first Cost Cutting Date is **28 August 2026**, then the first cycle is:

```text
Cycle Start:
28 July 2026 00:00:00

Cycle End:
27 August 2026 23:59:59

Due Generation Date:
28 August 2026
```

There is no previous outstanding due for the first cycle.

Therefore:

```text
Previous Outstanding Due = ৳0
Current Cycle New Due    = ৳500
--------------------------------
Total Due                = ৳500
```

---

# 4. Subsequent Cycles

For subsequent cycles, the system uses the configured Cost Cutting Date to determine the billing period.

Example:

Previous Cost Cutting Date:

```text
28 August 2026
```

Next Cost Cutting Date:

```text
28 September 2026
```

The new billing cycle is:

```text
Start:
28 August 2026 00:00:00

End:
27 September 2026 23:59:59
```

The customer's total due is:

```text
Previous Outstanding Due
+
Current Cycle New Due
=
Total Due
```

Example:

```text
Previous Outstanding Due = ৳500
Current Cycle New Due    = ৳100
--------------------------------
Total Due                = ৳600
```

---

# 5. Cost Cutting List Generation

After the due generation process is completed, the system generates the **Cost Cutting List** for the Back Office.

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

The `Cutting Amount` represents the customer's **total outstanding amount that should be cut**, including any previous unpaid amount carried forward.

---

# 6. Back Office Processing

The Back Office processes the Cost Cutting List externally.

The Back Office does **not** need to return a list containing unsuccessful customers.

Instead:

> **The Back Office will return only the customer records for which cost cutting was successfully completed.**

For example, the system sends:

| Customer ID | Customer Name | Customer Number | Cutting Amount |
| ----------- | ------------- | --------------- | -------------: |
| 1001        | Customer A    | 017XXXXXXXX     |           ৳600 |
| 1002        | Customer B    | 018XXXXXXXX     |           ৳350 |
| 1003        | Customer C    | 019XXXXXXXX     |           ৳800 |
| 1004        | Customer D    | 016XXXXXXXX     |           ৳250 |

The Back Office successfully cuts only:

| Customer ID | Customer Name | Customer Number | Cut Amount |
| ----------- | ------------- | --------------- | ---------: |
| 1001        | Customer A    | 017XXXXXXXX     |       ৳600 |
| 1003        | Customer C    | 019XXXXXXXX     |       ৳800 |

Customers 1002 and 1004 are **not included** in the returned file.

This means:

```text
Customer 1001 → Successfully Cut
Customer 1002 → Not Cut / Unpaid
Customer 1003 → Successfully Cut
Customer 1004 → Not Cut / Unpaid
```

---

# 7. Back Office Reconciliation Rule

The returned Back Office file is treated as the **successful cutting confirmation**.

The system must compare:

```text
Original Cost Cutting List
        VS
Back Office Successful Cutting List
```

For every customer:

### Customer exists in returned file

The customer is considered:

```text
Successfully Cut
```

The returned record should be used to confirm the cut amount.

### Customer does not exist in returned file

The customer is considered:

```text
Not Cut / Unpaid
```

The full outstanding amount must remain unpaid and be carried forward to the next cycle.

---

# 8. Important Rule: Missing Customer = Unpaid

The system must **not require the Back Office to explicitly send failed customers**.

If a customer was included in the original Cost Cutting List but does not appear in the successful cutting result:

```text
Customer was not successfully cut.
```

Therefore:

```text
Remaining Due = Total Due
```

The amount must be carried forward to the next cycle.

---

# 9. Example – Customer Not Cut

September cycle:

```text
Previous Outstanding Due = ৳500
Current Cycle New Due    = ৳100
--------------------------------
Total Due                = ৳600
```

Customer is included in the Back Office list:

```text
Customer A → ৳600
```

But Customer A does **not** appear in the Back Office successful-cutting file.

Therefore:

```text
Cut Amount        = ৳0
Remaining Due     = ৳600
Status            = Unpaid
```

The entire ৳600 is carried forward.

---

# 10. Next Month After Failed Cutting

Suppose the next month's new due is:

```text
৳200
```

The previous unpaid amount is:

```text
৳600
```

Therefore:

```text
Previous Outstanding Due = ৳600
Current Cycle New Due    = ৳200
--------------------------------
Total Due                = ৳800
```

The new Cost Cutting List should contain:

```text
Customer A → ৳800
```

---

# 11. Example – Successfully Cut Customer

Suppose:

```text
Total Due = ৳600
```

The customer appears in the Back Office successful-cutting file:

```text
Customer A → ৳600
```

Therefore:

```text
Cut Amount    = ৳600
Remaining Due = ৳0
Status        = Fully Cut
```

Nothing is carried forward.

---

# 12. Example – Multiple Customers

Original Cost Cutting List:

| Customer | Total Due |
| -------- | --------: |
| A        |      ৳600 |
| B        |      ৳350 |
| C        |      ৳800 |
| D        |      ৳250 |

Back Office successful result:

| Customer | Successfully Cut |
| -------- | ---------------: |
| A        |             ৳600 |
| C        |             ৳800 |

Reconciliation result:

| Customer | Total Due | Cut Amount | Remaining | Status    |
| -------- | --------: | ---------: | --------: | --------- |
| A        |      ৳600 |       ৳600 |        ৳0 | Fully Cut |
| B        |      ৳350 |         ৳0 |      ৳350 | Unpaid    |
| C        |      ৳800 |       ৳800 |        ৳0 | Fully Cut |
| D        |      ৳250 |         ৳0 |      ৳250 | Unpaid    |

The next cycle therefore starts with:

```text
Customer A → ৳0
Customer B → ৳350
Customer C → ৳0
Customer D → ৳250
```

If the next cycle generates new dues:

| Customer | Previous Outstanding | New Due | Next Total Due |
| -------- | -------------------: | ------: | -------------: |
| A        |                   ৳0 |    ৳100 |           ৳100 |
| B        |                 ৳350 |    ৳100 |           ৳450 |
| C        |                   ৳0 |    ৳200 |           ৳200 |
| D        |                 ৳250 |     ৳50 |           ৳300 |

---

# 13. No Partial Cutting Assumption

Under the current Back Office process, the system should assume that a customer is either:

```text
Successfully Cut
```

or:

```text
Not Cut
```

There is no partial-cut scenario unless the Back Office result file explicitly provides an actual cut amount.

Therefore, by default:

```text
Customer exists in successful result
→ Fully Cut

Customer does not exist in successful result
→ Not Cut
→ Full amount carried forward
```

If partial cutting is required in the future, the result file can include an `Actual Cut Amount` field and the reconciliation logic can be extended.

---

# 14. Due Status

Recommended statuses for the due/cost-cutting record:

### Generated

Due has been calculated and finalized.

### Sent to Back Office

The Cost Cutting List has been generated/exported and submitted.

### Fully Cut

Customer appears in the successful Back Office result and the full amount was cut.

### Unpaid

Customer was included in the Cost Cutting List but was not included in the successful Back Office result.

### Carried Forward

An unpaid amount has been carried to the next billing cycle.

A customer can therefore have a history such as:

```text
September:
Total Due = ৳600
Status = Unpaid
        ↓
October:
Previous Outstanding = ৳600
New Due = ৳200
Total Due = ৳800
        ↓
Status = Fully Cut
```

---

# 15. Reconciliation Process

The reconciliation process should work as follows:

```text
1. Generate current billing-cycle dues
             ↓
2. Add previous outstanding amounts
             ↓
3. Calculate Total Due
             ↓
4. Generate Cost Cutting List
             ↓
5. Export CSV/XLSX
             ↓
6. Back Office performs cutting
             ↓
7. Back Office returns ONLY successfully cut customers
             ↓
8. Import Back Office result
             ↓
9. Match returned customers with Cost Cutting List
             ↓
10. Matched customer = Successfully Cut
             ↓
11. Missing customer = Unpaid
             ↓
12. Carry unpaid amount forward
             ↓
13. Generate next billing cycle
```

---

# 16. Customer-Level Calculation

For every billing cycle:

```text
Previous Outstanding Due
+
Current Cycle New Due
=
Total Due
```

After reconciliation:

### Successfully Cut

```text
Remaining Due = ৳0
```

### Not Cut

```text
Remaining Due = Total Due
```

The remaining amount becomes the previous outstanding amount for the next cycle.

---

# 17. Final Business Rule

The most important business rule for the module is:

> **The billing cycle is determined by the configured Cost Cutting Date, not by the actual time at which the due list is generated. The cycle begins at 00:00:00 on the Cost Cutting Date and ends immediately before the next Cost Cutting Date. When the Back Office returns the cutting result, it returns only customers for whom cutting was successfully completed. Any customer included in the original Cost Cutting List but missing from the Back Office successful result is considered unpaid, and the customer's entire outstanding amount is automatically carried forward to the next billing cycle.**

### Example

If:

```text
Cost Cutting Date = 28th
```

Then:

```text
28 August 00:00:00
→
27 September 23:59:59
```

is one billing cycle.

If Customer A has:

```text
Previous Outstanding = ৳500
Current New Due      = ৳100
Total Due            = ৳600
```

and Customer A is **not present** in the Back Office successful-cutting file:

```text
Cut Amount       = ৳0
Remaining Due    = ৳600
Status           = Unpaid
```

Next month, if the new due is ৳150:

```text
Previous Outstanding = ৳600
New Due              = ৳150
--------------------------------
Total Due            = ৳750
```

Therefore, the next Cost Cutting List will request:

```text
Customer A → ৳750
```

If Customer A is successfully cut for ৳750, the outstanding balance becomes:

```text
৳0
```

and nothing is carried forward.
