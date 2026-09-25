<!DOCTYPE html>
<html>

<head><?php include APPPATH . 'views/comman/code_css_datatable.php'; ?></head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <h1>Canteen Due Generation <small>Generate, send and reconcile monthly dues</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?= $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Due Generation</li>
                </ol>
            </section>
            <section class="content"><?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?= $opening_required ? 'Start Initial Due Setup' : 'Start Monthly Due Generation'; ?></h3>
                        <?php if ($CI->permissions('site_edit')) { ?>
                            <div class="box-tools"><a href="<?= $base_url; ?>site#tab_4" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> Due Generation Settings</a></div>
                        <?php } ?>
                    </div>
                    <?php if ($CI->permissions('payment_management_record')) { ?>
                        <form method="post" action="<?= $base_url; ?>due_generation/generate" id="dueflow-generation-form"><input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                            <div class="box-body">
                                <div class="form-group" style="max-width:420px;"><label for="generation_month"><?= $opening_required ? 'Initial Due Setup Month' : 'Due Generation Month'; ?></label><input type="month" id="generation_month" name="generation_month" class="form-control" min="<?= html_escape($available_generation_month); ?>" max="<?= html_escape($available_generation_month); ?>" value="<?= html_escape($default_generation_month); ?>" required>
                                    <p class="help-block"><?php if ($opening_required) { ?>The initial setup uses each customer's current outstanding due. No historical billing period is calculated.<?php } else { ?>Cost Cutting Date: <strong>day <?= (int) $settings->cost_cutting_day; ?></strong>.<?php } ?></p>
                                    <div id="dueflow-generation-message" class="alert alert-warning" style="display:none;margin-bottom:0;"></div>
                                </div>
                            </div>
                            <div class="box-footer"><button type="submit" id="generate-dueflow-button" class="btn btn-success"><i class="fa fa-magic"></i> Generate DueFlow</button></div>
                        </form>
                    <?php } ?>
                </div>
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Generation History</h3>
                    </div>

                    <table class="table table-bordered table-striped" id="dueflow-history">
                        <thead class="bg-primary">
                            <tr>
                                <th>Generation</th>
                                <th>Cycle</th>
                                <th>Records</th>
                                <th>Total Due</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>SMS</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($generations as $generation) { ?><tr>
                                    <td><?= html_escape($generation->generation_number); ?></td>
                                    <td><?= $generation->generation_type === 'Opening Balance' ? 'Initial Due Setup (' . html_escape(date('F Y', strtotime($generation->due_cycle_date))) . ')' : html_escape(date('F Y', strtotime($generation->due_cycle_date))); ?></td>
                                    <td><?= (int) $generation->total_records; ?></td>
                                    <td><?= app_number_format($generation->total_due_amount); ?></td>
                                    <td><?= app_number_format($generation->total_remaining_due); ?></td>
                                    <td><?= html_escape($generation->status); ?></td>
                                    <td><?= html_escape(isset($generation->sms_status) ? $generation->sms_status : 'Not Sent'); ?></td>
                                    <td><a class="btn btn-xs btn-info" href="<?= $base_url; ?>due_generation/details/<?= (int) $generation->id; ?>"><i class="fa fa-eye"></i> Details</a> <a class="btn btn-xs btn-primary" href="<?= $base_url; ?>due_generation/download/<?= (int) $generation->id; ?>"><i class="fa fa-download"></i> CSV</a><?php if ($CI->permissions('send_sms') && $sms_settings->enabled && $sms_settings->due_generation_enabled && ((!isset($generation->sms_status) || !in_array($generation->sms_status, array('Sending', 'Sent'), true)) || ($generation->sms_status === 'Sending' && empty($generation->sms_sent_at)))) { ?> <button type="button" class="btn btn-xs btn-success preview-sms" data-id="<?= (int) $generation->id; ?>"><i class="fa fa-commenting"></i> Send SMS</button><?php } ?></td>
                                </tr><?php } ?></tbody>
                    </table>
                </div>
                <div class="modal fade" id="sms-preview-modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title">Preview SMS</h4>
                            </div>
                            <div class="modal-body">
                                <p><strong>Recipients:</strong> <span id="sms-recipient-count">0</span></p>
                                <div class="table-responsive" style="max-height:420px;overflow:auto">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Mobile</th>
                                                <th>Message</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sms-preview-rows"></tbody>
                                    </table>
                                </div>
                                <div id="sms-preview-error" class="alert alert-danger" style="display:none"></div>
                            </div>
                            <div class="modal-footer">
                                <form method="post" id="sms-send-form"><input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" id="confirm-sms-send"><i class="fa fa-paper-plane"></i> Send Pending SMS</button></form>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        </section>
    </div><?php include APPPATH . 'views/footer.php'; ?>
    </div>
    <?php include APPPATH . 'views/comman/code_js_datatable.php'; ?><script>
        $('#dueflow-history').DataTable({
            pageLength: 25,
            order: [
                [1, 'desc']
            ]
        });
        $('.preview-sms').on('click', function() {
            var id = $(this).data('id');
            var rows = $('#sms-preview-rows');
            var error = $('#sms-preview-error');
            rows.empty();
            error.hide().text('');
            $('#confirm-sms-send').prop('disabled', true);
            $.getJSON('<?= $base_url; ?>due_generation/sms_preview/' + id, function(response) {
                if (!response.success) {
                    error.text(response.message || 'SMS preview could not be loaded.').show();
                    return;
                }
                $('#sms-recipient-count').text(response.rows.length);
                var eligibleRows = 0;
                $.each(response.rows, function(index, row) {
                    if (row.status !== 'Sent' && row.status !== 'Invalid Number') eligibleRows++;
                    rows.append('<tr><td>' + $('<div>').text(row.customer_name).html() + '</td><td>' + $('<div>').text(row.mobile).html() + '</td><td>' + $('<div>').text(row.message).html() + '</td><td>' + $('<div>').text(row.status_label || 'Not Sent').html() + '</td></tr>');
                });
                $('#sms-send-form').attr('action', '<?= $base_url; ?>due_generation/send_sms/' + id);
                $('#confirm-sms-send').prop('disabled', eligibleRows === 0);
                $('#confirm-sms-send').text(eligibleRows === 0 ? 'No Valid SMS to Send' : 'Send Pending SMS');
                $('#sms-preview-modal').modal('show');
            }).fail(function() {
                error.text('SMS preview could not be loaded.').show();
                $('#sms-preview-modal').modal('show');
            });
        });
        (function() {
            var generationMonth = $('#generation_month');
            var generateButton = $('#generate-dueflow-button');
            var message = $('#dueflow-generation-message');
            var costCuttingDay = <?= (int) $settings->cost_cutting_day; ?>;
            var generatedCycles = <?= json_encode($generated_cycle_dates); ?>;
            var today = '<?= html_escape($CUR_DATE); ?>';
            var openingRequired = <?= $opening_required ? 'true' : 'false'; ?>;
            var availableMonth = '<?= html_escape($available_generation_month); ?>';

            function dueDate(month) {
                var parts = month.split('-');
                var lastDay = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10), 0).getDate();
                var day = Math.min(costCuttingDay, lastDay);
                return parts[0] + '-' + parts[1] + '-' + ('0' + day).slice(-2);
            }

            function updateGenerationState() {
                var month = generationMonth.val();
                var selectedDueDate = month ? dueDate(month) : '';
                var openingDueDate = dueDate(today.substring(0, 7));
                var text = '';
                var disabled = !month;
                if (month !== availableMonth) {
                    disabled = true;
                    text = 'Only ' + availableMonth + ' is currently available for DueFlow generation.';
                } else if (selectedDueDate > today) {
                    disabled = true;
                    text = 'Generation is available on or after ' + selectedDueDate + '.';
                } else if (openingRequired) {
                    if (openingDueDate > today) {
                        disabled = true;
                        text = 'Initial Due Setup is available on or after ' + openingDueDate + '.';
                    } else if (month !== today.substring(0, 7)) {
                        disabled = true;
                        text = 'Initial Due Setup must be created for the current month.';
                    } else {
                        text = 'This will create the Initial Due Setup from each customer\'s current total due.';
                    }
                } else if ($.inArray(selectedDueDate, generatedCycles) !== -1) {
                    disabled = true;
                    text = 'This billing cycle has already been generated.';
                }
                generateButton.prop('disabled', disabled);
                message.text(text).toggle(!!text);
            }

            generationMonth.on('change', updateGenerationState);
            updateGenerationState();
        }());
    </script>
</body>

</html>