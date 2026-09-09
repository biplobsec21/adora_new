<!DOCTYPE html>
<html>

<head>
  <!-- TABLES CSS CODE -->
  <?php include "comman/code_css_datatable.php"; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
  <div class="wrapper">

    <!-- Left side column. contains the logo and sidebar -->

    <?php include "sidebar.php"; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->

      <?php if ($CI->permissions('customers_edit')) { ?>
        <!--<div class="box-tools pull-right" style="margin-right: 10px;">-->
        <!--    <a class="btn btn-warning" href="<?php echo $base_url; ?>customers/bulk_repair_sales_due" onclick="return confirm('Are you sure you want to repair sales due calculations for ALL customers?')">-->
        <!--        <i class="fa fa-refresh"></i> Repair All Sales Due-->
        <!--    </a>-->
        <!--</div>-->
      <?php } ?>

      <section class="content-header">
        <h1>
          <?= $page_title; ?>
          <small>View/Search Customers</small>
        </h1>
        <ol class="breadcrumb">
          <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
          <li class="active"><?= $page_title; ?></li>

        </ol>
      </section>

      <div class="pay_now_modal">
      </div>
      <div class="pay_return_due_modal">
      </div>

      <!-- Main content -->
      <?= form_open('#', array('class' => '', 'id' => 'table_form')); ?>
      <input type="hidden" id='base_url' value="<?= $base_url; ?>">
      <section class="content">
        <div class="row">
          <!-- ********** ALERT MESSAGE START******* -->
          <?php include "comman/code_flashdata.php"; ?>
          <!-- ********** ALERT MESSAGE END******* -->
          <div class="col-xs-12">
            <div class="box">
              <div class="box-header with-border">
                <h3 class="box-title"><?= $page_title; ?></h3>
                <?php if ($CI->permissions('customers_add')) { ?>
                  <div class="box-tools">
                    <a class="btn btn-block btn-info" href="<?php echo $base_url; ?>customers/add">
                      <i class="fa fa-plus"></i> <?= $this->lang->line('new_customer'); ?></a>
                  </div>
                <?php } ?>
              </div>

              <!-- ADD FILTERS HERE - Before the table -->
              <div class="box-body">
                <div class="row" style="margin-bottom: 15px;">
                  <div class="col-md-3">
                    <div class="form-group">
                      <label for="filter_status">Customer Status</label>
                      <select class="form-control" id="filter_status" name="filter_status">
                        <option value="">All Customers</option>
                        <option value="1" selected>Active</option>
                        <option value="0">Inactive</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="form-group">
                      <label for="filter_due">Due Status</label>
                      <select class="form-control" id="filter_due" name="filter_due">
                        <option value="">All</option>
                        <option value="zero">Zero Due</option>
                        <option value="non_zero">Non-Zero Due</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="form-group">
                      <label style="visibility: hidden;">Action</label>
                      <button type="button" class="btn btn-primary btn-block" id="filter_btn">
                        <i class="fa fa-filter"></i> Apply Filters
                      </button>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="form-group">
                      <label style="visibility: hidden;">Reset</label>
                      <button type="button" class="btn btn-default btn-block" id="reset_filter_btn">
                        <i class="fa fa-refresh"></i> Reset Filters
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="box-body">
                <table id="example2" class="table table-bordered table-striped" width="100%">
                  <thead class="bg-primary ">
                    <tr>
                      <th class="text-center">
                        <input type="checkbox" class="group_check checkbox">
                      </th>
                      <th><?= $this->lang->line('customer_id'); ?></th>
                      <th>Customer Number</th>
                      <th><?= $this->lang->line('customer_name'); ?></th>
                      <th><?= $this->lang->line('mobile'); ?></th>
                      <th><?= $this->lang->line('address'); ?></th>
                      <th><?= $this->lang->line('total_paid'); ?>(-)</th>
                      <th><?= $this->lang->line('sales_due'); ?>(-)</th>
                      <th><?= $this->lang->line('sales_return_due'); ?>(+)</th>
                      <th><?= 'Total Due' ?></th>

                      <th><?= $this->lang->line('status'); ?></th>
                      <th><?= $this->lang->line('action'); ?></th>
                    </tr>
                  </thead>
                  <tbody>

                  </tbody>
                  <tfoot>
                    <tr class="bg-gray">
                      <th colspan="6" style="text-align:right">Total</th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th></th><!-- 7 -->
                      <th></th><!-- 8 -->
                    </tr>
                  </tfoot>
                </table>
              </div>
              <!-- /.box-body -->
            </div>
            <!-- /.box -->
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
      </section>
      <!-- /.content -->
      <?= form_close(); ?>
    </div>
    <!-- /.content-wrapper -->
    <?php include "footer.php"; ?>
    <!-- Add the sidebar's background. This div must be placed
       immediately after the control sidebar -->
    <div class="control-sidebar-bg"></div>
  </div>
  <!-- ./wrapper -->

  <!-- SOUND CODE -->
  <?php include "comman/code_js_sound.php"; ?>
  <!-- TABLES CODE -->
  <?php include "comman/code_js_datatable.php"; ?>
  <!-- bootstrap datepicker -->
  <script src="<?php echo $theme_link; ?>plugins/datepicker/bootstrap-datepicker.js"></script>
  <script type="text/javascript">
    //Date picker
    $('.datepicker').datepicker({
      autoclose: true,
      format: 'dd-mm-yyyy',
      todayHighlight: true
    });
  </script>

  <script type="text/javascript">
    $(document).ready(function() {
      //datatables
      var table = $('#example2').DataTable({
        lengthMenu: [
          [50, 100, 500, -1],
          [50, 100, 500, "All"]
        ],

        /* FOR EXPORT BUTTONS START*/
        dom: '<"row margin-bottom-12"<"col-sm-12"<"pull-left"l><"pull-right"fr><"pull-right margin-left-10 "B>>>tip',
        buttons: {
          buttons: [{
              className: 'btn bg-red color-palette btn-flat hidden delete_btn pull-left',
              text: 'Delete',
              action: function(e, dt, node, config) {
                multi_delete();
              }
            },
            {
              extend: 'copy',
              className: 'btn bg-teal color-palette btn-flat',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9]
              }
            },
            {
              extend: 'excel',
              className: 'btn bg-teal color-palette btn-flat',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9]
              }
            },
            {
              extend: 'pdf',
              className: 'btn bg-teal color-palette btn-flat',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9]
              }
            },
            {
              extend: 'print',
              className: 'btn bg-teal color-palette btn-flat',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9]
              }
            },
            {
              extend: 'csv',
              className: 'btn bg-teal color-palette btn-flat',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9]
              }
            },
            {
              extend: 'colvis',
              className: 'btn bg-teal color-palette btn-flat',
              text: 'Columns'
            },
          ]
        },
        /* FOR EXPORT BUTTONS END */

        "processing": true,
        "serverSide": true,
        "order": [],
        "responsive": true,
        "paging": true,
        "pagingType": "full_numbers",
        language: {
          processing: '<div class="text-primary bg-primary" style="position: relative;z-index:100;overflow: visible;">Processing...</div>',
          paginate: {
            first: "First",
            last: "Last",
            next: "Next",
            previous: "Previous"
          }
        },

        // Load data for the table's content from an Ajax source
        "ajax": {
          "url": "<?php echo site_url('customers/ajax_list') ?>",
          "type": "POST",
          "data": function(d) {
            d.filter_status = $('#filter_status').val();
            d.filter_due = $('#filter_due').val();
          },
          complete: function(data) {
            $('.column_checkbox').iCheck({
              checkboxClass: 'icheckbox_square-orange',
              radioClass: 'iradio_square-orange',
              increaseArea: '10%'
            });
            call_code();
          },
        },

        "columnDefs": [{
            "targets": [0, 11],
            "orderable": false,
          },
          {
            "targets": [0],
            "className": "text-center",
          },
        ],

        /*Start Footer Total*/
        "footerCallback": function(row, data, start, end, display) {
          var api = this.api(),
            data;
          var intVal = function(i) {
            return typeof i === 'string' ?
              i.replace(/[\$,]/g, '') * 1 :
              typeof i === 'number' ?
              i : 0;
          };
          var invoice_total = api
            .column(6, {
              page: 'none'
            })
            .data()
            .reduce(function(a, b) {
              return intVal(a) + intVal(b);
            }, 0);
          var sales_due = api
            .column(7, {
              page: 'none'
            })
            .data()
            .reduce(function(a, b) {
              return intVal(a) + intVal(b);
            }, 0);
          var sales_return_due = api
            .column(8, {
              page: 'none'
            })
            .data()
            .reduce(function(a, b) {
              return intVal(a) + intVal(b);
            }, 0);
          var total = api
            .column(9, {
              page: 'none'
            })
            .data()
            .reduce(function(a, b) {
              return intVal(a) + intVal(b);
            }, 0);
          $(api.column(6).footer()).html(app_number_format(invoice_total));
          $(api.column(7).footer()).html(app_number_format(sales_due));
          $(api.column(8).footer()).html(app_number_format(sales_return_due));
          $(api.column(9).footer()).html(app_number_format(total));
        },
        /*End Footer Total*/
      });

      new $.fn.dataTable.FixedHeader(table);

      // Filter button click event
      $('#filter_btn').on('click', function() {
        table.ajax.reload();
      });

      // Reset filter button click event
      $('#reset_filter_btn').on('click', function() {
        $('#filter_status').val('1'); // Reset to Active
        $('#filter_due').val(''); // Reset to All
        table.ajax.reload();
      });

      // Optional: Reload on Enter key in filter fields
      $('#filter_status, #filter_due').on('change', function() {
        table.ajax.reload();
      });
    });
  </script>

  <script src="<?php echo $theme_link; ?>js/customers.js"></script>
  <!-- Make sidebar menu hughlighter/selector -->
  <script>
    $(".<?php echo basename(__FILE__, '.php'); ?>-active-li").addClass("active");
  </script>

</body>

</html>