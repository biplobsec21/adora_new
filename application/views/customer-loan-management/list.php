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
                        <div class="box box-info">
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
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No customer loans found.</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
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
</body>

</html>