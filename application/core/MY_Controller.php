<?php

/**
 * Author: Askarali
 */
class MY_Controller extends CI_Controller
{
  // public $source_version = app_version();
  public function __construct()
  {
    parent::__construct();
    // $this->output->enable_profiler(TRUE);
    set_time_limit(0);
  }

  // ---------------------------------------------------------------
  // AUDIT MIDDLEWARE — _remap()
  //
  // Wraps every controller method call. For POST requests it:
  //   1. Identifies module + action type from controller/method name
  //   2. Snapshots the old DB row (for update/delete)
  //   3. Calls the real method
  //   4. Writes the audit log entry
  //
  // Zero changes required in any individual controller or model.
  // ---------------------------------------------------------------

  // Tables that must never be audited (noise / internal)
  private $_audit_skip_tables = [
    'db_audit_log',
    'db_sessions',
    'db_sitesettings',
    'db_permissions',
    'db_customer_payments',
    'db_supplier_payments',
    'db_stockentry',
    'db_inventory_movements',
    'inventory_movements',
  ];

  // Controller-name → primary DB table map
  private $_audit_table_map = [
    'sales'           => 'db_sales',
    'purchase'        => 'db_purchase',
    'damage'          => 'db_damage',
    'items'           => 'db_items',
    'customers'       => 'db_customers',
    'suppliers'       => 'db_suppliers',
    'expense'         => 'db_expense',
    'users'           => 'db_users',
    'roles'           => 'db_roles',
    'salesreturn'     => 'db_salesreturn',
    'sales_return'    => 'db_salesreturn',
    'purchasereturn'  => 'db_purchasereturn',
    'purchase_return' => 'db_purchasereturn',
    'brands'          => 'db_brands',
    'category'        => 'db_category',
    'tax'             => 'db_tax',
    'units'           => 'db_units',
    'warehouse'       => 'db_warehouse',
    'currency'        => 'db_currency',
    'payment_types'   => 'db_paymenttypes',
  ];

  // Child tables to include in the audit document alongside the header row.
  // Format: 'parent_table' => ['child_table', 'foreign_key_column']
  private $_audit_child_map = [
    'db_sales'          => ['db_salesitems',        'sales_id'],
    'db_purchase'       => ['db_purchaseitems',     'purchase_id'],
    'db_damage'         => ['db_damageitems',        'damage_id'],
    'db_salesreturn'    => ['db_salesitemsreturn',  'return_id'],
    'db_purchasereturn' => ['db_purchaseitemsreturn','return_id'],
  ];

  // Code-column per table (shown in the audit list as the human reference)
  private $_audit_code_col = [
    'db_sales'         => 'sales_code',
    'db_purchase'      => 'purchase_code',
    'db_damage'        => 'damage_code',
    'db_salesreturn'   => 'return_code',
    'db_purchasereturn'=> 'return_code',
    'db_items'         => 'item_name',
    'db_customers'     => 'customer_name',
    'db_suppliers'     => 'supplier_name',
    'db_expense'       => 'expense_title',
    'db_users'         => 'username',
    'db_roles'         => 'role_name',
    'db_brands'        => 'brand_name',
    'db_category'      => 'category_name',
    'db_tax'           => 'tax_name',
    'db_units'         => 'unit_name',
    'db_warehouse'     => 'warehouse_name',
    'db_currency'      => 'currency_name',
    'db_paymenttypes'  => 'payment_type',
  ];

  // Methods whose names indicate an UPDATE to an existing record
  private $_audit_update_methods = [
    'update', 'edit', 'save', 'update_items', 'newitems',
    'sales_save_and_update', 'purchase_save_and_update',
    'damage_save_and_update', 'update_status', 'update_role',
    'save_and_update', 'update_items',
  ];

  // Methods whose names indicate a DELETE
  private $_audit_delete_methods = [
    'delete', 'delete_sales', 'delete_purchase', 'delete_damage',
    'delete_items', 'multi_delete', 'delete_roles_from_table',
    'delete_items_from_table', 'destroy', 'remove',
  ];

  // Methods to skip from audit middleware (read-only/export operations)
  private $_audit_skip_methods = [
    'export_productions',
    'export_item_usage',
    'get_date_range_productions',
    'get_item_usage',
    'get_items_json',
  ];

  public function _remap($method, $params = [])
  {
    // ── Only audit POST requests; call through directly for everything else ──
    if ($this->input->method() !== 'post' || !$this->session->userdata('logged_in')) {
      return call_user_func_array([$this, $method], $params);
    }

    // ── Skip the audit controllers themselves to avoid recursion ──
    $controller = strtolower(get_class($this));
    if (in_array($controller, ['auditlog', 'login', 'logout', 'csrfdata'])) {
      return call_user_func_array([$this, $method], $params);
    }

    // ── Skip export and read-only methods from production_dashboard ──
    if ($controller === 'production_dashboard' && in_array($method, ['export_productions', 'export_item_usage', 'get_date_range_productions', 'get_item_usage', 'get_items_json'])) {
      return call_user_func_array([$this, $method], $params);
    }

    // ── Skip read-only/export methods from audit middleware ──
    if (in_array(strtolower($method), array_map('strtolower', $this->_audit_skip_methods))) {
      return call_user_func_array([$this, $method], $params);
    }

    // ── Resolve module and table ──
    $module = $controller;
    $table  = isset($this->_audit_table_map[$module])
              ? $this->_audit_table_map[$module]
              : null;

    // ── Classify action ──
    $method_lower = strtolower($method);
    $is_delete    = false;
    $is_update    = false;
    $is_create    = false;

    foreach ($this->_audit_delete_methods as $dm) {
      if (strpos($method_lower, strtolower($dm)) !== false) { $is_delete = true; break; }
    }
    if (!$is_delete) {
      foreach ($this->_audit_update_methods as $um) {
        if (strpos($method_lower, strtolower($um)) !== false) { $is_update = true; break; }
      }
    }
    // "save" with a hidden "command=save" POST field means create, not update
    if ($is_update) {
      $command = $this->input->post('command') ?: $this->input->get('command');
      if ($command === 'save') {
        $is_create  = true;
        $is_update  = false;
      }
    }

    // ── Resolve record ID from POST or URI params ──
    $record_id = $this->_audit_resolve_id($params);

    // ── BEFORE: snapshot full document (header + child items) ──
    $old_doc     = null;
    $record_code = null;
    if (($is_update || $is_delete) && $table && $record_id) {
      $old_doc = $this->_audit_build_document($table, (int)$record_id);
      if ($old_doc) {
        $record_code = $this->_audit_get_code($table, $old_doc['header']);
      }
    }

    // ── CALL THE REAL METHOD ──
    $result = call_user_func_array([$this, $method], $params);

    // ── AFTER: write audit log ──
    try {
      if ($table && ($is_create || $is_update || $is_delete)) {

        if ($is_create) {
          // For creates the model does multiple inserts; last insert_id() is the
          // child-items row. Re-read the header FK to get the parent ID.
          $new_parent_id = $this->_audit_resolve_parent_id($table);
          if ($new_parent_id) {
            $new_doc     = $this->_audit_build_document($table, $new_parent_id);
            $record_code = $new_doc ? $this->_audit_get_code($table, $new_doc['header']) : null;
            $this->_audit_write($module, 'create', $new_parent_id, $record_code, null, $new_doc);
          }

        } elseif ($is_update && $record_id) {
          $new_doc = $this->_audit_build_document($table, (int)$record_id);
          $this->_audit_write($module, 'update', $record_id, $record_code, $old_doc, $new_doc);

        } elseif ($is_delete && $record_id) {
          $this->_audit_write($module, 'delete', $record_id, $record_code, $old_doc, null);
        }
      }
    } catch (Exception $e) {
      log_message('error', '[AuditMiddleware] ' . $e->getMessage());
    }

    return $result;
  }

  // ── Resolve the primary record ID ──
  private function _audit_resolve_id($uri_params)
  {
    // 1. Explicit POST field named 'id', 'q_id', 'sales_id', etc.
    $post_id_keys = ['id', 'q_id', 'sales_id', 'purchase_id', 'damage_id',
                     'item_id', 'customer_id', 'supplier_id', 'role_id', 'user_id'];
    foreach ($post_id_keys as $key) {
      $val = $this->input->post($key);
      if (!empty($val) && is_numeric($val)) {
        return (int) $val;
      }
    }
    // 2. First URI segment parameter (e.g. /sales/update/42 → 42)
    if (!empty($uri_params) && is_numeric($uri_params[0])) {
      return (int) $uri_params[0];
    }
    return null;
  }

  // ── Build a full document: header row + child item rows ──
  // Returns ['header' => [...], 'items' => [[...], ...]]
  // or just ['header' => [...]] if no child table is configured.
  private function _audit_build_document($table, $record_id)
  {
    $header = $this->db->select('*')->from($table)->where('id', $record_id)->get()->row_array();
    if (empty($header)) {
      return null;
    }
    $header = $this->_audit_strip($header);
    $doc    = ['header' => $header];

    if (isset($this->_audit_child_map[$table])) {
      list($child_table, $fk) = $this->_audit_child_map[$table];
      $children  = $this->db->select('*')->from($child_table)->where($fk, $record_id)->get()->result_array();
      $doc['items'] = array_map([$this, '_audit_strip'], $children);
    }

    return $doc;
  }

  // ── For create events: resolve the parent record ID after multiple inserts ──
  private function _audit_resolve_parent_id($table)
  {
    // No child map → last insert_id() is the parent
    if (!isset($this->_audit_child_map[$table])) {
      return $this->db->insert_id() ?: null;
    }

    list($child_table, $fk) = $this->_audit_child_map[$table];

    // Try POST/GET fields first (most reliable)
    $post_id_keys = ['sales_id', 'purchase_id', 'damage_id', 'return_id', 'id'];
    foreach ($post_id_keys as $key) {
      $val = $this->input->post($key) ?: $this->input->get($key);
      if (!empty($val) && is_numeric($val)) {
        $exists = $this->db->select('id')->from($table)->where('id', (int)$val)->get()->row();
        if ($exists) { return (int) $val; }
      }
    }

    // Fallback: walk back from the last inserted child row
    $last_child_id = $this->db->insert_id();
    if ($last_child_id) {
      $child = $this->db->select($fk)->from($child_table)->where('id', $last_child_id)->get()->row_array();
      if ($child && !empty($child[$fk])) {
        return (int) $child[$fk];
      }
    }

    return null;
  }

  // ── Get the human-readable code from a header row ──
  private function _audit_get_code($table, array $header_row)
  {
    if (!isset($this->_audit_code_col[$table])) {
      return null;
    }
    $col = $this->_audit_code_col[$table];
    return isset($header_row[$col]) ? $header_row[$col] : null;
  }

  // ── Strip sensitive fields from a single row array ──
  private function _audit_strip(array $row)
  {
    $excluded = ['password', 'password_hash', 'token', 'remember_token', 'csrf_token', 'secret', 'api_key'];
    foreach ($excluded as $f) { unset($row[$f]); }
    return $row;
  }

  // ── Diff two documents and write one audit log row ──
  // $old_doc / $new_doc shape: ['header' => [...], 'items' => [[...]]]
  private function _audit_write($module, $action, $record_id, $record_code, $old_doc, $new_doc)
  {
    $changed_fields = null;

    if ($action === 'update' && $old_doc && $new_doc) {
      $changed = [];

      // Diff header fields
      $old_h = $old_doc['header'] ?? [];
      $new_h = $new_doc['header'] ?? [];
      foreach ($new_h as $k => $v) {
        if (array_key_exists($k, $old_h) && (string)$old_h[$k] !== (string)$v) {
          $changed[] = $k;
        }
      }

      // Detect any item-level change — flag as 'items'
      $old_items_json = isset($old_doc['items']) ? json_encode($old_doc['items']) : '[]';
      $new_items_json = isset($new_doc['items']) ? json_encode($new_doc['items']) : '[]';
      if ($old_items_json !== $new_items_json) {
        $changed[] = 'items';
      }

      // Nothing changed at all — skip
      if (empty($changed)) { return; }
      $changed_fields = json_encode(array_values(array_unique($changed)));
    }

    $entry = [
      'module'         => $module,
      'action'         => $action,
      'record_id'      => (int) $record_id,
      'record_code'    => $record_code,
      'old_values'     => $old_doc ? json_encode($old_doc) : null,
      'new_values'     => $new_doc ? json_encode($new_doc) : null,
      'changed_fields' => $changed_fields,
      'user_id'        => (int) $this->session->userdata('inv_userid'),
      'username'       => $this->session->userdata('inv_username'),
      'ip_address'     => $this->input->ip_address(),
      'user_agent'     => substr((string)$this->input->user_agent(), 0, 500),
      'created_at'     => date('Y-m-d H:i:s'),
    ];

    $this->db->insert('db_audit_log', $entry);
  }
  public function load_info()
  {


    //If currency not set retrieve from DB
    if (!$this->session->has_userdata('currency')) {
      $q1 = $this->db->query("SELECT a.currency_name,a.currency,a.currency_code,a.symbol,b.currency_placement FROM db_currency a,db_sitesettings b WHERE a.id=b.currency_id AND b.id=1");
      $currency = $q1->row()->currency;
      $currency_placement = $q1->row()->currency_placement;
      $currency_code = $q1->row()->currency_code;
      $this->session->set_userdata(array('currency'  => $currency, 'currency_placement'  => $currency_placement, 'currency_code'  => $currency_code));
    }
    //end



    $query = $this->db->select('site_name,version,language_id,timezone,time_format,date_format')->where('id', 1)->get('db_sitesettings');
    date_default_timezone_set(trim($query->row()->timezone));
    $time_format = (trim($query->row()->time_format) === '24') ? date("h:i:s") : date("h:i:s a");
    $date_view_format = trim($query->row()->date_format);
    $this->session->set_userdata(array('view_date'  => $date_view_format));
    $this->session->set_userdata(array('view_time'  => $query->row()->time_format));

    // Use the correct 24-hour format string for CUR_TIME when the site is configured for 24-hour time.
    $time_format = (trim($query->row()->time_format) === '24') ? date("H:i:s") : date("h:i:s a");

    //CHECK LANGUAGE IN SESSION ELSE FROM DB
    if (!$this->session->has_userdata('language') && $this->session->has_userdata('logged_in')) {
      $this->load->model('language_model');
      $this->language_model->set($query->row()->language_id);
    }
    if ($this->session->has_userdata('logged_in')) {
      $this->lang->load($this->session->userdata('language'), $this->session->userdata('language'));
    }
    //End

    $this->data = array(
      'theme_link'    => base_url() . 'theme/',
      'base_url'      => base_url(),
      'SITE_TITLE'    => $query->row()->site_name,
      'VERSION'       => $query->row()->version,
      'CURRENCY'       => $this->session->userdata('currency'),
      'CURRENCY_PLACE' => $this->session->userdata('currency_placement'),
      'CURRENCY_CODE' => $this->session->userdata('currency_code'),
      'CUR_DATE'      => date("Y-m-d"),
      'VIEW_DATE'      => $date_view_format,
      'CUR_TIME'      => $time_format,
      'SYSTEM_IP'     => $_SERVER['REMOTE_ADDR'],
      'SYSTEM_NAME'   => gethostbyaddr($_SERVER['REMOTE_ADDR']),
      'CUR_USERNAME'  => $this->session->userdata('inv_username'),
      'CUR_USERID'    => $this->session->userdata('inv_userid'),
    );
  }
  public function load_global()
  {
    //Check login or redirect to logout
    if ((int)$this->session->userdata('logged_in') !== 1) {
      redirect(base_url() . 'logout', 'refresh');
    }
    $this->load_info();
  }

  public function currency($value = '', $with_comma = false)
  {
    $value = trim($value);
    $currency_symbol = html_entity_decode($this->session->userdata('currency'), ENT_QUOTES, 'UTF-8');

    if (!empty($value) && is_numeric($value)) {
      $value = ($with_comma) ? number_format($value, 2) : number_format($value, 2, '.', '');
    }

    if ($this->session->userdata('currency_placement') === 'Left') {
      if (!empty($value)) {
        return $currency_symbol . " " . $value;
      }
      return $currency_symbol . "" . $value;
    } else {
      if (!empty($value)) {
        return $value . " " . $currency_symbol;
      }
      return $value . "" . $currency_symbol;
    }
  }

  public function currency_code($value = '')
  {
    if (!empty($this->session->userdata('currency_code'))) {
      if ($this->session->userdata('currency_placement') == 'Left') {
        return $this->session->userdata('currency_code') . " " . $value;
      } else {
        return $value . " " . $this->session->userdata('currency');
      }
    } else {
      return $value;
    }
  }
  public function permissions($permissions = '')
  {
    //If he the Admin
    if ((int)$this->session->userdata('inv_userid') === 1) {
      return true;
    }

    $tot = $this->db->query('SELECT count(*) as tot FROM db_permissions where permissions="' . $permissions . '" and role_id=' . $this->session->userdata('role_id'))->row()->tot;
    if ((int)$tot === 1) {
      return true;
    }
    return false;
  }
  public function permission_check($value = '')
  {
    if (!$this->permissions($value)) {
      show_error("Access Denied", 403, $heading = "You Don't Have Enough Permission!!");
    }
    return true;
  }
  public function permission_check_with_msg($value = '')
  {
    if (!$this->permissions($value)) {
      echo "You Don't Have Enough Permission for this Operation!";
      exit();
    }
    return true;
  }
  public function show_access_denied_page()
  {
    show_error("Access Denied", 403, $heading = "You Don't Have Enough Permission!!");
  }
  //end
  public function get_current_version_of_db()
  {
    return $this->db->select('version')->from('db_sitesettings')->get()->row()->version;
  }
}
