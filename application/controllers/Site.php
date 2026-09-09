<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Site extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load_global();
		$this->load->model('site_model');
		$this->load->model('due_generation_model', 'due_generation');
	}
	public function index()
	{
		$this->permission_check('site_edit');
		$data = $this->site_model->get_details();
		$data['due_generation_settings'] = $this->due_generation->get_settings();
		$data['page_title'] = $this->lang->line('site_settings');
		$this->load->view('site-settings', $data);
	}

	public function update_site()
	{
		$this->form_validation->set_rules('site_name', 'Site Name', 'trim|required');
		$this->form_validation->set_rules('cost_cutting_day', 'Cost Cutting Date', 'trim|required|integer|greater_than[0]|less_than[32]');
		if ($this->form_validation->run() === TRUE) {
			$result = $this->site_model->update_site();
			if ($result === 'success' && !$this->due_generation->save_settings($this->input->post('cost_cutting_day'), $this->data)) {
				$result = 'failed';
			}
			echo $result;
		} else {
			echo "Please Enter Compulsary(* marked) fields!";
		}
	}
	public function langauge($id)
	{
		$this->load->model('language_model');
		$this->language_model->set($id);
		redirect($_SERVER['HTTP_REFERER']);
	}
}
