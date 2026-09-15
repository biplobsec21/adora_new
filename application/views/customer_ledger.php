<!DOCTYPE html>
<html>

<head>
    <?php include "comman/code_css_datatable.php"; ?>
    <link rel="stylesheet" href="<?php echo $theme_link; ?>plugins/datepicker/datepicker3.css">
    <link rel="stylesheet" href="<?php echo $theme_link; ?>plugins/select2/select2.min.css">

    <style>
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .debit-amount {
            color: #dc3545;
            font-weight: bold;
        }

        .credit-amount {
            color: #28a745;
            font-weight: bold;
        }

        .balance-positive {
            color: #28a745;
            font-weight: bold;
        }

        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }

        /* Child row styles */
        .details-control {
            cursor: pointer;
            color: #3c8dbc;
            text-align: center;
        }

        .details-control:hover {
            color: #23527c;
        }

        .details-control i {
            transition: transform 0.3s;
        }

        .details-control.open i {
            transform: rotate(90deg);
        }

        .invoice-items-child {
            background: #f8f9fa;
            padding: 15px;
            border-left: 3px solid #3c8dbc;
        }

        .items-table-child {
            width: 100%;
            font-size: 12px;
            margin-bottom: 0;
        }

        .items-table-child th {
            background: #e9ecef;
            font-weight: 600;
            padding: 8px;
        }

        .items-table-child td {
            padding: 6px 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .global-customer-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 0;
            margin-bottom: 20px;
        }

        .global-customer-form {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .global-customer-form .form-group {
            margin-bottom: 0;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .customer-account-info {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .customer-details {
            border-right: 1px solid #dee2e6;
            padding: 20px;
        }

        .account-summary-merged {
            padding: 20px;
        }

        .summary-item-merged {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-item-merged:last-child {
            border-bottom: none;
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 1.1em;
            padding-top: 12px;
            margin-top: 5px;
        }

        .info-badge {
            background: #6c757d;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .customer-info-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .customer-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        /* Row with items indicator */
        tr.has-items {
            background-color: #f9f9f9;
        }

        tr.has-items:hover {
            background-color: #f0f0f0;
        }

        .item-count-badge {
            background: #3c8dbc;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }

        .payment-transaction {
            background-color: #f8fff8 !important;
        }

        .payment-transaction:hover {
            background-color: #f0fff0 !important;
        }

        .text-success {
            color: #28a745 !important;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .global-customer-form {
                flex-direction: column;
                align-items: stretch;
            }

            .global-customer-form .form-control {
                width: 100% !important;
            }

            .global-customer-form .form-group {
                width: 100%;
            }

            .customer-details {
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
        }
    </style>
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <h1>
                    <?= $page_title; ?>
                    <small>Customer Account Statement</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo $base_url; ?>customers">Customers</a></li>
                    <li class="active"><?= $page_title; ?></li>
                </ol>
            </section>

            <!-- Global Customer Selection Header -->
            <div class="global-customer-header">
                <div class="container-fluid">
                    <?= form_open('customers/customer_ledger', array('method' => 'post', 'class' => 'global-customer-form')); ?>
                    <div class="form-group">
                        <label for="global_customer_id">Customer:</label>
                        <select class="form-control select2-global" name="global_customer_id" id="global_customer_id" required style="width: 300px;">
                            <option value="">-- Select Customer --</option>
                            <?php
                            if (!empty($all_customers)) {
                                foreach ($all_customers as $customer) {
                                    $selected = (isset($customer_id) && $customer->id == $customer_id) ? 'selected' : '';
                                    echo "<option value='{$customer->id}' {$selected}>{$customer->customer_name} ({$customer->mobile})</option>";
                                }
                            } else {
                                echo "<option value=''>No customers found</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">From:</label>
                        <input type="text" class="form-control datepicker" name="start_date" id="start_date"
                            value="<?= $start_date; ?>" required style="width: 120px;" placeholder="DD-MM-YYYY">
                    </div>

                    <div class="form-group">
                        <label for="end_date">To:</label>
                        <input type="text" class="form-control datepicker" name="end_date" id="end_date"
                            value="<?= $end_date; ?>" required style="width: 120px;" placeholder="DD-MM-YYYY">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i> View Ledger
                        </button>
                    </div>
                    <?= form_close(); ?>
                </div>
            </div>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <?php if (!isset($customer_id)): ?>
                            <!-- Initial State - No Customer Selected -->
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Welcome to Customer Ledger</h3>
                                </div>
                                <div class="box-body">
                                    <div class="empty-state">
                                        <i class="fa fa-users fa-5x"></i>
                                        <h3>No Customer Selected</h3>
                                        <p class="lead">Please select a customer from the dropdown above to view their account ledger.</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Customer Account Overview -->
                            <div class="box customer-account-info">
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="margin: 0;">
                                        <i class="fa fa-user-circle"></i> Customer Account Overview
                                    </h3>
                                    <div class="box-tools pull-right">
                                        <span class="info-badge">
                                            <i class="fa fa-calendar"></i> <?= $start_date; ?> - <?= $end_date; ?>
                                        </span>
                                        <!-- <a href="<?php echo base_url('customers/print_ledger/' . $customer_id . '?start_date=' . $start_date . '&end_date=' . $end_date); ?>"
                                            class="btn btn-default btn-sm" target="_blank" style="margin-left: 10px;">
                                            <i class="fa fa-print"></i> Print
                                        </a> -->
                                    </div>
                                </div>
                                <div class="box-body" style="padding: 0;">
                                    <div class="row">
                                        <!-- Customer Details -->
                                        <div class="col-md-6">
                                            <div class="customer-details">
                                                <h4 style="margin-top: 0; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                                                    Customer Information
                                                </h4>
                                                <div class="customer-info-item">
                                                    <strong>Name:</strong>
                                                    <span style="float: right;"><?= $customer_info->customer_name; ?></span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Mobile:</strong>
                                                    <span style="float: right;"><?= $customer_info->mobile; ?></span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Customer Code:</strong>
                                                    <span style="float: right;"><?= $customer_info->customer_code; ?></span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Address:</strong>
                                                    <div style="margin-top: 5px; text-align: right; font-size: 0.9em;">
                                                        <?= $customer_info->address ?: 'N/A'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Account Summary -->
                                        <div class="col-md-6">
                                            <div class="account-summary-merged">
                                                <h4 style="margin-top: 0; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                                                    Account Summary
                                                </h4>
                                                <div class="summary-item-merged">
                                                    <span>Opening Balance</span>
                                                    <span class="<?= $account_summary['opening_balance'] >= 0 ? 'balance-negative' : 'balance-positive'; ?>">
                                                        ৳ <?= number_format(abs($account_summary['opening_balance']), 2); ?>
                                                        <?= $account_summary['opening_balance'] >= 0 ? 'DR' : 'CR'; ?>
                                                    </span>
                                                </div>
                                                <div class="summary-item-merged">
                                                    <span>Total Sales</span>
                                                    <span class="debit-amount">৳ <?= number_format($account_summary['total_invoice'], 2); ?></span>
                                                </div>
                                                <div class="summary-item-merged">
                                                    <span>Customer Loans</span>
                                                    <span class="debit-amount">৳ <?= number_format($account_summary['total_loan_issued'], 2); ?></span>
                                                </div>
                                                <div class="summary-item-merged">
                                                    <span>Loan Repayments</span>
                                                    <span class="credit-amount">৳ <?= number_format($account_summary['total_loan_repaid'], 2); ?></span>
                                                </div>
                                                <div class="summary-item-merged">
                                                    <span>Total Received</span>
                                                    <span class="credit-amount">৳ <?= number_format($account_summary['total_paid'], 2); ?></span>
                                                </div>
                                                <div class="summary-item-merged">
                                                    <span><strong>Balance Due</strong></span>
                                                    <span class="<?= $account_summary['balance_due'] >= 0 ? 'balance-negative' : 'balance-positive'; ?>" style="font-size: 1.1em;">
                                                        <strong>৳ <?= number_format(abs($account_summary['balance_due']), 2); ?>
                                                            <?= $account_summary['balance_due'] >= 0 ? 'DR' : 'CR'; ?></strong>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ledger Details -->
                            <!-- Replace the entire ledger table section with this updated version -->
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Ledger Details</h3>
                                    <div class="box-tools">
                                        <small>Showing transactions from <?= $start_date; ?> to <?= $end_date; ?></small>
                                    </div>
                                </div>
                                <div class="box-body">
                                    <div class="table-responsive">
                                        <table id="ledger_table" class="table table-bordered table-striped">
                                            <thead>
                                                <tr class="bg-gray">
                                                    <th style="width: 30px;"></th>
                                                    <th>Date</th>
                                                    <th>Reference No</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Debit</th>
                                                    <th>Credit</th>
                                                    <th>Balance</th>
                                                    <th>Payment Method</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!empty($ledger_data)):
                                                    foreach ($ledger_data as $transaction):
                                                        $hasItems = !empty($transaction->items) && $transaction->type == 'Sell';
                                                        $itemsJson = $hasItems ? htmlspecialchars(json_encode($transaction->items), ENT_QUOTES, 'UTF-8') : '';

                                                        // Determine if this is a payment transaction for special handling
                                                        $isPayment = in_array($transaction->type, ['Payment', 'Return Payment']);
                                                ?>
                                                        <tr class="<?= $hasItems ? 'has-items' : ''; ?> <?= $isPayment ? 'payment-transaction' : ''; ?>"
                                                            data-items='<?= $itemsJson; ?>'>
                                                            <td class="details-control">
                                                                <?php if ($hasItems): ?>
                                                                    <i class="fa fa-chevron-right"></i>
                                                                    <span class="item-count-badge"><?= count($transaction->items); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= date('d-m-Y h:i A', strtotime($transaction->date)); ?></td>
                                                            <td>
                                                                <?php if ($transaction->type == 'Sell'): ?>
                                                                    <a href="<?= base_url('sales/invoice/' . $transaction->sales_id); ?>" target="_blank">
                                                                        <?= $transaction->reference_no; ?>
                                                                    </a>
                                                                <?php elseif ($transaction->type == 'Sales Return'): ?>
                                                                    <a href="<?= base_url('sales_return/invoice/' . $transaction->return_id); ?>" target="_blank">
                                                                        <?= $transaction->reference_no; ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <?= $transaction->reference_no; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="label 
                                        <?= $transaction->type == 'Sell' ? 'label-danger' : ''; ?>
                                        <?= $transaction->type == 'Payment' ? 'label-success' : ''; ?>
                                        <?= $transaction->type == 'Sales Return' ? 'label-warning' : ''; ?>
                                        <?= $transaction->type == 'Opening Balance' ? 'label-info' : ''; ?>
                                        <?= $transaction->type == 'Return Payment' ? 'label-primary' : ''; ?>
                                        <?= $transaction->type == 'Customer Loan' ? 'label-primary' : ''; ?>
                                        <?= $transaction->type == 'Loan Repayment' ? 'label-success' : ''; ?>
                                    ">
                                                                    <?= $transaction->type; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($transaction->payment_status == 'Paid'): ?>
                                                                    <span class="label label-success">Paid</span>
                                                                <?php elseif ($transaction->payment_status == 'Pending'): ?>
                                                                    <span class="label label-warning">Pending</span>
                                                                <?php elseif ($isPayment): ?>
                                                                    <span class="label label-success">Completed</span>
                                                                <?php else: ?>
                                                                    <?= $transaction->payment_status ?: '-'; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="debit-amount text-right">
                                                                <?= $transaction->debit > 0 ? '৳ ' . number_format($transaction->debit, 2) : '-'; ?>
                                                            </td>
                                                            <td class="credit-amount text-right">
                                                                <?= $transaction->credit > 0 ? '৳ ' . number_format($transaction->credit, 2) : '-'; ?>
                                                            </td>
                                                            <td class="<?= $transaction->balance >= 0 ? 'balance-negative' : 'balance-positive'; ?> text-right">
                                                                ৳ <?= number_format(abs($transaction->balance), 2); ?>
                                                                <?= $transaction->balance >= 0 ? 'DR' : 'CR'; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($isPayment && $transaction->payment_method): ?>
                                                                    <span class="text-success">
                                                                        <i class="fa fa-money"></i> <?= $transaction->payment_method; ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <?= $transaction->payment_method ?: '-'; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($isPayment): ?>
                                                                    <small class="text-muted">
                                                                        <i class="fa fa-info-circle"></i>
                                                                        <?= $transaction->others ?: 'Payment received'; ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <?= $transaction->others ?: '-'; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php
                                                    endforeach;
                                                else:
                                                    ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center">
                                                            <div class="empty-state" style="padding: 20px;">
                                                                <i class="fa fa-info-circle fa-2x"></i>
                                                                <h4>No transactions found</h4>
                                                                <p>No transactions found for the selected customer and date range.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <?php include "footer.php"; ?>
    </div>

    <?php include "comman/code_js_datatable.php"; ?>
    <script src="<?php echo $theme_link; ?>plugins/datepicker/bootstrap-datepicker.js"></script>
    <script src="<?php echo $theme_link; ?>plugins/select2/select2.full.min.js"></script>

    <script>
        $(document).ready(function() {
            // Function to format child row
            function format(items) {
                if (!items || items.length === 0) return '';

                var html = '<div class="invoice-items-child">' +
                    '<table class="items-table-child table table-condensed table-bordered">' +
                    '<thead>' +
                    '<tr>' +
                    '<th style="width: 40px;">#</th>' +
                    '<th>Product</th>' +
                    '<th style="width: 80px;">Quantity</th>' +
                    '<th style="width: 100px;">Unit Price</th>' +
                    '<th style="width: 100px;">Discount</th>' +
                    '<th style="width: 80px;">Tax</th>' +
                    '<th style="width: 100px;">Subtotal</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';

                items.forEach(function(item, index) {
                    html += '<tr>' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td>' + item.item_name + '</td>' +
                        '<td class="text-center">' + item.sales_qty + '</td>' +
                        '<td class="text-right">৳ ' + parseFloat(item.price_per_unit).toFixed(2) + '</td>' +
                        '<td class="text-right">৳ ' + parseFloat(item.discount_amt).toFixed(2) + '</td>' +
                        '<td class="text-right">৳ ' + parseFloat(item.tax_amt).toFixed(2) + '</td>' +
                        '<td class="text-right"><strong>৳ ' + parseFloat(item.total_cost).toFixed(2) + '</strong></td>' +
                        '</tr>';
                });

                html += '</tbody></table></div>';
                return html;
            }

            // Auto-focus on global customer dropdown
            <?php if (!isset($customer_id)): ?>
                setTimeout(function() {
                    $('#global_customer_id').select2('open');
                }, 500);
            <?php endif; ?>

            // Initialize Select2
            $('#global_customer_id').select2({
                placeholder: "Select customer to view ledger",
                allowClear: false,
                width: '300px'
            });

            // Initialize datepicker
            $('.datepicker').datepicker({
                format: 'dd-mm-yyyy',
                autoclose: true,
                todayHighlight: true
            });

            // Keyboard shortcut for customer switcher (Ctrl+Shift+C)
            $(document).keydown(function(e) {
                if (e.ctrlKey && e.shiftKey && e.which == 67) {
                    e.preventDefault();
                    $('#global_customer_id').select2('open');
                }
            });

            // Initialize DataTable for ledger
            <?php if (isset($customer_id)): ?>
                if ($.fn.DataTable.isDataTable('#ledger_table')) {
                    $('#ledger_table').DataTable().destroy();
                }

                var table = $('#ledger_table').DataTable({
                    /* FOR EXPORT BUTTONS START*/
                    dom: '<"row margin-bottom-12"<"col-sm-12"<"pull-left"l><"pull-right"fr><"pull-right margin-left-10 "B>>>tip',
                    buttons: {
                        buttons: [{
                                className: 'btn bg-red color-palette btn-flat hidden delete_btn pull-left',
                                text: 'Delete',
                                action: function(e, dt, node, config) {
                                    // multi_delete();
                                }
                            },
                            {
                                extend: 'copy',
                                className: 'btn bg-teal color-palette btn-flat',
                                exportOptions: {
                                    columns: [1, 2, 3, 4, 5, 6, 7, 8, 9] // Updated: removed location column
                                }
                            },
                            {
                                extend: 'excel',
                                className: 'btn bg-teal color-palette btn-flat',
                                exportOptions: {
                                    columns: [1, 2, 3, 4, 5, 6, 7, 8, 9] // Updated: removed location column
                                }
                            },
                            {
                                extend: 'pdf',
                                className: 'btn bg-teal color-palette btn-flat',
                                exportOptions: {
                                    columns: [1, 2, 3, 4, 5, 6, 7, 8, 9] // Updated: removed location column
                                }
                            },
                            {
                                extend: 'print',
                                className: 'btn bg-teal color-palette btn-flat',
                                exportOptions: {
                                    columns: [1, 2, 3, 4, 5, 6, 7, 8, 9] // Updated: removed location column
                                }
                            },
                            {
                                extend: 'csv',
                                className: 'btn bg-teal color-palette btn-flat',
                                exportOptions: {
                                    columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                                }
                            },
                            {
                                extend: 'colvis',
                                className: 'btn bg-teal color-palette btn-flat',
                                text: 'Columns'
                            },
                        ]
                    },
                    /* FOR EXPORT BUTTONS END */
                    "ordering": false,
                    "pageLength": 2000,
                    "responsive": false,
                    "columnDefs": [{
                        "orderable": false,
                        "targets": 0
                    }],
                    "language": {
                        "search": "Search transactions:",
                        "lengthMenu": "Show _MENU_ entries"
                    }
                });

                // Add event listener for opening and closing details
                $('#ledger_table tbody').on('click', 'td.details-control', function() {
                    var tr = $(this).closest('tr');
                    var row = table.row(tr);
                    var itemsData = tr.data('items');

                    if (!itemsData) return; // No items to show

                    if (row.child.isShown()) {
                        // This row is already open - close it
                        row.child.hide();
                        tr.removeClass('shown');
                        $(this).find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        $(this).removeClass('open');
                    } else {
                        // Open this row
                        try {
                            var items = typeof itemsData === 'string' ? JSON.parse(itemsData) : itemsData;
                            row.child(format(items)).show();
                            tr.addClass('shown');
                            $(this).find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                            $(this).addClass('open');
                        } catch (e) {
                            console.error('Error parsing items:', e);
                        }
                    }
                });
            <?php endif; ?>

            // Initialize select2
            $('.select2-global').select2({
                placeholder: "Select customer to view ledger",
                allowClear: false
            });

            // Auto-submit when dates change
            $('#start_date, #end_date').on('change', function() {
                var customerId = $('#global_customer_id').val();
                if (customerId) {
                    setTimeout(function() {
                        $('.global-customer-form').submit();
                    }, 300);
                }
            });
        });
    </script>
</body>

</html>