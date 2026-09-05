Here is the complete and updated specification document for your End of Day (EOD) Closing Module, incorporating the dynamic role permissions and comprehensive auditing rules we discussed. You can save this directly as a `.md` file.

---

# End of Day (EOD) Closing Module Specification

## 1. Module Overview

The End of Day (EOD) Closing module provides a secure, auditable mechanism to finalize daily financial operations. It calculates total cash collections, outstanding dues, and expenses dynamically. It allows authorized users to input manual cash adjustments, view detailed transaction breakdowns, and take a static snapshot of the day's financials upon closing. It features a flexible retroactive closing mechanism to handle missed EOD processes without disrupting operations, all governed by a dynamic Role-Based Access Control (RBAC) and universal audit logging system.

---

## 2. Functional Requirements

* **Dynamic Dashboard:** The interface will calculate and display "Today's Collected Cash", "Today's Expenses", and "Today's Due" dynamically based on the transactions of the selected date.
* **Transaction Breakdown (Transparency):** Each summarized figure on the dashboard will feature a "View Details" button. Clicking this will open a modal displaying a tabular breakdown of the exact line-item transactions forming that total.
* **Manual Cash Adjustments (Free-Form):** A dedicated form allowing users to record physical cash additions (Cash In) or deductions (Cash Out) for the selected date.
* **Day Close Snapshot (Submit):** A final "Close Day" submission action that saves the dynamically calculated totals and manual adjustments into a static, historical record.
* **Pending EOD Warning (Retroactive Workflow):** If a previous day is not closed, the system dashboard will display a persistent, highly visible warning: *"Pending EOD for [Date]"*. This alert will not block new sales.
* **Retroactive Closing:** A calendar dropdown in the EOD module that allows authorized users to select a missed past date. All snapshot queries will strictly filter transactions matching that specific missed date rather than the current system date.
* **Historical Reporting:** A dedicated, optimized, and printable DataTables view displaying date-wise daily cash collection summaries.

---

## 3. Database Schema Requirements

**Table: `db_daily_closing**`
Records the immutable snapshot when a day is finalized.

| Column Name | Data Type | Description |
| --- | --- | --- |
| `id` | int | Primary Key |
| `closing_date` | date | The specific date being closed |
| `total_sales_due` | double(20,2) | Unpaid sales generated on this date |
| `total_cash_collected` | double(20,2) | Cash received (sales payments + previous dues) |
| `total_expenses` | double(20,2) | Operational expenses for the date |
| `custom_cash_additions` | double(20,2) | Sum of manual cash-in entries |
| `custom_cash_deductions` | double(20,2) | Sum of manual cash-out entries |
| `final_cash_in_hand` | double(20,2) | Calculated final physical cash |
| `closing_type` | varchar(20) | Enum: 'Regular' or 'Retroactive' (Audit trail) |
| `created_by` | varchar(50) | Username who closed the day |
| `system_ip` | varchar(50) | IP address of the user |

**Table: `db_cash_adjustments**`
Stores the manual free-form entries.

| Column Name | Data Type | Description |
| --- | --- | --- |
| `id` | int | Primary Key |
| `closing_date` | date | The date this adjustment applies to |
| `type` | varchar(20) | Enum: 'Addition' or 'Deduction' |
| `amount` | double(20,2) | The adjusted amount |
| `note` | text | Justification for the adjustment |
| `created_by` | varchar(50) | Username who created the entry |
| `system_ip` | varchar(50) | IP address of the user |

**Table: `db_audit_logs**`
Tracks unauthorized or privileged modifications.

| Column Name | Data Type | Description |
| --- | --- | --- |
| `id` | int | Primary Key |
| `table_name` | varchar(50) | The table modified (e.g., db_cash_adjustments, db_sales) |
| `record_id` | int | The ID of the modified row |
| `action` | varchar(20) | Enum: 'UPDATE' or 'DELETE' |
| `old_value` | text | JSON payload of the data before modification |
| `changed_by` | varchar(50) | Username who performed the action |
| `system_ip` | varchar(50) | IP address of the user |

---

## 4. Business & Security Rules

* **Dynamic Role-Based Access Control (RBAC):**
* The ability to perform a **Retroactive Close** (closing a missed past date) is controlled by a specific dynamic permission (e.g., `can_retroactive_close`) set in the system's role/permission module.
* Standard users without this permission can only view and close the current system date.


* **Dynamic Locking & Override Mechanism:**
* Once a day is closed (Regular or Retroactive) and the snapshot is saved, that date is flagged as "Locked".
* Editing invoices, adding new sales, or posting backdated payments for a locked date is disabled by default.
* Users who have been explicitly granted an override permission (e.g., `can_edit_closed_data`) via the role permission settings can bypass this lock to make necessary corrections.


* **Universal Audit Logging (Strict Traceability):**
* *Any* modification made to a locked date (whether editing a manual cash adjustment, altering an invoice, or posting a backdated payment) **must** be recorded in the `db_audit_logs` table.
* The audit log must capture the exact table modified, the specific record ID, the action type (Update/Delete), a JSON snapshot of the old data, the username of the person who made the change, and their System IP address.


* **Snapshot Recalculation:**
* If an authorized user edits an invoice or payment on a previously closed day using their override permissions, the system must either prompt them to re-close/update the EOD snapshot for that day, or automatically recalculate and update the `db_daily_closing` table to reflect the new totals.
* This recalculation event must also be logged in the audit trail alongside the user's IP address.