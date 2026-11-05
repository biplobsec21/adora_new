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
                <?php if ($batch->status === 'Completed') { ?>
                    <div class="alert alert-success">Cost Cutting has been processed. Invalid rows were not applied; review their validation messages below.</div>
                <?php } elseif ((int) $batch->error_records > 0) { ?>
                    <div class="alert alert-warning">Fix the invalid rows before processing. N/A customers are excluded from Cost Cutting and must be handled manually from the customer panel.</div>
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
                        <h4>Valid Rows</h4>
                        <?php
                        $total_due_at_upload = 0;
                        $total_cutting_amount = 0;
                        $total_due_after_cutting = 0;
                        $valid_items = array();
                        $invalid_items = array();
                        foreach ($items as $item) {
                            if ($item->validation_status === 'Invalid') {
                                $invalid_items[] = $item;
                                continue;
                            }
                            $valid_items[] = $item;
                            $total_due_at_upload += (float) $item->due_amount;
                            if ($item->cutting_amount !== null) {
                                $requested_amount = max(0, (float) $item->cutting_amount);
                                $applied_amount = min($requested_amount, (float) $item->due_amount);
                                $total_cutting_amount += (float) $item->cutting_amount;
                                $total_due_after_cutting += (float) $item->due_amount - $applied_amount;
                            }
                        }
                        ?>
                        <table class="table table-bordered table-striped">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Row</th>
                                    <th>Customer Number</th>
                                    <th>Customer</th>
                                    <th>Due (At upload)</th>
                                    <th>Cutting Amount</th>
                                    <th>Due After Cutting</th>
                                    <th>Result</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($valid_items as $item) { ?>
                                    <?php
                                    $due_after_cutting = null;
                                    if ($item->cutting_amount !== null) {
                                        $requested_amount = max(0, (float) $item->cutting_amount);
                                        $applied_amount = min($requested_amount, (float) $item->due_amount);
                                        $due_after_cutting = (float) $item->due_amount - $applied_amount;
                                    }
                                    ?>
                                    <tr class="<?= (float) $item->cutting_amount > (float) $item->due_amount ? 'warning' : ''; ?>">
                                        <td><?= (int) $item->source_row_number; ?></td>
                                        <td><?= html_escape($item->customer_code); ?></td>
                                        <td>
                                            <?= html_escape($item->customer_name); ?>
                                            <?php if (!empty($item->customer_id)) { ?>
                                                <a href="<?= base_url('customers/customer_ledger/' . (int) $item->customer_id); ?>" class="btn btn-xs btn-default" target="_blank" rel="noopener" title="Open customer ledger" aria-label="Open ledger for <?= html_escape($item->customer_name); ?>">
                                                    <i class="fa fa-book"></i> Ledger
                                                </a>
                                            <?php } ?>
                                        </td>
                                        <td><?= app_number_format($item->due_amount); ?></td>
                                        <td><?= $item->cutting_amount === null ? '-' : app_number_format($item->cutting_amount); ?></td>
                                        <td><?= $due_after_cutting === null ? '-' : app_number_format($due_after_cutting); ?></td>
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
                            <tfoot>
                                <tr class="bg-gray">
                                    <th colspan="3">Total</th>
                                    <th><?= app_number_format($total_due_at_upload); ?></th>
                                    <th><?= app_number_format($total_cutting_amount); ?></th>
                                    <th><?= app_number_format($total_due_after_cutting); ?></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </tfoot>
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
                <?php if (!empty($invalid_items)) { ?>
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title">Invalid / Not Processed Rows (<?= count($invalid_items); ?>)</h3>
                        </div>
                        <div class="box-body">
                            <?php if ($batch->status === 'Completed') { ?>
                                <p>This batch was already processed. These invalid rows were not applied and were not part of the reconciliation.</p>
                            <?php } else { ?>
                                <p>These rows were not processed. Fix the customer match or validation issue before uploading them again.</p>
                            <?php } ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="bg-red">
                                        <tr>
                                            <th>CSV Row</th>
                                            <th>Customer Number</th>
                                            <th>Customer Name</th>
                                            <th>Requested Cutting</th>
                                            <th>Reason Not Processed</th>
                                            <th>Payment Applied</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invalid_items as $item) { ?>
                                            <tr class="danger">
                                                <td><?= (int) $item->source_row_number; ?></td>
                                                <td><?= html_escape($item->customer_code ?: '-'); ?></td>
                                                <td><?= html_escape($item->customer_name ?: '-'); ?></td>
                                                <td><?= $item->cutting_amount === null ? '-' : app_number_format($item->cutting_amount); ?></td>
                                                <td><?= html_escape($item->validation_error ?: 'Invalid row'); ?></td>
                                                <td><?= app_number_format($item->new_paid_amount ?? 0); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php } ?>
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