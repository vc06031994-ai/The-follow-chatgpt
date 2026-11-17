<?php
/**
 * Course Rules tab — course-level defaults and progression settings.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Load the WordPress media picker used by the Course Identity image field.
if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}

$rules_meta_key = 'tfp_course_rules_settings';
$rules_settings = get_post_meta($course_id, $rules_meta_key, true);
$rules_settings = is_array($rules_settings) ? $rules_settings : [];

$rules_extra_fields = [
    'video_completion_percent',
    'quiz_pass_percent',
    'test_pass_percent',
    'late_work_grace_period',
    'allow_facilitator_overrides',
    'auto_unlock_on_close_section',
    'track_attendance',
    'allowed_misses',
    'count_excused_absences',
    'required_checks',
    'on_close_action',
    'enforcement_level',
    'notification_recipients',
    'quiz_questions_per_attempt',
    'quiz_attempts_allowed',
    'quiz_passing_score',
    'test_questions_per_attempt',
    'test_attempts_allowed',
    'test_passing_score',
    'assessment_randomization',
    'grading_homework_enabled',
    'grading_homework_points',
    'grading_quiz_enabled',
    'grading_quiz_points',
    'grading_test_enabled',
    'grading_test_points',
    'grading_pass_threshold',
    'grading_pass_rule',
    'grading_award',
    'grading_transcript_entry',
    'grading_ordination',
    'grading_component_minimum_enabled',
    'grading_component_minimum_mode',
    'grading_homework_minimum',
    'grading_test_minimum',
    'grading_facilitator_overrides',
];

$rules_sections = [
    'progression' => [
        'title' => __('Progression Model', 'tfp-dashboard'),
        'description' => __('Define how learners unlock the next section/week.', 'tfp-dashboard'),
        'fields' => [
            'course_mode' => __('Course Mode', 'tfp-dashboard'),
            'unlock_rule' => __('Unlock Rule', 'tfp-dashboard'),
            'late_work' => __('Late Work', 'tfp-dashboard'),
        ],
    ],
    'assessments' => [
        'title' => __('Assessments', 'tfp-dashboard'),
        'description' => __('Configure quiz and test behavior.', 'tfp-dashboard'),
        'fields' => [
            'quiz_defaults' => __('Quiz Defaults', 'tfp-dashboard'),
            'test_defaults' => __('Test Defaults', 'tfp-dashboard'),
            'randomization' => __('Randomization', 'tfp-dashboard'),
        ],
    ],
    'learning_flow' => [
        'title' => __('Learning Flow', 'tfp-dashboard'),
        'description' => __('Default step order within each module.', 'tfp-dashboard'),
        'fields' => [
            'step_order' => __('Step Order', 'tfp-dashboard'),
            'meeting_step' => __('Meeting Step', 'tfp-dashboard'),
        ],
    ],
    'grading' => [
        'title' => __('Grading', 'tfp-dashboard'),
        'description' => __('Points, module assessments, pass & credentials.', 'tfp-dashboard'),
        'fields' => [
            'points_per_module' => __('Points / module', 'tfp-dashboard'),
            'graded_items' => __('Graded Items', 'tfp-dashboard'),
            'points_model' => __('Points Model', 'tfp-dashboard'),
            'grade_calculation' => __('Grade Calculation', 'tfp-dashboard'),
            'quiz_grading' => __('Quiz Defaults', 'tfp-dashboard'),
            'test_grading' => __('Test Defaults', 'tfp-dashboard'),
            'quiz_randomization' => __('Quiz Randomization', 'tfp-dashboard'),
            'test_randomization' => __('Test Randomization', 'tfp-dashboard'),
            'certification' => __('Certification', 'tfp-dashboard'),
            'ordination' => __('Ordination', 'tfp-dashboard'),
        ],
    ],
    'completion' => [
        'title' => __('Completion Requirements', 'tfp-dashboard'),
        'description' => __('Define what “complete” means for each step.', 'tfp-dashboard'),
        'fields' => [
            'video_completion' => __('Video', 'tfp-dashboard'),
            'reading_completion' => __('Reading', 'tfp-dashboard'),
            'homework_completion' => __('Homework', 'tfp-dashboard'),
            'quiz_completion' => __('Quiz', 'tfp-dashboard'),
            'test_completion' => __('Test', 'tfp-dashboard'),
            'meeting_completion' => __('Meeting/Review', 'tfp-dashboard'),
        ],
    ],
    'facilitation' => [
        'title' => __('Facilitation Rules', 'tfp-dashboard'),
        'description' => __('Meeting requirements, policies, and close-out checklist.', 'tfp-dashboard'),
        'fields' => [
            'meeting_required' => __('Meeting Required', 'tfp-dashboard'),
            'attendance_policy' => __('Attendance Policy', 'tfp-dashboard'),
            'close_outs' => __('Close-Outs', 'tfp-dashboard'),
            'enforcement' => __('Enforcement', 'tfp-dashboard'),
        ],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tfp_save_course_rules']) || isset($_POST['tfp_save_course_identity']) || isset($_POST['tfp_save_global_overrides']))) {
    check_admin_referer('tfp_save_course_rules_' . $course_id);
    if (current_user_can('edit_post', $course_id)) {
        if (isset($_POST['tfp_save_course_identity'])) {
            $identity_name = isset($_POST['tfp_course_identity_name']) ? sanitize_text_field(wp_unslash($_POST['tfp_course_identity_name'])) : '';
            $identity_type = isset($_POST['tfp_course_identity_type']) ? sanitize_text_field(wp_unslash($_POST['tfp_course_identity_type'])) : '';
            $identity_description = isset($_POST['tfp_course_identity_description']) ? sanitize_textarea_field(wp_unslash($_POST['tfp_course_identity_description'])) : '';
            if ($identity_name !== '') {
                wp_update_post([
                    'ID' => $course_id,
                    'post_title' => $identity_name,
                    'post_excerpt' => $identity_description,
                ]);
            }
            if (in_array($identity_type, ['Foundation', 'Discipleship', 'Devotional'], true)) {
                update_post_meta($course_id, 'tfp_course_type', $identity_type);
            }
            $identity_image_id = isset($_POST['tfp_course_identity_image_id']) ? absint($_POST['tfp_course_identity_image_id']) : 0;
            if ($identity_image_id && wp_attachment_is_image($identity_image_id)) {
                set_post_thumbnail($course_id, $identity_image_id);
            }
            $course_heading_title = get_the_title($course_id);
            echo '<div class="tfp-admin-course-rules-notice" role="status">' . esc_html__('Course identity saved.', 'tfp-dashboard') . '</div>';
        } elseif (isset($_POST['tfp_save_global_overrides'])) {
            // Store only the editable global policy settings; locked policy values remain server-controlled.
            $submitted_roles = isset($_POST['tfp_global_permission_roles']) && is_array($_POST['tfp_global_permission_roles'])
                ? array_map('sanitize_key', wp_unslash($_POST['tfp_global_permission_roles']))
                : [];
            $allowed_roles = ['administrator', 'courseowner'];
            $permission_roles = array_values(array_intersect($allowed_roles, $submitted_roles));

            if (empty($permission_roles)) {
                echo '<div class="tfp-admin-course-rules-notice" role="alert">' . esc_html__('Select at least one permission role.', 'tfp-dashboard') . '</div>';
            } else {
                $global_overrides = [
                    'cohort_overrides_enabled' => false,
                    'permission_roles' => $permission_roles,
                    'policy_versioning' => isset($_POST['tfp_global_policy_versioning'])
                        ? sanitize_key(wp_unslash($_POST['tfp_global_policy_versioning']))
                        : 'auto',
                    'retroactive_changes' => isset($_POST['tfp_global_retroactive_changes'])
                        ? sanitize_key(wp_unslash($_POST['tfp_global_retroactive_changes']))
                        : 'not_allowed',
                ];

                if (!in_array($global_overrides['policy_versioning'], ['auto', 'explicit'], true)) {
                    $global_overrides['policy_versioning'] = 'auto';
                }
                if (!in_array($global_overrides['retroactive_changes'], ['not_allowed', 'admin_confirmation'], true)) {
                    $global_overrides['retroactive_changes'] = 'not_allowed';
                }

                update_post_meta($course_id, 'tfp_course_global_overrides', $global_overrides);
                echo '<div class="tfp-admin-course-rules-notice" role="status">' . esc_html__('Global overrides and permissions saved.', 'tfp-dashboard') . '</div>';
            }
        } else {
            $submitted = isset($_POST['tfp_course_rules']) && is_array($_POST['tfp_course_rules'])
                ? wp_unslash($_POST['tfp_course_rules'])
                : [];
            $clean = [];
            foreach ($rules_sections as $section_key => $section) {
                foreach ($section['fields'] as $field_key => $label) {
                    $raw = $submitted[$field_key] ?? '';
                    $clean[$field_key] = is_array($raw)
                    ? implode(',', array_map('sanitize_text_field', array_filter($raw, 'is_scalar')))
                    : (is_scalar($raw) ? sanitize_text_field((string) $raw) : '');
                }
            }
            foreach ($rules_extra_fields as $field_key) {
                $raw = $submitted[$field_key] ?? '';
                $clean[$field_key] = is_array($raw)
                    ? implode(',', array_map('sanitize_text_field', array_filter($raw, 'is_scalar')))
                    : (is_scalar($raw) ? sanitize_text_field((string) $raw) : '');
            }
            update_post_meta($course_id, $rules_meta_key, $clean);
            $rules_settings = $clean;
            echo '<div class="tfp-admin-course-rules-notice" role="status">' . esc_html__('Course rules saved.', 'tfp-dashboard') . '</div>';
        }
    }
}

// Build concise summary values for the Grading card.
$grading_homework_enabled = ($rules_settings['grading_homework_enabled'] ?? 'On') === 'On';
$grading_quiz_enabled = ($rules_settings['grading_quiz_enabled'] ?? 'Off') === 'On';
$grading_test_enabled = ($rules_settings['grading_test_enabled'] ?? 'On') === 'On';
$grading_homework_points = (int) ($rules_settings['grading_homework_points'] ?? 60);
$grading_quiz_points = (int) ($rules_settings['grading_quiz_points'] ?? 0);
$grading_test_points = (int) ($rules_settings['grading_test_points'] ?? 40);
$rules_settings['points_per_module'] = (string) ($rules_settings['points_per_module'] ?? '100');
$rules_settings['graded_items'] = 'Homework: ' . ($grading_homework_enabled ? $grading_homework_points : 'Off')
    . ' | Quiz: ' . ($grading_quiz_enabled ? $grading_quiz_points : 'Off')
    . ' | Test: ' . ($grading_test_enabled ? $grading_test_points : 'Off');
$rules_settings['points_model'] = 'Graded parts allocation';
$rules_settings['grade_calculation'] = $rules_settings['grading_component_minimum_mode'] ?? 'Course-average';
$rules_settings['quiz_grading'] = $rules_settings['quiz_passing_score'] ?? '80% passing';
$rules_settings['test_grading'] = $rules_settings['test_passing_score'] ?? '70% passing';
$rules_settings['quiz_randomization'] = $rules_settings['assessment_randomization'] ?? 'No repeats until questions exhausted';
$rules_settings['test_randomization'] = $rules_settings['test_assessment_randomization'] ?? 'No repeats until questions exhausted';
$rules_settings['certification'] = ($rules_settings['grading_award'] ?? 'Certificate of Completion') . ' | Pass: '
    . (string) ($rules_settings['grading_pass_threshold'] ?? '75') . '%';
$rules_settings['ordination'] = $rules_settings['grading_ordination'] ?? 'Admin approval';

// Build concise summary values for the Assessments card.
$rules_settings['quiz_defaults'] = (string) ($rules_settings['quiz_questions_per_attempt'] ?? '10') . ' questions | '
    . (string) ($rules_settings['quiz_attempts_allowed'] ?? '3') . ' attempts | '
    . (string) ($rules_settings['quiz_passing_score'] ?? '80') . '% passing';
$rules_settings['test_defaults'] = (string) ($rules_settings['test_questions_per_attempt'] ?? '10') . ' questions | '
    . (string) ($rules_settings['test_attempts_allowed'] ?? '2') . ' attempts | '
    . (string) ($rules_settings['test_passing_score'] ?? '70') . '% passing';
$rules_settings['randomization'] = $rules_settings['assessment_randomization'] ?? 'No repeats until questions exhausted';

// Build concise summary values for the Facilitation Rules card.
$rules_settings['attendance_policy'] = (($rules_settings['track_attendance'] ?? 'On') === 'On' ? 'On' : 'Off')
    . ' | Allowed misses: ' . (string) ($rules_settings['allowed_misses'] ?? '2')
    . ' | Excused absences: ' . (($rules_settings['count_excused_absences'] ?? 'On') === 'On' ? 'On' : 'Off');
$rules_settings['close_outs'] = array_key_exists('required_checks', $rules_settings)
    ? (trim((string) $rules_settings['required_checks']) !== '' ? str_replace(',', ', ', (string) $rules_settings['required_checks']) : 'None')
    : 'Attendance, Homework, Quiz, Test, Exercise, Notes';
$rules_settings['enforcement'] = (($rules_settings['enforcement_level'] ?? 'Strict') === 'Warnings allowed') ? 'Warnings allowed' : 'Strict';
$course_type_label = get_post_meta($course_id, 'tfp_course_type', true);
if (!$course_type_label) {
    $course_type_label = get_post_meta($course_id, '_sfwd-courses', true);
    if (is_array($course_type_label)) {
        $course_type_label = $course_type_label['course_type'] ?? '';
    }
}
$course_type_label = is_scalar($course_type_label) && (string) $course_type_label !== '' ? (string) $course_type_label : __('Not configured', 'tfp-dashboard');
$course_cover_id = get_post_thumbnail_id($course_id);
$course_cover = $course_cover_id ? wp_get_attachment_image_url($course_cover_id, 'thumbnail') : '';
$course_created = get_the_date('M j, Y g:i A', $course_id);
$course_updated = get_the_modified_date('M j, Y g:i A', $course_id);
$course_identity = [
    __('Course Name', 'tfp-dashboard') => $course_heading_title,
    __('Course Type', 'tfp-dashboard') => $course_type_label,
    __('Created', 'tfp-dashboard') => $course_created,
    __('Last Updated', 'tfp-dashboard') => $course_updated,
];

$format_rule_value = static function ($value) {
    $value = is_scalar($value) ? trim((string) $value) : '';
    return $value !== '' ? $value : __('Not configured', 'tfp-dashboard');
};
?>
<section class="tfp-admin-course-rules-page">
    <?php
    $global_overrides = get_post_meta($course_id, 'tfp_course_global_overrides', true);
    $global_overrides = is_array($global_overrides) ? $global_overrides : [];
    $global_permission_roles = isset($global_overrides['permission_roles']) && is_array($global_overrides['permission_roles'])
        ? $global_overrides['permission_roles']
        : ['administrator', 'courseowner'];
    $global_policy_versioning = $global_overrides['policy_versioning'] ?? 'auto';
    $global_retroactive_changes = $global_overrides['retroactive_changes'] ?? 'not_allowed';
    ?>
    <div class="tfp-global-overrides-toolbar">
        <button type="button" class="tfp-global-overrides-trigger" data-global-overrides-open aria-haspopup="dialog" aria-controls="tfp-global-overrides-modal">
            <?php esc_html_e('Global Overrides & Permissions', 'tfp-dashboard'); ?>
            <span aria-hidden="true">›</span>
        </button>
    </div>
    <form method="post" id="tfp-course-rules-form" class="tfp-admin-course-rules-form">
        <?php wp_nonce_field('tfp_save_course_rules_' . $course_id); ?>

        <div class="tfp-admin-course-rules-grid">
            <article class="tfp-admin-panel tfp-admin-course-rules-card tfp-rules-identity">
                <div class="tfp-admin-course-rules-card-heading">
                    <div><h3><?php esc_html_e('Course Identity', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('Name and basic metadata for this course template.', 'tfp-dashboard'); ?></p></div>
                    <button type="button" class="tfp-admin-course-rules-edit" data-course-identity-open aria-haspopup="dialog" aria-controls="tfp-course-identity-modal"><img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Edit_Pencil_Line_01.png" alt="" aria-hidden="true"><span><?php esc_html_e('Edit', 'tfp-dashboard'); ?></span></button>
                </div>
                <div class="tfp-admin-course-rules-identity">
                    <?php if ($course_cover) : ?><img src="<?php echo esc_url($course_cover); ?>" alt=""><?php endif; ?>
                    <div><strong><?php echo esc_html($course_heading_title); ?></strong><small><?php echo esc_html(get_post_meta($course_id, 'tfp_course_cover_dimensions', true) ?: ''); ?></small></div>
                </div>
                <dl class="tfp-admin-course-rules-rows">
                    <?php foreach ($course_identity as $label => $value) : ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php endforeach; ?>
                </dl>
            </article>

            <?php foreach ($rules_sections as $section_key => $section) : ?>
                <article class="tfp-admin-panel tfp-admin-course-rules-card tfp-rules-<?php echo esc_attr($section_key); ?>">
                    <div class="tfp-admin-course-rules-card-heading">
                        <div><h3><?php echo esc_html($section['title']); ?></h3><p><?php echo esc_html($section['description']); ?></p></div>
                        <button type="button" class="tfp-admin-course-rules-edit" <?php
                            if ($section_key === 'facilitation') {
                                echo 'data-facilitation-open aria-haspopup="dialog" aria-controls="tfp-facilitation-rules-modal"';
                            } elseif ($section_key === 'assessments') {
                                echo 'data-assessments-open aria-haspopup="dialog" aria-controls="tfp-assessments-modal"';
                            } elseif ($section_key === 'grading') {
                                echo 'data-grading-open aria-haspopup="dialog" aria-controls="tfp-grading-modal"';
                            } else {
                                echo 'data-rules-edit aria-expanded="false"';
                            }
                        ?>><img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Edit_Pencil_Line_01.png" alt="" aria-hidden="true"><span><?php esc_html_e('Edit', 'tfp-dashboard'); ?></span></button>
                    </div>
                    <dl class="tfp-admin-course-rules-rows">
                        <?php foreach ($section['fields'] as $field_key => $label) :
                            $value = $rules_settings[$field_key] ?? '';
                        ?>
                            <div>
                                <dt><?php echo esc_html($label); ?></dt>
                                <dd>
                                    <span data-rules-value><?php echo esc_html($format_rule_value($value)); ?></span>
                                    <input type="text" name="tfp_course_rules[<?php echo esc_attr($field_key); ?>]" value="<?php echo esc_attr(is_scalar($value) ? (string) $value : ''); ?>" data-rules-input hidden>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </form>
    <div class="tfp-course-identity-modal" id="tfp-course-identity-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-course-identity-title" hidden>
        <div class="tfp-course-identity-modal-backdrop" data-course-identity-close></div>
        <div class="tfp-course-identity-modal-panel">
            <div class="tfp-course-identity-modal-heading">
                <div><h2 id="tfp-course-identity-title"><?php esc_html_e('Edit Course Identity', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Name and basic metadata for this course template.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-identity-close" data-course-identity-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <div class="tfp-course-identity-field tfp-course-identity-image-field">
                <label><?php esc_html_e('Course Image', 'tfp-dashboard'); ?></label>
                <small><?php esc_html_e('210 × 210px | Supported formats: JPG, PNG', 'tfp-dashboard'); ?></small>
                <div class="tfp-course-identity-upload">
                    <img data-course-identity-preview src="<?php echo esc_url($course_cover ?: ''); ?>" alt="" <?php echo $course_cover ? '' : 'hidden'; ?>>
                    <span data-course-identity-upload-empty <?php echo $course_cover ? 'hidden' : ''; ?>>◎<small><?php esc_html_e('Drag and drop your image here', 'tfp-dashboard'); ?></small><small><?php esc_html_e('or', 'tfp-dashboard'); ?></small></span>
                    <button type="button" class="tfp-admin-course-rules-save" data-course-identity-upload><?php esc_html_e('＋ Upload Image', 'tfp-dashboard'); ?></button>
                    <input type="hidden" name="tfp_course_identity_image_id" value="<?php echo esc_attr($course_cover_id); ?>" data-course-identity-image-id>
                </div>
            </div>
            <div class="tfp-course-identity-field">
                <label for="tfp-course-identity-name"><?php esc_html_e('Course Name', 'tfp-dashboard'); ?></label>
                <input id="tfp-course-identity-name" type="text" name="tfp_course_identity_name" value="<?php echo esc_attr($course_heading_title); ?>" required>
            </div>
            <div class="tfp-course-identity-field">
                <label for="tfp-course-identity-type"><?php esc_html_e('Course Type', 'tfp-dashboard'); ?></label>
                <select id="tfp-course-identity-type" name="tfp_course_identity_type">
                    <option value=""><?php esc_html_e('Dropdown option', 'tfp-dashboard'); ?></option>
                    <?php foreach (['Foundation', 'Discipleship', 'Devotional'] as $identity_type_option) : ?>
                        <option value="<?php echo esc_attr($identity_type_option); ?>" <?php selected($course_type_label, $identity_type_option); ?>><?php echo esc_html($identity_type_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tfp-course-identity-field">
                <label for="tfp-course-identity-description"><?php esc_html_e('Short Description', 'tfp-dashboard'); ?></label>
                <input id="tfp-course-identity-description" type="text" name="tfp_course_identity_description" value="<?php echo esc_attr(get_post_field('post_excerpt', $course_id)); ?>" placeholder="<?php esc_attr_e('One-line summary', 'tfp-dashboard'); ?>">
            </div>
            <div class="tfp-course-identity-modal-actions">
                <button type="submit" name="tfp_save_course_identity" value="1" class="tfp-admin-course-rules-save"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-course-identity-close><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>
    <div class="tfp-course-rules-modal" id="tfp-course-progression-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-course-progression-title" hidden>
        <div class="tfp-course-rules-modal-backdrop" data-progression-close></div>
        <div class="tfp-course-rules-modal-panel">
            <div class="tfp-course-rules-modal-heading">
                <div><h2 id="tfp-course-progression-title"><?php esc_html_e('Edit Progression Model', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Define how learners unlock the next section/week.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-rules-modal-close" data-progression-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <div class="tfp-course-rules-choice-group">
                <strong><?php esc_html_e('Course Mode', 'tfp-dashboard'); ?></strong>
                <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[course_mode]" value="Facilitation-Based" <?php checked(($rules_settings['course_mode'] ?? 'Facilitation-Based'), 'Facilitation-Based'); ?>> <span><?php esc_html_e('Facilitation-Based', 'tfp-dashboard'); ?></span></label>
                <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[course_mode]" value="Self-Paced" <?php checked(($rules_settings['course_mode'] ?? ''), 'Self-Paced'); ?>> <span><?php esc_html_e('Self-Paced', 'tfp-dashboard'); ?></span></label>
            </div>
            <div class="tfp-course-rules-choice-group">
                <strong><?php esc_html_e('Unlock Rule', 'tfp-dashboard'); ?></strong>
                <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[unlock_rule]" value="Module Completion/Pass" <?php checked(($rules_settings['unlock_rule'] ?? ''), 'Module Completion/Pass'); ?>> <span><?php esc_html_e('Module Completion/Pass', 'tfp-dashboard'); ?></span></label>
                <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[unlock_rule]" value="Facilitator Close" <?php checked(($rules_settings['unlock_rule'] ?? 'Facilitator Close'), 'Facilitator Close'); ?>> <span><?php esc_html_e('Facilitator Close', 'tfp-dashboard'); ?></span></label>
            </div>
            <div class="tfp-course-rules-field">
                <label for="tfp-course-late-work"><?php esc_html_e('Late Work Policy', 'tfp-dashboard'); ?></label>
                <select form="tfp-course-rules-form" id="tfp-course-late-work" name="tfp_course_rules[late_work]">
                    <option value="Allowed" <?php selected(($rules_settings['late_work'] ?? 'Allowed'), 'Allowed'); ?>><?php esc_html_e('Allowed', 'tfp-dashboard'); ?></option>
                    <option value="Allowed with penalty" <?php selected(($rules_settings['late_work'] ?? ''), 'Allowed with penalty'); ?>><?php esc_html_e('Allowed with penalty', 'tfp-dashboard'); ?></option>
                    <option value="Not allowed" <?php selected(($rules_settings['late_work'] ?? ''), 'Not allowed'); ?>><?php esc_html_e('Not allowed', 'tfp-dashboard'); ?></option>
                </select>
            </div>
            <div class="tfp-course-rules-field">
                <label for="tfp-course-late-grace"><?php esc_html_e('Late Work Grace Period', 'tfp-dashboard'); ?></label>
                <select form="tfp-course-rules-form" id="tfp-course-late-grace" name="tfp_course_rules[late_work_grace_period]">
                    <option value="3 Days" <?php selected(($rules_settings['late_work_grace_period'] ?? '3 Days'), '3 Days'); ?>><?php esc_html_e('3 Days', 'tfp-dashboard'); ?></option>
                    <option value="5 Days" <?php selected(($rules_settings['late_work_grace_period'] ?? ''), '5 Days'); ?>><?php esc_html_e('5 Days', 'tfp-dashboard'); ?></option>
                </select>
            </div>
            <div class="tfp-course-rules-toggle-field">
                <strong><?php esc_html_e('Allow Facilitator Overrides', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[allow_facilitator_overrides]" value="On" <?php checked(($rules_settings['allow_facilitator_overrides'] ?? 'On'), 'On'); ?>><span></span></label><small><?php esc_html_e('On', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-course-rules-toggle-field">
                <strong><?php esc_html_e('Auto-unlock on Close Section', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[auto_unlock_on_close_section]" value="On" <?php checked(($rules_settings['auto_unlock_on_close_section'] ?? 'On'), 'On'); ?>><span></span></label><small><?php esc_html_e('On', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-course-rules-modal-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-progression-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>

    <div class="tfp-course-learning-flow-modal" id="tfp-course-learning-flow-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-course-learning-flow-title" hidden>
        <div class="tfp-course-learning-flow-backdrop" data-learning-flow-close></div>
        <div class="tfp-course-learning-flow-panel">
            <div class="tfp-course-learning-flow-heading">
                <div><h2 id="tfp-course-learning-flow-title"><?php esc_html_e('Edit Learning Flow', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Default step order within each module.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-learning-flow-close" data-learning-flow-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <?php
            $learning_steps = ['Video', 'Reading', 'Homework', 'Quiz', 'Meeting/Review'];
            $saved_step_order = isset($rules_settings['step_order']) && is_string($rules_settings['step_order']) && $rules_settings['step_order'] !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $rules_settings['step_order']))))
                : $learning_steps;
            $saved_step_order = array_values(array_intersect($saved_step_order, $learning_steps));
            foreach ($learning_steps as $learning_step) {
                if (!in_array($learning_step, $saved_step_order, true)) $saved_step_order[] = $learning_step;
            }
            ?>
            <div class="tfp-course-learning-flow-section">
                <strong><?php esc_html_e('Step Order', 'tfp-dashboard'); ?></strong>
                <p class="tfp-course-learning-flow-hint"><?php esc_html_e('Drag and drop to change the default order.', 'tfp-dashboard'); ?></p>
                <ol class="tfp-course-learning-flow-sortable" data-learning-flow-sortable>
                    <?php foreach ($saved_step_order as $learning_step) : ?>
                        <li class="tfp-course-learning-flow-step" draggable="true" data-step="<?php echo esc_attr($learning_step); ?>">
                            <span class="tfp-course-learning-flow-grip" aria-hidden="true">⠿</span>
                            <span><?php echo esc_html($learning_step); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <input type="hidden" form="tfp-course-rules-form" name="tfp_course_rules[step_order]" value="<?php echo esc_attr(implode(',', $saved_step_order)); ?>" data-learning-flow-order>
            </div>
            <div class="tfp-course-learning-flow-section tfp-course-learning-flow-meeting">
                <label for="tfp-course-meeting-step"><?php esc_html_e('Meeting Step', 'tfp-dashboard'); ?></label>
                <select form="tfp-course-rules-form" id="tfp-course-meeting-step" name="tfp_course_rules[meeting_step]">
                    <option value="Required" <?php selected(($rules_settings['meeting_step'] ?? 'Required'), 'Required'); ?>><?php esc_html_e('Required', 'tfp-dashboard'); ?></option>
                    <option value="Optional" <?php selected(($rules_settings['meeting_step'] ?? ''), 'Optional'); ?>><?php esc_html_e('Optional', 'tfp-dashboard'); ?></option>
                    <option value="Not used" <?php selected(($rules_settings['meeting_step'] ?? ''), 'Not used'); ?>><?php esc_html_e('Not used', 'tfp-dashboard'); ?></option>
                </select>
            </div>
            <div class="tfp-course-learning-flow-toggle">
                <strong><?php esc_html_e('Allow per-week override', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch">
                    <input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[allow_per_week_override]" value="On" <?php checked(($rules_settings['allow_per_week_override'] ?? ''), 'On'); ?>>
                    <span></span>
                </label>
                <small><?php echo (($rules_settings['allow_per_week_override'] ?? '') === 'On') ? esc_html__('On', 'tfp-dashboard') : esc_html__('Off', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-course-learning-flow-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-learning-flow-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>
    <div class="tfp-course-completion-modal" id="tfp-course-completion-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-course-completion-title" hidden>
        <div class="tfp-course-completion-backdrop" data-completion-close></div>
        <div class="tfp-course-completion-panel">
            <div class="tfp-course-completion-heading">
                <div><h2 id="tfp-course-completion-title"><?php esc_html_e('Edit Completion Requirements', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Define what “complete” means for each step.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-completion-close" data-completion-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>

            <div class="tfp-course-completion-field tfp-course-completion-video">
                <label for="tfp-course-video-completion"><?php esc_html_e('Video Completion %', 'tfp-dashboard'); ?></label>
                <input form="tfp-course-rules-form" id="tfp-course-video-completion" type="number" min="1" max="100" name="tfp_course_rules[video_completion_percent]" value="<?php echo esc_attr($rules_settings['video_completion_percent'] ?? '100'); ?>">
            </div>

            <div class="tfp-course-completion-field tfp-course-completion-reading">
                <label for="tfp-course-reading-completion"><?php esc_html_e('Reading', 'tfp-dashboard'); ?></label>
                <select form="tfp-course-rules-form" id="tfp-course-reading-completion" name="tfp_course_rules[reading_completion]">
                    <option value="Complete all assigned readings" <?php selected(($rules_settings['reading_completion'] ?? 'Complete all assigned readings'), 'Complete all assigned readings'); ?>><?php esc_html_e('Complete all assigned readings', 'tfp-dashboard'); ?></option>
                    <option value="Mark as read" <?php selected(($rules_settings['reading_completion'] ?? ''), 'Mark as read'); ?>><?php esc_html_e('Mark as read', 'tfp-dashboard'); ?></option>
                </select>
            </div>

            <div class="tfp-course-completion-field tfp-course-completion-homework">
                <strong><?php esc_html_e('Homework', 'tfp-dashboard'); ?></strong>
                <?php $homework_completion = $rules_settings['homework_completion'] ?? 'Submit required'; ?>
                <?php foreach (['Submit required', 'Submit + Pass', 'Submit + Reviewed'] as $homework_option) : ?>
                    <label class="tfp-course-completion-choice"><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[homework_completion]" value="<?php echo esc_attr($homework_option); ?>" <?php checked($homework_completion, $homework_option); ?>><span><?php echo esc_html($homework_option); ?></span></label>
                <?php endforeach; ?>
            </div>

            <div class="tfp-course-completion-assessment">
                <div class="tfp-course-completion-toggle-row">
                    <div class="tfp-course-completion-toggle-copy">
                        <strong><?php esc_html_e('Quiz', 'tfp-dashboard'); ?></strong>
                        <span><?php esc_html_e('On | Pass %', 'tfp-dashboard'); ?></span>
                    </div>
                    <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[quiz_completion]" value="On" <?php checked(($rules_settings['quiz_completion'] ?? 'On'), 'On'); ?>><span></span></label>
                    <input form="tfp-course-rules-form" class="tfp-course-completion-percent" type="number" min="0" max="100" aria-label="<?php esc_attr_e('Quiz pass percentage', 'tfp-dashboard'); ?>" name="tfp_course_rules[quiz_pass_percent]" value="<?php echo esc_attr($rules_settings['quiz_pass_percent'] ?? '80'); ?>">
                    <span class="tfp-course-completion-default"><?php esc_html_e('default: ON / 80%', 'tfp-dashboard'); ?></span>
                </div>
                <div class="tfp-course-completion-toggle-row">
                    <div class="tfp-course-completion-toggle-copy">
                        <strong><?php esc_html_e('Test', 'tfp-dashboard'); ?></strong>
                        <span><?php esc_html_e('On | Pass %', 'tfp-dashboard'); ?></span>
                    </div>
                    <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[test_completion]" value="On" <?php checked(($rules_settings['test_completion'] ?? 'On'), 'On'); ?>><span></span></label>
                    <input form="tfp-course-rules-form" class="tfp-course-completion-percent" type="number" min="0" max="100" aria-label="<?php esc_attr_e('Test pass percentage', 'tfp-dashboard'); ?>" name="tfp_course_rules[test_pass_percent]" value="<?php echo esc_attr($rules_settings['test_pass_percent'] ?? '70'); ?>">
                    <span class="tfp-course-completion-default"><?php esc_html_e('default: ON / 70%', 'tfp-dashboard'); ?></span>
                </div>
                <div class="tfp-course-completion-toggle-row tfp-course-completion-meeting">
                    <div class="tfp-course-completion-toggle-copy"><strong><?php esc_html_e('Meeting/Review', 'tfp-dashboard'); ?></strong></div>
                    <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[meeting_completion]" value="On" <?php checked(($rules_settings['meeting_completion'] ?? 'On'), 'On'); ?>><span></span></label>
                    <span class="tfp-course-completion-default"><?php esc_html_e('On', 'tfp-dashboard'); ?></span>
                </div>
            </div>

            <div class="tfp-course-completion-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-completion-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>




    <div class="tfp-course-rules-modal tfp-grading-modal" id="tfp-grading-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-grading-title" hidden>
        <div class="tfp-course-rules-modal-backdrop" data-grading-close></div>
        <div class="tfp-course-rules-modal-panel tfp-grading-panel">
            <div class="tfp-course-rules-modal-heading">
                <div><h2 id="tfp-grading-title"><?php esc_html_e('Edit Grading', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Module total = 100 points. Allocate points to graded parts, set assessment defaults, and define pass & certification rules.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-rules-modal-close" data-grading-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <section class="tfp-grading-group">
                <h3><?php esc_html_e('Graded Parts Allocation', 'tfp-dashboard'); ?></h3><small>Allocate points (must total 100)</small>
                <?php foreach (['homework' => ['Homework','On',60], 'quiz' => ['Quiz','Off',0], 'test' => ['Test','On',40]] as $part => $defaults) : ?>
                    <div class="tfp-grading-allocation-row">
                        <strong><?php echo esc_html($defaults[0]); ?></strong>
                        <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[grading_<?php echo esc_attr($part); ?>_enabled]" value="On" <?php checked(($rules_settings['grading_' . $part . '_enabled'] ?? $defaults[1]), 'On'); ?>><span></span></label>
                        <small><?php echo esc_html(($rules_settings['grading_' . $part . '_enabled'] ?? $defaults[1]) === 'On' ? 'On' : 'Off'); ?></small>
                        <input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[grading_<?php echo esc_attr($part); ?>_points]" value="<?php echo esc_attr($rules_settings['grading_' . $part . '_points'] ?? $defaults[2]); ?>" aria-label="<?php echo esc_attr($defaults[0] . ' points'); ?>">
                    </div>
                <?php endforeach; ?>
                <p class="tfp-grading-allocation-total">Allocated: <strong data-grading-total>100</strong>/100 · Remaining: <strong data-grading-remaining>0</strong></p>
            </section>
            <section class="tfp-grading-group">
                <h3><?php esc_html_e('Overall pass & Certification', 'tfp-dashboard'); ?></h3>
                <small>All modules count equally (course final = average of module %).</small>
                <label class="tfp-grading-inline-field"><span>Passing threshold %</span><input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[grading_pass_threshold]" value="<?php echo esc_attr($rules_settings['grading_pass_threshold'] ?? '75'); ?>"></label>
                <?php foreach (['Require all modules completed','Require facilitator close','Require no unresolved flags'] as $rule) : ?>
                    <label class="tfp-grading-choice"><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[grading_pass_rule]" value="<?php echo esc_attr($rule); ?>" <?php checked(($rules_settings['grading_pass_rule'] ?? 'Require all modules completed'), $rule); ?>><span><?php echo esc_html($rule); ?></span></label>
                <?php endforeach; ?>
            </section>
            <section class="tfp-grading-group">
                <h3>Award on pass</h3>
                <label class="tfp-grading-choice"><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[grading_award]" value="Certificate of Completion" <?php checked(($rules_settings['grading_award'] ?? 'Certificate of Completion'), 'Certificate of Completion'); ?>><span>Certificate of Completion</span></label>
            </section>
            <section class="tfp-grading-group">
                <h3>Transcript entry</h3>
                <?php foreach (['Record overall','Record module + overall'] as $entry) : ?>
                    <label class="tfp-grading-choice"><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[grading_transcript_entry]" value="<?php echo esc_attr($entry); ?>" <?php checked(($rules_settings['grading_transcript_entry'] ?? 'Record module + overall'), $entry); ?>><span><?php echo esc_html($entry); ?></span></label>
                <?php endforeach; ?>
            </section>
            <section class="tfp-grading-group">
                <h3>Ordination</h3>
                <?php foreach (['Admin approval','None'] as $ordination) : ?>
                    <label class="tfp-grading-choice"><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[grading_ordination]" value="<?php echo esc_attr($ordination); ?>" <?php checked(($rules_settings['grading_ordination'] ?? 'Admin approval'), $ordination); ?>><span><?php echo esc_html($ordination); ?></span></label>
                <?php endforeach; ?>
            </section>
            <section class="tfp-grading-group">
                <h3>Component minimum</h3>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[grading_component_minimum_enabled]" value="On" <?php checked(($rules_settings['grading_component_minimum_enabled'] ?? 'On'), 'On'); ?>><span></span></label>
                <small>On</small>
                <strong class="tfp-grading-mode">Mode: <?php echo esc_html($rules_settings['grading_component_minimum_mode'] ?? 'Course-average'); ?></strong>
                <small>Enforcement (implicit): Block certification if component minimums unmet.</small>
                <label class="tfp-grading-inline-field"><span>Homework minimum %</span><input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[grading_homework_minimum]" value="<?php echo esc_attr($rules_settings['grading_homework_minimum'] ?? '60'); ?>"></label>
                <label class="tfp-grading-inline-field"><span>Test minimum %</span><input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[grading_test_minimum]" value="<?php echo esc_attr($rules_settings['grading_test_minimum'] ?? '50'); ?>"></label>
            </section>
            <section class="tfp-grading-group tfp-grading-last-group">
                <h3>Allow Facilitator Overrides</h3>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[grading_facilitator_overrides]" value="On" <?php checked(($rules_settings['grading_facilitator_overrides'] ?? 'On'), 'On'); ?>><span></span></label>
                <small>On</small>
            </section>
            <div class="tfp-course-rules-modal-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-grading-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>

    <div class="tfp-course-rules-modal tfp-assessments-modal" id="tfp-assessments-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-assessments-title" hidden>
        <div class="tfp-course-rules-modal-backdrop" data-assessments-close></div>
        <div class="tfp-course-rules-modal-panel tfp-assessments-panel">
            <div class="tfp-course-rules-modal-heading">
                <div><h2 id="tfp-assessments-title"><?php esc_html_e('Edit Assessments', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Configure quiz & test defaults. Single-attempt assessments.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-rules-modal-close" data-assessments-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <section class="tfp-assessments-group">
                <h3><?php esc_html_e('Quiz Defaults', 'tfp-dashboard'); ?></h3>
                <label><span><?php esc_html_e('Questions per attempt', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="1" name="tfp_course_rules[quiz_questions_per_attempt]" value="<?php echo esc_attr($rules_settings['quiz_questions_per_attempt'] ?? '10'); ?>"></label>
                <label><span><?php esc_html_e('Attempts allowed', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="1" name="tfp_course_rules[quiz_attempts_allowed]" value="<?php echo esc_attr($rules_settings['quiz_attempts_allowed'] ?? '3'); ?>"></label>
                <label><span><?php esc_html_e('Passing score %', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[quiz_passing_score]" value="<?php echo esc_attr($rules_settings['quiz_passing_score'] ?? '80'); ?>"></label>
            </section>
            <section class="tfp-assessments-group">
                <h3><?php esc_html_e('Test Defaults', 'tfp-dashboard'); ?></h3>
                <label><span><?php esc_html_e('Questions per attempt', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="1" name="tfp_course_rules[test_questions_per_attempt]" value="<?php echo esc_attr($rules_settings['test_questions_per_attempt'] ?? '10'); ?>"></label>
                <label><span><?php esc_html_e('Attempts allowed', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="1" name="tfp_course_rules[test_attempts_allowed]" value="<?php echo esc_attr($rules_settings['test_attempts_allowed'] ?? '2'); ?>"></label>
                <label><span><?php esc_html_e('Passing score %', 'tfp-dashboard'); ?></span><input form="tfp-course-rules-form" type="number" min="0" max="100" name="tfp_course_rules[test_passing_score]" value="<?php echo esc_attr($rules_settings['test_passing_score'] ?? '70'); ?>"></label>
            </section>
            <section class="tfp-assessments-group tfp-assessments-randomization">
                <h3><?php esc_html_e('Randomization policy', 'tfp-dashboard'); ?></h3>
                <?php foreach (['No repeats until questions exhausted', 'Avoid recent questions', 'Prefer new', 'Allow repeats'] as $randomization_option) : ?>
                    <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[assessment_randomization]" value="<?php echo esc_attr($randomization_option); ?>" <?php checked(($rules_settings['assessment_randomization'] ?? 'No repeats until questions exhausted'), $randomization_option); ?>><span><?php echo esc_html($randomization_option); ?></span></label>
                <?php endforeach; ?>
            </section>
            <div class="tfp-course-rules-modal-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-assessments-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>

    <div class="tfp-course-rules-modal tfp-facilitation-rules-modal" id="tfp-facilitation-rules-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-facilitation-rules-title" hidden>
        <div class="tfp-course-rules-modal-backdrop" data-facilitation-close></div>
        <div class="tfp-course-rules-modal-panel tfp-facilitation-rules-panel">
            <div class="tfp-course-rules-modal-heading">
                <div><h2 id="tfp-facilitation-rules-title"><?php esc_html_e('Edit Facilitation Rules', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Meeting requirements, policies, and close-out checklist.', 'tfp-dashboard'); ?></p></div>
                <button type="button" class="tfp-course-rules-modal-close" data-facilitation-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
            </div>
            <div class="tfp-facilitation-field">
                <strong><?php esc_html_e('Meeting Required', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[meeting_required]" value="On" <?php checked(($rules_settings['meeting_required'] ?? 'On'), 'On'); ?>><span></span></label><small>On</small>
                <p><?php esc_html_e('If On, Learning Flow must include a Meeting step.', 'tfp-dashboard'); ?></p>
            </div>
            <div class="tfp-facilitation-field">
                <strong><?php esc_html_e('Track attendance', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[track_attendance]" value="On" <?php checked(($rules_settings['track_attendance'] ?? 'On'), 'On'); ?>><span></span></label><small>On | Allowed misses</small>
                <input form="tfp-course-rules-form" class="tfp-facilitation-number" type="number" min="0" max="99" name="tfp_course_rules[allowed_misses]" value="<?php echo esc_attr($rules_settings['allowed_misses'] ?? '2'); ?>" aria-label="Allowed misses">
            </div>
            <div class="tfp-facilitation-field">
                <strong><?php esc_html_e('Count excused absences', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[count_excused_absences]" value="On" <?php checked(($rules_settings['count_excused_absences'] ?? 'On'), 'On'); ?>><span></span></label><small>On</small>
                <p><?php esc_html_e('Learners may miss up to scheduled meetings before being flagged.', 'tfp-dashboard'); ?></p>
            </div>
            <div class="tfp-facilitation-field tfp-facilitation-checklist">
                <strong><?php esc_html_e('Close-outs / Required checks', 'tfp-dashboard'); ?></strong>
                <p><?php esc_html_e('Items facilitators must verify before closing a week.', 'tfp-dashboard'); ?></p>
                <?php $required_checks = array_filter(array_map('trim', explode(',', (string) ($rules_settings['required_checks'] ?? 'Attendance,Homework,Quiz,Test,Exercise,Notes')))); ?>
                <?php foreach (['Attendance', 'Homework', 'Quiz', 'Test', 'Exercise', 'Notes'] as $check_item) : ?>
                    <label><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[required_checks][]" value="<?php echo esc_attr($check_item); ?>" <?php checked(in_array($check_item, $required_checks, true)); ?>><span><?php echo esc_html($check_item); ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="tfp-facilitation-field">
                <strong><?php esc_html_e('On close action', 'tfp-dashboard'); ?></strong>
                <label class="tfp-course-rules-switch"><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[on_close_action]" value="On" <?php checked(($rules_settings['on_close_action'] ?? 'On'), 'On'); ?>><span></span></label><small>On</small>
                <p><?php esc_html_e('When enabled, learners who have completed required items are unlocked immediately when the week is closed.', 'tfp-dashboard'); ?></p>
            </div>
            <div class="tfp-facilitation-field tfp-facilitation-radio-group">
                <strong><?php esc_html_e('Enforcement level', 'tfp-dashboard'); ?></strong>
                <?php foreach (['Strict', 'Warnings allowed'] as $level) : ?>
                    <label><input form="tfp-course-rules-form" type="radio" name="tfp_course_rules[enforcement_level]" value="<?php echo esc_attr($level); ?>" <?php checked(($rules_settings['enforcement_level'] ?? 'Strict'), $level); ?>><span><?php echo esc_html($level === 'Strict' ? 'Strict (block progression)' : 'Warnings allowed (flag only)'); ?></span></label>
                <?php endforeach; ?>
                <p><?php esc_html_e('Strict prevents progression until resolved. Warnings flag for follow-up but do not block.', 'tfp-dashboard'); ?></p>
            </div>
            <div class="tfp-facilitation-field tfp-facilitation-radio-group tfp-facilitation-recipients">
                <strong><?php esc_html_e('Notification recipients', 'tfp-dashboard'); ?></strong>
                <?php $notification_recipients = array_filter(array_map('trim', explode(',', (string) ($rules_settings['notification_recipients'] ?? 'Facilitator Assigned to Cohort,Admin')))); ?>
                <?php foreach (['Facilitator Assigned to Cohort', 'Admin'] as $recipient) : ?>
                    <label><input form="tfp-course-rules-form" type="checkbox" name="tfp_course_rules[notification_recipients][]" value="<?php echo esc_attr($recipient); ?>" <?php checked(in_array($recipient, $notification_recipients, true)); ?>><span><?php echo esc_html($recipient); ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="tfp-course-rules-modal-actions">
                <button type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1" class="tfp-admin-course-rules-save"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                <button type="button" class="tfp-admin-course-rules-cancel" data-facilitation-close><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
            </div>
        </div>
    </div>

    <div class="tfp-global-overrides-modal" id="tfp-global-overrides-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-global-overrides-title" hidden>
        <div class="tfp-global-overrides-backdrop" data-global-overrides-close></div>
        <div class="tfp-global-overrides-panel">
            <form method="post" class="tfp-global-overrides-form">
                <?php wp_nonce_field('tfp_save_course_rules_' . $course_id); ?>
                <div class="tfp-global-overrides-content">
                    <header class="tfp-global-overrides-heading">
                        <h2 id="tfp-global-overrides-title"><?php esc_html_e('Edit Global Overrides & Permissions', 'tfp-dashboard'); ?></h2>
                        <p><?php esc_html_e('Cohort overrides and who can edit course rules.', 'tfp-dashboard'); ?></p>
                    </header>

                    <section class="tfp-global-overrides-group tfp-global-overrides-cohort">
                        <h3><?php esc_html_e('Cohort overrides', 'tfp-dashboard'); ?></h3>
                        <p class="tfp-global-overrides-muted"><?php esc_html_e('Disabled by policy (cannot be enabled)', 'tfp-dashboard'); ?></p>
                        <p class="tfp-global-overrides-muted"><?php esc_html_e('Cohort overrides are disallowed by policy. Core gating and certification rules can only be changed by Admins', 'tfp-dashboard'); ?></p>
                    </section>

                    <section class="tfp-global-overrides-group tfp-global-overrides-locked">
                        <h3><?php esc_html_e('Locked items (non-overrideable)', 'tfp-dashboard'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Passing threshold (course-level passing %)', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Component minimum enforcement (whether component minimums are required)', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Certification gating rules (require facilitator close; require all modules completed)', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('Ordination rating rules (require admin approval; require all modules completed, passing grade)', 'tfp-dashboard'); ?></li>
                        </ul>
                    </section>

                    <section class="tfp-global-overrides-group tfp-global-overrides-permissions">
                        <h3><?php esc_html_e('Permission roles', 'tfp-dashboard'); ?></h3>
                        <label class="tfp-global-overrides-check">
                            <input type="checkbox" name="tfp_global_permission_roles[]" value="administrator" <?php checked(in_array('administrator', $global_permission_roles, true)); ?>>
                            <span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span>
                        </label>
                        <label class="tfp-global-overrides-check">
                            <input type="checkbox" name="tfp_global_permission_roles[]" value="courseowner" <?php checked(in_array('courseowner', $global_permission_roles, true)); ?>>
                            <span><?php esc_html_e('CourseOwner', 'tfp-dashboard'); ?></span>
                        </label>
                        <p class="tfp-global-overrides-muted"><?php esc_html_e('Only permitted roles may edit course-level rules.', 'tfp-dashboard'); ?></p>
                    </section>

                    <section class="tfp-global-overrides-group tfp-global-overrides-radio-group">
                        <h3><?php esc_html_e('Policy versioning', 'tfp-dashboard'); ?></h3>
                        <label><input type="radio" name="tfp_global_policy_versioning" value="auto" <?php checked($global_policy_versioning, 'auto'); ?>><span><?php esc_html_e('Auto-create new version on change (recommended) — default selected', 'tfp-dashboard'); ?></span></label>
                        <label><input type="radio" name="tfp_global_policy_versioning" value="explicit" <?php checked($global_policy_versioning, 'explicit'); ?>><span><?php esc_html_e('Require explicit version creation', 'tfp-dashboard'); ?></span></label>
                    </section>

                    <section class="tfp-global-overrides-group tfp-global-overrides-radio-group">
                        <h3><?php esc_html_e('Retroactive changes', 'tfp-dashboard'); ?></h3>
                        <label><input type="radio" name="tfp_global_retroactive_changes" value="not_allowed" <?php checked($global_retroactive_changes, 'not_allowed'); ?>><span><?php esc_html_e('Not allowed — default selected', 'tfp-dashboard'); ?></span></label>
                        <label><input type="radio" name="tfp_global_retroactive_changes" value="admin_confirmation" <?php checked($global_retroactive_changes, 'admin_confirmation'); ?>><span><?php esc_html_e('Allowed with admin confirmation', 'tfp-dashboard'); ?></span></label>
                    </section>

                    <section class="tfp-global-overrides-validation">
                        <h3><?php esc_html_e('Validation rules', 'tfp-dashboard'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Backend must reject any attempt to change Locked items from any cohort context.', 'tfp-dashboard'); ?></li>
                            <li><?php esc_html_e('At least one Permission role must be selected.', 'tfp-dashboard'); ?></li>
                        </ul>
                    </section>

                    <div class="tfp-global-overrides-actions">
                        <button type="submit" name="tfp_save_global_overrides" value="1" class="tfp-global-overrides-save">
                            <span class="tfp-global-overrides-save-icon" aria-hidden="true">▣</span><?php esc_html_e('Save', 'tfp-dashboard'); ?>
                        </button>
                        <button type="button" class="tfp-global-overrides-cancel" data-global-overrides-close>
                            <span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</section>
<script>
(function () {
    var root = document.querySelector('.tfp-admin-course-rules-page');
    if (!root) return;
    var globalOverridesModal = root.querySelector('#tfp-global-overrides-modal');
    var globalOverridesOpen = root.querySelector('[data-global-overrides-open]');
    if (globalOverridesModal && globalOverridesOpen) {
        var closeGlobalOverrides = function () {
            globalOverridesModal.hidden = true;
            document.body.classList.remove('tfp-global-overrides-open');
        };
        globalOverridesOpen.addEventListener('click', function () {
            globalOverridesModal.hidden = false;
            document.body.classList.add('tfp-global-overrides-open');
            var firstControl = globalOverridesModal.querySelector('input');
            if (firstControl) firstControl.focus();
        });
        globalOverridesModal.querySelectorAll('[data-global-overrides-close]').forEach(function (element) {
            element.addEventListener('click', closeGlobalOverrides);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !globalOverridesModal.hidden) closeGlobalOverrides();
        });
        var globalOverridesForm = globalOverridesModal.querySelector('form');
        if (globalOverridesForm) {
            globalOverridesForm.addEventListener('submit', function (event) {
                var selectedRoles = globalOverridesForm.querySelectorAll('input[name="tfp_global_permission_roles[]"]:checked');
                if (!selectedRoles.length) {
                    event.preventDefault();
                    window.alert('Select at least one permission role.');
                    var firstRole = globalOverridesForm.querySelector('input[name="tfp_global_permission_roles[]"]');
                    if (firstRole) firstRole.focus();
                }
            });
        }
    }

    var identityModal = root.querySelector('#tfp-course-identity-modal');
    var identityOpen = root.querySelector('[data-course-identity-open]');
    if (identityModal && identityOpen) {
        var closeIdentity = function () { identityModal.hidden = true; document.body.classList.remove('tfp-course-identity-modal-open'); };
        identityOpen.addEventListener('click', function () {
            identityModal.hidden = false;
            document.body.classList.add('tfp-course-identity-modal-open');
            var nameField = identityModal.querySelector('#tfp-course-identity-name');
            if (nameField) nameField.focus();
        });
        identityModal.querySelectorAll('[data-course-identity-close]').forEach(function (el) { el.addEventListener('click', closeIdentity); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !identityModal.hidden) closeIdentity(); });
        var uploadButton = identityModal.querySelector('[data-course-identity-upload]');
        if (uploadButton) uploadButton.addEventListener('click', function () {
            if (!window.wp || !wp.media) return;
            var frame = wp.media({ title: 'Select Course Image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                identityModal.querySelector('[data-course-identity-image-id]').value = attachment.id;
                var preview = identityModal.querySelector('[data-course-identity-preview]');
                preview.src = attachment.url;
                preview.hidden = false;
                identityModal.querySelector('[data-course-identity-upload-empty]').hidden = true;
            });
            frame.open();
        });
    }



    var gradingModal = root.querySelector('#tfp-grading-modal');
    var gradingOpen = root.querySelector('[data-grading-open]');
    if (gradingModal && gradingOpen) {
        var closeGrading = function () {
            gradingModal.hidden = true;
            document.body.classList.remove('tfp-course-rules-modal-open');
        };
        gradingOpen.addEventListener('click', function () {
            gradingModal.hidden = false;
            document.body.classList.add('tfp-course-rules-modal-open');
            var firstField = gradingModal.querySelector('input, button');
            if (firstField) firstField.focus();
        });
        gradingModal.querySelectorAll('[data-grading-close]').forEach(function (el) {
            el.addEventListener('click', closeGrading);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !gradingModal.hidden) closeGrading();
        });
        var updateGradingAllocation = function () {
            var total = 0;
            gradingModal.querySelectorAll('.tfp-grading-allocation-row').forEach(function (row) {
                var enabled = row.querySelector('input[type="checkbox"]').checked;
                var points = parseInt(row.querySelector('input[type="number"]').value, 10) || 0;
                if (enabled) total += points;
            });
            var totalNode = gradingModal.querySelector('[data-grading-total]');
            var remainingNode = gradingModal.querySelector('[data-grading-remaining]');
            if (totalNode) totalNode.textContent = total;
            if (remainingNode) remainingNode.textContent = 100 - total;
        };
        gradingModal.querySelectorAll('.tfp-grading-allocation-row input').forEach(function (input) {
            input.addEventListener('change', updateGradingAllocation);
            input.addEventListener('input', updateGradingAllocation);
        });
        updateGradingAllocation();
    }

    var assessmentsModal = root.querySelector('#tfp-assessments-modal');
    var assessmentsOpen = root.querySelector('[data-assessments-open]');
    if (assessmentsModal && assessmentsOpen) {
        var closeAssessments = function () {
            assessmentsModal.hidden = true;
            document.body.classList.remove('tfp-course-rules-modal-open');
        };
        assessmentsOpen.addEventListener('click', function () {
            assessmentsModal.hidden = false;
            document.body.classList.add('tfp-course-rules-modal-open');
            var firstField = assessmentsModal.querySelector('input, button');
            if (firstField) firstField.focus();
        });
        assessmentsModal.querySelectorAll('[data-assessments-close]').forEach(function (el) {
            el.addEventListener('click', closeAssessments);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !assessmentsModal.hidden) closeAssessments();
        });
    }

    var facilitationModal = root.querySelector('#tfp-facilitation-rules-modal');
    var facilitationOpen = root.querySelector('[data-facilitation-open]');
    if (facilitationModal && facilitationOpen) {
        var closeFacilitation = function () {
            facilitationModal.hidden = true;
            document.body.classList.remove('tfp-course-rules-modal-open');
        };
        facilitationOpen.addEventListener('click', function () {
            facilitationModal.hidden = false;
            document.body.classList.add('tfp-course-rules-modal-open');
            var firstField = facilitationModal.querySelector('input, button');
            if (firstField) firstField.focus();
        });
        facilitationModal.querySelectorAll('[data-facilitation-close]').forEach(function (el) {
            el.addEventListener('click', closeFacilitation);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !facilitationModal.hidden) closeFacilitation();
        });
    }

    var progressionModal = root.querySelector('#tfp-course-progression-modal');
    var progressionOpen = root.querySelector('.tfp-rules-progression [data-rules-edit]');
    if (progressionModal && progressionOpen) {
        var closeProgression = function () { progressionModal.hidden = true; document.body.classList.remove('tfp-course-rules-modal-open'); };
        progressionOpen.addEventListener('click', function () {
            progressionModal.hidden = false;
            document.body.classList.add('tfp-course-rules-modal-open');
            var firstChoice = progressionModal.querySelector('input[type="radio"]');
            if (firstChoice) firstChoice.focus();
        });
        progressionModal.querySelectorAll('[data-progression-close]').forEach(function (el) { el.addEventListener('click', closeProgression); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !progressionModal.hidden) closeProgression(); });
    }

    // Replace native popup selects with accessible custom dropdowns so option
    // hover and selected states render consistently across browsers/operating systems.
    root.querySelectorAll('[role="dialog"] select').forEach(function (select) {
        if (select.dataset.customized === 'true') return;
        select.dataset.customized = 'true';
        var wrapper = document.createElement('div');
        wrapper.className = 'tfp-course-custom-select';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'tfp-course-custom-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        var list = document.createElement('div');
        list.className = 'tfp-course-custom-select-options';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        Array.prototype.forEach.call(select.options, function (option, index) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'tfp-course-custom-select-option';
            item.textContent = option.textContent;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.dataset.value = option.value;
            item.addEventListener('click', function () {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                close();
                trigger.focus();
            });
            list.appendChild(item);
        });
        function sync() {
            var current = select.options[select.selectedIndex];
            trigger.textContent = current ? current.textContent : '';
            Array.prototype.forEach.call(list.children, function (item) {
                var selected = item.dataset.value === select.value;
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }
        function close() {
            list.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            wrapper.classList.remove('is-open');
        }
        trigger.addEventListener('click', function () {
            var opening = list.hidden;
            list.hidden = !opening;
            trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
            wrapper.classList.toggle('is-open', opening);
        });
        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (list.hidden) { list.hidden = false; trigger.setAttribute('aria-expanded', 'true'); wrapper.classList.add('is-open'); }
                var active = list.querySelector('[aria-selected="true"]') || list.firstElementChild;
                if (active) active.focus();
            }
        });
        list.addEventListener('keydown', function (event) {
            var items = Array.prototype.slice.call(list.children);
            var currentIndex = items.indexOf(document.activeElement);
            if (event.key === 'Escape') { event.preventDefault(); close(); trigger.focus(); }
            else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                var next = event.key === 'ArrowDown' ? Math.min(items.length - 1, currentIndex + 1) : Math.max(0, currentIndex - 1);
                if (items[next]) items[next].focus();
            }
        });
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(list);
        wrapper.appendChild(select);
        select.classList.add('tfp-course-custom-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.addEventListener('change', sync);
        sync();
        document.addEventListener('click', function (event) { if (!wrapper.contains(event.target)) close(); });
    });


    var completionModal = root.querySelector('#tfp-course-completion-modal');
    var completionOpen = root.querySelector('.tfp-rules-completion [data-rules-edit]');
    if (completionModal && completionOpen) {
        var closeCompletion = function () {
            completionModal.hidden = true;
            document.body.classList.remove('tfp-course-completion-open');
        };
        completionOpen.addEventListener('click', function () {
            completionModal.hidden = false;
            document.body.classList.add('tfp-course-completion-open');
            var firstField = completionModal.querySelector('input, button, select');
            if (firstField) firstField.focus();
        });
        completionModal.querySelectorAll('[data-completion-close]').forEach(function (el) {
            el.addEventListener('click', closeCompletion);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !completionModal.hidden) closeCompletion();
        });
    }

    var learningFlowModal = root.querySelector('#tfp-course-learning-flow-modal');
    var learningFlowOpen = root.querySelector('.tfp-rules-learning_flow [data-rules-edit]');
    if (learningFlowModal && learningFlowOpen) {
        var closeLearningFlow = function () {
            learningFlowModal.hidden = true;
            document.body.classList.remove('tfp-course-learning-flow-open');
        };
        learningFlowOpen.addEventListener('click', function () {
            learningFlowModal.hidden = false;
            document.body.classList.add('tfp-course-learning-flow-open');
            var firstStep = learningFlowModal.querySelector('.tfp-course-learning-flow-step');
            if (firstStep) firstStep.focus();
        });
        learningFlowModal.querySelectorAll('[data-learning-flow-close]').forEach(function (el) {
            el.addEventListener('click', closeLearningFlow);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !learningFlowModal.hidden) closeLearningFlow();
        });

        var sortable = learningFlowModal.querySelector('[data-learning-flow-sortable]');
        var orderField = learningFlowModal.querySelector('[data-learning-flow-order]');
        if (sortable && orderField) {
            var draggedStep = null;
            var updateStepOrder = function () {
                orderField.value = Array.prototype.map.call(sortable.querySelectorAll('[data-step]'), function (item) {
                    return item.getAttribute('data-step');
                }).join(',');
            };
            sortable.querySelectorAll('[data-step]').forEach(function (item) {
                item.setAttribute('tabindex', '0');
                item.addEventListener('dragstart', function (event) {
                    draggedStep = item;
                    item.classList.add('is-dragging');
                    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
                });
                item.addEventListener('dragend', function () {
                    item.classList.remove('is-dragging');
                    sortable.querySelectorAll('.is-drop-target').forEach(function (node) { node.classList.remove('is-drop-target'); });
                    draggedStep = null;
                    updateStepOrder();
                });
                item.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    if (draggedStep && draggedStep !== item) item.classList.add('is-drop-target');
                });
                item.addEventListener('dragleave', function () { item.classList.remove('is-drop-target'); });
                item.addEventListener('drop', function (event) {
                    event.preventDefault();
                    item.classList.remove('is-drop-target');
                    if (!draggedStep || draggedStep === item) return;
                    var bounds = item.getBoundingClientRect();
                    var insertAfter = event.clientY > bounds.top + bounds.height / 2;
                    sortable.insertBefore(draggedStep, insertAfter ? item.nextSibling : item);
                    updateStepOrder();
                });
                item.addEventListener('keydown', function (event) {
                    if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
                        event.preventDefault();
                        var target = event.key === 'ArrowUp' ? item.previousElementSibling : item.nextElementSibling;
                        if (target) {
                            sortable.insertBefore(item, event.key === 'ArrowUp' ? target : target.nextSibling);
                            updateStepOrder();
                            item.focus();
                        }
                    }
                });
            });
            updateStepOrder();
        }
    }

    root.querySelectorAll('[data-rules-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('.tfp-admin-course-rules-card');
            if (card && card.classList.contains('tfp-rules-completion')) return;
            var editing = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', editing ? 'true' : 'false');
            button.querySelector('span:last-child').textContent = editing ? 'Done' : 'Edit';
            card.querySelectorAll('[data-rules-input]').forEach(function (input) { input.hidden = !editing; });
            card.querySelectorAll('[data-rules-value]').forEach(function (value) { value.hidden = editing; });
        });
    });
})();
</script>
