<?php
/**
 * Course Rules tab — course-level defaults and progression settings.
 */
if (!defined('ABSPATH')) {
    exit;
}

$rules_meta_key = 'tfp_course_rules_settings';
$rules_settings = get_post_meta($course_id, $rules_meta_key, true);
$rules_settings = is_array($rules_settings) ? $rules_settings : [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfp_save_course_rules'])) {
    check_admin_referer('tfp_save_course_rules_' . $course_id);
    if (current_user_can('edit_post', $course_id)) {
        $submitted = isset($_POST['tfp_course_rules']) && is_array($_POST['tfp_course_rules'])
            ? wp_unslash($_POST['tfp_course_rules'])
            : [];
        $clean = [];
        foreach ($rules_sections as $section_key => $section) {
            foreach ($section['fields'] as $field_key => $label) {
                $raw = $submitted[$field_key] ?? '';
                $clean[$field_key] = is_scalar($raw) ? sanitize_text_field((string) $raw) : '';
            }
        }
        update_post_meta($course_id, $rules_meta_key, $clean);
        $rules_settings = $clean;
        echo '<div class="tfp-admin-course-rules-notice" role="status">' . esc_html__('Course rules saved.', 'tfp-dashboard') . '</div>';
    }
}

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
    <form method="post" id="tfp-course-rules-form" class="tfp-admin-course-rules-form">
        <?php wp_nonce_field('tfp_save_course_rules_' . $course_id); ?>

        <div class="tfp-admin-course-rules-grid">
            <article class="tfp-admin-panel tfp-admin-course-rules-card tfp-rules-identity">
                <div class="tfp-admin-course-rules-card-heading">
                    <div><h3><?php esc_html_e('Course Identity', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('Name and basic metadata for this course template.', 'tfp-dashboard'); ?></p></div>
                    <a href="<?php echo esc_url(get_edit_post_link($course_id, 'raw')); ?>" class="tfp-admin-course-rules-edit">✎ <span><?php esc_html_e('Edit', 'tfp-dashboard'); ?></span></a>
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
                        <button type="button" class="tfp-admin-course-rules-edit" data-rules-edit aria-expanded="false"><span aria-hidden="true">✎</span> <span><?php esc_html_e('Edit', 'tfp-dashboard'); ?></span></button>
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
</section>
<script>
(function () {
    var root = document.querySelector('.tfp-admin-course-rules-page');
    if (!root) return;
    root.querySelectorAll('[data-rules-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('.tfp-admin-course-rules-card');
            var editing = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', editing ? 'true' : 'false');
            button.querySelector('span:last-child').textContent = editing ? 'Done' : 'Edit';
            card.querySelectorAll('[data-rules-input]').forEach(function (input) { input.hidden = !editing; });
            card.querySelectorAll('[data-rules-value]').forEach(function (value) { value.hidden = editing; });
        });
    });
})();
</script>
