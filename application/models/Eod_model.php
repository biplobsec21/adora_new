<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Eod_model extends CI_Model
{
    public function get_summary($closing_date)
    {
        $summary = array(
            'closing_date' => $closing_date,
            'total_sales_due' => 0,
            'total_cash_collected' => 0,
            'total_purchase_paid' => 0,
            'total_loan_given' => 0,
            'total_loan_repayments_received' => 0,
            'total_expenses' => 0,
            'custom_cash_additions' => 0,
            'custom_cash_deductions' => 0,
            'opening_cash' => 0,
            'final_cash_in_hand' => 0,
            'late_entry_amount' => 0,
            'closing_type' => null,
            'closed_by' => null,
            'closed_at' => null,
        );

        $live_summary = $this->get_live_summary($closing_date);
        foreach ($live_summary as $key => $value) {
            $summary[$key] = $value;
        }

        $closing = $this->db->get_where('db_daily_closing', array('closing_date' => $closing_date))->row();
        if ($closing) {
            $summary['total_sales_due'] = (float) $closing->total_sales_due;
            $summary['total_cash_collected'] = (float) $closing->total_cash_collected;
            $summary['total_purchase_paid'] = (float) $closing->total_purchase_paid;
            $summary['total_loan_given'] = (float) $closing->total_loan_given;
            $summary['total_loan_repayments_received'] = (float) $closing->total_loan_repayments_received;
            $summary['total_expenses'] = (float) $closing->total_expenses;
            $summary['custom_cash_additions'] = (float) $closing->custom_cash_additions;
            $summary['custom_cash_deductions'] = (float) $closing->custom_cash_deductions;
            $summary['opening_cash'] = $this->get_opening_cash($closing_date);
            $summary['final_cash_in_hand'] = (float) $closing->final_cash_in_hand;
            $summary['late_entry_amount'] = (float) $closing->late_entry_amount;
            $summary['closing_type'] = $closing->closing_type;
            $summary['closed_by'] = $closing->created_by;
            $summary['closed_at'] = $closing->created_at;
        }

        return $summary;
    }

    public function get_report($from_date, $to_date)
    {
        $sql = "SELECT
                    c.id,
                    c.closing_date,
                    c.closing_type,
                    c.total_sales_due,
                    c.total_cash_collected,
                    c.total_purchase_paid,
                    c.total_loan_given,
                    c.total_loan_repayments_received,
                    c.total_expenses,
                    c.custom_cash_additions,
                    c.custom_cash_deductions,
                    c.final_cash_in_hand,
                    c.late_entry_amount,
                    c.created_by,
                    c.created_at,
                    COALESCE(a.adjustment_count, 0) AS adjustment_count
                FROM db_daily_closing AS c
                LEFT JOIN (
                    SELECT closing_date, COUNT(*) AS adjustment_count
                    FROM db_cash_adjustments
                    GROUP BY closing_date
                ) AS a ON a.closing_date = c.closing_date
                WHERE c.closing_date BETWEEN ? AND ?
                ORDER BY c.closing_date DESC";

        return $this->db->query($sql, array($from_date, $to_date))->result();
    }

    public function get_detail_page($type, $closing_date, $page, $per_page = 50)
    {
        $page = max(1, (int) $page);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        if ($type === 'collected_cash') {
            $from = 'db_salespayments';
            $where = 'payment_date = ? AND status = 1 AND payment > 0';
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(payment), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, sales_id, payment_date AS transaction_date,
                                payment_type, payment AS amount, payment_note AS note,
                                created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } elseif ($type === 'purchase_payments') {
            $from = 'db_purchasepayments';
            $where = 'payment_date = ? AND status = 1 AND payment > 0';
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(payment), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, purchase_id, payment_date AS transaction_date,
                                payment_type, payment AS amount, payment_note AS note,
                                created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } elseif ($type === 'loan_given') {
            $from = 'db_customer_loan_transactions';
            $where = "transaction_date = ? AND transaction_type = 'LOAN_ISSUE'";
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(amount), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, loan_id, transaction_date AS transaction_date,
                                payment_method, amount, note, created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } elseif ($type === 'loan_repayments') {
            $from = 'db_customer_loan_transactions';
            $where = "transaction_date = ? AND transaction_type = 'REPAYMENT'";
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(amount), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, loan_id, transaction_date AS transaction_date,
                                payment_method, amount, note, created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } elseif ($type === 'sales_due') {
            $from = 'db_sales';
            $where = "sales_date = ? AND sales_status = 'Final' AND status = 1 AND COALESCE(grand_total, 0) > COALESCE(paid_amount, 0)";
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(COALESCE(grand_total, 0) - COALESCE(paid_amount, 0)), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, sales_code, sales_date AS transaction_date,
                                customer_id, grand_total, paid_amount,
                                COALESCE(grand_total, 0) - COALESCE(paid_amount, 0) AS amount,
                                payment_status, created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } elseif ($type === 'expenses') {
            $from = 'db_expense';
            $where = 'expense_date = ? AND status = 1';
            $params = array($closing_date);
            $count_sql = "SELECT COUNT(*) AS total FROM {$from} WHERE {$where}";
            $total_sql = "SELECT COALESCE(SUM(expense_amt), 0) AS total_amount FROM {$from} WHERE {$where}";
            $data_sql = "SELECT id, expense_code, expense_date AS transaction_date,
                                expense_for, reference_no, expense_amt AS amount,
                                note, created_by
                         FROM {$from}
                         WHERE {$where}
                         ORDER BY id DESC
                         LIMIT {$per_page} OFFSET {$offset}";
        } else {
            return false;
        }

        $count = $this->db->query($count_sql, $params)->row();
        $amount = $this->db->query($total_sql, $params)->row();
        $rows = $this->db->query($data_sql, $params)->result_array();
        $total = (int) $count->total;

        return array(
            'type' => $type,
            'date' => $closing_date,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_amount' => (float) $amount->total_amount,
            'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
            'data' => $rows,
        );
    }

    public function add_adjustment($closing_date, $type, $amount, $note, $created_by, $system_ip, $is_override = false)
    {
        $this->db->trans_begin();
        $new_value = array(
            'closing_date' => $closing_date,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'created_by' => $created_by,
            'system_ip' => $system_ip,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        );
        $inserted = $this->db->insert('db_cash_adjustments', $new_value);

        if ($inserted && $is_override) {
            $this->write_audit($closing_date, 'db_cash_adjustments', $this->db->insert_id(), 'INSERT', array(
                'closing_date' => $closing_date,
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
            ), $new_value, $created_by, $system_ip);
            $this->refresh_closing_snapshot($closing_date, $created_by, $system_ip);
        }

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $inserted;
    }

    public function get_adjustments($closing_date)
    {
        return $this->db->where('closing_date', $closing_date)
            ->order_by('id', 'DESC')
            ->get('db_cash_adjustments')
            ->result();
    }

    public function get_audit_logs($closing_date)
    {
        return $this->db->where('affected_date', $closing_date)
            ->order_by('id', 'DESC')
            ->get('db_audit_logs')
            ->result();
    }

    public function update_adjustment($id, $type, $amount, $note, $changed_by, $system_ip)
    {
        $adjustment = $this->db->get_where('db_cash_adjustments', array('id' => $id))->row();
        if (!$adjustment) {
            return array('success' => false, 'message' => 'Cash adjustment not found.');
        }

        $this->db->trans_begin();
        $new_value = array(
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
        );
        $this->write_audit($adjustment->closing_date, 'db_cash_adjustments', $id, 'UPDATE', $adjustment, $new_value, $changed_by, $system_ip);
        $this->db->where('id', $id)->update('db_cash_adjustments', $new_value);
        $this->refresh_closing_snapshot($adjustment->closing_date, $changed_by, $system_ip);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Cash adjustment could not be updated.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Cash adjustment updated successfully.');
    }

    public function delete_adjustment($id, $changed_by, $system_ip)
    {
        $adjustment = $this->db->get_where('db_cash_adjustments', array('id' => $id))->row();
        if (!$adjustment) {
            return array('success' => false, 'message' => 'Cash adjustment not found.');
        }

        $this->db->trans_begin();
        $this->write_audit($adjustment->closing_date, 'db_cash_adjustments', $id, 'DELETE', $adjustment, null, $changed_by, $system_ip);
        $this->db->where('id', $id)->delete('db_cash_adjustments');
        $this->refresh_closing_snapshot($adjustment->closing_date, $changed_by, $system_ip);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Cash adjustment could not be deleted.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Cash adjustment deleted successfully.');
    }

    public function get_pending_date($current_date, $start_date)
    {
        $query = $this->db->query(
            "SELECT MIN(activity_date) AS pending_date
             FROM (
                SELECT sales_date AS activity_date
                FROM db_sales
                WHERE sales_date < ? AND status = 1
                UNION
                SELECT expense_date AS activity_date
                FROM db_expense
                WHERE expense_date < ? AND status = 1
                UNION
                SELECT payment_date AS activity_date
                FROM db_salespayments
                WHERE payment_date < ? AND status = 1
                UNION
                SELECT payment_date AS activity_date
                FROM db_purchasepayments
                WHERE payment_date < ? AND status = 1
                UNION
                SELECT transaction_date AS activity_date
                FROM db_customer_loan_transactions
                WHERE transaction_date < ?
             ) AS activity_dates
             LEFT JOIN db_daily_closing AS closing ON closing.closing_date = activity_dates.activity_date
                         WHERE closing.id IS NULL
                             AND activity_dates.activity_date >= ?",
            array($current_date, $current_date, $current_date, $current_date, $current_date, $start_date)
        )->row();

        return $query ? $query->pending_date : null;
    }

    public function get_late_collected_cash($closing_date)
    {
        if (!is_string($closing_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $closing_date)) {
            return array(
                'previous_date' => null,
                'late_amount' => 0,
                'saved_total_cash_collected' => 0,
                'live_total_cash_collected' => 0,
            );
        }

        $previous_date = date('Y-m-d', strtotime($closing_date . ' -1 day'));
        $previous_closing = $this->db->get_where('db_daily_closing', array('closing_date' => $previous_date))->row();

        if (!$previous_closing) {
            return array(
                'previous_date' => $previous_date,
                'late_amount' => 0,
                'saved_total_cash_collected' => 0,
                'live_total_cash_collected' => 0,
            );
        }

        $live_payment = $this->db->query(
            "SELECT COALESCE(SUM(payment), 0) AS live_total_cash_collected
             FROM db_salespayments
             WHERE payment_date = ?
               AND status = 1
               AND payment > 0",
            array($previous_date)
        )->row();

        $saved_total_cash_collected = (float) $previous_closing->total_cash_collected;
        $live_total_cash_collected = (float) $live_payment->live_total_cash_collected;
        $late_amount = max(0, $live_total_cash_collected - $saved_total_cash_collected);

        return array(
            'previous_date' => $previous_date,
            'late_amount' => $late_amount,
            'saved_total_cash_collected' => $saved_total_cash_collected,
            'live_total_cash_collected' => $live_total_cash_collected,
        );
    }

    public function get_late_entry_summary($closing_date)
    {
        if (!is_string($closing_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $closing_date)) {
            return array();
        }

        $previous_date = date('Y-m-d', strtotime($closing_date . ' -1 day'));
        $previous_closing = $this->db->get_where('db_daily_closing', array('closing_date' => $previous_date))->row();

        if (!$previous_closing) {
            return array();
        }

        $live_summary = $this->get_live_summary($previous_date);
        $stored_summary = array(
            'total_sales_due' => (float) $previous_closing->total_sales_due,
            'total_cash_collected' => (float) $previous_closing->total_cash_collected,
            'total_purchase_paid' => (float) $previous_closing->total_purchase_paid,
            'total_loan_given' => (float) $previous_closing->total_loan_given,
            'total_loan_repayments_received' => (float) $previous_closing->total_loan_repayments_received,
            'total_expenses' => (float) $previous_closing->total_expenses,
            'custom_cash_additions' => (float) $previous_closing->custom_cash_additions,
            'custom_cash_deductions' => (float) $previous_closing->custom_cash_deductions,
            'final_cash_in_hand' => (float) $previous_closing->final_cash_in_hand,
        );

        $mismatch_fields = array(
            'total_sales_due',
            'total_cash_collected',
            'total_purchase_paid',
            'total_loan_given',
            'total_loan_repayments_received',
            'total_expenses',
            'custom_cash_additions',
            'custom_cash_deductions',
            'final_cash_in_hand',
        );

        $mismatch_details = array();
        foreach ($mismatch_fields as $field) {
            $stored_value = isset($stored_summary[$field]) ? (float) $stored_summary[$field] : 0;
            $live_value = isset($live_summary[$field]) ? (float) $live_summary[$field] : 0;

            if ((float) $stored_value !== (float) $live_value) {
                $mismatch_details[] = array(
                    'field' => $field,
                    'stored_value' => $stored_value,
                    'live_value' => $live_value,
                );
            }
        }

        if (empty($mismatch_details)) {
            return array();
        }

        return array(
            'previous_date' => $previous_date,
            'mismatch_details' => $mismatch_details,
            'live_summary' => $live_summary,
            'stored_summary' => $stored_summary,
        );
    }

    public function close_day($closing_date, $closing_type, $created_by, $system_ip)
    {
        $this->db->trans_begin();

        $existing = $this->db->get_where('db_daily_closing', array('closing_date' => $closing_date))->row();
        if ($existing) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'This date has already been closed.');
        }

        $late_entry_info = $this->get_late_collected_cash($closing_date);
        $previous_date = $late_entry_info['previous_date'];
        $late_entry_amount = (float) $late_entry_info['late_amount'];

        if ($previous_date && $late_entry_amount > 0) {
            $previous_closing = $this->db->get_where('db_daily_closing', array('closing_date' => $previous_date))->row();
            if ($previous_closing) {
                $previous_summary = $this->get_live_summary($previous_date);
                $updated_final_cash = (float) $previous_summary['final_cash_in_hand'];

                $new_value = array(
                    'total_sales_due' => (float) $previous_summary['total_sales_due'],
                    'total_cash_collected' => (float) $previous_summary['total_cash_collected'],
                    'total_purchase_paid' => (float) $previous_summary['total_purchase_paid'],
                    'total_loan_given' => (float) $previous_summary['total_loan_given'],
                    'total_loan_repayments_received' => (float) $previous_summary['total_loan_repayments_received'],
                    'total_expenses' => (float) $previous_summary['total_expenses'],
                    'custom_cash_additions' => (float) $previous_summary['custom_cash_additions'],
                    'custom_cash_deductions' => (float) $previous_summary['custom_cash_deductions'],
                    'final_cash_in_hand' => $updated_final_cash,
                    'late_entry_amount' => $late_entry_amount,
                );
                $this->write_audit($previous_date, 'db_daily_closing', $previous_closing->id, 'LATE_ENTRY', $previous_closing, $new_value, $created_by, $system_ip);
                $this->db->where('id', $previous_closing->id)->update('db_daily_closing', $new_value);
            }
        }

        $summary = $this->get_live_summary($closing_date);
        $this->db->insert('db_daily_closing', array(
            'closing_date' => $closing_date,
            'total_sales_due' => (float) $summary['total_sales_due'],
            'total_cash_collected' => (float) $summary['total_cash_collected'],
            'total_purchase_paid' => (float) $summary['total_purchase_paid'],
            'total_loan_given' => (float) $summary['total_loan_given'],
            'total_loan_repayments_received' => (float) $summary['total_loan_repayments_received'],
            'total_expenses' => (float) $summary['total_expenses'],
            'custom_cash_additions' => (float) $summary['custom_cash_additions'],
            'custom_cash_deductions' => (float) $summary['custom_cash_deductions'],
            'final_cash_in_hand' => (float) $summary['final_cash_in_hand'],
            'late_entry_amount' => 0,
            'closing_type' => $closing_type,
            'created_by' => $created_by,
            'system_ip' => $system_ip,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ));

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The day could not be closed.');
        }

        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Day closed successfully.');
    }

    public function reclose_day($closing_date, $closing_type, $created_by, $system_ip)
    {
        $this->db->trans_begin();

        $existing = $this->db->get_where('db_daily_closing', array('closing_date' => $closing_date))->row();
        if (!$existing) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'There is no existing close to refresh.');
        }

        $summary = $this->get_live_summary($closing_date);
        $new_value = array(
            'total_sales_due' => (float) $summary['total_sales_due'],
            'total_cash_collected' => (float) $summary['total_cash_collected'],
            'total_purchase_paid' => (float) $summary['total_purchase_paid'],
            'total_loan_given' => (float) $summary['total_loan_given'],
            'total_loan_repayments_received' => (float) $summary['total_loan_repayments_received'],
            'total_expenses' => (float) $summary['total_expenses'],
            'custom_cash_additions' => (float) $summary['custom_cash_additions'],
            'custom_cash_deductions' => (float) $summary['custom_cash_deductions'],
            'final_cash_in_hand' => (float) $summary['final_cash_in_hand'],
            'closing_type' => $closing_type,
            'created_by' => $created_by,
            'system_ip' => $system_ip,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        );
        $this->write_audit($closing_date, 'db_daily_closing', $existing->id, 'UPDATE', $existing, $new_value, $created_by, $system_ip);
        $updated = $this->db->where('id', $existing->id)->update('db_daily_closing', $new_value);

        if (!$this->db->trans_status() || !$updated) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The day could not be refreshed.');
        }

        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Day refreshed successfully.');
    }

    public function get_live_summary($closing_date)
    {
        $summary = array(
            'closing_date' => $closing_date,
            'total_sales_due' => 0,
            'total_cash_collected' => 0,
            'total_purchase_paid' => 0,
            'total_loan_given' => 0,
            'total_loan_repayments_received' => 0,
            'total_expenses' => 0,
            'custom_cash_additions' => 0,
            'custom_cash_deductions' => 0,
            'opening_cash' => $this->get_opening_cash($closing_date),
            'final_cash_in_hand' => 0,
        );

        $sales = $this->db->query(
            "SELECT
                COALESCE(SUM(GREATEST(COALESCE(grand_total, 0) - COALESCE(paid_amount, 0), 0)), 0) AS total_sales_due
             FROM db_sales
             WHERE sales_date = ?
               AND sales_status = 'Final'
               AND status = 1",
            array($closing_date)
        )->row();
        $summary['total_sales_due'] = (float) $sales->total_sales_due;

        $payments = $this->db->query(
            "SELECT COALESCE(SUM(payment), 0) AS total_cash_collected
             FROM db_salespayments
             WHERE payment_date = ?
               AND status = 1
               AND payment > 0",
            array($closing_date)
        )->row();
        $summary['total_cash_collected'] = (float) $payments->total_cash_collected;

        $purchase_payments = $this->db->query(
            "SELECT COALESCE(SUM(payment), 0) AS total_purchase_paid
             FROM db_purchasepayments
             WHERE payment_date = ?
               AND status = 1
               AND payment > 0",
            array($closing_date)
        )->row();
        $summary['total_purchase_paid'] = (float) $purchase_payments->total_purchase_paid;

        $loan_given = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total_loan_given
             FROM db_customer_loan_transactions
             WHERE transaction_date = ?
               AND transaction_type = 'LOAN_ISSUE'",
            array($closing_date)
        )->row();
        $summary['total_loan_given'] = (float) $loan_given->total_loan_given;

        $loan_repayments = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total_loan_repayments_received
             FROM db_customer_loan_transactions
             WHERE transaction_date = ?
               AND transaction_type = 'REPAYMENT'",
            array($closing_date)
        )->row();
        $summary['total_loan_repayments_received'] = (float) $loan_repayments->total_loan_repayments_received;

        $expenses = $this->db->query(
            "SELECT COALESCE(SUM(expense_amt), 0) AS total_expenses
             FROM db_expense
             WHERE expense_date = ?
               AND status = 1",
            array($closing_date)
        )->row();
        $summary['total_expenses'] = (float) $expenses->total_expenses;

        $adjustments = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'Addition' THEN amount ELSE 0 END), 0) AS custom_cash_additions,
                COALESCE(SUM(CASE WHEN type = 'Deduction' THEN amount ELSE 0 END), 0) AS custom_cash_deductions
             FROM db_cash_adjustments
             WHERE closing_date = ?",
            array($closing_date)
        )->row();
        $summary['custom_cash_additions'] = (float) $adjustments->custom_cash_additions;
        $summary['custom_cash_deductions'] = (float) $adjustments->custom_cash_deductions;

        $summary['final_cash_in_hand'] = $summary['opening_cash']
            + $summary['total_cash_collected']
            + $summary['total_loan_repayments_received']
            - $summary['total_purchase_paid']
            - $summary['total_loan_given']
            - $summary['total_expenses']
            + $summary['custom_cash_additions']
            - $summary['custom_cash_deductions'];

        return $summary;
    }

    public function get_opening_cash($closing_date)
    {
        $opening_date = config_item('eod_opening_date') ?: $closing_date;

        if ($closing_date === $opening_date) {
            return (float) config_item('eod_opening_cash');
        }

        $previous_date = date('Y-m-d', strtotime($closing_date . ' -1 day'));
        $previous_closing = $this->db->get_where('db_daily_closing', array('closing_date' => $previous_date))->row();

        if ($previous_closing) {
            return (float) $previous_closing->final_cash_in_hand;
        }

        return 0;
    }

    private function write_audit($affected_date, $table_name, $record_id, $action, $old_value, $new_value, $changed_by, $system_ip)
    {
        return $this->db->insert('db_audit_logs', array(
            'table_name' => $table_name,
            'record_id' => $record_id,
            'affected_date' => $affected_date,
            'action' => $action,
            'old_value' => json_encode($old_value),
            'new_value' => json_encode($new_value),
            'changed_by' => $changed_by,
            'system_ip' => $system_ip,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function refresh_closing_snapshot($closing_date, $changed_by, $system_ip)
    {
        $closing = $this->db->get_where('db_daily_closing', array('closing_date' => $closing_date))->row();
        if (!$closing) {
            return true;
        }

        $summary = $this->get_live_summary($closing_date);

        $new_value = array(
            'total_sales_due' => (float) $summary['total_sales_due'],
            'total_cash_collected' => (float) $summary['total_cash_collected'],
            'total_purchase_paid' => (float) $summary['total_purchase_paid'],
            'total_loan_given' => (float) $summary['total_loan_given'],
            'total_loan_repayments_received' => (float) $summary['total_loan_repayments_received'],
            'total_expenses' => (float) $summary['total_expenses'],
            'custom_cash_additions' => (float) $summary['custom_cash_additions'],
            'custom_cash_deductions' => (float) $summary['custom_cash_deductions'],
            'final_cash_in_hand' => (float) $summary['final_cash_in_hand'],
            'late_entry_amount' => (float) $closing->late_entry_amount,
        );
        $this->write_audit($closing_date, 'db_daily_closing', $closing->id, 'UPDATE', $closing, $new_value, $changed_by, $system_ip);
        return $this->db->where('id', $closing->id)->update('db_daily_closing', $new_value);
    }
}
