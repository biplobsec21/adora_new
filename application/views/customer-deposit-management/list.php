<!DOCTYPE html>
<html>
<?php $CI = &get_instance(); ?>

<head>
    <?php include APPPATH . 'views/comman/code_css_form.php'; ?>
    <link rel="stylesheet" href="<?= $theme_link; ?>plugins/datepicker/datepicker3.css">
    <style>
        .deposit-management-filter,
        .deposit-management-list,
        .deposit-audit-history {
            margin-top: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1><?= html_escape($page_title); ?><small> Customer deposits and withdrawals</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Customer Deposit Management</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?= $CI->currency(number_format((float) $totals['total_deposits'], 2, '.', '')); ?></h3>
                                <p>Deposits in Filter</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-orange">
                            <div class="inner">
                                <h3><?= $CI->currency(number_format((float) $totals['total_withdrawals'], 2, '.', '')); ?></h3>
                                <p>Withdrawals in Filter</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?= $selected_customer_balance === null ? '—' : $CI->currency(number_format((float) $selected_customer_balance, 2, '.', '')); ?></h3>
                                <p>Selected Customer's Available Deposit</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box box-default deposit-management-filter">
                    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter Transactions</h3></div>
                    <div class="box-body">
                        <form class="form-inline" method="get" action="<?= $base_url; ?>customer_deposit_management">
                            <div class="form-group">
                                <label for="customer_id">Customer</label>
                                <select class="form-control select2" name="customer_id" id="customer_id" style="min-width:260px">
                                    <option value="0">All Customers</option>
                                    <?php foreach ($customers as $customer) { ?>
                                        <option value="<?= (int) $customer['id']; ?>" <?= $selected_customer_id === (int) $customer['id'] ? 'selected' : ''; ?>><?= html_escape($customer['customer_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="from_date">From</label>
                                <input type="text" class="form-control datepicker" name="from_date" id="from_date" value="<?= html_escape($selected_from_date); ?>" placeholder="DD-MM-YYYY" style="width:125px">
                            </div>
                            <div class="form-group">
                                <label for="to_date">To</label>
                                <input type="text" class="form-control datepicker" name="to_date" id="to_date" value="<?= html_escape($selected_to_date); ?>" placeholder="DD-MM-YYYY" style="width:125px">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                            <a href="<?= $base_url; ?>customer_deposit_management" class="btn btn-default">Clear</a>
                        </form>
                    </div>
                </div>
                <div class="box box-info deposit-management-list">
                    <div class="box-header with-border">
                        <h3 class="box-title">Deposit Transaction List</h3>
                        <div class="box-tools">
                            <?php if ($CI->permissions('customer_deposit_add')) { ?>
                                <a class="btn btn-success" href="<?= $base_url; ?>customer_deposit_management/deposit"><i class="fa fa-plus"></i> Add Deposit</a>
                            <?php } ?>
                            <?php if ($CI->permissions('customer_deposit_withdraw')) { ?>
                                <a class="btn btn-warning" href="<?= $base_url; ?>customer_deposit_management/withdraw"><i class="fa fa-minus"></i> Withdraw Deposit</a>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th><th>Date</th><th>Customer</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Method</th><th>Note</th><th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions) { ?>
                                    <?php foreach ($transactions as $index => $transaction) { ?>
                                        <tr>
                                            <td><?= $index + 1; ?></td>
                                            <td><?= html_escape(show_date($transaction['transaction_date'])); ?></td>
                                            <td><?= html_escape($transaction['customer_name'] ?: 'Unknown Customer'); ?></td>
                                            <td><span class="label <?= $transaction['transaction_type'] === 'DEPOSIT' ? 'label-success' : 'label-warning'; ?>"><?= $transaction['transaction_type'] === 'DEPOSIT' ? 'Deposit' : 'Withdrawal'; ?></span></td>
                                            <td class="text-right"><?= $CI->currency(number_format((float) $transaction['amount'], 2, '.', '')); ?></td>
                                            <td class="text-right"><?= $CI->currency(number_format((float) $transaction['balance_after'], 2, '.', '')); ?></td>
                                            <td><?= html_escape($transaction['payment_method']); ?></td>
                                            <td><?= html_escape($transaction['note']); ?></td>
                                            <td><?= html_escape($transaction['created_by']); ?></td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr><td colspan="9" class="text-center">No deposit transactions found.</td></tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray"><th colspan="4" class="text-right">Filtered Totals</th><th class="text-right">Deposits: <?= $CI->currency(number_format((float) $totals['total_deposits'], 2, '.', '')); ?><br>Withdrawals: <?= $CI->currency(number_format((float) $totals['total_withdrawals'], 2, '.', '')); ?></th><th colspan="4"></th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="box box-default deposit-audit-history">
                    <div class="box-header with-border"><h3 class="box-title">Deposit Audit History</h3></div>
                    <div class="box-body table-responsive">
                        <?php if (!$audit_logs) { ?>
                            <p class="text-muted">No deposit changes recorded.</p>
                        <?php } else { ?>
                            <table class="table table-bordered table-striped">
                                <thead><tr><th>Time</th><th>Action</th><th>Record</th><th>Changed By</th><th>IP Address</th><th>Data</th></tr></thead>
                                <tbody>
                                    <?php foreach ($audit_logs as $audit) { ?>
                                        <tr>
                                            <td><?= html_escape($audit['created_at']); ?></td><td><?= html_escape($audit['action']); ?></td><td><?= html_escape($audit['record_id']); ?></td><td><?= html_escape($audit['changed_by']); ?></td><td><?= html_escape($audit['system_ip']); ?></td>
                                            <td><code><?= html_escape($audit['new_value'] ?: $audit['old_value']); ?></code></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>
    <?php include APPPATH . 'views/comman/code_js_form.php'; ?>
    <script src="<?= $theme_link; ?>plugins/datepicker/bootstrap-datepicker.js"></script>
    <script>
        $('.datepicker').datepicker({autoclose: true, format: 'dd-mm-yyyy', todayHighlight: true});
    </script>
</body>

</html>
