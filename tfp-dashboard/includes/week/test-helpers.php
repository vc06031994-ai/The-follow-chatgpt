<?php
if (!defined('ABSPATH')) exit;

/**
 * Test helpers for the Week (lesson) player's Test tab.
 *
 * The test is multiple-choice and auto-graded. Each question carries a
 * REQUIRED correct answer (`correct_index`) plus an optional `explanation`
 * shown on the Review Answers screen after grading. Grading always happens
 * SERVER-SIDE in tfp_week_grade_test() — the answer key (and the explanation,
 * which reveals it) is never sent to the browser before the student submits.
 *
 * Students get a limited number of attempts (2 by default, matching the Figma
 * design "Two attempts per Test").
 */

/**
 * Get test questions for a lesson.
 *
 * @param int  $lesson_id
 * @param bool $for_display If true, strips `correct_index` AND `explanation`
 *                          (both reveal the answer) so the browser never
 *                          receives the answer key before grading.
 * @return array
 */
function tfp_week_get_test_questions($lesson_id, $for_display = true)
{
    $json = get_post_meta($lesson_id, 'tfp_week_test_questions', true);
    if (empty($json)) {
        return [];
    }

    $questions = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        return [];
    }

    if ($for_display) {
        foreach ($questions as &$q) {
            unset($q['correct_index'], $q['explanation']);
        }
        unset($q);
    }

    return $questions;
}

/**
 * Stored test answers for a user + lesson, keyed by question id.
 *
 * @return array e.g. ['q1' => ['selected_index' => 2], ...]
 */
function tfp_week_get_test_answers($user_id, $lesson_id)
{
    $answers = get_user_meta($user_id, 'tfp_week_test_answers_' . $lesson_id, true);
    return is_array($answers) ? $answers : [];
}

/**
 * Is a single test question answered? (0 is a valid option index, so we can't
 * use a naive empty() check.)
 */
function tfp_week_is_test_question_answered($question, $answer)
{
    return isset($answer['selected_index'])
        && $answer['selected_index'] !== ''
        && $answer['selected_index'] !== null;
}

/**
 * How many test questions are answered out of the total?
 */
function tfp_week_test_progress($user_id, $lesson_id)
{
    $questions = tfp_week_get_test_questions($lesson_id, false);
    $answers   = tfp_week_get_test_answers($user_id, $lesson_id);

    $completed = 0;
    foreach ($questions as $q) {
        if (isset($q['id'], $answers[$q['id']]) && tfp_week_is_test_question_answered($q, $answers[$q['id']])) {
            $completed++;
        }
    }

    return [
        'completed' => $completed,
        'total'     => count($questions),
    ];
}

/**
 * Has every test question been answered?
 */
function tfp_week_is_test_fully_answered($user_id, $lesson_id)
{
    $progress = tfp_week_test_progress($user_id, $lesson_id);
    return $progress['total'] > 0 && $progress['completed'] === $progress['total'];
}

/**
 * Pass percentage for a test. Reads the per-lesson meta, falling back to a
 * filterable default of 80% (matching the Figma design's "80% or higher").
 */
function tfp_week_get_test_pass_percentage($lesson_id)
{
    $stored  = (int) get_post_meta($lesson_id, 'tfp_week_test_pass_percentage', true);
    $default = apply_filters('tfp_week_test_pass_percentage', 80, $lesson_id);

    if ($stored >= 1 && $stored <= 100) {
        return $stored;
    }

    return max(1, min(100, (int) $default));
}

/**
 * Maximum number of test attempts (default 2 — Figma: "Two attempts per Test").
 */
function tfp_week_get_test_max_attempts($lesson_id = 0)
{
    return (int) apply_filters('tfp_week_test_max_attempts', 1, $lesson_id);
}

/**
 * How many attempts has this student used on this test?
 */
function tfp_week_get_test_attempts($user_id, $lesson_id)
{
    return (int) get_user_meta($user_id, 'tfp_week_test_attempts_' . $lesson_id, true);
}

/**
 * Is a retake still available? (Failed + attempts remaining.)
 */
function tfp_week_can_retake_test($user_id, $lesson_id)
{
    return tfp_week_get_test_attempts($user_id, $lesson_id) < tfp_week_get_test_max_attempts($lesson_id);
}

/**
 * The last graded result for a test, or null if never submitted.
 *
 * @return array|null ['score'=>int,'passed'=>bool,'total'=>int,'correct'=>int,
 *                     'results'=>[...], 'graded_at'=>string]
 */
function tfp_week_get_test_result($user_id, $lesson_id)
{
    $result = get_user_meta($user_id, 'tfp_week_test_result_' . $lesson_id, true);
    if (!is_array($result) || !isset($result['score'])) {
        return null;
    }
    return $result;
}

/**
 * Clear the saved answers + result (used by the "Retake Test" action).
 * Attempts are intentionally NOT cleared — the attempt count is preserved.
 */
function tfp_week_clear_test($user_id, $lesson_id)
{
    delete_user_meta($user_id, 'tfp_week_test_answers_' . $lesson_id);
    delete_user_meta($user_id, 'tfp_week_test_result_' . $lesson_id);
}

/**
 * Server-authoritative grading.
 *
 * Grades every question against its stored correct_index, builds a per-question
 * result (prompt, options, selected/correct indices, is_correct, explanation)
 * so the Review Answers screen can be rendered server-side, and returns the
 * score + pass/fail summary. Never trusts the browser for scoring.
 *
 * @return array ['score'=>int,'passed'=>bool,'total'=>int,'correct'=>int,'results'=>[...]]
 */
function tfp_week_grade_test($user_id, $lesson_id, $answers)
{
    $questions = tfp_week_get_test_questions($lesson_id, false);
    $total     = count($questions);
    $correct   = 0;
    $results   = [];

    foreach ($questions as $q) {
        $qid        = isset($q['id']) ? $q['id'] : '';
        $chosen     = null;
        $is_correct = false;

        if ($qid !== '' && isset($answers[$qid]['selected_index'])) {
            $chosen     = (int) $answers[$qid]['selected_index'];
            $is_correct = isset($q['correct_index']) && (int) $q['correct_index'] === $chosen;
        }

        if ($is_correct) {
            $correct++;
        }

        $results[] = [
            'id'             => $qid,
            'type'           => isset($q['type']) ? $q['type'] : 'multiple_choice',
            'prompt'         => isset($q['prompt']) ? $q['prompt'] : '',
            'options'        => isset($q['options']) && is_array($q['options']) ? $q['options'] : [],
            'selected_index' => $chosen,
            'correct_index'  => isset($q['correct_index']) ? (int) $q['correct_index'] : null,
            'explanation'    => isset($q['explanation']) ? $q['explanation'] : '',
            'is_correct'     => $is_correct,
        ];
    }

    $score  = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
    $passed = $score >= tfp_week_get_test_pass_percentage($lesson_id);

    return [
        'score'   => $score,
        'passed'  => $passed,
        'total'   => $total,
        'correct' => $correct,
        'results' => $results,
    ];
}
