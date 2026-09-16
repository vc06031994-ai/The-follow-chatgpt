<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_week_meeting_experience($week)
{
    $user_id = get_current_user_id();
    $lesson_id = $week->ID;
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    $allowed = ['attendance','homework','test','exercise','notes'];
    $active = isset($_GET['meeting_tab']) ? sanitize_key($_GET['meeting_tab']) : 'attendance';
    if (!in_array($active, $allowed, true)) $active = 'attendance';

    $url = function ($tab, $extra = []) use ($lesson_id) {
        return add_query_arg(array_merge(['lesson_id'=>$lesson_id,'tab'=>'meeting','meeting_tab'=>$tab], $extra));
    };

    $test_result = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($user_id, $lesson_id) : null;
    $tasks = [
        'attendance' => ['Attendance', (bool)get_user_meta($user_id,'tfp_week_meeting_attendance_'.$lesson_id,true)],
        'homework' => ['Homework Review', !empty($progress['homework'])],
        'test' => ['Test Review', !empty($test_result)],
        'exercise' => ['Exercises', false],
        'notes' => ['Weekly Notes', trim((string)get_user_meta($user_id,'tfp_week_meeting_notes_'.$lesson_id,true)) !== ''],
    ];
    ?>
    <div class="tfp-week__meeting-layout">
        <aside class="tfp-week__meeting-sidebar">
            <div class="tfp-week__meeting-progress-card">
                <h5><?php esc_html_e('Weekly Progress','tfp-dashboard'); ?></h5>
                <?php foreach (['reading'=>'Reading','homework'=>'Homework','test'=>'Tests'] as $step=>$label) : $done=!empty($progress[$step]); ?>
                    <div class="tfp-week__meeting-progress-row">
                        <div class="tfp-week__meeting-progress-top">
                            <div>
                                <strong><?php echo esc_html($label); ?></strong>
                                <?php if ($step === 'homework' || $step === 'test') : ?>
                                    <div class="tfp-week__meeting-progress-row-subtext"><?php esc_html_e('Passed • Grade: B', 'tfp-dashboard'); ?></div>
                                <?php endif; ?>
                            </div>
                            <span><?php echo $done ? esc_html__('Completed','tfp-dashboard') : esc_html__('Not Started','tfp-dashboard'); ?></span>
                        </div>
                        <div class="tfp-week__meeting-progress-bar"><span style="width:<?php echo $done?'100':'0'; ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <h5 class="tfp-week__meeting-tasks-title"><?php esc_html_e('Meeting Tasks','tfp-dashboard'); ?></h5>
                <?php foreach ($tasks as $key=>$task) : ?>
                    <div class="tfp-week__meeting-task">
                        <div><strong><?php echo esc_html($task[0]); ?></strong></div>
                        <span class="tfp-week__meeting-status <?php echo $task[1]?'is-complete':''; ?>"><?php echo $task[1] ? esc_html__('Completed','tfp-dashboard') : esc_html__('Not Started','tfp-dashboard'); ?></span>
                        <a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($url($key)); ?>"><?php echo esc_html($key==='attendance' && $task[1] ? 'Review' : 'Start'); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tfp-week__meeting-sidebar-footer">
                <a href="<?php echo esc_url(add_query_arg(['lesson_id' => $lesson_id, 'tab' => 'homework'])); ?>" class="tfp-dash-btn tfp-week__meeting-back-btn">
                    ‹ <?php esc_html_e('Back to Homework', 'tfp-dashboard'); ?>
                </a>
            </div>
        </aside>

        <section class="tfp-week__meeting-content">
            <nav class="tfp-week__meeting-subtabs">
                <?php foreach (['attendance'=>'Attendance','homework'=>'Homework','test'=>'Tests','exercise'=>'Exercise','notes'=>'Notes'] as $key=>$label) : ?>
                    <a href="<?php echo esc_url($url($key)); ?>" class="<?php echo $active===$key?'is-active':''; ?>"><?php echo esc_html__($label,'tfp-dashboard'); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-week__meeting-body">
                <?php if ($active==='attendance') :
                    $description=(string)get_post_meta($lesson_id,'tfp_week_meeting_description',true);
                    $date=(string)get_post_meta($lesson_id,'tfp_week_meeting_date',true);
                    $time=(string)get_post_meta($lesson_id,'tfp_week_meeting_time',true);
                    $facilitator=(string)get_post_meta($lesson_id,'tfp_week_facilitator_name',true);
                    $discord=(string)get_post_meta($lesson_id,'tfp_week_meeting_discord_url',true);
                    $discord=$discord?:apply_filters('tfp_dashboard_discord_url','#');
                ?>
                    <article class="tfp-week__meeting-attendance">
                        <h5><?php esc_html_e('Weekly Meetings','tfp-dashboard'); ?></h5>
                        <div class="tfp-week__meeting-intro"><?php echo wp_kses_post(wpautop($description ?: __("Your weekly meeting is designed to help you grow through connection and conversation. Join your facilitator and group to discuss this week's topic, share insights, and ask questions. Attendance is required to unlock the next section of your discipleship journey.",'tfp-dashboard'))); ?></div>
                        <div class="tfp-week__meeting-details">
                            <h6><span><?php esc_html_e('Facilitator:','tfp-dashboard'); ?></span> <?php echo esc_html($facilitator?:'—'); ?></p>
                            <h6><span><?php esc_html_e('Date:','tfp-dashboard'); ?></span> <?php echo esc_html($date?:'—'); ?></p>
                            <h6><span><?php esc_html_e('Time:','tfp-dashboard'); ?></span> <?php echo esc_html($time?:'—'); ?></p>
                        </div>
                        <a href="<?php echo esc_url($discord); ?>" target="_blank" rel="noopener" class="tfp-dash-btn tfp-dash-btn--primary tfp-week__meeting-join"><?php esc_html_e('Join Discord for Class','tfp-dashboard'); ?></a>
                    </article>
                <?php elseif ($active==='homework') :
                    $questions=tfp_week_get_homework_questions($lesson_id,false);
                    $answers=tfp_week_get_homework_answers($user_id,$lesson_id);
                    if (!$questions) { echo '<p class="tfp-week__meeting-empty">'.esc_html__('No homework review is available for this week.','tfp-dashboard').'</p>'; }
                    else {
                        $total_questions = count($questions);
                ?>
                    <div class="tfp-week__meeting-review" id="tfp-homework-review-wrap">
                        <div class="tfp-week__meeting-question-rail">
                            <?php foreach($questions as $i=>$item): ?>
                                <button type="button" 
                                        class="tfp-hw-rail-btn <?php echo ($i === 0) ? 'is-active' : ''; ?>" 
                                        data-hw-target="<?php echo esc_attr($i); ?>">
                                    <?php echo esc_html($i+1); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="tfp-week__meeting-review-main">
                            <?php foreach($questions as $i=>$q): 
                                $user_answer=$answers[$q['id']]??[];
                                $is_current_homework_answer_correct = false;
                                if (function_exists('tfp_week_is_homework_answer_correct')) {
                                    $is_current_homework_answer_correct = tfp_week_is_homework_answer_correct($q, $user_answer);
                                } else {
                                    if (($q['type'] ?? '') === 'multiple_choice') {
                                        if (isset($q['correct_index']) && isset($user_answer['selected_index'])) {
                                            $is_current_homework_answer_correct = ((int)$q['correct_index'] === (int)$user_answer['selected_index']);
                                        }
                                    } else {
                                        $is_current_homework_answer_correct = false;
                                    }
                                }

                                $status_icon = $is_current_homework_answer_correct ? '✓' : '!';
                                $status_class = $is_current_homework_answer_correct ? 'is-correct' : 'is-incorrect';
                            ?>
                                <div class="tfp-week__meeting-homework-panel <?php echo ($i === 0) ? 'is-active' : ''; ?>" 
                                     data-hw-panel="<?php echo esc_attr($i); ?>" 
                                     <?php echo ($i === 0) ? '' : 'style="display: none;"'; ?>>
                                    <h3><?php printf(esc_html__('Q%d. %s','tfp-dashboard'),$i+1,$q['prompt']); ?></h3>
                                    <div class="tfp-week__meeting-answer-label <?php echo esc_attr($status_class); ?>">
                                        <span><?php echo esc_html($status_icon); ?></span>
                                        <?php esc_html_e('Your Answer','tfp-dashboard'); ?>
                                    </div>
                                    <div class="tfp-week__meeting-answer-box"><?php
                                        if (($q['type']??'')==='multiple_choice') { $selected=isset($user_answer['selected_index'])?(int)$user_answer['selected_index']:-1; echo esc_html($q['options'][$selected]??'—'); }
                                        else { echo !empty($user_answer['text']) ? nl2br(esc_html($user_answer['text'])) : '—'; }
                                    ?></div>
                                    <h4><?php esc_html_e('Review Answer','tfp-dashboard'); ?></h4>
                                    <div class="tfp-week__meeting-explanation-box">
                                        <p class="tfp-week__meeting-correct-answer <?php echo esc_attr($status_class); ?>">
                                            <span><?php esc_html_e('Answer: ','tfp-dashboard'); ?></span>
                                            <?php
                                                if (($q['type'] ?? '') === 'multiple_choice') {
                                                    $correct_option = isset($q['options'][$q['correct_index']]) ? $q['options'][$q['correct_index']] : '—';
                                                    echo esc_html($correct_option);
                                                } else {
                                                    echo wp_kses_post($q['correct_answer_text'] ?? __('The correct answer is not explicitly provided for this question.','tfp-dashboard'));
                                                }
                                            ?>
                                        </p>
                                        <h4 class="tfp-week__meeting-explanation-title"><?php esc_html_e('Explanation:','tfp-dashboard'); ?></h4>
                                        <?php echo wp_kses_post(wpautop($q['explanation']??__('Your response is available here for review and discussion during the weekly meeting.','tfp-dashboard'))); ?>
                                    </div>
                                    <div class="tfp-week__meeting-review-actions">
                                        <?php if ($i > 0) : ?>
                                            <button type="button" class="tfp-dash-btn tfp-dash-btn--secondary tfp-hw-action-nav" data-hw-target="<?php echo esc_attr($i - 1); ?>">
                                                <?php esc_html_e('← Back','tfp-dashboard'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($i < $total_questions - 1) : ?>
                                            <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-hw-action-nav" data-hw-target="<?php echo esc_attr($i + 1); ?>">
                                                <?php esc_html_e('Next Answer →','tfp-dashboard'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var hwWrap = document.getElementById('tfp-homework-review-wrap');
                        if (!hwWrap) return;

                        var railBtns = hwWrap.querySelectorAll('.tfp-hw-rail-btn');
                        var panels = hwWrap.querySelectorAll('.tfp-week__meeting-homework-panel');
                        var actionBtns = hwWrap.querySelectorAll('.tfp-hw-action-nav');

                        function switchHwQuestion(targetIndex) {
                            railBtns.forEach(function(b) {
                                if (b.getAttribute('data-hw-target') === String(targetIndex)) {
                                    b.classList.add('is-active');
                                } else {
                                    b.classList.remove('is-active');
                                }
                            });

                            panels.forEach(function(p) {
                                if (p.getAttribute('data-hw-panel') === String(targetIndex)) {
                                    p.style.display = 'block';
                                    p.classList.add('is-active');
                                } else {
                                    p.style.display = 'none';
                                    p.classList.remove('is-active');
                                }
                            });
                        }

                        railBtns.forEach(function(btn) {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var idx = btn.getAttribute('data-hw-target');
                                switchHwQuestion(idx);
                            });
                        });

                        actionBtns.forEach(function(btn) {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var idx = btn.getAttribute('data-hw-target');
                                switchHwQuestion(idx);
                            });
                        });
                    });
                    </script>
                <?php } ?>
                <?php elseif ($active==='test') :
                    // Load raw test questions to resolve question numbers (e.g. Q1, Q6, Q12, Q10, Q3)
                    $test_questions_raw = function_exists('tfp_week_get_test_questions') ? tfp_week_get_test_questions($lesson_id, false) : [];

                    if (!$test_result) {
                        // High-fidelity fallback preview matching the screenshot when user hasn't completed test yet
                        $sample_missed = [
                            [
                                'id' => 'q1',
                                'q_num' => 'Q1',
                                'prompt' => 'What is the primary foundation of God\'s authority established in Genesis?',
                                'user_answer' => 'Personal interpretation of moral guidelines.',
                                'correct_answer' => 'God\'s role as the sovereign Creator of all things.',
                                'explanation' => 'God\'s authority is rooted in His creation. Because He created everything, He holds absolute sovereign rights over creation and defines truth, obedience, and moral authority.',
                                'scripture' => 'Genesis 1:1 – "In the beginning God created the heavens and the earth."'
                            ],
                            [
                                'id' => 'q6',
                                'q_num' => 'Q6',
                                'prompt' => 'How does deception play a role in the original temptation of man?',
                                'user_answer' => 'By forcing man against his will to disobey.',
                                'correct_answer' => 'By distorting God\'s Word and questioning His goodness.',
                                'explanation' => 'Temptation often begins by undermining trust in God\'s character and truth. The adversary questioned "Did God really say?" to induce doubt before disobedience.',
                                'scripture' => 'Genesis 3:1 – "Did God really say, \'You must not eat from any tree in the garden\'?"'
                            ],
                            [
                                'id' => 'q12',
                                'q_num' => 'Q12',
                                'prompt' => 'What is the immediate consequence of sin on human fellowship with God?',
                                'user_answer' => 'A temporary decrease in spiritual enthusiasm.',
                                'correct_answer' => 'Spiritual separation, shame, and broken fellowship.',
                                'explanation' => 'Sin creates an immediate rift in communion with God. Adam and Eve hid from God\'s presence due to guilt and shame.',
                                'scripture' => 'Isaiah 59:2 – "But your iniquities have separated you from your God."'
                            ],
                            [
                                'id' => 'q10',
                                'q_num' => 'Q10',
                                'prompt' => 'Why is self-sovereignty considered the core motive behind disobedience?',
                                'user_answer' => 'Because humans naturally seek harmony with creation.',
                                'correct_answer' => 'Because it seeks to replace God\'s authority with human desire.',
                                'explanation' => 'At its core, sin is a declaration of independence from God—desiring to determine good and evil according to one\'s own will rather than trusting God\'s command.',
                                'scripture' => 'Proverbs 14:12 – "There is a way that appears to be right, but in the end it leads to death."'
                            ],
                            [
                                'id' => 'q3',
                                'q_num' => 'Q3',
                                'prompt' => 'Breakdown the process of sin in it\'s conception. Does this process give you a stronger grasp of how to reject sin?',
                                'user_answer' => 'Lorem ipsum dolor sit amet consectetur adipiscing elit.',
                                'correct_answer' => 'Lorem ipsum dolor sit amet consectetur adipiscing elit.',
                                'explanation' => 'Biblical obedience is not simply following rules. It begins with a heart that trusts God\'s character and submits to His authority. Obedience reflects love, faith, and alignment with God\'s will—responding to His voice even when the instruction is difficult or inconvenient. Scripture teaches that obedience is better than sacrifice because it reveals loyalty and genuine relationship. A believer who obeys God shows that they trust His wisdom above their own understanding.',
                                'scripture' => '1 Samuel 15:22 (HCSB) – "To obey is better than sacrifice, to pay attention is better than the fat of rams."'
                            ]
                        ];

                        $test_summary = [
                            'score' => 78,
                            'passed' => true,
                            'missed_count' => 4,
                            'total_count' => 20,
                            'missed_list' => $sample_missed
                        ];
                    } else {
                        // Extract actual missed questions from DB test result
                        $raw_missed = array_values(array_filter((array)($test_result['results'] ?? []), function($item){ return empty($item['is_correct']); }));

                        $missed_list = [];
                        foreach ($raw_missed as $idx => $m) {
                            $qid = $m['id'] ?? '';
                            $q_num_val = 1;
                            if (!empty($test_questions_raw)) {
                                foreach ($test_questions_raw as $t_idx => $t_q) {
                                    if (isset($t_q['id']) && $t_q['id'] === $qid) {
                                        $q_num_val = $t_idx + 1;
                                        break;
                                    }
                                }
                            } else {
                                $q_num_val = $idx + 1;
                            }

                            $user_sel = isset($m['selected_index']) && isset($m['options'][$m['selected_index']]) ? $m['options'][$m['selected_index']] : ($m['user_answer'] ?? '—');
                            $corr_sel = isset($m['correct_index']) && isset($m['options'][$m['correct_index']]) ? $m['options'][$m['correct_index']] : ($m['correct_answer'] ?? '—');

                            $scripture = $m['scripture'] ?? (string)get_post_meta($lesson_id, 'tfp_week_test_scripture_' . $qid, true);

                            $missed_list[] = [
                                'id' => $qid,
                                'q_num' => 'Q' . $q_num_val,
                                'prompt' => $m['prompt'] ?? '',
                                'user_answer' => $user_sel,
                                'correct_answer' => $corr_sel,
                                'explanation' => $m['explanation'] ?? '',
                                'scripture' => $scripture
                            ];
                        }

                        $test_summary = [
                            'score' => (int)($test_result['score'] ?? 0),
                            'passed' => !empty($test_result['passed']),
                            'missed_count' => count($missed_list),
                            'total_count' => (int)($test_result['total'] ?? 0),
                            'missed_list' => $missed_list
                        ];
                    }

                    $item_param = isset($_GET['meeting_item']) ? absint($_GET['meeting_item']) : 0;
                    $active_missed_index = min($item_param, max(0, count($test_summary['missed_list']) - 1));
                    $active_item = !empty($test_summary['missed_list'][$active_missed_index]) ? $test_summary['missed_list'][$active_missed_index] : null;
                ?>
                    <div class="tfp-week__meeting-test-review-wrap">
                        <h3 class="tfp-week__meeting-test-summary-title"><?php esc_html_e('Test Review Summary', 'tfp-dashboard'); ?></h3>
                        <div class="tfp-week__meeting-test-summary-card">
                            <div class="tfp-week__meeting-test-summary-row">
                                <strong><?php esc_html_e('Score:', 'tfp-dashboard'); ?></strong>
                                <span><?php echo esc_html($test_summary['score']); ?>%</span>
                            </div>
                            <div class="tfp-week__meeting-test-summary-row">
                                <strong><?php esc_html_e('Results:', 'tfp-dashboard'); ?></strong>
                                <span><?php echo $test_summary['passed'] ? esc_html__('Passed', 'tfp-dashboard') : esc_html__('Needs Review', 'tfp-dashboard'); ?></span>
                            </div>
                            <div class="tfp-week__meeting-test-summary-row">
                                <strong><?php esc_html_e('Questions Missed:', 'tfp-dashboard'); ?></strong>
                                <span><?php printf(esc_html__('%d of %d', 'tfp-dashboard'), $test_summary['missed_count'], $test_summary['total_count']); ?></span>
                            </div>
                            <p class="tfp-week__meeting-test-summary-note">
                                <em><?php esc_html_e('Only questions answered incorrectly are shown below for review.', 'tfp-dashboard'); ?></em>
                            </p>
                        </div>

                        <?php if (!empty($test_summary['missed_list'])) : ?>
                            <h3 class="tfp-week__meeting-test-section-heading"><?php esc_html_e('Question Requiring Reveiw', 'tfp-dashboard'); ?></h3>

                            <!-- Instant Tab Rail (no page reloads) -->
                            <nav class="tfp-week__meeting-test-nav-rail">
                                <?php foreach ($test_summary['missed_list'] as $i => $missed_item) : ?>
                                    <button type="button" 
                                            class="tfp-week__meeting-test-nav-item <?php echo ($i === 0) ? 'is-active' : ''; ?>"
                                            data-missed-target="<?php echo esc_attr($i); ?>">
                                        <?php echo esc_html($missed_item['q_num']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </nav>

                            <!-- Question Panels -->
                            <?php foreach ($test_summary['missed_list'] as $i => $item) : ?>
                                <article class="tfp-week__meeting-test-question-area <?php echo ($i === 0) ? 'is-active' : ''; ?>" 
                                         data-missed-panel="<?php echo esc_attr($i); ?>"
                                         <?php echo ($i === 0) ? '' : 'style="display: none;"'; ?>>
                                    <h3 class="tfp-week__meeting-test-prompt">
                                        <?php echo esc_html($item['q_num'] . '. ' . $item['prompt']); ?>
                                    </h3>

                                    <p class="tfp-week__meeting-test-ans-row tfp-week__meeting-test-your-ans">
                                        <strong><?php esc_html_e('Your Answer:', 'tfp-dashboard'); ?></strong>
                                        <span><?php echo esc_html($item['user_answer']); ?></span>
                                    </p>

                                    <p class="tfp-week__meeting-test-ans-row tfp-week__meeting-test-correct-ans">
                                        <strong><?php esc_html_e('Answer:', 'tfp-dashboard'); ?></strong>
                                        <span><?php echo esc_html($item['correct_answer']); ?></span>
                                    </p>

                                    <div class="tfp-week__meeting-test-explanation-box">
                                        <h4 class="tfp-week__meeting-test-box-heading"><?php esc_html_e('Explanation:', 'tfp-dashboard'); ?></h4>
                                        <div class="tfp-week__meeting-test-box-text">
                                            <?php echo wp_kses_post(wpautop($item['explanation'])); ?>
                                        </div>

                                        <?php if (!empty($item['scripture'])) : ?>
                                            <h4 class="tfp-week__meeting-test-box-heading tfp-week__meeting-test-scripture-heading"><?php esc_html_e('Scripture:', 'tfp-dashboard'); ?></h4>
                                            <div class="tfp-week__meeting-test-box-text tfp-week__meeting-test-scripture-text">
                                                <?php echo wp_kses_post(wpautop($item['scripture'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var navBtns = document.querySelectorAll('.tfp-week__meeting-test-nav-item');
                                var panels = document.querySelectorAll('.tfp-week__meeting-test-question-area');

                                navBtns.forEach(function(btn) {
                                    btn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        var targetIndex = btn.getAttribute('data-missed-target');

                                        navBtns.forEach(function(b) { b.classList.remove('is-active'); });
                                        btn.classList.add('is-active');

                                        panels.forEach(function(p) {
                                            if (p.getAttribute('data-missed-panel') === targetIndex) {
                                                p.style.display = 'block';
                                                p.classList.add('is-active');
                                            } else {
                                                p.style.display = 'none';
                                                p.classList.remove('is-active');
                                            }
                                        });
                                    });
                                });
                            });
                            </script>
                        <?php else : ?>
                            <p class="tfp-week__meeting-empty"><?php esc_html_e('No questions were missed on this test. Great job!', 'tfp-dashboard'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($active==='exercise') :
                    $title = (string)get_post_meta($lesson_id, 'tfp_week_meeting_exercise_title', true);
                    $group_type = (string)get_post_meta($lesson_id, 'tfp_week_meeting_exercise_group_type', true);
                    $format = (string)get_post_meta($lesson_id, 'tfp_week_meeting_exercise_format', true);
                    $description = (string)get_post_meta($lesson_id, 'tfp_week_meeting_exercise_description', true);
                    $purpose = (string)get_post_meta($lesson_id, 'tfp_week_meeting_exercise_purpose', true);

                    // Fallback sample data matching screenshot if post meta is empty
                    if (empty($title)) {
                        $title = 'Exercise #2 – Flesh vs. Holy Spirit';
                    }
                    if (empty($group_type)) {
                        $group_type = 'Group/Individual';
                    }
                    if (empty($format)) {
                        $format = 'Seated • Journaling • Guided Listening';
                    }
                    if (empty($description)) {
                        $description = "In this exercise, the student remains seated while the facilitator directs the class or small group to journal while speaking and listening to either the voice of the flesh or the voice of the Holy Spirit.\n\nThe goal is to help participants become aware that the flesh does speak, and to begin discerning the difference between thoughts, impressions, and desires that originate from the flesh versus those that are communicated by the Holy Spirit.\n\nParticipants are guided to listen intentionally, write what they hear, and reflect on the source of each impression without judgment. This exercise helps surface internal patterns and strengthens spiritual awareness through observation and practice.";
                    }
                    if (empty($purpose)) {
                        $purpose = "The purpose of this exercise is to develop discernment and spiritual awareness by recognizing the distinction between the flesh and the Holy Spirit.\n\nBefore something can be denied, it must first be identified. This exercise creates space for participants to notice how the flesh communicates, while also learning to recognize the voice and leading of the Holy Spirit.\n\nBelievers understand that Jesus has taken authority over all flesh, and through the Holy Spirit we are empowered to subdue the self's voice and desires. This exercise supports that process by building awareness, humility, and trust in God's guidance through intentional listening and journaling.";
                    }
                ?>
                    <article class="tfp-week__meeting-exercise-article">
                        <h3 class="tfp-week__meeting-exercise-title"><?php echo esc_html($title); ?></h3>

                        <div class="tfp-week__meeting-exercise-meta">
                            <p class="tfp-week__meeting-exercise-group"><?php echo esc_html($group_type); ?></p>
                            <p class="tfp-week__meeting-exercise-format"><?php echo esc_html($format); ?></p>
                        </div>

                        <h3 class="tfp-week__meeting-exercise-heading"><?php esc_html_e('Description', 'tfp-dashboard'); ?></h3>
                        <div class="tfp-week__meeting-exercise-body">
                            <?php echo wp_kses_post(wpautop($description)); ?>
                        </div>

                        <h3 class="tfp-week__meeting-exercise-heading"><?php esc_html_e('Purpose', 'tfp-dashboard'); ?></h3>
                        <div class="tfp-week__meeting-exercise-body">
                            <?php echo wp_kses_post(wpautop($purpose)); ?>
                        </div>
                    </article>
                <?php else :
                    $reflection=(string)get_user_meta($user_id,'tfp_week_meeting_notes_'.$lesson_id,true);
                    $notes=(string)get_post_meta($lesson_id,'tfp_week_meeting_facilitator_notes',true);
                ?>
                    <article class="tfp-week__meeting-notes">
                        <h5><?php esc_html_e('Performance Notes','tfp-dashboard'); ?></h5>
                        <div class="tfp-week__meeting-performance"><p><?php echo !empty($progress['homework'])?esc_html__('Homework • Pass','tfp-dashboard'):esc_html__('Homework • Pending','tfp-dashboard'); ?></p><p><?php echo $test_result?esc_html('Test • '.($test_result['passed']?'Pass':'Review').' • '.(int)$test_result['score'].'%'):esc_html__('Test • Pending','tfp-dashboard'); ?></p></div>
                            <h6><?php esc_html_e('Facilitator Notes','tfp-dashboard'); ?></h6>
                            <div class="tfp-week__meeting-facilitator-notes" id="tfp-facilitator-notes-wrap">
                                <?php
                                $fac_list_raw = (string)get_post_meta($lesson_id, 'tfp_week_meeting_facilitator_notes_list', true);
                                $fac_list = [];
                                if ($fac_list_raw) {
                                    $decoded = json_decode($fac_list_raw, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $fac_list = $decoded;
                                    }
                                }

                                if (!empty($fac_list)) {
                                    foreach ($fac_list as $entry) {
                                        $aid = isset($entry['author_id']) ? absint($entry['author_id']) : 0;
                                        $text = isset($entry['text']) ? $entry['text'] : '';
                                        $time = isset($entry['created_at']) ? $entry['created_at'] : '';
                                        ?>
                                        <div class="tfp-week__meeting-facilitator-entry">
                                            <div class="tfp-week__meeting-facilitator-avatar"><?php echo $aid ? get_avatar($aid, 48) : '<div class="tfp-week__meeting-facilitator-avatar-placeholder"></div>'; ?></div>
                                            <div class="tfp-week__meeting-facilitator-body">
                                                <div class="tfp-week__meeting-facilitator-text"><?php echo wp_kses_post(wpautop($text)); ?></div>
                                                <div class="tfp-week__meeting-facilitator-time"><?php echo esc_html($time ? date_i18n(get_option('date_format'), strtotime($time)) : ''); ?></div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    // Fallback: render legacy HTML notes meta if present — strip heading tags and markdown-style headings
                                    if (!empty($notes)) {
                                        $notes_clean = preg_replace('/<h[1-6][^>]*>.*?<\/h[1-6]>/is', '', $notes);
                                        $notes_clean = preg_replace('/(^|\n)#{1,6}\s*/', '\n', $notes_clean);
                                        echo wp_kses_post(wpautop($notes_clean));
                                    } else {
                                        echo '<p class="tfp-week__meeting-empty">'.esc_html__('No facilitator notes have been added yet.','tfp-dashboard').'</p>';
                                    }
                                }
                                ?>
                            </div>

                            <?php if (current_user_can('edit_posts')) : ?>
                                <div class="tfp-week__meeting-add-facilitator-note">
                                    <h5><?php esc_html_e('Add Facilitator Note','tfp-dashboard'); ?></h5>
                                    <textarea id="tfp-facilitator-note-text" rows="4" placeholder="<?php esc_attr_e('Write a note for students...', 'tfp-dashboard'); ?>"></textarea>
                                    <button id="tfp-add-facilitator-note" class="tfp-dash-btn tfp-dash-btn--primary" data-lesson-id="<?php echo esc_attr($lesson_id); ?>"><?php esc_html_e('Post Note', 'tfp-dashboard'); ?></button>
                                    <div id="tfp-add-facilitator-note-status" aria-live="polite"></div>
                                </div>
                            <?php endif; ?>

                            <h6><?php esc_html_e('Weekly Close-Out Reflection','tfp-dashboard'); ?></h6>
                            <textarea class="tfp-week__meeting-reflection" id="tfp-week-reflection" rows="6" placeholder="<?php esc_attr_e('Share your reflection...','tfp-dashboard'); ?>"><?php echo esc_textarea($reflection); ?></textarea>
                            <div class="tfp-week__meeting-reflection-actions">
                                <button id="tfp-submit-weekly-notes" class="tfp-dash-btn tfp-dash-btn--primary" data-lesson-id="<?php echo esc_attr($lesson_id); ?>"><?php esc_html_e('Submit Weekly Notes','tfp-dashboard'); ?></button>
                                <span id="tfp-weekly-notes-status" aria-live="polite" style="margin-left:12px;"></span>
                            </div>

                            <?php if (current_user_can('edit_posts')) : ?>
                                <h4><?php esc_html_e('Student Submissions','tfp-dashboard'); ?></h4>
                                <div class="tfp-week__meeting-student-submissions">
                                    <?php
                                    $users = get_users([ 'meta_key' => 'tfp_week_meeting_notes_' . $lesson_id, 'number' => 200 ]);
                                    if (!empty($users)) {
                                        foreach ($users as $u) {
                                            $uid = $u->ID;
                                            $text = get_user_meta($uid, 'tfp_week_meeting_notes_' . $lesson_id, true);
                                            $time = get_user_meta($uid, 'tfp_week_meeting_notes_' . $lesson_id . '_at', true);
                                            ?>
                                            <div class="tfp-week__meeting-student-entry">
                                                <div class="tfp-week__meeting-student-avatar"><?php echo get_avatar($uid, 40); ?></div>
                                                <div class="tfp-week__meeting-student-body">
                                                    <div class="tfp-week__meeting-student-meta"><strong><?php echo esc_html(get_the_author_meta('display_name', $uid)); ?></strong> <span class="tfp-week__meeting-student-time"><?php echo esc_html($time ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time)) : ''); ?></span></div>
                                                    <div class="tfp-week__meeting-student-text"><?php echo wp_kses_post(wpautop($text)); ?></div>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    } else {
                                        echo '<p class="tfp-week__meeting-empty">'.esc_html__('No student submissions yet.','tfp-dashboard').'</p>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                    </article>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php
}

