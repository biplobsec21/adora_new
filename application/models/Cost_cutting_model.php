<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cost_cutting_model extends CI_Model
{
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

        $opening_paid = (float) $this->db->select_sum('payment')
            ->where(array('customer_id' => $customer->id, 'status' => 1))
            ->get('db_cobpayments')->row()->payment;
        $sales_due = $this->db->select('COALESCE(SUM(grand_total - paid_amount), 0) AS due', false)
            ->where(array('customer_id' => $customer->id, 'status' => 1, 'sales_status' => 'Final'))
            ->where('grand_total > paid_amount')
            ->get('db_sales')->row();
        $due_amount = max(0, (float) $customer->opening_balance - $opening_paid + (float) $sales_due->due);
        $paid_amount = $opening_paid + (float) $this->db->select_sum('paid_amount')
            ->where(array('customer_id' => $customer->id, 'status' => 1, 'sales_status' => 'Final'))
            ->get('db_sales')->row()->paid_amount;

        return array(
            'customer_id' => (int) $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->customer_name,
            'due_amount' => $due_amount,
            'paid_amount' => $paid_amount,
            'status' => $due_amount <= 0 ? 'Paid' : ($paid_amount > 0 ? 'Partial Paid' : 'Unpaid'),
        );
    }

    public function add_item($batch_id, $item)
    {
        $item['batch_id'] = $batch_id;
        return $this->db->insert('db_cost_cutting_items', $item);
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
        if ((int) $batch->error_records > 0) {
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
            if ($applied_amount > 0 && !$this->allocate_cost_cutting_payment($snapshot['customer_id'], $applied_amount, $batch->payment_period, $batch->batch_number, $user_data)) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'Payment could not be recorded for Customer Number ' . $item->customer_code . '.');
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
        return $this->allocate_cost_cutting_payment((int) $customer_id, (float) $amount, $payment_date, $batch_number, $user_data);
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
        return true;
    }
}
