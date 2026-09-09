<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_public extends CI_Controller
{
    public function ledger($token)
    {
        $this->load->database();
        $customer = $this->db->select('c.*')->from('db_sms_ledger_links AS l')->join('db_customers AS c', 'c.id = l.customer_id')->where('l.token_hash', hash('sha256', (string) $token))->where('l.revoked_at', null)->group_start()->where('l.expires_at', null)->or_where('l.expires_at >', date('Y-m-d H:i:s'))->group_end()->where('c.status', 1)->get()->row();
        if (!$customer) show_404();
        $this->load->model('customer_ledger_model');
        $this->load->helper(array('url', 'html'));
        $data = array('customer_info' => $customer, 'page_title' => 'Customer Ledger', 'ledger_data' => $this->customer_ledger_model->get_customer_ledger($customer->id, '2000-01-01', date('Y-m-d')), 'account_summary' => $this->customer_ledger_model->get_account_summary($customer->id, '2000-01-01', date('Y-m-d')));
        $this->load->view('sms-ledger', $data);
    }
}
