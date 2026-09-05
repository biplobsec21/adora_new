<!DOCTYPE html>
<html>

<head>
    <?php include APPPATH . 'views/comman/code_css_form.php'; ?>
    <style>
        .eod-summary-card {
            min-height: 125px;
        }

        .eod-summary-card .small-box-footer {
            text-align: left;
            padding-left: 15px;
        }

        .eod-status {
            margin-top: 0;
        }
    </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <h1>End of Day <small>Daily financial summary</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">End of Day</li>
                </ol>
            </section>

            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>

                <?php if ($pending_date) { ?>
                    <div class="callout callout-warning">
                        <h4><i class="fa fa-exclamation-triangle"></i> Pending EOD</h4>
                        <p>The earliest EOD date with activity that has not been closed is <?= html_escape($pending_date); ?>.</p>
                    </div>
                <?php } ?>

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Summary Date</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?= $base_url; ?>eod" class="form-inline">
                            <div class="form-group">
                                <label for="eod-date">Date</label>
                                <input type="date" id="eod-date" name="date" class="form-control" value="<?= html_escape($summary['closing_date']); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">View Summary</button>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php
                    $cards = array(
                        array('label' => 'Collected Cash', 'value' => $summary['total_cash_collected'], 'class' => 'bg-green', 'icon' => 'fa-money', 'detail_type' => 'collected_cash'),
                        array('label' => 'Sales Due', 'value' => $summary['total_sales_due'], 'class' => 'bg-yellow', 'icon' => 'fa-clock-o', 'detail_type' => 'sales_due'),
                        array('label' => 'Expenses', 'value' => $summary['total_expenses'], 'class' => 'bg-red', 'icon' => 'fa-minus-circle', 'detail_type' => 'expenses'),
                        array('label' => 'Final Cash In Hand', 'value' => $summary['final_cash_in_hand'], 'class' => 'bg-aqua', 'icon' => 'fa-calculator', 'detail_type' => null),
                    );
                    foreach ($cards as $card) { ?>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box <?= $card['class']; ?> eod-summary-card">
                                <div class="inner">
                                    <h3><?= $CI->currency(number_format((float) $card['value'], 2, '.', '')); ?></h3>
                                    <p><?= html_escape($card['label']); ?></p>
                                    <?php if ($card['detail_type']) { ?>
                                        <button type="button" class="btn btn-default btn-xs eod-details-button" data-detail-type="<?= html_escape($card['detail_type']); ?>" data-detail-label="<?= html_escape($card['label']); ?>" data-detail-date="<?= html_escape($summary['closing_date']); ?>">
                                            <i class="fa fa-list"></i> View Details
                                        </button>
                                    <?php } ?>
                                </div>
                                <div class="icon"><i class="fa <?= $card['icon']; ?>"></i></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title">Cash Adjustments</h3>
                            </div>
                            <div class="box-body">
                                <dl class="dl-horizontal">
                                    <dt>Cash additions</dt>
                                    <dd><?= $CI->currency(number_format((float) $summary['custom_cash_additions'], 2, '.', '')); ?></dd>
                                    <dt>Cash deductions</dt>
                                    <dd><?= $CI->currency(number_format((float) $summary['custom_cash_deductions'], 2, '.', '')); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title">Closing Status</h3>
                            </div>
                            <div class="box-body">
                                <?php if ($summary['closing_type']) { ?>
                                    <p class="text-success eod-status"><i class="fa fa-check-circle"></i> Closed (<?= html_escape($summary['closing_type']); ?>)</p>
                                    <p>Closed by <?= html_escape($summary['closed_by']); ?> on <?= html_escape($summary['closed_at']); ?></p>
                                <?php } else { ?>
                                    <p class="text-warning eod-status"><i class="fa fa-exclamation-circle"></i> This date is not closed.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if ($CI->permissions('eod_adjustment_add')) { ?>
                        <div class="col-md-6">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Add Cash Adjustment</h3>
                                </div>
                                <div class="box-body">
                                    <?= form_open('eod/add_adjustment'); ?>
                                    <input type="hidden" name="closing_date" value="<?= html_escape($summary['closing_date']); ?>">
                                    <div class="form-group">
                                        <label for="adjustment-type">Type</label>
                                        <select id="adjustment-type" name="type" class="form-control" required>
                                            <option value="Addition">Cash Addition</option>
                                            <option value="Deduction">Cash Deduction</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="adjustment-amount">Amount</label>
                                        <input type="number" min="0.01" step="0.01" id="adjustment-amount" name="amount" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="adjustment-note">Note</label>
                                        <textarea id="adjustment-note" name="note" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-warning"><i class="fa fa-plus"></i> Add Adjustment</button>
                                    <?= form_close(); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($CI->permissions('eod_close')) { ?>
                        <div class="col-md-6">
                            <div class="box box-success">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Close This Date</h3>
                                </div>
                                <div class="box-body">
                                    <?php if ($summary['closing_type']) { ?>
                                        <p class="text-success">This date is already closed.</p>
                                    <?php } else { ?>
                                        <?= form_open('eod/close_day'); ?>
                                        <input type="hidden" name="closing_date" value="<?= html_escape($summary['closing_date']); ?>">
                                        <p>Save the displayed totals as the permanent EOD snapshot for <?= html_escape($summary['closing_date']); ?>.</p>
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Close this date? This action cannot be undone from this screen.');"><i class="fa fa-lock"></i> Close Day</button>
                                        <?= form_close(); ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <?php if ($CI->permissions('eod_adjustment_view')) { ?>
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">Manage Cash Adjustments</h3>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if (empty($adjustments)) { ?>
                                <p class="text-muted">No cash adjustments recorded for this date.</p>
                            <?php } else { ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Note</th>
                                            <th>Created By</th>
                                            <th>Created At</th>
                                            <?php if ($CI->permissions('eod_edit_closed_data')) { ?><th>Actions</th><?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($adjustments as $adjustment) { ?>
                                            <tr>
                                                <td><?= html_escape($adjustment->type); ?></td>
                                                <td><?= $CI->currency(number_format((float) $adjustment->amount, 2, '.', '')); ?></td>
                                                <td><?= html_escape($adjustment->note); ?></td>
                                                <td><?= html_escape($adjustment->created_by); ?></td>
                                                <td><?= html_escape($adjustment->created_at); ?></td>
                                                <?php if ($CI->permissions('eod_edit_closed_data')) { ?>
                                                    <td>
                                                        <form method="post" action="<?= $base_url; ?>eod/update_adjustment" class="form-inline" style="margin-bottom: 5px;">
                                                            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                                            <input type="hidden" name="id" value="<?= (int) $adjustment->id; ?>">
                                                            <input type="hidden" name="closing_date" value="<?= html_escape($summary['closing_date']); ?>">
                                                            <select name="type" class="form-control input-sm">
                                                                <option value="Addition" <?= $adjustment->type === 'Addition' ? 'selected' : ''; ?>>Addition</option>
                                                                <option value="Deduction" <?= $adjustment->type === 'Deduction' ? 'selected' : ''; ?>>Deduction</option>
                                                            </select>
                                                            <input type="number" name="amount" min="0.01" step="0.01" value="<?= html_escape($adjustment->amount); ?>" class="form-control input-sm" style="width: 100px;">
                                                            <input type="text" name="note" value="<?= html_escape($adjustment->note); ?>" class="form-control input-sm" placeholder="Note">
                                                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i></button>
                                                        </form>
                                                        <form method="post" action="<?= $base_url; ?>eod/delete_adjustment" onsubmit="return confirm('Delete this cash adjustment?');">
                                                            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                                            <input type="hidden" name="id" value="<?= (int) $adjustment->id; ?>">
                                                            <input type="hidden" name="closing_date" value="<?= html_escape($summary['closing_date']); ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Delete</button>
                                                        </form>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($CI->permissions('eod_audit_view')) { ?>
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title">EOD Change History</h3>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if (empty($audit_logs)) { ?>
                                <p class="text-muted">No changes recorded for this date.</p>
                            <?php } else { ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Table</th>
                                            <th>Action</th>
                                            <th>Record</th>
                                            <th>Changed By</th>
                                            <th>IP Address</th>
                                            <th>Previous Data</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audit_logs as $audit) { ?>
                                            <tr>
                                                <td><?= html_escape($audit->created_at); ?></td>
                                                <td><?= html_escape($audit->table_name); ?></td>
                                                <td><?= html_escape($audit->action); ?></td>
                                                <td><?= html_escape($audit->record_id); ?></td>
                                                <td><?= html_escape($audit->changed_by); ?></td>
                                                <td><?= html_escape($audit->system_ip); ?></td>
                                                <td><code><?= html_escape($audit->old_value); ?></code></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </section>
        </div>

        <div class="modal fade" id="eod-details-modal" tabindex="-1" role="dialog" aria-labelledby="eod-details-title">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="eod-details-title">Transaction Details</h4>
                    </div>
                    <div class="modal-body">
                        <div id="eod-details-loading" class="text-center" style="display:none;">Loading...</div>
                        <div id="eod-details-error" class="alert alert-danger" style="display:none;"></div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="eod-details-table">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="clearfix">
                            <span id="eod-details-page-status" class="pull-left"></span>
                            <div class="btn-group pull-right">
                                <button type="button" class="btn btn-default btn-sm" id="eod-details-prev">Previous</button>
                                <button type="button" class="btn btn-default btn-sm" id="eod-details-next">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include APPPATH . 'views/footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>
    <?php include APPPATH . 'views/comman/code_js_form.php'; ?>
    <script>
        (function($) {
            var detailState = {
                type: '',
                date: '',
                label: '',
                page: 1,
                totalPages: 0
            };

            function escapeHtml(value) {
                return $('<div>').text(value === null || typeof value === 'undefined' ? '' : value).html();
            }

            function formatAmount(value) {
                var amount = Number(value || 0);
                return amount.toFixed(2);
            }

            function renderDetails(response) {
                detailState.totalPages = response.total_pages;
                var $head = $('#eod-details-table thead').empty();
                var $body = $('#eod-details-table tbody').empty();
                var columns;
                if (response.type === 'collected_cash') {
                    columns = [
                        ['transaction_date', 'Date'],
                        ['sales_id', 'Sales ID'],
                        ['payment_type', 'Payment Type'],
                        ['amount', 'Amount'],
                        ['note', 'Note'],
                        ['created_by', 'Created By']
                    ];
                } else if (response.type === 'sales_due') {
                    columns = [
                        ['transaction_date', 'Date'],
                        ['sales_code', 'Sales Code'],
                        ['customer_id', 'Customer ID'],
                        ['grand_total', 'Grand Total'],
                        ['paid_amount', 'Paid Amount'],
                        ['amount', 'Due'],
                        ['payment_status', 'Payment Status']
                    ];
                } else {
                    columns = [
                        ['transaction_date', 'Date'],
                        ['expense_code', 'Expense Code'],
                        ['expense_for', 'Expense For'],
                        ['reference_no', 'Reference'],
                        ['amount', 'Amount'],
                        ['note', 'Note'],
                        ['created_by', 'Created By']
                    ];
                }
                var header = '<tr>' + $.map(columns, function(column) {
                    return '<th>' + escapeHtml(column[1]) + '</th>';
                }).join('') + '</tr>';
                $head.html(header);
                $.each(response.data, function(_, row) {
                    var cells = $.map(columns, function(column) {
                        var value = row[column[0]];
                        if (['amount', 'grand_total', 'paid_amount'].indexOf(column[0]) !== -1) {
                            value = formatAmount(value);
                        }
                        return '<td>' + escapeHtml(value) + '</td>';
                    }).join('');
                    $body.append('<tr>' + cells + '</tr>');
                });
                $('#eod-details-page-status').text(response.total + ' record(s), page ' + response.page + ' of ' + (response.total_pages || 1));
                $('#eod-details-prev').prop('disabled', response.page <= 1);
                $('#eod-details-next').prop('disabled', response.page >= response.total_pages || response.total_pages === 0);
            }

            function loadDetails() {
                $('#eod-details-loading').show();
                $('#eod-details-error').hide();
                $.getJSON('<?= $base_url; ?>eod/details', {
                    type: detailState.type,
                    date: detailState.date,
                    page: detailState.page
                }).done(renderDetails).fail(function(xhr) {
                    var message = 'Unable to load transaction details.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        message = xhr.responseJSON.error;
                    }
                    $('#eod-details-error').text(message).show();
                }).always(function() {
                    $('#eod-details-loading').hide();
                });
            }

            $('.eod-details-button').on('click', function() {
                detailState.type = $(this).data('detail-type');
                detailState.date = $(this).data('detail-date');
                detailState.label = $(this).data('detail-label');
                detailState.page = 1;
                $('#eod-details-title').text(detailState.label + ' Details - ' + detailState.date);
                $('#eod-details-modal').modal('show');
                loadDetails();
            });
            $('#eod-details-prev').on('click', function() {
                if (detailState.page > 1) {
                    detailState.page--;
                    loadDetails();
                }
            });
            $('#eod-details-next').on('click', function() {
                if (detailState.page < detailState.totalPages) {
                    detailState.page++;
                    loadDetails();
                }
            });
            $('#eod-details-modal').on('shown.bs.modal', function() {
                loadDetails();
            });
        }(jQuery));
    </script>
</body>

</html>