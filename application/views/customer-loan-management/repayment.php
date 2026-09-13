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
                    <small>Record customer loan repayment</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo $base_url; ?>customer_loan_management">Customer Loan Management</a></li>
                    <li class="active">Record Repayment</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-8 col-md-offset-2">
                        <?php include __DIR__ . '/../comman/code_flashdata.php'; ?>
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Repayment Details</h3>
                            </div>

                            <form class="form-horizontal" method="post" action="<?php echo $base_url; ?>customer_loan_management/save_repayment">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Open Loan</label>
                                        <div class="col-sm-9">
                                            <select class="form-control select2" name="loan_id" required>
                                                <option value="">Select Open Loan</option>
                                                <?php foreach ($open_loans as $loan) { ?>
                                                    <option value="<?= html_escape($loan['id']); ?>">
                                                        <?= html_escape($loan['customer_name']); ?> - Balance: <?= html_escape($CI->currency($loan['balance_amount'])); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Repayment Amount</label>
                                        <div class="col-sm-9">
                                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Payment Method</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" name="payment_method" value="Cash">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Date</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control datepicker" name="transaction_date" value="<?= html_escape(date('d-m-Y')); ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Note</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="note" rows="4"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="box-footer">
                                    <div class="col-sm-offset-3 col-sm-9">
                                        <button type="submit" class="btn btn-success">Save Repayment</button>
                                        <a href="<?php echo $base_url; ?>customer_loan_management" class="btn btn-default">Cancel</a>
                                    </div>
                                </div>
                            </form>
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