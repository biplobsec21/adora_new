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
                <h1>Customer Number Migration <small>Preview and update customer numbers</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Customer Number Migration</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Extraction Rules</h3>
                    </div>
                    <div class="box-body">
                        <p>Only a number at the beginning of the name, or a leading code such as <strong>BA-8938</strong>, is extracted. All other names become <strong>N/A</strong>.</p>
                        <form method="post" action="<?= $base_url; ?>customer_number_migration/apply" onsubmit="return confirm('Update customer numbers for all customers?');">
                            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-warning"><i class="fa fa-refresh"></i> Apply Migration</button>
                        </form>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Preview</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="customer-number-preview">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Customer ID</th>
                                    <th>Customer Name</th>
                                    <th>Current Number</th>
                                    <th>Proposed Number</th>
                                    <th>Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer) { ?>
                                    <tr>
                                        <td><?= html_escape($customer->customer_code); ?></td>
                                        <td><?= html_escape($customer->customer_name); ?></td>
                                        <td><?= html_escape($customer->customer_number); ?></td>
                                        <td><?= html_escape($customer->proposed_number); ?></td>
                                        <td><?= $customer->changed ? '<span class="label label-warning">Yes</span>' : '<span class="label label-success">No</span>'; ?></td>
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
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('#customer-number-preview').DataTable({
            pageLength: 50,
            order: [
                [0, 'asc']
            ]
        });
        $('.customers-active-li').addClass('active');
        $('.customer-number-migration-active-li').addClass('active');
    </script>
</body>

</html>