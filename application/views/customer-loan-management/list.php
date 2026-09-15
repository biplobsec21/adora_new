<!DOCTYPE html>
<html>
<?php $CI = &get_instance(); ?>

<head>
    <?php include __DIR__ . '/../comman/code_css_form.php'; ?>
    <link rel="stylesheet" href="<?php echo $theme_link; ?>plugins/datepicker/datepicker3.css">
    <style>
        .loan-management-filter,
        .loan-management-list,
        .loan-audit-history {
            margin-top: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1>
                    <?= html_escape($page_title); ?>
                    <small>Separate customer loan module</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Customer Loan Management</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <?php include __DIR__ . '/../comman/code_flashdata.php'; ?>
                        <div class="box box-default loan-management-filter">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Loans</h3>
                            </div>
                            <div class="box-body">
                                <form class="form-inline" method="get" action="<?php echo $base_url; ?>customer_loan_management">
                                    <div class="form-group">
                                        <label for="customer_id">Customer</label>
                                        <select class="form-control select2" name="customer_id" id="customer_id" style="min-width: 280px;">
                                            <option value="0">All Customers</option>
                                            <?php foreach ($customers as $customer) { ?>
                                                <option value="<?= html_escape($customer['id']); ?>" <?= $selected_customer_id === (int) $customer['id'] ? 'selected' : ''; ?>>
                                                    <?= html_escape($customer['customer_name']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="from_date">From</label>
                                        <input type="text" class="form-control datepicker" name="from_date" id="from_date" value="<?= html_escape($selected_from_date); ?>" placeholder="DD-MM-YYYY" style="width: 125px;">
                                    </div>
                                    <div class="form-group">
                                        <label for="to_date">To</label>
                                        <input type="text" class="form-control datepicker" name="to_date" id="to_date" value="<?= html_escape($selected_to_date); ?>" placeholder="DD-MM-YYYY" style="width: 125px;">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                                    <?php if ($selected_customer_id > 0 || $selected_from_date || $selected_to_date) { ?>
                                        <a href="<?php echo $base_url; ?>customer_loan_management" class="btn btn-default">Clear</a>
                                    <?php } ?>
                                </form>
                            </div>
                        </div>

                        <div class="box box-info loan-management-list">
                            <div class="box-header with-border">
                                <h3 class="box-title">Customer Loan List</h3>
                                <div class="box-tools">
                                    <a class="btn btn-success" href="<?php echo $base_url; ?>customer_loan_management/add">
                                        <i class="fa fa-plus"></i> Add Loan
                                    </a>
                                    <a class="btn btn-primary" href="<?php echo $base_url; ?>customer_loan_management/repayment">
                                        <i class="fa fa-money"></i> Record Repayment
                                    </a>
                                    <a class="btn btn-warning" href="<?php echo $base_url; ?>customer_loan_management/statement">
                                        <i class="fa fa-file-text-o"></i> Statement
                                    </a>
                                </div>
                            </div>
                            <br />
                            <div class="box-body table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th>Loan Date</th>
                                            <th>Loan Amount</th>
                                            <th>Paid Amount</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Note</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($loans)) { ?>
                                            <?php foreach ($loans as $index => $loan) { ?>
                                                <tr>
                                                    <td><?= $index + 1; ?></td>
                                                    <td><?= html_escape($loan['customer_name'] ?? 'Unknown Customer'); ?></td>
                                                    <td><?= html_escape(show_date($loan['loan_date'])); ?></td>
                                                    <td><?= html_escape($CI->currency($loan['loan_amount'])); ?></td>
                                                    <td><?= html_escape($CI->currency($loan['paid_amount'])); ?></td>
                                                    <td><?= html_escape($CI->currency($loan['balance_amount'])); ?></td>
                                                    <td>
                                                        <?php if (($loan['status'] ?? '') === 'OPEN') { ?>
                                                            <span class="label label-warning">OPEN</span>
                                                        <?php } elseif (($loan['status'] ?? '') === 'CLOSED') { ?>
                                                            <span class="label label-success">CLOSED</span>
                                                        <?php } else { ?>
                                                            <span class="label label-default"><?= html_escape($loan['status']); ?></span>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= html_escape($loan['note'] ?? ''); ?></td>
                                                    <td>
                                                        <?php if ($CI->permissions('customer_loan_edit')) { ?>
                                                            <a class="btn btn-xs btn-warning" href="<?= base_url('customer_loan_management/edit/' . $loan['id']); ?>" title="Edit loan">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No customer loans found.</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="box box-default loan-audit-history">
                            <div class="box-header with-border">
                                <h3 class="box-title">Loan Audit History</h3>
                            </div>
                            <div class="box-body table-responsive">
                                <?php if (empty($audit_logs)) { ?>
                                    <p class="text-muted">No loan changes recorded.</p>
                                <?php } else { ?>
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Table</th>
                                                <th>Action</th>
                                                <th>Record</th>
                                                <th>Changed By</th>
                                                <th>IP Address</th>
                                                <th>Previous Data</th>
                                                <th>New Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($audit_logs as $audit) { ?>
                                                <?php
                                                $old_value = $audit['old_value'];
                                                $new_value = $audit['new_value'];
                                                if (empty($new_value)) {
                                                    $fallback_value = json_decode($old_value, true);
                                                    if (is_array($fallback_value) && array_key_exists('previous', $fallback_value) && array_key_exists('new', $fallback_value)) {
                                                        $old_value = json_encode($fallback_value['previous']);
                                                        $new_value = json_encode($fallback_value['new']);
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= html_escape($audit['created_at']); ?></td>
                                                    <td><?= html_escape($audit['table_name']); ?></td>
                                                    <td><?= html_escape($audit['action']); ?></td>
                                                    <td><?= html_escape($audit['record_id']); ?></td>
                                                    <td><?= html_escape($audit['changed_by']); ?></td>
                                                    <td><?= html_escape($audit['system_ip']); ?></td>
                                                    <td><code><?= html_escape($old_value); ?></code></td>
                                                    <td><code><?= html_escape($new_value); ?></code></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php include __DIR__ . '/../footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>

    <?php include __DIR__ . '/../comman/code_js_form.php'; ?>
    <script src="<?php echo $theme_link; ?>plugins/datepicker/bootstrap-datepicker.js"></script>
    <script>
        $('.datepicker').datepicker({
            autoclose: true,
            format: 'dd-mm-yyyy',
            todayHighlight: true
        });
    </script>
</body>

</html>