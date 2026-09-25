<!DOCTYPE html>
<html>

<head>
    <?php include APPPATH . 'views/comman/code_css_datatable.php'; ?>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper"><?php include APPPATH . 'views/sidebar.php'; ?><div class="content-wrapper">
            <section class="content-header">
                <h1><?= html_escape($due_generation_title); ?> <small><?= html_escape($generation->generation_number); ?></small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>due_generation">Due Generation</a></li>
                    <li class="active">Details</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <?php if ($generation_success_message !== '') { ?>
                    <div class="alert alert-success alert-dismissable text-center">
                        <a href="javascript:void(0)" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                        <strong><?= html_escape($generation_success_message); ?></strong>
                    </div>
                <?php } ?>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Customer Due Ledger</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="dueflow-items">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Customer</th>
                                    <th>Address</th>
                                    <th>Mobile</th>
                                    <th>Previous</th>
                                    <th>New Due</th>
                                    <th>Total Due</th>
                                    <th>Cut</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($items as $item) { ?><tr>
                                        <td><?= html_escape($item->customer_number); ?> - <?= html_escape($item->customer_name); ?></td>
                                        <td><?= html_escape($item->customer_address ?? ''); ?></td>
                                        <td><?= html_escape($item->mobile); ?></td>
                                        <td><?= app_number_format($item->previous_outstanding_amount); ?></td>
                                        <td><?= app_number_format($item->new_due_amount); ?></td>
                                        <td><?= app_number_format($item->total_due_amount); ?></td>
                                        <td><?= app_number_format($item->actually_cut_amount); ?></td>
                                        <td><?= app_number_format($item->remaining_due_amount); ?></td>
                                        <td><?= html_escape($item->status); ?></td>
                                    </tr><?php } ?></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div><?php include APPPATH . 'views/footer.php'; ?></div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?><script>
        $('#dueflow-items').DataTable({
            pageLength: 50
        });
    </script>
</body>

</html>