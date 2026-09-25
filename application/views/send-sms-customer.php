<!DOCTYPE html>
<html>

<head>
    <?php include 'comman/code_css_form.php'; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <h1><?= html_escape($page_title); ?></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Send SMS to Customer</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-8 col-lg-6">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">New Customer Message</h3>
                            </div>
                            <form method="post" action="<?= $base_url; ?>send_sms_customer/send">
                                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="customer_id" name="customer_id" required style="width: 100%;">
                                            <option value="">Select customer</option>
                                            <?php foreach ($customers as $customer) { ?>
                                                <option value="<?= (int) $customer->id; ?>">
                                                    <?= html_escape($customer->customer_name); ?> (<?= html_escape($customer->mobile); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <?php if (empty($customers)) { ?><p class="help-block">No active customers with mobile numbers were found.</p><?php } ?>
                                    </div>
                                    <div class="form-group">
                                        <label for="message">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="message" name="message" rows="6" maxlength="1000" required placeholder="Type your message"></textarea>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" class="btn btn-success" <?= empty($customers) ? 'disabled' : ''; ?>><i class="fa fa-paper-plane"></i> Send SMS</button>
                                    <a href="<?= $base_url; ?>dashboard" class="btn btn-default">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include 'footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>
    <?php include 'comman/code_js_sound.php'; ?>
    <?php include 'comman/code_js_form.php'; ?>
</body>

</html>