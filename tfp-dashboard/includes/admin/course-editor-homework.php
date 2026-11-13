<?php
if (!defined('ABSPATH')) { exit; }

$homework_questions = function_exists('tfp_week_get_homework_questions') ? (array) tfp_week_get_homework_questions($week_id, false) : [];
$selected_homework_id = isset($_GET['homework_id']) ? sanitize_text_field(wp_unslash($_GET['homework_id'])) : '';
if (!$selected_homework_id && !empty($homework_questions)) {
    $selected_homework_id = (string) ($homework_questions[0]['id'] ?? '');
}
$selected_homework = null;
foreach ($homework_questions as $question) {
    if ((string) ($question['id'] ?? '') === $selected_homework_id) {
        $selected_homework = $question;
        break;
    }
}

if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_course_editor_action']) && 'save_homework' === sanitize_key(wp_unslash($_POST['tfp_course_editor_action']))) {
    check_admin_referer('tfp_course_editor_' . $course_id . '_' . $week_id);
    $qid = isset($_POST['homework_id']) ? sanitize_text_field(wp_unslash($_POST['homework_id'])) : '';
    $type = isset($_POST['homework_type']) ? sanitize_key(wp_unslash($_POST['homework_type'])) : 'multiple_choice';
    if (!in_array($type, ['multiple_choice', 'written', 'both'], true)) { $type = 'multiple_choice'; }
    $options_text = isset($_POST['homework_options_text']) ? sanitize_textarea_field(wp_unslash($_POST['homework_options_text'])) : '';
    $options = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', $options_text)), static function ($v) { return $v !== ''; }));
    $correct_answer = isset($_POST['homework_correct_answer']) ? sanitize_text_field(wp_unslash($_POST['homework_correct_answer'])) : '';
    $correct_index = null;
    foreach ($options as $i => $option) { if ($correct_answer !== '' && $option === $correct_answer) { $correct_index = $i; break; } }
    $prompt = isset($_POST['homework_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['homework_prompt'])) : '';
    $mc_prompt = isset($_POST['homework_mc_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['homework_mc_prompt'])) : '';
    $written_prompt = isset($_POST['homework_written_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['homework_written_prompt'])) : '';
    $entry = [
        'id' => $qid,
        'type' => $type,
        'prompt' => $type === 'both' ? $mc_prompt : $prompt,
        'options' => $options,
        'correct_index' => $correct_index,
        'correct_answer' => $correct_answer,
        'expected_response_guide' => isset($_POST['homework_expected_guide']) ? sanitize_textarea_field(wp_unslash($_POST['homework_expected_guide'])) : '',
        'answer_explanation' => isset($_POST['homework_explanation']) ? sanitize_textarea_field(wp_unslash($_POST['homework_explanation'])) : '',
        'supporting_scriptures' => isset($_POST['homework_scriptures']) ? sanitize_text_field(wp_unslash($_POST['homework_scriptures'])) : '',
        'mc_prompt' => $mc_prompt,
        'written_prompt' => $written_prompt,
    ];
    $updated = [];
    $found = false;
    foreach ($homework_questions as $question) {
        if ((string) ($question['id'] ?? '') === $qid) { $updated[] = $entry; $found = true; }
        else { $updated[] = $question; }
    }
    if ($found && current_user_can('edit_post', $week_id)) {
        update_post_meta($week_id, 'tfp_week_homework_questions', wp_json_encode($updated));
        $homework_questions = $updated;
        $selected_homework = $entry;
        echo '<div class="tfp-editor-notice" role="status">' . esc_html__('Homework item saved successfully.', 'tfp-dashboard') . '</div>';
    }
}

?>
<style>
.tfp-homework-editor-form { display:flex; flex-direction:column; gap:15px; }
.tfp-homework-editor-form label { display:flex; flex-direction:column; gap:7px; color:#92999a; font:600 12px/1.35 var(--tfp-dash-font,"Eudoxus Sans",sans-serif); }
.tfp-homework-editor-form input[type="text"], .tfp-homework-editor-form textarea { width:100%; min-height:42px; padding:10px 12px; border:1px solid #cbd0d1; border-radius:7px; background:#fff; color:#424748; font:400 13px/1.45 var(--tfp-dash-font,"Eudoxus Sans",sans-serif); box-shadow:none; }
.tfp-homework-editor-form textarea { resize:vertical; }
.tfp-homework-format { display:flex; flex-wrap:wrap; gap:16px; margin:0 0 2px; padding:0; border:0; }
.tfp-homework-format legend { width:100%; margin-bottom:8px; color:#92999a; font:600 12px/1.35 var(--tfp-dash-font,"Eudoxus Sans",sans-serif); }
.tfp-homework-format label { display:inline-flex; flex-direction:row; align-items:center; gap:7px; color:#606667; font:400 12px/1.4 var(--tfp-dash-font,"Eudoxus Sans",sans-serif); }
.tfp-homework-format input { accent-color:#006b73; }
.tfp-homework-editor-form [hidden] { display:none !important; }
.tfp-editor-homework-list .tfp-editor-reading-item { min-height:54px; }
.tfp-editor-homework-list .tfp-editor-reading-item small { text-transform:capitalize; }
.tfp-homework-save-icon { width:14px; height:14px; object-fit:contain; vertical-align:middle; }
@media(max-width:700px) { .tfp-homework-format { gap:10px; } .tfp-homework-editor-form { gap:12px; } }
</style>
<section class="tfp-editor-workspace tfp-editor-homework-workspace" id="tfp-homework-editor">
    <div class="tfp-editor-workspace-heading">
        <div><h2><?php esc_html_e('Homework Editor', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Edit reflection work, multiple choice, written answer, explanation, and supporting scriptures', 'tfp-dashboard'); ?></p></div>
        <span class="tfp-editor-count"><?php echo esc_html(sprintf(__('%d items', 'tfp-dashboard'), count($homework_questions))); ?></span>
    </div>
    <div class="tfp-editor-columns">
        <section class="tfp-editor-list-panel">
            <h3><?php esc_html_e('Scrollable homework list', 'tfp-dashboard'); ?></h3>
            <div class="tfp-editor-reading-list tfp-editor-homework-list">
                <?php foreach ($homework_questions as $index => $question) : $qid = (string) ($question['id'] ?? ''); ?>
                    <a class="tfp-editor-reading-item<?php echo $qid === $selected_homework_id ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['week_editor' => '1', 'week_id' => $week_id, 'editor_tab' => 'homework', 'homework_id' => $qid], tfp_admin_course_detail_url($course_id))); ?>">
                        <strong><?php echo esc_html(sprintf(__('Q%d. %s', 'tfp-dashboard'), $index + 1, wp_trim_words((string) ($question['prompt'] ?? ''), 8, '…'))); ?></strong>
                        <small><?php echo esc_html(str_replace('_', ' ', (string) ($question['type'] ?? 'written'))); ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($homework_questions)) : ?><p class="tfp-editor-empty"><?php esc_html_e('No homework items are assigned to this week yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
            </div>
        </section>
        <section class="tfp-editor-edit-panel">
            <h3><?php esc_html_e('Edit Homework Item', 'tfp-dashboard'); ?></h3>
            <?php if ($selected_homework) :
                $hw_type = (string) ($selected_homework['type'] ?? 'multiple_choice');
                $hw_options = (array) ($selected_homework['options'] ?? []);
                $hw_correct = (string) ($selected_homework['correct_answer'] ?? '');
                if ($hw_correct === '' && isset($selected_homework['correct_index'], $hw_options[$selected_homework['correct_index']])) { $hw_correct = (string) $hw_options[$selected_homework['correct_index']]; }
            ?>
            <form method="post" class="tfp-homework-editor-form">
                <?php wp_nonce_field('tfp_course_editor_' . $course_id . '_' . $week_id); ?>
                <input type="hidden" name="tfp_course_editor_action" value="save_homework">
                <input type="hidden" name="homework_id" value="<?php echo esc_attr($selected_homework_id); ?>">
                <fieldset class="tfp-homework-format">
                    <legend><?php esc_html_e('Question Format', 'tfp-dashboard'); ?></legend>
                    <label><input type="radio" name="homework_type" value="multiple_choice" <?php checked($hw_type, 'multiple_choice'); ?>><?php esc_html_e('Multiple Choice', 'tfp-dashboard'); ?></label>
                    <label><input type="radio" name="homework_type" value="written" <?php checked($hw_type, 'written'); ?>><?php esc_html_e('Written Answer', 'tfp-dashboard'); ?></label>
                    <label><input type="radio" name="homework_type" value="both" <?php checked($hw_type, 'both'); ?>><?php esc_html_e('Both', 'tfp-dashboard'); ?></label>
                </fieldset>
                <div data-hw-mc <?php echo in_array($hw_type, ['multiple_choice','both'], true) ? '' : 'hidden'; ?>>
                    <label><?php echo $hw_type === 'both' ? esc_html__('Multiple Choice Question', 'tfp-dashboard') : esc_html__('Question / Prompt', 'tfp-dashboard'); ?>
                        <textarea name="<?php echo $hw_type === 'both' ? 'homework_mc_prompt' : 'homework_prompt'; ?>" rows="2"><?php echo esc_textarea((string) ($hw_type === 'both' ? ($selected_homework['mc_prompt'] ?? $selected_homework['prompt'] ?? '') : ($selected_homework['prompt'] ?? ''))); ?></textarea>
                    </label>
                    <label><?php esc_html_e('MC Options (One per line)', 'tfp-dashboard'); ?><textarea name="homework_options_text" rows="4"><?php echo esc_textarea(implode("\n", $hw_options)); ?></textarea></label>
                    <label><?php esc_html_e('Correct Answer', 'tfp-dashboard'); ?><input type="text" name="homework_correct_answer" value="<?php echo esc_attr($hw_correct); ?>"></label>
                </div>
                <div data-hw-written <?php echo in_array($hw_type, ['written','both'], true) ? '' : 'hidden'; ?>>
                    <label><?php echo $hw_type === 'both' ? esc_html__('Written Response Question Prompt', 'tfp-dashboard') : esc_html__('Question / Prompt', 'tfp-dashboard'); ?>
                        <textarea name="<?php echo $hw_type === 'both' ? 'homework_written_prompt' : 'homework_prompt_written'; ?>" rows="3"><?php echo esc_textarea((string) ($hw_type === 'both' ? ($selected_homework['written_prompt'] ?? '') : ($selected_homework['prompt'] ?? ''))); ?></textarea>
                    </label>
                    <label data-hw-guide><?php esc_html_e('Expected Response Guide', 'tfp-dashboard'); ?><textarea name="homework_expected_guide" rows="3"><?php echo esc_textarea((string) ($selected_homework['expected_response_guide'] ?? '')); ?></textarea></label>
                </div>
                <label><?php esc_html_e('Answer Explanation', 'tfp-dashboard'); ?><textarea name="homework_explanation" rows="2"><?php echo esc_textarea((string) ($selected_homework['answer_explanation'] ?? '')); ?></textarea></label>
                <label data-hw-scriptures <?php echo $hw_type === 'multiple_choice' ? 'hidden' : ''; ?>><?php esc_html_e('Supporting Scriptures', 'tfp-dashboard'); ?><input type="text" name="homework_scriptures" value="<?php echo esc_attr((string) ($selected_homework['supporting_scriptures'] ?? '')); ?>"></label>
                <div class="tfp-editor-actions"><button type="submit" class="tfp-editor-save"><img class="tfp-homework-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt=""> <?php esc_html_e('Save', 'tfp-dashboard'); ?></button></div>
            </form>
            <?php else : ?><p class="tfp-editor-empty"><?php esc_html_e('Select a homework item from the list to edit it.', 'tfp-dashboard'); ?></p><?php endif; ?>
        </section>
    </div>
    <div class="tfp-editor-footnote"><?php esc_html_e('Choose a question format to display the matching homework fields.', 'tfp-dashboard'); ?></div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.tfp-homework-editor-form');
    if (!form) return;
    var mc = form.querySelector('[data-hw-mc]');
    var written = form.querySelector('[data-hw-written]');
    var guide = form.querySelector('[data-hw-guide]');
    var scriptures = form.querySelector('[data-hw-scriptures]');
    function toggle() {
        var checked = form.querySelector('input[name="homework_type"]:checked');
        var type = checked ? checked.value : 'multiple_choice';
        if (mc) mc.hidden = !(type === 'multiple_choice' || type === 'both');
        if (written) written.hidden = !(type === 'written' || type === 'both');
        if (guide) guide.hidden = type === 'both';
        if (scriptures) scriptures.hidden = type === 'multiple_choice';
    }
    form.querySelectorAll('input[name="homework_type"]').forEach(function (radio) { radio.addEventListener('change', toggle); });
    form.addEventListener('submit', function () {
        var type = form.querySelector('input[name="homework_type"]:checked');
        if (!type) return;
        if (type.value === 'written') {
            var writtenPrompt = form.querySelector('[name="homework_prompt_written"]');
            var hiddenPrompt = document.createElement('input');
            hiddenPrompt.type = 'hidden'; hiddenPrompt.name = 'homework_prompt'; hiddenPrompt.value = writtenPrompt ? writtenPrompt.value : '';
            form.appendChild(hiddenPrompt);
        }
    });
    toggle();
});
</script>
