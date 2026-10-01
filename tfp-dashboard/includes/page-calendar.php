<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_calendar_content() {
    $user_id = get_current_user_id();
    $tabs = ['schedule'=>__('My Schedule','tfp-dashboard'),'requests'=>__('My Requests','tfp-dashboard')];
    $active_tab = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? sanitize_key($_GET['tab']) : 'schedule';
    $ym = isset($_GET['ym']) ? sanitize_text_field($_GET['ym']) : current_time('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/',$ym)) $ym=current_time('Y-m');
    $timestamp = strtotime($ym.'-01');
    $prev_ym=date('Y-m',strtotime('-1 month',$timestamp)); $next_ym=date('Y-m',strtotime('+1 month',$timestamp));
    $first=date('Y-m-01',$timestamp); $last=date('Y-m-t',$timestamp);
    $month_title=date_i18n('F Y',$timestamp);
    $days_in_month=(int)date('t',$timestamp); $start=(int)date('w',$timestamp);
    $meetings=tfp_calendar_meetings_for_month($user_id,$ym);
    $off_days=tfp_calendar_off_days($first,$last);
    $next_meetings=tfp_calendar_next_meetings($user_id,3);
    $cohort=tfp_calendar_student_cohort($user_id);
    $requests=tfp_dashboard_user_skip_requests($user_id);
    ?>
    <div class="tfp-cal-header-actions" data-tfp-calendar>
      <div class="tfp-cal-header-left">
        <div class="tfp-dash-pageheader__crumb" style="margin-bottom:8px"><a href="<?php echo esc_url(home_url('/')); ?>">Discipleship</a><span aria-hidden="true">/</span><span class="tfp-dash-pageheader__current">Calendar</span></div>
        <h1 class="tfp-dash-pageheader__title" style="font-size:28px;font-weight:700;color:#111827;margin-bottom:8px">My Calendar</h1>
        <p class="tfp-dash-pageheader__subtitle" style="font-size:14px;color:#151411;margin:0">View your upcoming meetings, off days, and skip requests.</p>
      </div>
      <div class="tfp-cal-skip-action"><a href="#" class="tfp-cal-btn-skip" data-open-skip data-date="" data-cohort="<?php echo esc_attr($cohort['id']??0); ?>"><span aria-hidden="true">+</span> Request a Skip</a></div>

      <div class="tfp-cal-tabs">
        <?php foreach($tabs as $key=>$label): ?>
          <a href="<?php echo esc_url(add_query_arg(['tab'=>$key,'ym'=>$ym])); ?>" class="tfp-cal-tabs__link <?php echo $active_tab===$key?'is-active':''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </div>

      <?php if($active_tab==='schedule'): ?>
      <div class="tfp-cal-layout">
        <div class="tfp-cal-grid-area">
          <div class="tfp-cal-controls">
            <a href="<?php echo esc_url(add_query_arg(['tab'=>'schedule','ym'=>$prev_ym])); ?>" class="tfp-cal-btn-nav">← Previous</a>
            <h2 class="tfp-cal-month-title"><?php echo esc_html($month_title); ?></h2>
            <a href="<?php echo esc_url(add_query_arg(['tab'=>'schedule','ym'=>$next_ym])); ?>" class="tfp-cal-btn-nav">Next →</a>
          </div>
          <div class="tfp-cal-weekdays"><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) echo '<div class="tfp-cal-weekday">'.esc_html($d).'</div>'; ?></div>
          <div class="tfp-cal-grid">
            <?php for($i=0;$i<$start;$i++): ?><div class="tfp-cal-day is-inactive"></div><?php endfor; ?>
            <?php for($day=1;$day<=$days_in_month;$day++):
              $date=sprintf('%s-%02d',$ym,$day); $day_meetings=$meetings[$date]??[]; $off=$off_days[$date]??null;
              $classes='tfp-cal-day'.($off?' is-selected':'');
              if($date===current_time('Y-m-d'))$classes.=' is-today';
            ?>
              <div class="<?php echo esc_attr($classes); ?>" data-calendar-date="<?php echo esc_attr($date); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr($date); ?>">
                <span class="tfp-cal-day-num"><?php echo esc_html($day); ?></span>
                <?php if($off): ?><span class="tfp-cal-event-pill tfp-cal-event-pill--holiday"><?php echo esc_html($off['name']); ?></span><?php endif; ?>
                <?php foreach(array_slice($day_meetings,0,2) as $meeting): ?><span class="tfp-cal-event-pill tfp-cal-event-pill--meeting"><?php echo esc_html($meeting['cohort']); ?></span><?php endforeach; ?>
                <?php if(count($day_meetings)>2): ?><span class="tfp-cal-event-pill tfp-cal-event-pill--meeting">+<?php echo count($day_meetings)-2; ?> more</span><?php endif; ?>
              </div>
            <?php endfor; ?>
            <?php $total=$start+$days_in_month; $remaining=(7-($total%7))%7; for($i=0;$i<$remaining;$i++): ?><div class="tfp-cal-day is-inactive"></div><?php endfor; ?>
          </div>
        </div>
        <aside class="tfp-cal-sidebar">
          <div>
            <h3 class="tfp-cal-sidebar-section__title">Select a day</h3>
            <p class="tfp-cal-sidebar-section__desc">Click any date to see meetings and details.</p>
            <div class="tfp-cal-sidebar-empty-state" data-calendar-detail>
              <p>Click a date on the calendar to see meetings and details.</p>
            </div>
          </div>
          <div>
            <h3 class="tfp-cal-sidebar-section__title" style="margin-bottom:16px">Upcoming Off Days</h3>
            <div class="tfp-cal-sidebar-list">
              <?php $future_off=get_posts(['post_type'=>'tfp_off_day','post_status'=>'publish','posts_per_page'=>3,'meta_key'=>'_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_date','value'=>current_time('Y-m-d'),'compare'=>'>=','type'=>'DATE']]]); ?>
              <?php if($future_off): foreach($future_off as $p): $od=get_post_meta($p->ID,'_date',true); ?>
                <div class="tfp-cal-sidebar-item"><span class="tfp-cal-sidebar-item-name"><?php echo esc_html(get_the_title($p->ID)); ?></span><span class="tfp-cal-sidebar-item-date"><?php echo esc_html(date_i18n('M j, Y',strtotime($od))); ?></span></div>
              <?php endforeach; else: ?><div class="tfp-cal-sidebar-empty-state"><p>No upcoming off days.</p></div><?php endif; ?>
            </div>
          </div>
          <div>
            <h3 class="tfp-cal-sidebar-section__title" style="margin-bottom:16px">Next Meetings</h3>
            <div class="tfp-cal-sidebar-list tfp-cal-sidebar-list--meetings">
              <?php if($next_meetings): foreach($next_meetings as $m): ?>
                <?php $short_cohort = function_exists('mb_substr') ? mb_substr((string) $m['cohort'], 0, 5) : substr((string) $m['cohort'], 0, 5); ?>
                <button type="button" class="tfp-cal-sidebar-item tfp-cal-sidebar-item--meeting" data-calendar-date="<?php echo esc_attr($m['date']); ?>"><span class="tfp-cal-sidebar-item-name"><?php echo esc_html($short_cohort); ?>...</span><span class="tfp-cal-sidebar-item-date"><?php echo esc_html(date_i18n('M j',strtotime($m['date']))); ?> · <?php echo esc_html($m['start_label']); ?></span></button>
              <?php endforeach; else: ?><div class="tfp-cal-sidebar-empty-state"><p>No upcoming meetings.</p></div><?php endif; ?>
            </div>
          </div>
        </aside>
      </div>
      <?php else: ?>
        <div class="tfp-docs-panel">
          <h2 class="tfp-docs-panel__title">My Skip Requests</h2>
          <?php if($requests): ?><div class="tfp-exp-table-wrap"><table class="tfp-exp-table"><thead><tr><th>Date</th><th>Reason</th><th>Status</th></tr></thead><tbody>
          <?php foreach($requests as $request): ?><tr><td><?php echo esc_html(date_i18n('M j, Y',strtotime($request['date']))); ?></td><td><?php echo esc_html($request['reason']); ?></td><td><span class="tfp-exp-badge"><?php echo esc_html(ucfirst($request['status'])); ?></span></td></tr><?php endforeach; ?>
          </tbody></table></div><?php else: ?><div class="tfp-exp-empty">You have not submitted any skip requests.</div><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="tfp-exp-modal" data-skip-modal>
        <div class="tfp-exp-modal__box">
          <div class="tfp-exp-modal__head"><h3>Request a Skip</h3><button type="button" class="tfp-exp-modal__close" data-close-modal aria-label="Close">×</button></div>
          <form>
            <label>Date<input type="date" name="skip_date" required></label>
            <input type="hidden" name="skip_cohort" value="<?php echo esc_attr($cohort['id']??0); ?>">
            <label>Reason<select name="reason" required><option value="">Select a reason</option><option value="Travel">Travel</option><option value="Personal">Personal</option><option value="Work">Work</option><option value="Illness">Illness</option><option value="Other">Other</option></select></label>
            <label>Additional notes<textarea name="notes" rows="4"></textarea></label>
            <div class="tfp-exp-modal__actions"><button type="button" class="tfp-dash-btn" data-close-modal>Cancel</button><button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">Submit Request</button></div>
            <div data-form-status aria-live="polite"></div>
          </form>
        </div>
      </div>
    </div>
    <?php
}
