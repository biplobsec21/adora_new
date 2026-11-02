<!DOCTYPE html>
<html>

<head>
    <!-- TABLES CSS CODE -->
    <?php include "comman/code_css_form.php"; ?>
    <!-- </copy> -->
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">

        <?php include "sidebar.php"; ?>

        <?php

        if (!isset($customer_name)) {
            $customer_name = $mobile = $phone = $email = $country_id = $state_id = $city =
                $postcode = $address = $supplier_code = $gstin = $tax_number =
                $state_code = $customer_code = $company_name = $company_mobile = $opening_balance = '';
        }
        ?>


        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <h1>
                    <?= $page_title; ?>
                    <small>Customer Payments</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo $base_url; ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo $base_url; ?>customers"><?= $this->lang->line('customers_list'); ?></a></li>
                    <li class="active"><?= $page_title; ?></li>
                </ol>
            </section>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <!-- ********** ALERT MESSAGE START******* -->
                    <?php include "comman/code_flashdata.php"; ?>
                    <!-- ********** ALERT MESSAGE END******* -->
                    <!-- right column -->

                    <!--/.col (right) -->


                    <div class="col-md-12">

                        <div class="box">
                            <div class="box-header">
                                <h3 class="box-title text-blue">Customer Payments</h3>
                            </div>
                            <!-- /.box-header -->
                            <div class="box-body table-responsive no-padding">
                                <input type="hidden" id="base_url" value="<?= html_escape($base_url); ?>">

                                <table class="table table-bordered table-hover " id="report-data">
                                    <thead>
                                        <tr class="bg-gray">
                                            <th style="">#</th>
                                            <th style=""><?= $this->lang->line('payment_date'); ?></th>
                                            <th style=""><?= $this->lang->line('payment'); ?></th>
                                            <th style=""><?= $this->lang->line('payment_type'); ?></th>
                                            <th style=""><?= $this->lang->line('payment_note'); ?></th>
                                            <th>Applied to</th>
                                            <?php if ($CI->permissions('sales_payment_delete')) { ?>
                                                <th style=""><?= $this->lang->line('action'); ?></th>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (isset($q_id)) {
                                            $summary_rows = $this->db
                                                ->select('id, payment_date, payment_type, payment, payment_note, created_date, created_time')
                                                ->from('db_customer_payments')
                                                ->where('customer_id', (int) $q_id)
                                                ->where('status', 1)
                                                ->where('payment >', 0)
                                                ->order_by('payment_date', 'DESC')
                                                ->order_by('id', 'DESC')
                                                ->get()
                                                ->result();
                                            $payment_rows = $this->db
                                                ->select('payment.id, payment.payment_date, payment.payment_type, payment.payment, payment.payment_note, payment.created_date, payment.created_time, sale.sales_code')
                                                ->from('db_salespayments AS payment')
                                                ->join('db_sales AS sale', 'sale.id = payment.sales_id')
                                                ->where('sale.customer_id', (int) $q_id)
                                                ->where('payment.status', 1)
                                                ->where('payment.payment >', 0)
                                                ->order_by('payment.id', 'ASC')
                                                ->get()
                                                ->result();
                                            $payments_by_group = array();
                                            foreach ($payment_rows as $payment_row) {
                                                $group_key = serialize(array($payment_row->payment_date, $payment_row->payment_type, $payment_row->created_date, $payment_row->created_time));
                                                $payments_by_group[$group_key][] = $payment_row;
                                            }

                                            $rows = array();
                                            foreach ($summary_rows as $summary_row) {
                                                $group_key = serialize(array($summary_row->payment_date, $summary_row->payment_type, $summary_row->created_date, $summary_row->created_time));
                                                if (!isset($rows[$group_key])) {
                                                    $rows[$group_key] = array(
                                                        'date' => $summary_row->payment_date,
                                                        'type' => $summary_row->payment_type,
                                                        'note' => $summary_row->payment_note,
                                                        'amount' => 0,
                                                        'summary_id' => (int) $summary_row->id,
                                                        'allocations' => $payments_by_group[$group_key] ?? array(),
                                                        'opening' => false,
                                                    );
                                                }
                                                $rows[$group_key]['amount'] += (float) $summary_row->payment;
                                            }
                                            $opening_rows = $this->db->where('customer_id', (int) $q_id)
                                                ->where('status', 1)
                                                ->where('payment >', 0)
                                                ->order_by('payment_date', 'DESC')
                                                ->order_by('id', 'DESC')
                                                ->get('db_cobpayments')
                                                ->result();
                                            foreach ($opening_rows as $opening) {
                                                $rows['opening-' . (int) $opening->id] = array(
                                                    'date' => $opening->payment_date,
                                                    'type' => $opening->payment_type,
                                                    'note' => $opening->payment_note,
                                                    'amount' => (float) $opening->payment,
                                                    'opening_id' => (int) $opening->id,
                                                    'allocations' => array(),
                                                    'opening' => true,
                                                );
                                            }
                                            uasort($rows, function ($left, $right) {
                                                $date_comparison = strcmp((string) $right['date'], (string) $left['date']);
                                                return $date_comparison !== 0 ? $date_comparison : (($right['summary_id'] ?? $right['opening_id'] ?? 0) <=> ($left['summary_id'] ?? $left['opening_id'] ?? 0));
                                            });

                                            if (count($rows) > 0) {
                                                $i = 1;
                                                $total_paid = 0;
                                                foreach ($rows as $row_key => $payment_row) {
                                                    $total_paid += $payment_row['amount'];
                                                    echo '<tr>';
                                                    echo '<td>' . $i . '</td>';
                                                    echo '<td>' . html_escape(show_date($payment_row['date'])) . '</td>';
                                                    echo "<td class='text-right'>" . $CI->currency($payment_row['amount']) . '</td>';
                                                    echo '<td>' . html_escape($payment_row['type']) . '</td>';
                                                    echo '<td>' . html_escape($payment_row['note']) . '</td>';
                                                    if ($payment_row['opening']) {
                                                        echo '<td>Opening balance</td>';
                                                    } elseif (count($payment_row['allocations']) > 0) {
                                                        echo '<td><button type="button" class="btn btn-info btn-xs" onclick="toggle_customer_payment_details('
                                                            . (int) $payment_row['summary_id'] . ', this)">Show applied (' . count($payment_row['allocations']) . ')</button></td>';
                                                    } else {
                                                        echo '<td><span class="text-muted">No active allocations</span></td>';
                                                    }
                                                    if ($CI->permissions('sales_payment_delete')) {
                                                        if ($payment_row['opening']) {
                                                            echo '<td><button type="button" class="btn btn-danger btn-xs" onclick="delete_opening_balance_entry('
                                                                . (int) $payment_row['opening_id'] . ', ' . (int) $q_id
                                                                . ')"><i class="fa fa-trash"></i> Delete</button></td>';
                                                        } elseif (count($payment_row['allocations']) > 0) {
                                                            echo '<td><button type="button" class="btn btn-danger btn-xs" onclick="delete_customer_sales_payment_group('
                                                                . (int) $payment_row['summary_id'] . ', ' . (int) $q_id . ', '
                                                                . count($payment_row['allocations']) . ')"><i class="fa fa-trash"></i> Delete group</button></td>';
                                                        } else {
                                                            echo '<td></td>';
                                                        }
                                                    }
                                                    echo '</tr>';

                                                    foreach ($payment_row['allocations'] as $allocation) {
                                                        echo '<tr class="customer-payment-detail customer-payment-detail-' . (int) $payment_row['summary_id'] . '" style="display:none;">';
                                                        echo '<td></td><td>' . html_escape(show_date($allocation->payment_date)) . '</td>';
                                                        echo "<td class='text-right'>" . $CI->currency($allocation->payment) . '</td>';
                                                        echo '<td>' . html_escape($allocation->payment_type) . '</td>';
                                                        $allocation_note = $allocation->payment_note ?: 'Payment for ' . $allocation->sales_code;
                                                        echo '<td>' . html_escape($allocation_note) . '</td><td>' . html_escape($allocation->sales_code) . '</td>';
                                                        if ($CI->permissions('sales_payment_delete')) {
                                                            echo '<td><button type="button" class="btn btn-danger btn-xs" onclick="delete_customer_sales_payment('
                                                                . (int) $allocation->id . ', ' . (int) $q_id
                                                                . ')"><i class="fa fa-trash"></i> Delete</button></td>';
                                                        }
                                                        echo '</tr>';
                                                    }
                                                    $i++;
                                                }
                                                echo "<tr class='text-bold'><td colspan=2 class='text-right'>Total</td>";
                                                echo "<td class='text-right'>" . $CI->currency($total_paid) . "</td><td colspan='" . ($CI->permissions('sales_payment_delete') ? 4 : 3) . "'></td></tr>";
                                            } else {
                                                echo "<tr><td colspan='" . ($CI->permissions('sales_payment_delete') ? 7 : 6) . "' class='text-center text-bold'>No Previous Payments Found!!</td></tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='" . ($CI->permissions('sales_payment_delete') ? 7 : 6) . "' class='text-center text-bold'>No Previous Payments Found!!</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>


                            </div>
                            <!-- /.box-body -->
                        </div>
                        <!-- /.box -->
                    </div>
                </div>
                <!-- /.row -->

            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

        <?php include "footer.php"; ?>


        <!-- Add the sidebar's background. This div must be placed
       immediately after the control sidebar -->
        <div class="control-sidebar-bg"></div>
    </div>
    <!-- ./wrapper -->

    <!-- SOUND CODE -->
    <?php include "comman/code_js_sound.php"; ?>
    <!-- TABLES CODE -->
    <?php include "comman/code_js_form.php"; ?>

    <script src="<?php echo $theme_link; ?>js/customers.js?v=<?= filemtime(FCPATH . 'theme/js/customers.js'); ?>"></script>
    <!-- Make sidebar menu hughlighter/selector -->
    <script>
        $(".<?php echo basename(__FILE__, '.php'); ?>-active-li").addClass("active");
    </script>
</body>

</html>