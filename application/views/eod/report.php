<!DOCTYPE html>
<html>

<head>
    <?php include APPPATH . 'views/comman/code_css_form.php'; ?>
    <style>
        @media print {

            .main-header,
            .main-sidebar,
            .content-header,
            .box:first-child,
            .no-print,
            .main-footer,
            .control-sidebar {
                display: none !important;
            }

            .content-wrapper {
                margin-left: 0 !important;
            }

            .eod-report-table {
                font-size: 10px;
            }
        }
    </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <h1>EOD Report <small>Closed daily summaries</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">EOD Report</li>
                </ol>
            </section>

            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Report Filters</h3>
                    </div>
                    <div class="box-body">
                        <form id="eod-report-form" class="form-horizontal" onsubmit="return false;">
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="from_date">From Date</label>
                                <div class="col-sm-3">
                                    <div class="input-group date">
                                        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                        <input type="text" class="form-control datepicker" id="from_date" value="<?= show_date(date('Y-m-01')); ?>">
                                    </div>
                                </div>
                                <label class="col-sm-2 control-label" for="to_date">To Date</label>
                                <div class="col-sm-3">
                                    <div class="input-group date">
                                        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                        <input type="text" class="form-control datepicker" id="to_date" value="<?= show_date(date('Y-m-d')); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group no-print">
                                <div class="col-sm-8 col-sm-offset-2">
                                    <button type="button" id="eod-report-show" class="btn btn-success"><i class="fa fa-search"></i> Show Report</button>
                                    <button type="button" id="eod-report-print" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">EOD Records</h3>
                        <div class="no-print">
                            <?php $this->load->view('components/export_btn', array('tableId' => 'eod-report-data')); ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <div id="eod-report-message" class="alert alert-danger" style="display:none;"></div>
                        <div id="eod-report-loading" class="text-center" style="display:none;">Loading...</div>
                        <table id="eod-report-data" class="table table-bordered table-hover eod-report-table">
                            <thead>
                                <tr class="bg-blue">
                                    <th>#</th>
                                    <th>Closing Date</th>
                                    <th>Type</th>
                                    <th>Sales Due</th>
                                    <th>Collected Cash</th>
                                    <th>Expenses</th>
                                    <th>Cash Additions</th>
                                    <th>Cash Deductions</th>
                                    <th>Final Cash</th>
                                    <th>Adjustments</th>
                                    <th>Closed By</th>
                                    <th>Closed At</th>
                                </tr>
                            </thead>
                            <tbody id="eod-report-body"></tbody>
                            <tfoot>
                                <tr class="bg-gray">
                                    <th colspan="3" class="text-right">Totals</th>
                                    <th id="total-sales-due"></th>
                                    <th id="total-collected-cash"></th>
                                    <th id="total-expenses"></th>
                                    <th id="total-additions"></th>
                                    <th id="total-deductions"></th>
                                    <th id="total-final-cash"></th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <?php include APPPATH . 'views/footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>

    <?php include APPPATH . 'views/comman/code_js_sound.php'; ?>
    <?php include APPPATH . 'views/comman/code_js_form.php'; ?>
    <?php include APPPATH . 'views/comman/code_js_export.php'; ?>
    <script>
        (function($) {
            function escapeHtml(value) {
                return $('<div>').text(value === null || typeof value === 'undefined' ? '' : value).html();
            }

            function money(value) {
                return Number(value || 0).toFixed(2);
            }

            function renderReport(rows) {
                var totals = {
                    sales: 0,
                    collected: 0,
                    expenses: 0,
                    additions: 0,
                    deductions: 0,
                    final: 0
                };
                var body = $('#eod-report-body').empty();
                $.each(rows, function(index, row) {
                    totals.sales += Number(row.total_sales_due || 0);
                    totals.collected += Number(row.total_cash_collected || 0);
                    totals.expenses += Number(row.total_expenses || 0);
                    totals.additions += Number(row.custom_cash_additions || 0);
                    totals.deductions += Number(row.custom_cash_deductions || 0);
                    totals.final += Number(row.final_cash_in_hand || 0);
                    body.append('<tr>' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td>' + escapeHtml(row.closing_date) + '</td>' +
                        '<td>' + escapeHtml(row.closing_type) + '</td>' +
                        '<td class="text-right">' + money(row.total_sales_due) + '</td>' +
                        '<td class="text-right">' + money(row.total_cash_collected) + '</td>' +
                        '<td class="text-right">' + money(row.total_expenses) + '</td>' +
                        '<td class="text-right">' + money(row.custom_cash_additions) + '</td>' +
                        '<td class="text-right">' + money(row.custom_cash_deductions) + '</td>' +
                        '<td class="text-right">' + money(row.final_cash_in_hand) + '</td>' +
                        '<td class="text-center">' + escapeHtml(row.adjustment_count) + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>');
                });
                $('#total-sales-due').text(money(totals.sales));
                $('#total-collected-cash').text(money(totals.collected));
                $('#total-expenses').text(money(totals.expenses));
                $('#total-additions').text(money(totals.additions));
                $('#total-deductions').text(money(totals.deductions));
                $('#total-final-cash').text(money(totals.final));
            }

            function loadReport() {
                $('#eod-report-message').hide();
                $('#eod-report-loading').show();
                $.getJSON('<?= $base_url; ?>eod/report_data', {
                    from_date: $('#from_date').val(),
                    to_date: $('#to_date').val()
                }).done(function(response) {
                    renderReport(response.data || []);
                }).fail(function(xhr) {
                    var message = 'Unable to load the EOD report.';
                    if (xhr.responseJSON && xhr.responseJSON.error) message = xhr.responseJSON.error;
                    $('#eod-report-message').text(message).show();
                    renderReport([]);
                }).always(function() {
                    $('#eod-report-loading').hide();
                });
            }

            $('#eod-report-show').on('click', loadReport);
            $('#eod-report-print').on('click', function() {
                var printWindow = window.open('', '_blank', 'width=1200,height=800');
                if (!printWindow) {
                    return;
                }

                var table = $('#eod-report-data').clone().removeAttr('id').prop('outerHTML');
                var fromDate = escapeHtml($('#from_date').val());
                var toDate = escapeHtml($('#to_date').val());
                printWindow.document.open();
                printWindow.document.write('<!doctype html><html><head><title>EOD Report</title>' +
                    '<style>' +
                    '@page { size: landscape; margin: 12mm; }' +
                    'body { font-family: Arial, sans-serif; color: #111; }' +
                    'h1 { font-size: 20px; margin: 0 0 6px; }' +
                    'p { margin: 0 0 14px; font-size: 12px; }' +
                    'table { width: 100%; border-collapse: collapse; font-size: 10px; }' +
                    'th, td { border: 1px solid #777; padding: 5px; text-align: left; }' +
                    'th { background: #e5e5e5; }' +
                    'td:nth-child(4), td:nth-child(5), td:nth-child(6), td:nth-child(7), td:nth-child(8), td:nth-child(9), th:nth-child(4), th:nth-child(5), th:nth-child(6), th:nth-child(7), th:nth-child(8), th:nth-child(9) { text-align: right; }' +
                    '</style></head><body>' +
                    '<h1>EOD Report</h1><p>Date range: ' + fromDate + ' to ' + toDate + '</p>' +
                    table +
                    '</body></html>');
                printWindow.document.close();
                printWindow.focus();
                printWindow.onload = function() {
                    printWindow.print();
                    printWindow.close();
                };
            });
            loadReport();
        }(jQuery));
    </script>
    <script>
        $('.eod-report-active-li').addClass('active');
    </script>
</body>

</html>