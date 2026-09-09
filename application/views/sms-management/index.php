<!DOCTYPE html>
<html>

<head><?php include APPPATH . 'views/comman/code_css_datatable.php'; ?></head>

<body class="hold-transition skin-blue sidebar-mini">
    <div class="wrapper">
        <?php include APPPATH . 'views/sidebar.php'; ?><div class="content-wrapper">
            <section class="content-header">
                <h1>SMS Management <small>Provider, templates and triggers</small></h1>
            </section>
            <section class="content"><?php include APPPATH . 'views/comman/code_flashdata.php'; ?>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">SMS Configuration</h3>
                        <div class="box-tools"><a class="btn btn-default btn-sm" href="<?= $base_url; ?>sms_management/logs"><i class="fa fa-list"></i> View SMS Logs</a></div>
                    </div>
                    <form method="post" action="<?= $base_url; ?>sms_management/save_settings"><input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <div class="box-body">
                            <div class="form-group"><label>Provider URL</label><input class="form-control" name="provider_url" value="<?= html_escape($settings->provider_url); ?>"></div>
                            <div class="form-group"><label>Many-to-Many Provider URL</label><input class="form-control" name="many_provider_url" value="<?= html_escape($settings->many_provider_url); ?>"></div>
                            <div class="form-group"><label>Balance URL</label><input class="form-control" name="balance_url" value="<?= html_escape($settings->balance_url); ?>"></div>
                            <div class="form-group"><label>API Key</label><input type="password" class="form-control" name="api_key" value="<?= html_escape($settings->api_key); ?>"></div>
                            <div class="form-group"><label>Sender ID</label><input class="form-control" name="sender_id" value="<?= html_escape($settings->sender_id); ?>"></div><label><input type="checkbox" name="enabled" value="1" <?= $settings->enabled ? 'checked' : ''; ?>> Enable SMS</label><br><label><input type="checkbox" name="due_generation_enabled" value="1" <?= $settings->due_generation_enabled ? 'checked' : ''; ?>> Send after Due Generation</label><br><label><input type="checkbox" name="cost_cutting_enabled" value="1" <?= $settings->cost_cutting_enabled ? 'checked' : ''; ?>> Send after Cost Cutting</label>
                        </div>
                        <div class="box-footer"><button class="btn btn-primary"><i class="fa fa-save"></i> Save Configuration</button></div>
                    </form>
                </div>
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">SMS Templates</h3>
                    </div>
                    <div class="box-body"><?php foreach ($templates as $template) { ?><form method="post" action="<?= $base_url; ?>sms_management/save_template/<?= (int) $template->id; ?>" style="border-bottom:1px solid #eee;padding:12px 0"><input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>"><strong><?= html_escape($template->template_name); ?></strong><textarea name="message_body" class="form-control" rows="3"><?= html_escape($template->message_body); ?></textarea>
                                <p class="help-block">Placeholders: {due_month} (example: Sep 2026), {customer_name}, {total_due}, {new_due}, {cut_amount}, {remaining_due}, {ledger_url}, {site_name}</p><label><input type="checkbox" name="enabled" value="1" <?= $template->enabled ? 'checked' : ''; ?>> Enabled</label> <button class="btn btn-xs btn-primary">Save Template</button>
                            </form><?php } ?></div>
                </div>
            </section>
        </div><?php include APPPATH . 'views/footer.php'; ?></div><?php include APPPATH . 'views/comman/code_js_datatable.php'; ?>
</body>

</html>