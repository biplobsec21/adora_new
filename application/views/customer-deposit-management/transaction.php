<!DOCTYPE html>
<html>

<head>
    <?php include APPPATH . 'views/comman/code_css_form.php'; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1><?= html_escape($page_title); ?><small> Record customer funds</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?= $base_url; ?>customer_deposit_management">Customer Deposit Management</a></li>
                    <li class="active"><?= !empty($withdrawal) ? 'Withdraw Deposit' : 'Add Deposit'; ?></li>
                </ol>
            </section>
            <section class="content">
                <div class="row">
                    <div class="col-md-8 col-md-offset-2">
                        <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= !empty($withdrawal) ? 'Withdrawal Details' : 'Deposit Details'; ?></h3>
                            </div>
                            <form class="form-horizontal" method="post" action="<?= $base_url; ?>customer_deposit_management/<?= !empty($withdrawal) ? 'save_withdrawal' : 'save_deposit'; ?>">
                                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Customer</label>
                                        <div class="col-sm-9">
                                            <select class="form-control select2" name="customer_id" id="deposit-customer" required>
                                                <option value="">Select Customer</option>
                                                <?php foreach ($customers as $customer) { ?>
                                                    <option value="<?= (int) $customer['id']; ?>"<?= !empty($withdrawal) ? ' data-balance="' . html_escape($customer['deposit_balance']) . '"' : ''; ?>>
                                                        <?= html_escape($customer['customer_name']); ?>
                                                        <?php if (!empty($withdrawal)) { ?> - Available: <?= html_escape(number_format((float) $customer['deposit_balance'], 2)); ?><?php } ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <?php if (!empty($withdrawal)) { ?>
                                                <p class="help-block">Current available deposit: <strong id="deposit-balance">0.00</strong>. A withdrawal is checked against the balance on the selected transaction date.</p>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label"><?= !empty($withdrawal) ? 'Withdrawal Amount' : 'Deposit Amount'; ?></label>
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
                                            <textarea class="form-control" name="note" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <div class="col-sm-offset-3 col-sm-9">
                                        <button type="submit" class="btn <?= !empty($withdrawal) ? 'btn-warning' : 'btn-success'; ?>">
                                            <?= !empty($withdrawal) ? 'Record Withdrawal' : 'Save Deposit'; ?>
                                        </button>
                                        <a href="<?= $base_url; ?>customer_deposit_management" class="btn btn-default">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
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
        $('.datepicker').datepicker({
            autoclose: true,
            format: 'dd-mm-yyyy',
            todayHighlight: true
        });
        $('#deposit-customer').on('change', function() {
            var balance = $(this).find(':selected').data('balance') || 0;
            $('#deposit-balance').text(Number(balance).toFixed(2));
        }).trigger('change');
    </script>
</body>

</html>
