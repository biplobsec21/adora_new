<!DOCTYPE html>
<html>
<head>
   <?php include "comman/code_css_datatable.php"; ?>
   <link rel="stylesheet" href="<?php echo $theme_link; ?>plugins/datepicker/datepicker3.css">
   <style>
      .label-module  { background-color: #6c757d; }
      .filter-row    { margin-bottom: 12px; }
   </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

   <?php include "sidebar.php"; ?>

   <div class="content-wrapper">
      <section class="content-header">
         <h1><?= $page_title; ?> <small>Track all create / update / delete events</small></h1>
         <ol class="breadcrumb">
            <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active"><?= $page_title; ?></li>
         </ol>
      </section>

      <input type="hidden" id="base_url" value="<?= $base_url; ?>">

      <section class="content">
         <div class="row">
            <?php include "comman/code_flashdata.php"; ?>
            <div class="col-xs-12">
               <div class="box">
                  <div class="box-header with-border">

                     <!-- Filters -->
                     <div class="row filter-row">
                        <div class="col-md-2">
                           <label>Module</label>
                           <select class="form-control select2" id="filter_module" style="width:100%">
                              <option value="">— All Modules —</option>
                              <?php
                              $modules = ['sales','sales_payment','opening_balance_payment','purchase','sales_return','purchase_return','damage','items','customers','suppliers','expense','users','roles'];
                              foreach ($modules as $m) {
                                 echo "<option value='{$m}'>" . ucfirst(str_replace('_',' ',$m)) . "</option>";
                              }
                              ?>
                           </select>
                        </div>
                        <div class="col-md-2">
                           <label>Action</label>
                           <select class="form-control select2" id="filter_action" style="width:100%">
                              <option value="">— All Actions —</option>
                              <option value="create">Create</option>
                              <option value="update">Update</option>
                              <option value="delete">Delete</option>
                           </select>
                        </div>
                        <div class="col-md-2">
                           <label>User</label>
                           <select class="form-control select2" id="filter_user" style="width:100%">
                              <option value="">— All Users —</option>
                              <?php foreach ($users as $u) { ?>
                                 <option value="<?= $u->username; ?>"><?= ucfirst($u->username); ?></option>
                              <?php } ?>
                           </select>
                        </div>
                        <div class="col-md-2">
                           <label>From Date</label>
                           <div class="input-group date">
                              <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                              <input type="text" class="form-control datepicker" id="filter_from_date" placeholder="dd-mm-yyyy">
                           </div>
                        </div>
                        <div class="col-md-2">
                           <label>To Date</label>
                           <div class="input-group date">
                              <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                              <input type="text" class="form-control datepicker" id="filter_to_date" placeholder="dd-mm-yyyy">
                           </div>
                        </div>
                        <div class="col-md-2">
                           <label>Record Code</label>
                           <input type="text" class="form-control" id="filter_record_code" placeholder="Invoice / code…">
                        </div>
                     </div>

                     <!-- Export -->
                     <?php if ($CI->permissions('audit_log_export')) { ?>
                     <div class="row">
                        <div class="col-md-12">
                           <a id="export_btn" href="#" class="btn btn-success btn-sm">
                              <i class="fa fa-download"></i> Export CSV
                           </a>
                        </div>
                     </div>
                     <?php } ?>

                  </div><!-- /.box-header -->

                  <div class="box-body">
                     <table id="audit_datatable" class="table table-bordered table-striped table-hover" width="100%">
                        <thead class="bg-primary">
                           <tr>
                              <th>#</th>
                              <th>Module</th>
                              <th>Action</th>
                              <th>Record</th>
                              <th>Changed Fields</th>
                              <th>User</th>
                              <th>IP Address</th>
                              <th>Date / Time</th>
                              <th>Detail</th>
                           </tr>
                        </thead>
                        <tbody></tbody>
                     </table>
                  </div><!-- /.box-body -->
               </div><!-- /.box -->
            </div>
         </div>
      </section>
   </div><!-- /.content-wrapper -->

   <?php include "footer.php"; ?>
   <div class="control-sidebar-bg"></div>
</div><!-- ./wrapper -->

<?php include "comman/code_js_sound.php"; ?>
<?php include "comman/code_js_datatable.php"; ?>
<script src="<?php echo $theme_link; ?>plugins/datepicker/bootstrap-datepicker.js"></script>

<script>
$('.datepicker').datepicker({ autoclose: true, format: 'dd-mm-yyyy', todayHighlight: true });
$('.select2').select2();

function build_export_url() {
   var base = $('#base_url').val();
   var params = new URLSearchParams({
      module:      $('#filter_module').val(),
      action:      $('#filter_action').val(),
      username:    $('#filter_user').val(),
      from_date:   $('#filter_from_date').val(),
      to_date:     $('#filter_to_date').val(),
   });
   return base + 'auditlog/export?' + params.toString();
}

$('#export_btn').on('click', function(e) {
   e.preventDefault();
   window.location.href = build_export_url();
});

function load_datatable() {
   var table = $('#audit_datatable').DataTable({
      dom: '<"row margin-bottom-12"<"col-sm-12"<"pull-left"l><"pull-right"fr><"pull-right margin-left-10"B>>>tip',
      buttons: {
         buttons: [
            { extend: 'copy',  className: 'btn bg-teal color-palette btn-flat', exportOptions: { columns: [0,1,2,3,4,5,6,7] } },
            { extend: 'excel', className: 'btn bg-teal color-palette btn-flat', exportOptions: { columns: [0,1,2,3,4,5,6,7] } },
            { extend: 'pdf',   className: 'btn bg-teal color-palette btn-flat', exportOptions: { columns: [0,1,2,3,4,5,6,7] } },
            { extend: 'print', className: 'btn bg-teal color-palette btn-flat', exportOptions: { columns: [0,1,2,3,4,5,6,7] } },
            { extend: 'colvis', className: 'btn bg-teal color-palette btn-flat', text: 'Columns' }
         ]
      },
      pageLength: 50,
      processing: true,
      serverSide: true,
      order: [[7, 'desc']],
      responsive: true,
      deferRender: true,
      searchDelay: 600,
      paging: true,
      lengthMenu: [[10,25,50,100,500,-1],[10,25,50,100,500,'All']],
      pagingType: 'full_numbers',
      language: {
         processing: '<div class="text-primary">Processing...</div>',
         paginate: { first:'First', last:'Last', next:'Next', previous:'Previous' }
      },
      ajax: {
         url: '<?= site_url('auditlog/ajax_list'); ?>',
         type: 'POST',
         data: function(d) {
            d.filter_module      = $('#filter_module').val();
            d.filter_action      = $('#filter_action').val();
            d.filter_user        = $('#filter_user').val();
            d.filter_from_date   = $('#filter_from_date').val();
            d.filter_to_date     = $('#filter_to_date').val();
            d.filter_record_code = $('#filter_record_code').val();
         },
         cache: false,
         timeout: 30000
      },
      columnDefs: [
         { targets: [8], orderable: false }
      ]
   });
   new $.fn.dataTable.FixedHeader(table);
}

$(document).ready(function() { load_datatable(); });

var filterTimeout;
$('#filter_module,#filter_action,#filter_user,#filter_from_date,#filter_to_date').on('change', function() {
   clearTimeout(filterTimeout);
   filterTimeout = setTimeout(function() {
      $('#audit_datatable').DataTable().destroy();
      load_datatable();
   }, 500);
});
$('#filter_record_code').on('keyup', function() {
   clearTimeout(filterTimeout);
   filterTimeout = setTimeout(function() {
      $('#audit_datatable').DataTable().destroy();
      load_datatable();
   }, 700);
});
</script>

<script>
   $(".<?php echo basename(__FILE__, '.php'); ?>-active-li").addClass("active");
</script>
</body>
</html>
