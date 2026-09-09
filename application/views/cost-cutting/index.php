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
                <h1>Canteen Cost Cutting <small>Upload CSV for review</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Cost Cutting</li>
                </ol>
            </section>
            <section class="content">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Upload Back Office Cutting File</h3>
                        <div class="box-tools"><a href="<?= $base_url; ?>due_generation" class="btn btn-success btn-sm"><i class="fa fa-magic"></i> Open Canteen Due Generation</a></div>
                    </div>
                    <form method="post" action="<?= $base_url; ?>cost_cutting/upload" enctype="multipart/form-data">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <div class="box-body">
                            <div class="form-group col-md-4">
                                <label for="payment_period">Payment Period</label>
                                <input type="month" id="payment_period" name="payment_period" class="form-control" value="<?= date('Y-m'); ?>" required>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="cutting_file">CSV File</label>
                                <input type="file" id="cutting_file" name="cutting_file" class="form-control" accept=".csv,text/csv" required>
                                <p class="help-block">Required columns: Customer Number, Cutting Amount. Customer Name is optional. Customers with N/A numbers must be handled manually from the customer panel.</p>
                                <a href="<?= $base_url; ?>cost_cutting/download_example" class="btn btn-default"><i class="fa fa-download"></i> Download Example CSV</a>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Upload &amp; Review</button>
                        </div>
                    </form>
                </div>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Cost Cutting History</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="cost-cutting-history">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Cost Cutting ID</th>
                                    <th>Payment Period</th>
                                    <th>Records</th>
                                    <th>Total Cutting</th>
                                    <th>Paid</th>
                                    <th>Partial</th>
                                    <th>Unpaid</th>
                                    <th>Status</th>
                                    <th>Processed At</th>
                                    <th>SMS</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch) { ?>
                                    <tr>
                                        <td><?= html_escape($batch->batch_number); ?></td>
                                        <td><?= html_escape($batch->payment_period_label); ?></td>
                                        <td><?= (int) $batch->total_records; ?></td>
                                        <td><?= app_number_format($batch->total_cutting_amount); ?></td>
                                        <td><?= (int) $batch->paid_count; ?></td>
                                        <td><?= (int) $batch->partial_count; ?></td>
                                        <td><?= (int) $batch->unpaid_count; ?></td>
                                        <?php $status_class = $batch->status === 'Completed' ? 'success' : ($batch->status === 'Uploaded' || $batch->status === 'Failed' ? 'danger' : 'warning'); ?>
                                        <td><span class="label label-<?= $status_class; ?>"><?= html_escape($batch->status); ?></span></td>
                                        <td><?= html_escape($batch->processed_at ?: '-'); ?></td>
                                        <td><?php if ($CI->permissions('send_sms') && $sms_settings->enabled && $sms_settings->cost_cutting_enabled) { ?><button type="button" class="btn btn-xs btn-success preview-costcutting-sms" data-id="<?= (int) $batch->id; ?>"><i class="fa fa-commenting"></i> Send SMS</button><?php } ?></td>
                                        <td><a href="<?= $base_url; ?>cost_cutting/review/<?= (int) $batch->id; ?>" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View</a></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
    </div>
    <div class="modal fade" id="costcutting-sms-preview-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Preview SMS</h4>
                </div>
                <div class="modal-body">
                    <p><strong>Recipients:</strong> <span id="costcutting-sms-recipient-count">0</span></p>
                    <div class="table-responsive" style="max-height:420px;overflow:auto">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Mobile</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="costcutting-sms-preview-rows"></tbody>
                        </table>
                    </div>
                    <div id="costcutting-sms-preview-error" class="alert alert-danger" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <form method="post" id="costcutting-sms-send-form"><input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" id="costcutting-confirm-sms-send"><i class="fa fa-paper-plane"></i> Send Pending SMS</button></form>
                </div>
            </div>
        </div>
    </div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('#cost-cutting-history').DataTable({
            pageLength: 25,
            order: [
                [1, 'desc']
            ]
        });
        $('.preview-costcutting-sms').on('click', function() {
            var id = $(this).data('id');
            var rows = $('#costcutting-sms-preview-rows');
            var error = $('#costcutting-sms-preview-error');
            rows.empty();
            error.hide().text('');
            $('#costcutting-confirm-sms-send').prop('disabled', true);
            $.getJSON('<?= $base_url; ?>cost_cutting/sms_preview/' + id, function(response) {
                if (!response.success) {
                    error.text(response.message || 'SMS preview could not be loaded.').show();
                    return;
                }
                $('#costcutting-sms-recipient-count').text(response.rows.length);
                var eligibleRows = 0;
                $.each(response.rows, function(index, row) {
                    if (row.status !== 'Sent') eligibleRows++;
                    rows.append('<tr><td>' + $('<div>').text(row.customer_name).html() + '</td><td>' + $('<div>').text(row.mobile).html() + '</td><td>' + $('<div>').text(row.message).html() + '</td><td>' + $('<div>').text(row.status_label || 'Not Sent').html() + '</td></tr>');
                });
                $('#costcutting-sms-send-form').attr('action', '<?= $base_url; ?>cost_cutting/send_sms/' + id);
                $('#costcutting-confirm-sms-send').prop('disabled', eligibleRows === 0);
                $('#costcutting-confirm-sms-send').text(eligibleRows === 0 ? 'All SMS Already Sent' : 'Send Pending SMS');
                $('#costcutting-sms-preview-modal').modal('show');
            }).fail(function() {
                error.text('SMS preview could not be loaded.').show();
                $('#costcutting-sms-preview-modal').modal('show');
            });
        });
        $('.cost-cutting-active-li').addClass('active');
    </script>
</body>

</html>