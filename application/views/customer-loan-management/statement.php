<!DOCTYPE html>
<html>
<?php $CI = &get_instance(); ?>

<head>
    <?php include __DIR__ . '/../comman/code_css_form.php'; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1>
                    <?= html_escape($page_title); ?>
                    <small>Customer loan statement</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo $base_url; ?>customer_loan_management">Customer Loan Management</a></li>
                    <li class="active">Statement</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <?php include __DIR__ . '/../comman/code_flashdata.php'; ?>
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Select Customer</h3>
                            </div>
                            <div class="box-body">
                                <form class="form-inline" method="get" action="<?php echo $base_url; ?>customer_loan_management/statement">
                                    <div class="form-group">
                                        <label for="customer_id">Customer</label>
                                        <select class="form-control select2" name="customer_id" id="customer_id">
                                            <option value="">Select Customer</option>
                                            <?php foreach ($customers as $customer_option) { ?>
                                                <option value="<?= html_escape($customer_option['id']); ?>" <?= ($selected_customer_id == $customer_option['id']) ? 'selected' : ''; ?>>
                                                    <?= html_escape($customer_option['customer_name']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">View Statement</button>
                                </form>
                            </div>
                        </div>

                        <?php if (isset($summary)) { ?>
                            <div class="box box-success">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Summary for <?= html_escape($customer['customer_name'] ?? 'Customer'); ?></h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="small-box bg-aqua">
                                                <div class="inner">
                                                    <h3><?= html_escape($CI->currency($summary['total_loan_amount'])); ?></h3>
                                                    <p>Total Loan Amount</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small-box bg-green">
                                                <div class="inner">
                                                    <h3><?= html_escape($CI->currency($summary['total_paid_amount'])); ?></h3>
                                                    <p>Total Paid Amount</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small-box bg-red">
                                                <div class="inner">
                                                    <h3><?= html_escape($CI->currency($summary['total_balance_amount'])); ?></h3>
                                                    <p>Outstanding Balance</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Transaction History</h3>
                                </div>
                                <div class="box-body table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($statement)) { ?>
                                                <?php foreach ($statement as $index => $entry) { ?>
                                                    <tr>
                                                        <td><?= $index + 1; ?></td>
                                                        <td><?= html_escape(show_date($entry['transaction_date'])); ?></td>
                                                        <td><?= html_escape($entry['transaction_type']); ?></td>
                                                        <td><?= html_escape($CI->currency($entry['amount'])); ?></td>
                                                        <td><?= html_escape($entry['payment_method'] ?? ''); ?></td>
                                                        <td><?= html_escape($entry['note'] ?? ''); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No transactions found for this customer.</td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </section>
        </div>
        <?php include __DIR__ . '/../footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>

    <?php include __DIR__ . '/../comman/code_js_form.php'; ?>
</body>

</html>