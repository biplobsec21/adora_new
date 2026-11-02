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
                <h1>Customer Refunds <small>Refund register and payment confirmation</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Customer Refunds</li>
                </ol>
            </section>
            <section class="content">
                <?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Refunds</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped" id="customer-refunds">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Refund #</th>
                                    <th>Customer</th>
                                    <th>Due Generation</th>
                                    <th>Cost Cutting Batch</th>
                                    <th>Refund Amount</th>
                                    <th>Status</th>
                                    <th>Generated</th>
                                    <th>Paid</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refunds as $refund) { ?>
                                    <tr>
                                        <td><?= (int) $refund->id; ?></td>
                                        <td><?= html_escape($refund->customer_number); ?> - <?= html_escape($refund->customer_name); ?></td>
                                        <td><?= html_escape($refund->generation_number); ?> · <?= html_escape(date('F Y', strtotime($refund->due_cycle_date))); ?></td>
                                        <td><?= html_escape($refund->batch_number); ?></td>
                                        <td><?= app_number_format($refund->refund_amount); ?></td>
                                        <td>
                                            <?php $status_label = $refund->status === 'Paid' ? 'success' : 'warning'; ?>
                                            <span class="label label-<?= $status_label; ?>"><?= html_escape($refund->status); ?></span>
                                        </td>
                                        <td><?= html_escape($refund->generated_at); ?></td>
                                        <td>
                                            <?php if ($refund->paid_at) { ?>
                                                <?= html_escape($refund->paid_at); ?><br>
                                                <small><?= html_escape($refund->paid_by); ?></small>
                                            <?php } else { ?>
                                                —
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($refund->status === 'Pending' && $CI->permissions('customer_refunds_pay')) { ?>
                                                <button type="button" class="btn btn-success btn-xs refund-payment-button"
                                                    data-toggle="modal" data-target="#refund-payment-modal"
                                                    data-id="<?= (int) $refund->id; ?>"
                                                    data-customer="<?= html_escape($refund->customer_number . ' - ' . $refund->customer_name); ?>"
                                                    data-generation="<?= html_escape($refund->generation_number); ?>"
                                                    data-generation-month="<?= html_escape(date('F Y', strtotime($refund->due_cycle_date))); ?>"
                                                    data-generation-due="<?= html_escape(app_number_format($refund->generation_due_amount)); ?>"
                                                    data-generation-cut="<?= html_escape(app_number_format($refund->generation_cut_amount)); ?>"
                                                    data-cost-batch="<?= html_escape($refund->batch_number); ?>"
                                                    data-cost-requested="<?= html_escape(app_number_format($refund->cost_cutting_requested_amount)); ?>"
                                                    data-cost-applied="<?= html_escape(app_number_format($refund->cost_cutting_applied_amount)); ?>"
                                                    data-due-after-cut="<?= html_escape(app_number_format($refund->due_after_cost_cutting)); ?>"
                                                    data-refund-amount="<?= html_escape(app_number_format($refund->refund_amount)); ?>">
                                                    <i class="fa fa-money"></i> Refund Payment
                                                </button>
                                            <?php } else { ?>
                                                —
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <?php include APPPATH . 'views/footer.php'; ?>
        <div class="control-sidebar-bg"></div>
    </div>

    <div class="modal fade" id="refund-payment-modal" tabindex="-1" role="dialog" aria-labelledby="refund-payment-title">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="post" id="refund-payment-form">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                        <h4 class="modal-title" id="refund-payment-title">Confirm Customer Refund Payment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <div class="alert alert-info">
                            <p><strong>Customer:</strong> <span id="refund-customer"></span></p>
                            <p>Your Due Generation for <strong id="refund-generation-month"></strong> (<span id="refund-generation-number"></span>) was <strong id="refund-generation-due"></strong>. Cost Cutting batch <strong id="refund-cost-batch"></strong> requested <strong id="refund-cost-requested"></strong> and applied <strong id="refund-cost-applied"></strong>, leaving <strong id="refund-due-after-cut"></strong> due after cutting. The overpaid amount of <strong id="refund-amount"></strong> is recorded for refund against this generation.</p>
                            <p class="text-muted">This confirmation marks the refund as paid; ensure the refund has actually been issued before confirming.</p>
                        </div>
                        <dl class="dl-horizontal">
                            <dt>Due Generation Total</dt><dd id="refund-generation-due-detail"></dd>
                            <dt>Due Generation Cut</dt><dd id="refund-generation-cut"></dd>
                            <dt>Refund Amount</dt><dd id="refund-amount-detail"></dd>
                        </dl>
                        <div class="form-group">
                            <label for="refund-payment-reference">Payment Reference (optional)</label>
                            <input type="text" name="payment_reference" id="refund-payment-reference" class="form-control" maxlength="100">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Confirm Refund Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
    <script>
        $('#customer-refunds').DataTable({
            pageLength: 50,
            order: [[0, 'desc']]
        });

        $('.refund-payment-button').on('click', function () {
            var button = $(this);
            $('#refund-payment-form').attr('action', '<?= $base_url; ?>customer_refunds/mark_paid/' + button.data('id'));
            $('#refund-customer').text(button.data('customer'));
            $('#refund-generation-month').text(button.data('generation-month'));
            $('#refund-generation-number').text(button.data('generation'));
            $('#refund-generation-due').text(button.data('generation-due'));
            $('#refund-cost-batch').text(button.data('cost-batch'));
            $('#refund-cost-requested').text(button.data('cost-requested'));
            $('#refund-cost-applied').text(button.data('cost-applied'));
            $('#refund-due-after-cut').text(button.data('due-after-cut'));
            $('#refund-amount, #refund-amount-detail').text(button.data('refund-amount'));
            $('#refund-generation-due-detail').text(button.data('generation-due'));
            $('#refund-generation-cut').text(button.data('generation-cut'));
            $('#refund-payment-reference').val('');
        });

        $('#refund-payment-form').on('submit', function () {
            return confirm('Confirm that this customer refund has actually been paid?');
        });
    </script>
</body>
</html>
