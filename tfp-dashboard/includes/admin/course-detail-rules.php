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
    'late_work_grace_period',
    'allow_facilitator_overrides',
    'auto_unlock_on_close_section',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tfp_save_course_rules']) || isset($_POST['tfp_save_course_identity']))) {
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
        } else {
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
            foreach ($rules_extra_fields as $field_key) {
                $raw = $submitted[$field_key] ?? '';
                $clean[$field_key] = is_scalar($raw) ? sanitize_text_field((string) $raw) : '';
            }
            update_post_meta($course_id, $rules_meta_key, $clean);
            $rules_settings = $clean;
            echo '<div class="tfp-admin-course-rules-notice" role="status">' . esc_html__('Course rules saved.', 'tfp-dashboard') . '</div>';
        }
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
                        <button type="button" class="tfp-admin-course-rules-edit" data-rules-edit aria-expanded="false"><img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Edit_Pencil_Line_01.png" alt="" aria-hidden="true"><span><?php esc_html_e('Edit', 'tfp-dashboard'); ?></span></button>
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
</section>
<script>
(function () {
    var root = document.querySelector('.tfp-admin-course-rules-page');
    if (!root) return;
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
    root.querySelectorAll('.tfp-course-identity-field select, .tfp-course-rules-field select').forEach(function (select) {
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
            var editing = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', editing ? 'true' : 'false');
            button.querySelector('span:last-child').textContent = editing ? 'Done' : 'Edit';
            card.querySelectorAll('[data-rules-input]').forEach(function (input) { input.hidden = !editing; });
            card.querySelectorAll('[data-rules-value]').forEach(function (value) { value.hidden = editing; });
        });
    });
})();
</script>
