<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_documents_content() {
    $user_id=get_current_user_id(); $current_user=wp_get_current_user();
    $first_name=$current_user->first_name?:$current_user->display_name;
    $tabs=['agreements'=>'Agreements','certificates'=>'Certificates','receipts'=>'Receipts'];
    $active_tab=isset($_GET['tab'])&&isset($tabs[$_GET['tab']])?sanitize_key($_GET['tab']):'agreements';
    $rows=[];
    if($active_tab==='agreements') $rows=tfp_dashboard_user_documents($user_id,'Agreement');
    elseif($active_tab==='certificates') $rows=tfp_dashboard_user_certificates($user_id);
    elseif($active_tab==='receipts') $rows=tfp_dashboard_user_receipts($user_id);
    ?>
    <div class="tfp-dash-pageheader" data-tfp-documents>
      <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open dashboard menu', 'tfp-dashboard'); ?>">
        <?php echo tfp_dashboard_icon('toggle'); ?>
      </button>
      <div class="tfp-dash-pageheader__crumb"><a href="<?php echo esc_url(home_url('/')); ?>">Discipleship</a><span>/</span><span class="tfp-dash-pageheader__current">Documents</span></div>
      <h1 class="tfp-dash-pageheader__title"><?php echo esc_html($first_name); ?>, My Documents</h1>
      <p class="tfp-dash-pageheader__subtitle">Access your agreements, contracts, class materials, and certificates in one place.</p>

    </div>
    <div class="tfp-docs-tabs" style="margin-top:24px">
        <?php foreach($tabs as $key=>$label): ?><a href="<?php echo esc_url(add_query_arg('tab',$key)); ?>" class="tfp-docs-tabs__link <?php echo $active_tab===$key?'is-active':''; ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
      </div>

      <div class="tfp-docs-panel">
        <h2 class="tfp-docs-panel__title"><?php echo esc_html($tabs[$active_tab]); ?></h2>
        <?php if($rows): ?>
        <div class="tfp-exp-table-wrap"><table class="tfp-exp-table">
          <thead><tr><th><?php echo $active_tab==='receipts'?'Receipt':('Document'); ?></th><th>Type</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($rows as $row): ?>
            <tr>
              <td><strong><?php echo esc_html($row['title']); ?></strong><?php if(isset($row['amount'])): ?><div class="tfp-exp-muted"><?php echo wp_kses_post($row['amount']); ?></div><?php endif; ?></td>
              <td><?php echo esc_html($row['type']); ?></td>
              <td><?php echo esc_html($row['date']?date_i18n(get_option('date_format'),strtotime($row['date'])):'—'); ?></td>
              <td><span class="tfp-exp-badge"><?php echo esc_html(ucwords(str_replace('_',' ',$row['status']))); ?></span></td>
              <td><div class="tfp-exp-actions">
                <?php if(!empty($row['url'])): ?><a class="tfp-exp-btn" href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener"><?php echo $active_tab==='receipts'?'View Receipt':'View / Download'; ?></a><?php endif; ?>
                <?php if($active_tab==='agreements' && in_array($row['status'],['pending','active'],true)): ?><button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-sign-document="<?php echo esc_attr($row['id']); ?>" data-title="<?php echo esc_attr($row['title']); ?>">Sign</button><?php elseif($active_tab==='agreements' && $row['status']==='signed'): ?><span class="tfp-exp-muted">Signed <?php echo !empty($row['signed_at'])?esc_html(date_i18n(get_option('date_format'),strtotime($row['signed_at']))):''; ?></span><?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php else: ?><div class="tfp-exp-empty">No <?php echo esc_html(strtolower($tabs[$active_tab])); ?> available yet.</div><?php endif; ?>
      </div>

      <div class="tfp-exp-modal" data-document-modal>
        <div class="tfp-exp-modal__box">
          <div class="tfp-exp-modal__head"><h3>Sign document</h3><button type="button" class="tfp-exp-modal__close" data-close-modal aria-label="Close">×</button></div>
          <p>By typing your full name below, you confirm that you are signing <strong data-doc-title></strong>.</p>
          <form data-sign-form>
            <input type="hidden" name="document_id">
            <label>Signature<input type="text" name="signature" autocomplete="name" placeholder="Your full name" required></label>
            <div class="tfp-exp-modal__actions"><button type="button" class="tfp-dash-btn" data-close-modal>Cancel</button><button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">Sign Document</button></div>
            <div data-form-status aria-live="polite"></div>
          </form>
        </div>
      </div>
    <?php
}
