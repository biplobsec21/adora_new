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
                <h1>Review Cost Cutting <small><?= html_escape($batch->batch_number); ?></small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?= $base_url; ?>cost_cutting">Cost Cutting</a></li>
                    <li class="active">Review</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <?php if ((int) $batch->error_records > 0) { ?>
                    <div class="alert alert-warning">Fix the invalid rows before processing. N/A customers are excluded from Cost Cutting and must be handled manually from the customer panel.</div>
                <?php } elseif ($batch->status === 'Completed') { ?>
                    <div class="alert alert-success">Cost Cutting has been processed. Payment records were updated.</div>
                <?php } else { ?>
                    <div class="alert alert-info">Review is complete. No payment records have been changed.</div>
                <?php } ?>
                <div class="row">
                    <div class="col-md-3">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?= (int) $batch->total_records; ?></h3>
                                <p>Total Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?= (int) $batch->valid_records; ?></h3>
                                <p>Valid Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?= (int) $batch->error_records; ?></h3>
                                <p>Errors</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?= html_escape($batch->payment_period_label); ?></h3>
                                <p>Payment Period</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Uploaded File: <?= html_escape($batch->file_name); ?></h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Row</th>
                                    <th>Customer Number</th>
                                    <th>Customer</th>
                                    <th>Total Due</th>
                                    <th>Cutting Amount</th>
                                    <th>Result</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item) { ?>
                                    <tr class="<?= $item->validation_status === 'Invalid' ? 'danger' : ((float) $item->cutting_amount > (float) $item->due_amount ? 'warning' : ''); ?>">
                                        <td><?= (int) $item->source_row_number; ?></td>
                                        <td><?= html_escape($item->customer_code); ?></td>
                                        <td><?= html_escape($item->customer_name); ?></td>
                                        <td><?= app_number_format($item->due_amount); ?></td>
                                        <td><?= $item->cutting_amount === null ? '-' : app_number_format($item->cutting_amount); ?></td>
                                        <td><?= html_escape($item->new_status ?: '-'); ?></td>
                                        <td>
                                            <?php if ((float) $item->cutting_amount > (float) $item->due_amount) { ?>
                                                <span class="label label-warning">Overpaid</span>
                                            <?php } else { ?>
                                                <?= html_escape($item->validation_error ?: 'Valid'); ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <?php if ((int) $batch->error_records === 0 && $batch->status !== 'Completed') { ?>
                            <form method="post" action="<?= $base_url; ?>cost_cutting/process/<?= (int) $batch->id; ?>" onsubmit="return confirm('Process this Cost Cutting batch? Overpayments will be capped at the current due amount.');" style="display:inline-block;">
                                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Process Cost Cutting</button>
                            </form>
                        <?php } ?>
                        <a href="<?= $base_url; ?>cost_cutting" class="btn btn-default">Upload Another File</a>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
    </div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('.cost-cutting-active-li').addClass('active');
    </script>
</body>

</html>