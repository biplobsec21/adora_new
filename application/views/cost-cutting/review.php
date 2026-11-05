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
                <h1>Review Cost Cutting <small><?= html_escape($batch->batch_number); ?></small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?= $base_url; ?>cost_cutting">Cost Cutting</a></li>
                    <li class="active">Review</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <?php if ($batch->status === 'Completed') { ?>
                    <div class="alert alert-success">Cost Cutting has been processed. Invalid rows were not applied; review their validation messages below.</div>
                <?php } elseif ($blocking_error_count > 0) { ?>
                    <div class="alert alert-warning">Fix the invalid rows before processing. N/A customers are excluded from Cost Cutting and must be handled manually from the customer panel.</div>
                <?php } else { ?>
                    <div class="alert alert-info">Review is complete. No payment records have been changed.</div>
                <?php } ?>
                <div class="row">
                    <div class="col-md-3">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?= (int) $batch->total_records; ?></h3>
                                <p>Total Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?= (int) $batch->valid_records; ?></h3>
                                <p>Valid Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?= (int) $batch->error_records; ?></h3>
                                <p>Errors</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?= html_escape($batch->payment_period_label); ?></h3>
                                <p>Payment Period</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Uploaded File: <?= html_escape($batch->file_name); ?></h3>
                    </div>
                    <div class="box-body table-responsive">
                        <h4>Valid Rows</h4>
                        <?php
                        $total_due_at_upload = 0;
                        $total_cutting_amount = 0;
                        $total_due_after_cutting = 0;
                        $valid_items = array();
                        foreach ($items as $item) {
                            $valid_items[] = $item;
                            $total_due_at_upload += (float) $item->due_amount;
                            if ($item->cutting_amount !== null) {
                                $requested_amount = max(0, (float) $item->cutting_amount);
                                $applied_amount = min($requested_amount, (float) $item->due_amount);
                                $total_cutting_amount += (float) $item->cutting_amount;
                                $total_due_after_cutting += (float) $item->due_amount - $applied_amount;
                            }
                        }
                        ?>
                        <table class="table table-bordered table-striped">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Row</th>
                                    <th>Customer Number</th>
                                    <th>Customer</th>
                                    <th>Due (At upload)</th>
                                    <th>Cutting Amount</th>
                                    <th>Due After Cutting</th>
                                    <th>Result</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($valid_items as $item) { ?>
                                    <?php
                                    $due_after_cutting = null;
                                    if ($item->cutting_amount !== null) {
                                        $requested_amount = max(0, (float) $item->cutting_amount);
                                        $applied_amount = min($requested_amount, (float) $item->due_amount);
                                        $due_after_cutting = (float) $item->due_amount - $applied_amount;
                                    }
                                    ?>
                                    <tr class="<?= (float) $item->cutting_amount > (float) $item->due_amount ? 'warning' : ''; ?>">
                                        <td><?= (int) $item->source_row_number; ?></td>
                                        <td><?= html_escape($item->customer_code); ?></td>
                                        <td>
                                            <?= html_escape($item->customer_name); ?>
                                            <?php if (!empty($item->customer_id)) { ?>
                                                <a href="<?= base_url('customers/customer_ledger/' . (int) $item->customer_id); ?>" class="btn btn-xs btn-default" target="_blank" rel="noopener" title="Open customer ledger" aria-label="Open ledger for <?= html_escape($item->customer_name); ?>">
                                                    <i class="fa fa-book"></i> Ledger
                                                </a>
                                            <?php } ?>
                                        </td>
                                        <td><?= app_number_format($item->due_amount); ?></td>
                                        <td><?= $item->cutting_amount === null ? '-' : app_number_format($item->cutting_amount); ?></td>
                                        <td><?= $due_after_cutting === null ? '-' : app_number_format($due_after_cutting); ?></td>
                                        <td><?= html_escape($item->new_status ?: '-'); ?></td>
                                        <td>
                                            <?php if ((float) $item->cutting_amount > (float) $item->due_amount) { ?>
                                                <span class="label label-warning">Overpaid</span>
                                            <?php } else { ?>
                                                <?= html_escape($item->validation_error ?: 'Valid'); ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray">
                                    <th colspan="3">Total</th>
                                    <th><?= app_number_format($total_due_at_upload); ?></th>
                                    <th><?= app_number_format($total_cutting_amount); ?></th>
                                    <th><?= app_number_format($total_due_after_cutting); ?></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="box-footer">
                        <?php if ($blocking_error_count === 0 && $batch->status !== 'Completed') { ?>
                            <form method="post" action="<?= $base_url; ?>cost_cutting/process/<?= (int) $batch->id; ?>" onsubmit="return confirm('Process this Cost Cutting batch? Overpayments will be capped at the current due amount.');" style="display:inline-block;">
                                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Process Cost Cutting</button>
                            </form>
                        <?php } ?>
                        <a href="<?= $base_url; ?>cost_cutting" class="btn btn-default">Upload Another File</a>
                    </div>
                </div>
                <?php if (!empty($batch_errors)) { ?>
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title">CSV Rows Not Matched / Processed (<?= count($batch_errors); ?>)</h3>
                        </div>
                        <div class="box-body">
                            <?php if ($batch->status === 'Completed') { ?>
                                <p>Unmatched CSV rows are stored separately. Rows already manually applied are linked to the selected customer and included in this batch; unresolved rows have not been applied.</p>
                            <?php } else { ?>
                                <p>These CSV rows are stored separately and will not be applied. Correct blocking errors before processing; unmatched names can be handled in a later upload.</p>
                            <?php } ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="bg-red">
                                        <tr>
                                            <th>CSV Row</th>
                                            <th>CSV Number</th>
                                            <th>Customer Name</th>
                                            <th>Requested Cutting</th>
                                            <th>Reason Not Processed</th>
                                            <th>Payment Applied</th>
                                            <th>Selected Customer</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($batch_errors as $item) { ?>
                                            <tr class="danger">
                                                <td><?= (int) $item->source_row_number; ?></td>
                                                <td><?= html_escape($item->customer_number ?: '-'); ?></td>
                                                <td><?= html_escape($item->customer_name ?: '-'); ?></td>
                                                <td><?= $item->cutting_amount === null ? '-' : app_number_format($item->cutting_amount); ?></td>
                                                <td><?= html_escape($item->error_message); ?></td>
                                                <td><?= $item->applied_amount === null ? '0.00' : app_number_format($item->applied_amount); ?></td>
                                                <td>
                                                    <?php if (!empty($item->resolved_customer_id)) { ?>
                                                        <?= html_escape($item->resolved_customer_number . ' - ' . $item->resolved_customer_name); ?>
                                                        <br><small>Applied by <?= html_escape($item->resolved_by); ?> at <?= html_escape($item->resolved_at); ?></small>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if ($batch->status === 'Completed' && empty($item->resolved_at) && $CI->permissions('payment_management_record')) { ?>
                                                        <button type="button" class="btn btn-xs btn-warning apply-batch-error"
                                                            data-error-id="<?= (int) $item->id; ?>"
                                                            data-customer-name="<?= html_escape($item->customer_name); ?>"
                                                            data-cutting-amount="<?= html_escape($item->cutting_amount); ?>">
                                                            <i class="fa fa-money"></i> Apply
                                                        </button>
                                                    <?php } elseif (!empty($item->resolved_at)) { ?>
                                                        <span class="label label-success">Applied</span>
                                                    <?php } else { ?>
                                                        <span class="label label-default">Available after batch completion</span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
    </div>
    <div class="modal fade" id="apply-batch-error-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" id="apply-batch-error-form">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Apply Cost Cutting to Matched Customer</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="customer_id" id="apply-error-customer-id">
                        <div class="form-group">
                            <label>Customer name from CSV</label>
                            <input type="text" id="apply-error-source-name" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Cutting amount from CSV</label>
                            <input type="text" id="apply-error-requested-amount" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="apply-error-search">Find matching active customer</label>
                            <input type="text" id="apply-error-search" class="form-control" autocomplete="off">
                            <p class="help-block">Only active customers whose names match all search terms are listed. Confirm the selected customer's current number and name before applying.</p>
                        </div>
                        <div class="form-group">
                            <label for="apply-error-customer-select">Current customer number and name</label>
                            <select id="apply-error-customer-select" class="form-control" required>
                                <option value="">Search for a customer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>DueFlow period snapshot (not an upload-time balance)</label>
                            <input type="text" id="apply-error-due-at-upload" class="form-control" readonly>
                            <p class="help-block">This is the customer's DueFlow amount for the batch period, when available. It is not a balance reconstructed for the exact CSV upload time.</p>
                        </div>
                        <div class="form-group">
                            <label>Current due</label>
                            <input type="text" id="apply-error-current-due" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Amount to apply now</label>
                            <input type="text" id="apply-error-amount-now" class="form-control" readonly>
                        </div>
                        <div id="apply-error-message" class="alert alert-warning" style="display:none"></div>
                        <p class="text-danger">The payment is capped at the selected customer's current due and will be recorded in this completed batch. It cannot be applied twice to the same customer in the batch.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="apply-error-submit" disabled>Confirm Cost Cutting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('.cost-cutting-active-li').addClass('active');
        (function() {
            var batchId = <?= (int) $batch->id; ?>;
            var baseUrl = <?= json_encode(base_url()); ?>;
            var activeErrorId = 0;
            var requestedAmount = 0;
            var searchTimer = null;
            var $modal = $('#apply-batch-error-modal');
            var $select = $('#apply-error-customer-select');

            function formatAmount(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function loadCandidates(query) {
                $select.html('<option value="">Searching...</option>').prop('disabled', true);
                $('#apply-error-submit').prop('disabled', true);
                $('#apply-error-message').hide().text('');
                $.getJSON(baseUrl + 'cost_cutting/error_customer_candidates/' + batchId + '/' + activeErrorId, {
                    q: query
                }).done(function(response) {
                    $select.empty().append($('<option>', {
                        value: '',
                        text: response.customers.length ? 'Select the correct customer' : 'No matching customers found'
                    }));
                    $.each(response.customers, function(_, customer) {
                        $select.append($('<option>', {
                            value: customer.id,
                            text: customer.customer_number + ' - ' + customer.customer_name
                        }));
                    });
                    $select.prop('disabled', false);
                }).fail(function() {
                    $select.html('<option value="">Could not load customer matches</option>').prop('disabled', true);
                    $('#apply-error-message').text('Customer matches could not be loaded. Please try again.').show();
                });
            }

            function loadCustomerPreview(customerId) {
                $('#apply-error-customer-id').val('');
                $('#apply-error-due-at-upload, #apply-error-current-due, #apply-error-amount-now').val('');
                $('#apply-error-submit').prop('disabled', true);
                if (!customerId) {
                    return;
                }
                $.getJSON(baseUrl + 'cost_cutting/error_customer_preview/' + batchId + '/' + activeErrorId, {
                    customer_id: customerId
                }).done(function(response) {
                    if (!response.success || !response.preview) {
                        $('#apply-error-message').text('The selected customer is no longer available for this row.').show();
                        return;
                    }
                    var preview = response.preview;
                    var applied = Math.min(requestedAmount, Number(preview.current_due));
                    $('#apply-error-customer-id').val(preview.customer_id);
                    $('#apply-error-due-at-upload').val(preview.due_at_upload === null ? 'No matching DueFlow row' : formatAmount(preview.due_at_upload));
                    $('#apply-error-current-due').val(formatAmount(preview.current_due));
                    $('#apply-error-amount-now').val(formatAmount(applied));
                    $('#apply-error-submit').prop('disabled', applied <= 0);
                    if (applied <= 0) {
                        $('#apply-error-message').text('This customer has no current due, so no Cost Cutting payment can be applied.').show();
                    } else if (applied < requestedAmount) {
                        $('#apply-error-message').text('The requested amount exceeds the current due. Only ' + formatAmount(applied) + ' will be applied.').show();
                    } else {
                        $('#apply-error-message').hide().text('');
                    }
                }).fail(function() {
                    $('#apply-error-message').text('The selected customer due could not be loaded. Please try again.').show();
                });
            }

            $('.apply-batch-error').on('click', function() {
                var $button = $(this);
                activeErrorId = Number($button.data('error-id'));
                requestedAmount = Number($button.data('cutting-amount'));
                $('#apply-error-source-name').val($button.data('customer-name'));
                $('#apply-error-requested-amount').val(formatAmount(requestedAmount));
                $('#apply-batch-error-form').attr('action', baseUrl + 'cost_cutting/apply_error_customer/' + batchId + '/' + activeErrorId);
                $('#apply-error-search').val($button.data('customer-name'));
                $('#apply-error-customer-id').val('');
                $('#apply-error-due-at-upload, #apply-error-current-due, #apply-error-amount-now').val('');
                $modal.modal('show');
                loadCandidates($button.data('customer-name'));
            });

            $('#apply-error-search').on('input', function() {
                var query = $(this).val();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    if (activeErrorId) {
                        loadCandidates(query);
                    }
                }, 300);
            });
            $select.on('change', function() {
                loadCustomerPreview($(this).val());
            });
            $('#apply-batch-error-form').on('submit', function(event) {
                if (!$('#apply-error-customer-id').val()) {
                    event.preventDefault();
                    return;
                }
                if (!confirm('Apply this Cost Cutting amount to the selected customer? The payment will be recorded in this completed batch.')) {
                    event.preventDefault();
                }
            });
        })();
    </script>
</body>

</html>