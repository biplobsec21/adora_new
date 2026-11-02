<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_refunds_model extends CI_Model
{
    public function get_refunds()
    {
        return $this->db->select(
            'r.*, c.customer_number, c.customer_name, g.generation_number, g.due_cycle_date,
             gi.total_due_amount AS generation_due_amount, gi.actually_cut_amount AS generation_cut_amount,
             b.batch_number, b.payment_period, ci.cutting_amount AS cost_cutting_requested_amount,
             ci.new_paid_amount AS cost_cutting_applied_amount,
             GREATEST(gi.total_due_amount - ci.new_paid_amount, 0) AS due_after_cost_cutting',
            false
        )
            ->from('db_due_generation_refunds AS r')
            ->join('db_customers AS c', 'c.id = r.customer_id')
            ->join('db_due_generations AS g', 'g.id = r.generation_id')
            ->join('db_due_generation_items AS gi', 'gi.id = r.generation_item_id')
            ->join('db_cost_cutting_batches AS b', 'b.id = r.cost_cutting_batch_id')
            ->join('db_cost_cutting_items AS ci', 'ci.id = r.cost_cutting_item_id')
            ->order_by('r.id', 'DESC')
            ->get()
            ->result();
    }

    public function mark_paid($refund_id, $user_data)
    {
        $this->db->trans_begin();
        $refund = $this->db->query(
            'SELECT id, status FROM db_due_generation_refunds WHERE id = ? FOR UPDATE',
            array((int) $refund_id)
        )->row();

        if (!$refund) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The customer refund record was not found.');
        }
        if ($refund->status !== 'Pending') {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Only pending refund records can be marked paid.');
        }

        $updated = $this->db->where('id', (int) $refund_id)->where('status', 'Pending')->update('db_due_generation_refunds', array(
            'status' => 'Paid',
            'paid_by' => $user_data['CUR_USERNAME'],
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_reference' => trim((string) $this->input->post('payment_reference', true)) ?: null,
        ));
        if (!$updated || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The refund payment could not be recorded.');
        }

        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Customer refund marked as paid.');
    }
}
