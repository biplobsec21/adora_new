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
                <h1>Canteen Audit History <small>Due Generation and Cost Cutting</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Audit History</li>
                </ol>
            </section>
            <section class="content">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Monthly Activity</h3>
                    </div>
                    <form method="get" action="<?= $base_url; ?>canteen_audit" class="form-inline">
                        <div class="box-body">
                            <label for="audit-month">Month</label>
                            <select name="month" id="audit-month" class="form-control" style="min-width:220px;">
                                <?php foreach ($month_options as $month) { ?>
                                    <option value="<?= html_escape($month); ?>" <?= $month === $selected_month ? 'selected' : ''; ?>><?= html_escape(date('F Y', strtotime($month . '-01'))); ?></option>
                                <?php } ?>
                                <?php if (empty($month_options)) { ?><option value="<?= html_escape($selected_month); ?>"><?= html_escape(date('F Y', strtotime($selected_month . '-01'))); ?></option><?php } ?>
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Audit: <?= html_escape(date('F Y', strtotime($selected_month . '-01'))); ?></h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="canteen-audit-history">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Event</th>
                                    <th>Period</th>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Operator</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $row) { ?>
                                    <tr>
                                        <td><?= html_escape($row['event_type']); ?></td>
                                        <td><?= html_escape($row['period']); ?></td>
                                        <td><?= html_escape($row['reference']); ?></td>
                                        <td><?= html_escape($row['event_at']); ?></td>
                                        <td><?= html_escape($row['operator']); ?></td>
                                        <td><?= html_escape($row['status']); ?></td>
                                        <td><a href="<?= html_escape($row['url']); ?>" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View</a> <a href="<?= html_escape($row['download_url']); ?>" class="btn btn-xs btn-primary"><i class="fa fa-download"></i> CSV</a></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php if (empty($history)) { ?><p class="text-muted">No Due Generation or Cost Cutting activity was found for this month.</p><?php } ?>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
    </div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('#canteen-audit-history').DataTable({
            pageLength: 25,
            order: [
                [3, 'desc']
            ]
        });
        $('.payment-management-group-active-li').addClass('active');
        $('.canteen-audit-active-li').addClass('active');
    </script>
</body>

</html>