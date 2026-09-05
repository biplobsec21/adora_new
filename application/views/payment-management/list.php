<!DOCTYPE html>
<html>

<head>
    <?php include APPPATH . 'views/comman/code_css_datatable.php'; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1>Payment Management <small>Customer dues and payments</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Payment Management</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <?php if ($CI->permissions('payment_management_view')) { ?>
                            <form method="get" action="<?= $base_url; ?>payment_management/export_due_customers" class="form-inline pull-left">
                                <select name="address" class="form-control">
                                    <option value="">All Addresses</option>
                                    <?php foreach ($customer_addresses as $customer_address) { ?>
                                        <option value="<?= html_escape($customer_address->address); ?>"><?= html_escape($customer_address->address); ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="btn btn-info"><i class="fa fa-download"></i> Download Due Customers Lists</button>
                            </form>
                        <?php } ?>

                        <div class="clearfix"></div>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-12 text-left">
                                <h3 class="box-title pull-left">Due Payment List</h3>
                                <?php if ($CI->permissions('payment_management_record')) { ?>
                                    <button type="button" class="btn btn-success pull-right" id="bulk-payment"><i class="fa fa-money"></i> Save Bulk Payment</button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr>
                        <table id="payment-list" class="table table-bordered table-striped" width="100%">
                            <thead class="bg-primary">
                                <tr>
                                    <th><input type="checkbox" id="select-all-payments"></th>
                                    <th>Customer</th>
                                    <th>Customer ID</th>
                                    <th>Address</th>
                                    <th>Due Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>

    <div class="modal fade" id="bulk-payment-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?= $base_url; ?>payment_management/bulk_payment" id="bulk-payment-form">
                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Record Bulk Payment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <div id="bulk-payment-count" class="alert alert-info"></div>
                        <div class="form-group"><label>Payment Amount</label><select name="amount_mode" id="bulk-amount-mode" class="form-control">
                                <option value="full_due">Pay each customer's full due</option>
                                <option value="fixed_amount">Apply the same amount to each customer</option>
                            </select></div>
                        <div class="form-group" id="bulk-fixed-amount-group" style="display:none;"><label>Amount per customer</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01"></div>
                        <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required></div>
                        <div class="form-group"><label>Payment Type</label><select name="payment_type" class="form-control" required><?php foreach ($this->db->where('status', 1)->get('db_paymenttypes')->result() as $payment_type) { ?><option value="<?= html_escape($payment_type->payment_type); ?>"><?= html_escape($payment_type->payment_type); ?></option><?php } ?></select></div>
                        <div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Record Payments</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="manual-payment-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?= $base_url; ?>payment_management/record_payment">
                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Record Manual Payment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="customer_id" id="payment-customer-id">
                        <div class="form-group"><label>Customer</label><input type="text" id="payment-customer-name" class="form-control" readonly></div>
                        <div class="form-group"><label>Remaining Amount</label><input type="text" id="payment-remaining" class="form-control" readonly></div>
                        <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required></div>
                        <div class="form-group"><label>Amount</label><input type="number" name="amount" id="payment-amount" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="form-group"><label>Payment Type</label><select name="payment_type" class="form-control" required>
                                <?php foreach ($this->db->where('status', 1)->get('db_paymenttypes')->result() as $payment_type) { ?>
                                    <option value="<?= html_escape($payment_type->payment_type); ?>"><?= html_escape($payment_type->payment_type); ?></option>
                                <?php } ?>
                            </select></div>
                        <div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save Payment</button></div>
                </form>
            </div>
        </div>
    </div>

    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $(function() {
            var table = $('#payment-list').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                order: [],
                ajax: {
                    url: '<?= $base_url; ?>payment_management/ajax_list',
                    type: 'POST',
                },
                columnDefs: [{
                    targets: [0, 6],
                    orderable: false
                }, {
                    targets: 0,
                    className: 'text-center'
                }]
            });

            $('#select-all-payments').on('ifChecked change', function() {
                $('.payment-select').prop('checked', this.checked).iCheck('update');
            });
            $(document).on('click', '.record-payment', function(event) {
                event.preventDefault();
                $('#payment-customer-id').val($(this).data('id'));
                $('#payment-customer-name').val($(this).data('customer'));
                $('#payment-remaining').val($(this).data('due'));
                $('#payment-amount').attr('max', $(this).data('due')).val($(this).data('due'));
                $('#manual-payment-modal').modal('show');
            });

            $('#bulk-amount-mode').on('change', function() {
                $('#bulk-fixed-amount-group').toggle(this.value === 'fixed_amount');
            });
            $('#bulk-payment').on('click', function() {
                var ids = $('.payment-select:checked').map(function() {
                    return this.value;
                }).get();
                if (!ids.length) {
                    alert('Select at least one customer.');
                    return;
                }
                $('#bulk-payment-form input[name="customer_ids[]"]').remove();
                $.each(ids, function(_, id) {
                    $('<input>', {
                        type: 'hidden',
                        name: 'customer_ids[]',
                        value: id
                    }).appendTo('#bulk-payment-form');
                });
                $('#bulk-payment-count').text(ids.length + ' customer(s) selected.');
                $('#bulk-payment-modal').modal('show');
            });
        });
    </script>
    <script>
        $('.payment-management-active-li').addClass('active');
    </script>
</body>

</html>