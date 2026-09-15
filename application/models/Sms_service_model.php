<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_service_model extends CI_Model
{
    public function get_settings()
    {
        $row = $this->db->get_where('db_sms_settings', array('id' => 1))->row();
        return $row ?: (object) array('id' => 1, 'provider_url' => 'http://bulksmsbd.net/api/smsapi', 'many_provider_url' => 'http://bulksmsbd.net/api/smsapimany', 'balance_url' => 'http://bulksmsbd.net/api/getBalanceApi', 'api_key' => '', 'sender_id' => '', 'enabled' => 0, 'sales_invoice_enabled' => 0, 'due_generation_enabled' => 0, 'cost_cutting_enabled' => 0);
    }

    public function get_balance()
    {
        $settings = $this->get_settings();
        if (trim((string) $settings->balance_url) === '' || trim((string) $settings->api_key) === '') {
            return array('success' => false, 'message' => 'Balance API is not configured.');
        }

        $url = $settings->balance_url . (strpos($settings->balance_url, '?') === false ? '?' : '&') . http_build_query(array('api_key' => $settings->api_key));
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || $http_code < 200 || $http_code >= 300) {
            return array('success' => false, 'message' => $error ?: 'Balance API request failed.');
        }

        $response = json_decode((string) $body, true);
        $balance = is_array($response) ? ($response['balance'] ?? ($response['data']['balance'] ?? null)) : null;
        if ($balance === null && is_numeric(trim((string) $body))) {
            $balance = trim((string) $body);
        }

        return $balance !== null
            ? array('success' => true, 'balance' => $balance)
            : array('success' => false, 'message' => 'Balance was not returned by the provider.');
    }

    public function save_settings($input, $user)
    {
        return $this->db->where('id', 1)->update('db_sms_settings', array(
            'provider_url' => trim($input['provider_url']),
            'many_provider_url' => trim($input['many_provider_url']),
            'balance_url' => trim($input['balance_url']),
            'api_key' => trim($input['api_key']),
            'sender_id' => trim($input['sender_id']),
            'enabled' => empty($input['enabled']) ? 0 : 1,
            'sales_invoice_enabled' => empty($input['sales_invoice_enabled']) ? 0 : 1,
            'due_generation_enabled' => empty($input['due_generation_enabled']) ? 0 : 1,
            'cost_cutting_enabled' => empty($input['cost_cutting_enabled']) ? 0 : 1,
            'updated_by' => $user['CUR_USERNAME'],
        ));
    }

    public function get_templates()
    {
        return $this->db->order_by('event_key', 'ASC')->get('db_sms_templates')->result();
    }
    public function save_template($id, $body, $enabled, $user)
    {
        return $this->db->where('id', (int) $id)->update('db_sms_templates', array('message_body' => trim($body), 'enabled' => empty($enabled) ? 0 : 1, 'updated_by' => $user['CUR_USERNAME']));
    }

    public function send_sales_invoice($sales_id, $user)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled) return false;

        $sale = $this->db->select('s.id, s.customer_id, s.sales_code, s.sales_date, s.grand_total, s.paid_amount, c.sales_due, c.customer_name, c.customer_number, c.mobile')
            ->from('db_sales s')
            ->join('db_customers c', 'c.id = s.customer_id', 'inner')
            ->where('s.id', (int) $sales_id)
            ->where('c.status', 1)
            ->get()->row();
        if (!$sale || (int) $sale->customer_id === 1 || trim((string) $sale->mobile) === '') return false;

        return $this->send_customer_event('sales_invoice', $sale->customer_id, array(
            'due_month' => date('M Y', strtotime($sale->sales_date)),
            'total_due' => $sale->sales_due,
            'new_due' => max(0, (float) $sale->grand_total - (float) $sale->paid_amount),
            'sales_code' => $sale->sales_code,
            'sales_amount' => $sale->grand_total,
            'paid_amount' => $sale->paid_amount,
            'invoice_due' => max(0, (float) $sale->grand_total - (float) $sale->paid_amount),
            'remaining_due' => $sale->sales_due,
        ), null, null, $user);
    }
    public function get_logs($date = '')
    {
        $this->db->select('l.*, c.customer_name AS customer_name');
        $this->db->from('db_sms_logs AS l');
        $this->db->join('db_customers AS c', 'c.id = l.customer_id', 'left');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->db->where('l.created_at >=', $date . ' 00:00:00')->where('l.created_at <', date('Y-m-d 00:00:00', strtotime($date . ' +1 day')));
        } elseif (preg_match('/^\d{4}-\d{2}$/', $date)) {
            $this->db->where('l.created_at >=', $date . '-01 00:00:00')->where('l.created_at <', date('Y-m-d 00:00:00', strtotime($date . '-01 +1 month')));
        }
        return $this->db->order_by('l.id', 'DESC')->limit(500)->get()->result();
    }

    public function preview_due_generation($generation_id)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->due_generation_enabled) return array('success' => false, 'message' => 'SMS is disabled for Due Generation.');
        $this->load->model('due_generation_model', 'due_generation');
        $generation = $this->due_generation->get_generation($generation_id);
        $template = $this->db->get_where('db_sms_templates', array('event_key' => 'due_generation', 'enabled' => 1))->row();
        if (!$generation || !$template) return array('success' => false, 'message' => 'SMS template or Due Generation was not found.');
        $rows = array();
        $due_month = date('M Y', strtotime($generation->due_cycle_date));
        $latest_logs = $this->db
            ->where('generation_id', (int) $generation_id)
            ->where('event_key', 'due_generation')
            ->order_by('id', 'DESC')
            ->get('db_sms_logs')
            ->result();
        $latest_log_by_customer = array();
        foreach ($latest_logs as $log) {
            $customer_id = (int) $log->customer_id;
            if ($customer_id <= 0 || isset($latest_log_by_customer[$customer_id])) continue;
            $latest_log_by_customer[$customer_id] = $log;
        }

        foreach ($this->due_generation->get_items($generation_id) as $item) {
            $customer = $this->db->get_where('db_customers', array('id' => (int) $item->customer_id, 'status' => 1))->row();
            $mobile = trim((string) ($item->mobile ?? ''));
            if ($mobile === '' && $customer) $mobile = trim((string) $customer->mobile);
            if ($mobile === '') continue;

            $customer_name = trim((string) ($item->customer_name ?? ''));
            if ($customer_name === '' && $customer) $customer_name = trim((string) $customer->customer_name);

            $customer_number = trim((string) ($item->customer_number ?? ''));
            if ($customer_number === '' && $customer) $customer_number = trim((string) $customer->customer_number);

            $preview_customer = (object) array(
                'id' => (int) $item->customer_id,
                'customer_name' => $customer_name,
                'customer_number' => $customer_number,
                'mobile' => $mobile,
            );

            $log = $latest_log_by_customer[(int) $item->customer_id] ?? null;
            $status = $log ? $log->status : 'Not Sent';
            $status_label = $status === 'Sent' ? 'Already Sent' : ($status === 'Failed' ? 'Resend' : ($status === 'Queued' ? 'Queued' : 'Not Sent'));
            $rows[] = array(
                'customer_id' => (int) $item->customer_id,
                'customer_name' => $customer_name,
                'mobile' => $mobile,
                'message' => $this->render_message($template->message_body, $preview_customer, array('due_month' => $due_month, 'total_due' => $item->total_due_amount, 'new_due' => $item->new_due_amount, 'remaining_due' => $item->remaining_due_amount)),
                'status' => $status,
                'status_label' => $status_label,
                'can_resend' => $log ? in_array($log->status, array('Failed', 'Queued'), true) : true,
            );
        }
        return array('success' => true, 'generation' => $generation, 'rows' => $rows);
    }

    public function send_due_generation_manual($generation_id, $user)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->due_generation_enabled) return array('success' => false, 'message' => 'SMS is disabled for Due Generation.');
        $preview = $this->preview_due_generation($generation_id);
        if (!$preview['success']) return $preview;
        $generation = $preview['generation'];
        $failed_or_queued = $this->db->where('generation_id', (int) $generation_id)->where('event_key', 'due_generation')->where_in('status', array('Failed', 'Queued'))->count_all_results('db_sms_logs');
        if (in_array($generation->sms_status, array('Sending', 'Sent'), true) && $failed_or_queued === 0) {
            return array('success' => false, 'message' => 'SMS has already been sent or is currently being sent for this generation.');
        }

        $eligible_customer_ids = array();
        foreach ($preview['rows'] as $row) {
            if (!empty($row['status']) && $row['status'] === 'Sent') continue;
            $eligible_customer_ids[(int) $row['customer_id']] = true;
        }

        if (empty($eligible_customer_ids)) {
            return array('success' => false, 'message' => 'All SMS for this generation have already been sent.');
        }

        $this->db->where('id', (int) $generation_id)->update('db_due_generations', array('sms_status' => 'Sending'));
        $events = array();
        foreach ($this->due_generation->get_items($generation_id) as $item) {
            if (!isset($eligible_customer_ids[(int) $item->customer_id])) continue;
            $events[] = array('customer_id' => $item->customer_id, 'customer_name' => $item->customer_name, 'customer_number' => $item->customer_number, 'mobile' => $item->mobile, 'values' => array('due_month' => date('M Y', strtotime($generation->due_cycle_date)), 'total_due' => $item->total_due_amount, 'new_due' => $item->new_due_amount, 'remaining_due' => $item->remaining_due_amount), 'generation_id' => $generation_id, 'batch_id' => null);
        }
        $this->send_customer_events_batch('due_generation', $events, $user);
        $failed = $this->db->where('generation_id', (int) $generation_id)->where('event_key', 'due_generation')->where_in('status', array('Failed', 'Queued'))->count_all_results('db_sms_logs');
        $status = $failed > 0 ? ($failed < count($events) ? 'Partial' : 'Failed') : 'Sent';
        $this->db->where('id', (int) $generation_id)->update('db_due_generations', array('sms_status' => $status, 'sms_sent_at' => date('Y-m-d H:i:s')));
        return array('success' => $status === 'Sent', 'message' => $status === 'Sent' ? 'SMS sent successfully.' : 'SMS completed with failed recipients.');
    }

    public function send_due_generation($generation_id, $user)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->due_generation_enabled) return;
        $this->load->model('due_generation_model', 'due_generation');
        $generation = $this->due_generation->get_generation($generation_id);
        $due_month = $generation ? date('M Y', strtotime($generation->due_cycle_date)) : date('M Y');
        $events = array();
        foreach ($this->due_generation->get_items($generation_id) as $item) $events[] = array('customer_id' => $item->customer_id, 'values' => array('due_month' => $due_month, 'total_due' => $item->total_due_amount, 'new_due' => $item->new_due_amount, 'remaining_due' => $item->remaining_due_amount), 'generation_id' => $generation_id, 'batch_id' => null);
        $this->send_customer_events_batch('due_generation', $events, $user);
    }

    public function preview_cost_cutting($batch_id)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->cost_cutting_enabled) return array('success' => false, 'message' => 'SMS is disabled for Cost Cutting.');
        $this->load->model('cost_cutting_model', 'cost_cutting');
        $batch = $this->cost_cutting->get_batch($batch_id);
        $template = $this->db->get_where('db_sms_templates', array('event_key' => 'cost_cutting', 'enabled' => 1))->row();
        if (!$batch || !$template) return array('success' => false, 'message' => 'SMS template or Cost Cutting batch was not found.');

        $rows = array();
        $due_month = date('M Y', strtotime($batch->payment_period));
        $latest_logs = $this->db
            ->where('cost_cutting_batch_id', (int) $batch_id)
            ->where('event_key', 'cost_cutting')
            ->order_by('id', 'DESC')
            ->get('db_sms_logs')
            ->result();
        $latest_log_by_customer = array();
        foreach ($latest_logs as $log) {
            $customer_id = (int) $log->customer_id;
            if ($customer_id <= 0 || isset($latest_log_by_customer[$customer_id])) continue;
            $latest_log_by_customer[$customer_id] = $log;
        }

        foreach ($this->cost_cutting->get_items($batch_id) as $item) {
            if (!(int) $item->customer_id || (float) $item->new_paid_amount <= 0) continue;
            $customer = $this->db->get_where('db_customers', array('id' => (int) $item->customer_id, 'status' => 1))->row();
            if (!$customer || trim((string) $customer->mobile) === '') continue;

            $customer_name = trim((string) ($item->customer_name ?: $customer->customer_name));
            $customer_number = trim((string) ($customer->customer_number ?: ''));
            $preview_customer = (object) array(
                'id' => (int) $item->customer_id,
                'customer_name' => $customer_name,
                'customer_number' => $customer_number,
                'mobile' => $customer->mobile,
            );

            $log = $latest_log_by_customer[(int) $item->customer_id] ?? null;
            $status = $log ? $log->status : 'Not Sent';
            $status_label = $status === 'Sent' ? 'Already Sent' : ($status === 'Failed' ? 'Resend' : ($status === 'Queued' ? 'Queued' : 'Not Sent'));
            $rows[] = array(
                'customer_id' => (int) $item->customer_id,
                'customer_name' => $customer_name,
                'mobile' => $customer->mobile,
                'message' => $this->render_message($template->message_body, $preview_customer, array('due_month' => $due_month, 'cut_amount' => $item->new_paid_amount, 'remaining_due' => max(0, (float) $item->due_amount - (float) $item->new_paid_amount), 'total_due' => $item->due_amount)),
                'status' => $status,
                'status_label' => $status_label,
            );
        }

        return array('success' => true, 'batch' => $batch, 'rows' => $rows);
    }

    public function send_cost_cutting_manual($batch_id, $user)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->cost_cutting_enabled) return array('success' => false, 'message' => 'SMS is disabled for Cost Cutting.');
        $preview = $this->preview_cost_cutting($batch_id);
        if (!$preview['success']) return $preview;

        $eligible_customer_ids = array();
        foreach ($preview['rows'] as $row) {
            if (!empty($row['status']) && $row['status'] === 'Sent') continue;
            $eligible_customer_ids[(int) $row['customer_id']] = true;
        }

        if (empty($eligible_customer_ids)) {
            return array('success' => false, 'message' => 'All SMS for this Cost Cutting batch have already been sent.');
        }

        $events = array();
        foreach ($this->cost_cutting->get_items($batch_id) as $item) {
            if (!(int) $item->customer_id || (float) $item->new_paid_amount <= 0 || !isset($eligible_customer_ids[(int) $item->customer_id])) continue;
            $customer = $this->db->get_where('db_customers', array('id' => (int) $item->customer_id, 'status' => 1))->row();
            if (!$customer || trim((string) $customer->mobile) === '') continue;
            $events[] = array(
                'customer_id' => $item->customer_id,
                'customer_name' => trim((string) ($item->customer_name ?: $customer->customer_name)),
                'customer_number' => trim((string) ($customer->customer_number ?: '')),
                'mobile' => $customer->mobile,
                'values' => array(
                    'due_month' => date('M Y', strtotime($preview['batch']->payment_period)),
                    'cut_amount' => $item->new_paid_amount,
                    'remaining_due' => max(0, (float) $item->due_amount - (float) $item->new_paid_amount),
                    'total_due' => $item->due_amount,
                ),
                'generation_id' => null,
                'batch_id' => (int) $batch_id,
            );
        }

        if (empty($events)) {
            return array('success' => false, 'message' => 'No eligible Cost Cutting SMS recipients were found.');
        }

        $this->send_customer_events_batch('cost_cutting', $events, $user);
        $failed_or_queued = $this->db->where('cost_cutting_batch_id', (int) $batch_id)->where('event_key', 'cost_cutting')->where_in('status', array('Failed', 'Queued'))->count_all_results('db_sms_logs');
        $status = $failed_or_queued > 0 ? ($failed_or_queued < count($events) ? 'Partial' : 'Failed') : 'Sent';
        return array('success' => $status === 'Sent', 'message' => $status === 'Sent' ? 'Cost Cutting SMS sent successfully.' : 'Cost Cutting SMS completed with failed recipients.');
    }

    public function send_cost_cutting($batch_id, $user)
    {
        $settings = $this->get_settings();
        if (!(int) $settings->enabled || !(int) $settings->cost_cutting_enabled) return;
        $this->load->model('cost_cutting_model', 'cost_cutting');
        $batch = $this->cost_cutting->get_batch($batch_id);
        $due_month = $batch ? date('M Y', strtotime($batch->payment_period)) : date('M Y');
        $events = array();
        foreach ($this->cost_cutting->get_items($batch_id) as $item) if ($item->customer_id && $item->new_paid_amount > 0) $events[] = array('customer_id' => $item->customer_id, 'values' => array('due_month' => $due_month, 'cut_amount' => $item->new_paid_amount, 'remaining_due' => max(0, (float) $item->due_amount - (float) $item->new_paid_amount), 'total_due' => $item->due_amount), 'generation_id' => null, 'batch_id' => $batch_id);
        $this->send_customer_events_batch('cost_cutting', $events, $user);
    }

    private function send_customer_events_batch($event_key, $events, $user)
    {
        $settings = $this->get_settings();
        $template = $this->db->get_where('db_sms_templates', array('event_key' => $event_key, 'enabled' => 1))->row();
        if (!$template || empty($events)) return;
        foreach (array_chunk($events, 50) as $event_batch) {
            $messages = array();
            $log_ids = array();
            foreach ($event_batch as $event) {
                $customer = $this->db->get_where('db_customers', array('id' => (int) $event['customer_id'], 'status' => 1))->row();
                $mobile = trim((string) ($event['mobile'] ?? ''));
                if ($mobile === '' && $customer) $mobile = trim((string) $customer->mobile);
                if ($mobile === '') continue;

                $customer_name = trim((string) ($event['customer_name'] ?? ''));
                if ($customer_name === '' && $customer) $customer_name = trim((string) $customer->customer_name);

                $customer_number = trim((string) ($event['customer_number'] ?? ''));
                if ($customer_number === '' && $customer) $customer_number = trim((string) $customer->customer_number);

                $customer_payload = (object) array(
                    'id' => (int) ($event['customer_id'] ?? 0),
                    'customer_name' => $customer_name,
                    'customer_number' => $customer_number,
                    'mobile' => $mobile,
                );

                $message = $this->render_message($template->message_body, $customer_payload, $event['values']);
                $this->db->insert('db_sms_logs', array('customer_id' => $customer_payload->id, 'event_key' => $event_key, 'template_id' => $template->id, 'generation_id' => $event['generation_id'], 'cost_cutting_batch_id' => $event['batch_id'], 'recipient' => $mobile, 'message_body' => $message, 'status' => 'Queued', 'sent_by' => $user['CUR_USERNAME']));
                $log_ids[] = $this->db->insert_id();
                $messages[] = array('to' => $mobile, 'message' => $message);
            }
            if (empty($messages)) continue;
            $response = $this->call_many_provider($settings, $messages);
            $success = $response['code'] === '202';
            foreach ($log_ids as $log_id) $this->db->where('id', $log_id)->update('db_sms_logs', array('provider_code' => $response['code'], 'provider_response' => $response['body'], 'status' => $success ? 'Sent' : 'Failed', 'error_message' => $success ? null : $response['error'], 'sent_at' => date('Y-m-d H:i:s')));
        }
    }

    public function send_customer_event($event_key, $customer_id, $values, $generation_id, $batch_id, $user)
    {
        $customer = $this->db->get_where('db_customers', array('id' => (int) $customer_id, 'status' => 1))->row();
        $template = $this->db->get_where('db_sms_templates', array('event_key' => $event_key, 'enabled' => 1))->row();
        if (!$customer || !$template) return false;
        if (trim((string) $customer->mobile) === '') return false;
        $message = $this->render_message($template->message_body, $customer, $values);
        $this->db->insert('db_sms_logs', array('customer_id' => $customer->id, 'event_key' => $event_key, 'template_id' => $template->id, 'generation_id' => $generation_id, 'cost_cutting_batch_id' => $batch_id, 'recipient' => $customer->mobile, 'message_body' => $message, 'status' => 'Queued', 'sent_by' => $user['CUR_USERNAME']));
        $log_id = $this->db->insert_id();
        $settings = $this->get_settings();
        $response = $this->call_provider($settings, $customer->mobile, $message);
        $success = $response['code'] === '202';
        $this->db->where('id', $log_id)->update('db_sms_logs', array('provider_code' => $response['code'], 'provider_response' => $response['body'], 'status' => $success ? 'Sent' : 'Failed', 'error_message' => $success ? null : $response['error'], 'sent_at' => date('Y-m-d H:i:s')));
        return $success;
    }

    public function get_or_create_ledger_url($customer_id)
    {
        $row = $this->db->get_where('db_sms_ledger_links', array('customer_id' => (int) $customer_id, 'revoked_at' => null))->row();
        if ($row && strlen(trim((string) $row->token_value)) === 12) return base_url('ll/' . $row->token_value);
        if ($row) {
            $token = $this->create_short_token();
            $this->db->where('id', $row->id)->update('db_sms_ledger_links', array('token_value' => $token, 'token_hash' => hash('sha256', $token)));
            return base_url('ll/' . $token);
        }
        $token = $this->create_short_token();
        $this->db->insert('db_sms_ledger_links', array('customer_id' => (int) $customer_id, 'token_value' => $token, 'token_hash' => hash('sha256', $token)));
        return base_url('ll/' . $token);
    }

    private function render_message($template, $customer, $values)
    {
        $values = array_merge(array('due_month' => date('M Y'), 'total_due' => 0, 'new_due' => 0, 'cut_amount' => 0, 'remaining_due' => 0, 'sales_code' => '', 'sales_amount' => 0, 'paid_amount' => 0, 'invoice_due' => 0), $values);
        $site = $this->db->select('site_name')->where('id', 1)->get('db_sitesettings')->row();
        return strtr($template, array('{customer_name}' => $customer->customer_name, '{customer_number}' => $customer->customer_number, '{due_month}' => $values['due_month'], '{total_due}' => number_format($values['total_due'], 2), '{new_due}' => number_format($values['new_due'], 2), '{cut_amount}' => number_format($values['cut_amount'], 2), '{remaining_due}' => number_format($values['remaining_due'], 2), '{sales_code}' => $values['sales_code'], '{sales_amount}' => number_format($values['sales_amount'], 2), '{paid_amount}' => number_format($values['paid_amount'], 2), '{invoice_due}' => number_format($values['invoice_due'], 2), '{ledger_url}' => $this->get_or_create_ledger_url($customer->id), '{site_name}' => $site ? $site->site_name : 'Canteen'));
    }

    private function create_short_token()
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $bytes = function_exists('random_bytes') ? random_bytes(12) : openssl_random_pseudo_bytes(12);
            $token = '';
            for ($index = 0; $index < 12; $index++) $token .= $alphabet[ord($bytes[$index]) % strlen($alphabet)];
        } while ($this->db->get_where('db_sms_ledger_links', array('token_value' => $token))->row());
        return $token;
    }

    private function call_many_provider($settings, $messages)
    {
        $payload = json_encode(array('api_key' => $settings->api_key, 'senderid' => $settings->sender_id, 'messages' => $messages));
        $ch = curl_init($settings->many_provider_url);
        curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => array('Content-Type: application/json'), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => true));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        $provider_code = '';
        if (is_array($decoded)) {
            if (isset($decoded['response_code'])) $provider_code = (string) $decoded['response_code'];
            elseif (isset($decoded['code'])) $provider_code = (string) $decoded['code'];
        }
        if ($provider_code === '' && $http_code >= 200 && $http_code < 300) $provider_code = trim((string) preg_replace('/[^0-9]/', '', substr((string) $body, 0, 10)));
        $success = $provider_code === '202' || $http_code >= 200 && $http_code < 300 && $provider_code === '';
        return array('code' => $provider_code, 'body' => (string) $body, 'error' => $success ? null : ($error ?: 'Bulk SMS provider did not return success code 202.'));
    }

    private function call_provider($settings, $mobile, $message)
    {
        $query = http_build_query(array('api_key' => $settings->api_key, 'type' => 'text', 'number' => $mobile, 'senderid' => $settings->sender_id, 'message' => $message));
        $ch = curl_init($settings->provider_url . '?' . $query);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = (string) $body;
        $decoded = json_decode($body, true);
        $code = '';
        if (is_array($decoded)) {
            if (isset($decoded['response_code'])) {
                $code = (string) $decoded['response_code'];
            } elseif (isset($decoded['code'])) {
                $code = (string) $decoded['code'];
            }
        }
        if ($code === '') {
            $code = trim((string) preg_replace('/[^0-9]/', '', substr($body, 0, 10)));
        }

        $success = $code === '202' || ($code === '' && $http_code >= 200 && $http_code < 300);
        return array(
            'code' => $code,
            'body' => $body,
            'error' => $success ? null : ($error ?: 'Provider did not return success code 202.'),
        );
    }
}
