<?php
if (!defined('ABSPATH'))
    exit;

function tfp_dashboard_render_calendar_content()
{
    // Define tabs
    $tabs = [
        'schedule' => __('My Schedule', 'tfp-dashboard'),
        'requests' => __('My Requests', 'tfp-dashboard'),
    ];

    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_text_field($_GET['tab']) : 'schedule';

    // Calendar logic
    $ym = isset($_GET['ym']) ? sanitize_text_field($_GET['ym']) : date('Y-m');
    $timestamp = strtotime($ym . '-01');
    if ($timestamp === false) {
        $ym = date('Y-m');
        $timestamp = strtotime($ym . '-01');
    }

    // Previous and Next month URLs
    $prev_ym = date('Y-m', strtotime('-1 month', $timestamp));
    $next_ym = date('Y-m', strtotime('+1 month', $timestamp));

    $prev_url = add_query_arg('ym', $prev_ym);
    $next_url = add_query_arg('ym', $next_ym);

    // Month details
    $month_title = date('F Y', $timestamp);
    $days_in_month = date('t', $timestamp);
    $str = date('w', mktime(0, 0, 0, date('m', $timestamp), 1, date('Y', $timestamp))); // Day of the week for 1st day (0=Sun, 6=Sat)

    // For previous month trailing days
    $prev_timestamp = strtotime('-1 month', $timestamp);
    $prev_days_in_month = date('t', $prev_timestamp);

    // We can keep the same mock events for demonstration, mapped to the 2nd, 9th, 16th, 23rd, 30th etc.
    $meetings = [2, 9, 16, 23, 30];
    $holidays = [19];
    ?>
    <div class="tfp-cal-header-actions">
        <div class="tfp-cal-header-left">
            <div class="tfp-dash-pageheader__crumb" style="margin-bottom: 8px;">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Discipleship', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <span class="tfp-dash-pageheader__current"><?php esc_html_e('Calendar', 'tfp-dashboard'); ?></span>
            </div>
            <h1 class="tfp-dash-pageheader__title" style="font-size: 28px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                <?php esc_html_e('My Calendar', 'tfp-dashboard'); ?>
            </h1>
            <p class="tfp-dash-pageheader__subtitle" style="font-size: 14px; color: #4B5563; margin: 0;">
                <?php esc_html_e('View your upcoming meetings, off days, and skip requests.', 'tfp-dashboard'); ?>
            </p>
        </div>
        <div>
            <a href="#" class="tfp-cal-btn-skip">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 3.33334V12.6667M3.33334 8H12.6667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php esc_html_e('Request a Skip', 'tfp-dashboard'); ?>
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tfp-cal-tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?php echo esc_attr($key); ?>" class="tfp-cal-tabs__link <?php echo $active_tab === $key ? 'is-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($active_tab === 'schedule'): ?>
        <div class="tfp-cal-layout">
            <!-- Left: Calendar Grid -->
            <div class="tfp-cal-grid-area">
                <div class="tfp-cal-controls">
                    <a href="<?php echo esc_url($prev_url); ?>" class="tfp-cal-btn-nav">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 12.6667L5.33333 8.00001L10 3.33334" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                    </a>
                    <h2 class="tfp-cal-month-title"><?php echo esc_html($month_title); ?></h2>
                    <a href="<?php echo esc_url($next_url); ?>" class="tfp-cal-btn-nav">
                        <?php esc_html_e('Next', 'tfp-dashboard'); ?>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 12.6667L10.6667 8.00001L6 3.33334" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>

                <div class="tfp-cal-weekdays">
                    <div class="tfp-cal-weekday">Sun</div>
                    <div class="tfp-cal-weekday">Mon</div>
                    <div class="tfp-cal-weekday">Tue</div>
                    <div class="tfp-cal-weekday">Wed</div>
                    <div class="tfp-cal-weekday">Thu</div>
                    <div class="tfp-cal-weekday">Fri</div>
                    <div class="tfp-cal-weekday">Sat</div>
                </div>

                <div class="tfp-cal-grid">
                    <?php
                    // Display previous month trailing days
                    if ($str > 0) {
                        $start_prev_day = $prev_days_in_month - $str + 1;
                        for ($i = 0; $i < $str; $i++) {
                            echo '<div class="tfp-cal-day is-inactive"><span class="tfp-cal-day-num">' . ($start_prev_day + $i) . '</span></div>';
                        }
                    }

                    // Display current month days
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $classes = 'tfp-cal-day';
                        // Highlight today if it's the current real month
                        if ($ym === date('Y-m') && $day == date('j')) {
                            // Example: highlight today, or rely on mock data
                        }

                        if ($day === 16 || $day === 19) {
                            $classes .= ' is-selected';
                        }

                        echo '<div class="' . esc_attr($classes) . '">';
                        echo '<span class="tfp-cal-day-num">' . $day . '</span>';
                        if (in_array($day, $meetings)) {
                            echo '<span class="tfp-cal-event-pill tfp-cal-event-pill--meeting">Spring 2026 - Disci</span>';
                        }
                        if (in_array($day, $holidays)) {
                            echo '<span class="tfp-cal-event-pill tfp-cal-event-pill--holiday">Juneteenth</span>';
                        }
                        echo '</div>';
                    }

                    // Calculate remaining cells to complete the last row
                    $total_cells = $str + $days_in_month;
                    $remaining = 7 - ($total_cells % 7);
                    if ($remaining > 0 && $remaining < 7) {
                        for ($i = 1; $i <= $remaining; $i++) {
                            echo '<div class="tfp-cal-day is-inactive"><span class="tfp-cal-day-num">' . $i . '</span></div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Right: Sidebar -->
            <div class="tfp-cal-sidebar">

                <div>
                    <h3 class="tfp-cal-sidebar-section__title"><?php esc_html_e('Select a day', 'tfp-dashboard'); ?></h3>
                    <p class="tfp-cal-sidebar-section__desc"><?php esc_html_e('Click any date to see details', 'tfp-dashboard'); ?></p>
                    <div class="tfp-cal-sidebar-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <p><?php esc_html_e('Click a date on the calendar to see meetings and details.', 'tfp-dashboard'); ?></p>
                    </div>
                </div>

                <div>
                    <h3 class="tfp-cal-sidebar-section__title" style="margin-bottom: 16px;"><?php esc_html_e('Upcoming Off Days', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-cal-sidebar-list">
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Juneteenth</span>
                            <span class="tfp-cal-sidebar-item-date">Jun 19, 2026</span>
                        </div>
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Independence Day</span>
                            <span class="tfp-cal-sidebar-item-date">July 14, 2026</span>
                        </div>
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Thanksgiving</span>
                            <span class="tfp-cal-sidebar-item-date">Nov 26, 20226</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="tfp-cal-sidebar-section__title" style="margin-bottom: 16px;"><?php esc_html_e('Next Meetings', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-cal-sidebar-list">
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Cohort Name</span>
                            <span class="tfp-cal-sidebar-item-date">2026-06-03 - 6:00 PM</span>
                        </div>
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Cohort Name</span>
                            <span class="tfp-cal-sidebar-item-date">2026-06-03 - 6:00 PM</span>
                        </div>
                        <div class="tfp-cal-sidebar-item">
                            <span class="tfp-cal-sidebar-item-name">Cohort Name</span>
                            <span class="tfp-cal-sidebar-item-date">2026-06-03 - 6:00 PM</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php else: ?>
        <p><?php esc_html_e('No requests available.', 'tfp-dashboard'); ?></p>
    <?php endif; ?>
    <?php
}
