<?php
if (!defined('ABSPATH')) exit;

/**
 * Program Course Detail — the course-level screen shown after Continue Program.
 * Uses real LearnDash course/week data and reuses the existing week-player routes.
 */
function tfp_dashboard_render_program_course_detail($course_id)
{
    $user_id = get_current_user_id();
    $course = get_post(absint($course_id));
    if (!$course || $course->post_type !== 'sfwd-courses') {
        echo '<div class="tfp-dash-panel tfp-program-empty"><h3>' . esc_html__('Course not found', 'tfp-dashboard') . '</h3></div>';
        return;
    }

    $enrolled = function_exists('learndash_user_get_enrolled_courses')
        ? in_array((int) $course_id, array_map('absint', (array) learndash_user_get_enrolled_courses($user_id)), true)
        : ((int) tfp_ld_get_program_course_id($user_id) === (int) $course_id);

    if (!$enrolled) {
        echo '<div class="tfp-dash-panel tfp-program-empty"><h3>' . esc_html__('Course access unavailable', 'tfp-dashboard') . '</h3></div>';
        return;
    }

    $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $current_week = function_exists('tfp_ld_get_current_week') ? tfp_ld_get_current_week($user_id, $course_id) : null;
    $current_week = $current_week ?: (!empty($weeks) ? $weeks[0] : null);
    $tab = isset($_GET['course_tab']) ? sanitize_key($_GET['course_tab']) : 'about';
    if (!in_array($tab, ['about', 'assignment', 'review'], true)) $tab = 'about';

    $program_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-program') : '#';
    $continue_url = $current_week
        ? add_query_arg(['lesson_id' => $current_week->ID, 'tab' => 'video'], function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-week') : '#')
        : $program_url;

    tfp_dashboard_render_page_header(
        __('Programs', 'tfp-dashboard'),
        get_the_title($course_id),
        __('Welcome back. Continue where you left off or review what you\'ve completed.', 'tfp-dashboard')
    );

    $image = get_the_post_thumbnail($course_id, 'large');
    $author_id = (int) $course->post_author;
    $author_name = $author_id ? get_the_author_meta('display_name', $author_id) : '';
    if (!$author_name) $author_name = __('The Follow Project', 'tfp-dashboard');

    $progress = function_exists('tfp_ld_get_journey_progress') ? tfp_ld_get_journey_progress($user_id, $course_id) : ['completed'=>0,'total'=>count($weeks)];
    $percent = !empty($progress['total']) ? (int) round(($progress['completed'] / $progress['total']) * 100) : 0;
    $description = get_the_content(null, false, $course_id);
    if (!$description) $description = get_the_excerpt($course_id);
    if (!$description) $description = __('Learn, reflect, and grow through each course section with weekly lessons, readings, assignments, quizzes, tests, and group meetings.', 'tfp-dashboard');

    $first_week = !empty($weeks) ? $weeks[0] : null;
    $video_url = $current_week ? get_post_meta($current_week->ID, 'tfp_week_video_url', true) : '';
    if (!$video_url && $first_week) $video_url = get_post_meta($first_week->ID, 'tfp_week_video_url', true);

    $course_comments = get_comments([
        'post_id' => $course_id,
        'status' => 'approve',
        'number' => 4,
        'orderby' => 'comment_date_gmt',
        'order' => 'DESC',
    ]);
    $comment_count = (int) get_comments(['post_id' => $course_id, 'status' => 'approve', 'count' => true]);
    $rating = get_post_meta($course_id, 'tfp_course_rating', true);
    $rating = $rating !== '' ? (float) $rating : 0;

    ?>
    <div class="tfp-course-detail">
        <div class="tfp-course-detail__layout">
            <main class="tfp-course-detail__main">
                <div class="tfp-course-detail__media">
                    <?php if ($video_url): ?>
                        <video controls preload="metadata" poster="<?php echo esc_url(get_the_post_thumbnail_url($course_id, 'large')); ?>">
                            <source src="<?php echo esc_url($video_url); ?>">
                        </video>
                    <?php elseif ($image): ?>
                        <?php echo $image; ?>
                    <?php else: ?>
                        <div class="tfp-course-detail__media-empty"><?php esc_html_e('Course media will appear here.', 'tfp-dashboard'); ?></div>
                    <?php endif; ?>
                </div>

                <div class="tfp-course-detail__title-row">
                    <div>
                        <h2><?php echo esc_html(get_the_title($course_id)); ?></h2>
                        <div class="tfp-course-detail__instructor">
                            <span class="tfp-course-detail__avatar"><?php echo $author_id ? wp_get_attachment_image((int)get_user_meta($author_id, 'tfp_profile_photo_id', true), 32, false) : ''; ?></span>
                            <span><?php echo esc_html($author_name); ?></span>
                            <?php if ($rating): ?><span class="tfp-course-detail__rating">★ <?php echo esc_html(number_format($rating, 1)); ?><?php if ($comment_count) echo ' (' . esc_html($comment_count) . ' ' . esc_html__('Reviews','tfp-dashboard') . ')'; ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="tfp-course-detail__meta">
                    <span>◯ <?php echo esc_html(sprintf(__('%d Student%s','tfp-dashboard'), function_exists('learndash_get_course_users_access_from_meta') ? count((array) learndash_get_course_users_access_from_meta($course_id)) : 0, '' )); ?></span>
                    <span>▣ <?php echo esc_html(count($weeks)); ?> <?php esc_html_e('Module', 'tfp-dashboard'); ?></span>
                    <span>◷ <?php echo esc_html(count($weeks) * 10); ?>m</span>
                </div>

                <nav class="tfp-course-detail__tabs">
                    <?php foreach (['about'=>__('About','tfp-dashboard'),'assignment'=>__('Assignment','tfp-dashboard'),'review'=>__('Review','tfp-dashboard')] as $key=>$label): ?>
                        <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['course_id'=>$course_id,'course_tab'=>$key], $program_url)); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php if ($tab === 'about'): ?>
                    <section class="tfp-course-detail__content">
                        <h3><?php esc_html_e('Description','tfp-dashboard'); ?></h3>
                        <div class="tfp-course-detail__richtext"><?php echo wp_kses_post(wpautop(wp_strip_all_tags($description))); ?></div>
                        <h3><?php esc_html_e('Key Point','tfp-dashboard'); ?></h3>
                        <ul class="tfp-course-detail__points">
                            <li><?php esc_html_e('Weekly videos and readings for each section','tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Practical assignments to strengthen learning','tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Tests and quizzes to measure growth','tfp-dashboard'); ?></li>
                        </ul>
                    </section>
                <?php elseif ($tab === 'assignment'): ?>
                    <?php tfp_dashboard_render_program_course_assignments($user_id, $course_id, $weeks, $current_week, $program_url); ?>
                <?php else: ?>
                    <section class="tfp-course-detail__reviews">
                        <?php if ($course_comments): foreach ($course_comments as $comment): ?>
                            <article class="tfp-course-review">
                                <h4><?php echo esc_html(get_comment_author($comment)); ?></h4>
                                <div class="tfp-course-review__stars">★ ★ ★ ★ ★</div>
                                <time><?php echo esc_html(get_comment_date(get_option('date_format'), $comment)); ?></time>
                                <p><?php echo esc_html(get_comment_text($comment)); ?></p>
                            </article>
                        <?php endforeach; else: ?>
                            <div class="tfp-course-review__empty"><?php esc_html_e('No course reviews have been published yet.','tfp-dashboard'); ?></div>
                        <?php endif; ?>
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Give Review','tfp-dashboard'); ?></button>
                        <?php if ($comment_count > 4): ?><button type="button" class="tfp-dash-btn tfp-course-detail__more"><?php esc_html_e('See more review','tfp-dashboard'); ?></button><?php endif; ?>
                    </section>
                <?php endif; ?>
            </main>

            <aside class="tfp-course-detail__sidebar">
                <div class="tfp-course-detail__side-head">
                    <h3><?php echo esc_html(get_the_title($course_id)); ?></h3>
                    <div><span><?php echo esc_html($author_name); ?></span><span class="tfp-course-detail__side-rating">★ <?php echo esc_html($rating ? number_format($rating,1) : '—'); ?></span></div>
                </div>
                <div class="tfp-course-detail__side-progress"><span><?php echo esc_html(count($weeks) ? sprintf(__('%d/%d Module','tfp-dashboard'), $progress['completed'], count($weeks)) : '0/0 Module'); ?></span><strong><?php echo esc_html($percent); ?>%</strong></div>
                <div class="tfp-course-detail__progressbar"><span style="width:<?php echo esc_attr($percent); ?>%"></span></div>
                <h4><?php echo esc_html(count($weeks)); ?> <?php esc_html_e('Module','tfp-dashboard'); ?></h4>
                <ol class="tfp-course-detail__modules">
                    <?php foreach ($weeks as $i=>$week):
                        $complete = function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($user_id, $week->ID);
                        $is_current = $current_week && $current_week->ID === $week->ID;
                        ?>
                        <li class="<?php echo $complete ? 'is-complete' : ($is_current ? 'is-current' : ''); ?>">
                            <span class="tfp-course-detail__module-number"><?php echo $complete ? '✓' : esc_html($i+1); ?></span>
                            <span class="tfp-course-detail__module-name"><?php echo esc_html($week->post_title); ?></span>
                            <time><?php esc_html_e('10:00','tfp-dashboard'); ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <a class="tfp-dash-btn tfp-dash-btn--primary tfp-course-detail__continue" href="<?php echo esc_url($continue_url); ?>"><?php esc_html_e('Continue Class','tfp-dashboard'); ?></a>
            </aside>
        </div>
    </div>
    <?php
}

function tfp_dashboard_render_program_course_assignments($user_id, $course_id, $weeks, $current_week, $program_url)
{
    if (!$current_week) {
        echo '<div class="tfp-course-detail__content"><p>' . esc_html__('No course modules are available yet.', 'tfp-dashboard') . '</p></div>';
        return;
    }
    $progress = tfp_ld_get_week_progress($user_id, $current_week->ID);
    $week_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-week') : '#';
    $reading_done = !empty($progress['reading']);
    $homework_done = !empty($progress['homework']);
    $quiz_done = !empty($progress['quiz']);
    $test_done = !empty($progress['test']);
    $video_done = !empty($progress['video']);
    $items = [
        ['title'=>sprintf(__('%s: Watch Video','tfp-dashboard'),$current_week->post_title),'desc'=>__('Watch the lesson and take notes as you follow along.','tfp-dashboard'),'tab'=>'video','done'=>$video_done,'label'=>__('Start Lesson','tfp-dashboard')],
        ['title'=>sprintf(__('%s: Start Reading','tfp-dashboard'),$current_week->post_title),'desc'=>__('Open the reading section to explore this week\'s topic.','tfp-dashboard'),'tab'=>'reading','done'=>$reading_done,'label'=>__('Start Reading','tfp-dashboard')],
        ['title'=>sprintf(__('%s: Homework','tfp-dashboard'),$current_week->post_title),'desc'=>__('Answer the reflection questions to apply what you learned.','tfp-dashboard'),'tab'=>'homework','done'=>$homework_done,'label'=>__('Start Homework','tfp-dashboard')],
        ['title'=>sprintf(__('%s: Take Quiz','tfp-dashboard'),$current_week->post_title),'desc'=>__('Complete the short quiz to test your understanding.','tfp-dashboard'),'tab'=>'quiz','done'=>$quiz_done,'label'=>__('Start Quiz','tfp-dashboard')],
        ['title'=>sprintf(__('%s: Take Test','tfp-dashboard'),$current_week->post_title),'desc'=>__('Complete this test to unlock next week\'s lessons.','tfp-dashboard'),'tab'=>'test','done'=>$test_done,'label'=>__('Start Test','tfp-dashboard')],
        ['title'=>__('Weekly Group Meeting','tfp-dashboard'),'desc'=>__('Join your group to review this week\'s topic and finish your weekly progress.','tfp-dashboard'),'tab'=>'meeting','done'=>false,'label'=>__('Join Meeting','tfp-dashboard')],
    ];
    ?>
    <section class="tfp-course-detail__assignment">
        <?php foreach ($items as $item):
            $url = add_query_arg(['lesson_id'=>$current_week->ID,'tab'=>$item['tab']], $week_url);
            ?>
            <article class="tfp-course-assignment <?php echo $item['done'] ? 'is-complete' : ''; ?>">
                <div>
                    <h3><?php echo esc_html($item['title']); ?></h3>
                    <p><?php echo esc_html($item['desc']); ?></p>
                </div>
                <a class="tfp-dash-btn <?php echo $item['done'] ? 'tfp-reded-btn' : 'tfp-dash-btn--primary'; ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($item['done'] ? __('Completed','tfp-dashboard') : $item['label']); ?></a>
                <?php if (!$item['done'] && $item['tab'] !== 'meeting'): ?><span class="tfp-course-assignment__due"><?php esc_html_e('7 Days Left','tfp-dashboard'); ?></span><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
}
