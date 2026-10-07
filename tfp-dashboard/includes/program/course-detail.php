<?php
if (!defined('ABSPATH'))
    exit;

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
    if (!in_array($tab, ['about', 'assignment', 'review'], true))
        $tab = 'about';

    // Handle the student course review submission on the same Program Detail screen.
    if (
        $tab === 'review'
        && 'POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '')
        && isset($_POST['tfp_course_review_submit'])
        && (int) ($_POST['tfp_review_course_id'] ?? 0) === (int) $course_id
    ) {
        $nonce = isset($_POST['tfp_course_review_nonce']) ? sanitize_text_field(wp_unslash($_POST['tfp_course_review_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'tfp_submit_course_review_' . $course_id)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'tfp-dashboard'));
        }

        $review_text = isset($_POST['tfp_review_text']) ? trim(wp_strip_all_tags(wp_unslash($_POST['tfp_review_text']))) : '';
        $review_rating = isset($_POST['tfp_review_rating']) ? absint($_POST['tfp_review_rating']) : 0;
        $review_rating = min(5, max(1, $review_rating));

        if ($review_text === '') {
            $redirect = add_query_arg(
                ['course_id' => $course_id, 'course_tab' => 'review', 'review_error' => 'empty'],
                $program_url
            );
            wp_safe_redirect($redirect);
            exit;
        }

        // One review per student per course. Existing review is updated instead of creating duplicates.
        $existing_review = get_comments([
            'post_id' => $course_id,
            'user_id' => $user_id,
            'number' => 1,
            'status' => 'all',
            'type' => 'comment',
        ]);

        if (!empty($existing_review)) {
            $comment_id = (int) $existing_review[0]->comment_ID;
            wp_update_comment([
                'comment_ID' => $comment_id,
                'comment_content' => $review_text,
                'comment_approved' => 1,
            ]);
        } else {
            $comment_id = wp_insert_comment([
                'comment_post_ID' => $course_id,
                'comment_content' => $review_text,
                'user_id' => $user_id,
                'comment_author' => wp_get_current_user()->display_name,
                'comment_author_email' => wp_get_current_user()->user_email,
                'comment_type' => 'comment',
                'comment_approved' => 1,
            ]);
        }

        if ($comment_id) {
            update_comment_meta($comment_id, 'tfp_course_review_rating', $review_rating);

            // Keep the course rating in sync with approved student ratings.
            $rated_comments = get_comments([
                'post_id' => $course_id,
                'status' => 'approve',
                'number' => 0,
            ]);
            $ratings = [];
            foreach ($rated_comments as $rated_comment) {
                $saved_rating = (int) get_comment_meta($rated_comment->comment_ID, 'tfp_course_review_rating', true);
                if ($saved_rating >= 1 && $saved_rating <= 5) {
                    $ratings[] = $saved_rating;
                }
            }
            if ($ratings) {
                update_post_meta($course_id, 'tfp_course_rating', round(array_sum($ratings) / count($ratings), 1));
            }

            $redirect = add_query_arg(
                ['course_id' => $course_id, 'course_tab' => 'review', 'review_submitted' => '1'],
                $program_url
            );
            wp_safe_redirect($redirect);
            exit;
        }

        $redirect = add_query_arg(
            ['course_id' => $course_id, 'course_tab' => 'review', 'review_error' => 'save'],
            $program_url
        );
        wp_safe_redirect($redirect);
        exit;
    }

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
    if (!$author_name)
        $author_name = __('The Follow Project', 'tfp-dashboard');

    $progress = function_exists('tfp_ld_get_journey_progress') ? tfp_ld_get_journey_progress($user_id, $course_id) : ['completed' => 0, 'total' => count($weeks)];
    $percent = !empty($progress['total']) ? (int) round(($progress['completed'] / $progress['total']) * 100) : 0;
    $description = get_the_content(null, false, $course_id);
    if (!$description)
        $description = get_the_excerpt($course_id);
    if (!$description)
        $description = __('Learn, reflect, and grow through each course section with weekly lessons, readings, assignments, quizzes, tests, and group meetings.', 'tfp-dashboard');

    $first_week = !empty($weeks) ? $weeks[0] : null;
    $video_url = $current_week ? get_post_meta($current_week->ID, 'tfp_week_video_url', true) : '';
    if (!$video_url && $first_week)
        $video_url = get_post_meta($first_week->ID, 'tfp_week_video_url', true);

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
                        <video controls preload="metadata"
                            poster="<?php echo esc_url(get_the_post_thumbnail_url($course_id, 'large')); ?>">
                            <source src="<?php echo esc_url($video_url); ?>">
                        </video>
                    <?php elseif ($image): ?>
                        <?php echo $image; ?>
                    <?php else: ?>
                        <div class="tfp-course-detail__media-empty">
                            <?php esc_html_e('Course media will appear here.', 'tfp-dashboard'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tfp-course-detail__title-row">
                    <div>
                        <h3><?php echo esc_html(get_the_title($course_id)); ?></h3>
                        <div class="tfp-course-detail__instructor">
                            <span class="tfp-course-detail__avatar">
                                <?php
                                if ($author_id) {
                                    echo get_avatar(
                                        $author_id,
                                        32,
                                        '',
                                        $author_name,
                                        ['class' => 'tfp-course-detail__avatar-img']
                                    );
                                }
                                ?>
                            </span>
                            <span><?php echo esc_html($author_name); ?></span>
                            <?php if ($rating): ?><span class="tfp-course-detail__rating">★
                                    <?php echo esc_html(number_format($rating, 1)); ?>
                                    <?php if ($comment_count)
                                        echo ' (' . esc_html($comment_count) . ' ' . esc_html__('Reviews', 'tfp-dashboard') . ')'; ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="tfp-course-detail__meta">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path
                                d="M7.99951 1.54492C8.97462 1.54492 9.92312 1.86508 10.6987 2.45605C11.4743 3.04705 12.0347 3.8763 12.2935 4.81641C12.5521 5.75666 12.4955 6.75648 12.1313 7.66113C11.7672 8.56571 11.1157 9.3254 10.2778 9.82422L10.189 9.87695L10.2876 9.90918C11.9882 10.4649 13.4459 11.6377 14.3921 13.2734V13.2744C14.4237 13.326 14.4455 13.3835 14.4546 13.4434C14.4636 13.5031 14.4606 13.5645 14.4458 13.623C14.4309 13.6814 14.404 13.7361 14.3677 13.7842C14.3312 13.8324 14.2852 13.8731 14.2329 13.9033C14.1805 13.9336 14.1221 13.9534 14.062 13.9609C14.0021 13.9684 13.9414 13.9636 13.8833 13.9473C13.8251 13.9308 13.7704 13.903 13.7231 13.8652C13.6759 13.8275 13.6368 13.7806 13.6079 13.7275L13.6069 13.7266L13.3745 13.3516C12.1587 11.5287 10.1793 10.4531 7.99951 10.4531C5.67444 10.4533 3.57771 11.6775 2.39209 13.7266V13.7275C2.36326 13.7806 2.32403 13.8275 2.27686 13.8652C2.22961 13.903 2.1749 13.9308 2.1167 13.9473C2.05846 13.9637 1.99705 13.9685 1.93701 13.9609C1.87709 13.9534 1.81939 13.9335 1.76709 13.9033C1.71471 13.8731 1.66883 13.8324 1.63232 13.7842C1.59598 13.7361 1.56907 13.6815 1.5542 13.623C1.53935 13.5645 1.53638 13.5031 1.54541 13.4434C1.55448 13.3837 1.57541 13.3259 1.60693 13.2744L1.60791 13.2734C2.55408 11.6371 4.01176 10.4643 5.7124 9.90918L5.81104 9.87695L5.72217 9.82422C4.88427 9.32541 4.2328 8.56572 3.86865 7.66113C3.50451 6.75648 3.44786 5.75666 3.70654 4.81641C3.96528 3.87632 4.52573 3.04704 5.30127 2.45605C6.07676 1.86521 7.02459 1.54501 7.99951 1.54492ZM8.69189 2.52148C8.00404 2.38466 7.29105 2.45436 6.64307 2.72266C5.99502 2.99109 5.441 3.44609 5.05127 4.0293C4.66153 4.61258 4.45264 5.29849 4.45264 6C4.4537 6.94026 4.82783 7.84195 5.49268 8.50684C6.15753 9.17169 7.05927 9.54577 7.99951 9.54688C8.70088 9.54688 9.38701 9.33881 9.97021 8.94922C10.5534 8.55954 11.0084 8.00541 11.2769 7.35742C11.5453 6.70932 11.6159 5.99564 11.479 5.30762C11.3422 4.61982 11.0041 3.98814 10.5083 3.49219C10.0123 2.99617 9.37988 2.65835 8.69189 2.52148Z"
                                fill="#818183" stroke="#818183" stroke-width="0.09375" />
                        </svg>

                        <?php echo esc_html(sprintf(__('%d Student%s', 'tfp-dashboard'), function_exists('learndash_get_course_users_access_from_meta') ? count((array) learndash_get_course_users_access_from_meta($course_id)) : 0, '')); ?></span>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path
                                d="M13.5 2.5H2.5C2.23478 2.5 1.98043 2.60536 1.79289 2.79289C1.60536 2.98043 1.5 3.23478 1.5 3.5V12.5C1.5 12.7652 1.60536 13.0196 1.79289 13.2071C1.98043 13.3946 2.23478 13.5 2.5 13.5H13.5C13.7652 13.5 14.0196 13.3946 14.2071 13.2071C14.3946 13.0196 14.5 12.7652 14.5 12.5V3.5C14.5 3.23478 14.3946 2.98043 14.2071 2.79289C14.0196 2.60536 13.7652 2.5 13.5 2.5ZM13.5 12.5H2.5V3.5H13.5V12.5ZM11.5 6C11.5 6.13261 11.4473 6.25979 11.3536 6.35355C11.2598 6.44732 11.1326 6.5 11 6.5H5C4.86739 6.5 4.74021 6.44732 4.64645 6.35355C4.55268 6.25979 4.5 6.13261 4.5 6C4.5 5.86739 4.55268 5.74021 4.64645 5.64645C4.74021 5.55268 4.86739 5.5 5 5.5H11C11.1326 5.5 11.2598 5.55268 11.3536 5.64645C11.4473 5.74021 11.5 5.86739 11.5 6ZM11.5 8C11.5 8.13261 11.4473 8.25979 11.3536 8.35355C11.2598 8.44732 11.1326 8.5 11 8.5H5C4.86739 8.5 4.74021 8.44732 4.64645 8.35355C4.55268 8.25979 4.5 8.13261 4.5 8C4.5 7.86739 4.55268 7.74021 4.64645 7.64645C4.74021 7.55268 4.86739 7.5 5 7.5H11C11.1326 7.5 11.2598 7.55268 11.3536 7.64645C11.4473 7.74021 11.5 7.86739 11.5 8ZM11.5 10C11.5 10.1326 11.4473 10.2598 11.3536 10.3536C11.2598 10.4473 11.1326 10.5 11 10.5H5C4.86739 10.5 4.74021 10.4473 4.64645 10.3536C4.55268 10.2598 4.5 10.1326 4.5 10C4.5 9.86739 4.55268 9.74021 4.64645 9.64645C4.74021 9.55268 4.86739 9.5 5 9.5H11C11.1326 9.5 11.2598 9.55268 11.3536 9.64645C11.4473 9.74021 11.5 9.86739 11.5 10Z"
                                fill="#818183" />
                        </svg>

                        <?php echo esc_html(count($weeks)); ?>     <?php esc_html_e('Module', 'tfp-dashboard'); ?></span>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path
                                d="M8 1.54688C9.71092 1.54869 11.3517 2.22867 12.5615 3.43848C13.7713 4.64828 14.4513 6.28908 14.4531 8C14.4531 9.27631 14.0743 10.5237 13.3652 11.585C12.6562 12.6461 11.6488 13.4735 10.4697 13.9619C9.29062 14.4503 7.99295 14.5781 6.74121 14.3291C5.48943 14.0801 4.33901 13.466 3.43652 12.5635C2.53404 11.661 1.91989 10.5106 1.6709 9.25879C1.42194 8.00705 1.54968 6.70938 2.03809 5.53027C2.52651 4.35121 3.35389 3.34381 4.41504 2.63477C5.40983 1.97007 6.56826 1.59599 7.76074 1.55176L8 1.54688ZM10.123 2.875C9.10953 2.45519 7.99392 2.34558 6.91797 2.55957C5.84198 2.7736 4.85387 3.30238 4.07812 4.07812C3.30238 4.85387 2.7736 5.84198 2.55957 6.91797C2.34558 7.99392 2.45519 9.10953 2.875 10.123C3.29482 11.1365 4.0059 12.0028 4.91797 12.6123C5.77322 13.1838 6.7697 13.505 7.79492 13.543L8 13.5469C9.4706 13.5452 10.8801 12.9598 11.9199 11.9199C12.9598 10.8801 13.5452 9.4706 13.5469 8C13.5469 6.90293 13.2218 5.83015 12.6123 4.91797C12.0028 4.0059 11.1365 3.29482 10.123 2.875ZM8 4.04688C8.12018 4.04688 8.23534 4.09471 8.32031 4.17969C8.40529 4.26466 8.45312 4.37982 8.45312 4.5V7.54688H11.5C11.6202 7.54688 11.7353 7.59471 11.8203 7.67969C11.9053 7.76466 11.9531 7.87982 11.9531 8C11.9531 8.12018 11.9053 8.23534 11.8203 8.32031C11.7353 8.40529 11.6202 8.45312 11.5 8.45312H8C7.87982 8.45312 7.76466 8.40529 7.67969 8.32031C7.59471 8.23534 7.54688 8.12018 7.54688 8V4.5C7.54688 4.37982 7.59471 4.26466 7.67969 4.17969C7.76466 4.09471 7.87982 4.04688 8 4.04688Z"
                                fill="#818183" stroke="#818183" stroke-width="0.09375" />
                        </svg>

                        <?php echo esc_html(count($weeks) * 10); ?>m</span>
                </div>

                <nav class="tfp-course-detail__tabs">
                    <?php foreach (['about' => __('About', 'tfp-dashboard'), 'assignment' => __('Assignment', 'tfp-dashboard'), 'review' => __('Review', 'tfp-dashboard')] as $key => $label): ?>
                        <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>"
                            href="<?php echo esc_url(add_query_arg(['course_id' => $course_id, 'course_tab' => $key], $program_url)); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php if ($tab === 'about'): ?>
                    <section class="tfp-course-detail__content">
                        <h3><?php esc_html_e('Description', 'tfp-dashboard'); ?></h3>
                        <div class="tfp-course-detail__richtext">
                            <?php echo wp_kses_post(wpautop(wp_strip_all_tags($description))); ?>
                        </div>
                        <h3><?php esc_html_e('Key Point', 'tfp-dashboard'); ?></h3>
                        <ul class="tfp-course-detail__points">
                            <li><?php esc_html_e('Weekly videos and readings for each section', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Practical assignments to strengthen learning', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Tests and quizzes to measure growth', 'tfp-dashboard'); ?></li>
                        </ul>
                    </section>
                <?php elseif ($tab === 'assignment'): ?>
                    <?php tfp_dashboard_render_program_course_assignments($user_id, $course_id, $weeks, $current_week, $program_url); ?>
                <?php else: ?>
                    <section class="tfp-course-detail__reviews">
                        <?php if (isset($_GET['review_submitted'])): ?>
                            <div class="tfp-course-review__notice"><?php esc_html_e('Your review has been saved.', 'tfp-dashboard'); ?></div>
                        <?php elseif (isset($_GET['review_error'])): ?>
                            <div class="tfp-course-review__notice tfp-course-review__notice--error">
                                <?php
                                $review_error = sanitize_key(wp_unslash($_GET['review_error']));
                                echo esc_html(
                                    $review_error === 'empty'
                                        ? __('Please enter a review before submitting.', 'tfp-dashboard')
                                        : __('We could not save your review. Please try again.', 'tfp-dashboard')
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($course_comments): ?>
                            <?php foreach ($course_comments as $comment):
                                $comment_rating = (int) get_comment_meta($comment->comment_ID, 'tfp_course_review_rating', true);
                                $comment_rating = $comment_rating >= 1 && $comment_rating <= 5 ? $comment_rating : 0;
                                ?>
                                <article class="tfp-course-review">
                                    <h4><?php echo esc_html(get_comment_author($comment)); ?></h4>
                                    <?php if ($comment_rating): ?>
                                        <div class="tfp-course-review__stars" aria-label="<?php echo esc_attr(sprintf(__('%d out of 5 stars', 'tfp-dashboard'), $comment_rating)); ?>">
                                            <?php echo esc_html(str_repeat('★', $comment_rating) . str_repeat('☆', 5 - $comment_rating)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <time><?php echo esc_html(get_comment_date(get_option('date_format'), $comment)); ?></time>
                                    <p><?php echo esc_html(get_comment_text($comment)); ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="tfp-course-review__empty">
                                <?php esc_html_e('No course reviews have been published yet.', 'tfp-dashboard'); ?>
                            </div>
                        <?php endif; ?>

                        <details class="tfp-course-review-form">
                            <summary class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Give Review', 'tfp-dashboard'); ?></summary>
                            <form method="post" class="tfp-course-review-form__inner">
                                <?php wp_nonce_field('tfp_submit_course_review_' . $course_id, 'tfp_course_review_nonce'); ?>
                                <input type="hidden" name="tfp_course_review_submit" value="1">
                                <input type="hidden" name="tfp_review_course_id" value="<?php echo esc_attr($course_id); ?>">

                                <div class="tfp-course-review-form__field">
                                    <label for="tfp-review-rating-<?php echo esc_attr($course_id); ?>"><?php esc_html_e('Your Rating', 'tfp-dashboard'); ?></label>
                                    <select id="tfp-review-rating-<?php echo esc_attr($course_id); ?>" name="tfp_review_rating" required>
                                        <option value=""><?php esc_html_e('Select rating', 'tfp-dashboard'); ?></option>
                                        <option value="5">5 — ★★★★★</option>
                                        <option value="4">4 — ★★★★☆</option>
                                        <option value="3">3 — ★★★☆☆</option>
                                        <option value="2">2 — ★★☆☆☆</option>
                                        <option value="1">1 — ★☆☆☆☆</option>
                                    </select>
                                </div>

                                <div class="tfp-course-review-form__field">
                                    <label for="tfp-review-text-<?php echo esc_attr($course_id); ?>"><?php esc_html_e('Your Review', 'tfp-dashboard'); ?></label>
                                    <textarea id="tfp-review-text-<?php echo esc_attr($course_id); ?>" name="tfp_review_text" rows="5" required placeholder="<?php esc_attr_e('Share your experience with this course...', 'tfp-dashboard'); ?>"></textarea>
                                </div>

                                <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Submit Review', 'tfp-dashboard'); ?></button>
                            </form>
                        </details>

                        <?php if ($comment_count > 4): ?>
                            <button type="button" class="tfp-dash-btn tfp-course-detail__more"><?php esc_html_e('See more review', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>

            </main>

            <aside class="tfp-course-detail__sidebar">
                <div class="tfp-course-detail__side-head">
                    <h3><?php echo esc_html(get_the_title($course_id)); ?></h3>
                    <div class="tfp-course-detail__side-author">
                        <span class="tfp-course-detail__avatar tfp-course-detail__avatar--side">
                            <?php
                            if ($author_id) {
                                echo get_avatar(
                                    $author_id,
                                    32,
                                    '',
                                    $author_name,
                                    ['class' => 'tfp-course-detail__avatar-img']
                                );
                            }
                            ?>
                        </span>
                        <span><?php echo esc_html($author_name); ?></span>
                        <span class="tfp-course-detail__side-rating">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path
                                    d="M10 2.5L12.0279 7.20889L17.1329 7.68237L13.2811 11.0661L14.4084 16.0676L10 13.45L5.59161 16.0676L6.71886 11.0661L2.86708 7.68237L7.97214 7.20889L10 2.5Z"
                                    fill="#FFBB54" />
                            </svg>
                            <?php echo esc_html($rating ? number_format($rating, 1) : '—'); ?></span>
                    </div>
                </div>
                <?php if (!empty($progress['completed']) && (int) $progress['completed'] > 0): ?>
                    <div class="tfp-course-detail__progress">
                        <div class="tfp-course-detail__progress-track">
                            <span style="width:<?php echo esc_attr(min(100, max(0, $percent))); ?>%"></span>
                        </div>
                        <div class="tfp-course-detail__progress-meta">
                            <span><?php echo esc_html(count($weeks) ? sprintf(__('%d/%d Module', 'tfp-dashboard'), $progress['completed'], count($weeks)) : '0/0 Module'); ?></span>
                            <span><?php echo esc_html($percent); ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="tfp-course-detail__module-summary">
                    <h4><?php echo esc_html(count($weeks)); ?> <?php esc_html_e('Module', 'tfp-dashboard'); ?></h4>
                    <span><?php echo esc_html(count($weeks) ? sprintf(__('%d/%d Done', 'tfp-dashboard'), $progress['completed'], count($weeks)) : '0/0 Done'); ?></span>
                </div>
                <ol class="tfp-course-detail__modules">
                    <?php foreach ($weeks as $i => $week):
                        $complete = function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($user_id, $week->ID);
                        $is_current = $current_week && $current_week->ID === $week->ID;
                        ?>
                        <li class="<?php echo $complete ? 'is-complete' : ($is_current ? 'is-current' : ''); ?>">
                            <span
                                class="tfp-course-detail__module-number"><?php echo $complete ? '✓' : esc_html($i + 1); ?></span>
                            <span class="tfp-course-detail__module-name"><?php echo esc_html($week->post_title); ?></span>
                            <time><?php esc_html_e('10:00', 'tfp-dashboard'); ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <a class="tfp-dash-btn tfp-dash-btn--primary tfp-course-detail__continue"
                    href="<?php echo esc_url($continue_url); ?>"><?php esc_html_e('Continue Class', 'tfp-dashboard'); ?></a>
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
    // Assignment sequence: each task unlocks only after the previous task is complete.
    // This keeps the student journey linear, matching the Figma interaction pattern.
    $items = [
        ['title' => sprintf(__('%s: Watch Video', 'tfp-dashboard'), $current_week->post_title), 'desc' => __('Watch the lesson and take notes as you follow along.', 'tfp-dashboard'), 'tab' => 'video', 'done' => $video_done, 'enabled' => true, 'label' => __('Start Lesson', 'tfp-dashboard')],
        ['title' => sprintf(__('%s: Start Reading', 'tfp-dashboard'), $current_week->post_title), 'desc' => __('Open the reading section to explore this week\'s topic.', 'tfp-dashboard'), 'tab' => 'reading', 'done' => $reading_done, 'enabled' => $video_done, 'label' => __('Start Reading', 'tfp-dashboard')],
        ['title' => sprintf(__('%s: Homework', 'tfp-dashboard'), $current_week->post_title), 'desc' => __('Answer the reflection questions to apply what you learned.', 'tfp-dashboard'), 'tab' => 'homework', 'done' => $homework_done, 'enabled' => $reading_done, 'label' => __('Start Homework', 'tfp-dashboard')],
        ['title' => sprintf(__('%s: Take Quiz', 'tfp-dashboard'), $current_week->post_title), 'desc' => __('Complete the short quiz to test your understanding.', 'tfp-dashboard'), 'tab' => 'quiz', 'done' => $quiz_done, 'enabled' => $homework_done, 'label' => __('Start Quiz', 'tfp-dashboard')],
        ['title' => sprintf(__('%s: Take Test', 'tfp-dashboard'), $current_week->post_title), 'desc' => __('Complete this test to unlock next week\'s lessons.', 'tfp-dashboard'), 'tab' => 'test', 'done' => $test_done, 'enabled' => $quiz_done, 'label' => __('Start Test', 'tfp-dashboard')],
        ['title' => __('Weekly Group Meeting', 'tfp-dashboard'), 'desc' => __('Join your group to review this week\'s topic and finish your weekly progress.', 'tfp-dashboard'), 'tab' => 'meeting', 'done' => false, 'enabled' => $test_done, 'label' => __('Join Meeting', 'tfp-dashboard')],
    ];
    ?>
    <section class="tfp-course-detail__assignment">
        <?php foreach ($items as $item):
            $url = add_query_arg(['lesson_id' => $current_week->ID, 'tab' => $item['tab']], $week_url);
            ?>
            <article class="tfp-course-assignment <?php echo $item['done'] ? 'is-complete' : ''; ?>">
                <div>
                    <h6><?php echo esc_html($item['title']); ?></h6>
                    <p><?php echo esc_html($item['desc']); ?></p>
                </div>
                <div class="tfp-course-assignment__actions">
                    <?php if (!empty($item['enabled'])): ?>
                        <a class="tfp-dash-btn <?php echo $item['done'] ? 'tfp-reded-btn' : 'tfp-dash-btn--primary'; ?>"
                            href="<?php echo esc_url($url); ?>"><?php echo esc_html($item['done'] ? __('Completed', 'tfp-dashboard') : $item['label']); ?></a>
                    <?php else: ?>
                        <span class="tfp-dash-btn tfp-course-assignment__disabled" aria-disabled="true"><?php echo esc_html($item['label']); ?></span>
                    <?php endif; ?>
                    <?php if (!$item['done'] && $item['tab'] !== 'meeting'): ?>
                        <span class="tfp-course-assignment__due"><?php esc_html_e('7 Days Left', 'tfp-dashboard'); ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
}
