<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Due_generation_model extends CI_Model
{
    public function get_settings()
    {
        $settings = $this->db->get_where('db_due_generation_settings', array('id' => 1))->row();
        return $settings ?: (object) array('id' => 1, 'cost_cutting_day' => 25);
    }

    public function save_settings($day, $user_data)
    {
        $day = (int) $day;
        if ($day < 1 || $day > 31) return false;
        return $this->db->where('id', 1)->update('db_due_generation_settings', array('cost_cutting_day' => $day, 'updated_by' => $user_data['CUR_USERNAME']));
    }

    public function calculate_cycle($due_cycle_date, $day)
    {
        $due = $this->make_due_date(substr($due_cycle_date, 0, 7), (int) $day);
        $previous_generation = $this->db->where('due_cycle_date <', $due)
            ->order_by('due_cycle_date', 'DESC')
            ->limit(1)
            ->get('db_due_generations')
            ->row();
        if ($previous_generation) {
            $start = $previous_generation->due_cycle_date;
        } else {
            $previous_month = date('Y-m', strtotime($due . ' -1 month'));
            $start = $this->make_due_date($previous_month, (int) $day);
        }
        return array('due_cycle_date' => $due, 'billing_cycle_start' => $start . ' 00:00:00', 'billing_cycle_end' => date('Y-m-d H:i:s', strtotime($due . ' 00:00:00 -1 second')), 'next_cycle_start' => $due . ' 00:00:00');
    }

    public function create_generation($due_cycle_date, $user_data)
    {
        $settings = $this->get_settings();
        if (!$this->get_opening_generation()) {
            $opening_due_date = $this->make_due_date(substr($user_data['CUR_DATE'], 0, 7), (int) $settings->cost_cutting_day);
            if ($opening_due_date > $user_data['CUR_DATE']) return array('success' => false, 'message' => 'Opening Balance generation is available on or after ' . $opening_due_date . '.');
            return $this->create_opening_balance_generation($settings, $user_data);
        }

        $cycle = $this->calculate_cycle($due_cycle_date, $settings->cost_cutting_day);
        if ($cycle['due_cycle_date'] > $user_data['CUR_DATE']) return array('success' => false, 'message' => 'DueFlow cannot finalize this cycle before ' . $cycle['due_cycle_date'] . '.');
        $opening = $this->get_opening_generation();
        if ($opening) {
            $next_generation_due = $this->get_next_generation_due_date($settings->cost_cutting_day);
            if ($cycle['due_cycle_date'] !== $next_generation_due) return array('success' => false, 'message' => 'The next available Monthly Cycle is ' . $next_generation_due . '.');
        }
        if ($this->db->get_where('db_due_generations', array('due_cycle_date' => $cycle['due_cycle_date']))->row()) return array('success' => false, 'message' => 'The DueFlow cycle ' . $cycle['billing_cycle_start'] . ' to ' . $cycle['billing_cycle_end'] . ' has already been generated.');

        $customers = $this->db->where('status', 1)->order_by('customer_name', 'ASC')->get('db_customers')->result();
        $this->db->trans_begin();
        $generation_number = 'DF-' . date('YmdHis') . '-' . mt_rand(100, 999);
        $this->db->insert('db_due_generations', array('generation_number' => $generation_number, 'due_cycle_date' => $cycle['due_cycle_date'], 'generation_type' => 'Monthly Cycle', 'cost_cutting_day' => (int) $settings->cost_cutting_day, 'billing_cycle_start' => $cycle['billing_cycle_start'], 'billing_cycle_end' => $cycle['billing_cycle_end'], 'file_name' => 'dueflow-' . $cycle['due_cycle_date'] . '.csv', 'file_path' => 'uploads/csv/due-generation/dueflow-' . $cycle['due_cycle_date'] . '.csv', 'generated_by' => $user_data['CUR_USERNAME'], 'generated_at' => date('Y-m-d H:i:s'), 'system_ip' => $user_data['SYSTEM_IP']));
        $generation_id = $this->db->insert_id();
        $rows = array();
        $summary = array('total_records' => 0, 'total_new_due' => 0, 'total_previous_outstanding' => 0, 'total_due_amount' => 0, 'total_remaining_due' => 0);
        foreach ($customers as $customer) {
            $new_due = $this->get_cycle_new_due($customer->id, $cycle['billing_cycle_start'], $cycle['next_cycle_start']);
            $loan_due = round($this->get_loan_due_movement($customer->id, $cycle['billing_cycle_start'], $cycle['next_cycle_start']), 2);
            $previous = $this->get_previous_outstanding($customer->id, $cycle['due_cycle_date']);
            $total = round($previous + $new_due, 2);
            if ($total <= 0) continue;
            $item = array('generation_id' => $generation_id, 'customer_id' => $customer->id, 'customer_number' => $customer->customer_number, 'customer_name' => $customer->customer_name, 'mobile' => $customer->mobile, 'previous_outstanding_amount' => $previous, 'loan_due_amount' => $loan_due, 'new_due_amount' => $new_due, 'total_due_amount' => $total, 'remaining_due_amount' => $total);
            $this->db->insert('db_due_generation_items', $item);
            $rows[] = array_merge($item, array('customer_address' => $customer->address ?? ''));
            $summary['total_records']++;
            $summary['total_new_due'] += $new_due;
            $summary['total_previous_outstanding'] += $previous;
            $summary['total_due_amount'] += $total;
            $summary['total_remaining_due'] += $total;
        }

        $directory = FCPATH . 'uploads/csv/due-generation/';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The DueFlow CSV directory could not be created.');
        }
        $file_path = $directory . 'dueflow-' . $cycle['due_cycle_date'] . '.csv';
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The DueFlow CSV file could not be created.');
        }
        fputcsv($handle, array('Customer Number', 'Customer Name', 'Customer Address', 'Previous Outstanding', 'New Due', 'Cutting Amount'));
        foreach ($rows as $row) fputcsv($handle, array($row['customer_number'], $row['customer_name'], $row['customer_address'] ?? '', $row['previous_outstanding_amount'], $row['new_due_amount'], $row['total_due_amount']));
        fclose($handle);
        $this->db->where('id', $generation_id)->update('db_due_generations', $summary);
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            @unlink($file_path);
            return array('success' => false, 'message' => 'The DueFlow generation could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'generation_id' => $generation_id, 'message' => 'DueFlow generated successfully.');
    }

    public function get_opening_generation()
    {
        return $this->db->get_where('db_due_generations', array('generation_type' => 'Opening Balance'))->row();
    }

    public function get_first_monthly_due_date($opening_date, $day)
    {
        return $this->make_due_date(date('Y-m', strtotime($opening_date . ' +1 month')), (int) $day);
    }

    public function get_next_generation_due_date($day)
    {
        $opening = $this->get_opening_generation();
        if (!$opening) return $this->make_due_date(date('Y-m'), (int) $day);
        $latest = $this->db->where('generation_type', 'Monthly Cycle')->order_by('due_cycle_date', 'DESC')->limit(1)->get('db_due_generations')->row();
        $base_date = $latest ? $latest->due_cycle_date : $opening->due_cycle_date;
        return $this->make_due_date(date('Y-m', strtotime($base_date . ' +1 month')), (int) $day);
    }

    private function create_opening_balance_generation($settings, $user_data)
    {
        $opening_date = $user_data['CUR_DATE'];
        $customers = $this->db->where('status', 1)->order_by('customer_name', 'ASC')->get('db_customers')->result();
        $this->db->trans_begin();
        $generation_number = 'DF-' . date('YmdHis') . '-' . mt_rand(100, 999);
        $this->db->insert('db_due_generations', array('generation_number' => $generation_number, 'due_cycle_date' => $opening_date, 'generation_type' => 'Opening Balance', 'cost_cutting_day' => (int) $settings->cost_cutting_day, 'file_name' => 'dueflow-opening-' . $opening_date . '.csv', 'file_path' => 'uploads/csv/due-generation/dueflow-opening-' . $opening_date . '.csv', 'generated_by' => $user_data['CUR_USERNAME'], 'generated_at' => date('Y-m-d H:i:s'), 'system_ip' => $user_data['SYSTEM_IP']));
        $generation_id = $this->db->insert_id();
        $rows = array();
        $summary = array('total_records' => 0, 'total_new_due' => 0, 'total_previous_outstanding' => 0, 'total_due_amount' => 0, 'total_remaining_due' => 0);
        foreach ($customers as $customer) {
            $opening_due = $this->get_customer_current_due($customer->id, $user_data['CUR_DATE']);
            $loan_due = round($this->get_loan_due_movement($customer->id, '0000-01-01', date('Y-m-d', strtotime($user_data['CUR_DATE'] . ' +1 day'))), 2);
            if ($opening_due <= 0) continue;
            $item = array('generation_id' => $generation_id, 'customer_id' => $customer->id, 'customer_number' => $customer->customer_number, 'customer_name' => $customer->customer_name, 'mobile' => $customer->mobile, 'previous_outstanding_amount' => 0, 'loan_due_amount' => $loan_due, 'new_due_amount' => $opening_due, 'total_due_amount' => $opening_due, 'remaining_due_amount' => $opening_due);
            $this->db->insert('db_due_generation_items', $item);
            $rows[] = array_merge($item, array('customer_address' => $customer->address ?? ''));
            $summary['total_records']++;
            $summary['total_new_due'] += $opening_due;
            $summary['total_due_amount'] += $opening_due;
            $summary['total_remaining_due'] += $opening_due;
        }
        $file_path = $this->write_generation_csv($opening_date, $rows, true);
        if (!$file_path) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The Opening Balance CSV file could not be created.');
        }
        $this->db->where('id', $generation_id)->update('db_due_generations', array_merge($summary, array('file_path' => $file_path)));
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            @unlink(FCPATH . $file_path);
            return array('success' => false, 'message' => 'The Opening Balance generation could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'generation_id' => $generation_id, 'message' => 'Opening Balance generated successfully.');
    }

    public function get_generations()
    {
        $generations = $this->db->order_by('due_cycle_date', 'DESC')->get('db_due_generations')->result();
        if (!$generations) {
            return $generations;
        }

        $payment_periods = array();
        foreach ($generations as $generation) {
            $payment_periods[] = date('Y-m-01', strtotime($generation->due_cycle_date));
            $generation->overpaid_count = 0;
            $generation->overpaid_amount = 0;
        }
        $payment_periods = array_values(array_unique($payment_periods));
        $overpayments = $this->db->select('b.payment_period, COUNT(DISTINCT i.customer_code) AS overpaid_count, SUM(i.cutting_amount - i.new_paid_amount) AS overpaid_amount', false)
            ->from('db_cost_cutting_items AS i')
            ->join('db_cost_cutting_batches AS b', 'b.id = i.batch_id')
            ->where_in('b.payment_period', $payment_periods)
            ->where('b.status', 'Completed')
            ->where('i.validation_error IS NOT NULL', null, false)
            ->where('i.validation_error !=', '')
            ->where('i.cutting_amount > i.new_paid_amount', null, false)
            ->group_by('b.payment_period')
            ->get()
            ->result();
        $overpayments_by_period = array();
        foreach ($overpayments as $overpayment) {
            $overpayments_by_period[date('Y-m', strtotime($overpayment->payment_period))] = $overpayment;
        }
        foreach ($generations as $generation) {
            $payment_month = date('Y-m', strtotime($generation->due_cycle_date));
            if (isset($overpayments_by_period[$payment_month])) {
                $generation->overpaid_count = (int) $overpayments_by_period[$payment_month]->overpaid_count;
                $generation->overpaid_amount = (float) $overpayments_by_period[$payment_month]->overpaid_amount;
            }
        }

        return $generations;
    }
    public function get_generation($id)
    {
        return $this->db->get_where('db_due_generations', array('id' => (int) $id))->row();
    }
    public function get_items($id)
    {
        $items = $this->db->select('i.*, COALESCE(c.customer_number, i.customer_number) AS customer_number, COALESCE(c.customer_name, i.customer_name) AS customer_name, COALESCE(c.mobile, i.mobile) AS mobile, c.address AS customer_address')
            ->from('db_due_generation_items AS i')
            ->join('db_customers AS c', 'c.id = i.customer_id', 'left')
            ->where('i.generation_id', (int) $id)
            ->order_by('customer_name', 'ASC')
            ->get()->result();

        $generation = $this->get_generation($id);
        if (!$generation || !$items) {
            return $items;
        }

        $payment_period = date('Y-m-01', strtotime($generation->due_cycle_date));
        $overpaid_items = $this->db->select('i.customer_id, i.id AS cost_cutting_item_id, i.batch_id AS cost_cutting_batch_id, b.batch_number, i.cutting_amount, i.new_paid_amount, r.id AS refund_id, r.status AS refund_status, r.refund_amount')
            ->from('db_cost_cutting_items AS i')
            ->join('db_cost_cutting_batches AS b', 'b.id = i.batch_id')
            ->join('db_due_generation_refunds AS r', 'r.cost_cutting_item_id = i.id', 'left')
            ->where('b.payment_period', $payment_period)
            ->where('b.status', 'Completed')
            ->where('i.cutting_amount > i.new_paid_amount', null, false)
            ->order_by('b.id', 'ASC')
            ->get()
            ->result();
        $overpaid_by_customer = array();
        foreach ($overpaid_items as $overpaid_item) {
            $requested = max(0, (float) $overpaid_item->cutting_amount);
            $applied = max(0, (float) $overpaid_item->new_paid_amount);
            if ($requested <= $applied) {
                continue;
            }
            $overpaid_by_customer[(int) $overpaid_item->customer_id] = array(
                'batch_number' => $overpaid_item->batch_number,
                'requested_amount' => $requested,
                'applied_amount' => $applied,
                'overpaid_amount' => round($requested - $applied, 2),
                'cost_cutting_item_id' => (int) $overpaid_item->cost_cutting_item_id,
                'cost_cutting_batch_id' => (int) $overpaid_item->cost_cutting_batch_id,
                'refund_id' => $overpaid_item->refund_id ? (int) $overpaid_item->refund_id : null,
                'refund_status' => $overpaid_item->refund_status,
                'refund_amount' => $overpaid_item->refund_amount !== null ? (float) $overpaid_item->refund_amount : null,
            );
        }
        foreach ($items as $item) {
            $item->completed_cost_cutting_overpayment = $overpaid_by_customer[(int) $item->customer_id] ?? null;
        }

        return $items;
    }

    public function get_download_groups($id)
    {
        $groups = $this->db->select('MIN(BINARY c.address) AS customer_address, COUNT(*) AS customer_count', false)
            ->from('db_due_generation_items AS i')
            ->join('db_customers AS c', 'c.id = i.customer_id', 'left')
            ->where('i.generation_id', (int) $id)
            ->where('c.address IS NOT NULL', null, false)
            ->where('TRIM(c.address) <>', '')
            ->group_by('BINARY LOWER(TRIM(c.address))', false)
            ->order_by('LOWER(TRIM(c.address))', 'ASC', false)
            ->get()
            ->result();

        $no_address_count = $this->db->from('db_due_generation_items AS i')
            ->join('db_customers AS c', 'c.id = i.customer_id', 'left')
            ->where('i.generation_id', (int) $id)
            ->group_start()
            ->where('c.address IS NULL', null, false)
            ->or_where('TRIM(c.address) =', '')
            ->group_end()
            ->count_all_results();
        if ($no_address_count > 0) {
            $groups[] = (object) array(
                'customer_address' => '__NO_CUSTOMER_ADDRESS__',
                'customer_count' => $no_address_count,
            );
        }

        return $groups;
    }
    public function mark_sent($id)
    {
        return $this->db->where('id', (int) $id)->where('status', 'Generated')->update('db_due_generations', array('status' => 'Sent to Back Office'));
    }

    public function reconcile_cost_cutting_batch($batch_id, $user_data)
    {
        $batch = $this->db->get_where('db_cost_cutting_batches', array('id' => (int) $batch_id))->row();
        if (!$batch) return array('success' => false, 'found' => false, 'message' => 'Cost Cutting batch was not found.');
        if ($batch->status !== 'Completed') return array('success' => false, 'found' => false, 'message' => 'Only completed Cost Cutting batches can be synchronized with DueFlow.');

        $period_start = date('Y-m-01', strtotime($batch->payment_period));
        $period_end = date('Y-m-01', strtotime($period_start . ' +1 month'));
        $generation = $this->db->where('generation_type', 'Monthly Cycle')
            ->where('due_cycle_date >=', $period_start)
            ->where('due_cycle_date <', $period_end)
            ->order_by('due_cycle_date', 'DESC')
            ->limit(1)
            ->get('db_due_generations')
            ->row();
        if (!$generation) {
            $opening = $this->get_opening_generation();
            if ($opening && date('Y-m', strtotime($opening->due_cycle_date)) === date('Y-m', strtotime($batch->payment_period))) $generation = $opening;
        }
        if (!$generation) return array('success' => true, 'found' => false, 'message' => 'No DueFlow generation exists for this Cost Cutting period.');

        $generation_already_reconciled = $generation->status === 'Reconciled';
        $cut_items = array();
        $completed_batch_items = $this->db
            ->select('i.*')
            ->from('db_cost_cutting_items AS i')
            ->join('db_cost_cutting_batches AS b', 'b.id = i.batch_id')
            ->where('i.batch_id', (int) $batch_id)
            ->where('b.status', 'Completed')
            ->get()
            ->result();
        foreach ($completed_batch_items as $item) {
            $cut_items[strtoupper(trim($item->customer_code))] = $item;
        }
        $items = $this->get_items($generation->id);
        $summary = array('total_cut_amount' => 0, 'total_remaining_due' => 0);
        foreach ($items as $item) {
            $number = strtoupper(trim($item->customer_number));
            $matched = array_key_exists($number, $cut_items);
            $cost_cutting_item = $matched ? $cut_items[$number] : null;
            $cut = $matched ? min((float) $item->total_due_amount, max(0, (float) $cost_cutting_item->new_paid_amount)) : 0;
            $remaining = $this->get_reconciled_remaining_due($item, $cut, $user_data['CUR_DATE']);
            $status = $remaining > 0 ? ($cut > 0 ? 'Carried Forward' : 'Unpaid') : 'Fully Cut';
            if (!$generation_already_reconciled) {
                $this->db->where('id', (int) $item->id)->update('db_due_generation_items', array('actually_cut_amount' => $cut, 'remaining_due_amount' => $remaining, 'status' => $status));
            }
            if ($cost_cutting_item && (float) $cost_cutting_item->cutting_amount > (float) $cost_cutting_item->new_paid_amount) {
                $existing_refund = $this->db->get_where('db_due_generation_refunds', array(
                    'cost_cutting_item_id' => (int) $cost_cutting_item->id,
                ))->row();
                if (!$existing_refund) {
                    $refund_amount = round((float) $cost_cutting_item->cutting_amount - (float) $cost_cutting_item->new_paid_amount, 2);
                    if (!$this->db->insert('db_due_generation_refunds', array(
                        'customer_id' => (int) $item->customer_id,
                        'generation_id' => (int) $generation->id,
                        'generation_item_id' => (int) $item->id,
                        'cost_cutting_batch_id' => (int) $batch_id,
                        'cost_cutting_item_id' => (int) $cost_cutting_item->id,
                        'refund_amount' => $refund_amount,
                        'status' => 'Pending',
                        'generated_by' => $user_data['CUR_USERNAME'],
                        'generated_at' => date('Y-m-d H:i:s'),
                        'system_ip' => $user_data['SYSTEM_IP'],
                    ))) {
                        return array('success' => false, 'found' => true, 'message' => 'The pending refund record could not be created for Customer Number ' . $item->customer_number . '.');
                    }
                }
            }
            $summary['total_cut_amount'] += $cut;
            $summary['total_remaining_due'] += $remaining;
        }
        if (!$generation_already_reconciled) {
            $this->db->where('id', (int) $generation->id)->update('db_due_generations', array_merge($summary, array('status' => 'Reconciled', 'reconciled_by' => $user_data['CUR_USERNAME'], 'reconciled_at' => date('Y-m-d H:i:s'))));
        }
        return array('success' => $this->db->trans_status(), 'found' => true, 'message' => 'DueFlow was synchronized with Cost Cutting.');
    }

    public function reconcile_manual_cost_cutting_item($batch_id, $item_id, $user_data)
    {
        $batch = $this->db->get_where('db_cost_cutting_batches', array(
            'id' => (int) $batch_id,
            'status' => 'Completed',
        ))->row();
        $cost_cutting_item = $this->db->get_where('db_cost_cutting_items', array(
            'id' => (int) $item_id,
            'batch_id' => (int) $batch_id,
        ))->row();
        if (!$batch || !$cost_cutting_item) {
            return array('success' => false, 'found' => false, 'message' => 'The manual Cost Cutting item could not be found.');
        }

        $period_start = date('Y-m-01', strtotime($batch->payment_period));
        $period_end = date('Y-m-01', strtotime($period_start . ' +1 month'));
        $generation = $this->db->where('generation_type', 'Monthly Cycle')
            ->where('due_cycle_date >=', $period_start)
            ->where('due_cycle_date <', $period_end)
            ->order_by('due_cycle_date', 'DESC')
            ->limit(1)
            ->get('db_due_generations')
            ->row();
        if (!$generation) {
            $generation = $this->db->where('generation_type', 'Opening Balance')
                ->where('due_cycle_date >=', $period_start)
                ->where('due_cycle_date <', $period_end)
                ->order_by('due_cycle_date', 'DESC')
                ->limit(1)
                ->get('db_due_generations')
                ->row();
        }
        if (!$generation) {
            return array('success' => true, 'found' => false, 'message' => 'No DueFlow generation exists for this Cost Cutting period.');
        }

        $generation_item = $this->db->query(
            'SELECT * FROM db_due_generation_items WHERE generation_id = ? AND customer_id = ? FOR UPDATE',
            array((int) $generation->id, (int) $cost_cutting_item->customer_id)
        )->row();
        if (!$generation_item) {
            return array('success' => true, 'found' => false, 'message' => 'No DueFlow row exists for the selected customer in this period.');
        }
        $this->db->query(
            'SELECT id FROM db_due_generations WHERE id = ? FOR UPDATE',
            array((int) $generation->id)
        );

        $remaining_generation_due = max(
            0,
            (float) $generation_item->total_due_amount - (float) $generation_item->actually_cut_amount
        );
        $cut_amount = min((float) $cost_cutting_item->new_paid_amount, $remaining_generation_due);
        $new_cut_amount = round((float) $generation_item->actually_cut_amount + $cut_amount, 2);
        $current_customer_due = $this->get_customer_current_due(
            (int) $cost_cutting_item->customer_id,
            $user_data['CUR_DATE']
        );
        $remaining_due = min(
            max(0, round((float) $generation_item->total_due_amount - $new_cut_amount, 2)),
            $current_customer_due
        );
        $status = $remaining_due > 0
            ? ($new_cut_amount > 0 ? 'Carried Forward' : 'Unpaid')
            : ($new_cut_amount > 0 ? 'Fully Cut' : 'Paid Outside Cost Cutting');
        if (!$this->db->where('id', (int) $generation_item->id)->update('db_due_generation_items', array(
            'actually_cut_amount' => $new_cut_amount,
            'remaining_due_amount' => $remaining_due,
            'status' => $status,
        ))) {
            return array('success' => false, 'found' => true, 'message' => 'The DueFlow row could not be updated.');
        }

        if ((float) $cost_cutting_item->cutting_amount > (float) $cost_cutting_item->new_paid_amount) {
            $existing_refund = $this->db->get_where('db_due_generation_refunds', array(
                'cost_cutting_item_id' => (int) $cost_cutting_item->id,
            ))->row();
            if (!$existing_refund) {
                $refund_amount = round(
                    (float) $cost_cutting_item->cutting_amount - (float) $cost_cutting_item->new_paid_amount,
                    2
                );
                if (!$this->db->insert('db_due_generation_refunds', array(
                    'customer_id' => (int) $generation_item->customer_id,
                    'generation_id' => (int) $generation->id,
                    'generation_item_id' => (int) $generation_item->id,
                    'cost_cutting_batch_id' => (int) $batch_id,
                    'cost_cutting_item_id' => (int) $cost_cutting_item->id,
                    'refund_amount' => $refund_amount,
                    'status' => 'Pending',
                    'generated_by' => $user_data['CUR_USERNAME'],
                    'generated_at' => date('Y-m-d H:i:s'),
                    'system_ip' => $user_data['SYSTEM_IP'],
                ))) {
                    return array('success' => false, 'found' => true, 'message' => 'The pending refund record could not be created.');
                }
            }
        }

        $summary_updated = $this->db->query(
            'UPDATE db_due_generations
             SET total_cut_amount = total_cut_amount + ?,
                 total_remaining_due = total_remaining_due + ?
             WHERE id = ?',
            array(
                round($new_cut_amount - (float) $generation_item->actually_cut_amount, 2),
                round($remaining_due - (float) $generation_item->remaining_due_amount, 2),
                (int) $generation->id,
            )
        );
        if (!$summary_updated) {
            return array('success' => false, 'found' => true, 'message' => 'DueFlow generation totals could not be updated.');
        }

        return array('success' => $this->db->trans_status(), 'found' => true, 'message' => 'The selected customer was reconciled with DueFlow.');
    }

    public function reconcile($generation_id, $result_path, $user_data)
    {
        $generation = $this->get_generation($generation_id);
        if (!$generation || $generation->status === 'Reconciled') return array('success' => false, 'message' => 'This DueFlow generation has already been reconciled.');
        $handle = fopen($result_path, 'r');
        if (!$handle) return array('success' => false, 'message' => 'The Back Office result CSV could not be opened.');
        $header = fgetcsv($handle);
        $columns = $this->map_result_columns($header);
        if ($columns['customer'] === false) {
            fclose($handle);
            return array('success' => false, 'message' => 'The result CSV must contain Customer Number.');
        }
        $successful = array();
        while (($row = fgetcsv($handle)) !== false) {
            $number = strtoupper(trim((string) ($row[$columns['customer']] ?? '')));
            if ($number === '' || isset($successful[$number])) continue;
            $successful[$number] = $columns['amount'] === false ? null : max(0, (float) str_replace(',', '', $row[$columns['amount']]));
        }
        fclose($handle);
        $this->load->model('cost_cutting_model', 'cost_cutting');
        $items = $this->get_items($generation_id);
        $this->db->trans_begin();
        $summary = array('total_cut_amount' => 0, 'total_remaining_due' => 0);
        foreach ($items as $item) {
            $number = strtoupper(trim($item->customer_number));
            $matched = array_key_exists($number, $successful);
            $cut = $matched ? ($successful[$number] === null ? (float) $item->total_due_amount : min((float) $item->total_due_amount, $successful[$number])) : 0;
            if ($cut > 0 && !$this->cost_cutting->record_cost_cutting_payment($item->customer_id, $cut, $generation->due_cycle_date, $generation->generation_number, $user_data)) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Payment could not be recorded for Customer Number ' . $item->customer_number . '.');
            }
            $remaining = $this->get_reconciled_remaining_due($item, $cut, $user_data['CUR_DATE']);
            $status = $remaining > 0 ? ($matched ? 'Carried Forward' : 'Unpaid') : 'Fully Cut';
            $this->db->where('id', $item->id)->update('db_due_generation_items', array('actually_cut_amount' => $cut, 'remaining_due_amount' => $remaining, 'status' => $status));
            $summary['total_cut_amount'] += $cut;
            $summary['total_remaining_due'] += $remaining;
        }
        $this->db->where('id', $generation_id)->update('db_due_generations', array_merge($summary, array('status' => 'Reconciled', 'result_file_name' => basename($result_path), 'result_file_path' => str_replace(FCPATH, '', $result_path), 'reconciled_by' => $user_data['CUR_USERNAME'], 'reconciled_at' => date('Y-m-d H:i:s'))));
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'DueFlow reconciliation could not be saved.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'DueFlow reconciliation completed.');
    }

    private function make_due_date($year_month, $day)
    {
        $timestamp = strtotime($year_month . '-01');
        return date('Y-m-', $timestamp) . str_pad(min($day, (int) date('t', $timestamp)), 2, '0', STR_PAD_LEFT);
    }
    private function get_cycle_new_due($customer_id, $start, $next_start)
    {
        $row = $this->db->select('COALESCE(SUM(GREATEST(grand_total - paid_amount, 0)), 0) AS due', false)->where('customer_id', (int) $customer_id)->where('status', 1)->where('sales_status', 'Final')->where('sales_date >=', $start)->where('sales_date <', $next_start)->get('db_sales')->row();
        return round(max(0, (float) $row->due) + $this->get_loan_due_movement($customer_id, $start, $next_start), 2);
    }
    private function get_customer_current_due($customer_id, $as_of_date)
    {
        $customer = $this->db->get_where('db_customers', array('id' => (int) $customer_id, 'status' => 1))->row();
        if (!$customer) return 0;
        $opening_paid = (float) $this->db->select_sum('payment')->where(array('customer_id' => (int) $customer_id, 'status' => 1))->get('db_cobpayments')->row()->payment;
        $sales = $this->db->select('COALESCE(SUM(GREATEST(grand_total - paid_amount, 0)), 0) AS due', false)->where(array('customer_id' => (int) $customer_id, 'status' => 1, 'sales_status' => 'Final'))->get('db_sales')->row();
        $loan_due = $this->get_loan_due_movement($customer_id, '0000-01-01', date('Y-m-d', strtotime($as_of_date . ' +1 day')));
        return max(0, round((float) $customer->opening_balance - $opening_paid + (float) $sales->due + $loan_due, 2));
    }

    private function get_reconciled_remaining_due($item, $cut, $as_of_date)
    {
        $generation_remaining = max(0, round((float) $item->total_due_amount - (float) $cut, 2));
        $current_customer_due = $this->get_customer_current_due((int) $item->customer_id, $as_of_date);

        return min($generation_remaining, $current_customer_due);
    }

    private function get_loan_due_movement($customer_id, $start, $end)
    {
        $row = $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "LOAN_ISSUE" THEN amount ELSE 0 END), 0) AS loan_issued,
                                  COALESCE(SUM(CASE WHEN transaction_type = "REPAYMENT" THEN amount ELSE 0 END), 0) AS loan_repaid')
            ->where('customer_id', (int) $customer_id)
            ->where('transaction_date >=', $start)
            ->where('transaction_date <', $end)
            ->where_in('transaction_type', array('LOAN_ISSUE', 'REPAYMENT'))
            ->get('db_customer_loan_transactions')
            ->row();

        return (float) $row->loan_issued - (float) $row->loan_repaid;
    }
    private function write_generation_csv($due_cycle_date, $rows, $opening = false)
    {
        $directory = FCPATH . 'uploads/csv/due-generation/';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return false;
        $file_name = $opening ? 'dueflow-opening-' . $due_cycle_date . '.csv' : 'dueflow-' . $due_cycle_date . '.csv';
        $file_path = $directory . $file_name;
        $handle = fopen($file_path, 'w');
        if (!$handle) return false;
        fputcsv($handle, array('Customer Number', 'Customer Name', 'Customer Address', 'Previous Outstanding', 'New Due', 'Cutting Amount'));
        foreach ($rows as $row) fputcsv($handle, array($row['customer_number'], $row['customer_name'], $row['customer_address'] ?? '', $row['previous_outstanding_amount'], $row['new_due_amount'], $row['total_due_amount']));
        fclose($handle);
        return 'uploads/csv/due-generation/' . $file_name;
    }
    private function get_previous_outstanding($customer_id, $due_cycle_date)
    {
        $row = $this->db->select('i.remaining_due_amount')->from('db_due_generation_items AS i')->join('db_due_generations AS g', 'g.id = i.generation_id')->where('i.customer_id', (int) $customer_id)->where('g.due_cycle_date <', $due_cycle_date)->order_by('g.due_cycle_date', 'DESC')->limit(1)->get()->row();
        return $row ? max(0, (float) $row->remaining_due_amount) : 0;
    }
    private function map_result_columns($header)
    {
        $columns = array('customer' => false, 'amount' => false);
        foreach ((array) $header as $index => $value) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)));
            $key = preg_replace('/[^a-z0-9]/', '', $key);
            if (in_array($key, array('customernumber', 'customerno', 'number'), true)) $columns['customer'] = $index;
            if (in_array($key, array('actualcutamount', 'cutamount', 'cuttingamount', 'amount'), true)) $columns['amount'] = $index;
        }
        return $columns;
    }
}
