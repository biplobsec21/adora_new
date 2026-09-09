<!DOCTYPE html>
<html>

<head><?php include APPPATH . 'views/comman/code_css_datatable.php'; ?></head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper"><?php include APPPATH . 'views/sidebar.php'; ?><div class="content-wrapper">
            <section class="content-header">
                <h1>SMS Logs</h1>
            </section>
            <section class="content">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <form method="get" action="<?= $base_url; ?>sms_management/logs" class="form-inline">
                            <div class="form-group" style="margin-right:10px;">
                                <label for="date">Select Date</label>
                                <input type="date" name="date" id="date" class="form-control" value="<?= html_escape($selected_date); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="<?= $base_url; ?>sms_management/logs" class="btn btn-default">Reset</a>
                        </form>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="sms-logs">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Customer</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Provider Code</th>
                                    <th>Message</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($logs as $log) { ?><tr>
                                        <td><?= html_escape($log->created_at); ?></td>
                                        <td><?= html_escape($log->event_key); ?></td>
                                        <td><?= html_escape($log->customer_name ?: 'N/A'); ?></td>
                                        <td><?= html_escape($log->recipient); ?></td>
                                        <td><?= html_escape($log->status); ?></td>
                                        <td><?= html_escape($log->provider_code); ?></td>
                                        <td><?= nl2br(html_escape($log->message_body)); ?></td>
                                        <td><?= nl2br(html_escape($log->error_message)); ?></td>
                                    </tr><?php } ?></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div><?php include APPPATH . 'views/footer.php'; ?></div><?php include APPPATH . 'views/comman/code_js_datatable.php'; ?><script>
        $('#sms-logs').DataTable({
            pageLength: 50
        });
    </script>
</body>

</html>