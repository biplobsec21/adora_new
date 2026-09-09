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
        $postcode = $address = $gstin = $tax_number =
        $state_code = $customer_code = $customer_number = $opening_balance = '';
    }
    ?>


    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->
      <section class="content-header">
        <h1>
          <?= $page_title; ?>
          <small>Add/Update Customer</small>
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
          <div class="col-md-12">
            <!-- Horizontal Form -->
            <div class="box box-info ">

              <!-- form start -->
              <form class="form-horizontal" id="customers-form">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <input type="hidden" id="base_url" value="<?php echo html_escape($base_url); ?>">
                <div class="box-body">
                  <div class="row">
                    <div class="col-md-5">
                      <div class="form-group">
                        <label for="customer_name" class="col-sm-4 control-label"><?= $this->lang->line('customer_name'); ?><label class="text-danger">*</label></label>

                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="" value="<?php echo html_escape($customer_name); ?>">
                          <span id="customer_name_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>



                      <div class="form-group">
                        <label for="customer_number" class="col-sm-4 control-label">Customer Number</label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="customer_number" name="customer_number" placeholder="N/A" value="<?php echo html_escape($customer_number); ?>">
                          <span id="customer_number_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>

                      <div class="form-group">
                        <label for="mobile" class="col-sm-4 control-label"><?= $this->lang->line('mobile'); ?></label>

                        <div class="col-sm-8">
                          <input type="text" class="form-control no_special_char_no_space" id="mobile" name="mobile" placeholder="" value="<?php echo html_escape($mobile); ?>">
                          <span id="mobile_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>


                      <div class="form-group">
                        <label for="email" class="col-sm-4 control-label"><?= $this->lang->line('email'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="email" name="email" placeholder="" value="<?php echo html_escape($email); ?>">
                          <span id="email_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="phone" class="col-sm-4 control-label"><?= $this->lang->line('phone'); ?></label>

                        <div class="col-sm-8">
                          <input type="text" class="form-control no_special_char_no_space" id="phone" name="phone" placeholder="" value="<?php echo html_escape($phone); ?>">
                          <span id="phone_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="gstin" class="col-sm-4 control-label"><?= $this->lang->line('gst_number'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="gstin" name="gstin" placeholder="" value="<?php echo html_escape($gstin); ?>">
                          <span id="gstin_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="tax_number" class="col-sm-4 control-label"><?= $this->lang->line('tax_number'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="tax_number" name="tax_number" placeholder="" value="<?php echo html_escape($tax_number); ?>">
                          <span id="tax_number_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <!-- ########### -->
                    </div>


                    <div class="col-md-5">


                      <div class="form-group">
                        <label for="opening_balance" class="col-sm-4 control-label"><?= $this->lang->line('previous_due'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="opening_balance" name="opening_balance" placeholder="" value="<?php echo html_escape($opening_balance); ?>">
                          <span id="opening_balance_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="country" class="col-sm-4 control-label"><?= $this->lang->line('country'); ?></label>

                        <div class="col-sm-8">
                          <select class="form-control select2" id="country" name="country" style="width: 100%;">
                            <?php
                            $query1 = "select * from db_country where status=1";
                            $q1 = $this->db->query($query1);
                            if ($q1->num_rows() > 0) {
                              foreach ($q1->result() as $res1) {
                                $selected = ($country_id == $res1->id) ? 'selected' : '';
                                echo "<option $selected value='" . html_escape($res1->id) . "'>" . html_escape($res1->country) . "</option>";
                              }
                            } else {
                            ?>
                              <option value="">No Records Found</option>
                            <?php
                            }
                            ?>
                          </select>
                          <span id="country_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="state" class="col-sm-4 control-label"><?= $this->lang->line('state'); ?></label>

                        <div class="col-sm-8">
                          <select class="form-control select2" id="state" name="state" style="width: 100%;">
                            <?php
                            $query2 = "select * from db_states where status=1";
                            $q2 = $this->db->query($query2);
                            if ($q2->num_rows() > 0) {
                              echo '<option value="">-Select-</option>';
                              foreach ($q2->result() as $res1) {
                                $selected = ($state_id == $res1->id) ? 'selected' : '';
                                echo "<option $selected value='" . html_escape($res1->id) . "'>" . html_escape($res1->state) . "</option>";
                              }
                            } else {
                            ?>
                              <option value="">No Records Found</option>
                            <?php
                            }
                            ?>
                          </select>
                          <span id="state_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="city" class="col-sm-4 control-label"><?= $this->lang->line('city'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control" id="city" name="city" placeholder="" value="<?php echo html_escape($city); ?>">
                          <span id="city_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="postcode" class="col-sm-4 control-label"><?= $this->lang->line('postcode'); ?></label>
                        <div class="col-sm-8">
                          <input type="text" class="form-control no_special_char_no_space" id="postcode" name="postcode" placeholder="" value="<?php echo html_escape($postcode); ?>">
                          <span id="postcode_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="address" class="col-sm-4 control-label"><?= $this->lang->line('address'); ?></label>
                        <div class="col-sm-8">
                          <textarea type="text" class="form-control" id="address" name="address" placeholder=""><?php echo html_escape($address); ?></textarea>
                          <span id="address_msg" style="display:none" class="text-danger"></span>
                        </div>
                      </div>

                    </div>
                    <!-- ########### -->
                  </div>



                </div>
                <!-- /.box-body -->

                <div class="box-footer">
                  <div class="col-sm-8 col-sm-offset-2 text-center">
                    <!-- <div class="col-sm-4"></div> -->
                    <?php
                    if ($customer_name != "") {
                      $btn_name = "Update";
                      $btn_id = "update";
                    ?>
                      <input type="hidden" name="q_id" id="q_id" value="<?php echo html_escape($q_id); ?>" />
                    <?php
                    } else {
                      $btn_name = "Save";
                      $btn_id = "save";
                    }

                    ?>
                    <div class="col-md-3 col-md-offset-3">
                      <button type="button" id="<?php echo $btn_id; ?>" class=" btn btn-block btn-success" title="Save Data"><?php echo $btn_name; ?></button>
                    </div>
                    <div class="col-sm-3">
                      <button type="button" class="col-sm-3 btn btn-block btn-warning close_btn" title="Go Dashboard">Close</button>
                    </div>
                  </div>
                </div>
                <!-- /.box-footer -->
              </form>
            </div>
            <!-- /.box -->

          </div>
          <!--/.col (right) -->
          <div class="col-md-12">

            <div class="box">
              <div class="box-header">
                <h3 class="box-title text-blue"><?= $this->lang->line('opening_balance_payments'); ?></h3>
              </div>
              <!-- /.box-header -->
              <div class="box-body table-responsive no-padding">

                <table class="table table-bordered table-hover " id="report-data">
                  <thead>
                    <tr class="bg-gray">
                      <th style="">#</th>
                      <th style=""><?= $this->lang->line('payment_date'); ?></th>
                      <th style=""><?= $this->lang->line('payment'); ?></th>
                      <th style=""><?= $this->lang->line('payment_type'); ?></th>
                      <th style=""><?= $this->lang->line('payment_note'); ?></th>
                      <th style=""><?= $this->lang->line('action'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    if (isset($q_id)) {
                      $q3 = $this->db->query("select * from db_cobpayments where customer_id=$q_id");
                      if ($q3->num_rows() > 0) {
                        $i = 1;
                        $total_paid = 0;
                        foreach ($q3->result() as $res3) {
                          $total_paid += $res3->payment;
                          echo "<tr>";
                          echo "<td>" . $i . "</td>";
                          echo "<td>" . html_escape(show_date($res3->payment_date)) . "</td>";
                          echo "<td class='text-right'>" . html_escape($CI->currency($res3->payment)) . "</td>";
                          echo "<td>" . html_escape($res3->payment_type) . "</td>";
                          echo "<td>" . html_escape($res3->payment_note) . "</td>";
                          echo '<td><i class="fa fa-trash text-red pointer" onclick="delete_opening_balance_entry(' . (int)$res3->id . ')"> Delete</i></td>';
                          echo "</tr>";
                          $i++;
                        }
                        echo "<tr class='text-bold'>
                                            <td colspan=2 class='text-right '>Total</td>
                                            <td class='text-right'>" . html_escape($CI->currency($total_paid)) . "</td>
                                            <td colspan=3></td>
                                          </tr>";
                      } else {
                        echo "<tr><td colspan='6' class='text-center text-bold'>No Opening Balance Payments Found</td></tr>";
                      }
                    } else {
                      echo "<tr><td colspan='6' class='text-center text-bold'>No Opening Balance Payments Found</td></tr>";
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

  <script src="<?php echo $theme_link; ?>js/customers.js"></script>
  <!-- Make sidebar menu hughlighter/selector -->
  <script>
    $(".<?php echo basename(__FILE__, '.php'); ?>-active-li").addClass("active");
  </script>
</body>

</html>