<?php
if (!defined('ABSPATH')) exit;

/**
 * Get homework questions for a specific lesson.
 * 
 * @param int $lesson_id
 * @param bool $for_display If true, strips the 'correct_index' from multiple_choice questions.
 * @return array
 */
function tfp_week_get_homework_questions($lesson_id, $for_display = true)
{
    $json = get_post_meta($lesson_id, 'tfp_week_homework_questions', true);
    if (empty($json)) {
        return [];
    }

    $questions = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        return [];
    }

    if ($for_display) {
        foreach ($questions as &$q) {
            if (isset($q['type']) && $q['type'] === 'multiple_choice') {
                unset($q['correct_index']);
            }
        }
    }

    return $questions;
}

/**
 * Get stored homework answers for a user and lesson.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return array Associative array keyed by question ID.
 */
function tfp_week_get_homework_answers($user_id, $lesson_id)
{
    $meta_key = 'tfp_week_homework_answers_' . $lesson_id;
    $answers = get_user_meta($user_id, $meta_key, true);
    return is_array($answers) ? $answers : [];
}

/**
 * Check if a specific question is answered.
 * 
 * @param array $question The question object from JSON.
 * @param array $answer The stored answer for this question.
 * @return bool
 */
function tfp_week_is_question_answered($question, $answer)
{
    if (empty($question['type']) || empty($answer)) {
        return false;
    }

    $type = $question['type'];

    if ($type === 'multiple_choice') {
        return isset($answer['selected_index']) && $answer['selected_index'] !== '';
    }

    if ($type === 'written') {
        return !empty($answer['text']) && trim($answer['text']) !== '';
    }

    if ($type === 'both') {
        return isset($answer['yes_no']) && $answer['yes_no'] !== '' && !empty($answer['text']) && trim($answer['text']) !== '';
    }

    return false;
}

/**
 * Save a single homework answer.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @param string $question_id
 * @param array $answer
 * @return bool True on success, false if question doesn't exist.
 */
function tfp_week_save_homework_answer($user_id, $lesson_id, $question_id, $answer)
{
    $questions = tfp_week_get_homework_questions($lesson_id, false);
    
    // Validate question exists
    $found_q = null;
    foreach ($questions as $q) {
        if (isset($q['id']) && $q['id'] === $question_id) {
            $found_q = $q;
            break;
        }
    }

    if (!$found_q) {
        return false;
    }

    // Homework becomes read-only after the student submits it. This must be
    // enforced server-side; the browser UI alone is not an authorization
    // boundary.
    $week_progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!empty($week_progress['homework'])) {
        return false;
    }

    // Ensure answer type matches question type for data integrity
    $answer['type'] = $found_q['type'];

    // Validate answer values before storing them. Partial answers are allowed
    // during autosave, but any supplied value must belong to the question.
    if ($found_q['type'] === 'multiple_choice' && isset($answer['selected_index'])) {
        $selected_index = (int) $answer['selected_index'];
        if (empty($found_q['options']) || !array_key_exists($selected_index, (array) $found_q['options'])) {
            return false;
        }
        $answer['selected_index'] = $selected_index;
    }

    if ($found_q['type'] === 'both' && isset($answer['yes_no']) && !in_array($answer['yes_no'], ['yes', 'no'], true)) {
        return false;
    }

    if (isset($answer['text'])) {
        $answer['text'] = function_exists('mb_substr')
            ? mb_substr((string) $answer['text'], 0, 10000)
            : substr((string) $answer['text'], 0, 10000);
    }

    $answers = tfp_week_get_homework_answers($user_id, $lesson_id);
    $answers[$question_id] = $answer;

    $meta_key = 'tfp_week_homework_answers_' . $lesson_id;
    update_user_meta($user_id, $meta_key, $answers);

    return true;
}

/**
 * Get homework progress count.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return array ['completed' => int, 'total' => int]
 */
function tfp_week_homework_progress($user_id, $lesson_id)
{
    $questions = tfp_week_get_homework_questions($lesson_id, false);
    $answers = tfp_week_get_homework_answers($user_id, $lesson_id);

    $completed = 0;
    foreach ($questions as $q) {
        if (isset($q['id']) && isset($answers[$q['id']])) {
            if (tfp_week_is_question_answered($q, $answers[$q['id']])) {
                $completed++;
            }
        }
    }

    return [
        'completed' => $completed,
        'total'     => count($questions)
    ];
}

/**
 * Check if all homework questions are answered.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return bool
 */
function tfp_week_is_homework_fully_answered($user_id, $lesson_id)
{
    $progress = tfp_week_homework_progress($user_id, $lesson_id);
    return $progress['total'] > 0 && $progress['completed'] === $progress['total'];
}

/**
 * Check if a homework answer is correct.
 *
 * This function determines if the user's answer for a specific homework question is correct.
 * For multiple-choice questions, it compares the selected index with the correct index.
 * For written or 'both' types, it assumes correctness based on a 'graded_correct'
 * field in the answer data if available, or a 'correct_answer_text' field in the question.
 * If neither is available, it defaults to false for written answers for the purpose of
 * visual differentiation in review, implying manual grading is needed.
 *
 * @param array $question The question object from JSON (includes correct_index/correct_answer_text).
 * @param array $answer The stored answer for this question.
 * @return bool True if the answer is considered correct, false otherwise.
 */
function tfp_week_is_homework_answer_correct($question, $answer)
{
    if (empty($question['type']) || empty($answer)) {
        return false;
    }

    $type = $question['type'];

    if ($type === 'multiple_choice') {
        // For multiple choice, compare selected index with correct index
        return isset($question['correct_index'])
               && isset($answer['selected_index'])
               && ((int)$question['correct_index'] === (int)$answer['selected_index']);
    }

    // For written or 'both' types, determining correctness is subjective or requires grading.
    // Assuming a 'graded_correct' flag in the answer for manual grading.
    if (isset($answer['graded_correct'])) {
        return (bool)$answer['graded_correct'];
    }

    // If no explicit grading flag, and for written/both, if a correct answer text is provided,
    // we could compare (though exact string comparison for written answers is usually too strict).
    // For now, if an answer is present, and no explicit 'graded_correct' is set,
    // we'll default to incorrect for visual differentiation,
    // to prompt the user to implement actual grading logic for written answers.
    if (!empty($answer['text']) && trim($answer['text']) !== '') {
        // This is where more sophisticated grading logic for written answers would go
        // e.g., comparing with $question['correct_answer_text'] if available,
        // or using an AI-based grader. For now, defaulting to false for visual.
        return false;
    }

    return false; // Default to incorrect if no specific condition is met
}
