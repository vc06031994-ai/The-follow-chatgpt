<?php
if (!defined('ABSPATH')) { exit; }

$test_questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($week_id, false) : [];
$selected_test_id = isset($_GET['test_id']) ? sanitize_text_field(wp_unslash($_GET['test_id'])) : '';
if ($selected_test_id === '' && !empty($test_questions)) {
    $selected_test_id = (string) ($test_questions[0]['id'] ?? '');
}
$selected_test = null;
foreach ($test_questions as $question) {
    if ((string) ($question['id'] ?? '') === $selected_test_id) {
        $selected_test = $question;
        break;
    }
}

if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_course_editor_action'])) {
    $test_action = sanitize_key(wp_unslash($_POST['tfp_course_editor_action']));
    if (in_array($test_action, ['save_test', 'duplicate_test', 'remove_test'], true)) {
        check_admin_referer('tfp_course_editor_' . $course_id . '_' . $week_id);
        $qid = isset($_POST['test_id']) ? sanitize_text_field(wp_unslash($_POST['test_id'])) : '';
        $found_index = -1;
        foreach ($test_questions as $index => $question) {
            if ((string) ($question['id'] ?? '') === $qid) { $found_index = $index; break; }
        }

        if ($found_index >= 0 && current_user_can('edit_post', $week_id)) {
            if ($test_action === 'remove_test') {
                array_splice($test_questions, $found_index, 1);
                update_post_meta($week_id, 'tfp_week_test_questions', wp_json_encode(array_values($test_questions)));
                $selected_test_id = !empty($test_questions) ? (string) ($test_questions[0]['id'] ?? '') : '';
                $selected_test = !empty($test_questions) ? $test_questions[0] : null;
                echo '<div class="tfp-editor-notice" role="status">' . esc_html__('Test question removed.', 'tfp-dashboard') . '</div>';
            } elseif ($test_action === 'duplicate_test') {
                $copy = $test_questions[$found_index];
                $copy['id'] = 'q' . wp_generate_password(8, false, false);
                $copy['prompt'] = (string) ($copy['prompt'] ?? '') . ' (Copy)';
                array_splice($test_questions, $found_index + 1, 0, [$copy]);
                update_post_meta($week_id, 'tfp_week_test_questions', wp_json_encode(array_values($test_questions)));
                $selected_test_id = (string) $copy['id'];
                $selected_test = $copy;
                echo '<div class="tfp-editor-notice" role="status">' . esc_html__('Test question duplicated.', 'tfp-dashboard') . '</div>';
            } elseif ($test_action === 'save_test') {
                $type = isset($_POST['test_type']) ? sanitize_key(wp_unslash($_POST['test_type'])) : 'multiple_choice';
                if (!in_array($type, ['multiple_choice', 'written', 'both'], true)) { $type = 'multiple_choice'; }
                $options_text = isset($_POST['test_options_text']) ? sanitize_textarea_field(wp_unslash($_POST['test_options_text'])) : '';
                $options = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', $options_text)), static function ($value) { return $value !== ''; }));
                $correct_answer = isset($_POST['test_correct_answer']) ? sanitize_text_field(wp_unslash($_POST['test_correct_answer'])) : '';
                $correct_index = null;
                foreach ($options as $index => $option) {
                    if ($correct_answer !== '' && $option === $correct_answer) { $correct_index = $index; break; }
                }
                $mc_prompt = isset($_POST['test_mc_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['test_mc_prompt'])) : '';
                $written_prompt = isset($_POST['test_written_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['test_written_prompt'])) : '';
                $plain_prompt = isset($_POST['test_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['test_prompt'])) : '';
                $prompt = $type === 'both' ? $mc_prompt : ($type === 'written' ? $written_prompt : $plain_prompt);
                $tags_raw = isset($_POST['test_mastery_tags']) ? sanitize_text_field(wp_unslash($_POST['test_mastery_tags'])) : '';
                $tags = array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', $tags_raw)))));
                $entry = [
                    'id' => $qid,
                    'type' => $type,
                    'prompt' => $prompt,
                    'mc_prompt' => $mc_prompt,
                    'written_prompt' => $written_prompt,
                    'options' => $options,
                    'correct_answer' => $correct_answer,
                    'correct_index' => $correct_index,
                    'explanation' => isset($_POST['test_explanation']) ? sanitize_textarea_field(wp_unslash($_POST['test_explanation'])) : '',
                    'written_explanation' => isset($_POST['test_written_explanation']) ? sanitize_textarea_field(wp_unslash($_POST['test_written_explanation'])) : '',
                    'mastery_tags' => $tags,
                    'supporting_scriptures' => isset($_POST['test_scriptures']) ? sanitize_text_field(wp_unslash($_POST['test_scriptures'])) : '',
                ];
                $test_questions[$found_index] = array_merge($test_questions[$found_index], $entry);
                update_post_meta($week_id, 'tfp_week_test_questions', wp_json_encode(array_values($test_questions)));
                $selected_test_id = $qid;
                $selected_test = $test_questions[$found_index];
                echo '<div class="tfp-editor-notice" role="status">' . esc_html__('Test question saved successfully.', 'tfp-dashboard') . '</div>';
            }
        }
    }
}
?>
<style>
.tfp-test-editor-form{display:flex;flex-direction:column;gap:14px}
.tfp-test-editor-form label{display:flex;flex-direction:column;gap:7px;color:#92999a;font:600 12px/1.35 var(--tfp-dash-font,"Eudoxus Sans",sans-serif)}
.tfp-test-editor-form input[type=text],.tfp-test-editor-form textarea{width:100%;min-height:40px;padding:10px 12px;border:1px solid #cbd0d1;border-radius:7px;background:#fff;color:#424748;font:400 13px/1.45 var(--tfp-dash-font,"Eudoxus Sans",sans-serif);box-shadow:none}
.tfp-test-editor-form textarea{resize:vertical}
.tfp-test-format{display:flex;flex-wrap:wrap;gap:16px;margin:0;padding:0;border:0}
.tfp-test-format legend{width:100%;margin-bottom:7px;color:#92999a;font:600 12px/1.35 var(--tfp-dash-font,"Eudoxus Sans",sans-serif)}
.tfp-test-format label{display:inline-flex;flex-direction:row;align-items:center;gap:7px;color:#606667;font:400 12px/1.4 var(--tfp-dash-font,"Eudoxus Sans",sans-serif)}
.tfp-test-format input{accent-color:#006b73}
.tfp-test-editor-form [hidden]{display:none!important}
.tfp-test-tags-help{color:#8b9095;font-size:11px;font-weight:400}
.tfp-editor-test-list .tfp-editor-reading-item{min-height:54px}
.tfp-test-editor-form .tfp-editor-actions{display:flex;flex-wrap:wrap;gap:8px}
.tfp-test-editor-form .tfp-editor-secondary{min-height:34px;padding:0 12px;border:1px solid #cbd0d1;border-radius:5px;background:#fff;color:#222;font:600 12px var(--tfp-dash-font,"Eudoxus Sans",sans-serif);cursor:pointer}
@media(max-width:700px){.tfp-test-format{gap:10px}.tfp-test-editor-form{gap:12px}}
</style>
<section class="tfp-editor-workspace tfp-editor-test-workspace" id="tfp-test-bank-editor">
    <div class="tfp-editor-workspace-heading">
        <div><h2><?php esc_html_e('Test Bank Editor', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Manage mastery assessments, mastery tracking tags, and performance tracking questions.', 'tfp-dashboard'); ?></p></div>
        <span class="tfp-editor-count"><?php echo esc_html(sprintf(__('%d items', 'tfp-dashboard'), count($test_questions))); ?></span>
    </div>
    <div class="tfp-editor-columns">
        <section class="tfp-editor-list-panel">
            <h3><?php esc_html_e('Scrollable test question list', 'tfp-dashboard'); ?></h3>
            <div class="tfp-editor-reading-list tfp-editor-test-list">
                <?php foreach ($test_questions as $index => $question) : $qid = (string) ($question['id'] ?? ''); ?>
                    <a class="tfp-editor-reading-item<?php echo $qid === $selected_test_id ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['week_editor' => '1', 'week_id' => $week_id, 'editor_tab' => 'test_bank', 'test_id' => $qid], tfp_admin_course_detail_url($course_id))); ?>">
                        <strong><?php echo esc_html(sprintf(__('Q%d. %s', 'tfp-dashboard'), $index + 1, wp_trim_words((string) ($question['prompt'] ?? ''), 8, '…'))); ?></strong>
                        <small><?php echo esc_html(str_replace('_', ' ', (string) ($question['type'] ?? 'multiple_choice'))); ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($test_questions)) : ?><p class="tfp-editor-empty"><?php esc_html_e('No test questions are assigned to this week yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
            </div>
        </section>
        <section class="tfp-editor-edit-panel">
            <h3><?php esc_html_e('Edit Test Question', 'tfp-dashboard'); ?></h3>
            <?php if ($selected_test) :
                $test_type = (string) ($selected_test['type'] ?? 'multiple_choice');
                $test_options = (array) ($selected_test['options'] ?? []);
                $test_correct = (string) ($selected_test['correct_answer'] ?? '');
                if ($test_correct === '' && isset($selected_test['correct_index'], $test_options[$selected_test['correct_index']])) { $test_correct = (string) $test_options[$selected_test['correct_index']]; }
                $test_prompt = (string) ($selected_test['prompt'] ?? '');
                $mc_prompt_value = (string) ($selected_test['mc_prompt'] ?? $test_prompt);
                $written_prompt_value = (string) ($selected_test['written_prompt'] ?? '');
                $mastery_tags = (array) ($selected_test['mastery_tags'] ?? []);
            ?>
            <form method="post" class="tfp-test-editor-form">
                <?php wp_nonce_field('tfp_course_editor_' . $course_id . '_' . $week_id); ?>
                <input type="hidden" name="test_id" value="<?php echo esc_attr($selected_test_id); ?>">
                <fieldset class="tfp-test-format">
                    <legend><?php esc_html_e('Question Format', 'tfp-dashboard'); ?></legend>
                    <label><input type="radio" name="test_type" value="multiple_choice" <?php checked($test_type, 'multiple_choice'); ?>><?php esc_html_e('Multiple Choice', 'tfp-dashboard'); ?></label>
                    <label><input type="radio" name="test_type" value="written" <?php checked($test_type, 'written'); ?>><?php esc_html_e('Written Answer', 'tfp-dashboard'); ?></label>
                    <label><input type="radio" name="test_type" value="both" <?php checked($test_type, 'both'); ?>><?php esc_html_e('Both', 'tfp-dashboard'); ?></label>
                </fieldset>
                <div data-test-mc <?php echo in_array($test_type, ['multiple_choice','both'], true) ? '' : 'hidden'; ?>>
                    <label><?php echo $test_type === 'both' ? esc_html__('Multiple Choice Question', 'tfp-dashboard') : esc_html__('Question / Prompt', 'tfp-dashboard'); ?><textarea name="test_mc_prompt" rows="2"><?php echo esc_textarea($mc_prompt_value); ?></textarea></label>
                    <label><?php esc_html_e('MC Options (One per line)', 'tfp-dashboard'); ?><textarea name="test_options_text" rows="4"><?php echo esc_textarea(implode("\n", $test_options)); ?></textarea></label>
                    <label><?php esc_html_e('Correct Answer', 'tfp-dashboard'); ?><input type="text" name="test_correct_answer" value="<?php echo esc_attr($test_correct); ?>"></label>
                </div>
                <div data-test-written <?php echo in_array($test_type, ['written','both'], true) ? '' : 'hidden'; ?>>
                    <label><?php echo $test_type === 'both' ? esc_html__('Written Response Question Prompt', 'tfp-dashboard') : esc_html__('Question / Prompt', 'tfp-dashboard'); ?><textarea name="test_written_prompt" rows="3"><?php echo esc_textarea($written_prompt_value !== '' ? $written_prompt_value : ($test_type === 'written' ? $test_prompt : '')); ?></textarea></label>
                </div>
                <div data-test-explanation>
                    <label><?php echo $test_type === 'both' ? esc_html__('Answer Explanation (for feedback)', 'tfp-dashboard') : esc_html__('Explanation / Support', 'tfp-dashboard'); ?><textarea name="test_explanation" rows="2"><?php echo esc_textarea((string) ($selected_test['explanation'] ?? '')); ?></textarea></label>
                </div>
                <div data-test-written-explanation <?php echo $test_type === 'both' ? '' : 'hidden'; ?>>
                    <label><?php esc_html_e('Answer Explanation (for feedback)', 'tfp-dashboard'); ?><textarea name="test_written_explanation" rows="2"><?php echo esc_textarea((string) ($selected_test['written_explanation'] ?? '')); ?></textarea></label>
                </div>
                <label><?php esc_html_e('Mastery Tags', 'tfp-dashboard'); ?><input type="text" name="test_mastery_tags" value="<?php echo esc_attr(implode(', ', $mastery_tags)); ?>" placeholder="<?php esc_attr_e('Add tags, separated by commas', 'tfp-dashboard'); ?>"><span class="tfp-test-tags-help"><?php esc_html_e('Separate each mastery tag with a comma.', 'tfp-dashboard'); ?></span></label>
                <label><?php esc_html_e('Supporting Scriptures', 'tfp-dashboard'); ?><input type="text" name="test_scriptures" value="<?php echo esc_attr((string) ($selected_test['supporting_scriptures'] ?? '')); ?>"></label>
                <div class="tfp-editor-actions">
                    <button type="submit" name="tfp_course_editor_action" value="save_test" class="tfp-editor-save"><img class="tfp-homework-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"> <?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                    <button type="submit" name="tfp_course_editor_action" value="duplicate_test" class="tfp-editor-secondary"><?php esc_html_e('Duplicate', 'tfp-dashboard'); ?></button>
                    <button type="submit" name="tfp_course_editor_action" value="remove_test" class="tfp-editor-secondary" onclick="return confirm('<?php echo esc_js(__('Remove this test question?', 'tfp-dashboard')); ?>');"><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button>
                </div>
            </form>
            <?php else : ?><p class="tfp-editor-empty"><?php esc_html_e('Select a test question from the list to edit it.', 'tfp-dashboard'); ?></p><?php endif; ?>
        </section>
    </div>
    <div class="tfp-editor-footnote"><?php esc_html_e('Test questions help track long-term formation. Written and Both formats also store the written response prompt and supporting feedback.', 'tfp-dashboard'); ?></div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.tfp-test-editor-form');
    if (!form) return;
    var mc = form.querySelector('[data-test-mc]');
    var written = form.querySelector('[data-test-written]');
    var writtenExplanation = form.querySelector('[data-test-written-explanation]');
    function toggle() {
        var selected = form.querySelector('input[name="test_type"]:checked');
        var type = selected ? selected.value : 'multiple_choice';
        if (mc) mc.hidden = !(type === 'multiple_choice' || type === 'both');
        if (written) written.hidden = !(type === 'written' || type === 'both');
        if (writtenExplanation) writtenExplanation.hidden = type !== 'both';
        var mcLabel = mc ? mc.querySelector('label') : null;
        var writtenLabel = written ? written.querySelector('label') : null;
        if (mcLabel) mcLabel.firstChild.textContent = type === 'both' ? '<?php echo esc_js(__('Multiple Choice Question', 'tfp-dashboard')); ?>' : '<?php echo esc_js(__('Question / Prompt', 'tfp-dashboard')); ?>';
        if (writtenLabel) writtenLabel.firstChild.textContent = type === 'both' ? '<?php echo esc_js(__('Written Response Question Prompt', 'tfp-dashboard')); ?>' : '<?php echo esc_js(__('Question / Prompt', 'tfp-dashboard')); ?>';
    }
    form.querySelectorAll('input[name="test_type"]').forEach(function (radio) { radio.addEventListener('change', toggle); });
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        if (submitter && submitter.value !== 'save_test') return;
        var selected = form.querySelector('input[name="test_type"]:checked');
        if (!selected) return;
        var type = selected.value;
        if (type === 'multiple_choice') {
            var mcField = form.querySelector('[name="test_mc_prompt"]');
            var prompt = document.createElement('input'); prompt.type = 'hidden'; prompt.name = 'test_prompt'; prompt.value = mcField ? mcField.value : ''; form.appendChild(prompt);
        } else if (type === 'written') {
            var writtenField = form.querySelector('[name="test_written_prompt"]');
            var promptWritten = document.createElement('input'); promptWritten.type = 'hidden'; promptWritten.name = 'test_prompt'; promptWritten.value = writtenField ? writtenField.value : ''; form.appendChild(promptWritten);
        }
    });
    toggle();
});
</script>
