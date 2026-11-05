<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cost_cutting_model extends CI_Model
{
    private $customer_name_index;

    public function create_batch($period, $file_name, $file_path, $user_data)
    {
        $this->db->insert('db_cost_cutting_batches', array(
            'batch_number' => 'CC-' . date('YmdHis') . '-' . mt_rand(100, 999),
            'payment_period' => $period,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'uploaded_by' => $user_data['CUR_USERNAME'],
            'system_ip' => $user_data['SYSTEM_IP'],
        ));

        $batch_id = $this->db->insert_id();
        $batch_number = 'CC-' . date('Y-m', strtotime($period)) . '-' . str_pad($batch_id, 4, '0', STR_PAD_LEFT);
        $this->db->where('id', $batch_id)->update('db_cost_cutting_batches', array('batch_number' => $batch_number));
        return $batch_id;
    }

    public function get_customer_snapshot($customer_number)
    {
        $this->db->from('db_customers AS c');
        $this->db->where('c.status', 1);
        $this->db->where('c.customer_number', strtoupper(trim($customer_number)));
        $customer = $this->db->get()->row();
        if (!$customer) {
            return null;
        }

        return $this->build_customer_snapshot($customer);
    }

    public function get_customer_snapshot_by_id($customer_id)
    {
        $customer = $this->db->get_where('db_customers', array(
            'id' => (int) $customer_id,
            'status' => 1,
        ))->row();
        return $customer ? $this->build_customer_snapshot($customer) : null;
    }

    private function build_customer_snapshot($customer)
    {
        $opening_paid = (float) $this->db->select_sum('payment')
            ->where(array('customer_id' => $customer->id, 'status' => 1))
            ->get('db_cobpayments')->row()->payment;
        $sales_due = $this->db->select('COALESCE(SUM(grand_total - paid_amount), 0) AS due', false)
            ->where(array('customer_id' => $customer->id, 'status' => 1, 'sales_status' => 'Final'))
            ->where('grand_total > paid_amount')
            ->get('db_sales')->row();
        $loan_due = $this->get_customer_loan_due($customer->id);
        $due_amount = max(0, (float) $customer->opening_balance - $opening_paid + (float) $sales_due->due + $loan_due);
        $paid_amount = $opening_paid + (float) $this->db->select_sum('paid_amount')
            ->where(array('customer_id' => $customer->id, 'status' => 1, 'sales_status' => 'Final'))
            ->get('db_sales')->row()->paid_amount;

        return array(
            'customer_id' => (int) $customer->id,
            'customer_number' => $customer->customer_number,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->customer_name,
            'due_amount' => $due_amount,
            'paid_amount' => $paid_amount,
            'status' => $due_amount <= 0 ? 'Paid' : ($paid_amount > 0 ? 'Partial Paid' : 'Unpaid'),
        );
    }

    public function find_customer_snapshot_by_name($customer_name)
    {
        $normalized_name = $this->normalize_customer_name($customer_name);
        if ($normalized_name === '') {
            return array('snapshot' => null, 'match_count' => 0);
        }
        $name_without_number = preg_replace('/^(?:[a-z]{1,8}-\s*)?\d+\s+/u', '', $normalized_name);
        $name_keys = array_unique(array_filter(array($normalized_name, $name_without_number)));

        if ($this->customer_name_index === null) {
            $this->customer_name_index = array();
            $customers = $this->db->select('customer_number, customer_name')
                ->where('status', 1)
                ->get('db_customers')
                ->result();

            foreach ($customers as $customer) {
                $name_key = $this->normalize_customer_name($customer->customer_name);
                if ($name_key === '') {
                    continue;
                }
                $this->customer_name_index[$name_key][] = $customer->customer_number;

                $name_without_number = preg_replace('/^(?:[a-z]{1,8}-\s*)?\d+\s+/u', '', $name_key);
                if ($name_without_number !== $name_key && $name_without_number !== '') {
                    $this->customer_name_index[$name_without_number][] = $customer->customer_number;
                }
            }
        }

        $matches = array();
        foreach ($name_keys as $name_key) {
            $matches = array_merge($matches, $this->customer_name_index[$name_key] ?? array());
        }
        $matches = array_values(array_unique($matches));
        if (count($matches) !== 1) {
            return array('snapshot' => null, 'match_count' => count($matches));
        }

        return array(
            'snapshot' => $this->get_customer_snapshot($matches[0]),
            'match_count' => 1,
        );
    }

    private function normalize_customer_name($customer_name)
    {
        $normalized_name = preg_replace('/\s+/u', ' ', trim((string) $customer_name));
        if ($normalized_name === null) {
            $normalized_name = preg_replace('/\s+/', ' ', trim((string) $customer_name));
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized_name, 'UTF-8')
            : strtolower($normalized_name);
    }

    public function add_item($batch_id, $item)
    {
        $item['batch_id'] = $batch_id;
        return $this->db->insert('db_cost_cutting_items', $item);
    }

    public function add_batch_error($batch_id, $error)
    {
        $error['batch_id'] = (int) $batch_id;
        return $this->db->insert('db_cost_cutting_batch_errors', $error);
    }

    public function update_batch_summary($batch_id, $summary)
    {
        return $this->db->where('id', $batch_id)->update('db_cost_cutting_batches', $summary);
    }

    public function get_batch($batch_id)
    {
        return $this->db->get_where('db_cost_cutting_batches', array('id' => (int) $batch_id))->row();
    }

    public function get_batches()
    {
        return $this->db->order_by('id', 'DESC')
            ->get('db_cost_cutting_batches')
            ->result();
    }

    public function get_items($batch_id)
    {
        return $this->db->order_by('source_row_number', 'ASC')
            ->get_where('db_cost_cutting_items', array('batch_id' => (int) $batch_id))
            ->result();
    }

    public function get_batch_errors($batch_id)
    {
        return $this->db->order_by('source_row_number', 'ASC')
            ->get_where('db_cost_cutting_batch_errors', array('batch_id' => (int) $batch_id))
            ->result();
    }

    public function get_blocking_batch_error_count($batch_id)
    {
        return (int) $this->db->where(array(
            'batch_id' => (int) $batch_id,
            'blocks_processing' => 1,
        ))->count_all_results('db_cost_cutting_batch_errors');
    }

    public function get_batch_error($batch_id, $error_id)
    {
        return $this->db->get_where('db_cost_cutting_batch_errors', array(
            'id' => (int) $error_id,
            'batch_id' => (int) $batch_id,
        ))->row();
    }

    public function get_error_customer_candidates($batch_id, $error_id, $search = '')
    {
        $batch = $this->get_batch($batch_id);
        $error = $this->get_batch_error($batch_id, $error_id);
        if (!$batch || $batch->status !== 'Completed' || !$error || $error->resolved_at) {
            return array();
        }

        $search = trim((string) $search);
        $source_name = trim((string) $error->customer_name);
        if ($search === '' || strcasecmp($search, $source_name) === 0) {
            $search = preg_replace('/^(?:[a-z]{1,8}-\s*)?\d+\s+/iu', '', $source_name);
        }
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter($tokens, function ($token) {
            return function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') >= 3 : strlen($token) >= 3;
        }));
        if (!$tokens) {
            return array();
        }

        $this->db->select('id, customer_number, customer_name')
            ->from('db_customers')
            ->where('status', 1);
        foreach ($tokens as $token) {
            $this->db->like('customer_name', $token);
        }
        $customers = $this->db
            ->limit(200)
            ->get()
            ->result();

        $candidates = array();
        foreach ($customers as $customer) {
            $normalized_name = $this->normalize_customer_name($customer->customer_name);
            $score = 0;
            foreach ($tokens as $token) {
                if (strpos($normalized_name, $this->normalize_customer_name($token)) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $customer->match_score = $score;
                $candidates[] = $customer;
            }
        }
        usort($candidates, function ($left, $right) {
            if ($left->match_score === $right->match_score) {
                return strcasecmp($left->customer_name, $right->customer_name);
            }
            return $right->match_score <=> $left->match_score;
        });

        return array_slice($candidates, 0, 50);
    }

    public function preview_batch_error_customer($batch_id, $error_id, $customer_id)
    {
        $batch = $this->get_batch($batch_id);
        $error = $this->get_batch_error($batch_id, $error_id);
        if (!$batch || $batch->status !== 'Completed' || !$error || $error->resolved_at) {
            return null;
        }
        $snapshot = $this->get_customer_snapshot_by_id($customer_id);
        if (!$snapshot) {
            return null;
        }

        return array(
            'customer_id' => $snapshot['customer_id'],
            'customer_number' => $snapshot['customer_number'],
            'customer_name' => $snapshot['customer_name'],
            'current_due' => $snapshot['due_amount'],
            'due_at_upload' => $this->get_dueflow_snapshot_amount($batch, $customer_id),
        );
    }

    private function get_dueflow_snapshot_amount($batch, $customer_id)
    {
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
            return null;
        }
        $item = $this->db->select('total_due_amount')
            ->get_where('db_due_generation_items', array(
                'generation_id' => (int) $generation->id,
                'customer_id' => (int) $customer_id,
            ))->row();
        return $item ? (float) $item->total_due_amount : null;
    }

    public function apply_batch_error($batch_id, $error_id, $customer_id, $user_data)
    {
        $this->db->trans_begin();
        $batch = $this->db->query(
            'SELECT * FROM db_cost_cutting_batches WHERE id = ? FOR UPDATE',
            array((int) $batch_id)
        )->row();
        $error = $this->db->query(
            'SELECT * FROM db_cost_cutting_batch_errors WHERE id = ? AND batch_id = ? FOR UPDATE',
            array((int) $error_id, (int) $batch_id)
        )->row();
        $customer = $this->db->query(
            'SELECT * FROM db_customers WHERE id = ? AND status = 1 FOR UPDATE',
            array((int) $customer_id)
        )->row();

        if (!$batch || $batch->status !== 'Completed' || !$error || $error->resolved_at || (int) $error->blocks_processing !== 0 || !$customer) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'This CSV error row is no longer available for manual Cost Cutting.');
        }
        $existing_item = $this->db->get_where('db_cost_cutting_items', array(
            'batch_id' => (int) $batch_id,
            'customer_id' => (int) $customer_id,
        ))->row();
        $existing_resolution = $this->db->get_where('db_cost_cutting_batch_errors', array(
            'batch_id' => (int) $batch_id,
            'resolved_customer_id' => (int) $customer_id,
        ))->row();
        if ($existing_item || $existing_resolution) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'This customer already has a Cost Cutting entry in this batch.');
        }

        $snapshot = $this->get_customer_snapshot_by_id($customer_id);
        $requested_amount = (float) $error->cutting_amount;
        if (!$snapshot || $requested_amount <= 0 || (float) $snapshot['due_amount'] <= 0) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The selected customer has no current due to apply this Cost Cutting amount to.');
        }
        $applied_amount = min($requested_amount, (float) $snapshot['due_amount']);
        $due_at_upload = $this->get_dueflow_snapshot_amount($batch, $customer_id);
        $due_for_item = $due_at_upload === null ? (float) $snapshot['due_amount'] : $due_at_upload;
        $payment_note = $batch->batch_number . ' CSV row ' . (int) $error->source_row_number;
        if (!$this->allocate_cost_cutting_payment(
            (int) $customer_id,
            $applied_amount,
            $user_data['CUR_DATE'],
            $payment_note,
            $user_data
        ) || !$this->refresh_customer_sales_due((int) $customer_id)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The manual Cost Cutting payment could not be recorded.');
        }

        $new_status = $applied_amount >= (float) $snapshot['due_amount']
            ? 'Paid'
            : ($applied_amount > 0 ? 'Partial Paid' : 'Unpaid');
        $item_saved = $this->add_item($batch_id, array(
            'source_row_number' => (int) $error->source_row_number,
            'customer_id' => (int) $customer_id,
            'customer_code' => $snapshot['customer_number'],
            'customer_name' => $snapshot['customer_name'],
            'due_amount' => $due_for_item,
            'cutting_amount' => $requested_amount,
            'previous_status' => $snapshot['status'],
            'previous_paid_amount' => (float) $snapshot['paid_amount'],
            'new_status' => $new_status,
            'new_paid_amount' => $applied_amount,
            'validation_status' => 'Valid',
            'validation_error' => $requested_amount > $applied_amount
                ? 'Overpaid by ' . number_format($requested_amount - $applied_amount, 2) . '; processing was capped at the current due.'
                : null,
        ));
        if (!$item_saved) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The manual Cost Cutting item could not be added to this batch.');
        }
        $payment_item_id = (int) $this->db->insert_id();
        if (!$this->db->where(array('id' => (int) $error_id, 'batch_id' => (int) $batch_id))->update(
            'db_cost_cutting_batch_errors',
            array(
                'due_at_upload' => $due_at_upload,
                'resolved_customer_id' => (int) $customer_id,
                'resolved_customer_number' => $snapshot['customer_number'],
                'resolved_customer_name' => $snapshot['customer_name'],
                'applied_amount' => $applied_amount,
                'payment_item_id' => $payment_item_id,
                'resolved_by' => $user_data['CUR_USERNAME'],
                'resolved_at' => date('Y-m-d H:i:s'),
            )
        )) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The CSV error row resolution could not be saved.');
        }

        if (!$this->refresh_batch_summary($batch_id)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The Cost Cutting batch summary could not be updated.');
        }
        $this->load->model('due_generation_model', 'due_generation');
        $sync_result = $this->due_generation->reconcile_manual_cost_cutting_item($batch_id, $payment_item_id, $user_data);
        if (!$sync_result['success'] || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array(
                'success' => false,
                'message' => $sync_result['message'] ?: 'Manual Cost Cutting could not be synchronized with DueFlow.',
            );
        }

        $this->db->trans_commit();
        return array(
            'success' => true,
            'message' => 'Manual Cost Cutting applied: ' . number_format($applied_amount, 2)
                . ($due_at_upload === null ? '. No matching DueFlow row was found.' : ' and added to the batch and DueFlow.')
                . ($applied_amount < $requested_amount ? ' The amount was capped at the current due.' : ''),
        );
    }

    private function refresh_batch_summary($batch_id)
    {
        $items = $this->db->select('COUNT(*) AS valid_records, COALESCE(SUM(due_amount), 0) AS total_due_amount, COALESCE(SUM(cutting_amount), 0) AS total_cutting_amount, SUM(new_status = "Paid") AS paid_count, SUM(new_status = "Partial Paid") AS partial_count, SUM(new_status = "Unpaid") AS unpaid_count')
            ->get_where('db_cost_cutting_items', array('batch_id' => (int) $batch_id))
            ->row();
        $errors = $this->db->where(array('batch_id' => (int) $batch_id, 'resolved_at' => null))
            ->count_all_results('db_cost_cutting_batch_errors');
        return $this->db->where('id', (int) $batch_id)->update('db_cost_cutting_batches', array(
            'valid_records' => (int) $items->valid_records,
            'error_records' => (int) $errors,
            'total_due_amount' => (float) $items->total_due_amount,
            'total_cutting_amount' => (float) $items->total_cutting_amount,
            'paid_count' => (int) $items->paid_count,
            'partial_count' => (int) $items->partial_count,
            'unpaid_count' => (int) $items->unpaid_count,
        ));
    }

    public function get_completed_batch_for_customer($payment_period, $customer_number, $exclude_batch_id = 0)
    {
        $this->db->select('b.batch_number')
            ->from('db_cost_cutting_batches AS b')
            ->join('db_cost_cutting_items AS i', 'i.batch_id = b.id')
            ->where('b.payment_period', $payment_period)
            ->where('b.status', 'Completed')
            ->where('i.customer_code', strtoupper(trim($customer_number)));
        if ((int) $exclude_batch_id > 0) {
            $this->db->where('b.id !=', (int) $exclude_batch_id);
        }
        return $this->db->get()->row();
    }

    public function process_batch($batch_id, $user_data)
    {
        $batch = $this->get_batch($batch_id);
        if (!$batch || in_array($batch->status, array('Completed', 'Reverted'), true)) {
            return array('success' => false, 'message' => 'This Cost Cutting batch cannot be processed.');
        }
        if ($this->get_blocking_batch_error_count($batch_id) > 0) {
            return array('success' => false, 'message' => 'Fix all invalid rows before processing this batch.');
        }

        $items = $this->get_items($batch_id);
        $this->db->trans_begin();
        foreach ($items as $item) {
            $this->lock_customer_by_number($item->customer_code);
            $processed_batch = $this->get_completed_batch_for_customer($batch->payment_period, $item->customer_code, $batch_id);
            if ($processed_batch) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Customer Number ' . $item->customer_code . ' was already processed in batch ' . $processed_batch->batch_number . ' for this payment period.');
            }
            $snapshot = $this->get_customer_snapshot($item->customer_code);
            if (!$snapshot) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Customer Number ' . $item->customer_code . ' is no longer available.');
            }

            $requested_amount = max(0, (float) $item->cutting_amount);
            $applied_amount = min($requested_amount, (float) $snapshot['due_amount']);
            if ($applied_amount > 0 && !$this->allocate_cost_cutting_payment($snapshot['customer_id'], $applied_amount, $user_data['CUR_DATE'], $batch->batch_number, $user_data)) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Payment could not be recorded for Customer Number ' . $item->customer_code . '.');
            }

            if (!$this->refresh_customer_sales_due((int) $snapshot['customer_id'])) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Customer sales due could not be recalculated for Customer Number ' . $item->customer_code . '.');
            }

            $new_status = $applied_amount >= (float) $snapshot['due_amount'] ? 'Paid' : ($applied_amount > 0 ? 'Partial Paid' : 'Unpaid');
            $this->db->where('id', (int) $item->id)->update('db_cost_cutting_items', array(
                'previous_status' => $snapshot['status'],
                'previous_paid_amount' => $snapshot['paid_amount'],
                'new_status' => $new_status,
                'new_paid_amount' => $applied_amount,
                'validation_error' => $requested_amount > $applied_amount ? 'Overpaid; applied amount was capped at the current due.' : null,
            ));
            if (!$this->db->trans_status()) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Cost Cutting item update failed.');
            }
        }

        $this->db->where('id', (int) $batch_id)->update('db_cost_cutting_batches', array(
            'status' => 'Completed',
            'processed_by' => $user_data['CUR_USERNAME'],
            'processed_at' => date('Y-m-d H:i:s'),
        ));
        $this->load->model('due_generation_model', 'due_generation');
        $sync_result = $this->due_generation->reconcile_cost_cutting_batch((int) $batch_id, $user_data);
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Cost Cutting batch could not be completed.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => $sync_result['found'] ? 'Cost Cutting processed and DueFlow synchronized successfully.' : 'Cost Cutting processed successfully.');
    }

    public function record_cost_cutting_payment($customer_id, $amount, $payment_date, $batch_number, $user_data)
    {
        $customer = $this->db->select('customer_number')->get_where('db_customers', array('id' => (int) $customer_id))->row();
        if (!$customer) return false;
        $this->lock_customer_by_number($customer->customer_number);
        if (!$this->allocate_cost_cutting_payment((int) $customer_id, (float) $amount, $payment_date, $batch_number, $user_data)) {
            return false;
        }
        return $this->refresh_customer_sales_due((int) $customer_id);
    }

    private function refresh_customer_sales_due($customer_id)
    {
        return $this->db->query(
            "UPDATE db_customers
             SET sales_due = (
                 SELECT COALESCE(SUM(grand_total), 0) - COALESCE(SUM(paid_amount), 0)
                 FROM db_sales
                 WHERE customer_id = ?
                   AND status = 1
                   AND sales_status = 'Final'
             )
             WHERE id = ?",
            array((int) $customer_id, (int) $customer_id)
        );
    }

    private function lock_customer_by_number($customer_number)
    {
        return $this->db->query(
            'SELECT id FROM db_customers WHERE customer_number = ? AND status = 1 FOR UPDATE',
            array(strtoupper(trim($customer_number)))
        )->row();
    }

    private function allocate_cost_cutting_payment($customer_id, $amount, $payment_date, $batch_number, $user_data)
    {
        $customer = $this->db->get_where('db_customers', array('id' => (int) $customer_id, 'status' => 1))->row();
        if (!$customer) {
            return false;
        }
        $opening_paid = (float) $this->db->select_sum('payment')
            ->where(array('customer_id' => $customer_id, 'status' => 1))
            ->get('db_cobpayments')->row()->payment;
        $opening_due = max(0, (float) $customer->opening_balance - $opening_paid);
        $sales = $this->db->query("SELECT id, grand_total, paid_amount,
                GREATEST(COALESCE(grand_total, 0) - COALESCE(paid_amount, 0), 0) AS sales_due
            FROM db_sales WHERE customer_id = ? AND status = 1 AND sales_status = 'Final'
              AND COALESCE(grand_total, 0) > COALESCE(paid_amount, 0)
            ORDER BY sales_date ASC, id ASC", array($customer_id))->result();

        if ($opening_due > 0) {
            $opening_payment = min($amount, $opening_due);
            if (!$this->db->insert('db_cobpayments', array(
                'customer_id' => $customer_id,
                'payment_date' => $payment_date,
                'payment_type' => 'Cost Cutting',
                'payment' => $opening_payment,
                'payment_note' => $batch_number,
                'created_date' => $user_data['CUR_DATE'],
                'created_time' => $user_data['CUR_TIME'],
                'created_by' => $user_data['CUR_USERNAME'],
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'status' => 1,
            ))) {
                return false;
            }
            $amount -= $opening_payment;
        }
        foreach ($sales as $sale) {
            if ($amount <= 0) {
                break;
            }
            $payment_amount = min($amount, (float) $sale->sales_due);
            if (!$this->db->insert('db_salespayments', array(
                'sales_id' => $sale->id,
                'payment_date' => $payment_date,
                'payment_type' => 'Cost Cutting',
                'payment' => $payment_amount,
                'payment_note' => $batch_number,
                'created_time' => $user_data['CUR_TIME'],
                'created_date' => $user_data['CUR_DATE'],
                'created_by' => $user_data['CUR_USERNAME'],
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'status' => 1,
            ))) {
                return false;
            }
            $payment_id = $this->db->insert_id();
            $new_paid = (float) $sale->paid_amount + $payment_amount;
            if (!$this->db->where('id', $sale->id)->update('db_sales', array(
                'paid_amount' => $new_paid,
                'payment_status' => $new_paid >= (float) $sale->grand_total ? 'Paid' : 'Partial',
            ))) {
                return false;
            }
            if (!$this->db->insert('db_customer_payments', array(
                'salespayment_id' => $payment_id,
                'customer_id' => $customer_id,
                'payment_date' => $payment_date,
                'payment_type' => 'Cost Cutting',
                'payment' => $payment_amount,
                'payment_note' => $batch_number,
                'created_time' => $user_data['CUR_TIME'],
                'created_date' => $user_data['CUR_DATE'],
                'created_by' => $user_data['CUR_USERNAME'],
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'status' => 1,
            ))) {
                return false;
            }
            $amount -= $payment_amount;
        }

        if ($amount > 0) {
            $loans = $this->db->select('cl.*, COALESCE(SUM(CASE WHEN clt.transaction_type = "LOAN_ISSUE" THEN clt.amount WHEN clt.transaction_type = "REPAYMENT" THEN -clt.amount ELSE 0 END), 0) AS transaction_balance', false)
                ->from('db_customer_loans cl')
                ->join('db_customer_loan_transactions clt', 'clt.loan_id = cl.id', 'left')
                ->where('cl.customer_id', (int) $customer_id)
                ->where('cl.status', 'OPEN')
                ->group_by('cl.id')
                ->having('transaction_balance >', 0)
                ->order_by('cl.loan_date', 'ASC')
                ->order_by('cl.id', 'ASC')
                ->get()->result();

            foreach ($loans as $loan) {
                if ($amount <= 0) {
                    break;
                }

                $loan_balance = min((float) $loan->transaction_balance, $amount);
                $new_paid_amount = (float) $loan->paid_amount + $loan_balance;
                $new_balance_amount = max(0, (float) $loan->transaction_balance - $loan_balance);
                $loan_status = $new_balance_amount <= 0 ? 'CLOSED' : 'OPEN';
                $old_loan = (array) $loan;

                if (!$this->db->where('id', (int) $loan->id)->update('db_customer_loans', array(
                    'paid_amount' => $new_paid_amount,
                    'balance_amount' => $new_balance_amount,
                    'status' => $loan_status,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $user_data['userid'] ?? 0,
                ))) {
                    return false;
                }

                if (!$this->db->insert('db_customer_loan_transactions', array(
                    'loan_id' => (int) $loan->id,
                    'customer_id' => (int) $customer_id,
                    'transaction_type' => 'REPAYMENT',
                    'amount' => $loan_balance,
                    'transaction_date' => $payment_date,
                    'payment_method' => 'Cost Cutting',
                    'note' => $batch_number,
                    'created_by' => $user_data['userid'] ?? 0,
                    'created_date' => $user_data['CUR_DATE'],
                    'created_time' => $user_data['CUR_TIME'],
                    'created_at' => date('Y-m-d H:i:s'),
                ))) {
                    return false;
                }

                $transaction_id = $this->db->insert_id();
                $updated_loan = $this->db->get_where('db_customer_loans', array('id' => (int) $loan->id))->row_array();
                $this->write_loan_audit('db_customer_loans', (int) $loan->id, 'UPDATE', $old_loan, $updated_loan, $payment_date, $user_data);
                $this->write_loan_audit('db_customer_loan_transactions', $transaction_id, 'INSERT', null, array(
                    'loan_id' => (int) $loan->id,
                    'customer_id' => (int) $customer_id,
                    'transaction_type' => 'REPAYMENT',
                    'amount' => $loan_balance,
                    'transaction_date' => $payment_date,
                    'payment_method' => 'Cost Cutting',
                    'note' => $batch_number,
                ), $payment_date, $user_data);

                $amount -= $loan_balance;
            }
        }
        return true;
    }

    private function get_customer_loan_due($customer_id)
    {
        $loan_balance = $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "LOAN_ISSUE" THEN amount WHEN transaction_type = "REPAYMENT" THEN -amount ELSE 0 END), 0) AS balance', false)
            ->where('customer_id', (int) $customer_id)
            ->where_in('transaction_type', array('LOAN_ISSUE', 'REPAYMENT'))
            ->get('db_customer_loan_transactions')->row();

        return max(0, (float) $loan_balance->balance);
    }

    private function write_loan_audit($table_name, $record_id, $action, $old_value, $new_value, $affected_date, $user_data)
    {
        $audit = array(
            'table_name' => $table_name,
            'record_id' => $record_id,
            'affected_date' => $affected_date,
            'action' => $action,
            'old_value' => json_encode($old_value),
            'changed_by' => $user_data['CUR_USERNAME'] ?? ($user_data['userid'] ?? 'SYSTEM'),
            'system_ip' => $user_data['SYSTEM_IP'] ?? $this->input->ip_address(),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        );

        if ($this->db->field_exists('new_value', 'db_audit_logs')) {
            $audit['new_value'] = json_encode($new_value);
        } else {
            $audit['old_value'] = json_encode(array('previous' => $old_value, 'new' => $new_value));
        }

        return $this->db->insert('db_audit_logs', $audit);
    }
}
