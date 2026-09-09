<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Canteen_audit_model extends CI_Model
{
    public function get_history($month)
    {
        $history = array();
        $start = $month . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));

        $generations = $this->db->where('due_cycle_date >=', $start)->where('due_cycle_date <', $end)->order_by('due_cycle_date', 'DESC')->get('db_due_generations')->result();
        foreach ($generations as $generation) {
            $history[] = array(
                'event_type' => 'Due Generation',
                'period' => $generation->generation_type === 'Opening Balance' ? 'Initial Due Setup (' . date('F Y', strtotime($generation->due_cycle_date)) . ')' : date('F Y', strtotime($generation->due_cycle_date)),
                'reference' => $generation->generation_number,
                'event_at' => $generation->generated_at,
                'operator' => $generation->generated_by,
                'records' => (int) $generation->total_records,
                'total_due' => (float) $generation->total_due_amount,
                'total_cut' => (float) $generation->total_cut_amount,
                'remaining' => (float) $generation->total_remaining_due,
                'status' => $generation->status,
                'url' => base_url('due_generation/details/' . (int) $generation->id),
                'download_url' => base_url('canteen_audit/download_due_generation/' . (int) $generation->id),
            );
        }

        $batches = $this->db->where('payment_period >=', $start)->where('payment_period <', $end)->order_by('payment_period', 'DESC')->get('db_cost_cutting_batches')->result();
        foreach ($batches as $batch) {
            $history[] = array(
                'event_type' => 'Cost Cutting',
                'period' => date('F Y', strtotime($batch->payment_period)),
                'reference' => $batch->batch_number,
                'event_at' => $batch->processed_at ?: $batch->uploaded_at,
                'operator' => $batch->processed_by ?: $batch->uploaded_by,
                'records' => (int) $batch->total_records,
                'total_due' => (float) $batch->total_due_amount,
                'total_cut' => (float) $batch->total_cutting_amount,
                'remaining' => max(0, (float) $batch->total_due_amount - (float) $batch->total_cutting_amount),
                'status' => $batch->status,
                'url' => base_url('cost_cutting/review/' . (int) $batch->id),
                'download_url' => base_url('canteen_audit/download_cost_cutting/' . (int) $batch->id),
            );
        }

        usort($history, function ($left, $right) {
            return strcmp($right['event_at'], $left['event_at']);
        });
        return $history;
    }

    public function get_month_options()
    {
        $months = array();
        $rows = $this->db->select('DATE_FORMAT(due_cycle_date, "%Y-%m") AS month', false)->distinct()->get('db_due_generations')->result();
        foreach ($rows as $row) $months[$row->month] = true;
        $rows = $this->db->select('DATE_FORMAT(payment_period, "%Y-%m") AS month', false)->distinct()->get('db_cost_cutting_batches')->result();
        foreach ($rows as $row) $months[$row->month] = true;
        $months = array_keys($months);
        rsort($months);
        return $months;
    }
}
