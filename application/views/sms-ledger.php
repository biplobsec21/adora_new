<!DOCTYPE html>
<html>

<head><?php include APPPATH . 'views/comman/code_css_datatable.php'; ?></head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <div class="content-wrapper" style="margin-left:0">
            <section class="content-header">
                <h1>Customer Ledger <small><?= html_escape($customer_info->customer_name); ?></small></h1>
            </section>
            <section class="content">
                <div class="alert alert-info">This secure ledger link is read-only.</div>
                <div class="box box-primary">
                    <div class="box-body">
                        <p><strong>Customer:</strong> <?= html_escape($customer_info->customer_name); ?></p>
                        <p><strong>Customer Number:</strong> <?= html_escape($customer_info->customer_number); ?></p>
                        <p><strong>Mobile:</strong> <?= html_escape($customer_info->mobile); ?></p>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($ledger_data as $entry) { ?><tr>
                                        <td><?= html_escape($entry->date); ?></td>
                                        <td><?= html_escape($entry->type); ?></td>
                                        <td><?= html_escape($entry->reference_no); ?></td>
                                        <td><?= app_number_format($entry->debit); ?></td>
                                        <td><?= app_number_format($entry->credit); ?></td>
                                        <td><?= app_number_format($entry->balance); ?></td>
                                    </tr><?php } ?></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>

</html>