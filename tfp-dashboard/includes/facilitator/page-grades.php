<?php
if (!defined('ABSPATH')) exit;

function tfp_facilitator_grades_state() {
    $course = isset($_GET['tfp_fg_course']) ? absint($_GET['tfp_fg_course']) : 0;
    $cohort = isset($_GET['tfp_fg_cohort']) ? absint($_GET['tfp_fg_cohort']) : 0;
    $week = isset($_GET['tfp_fg_week']) ? absint($_GET['tfp_fg_week']) : 0;
    $search = isset($_GET['tfp_fg_search']) ? sanitize_text_field(wp_unslash($_GET['tfp_fg_search'])) : '';
    if ($cohort && function_exists('tfp_facilitator_cohort_course_id')) {
        $linked = (int) tfp_facilitator_cohort_course_id($cohort);
        if ($linked) $course = $linked;
    }
    return compact('course','cohort','week','search');
}

function tfp_facilitator_grades_course_options($cohorts) {
    $out = [];
    foreach ((array)$cohorts as $cohort) {
        $cid = (int)get_post_meta($cohort->ID,'_related_course',true);
        if ($cid) $out[$cid] = get_the_title($cid);
    }
    asort($out, SORT_NATURAL|SORT_FLAG_CASE);
    return $out;
}

function tfp_facilitator_grades_cohort_options($cohorts,$course=0) {
    $out=[];
    foreach ((array)$cohorts as $cohort) {
        $cid=(int)get_post_meta($cohort->ID,'_related_course',true);
        if ($course && $cid !== (int)$course) continue;
        $out[(int)$cohort->ID]=$cohort->post_title;
    }
    asort($out, SORT_NATURAL|SORT_FLAG_CASE);
    return $out;
}

function tfp_facilitator_grades_visible_cohorts($cohorts,$state) {
    $out=[];
    foreach ((array)$cohorts as $cohort) {
        $cohort_id=(int)$cohort->ID;
        $course_id=(int)get_post_meta($cohort_id,'_related_course',true);
        if ($state['course'] && $course_id !== (int)$state['course']) continue;
        if ($state['cohort'] && $cohort_id !== (int)$state['cohort']) continue;
        if ($state['search'] !== '') {
            $needle=strtolower($state['search']);
            $hay=strtolower($cohort->post_title.' '.($course_id?get_the_title($course_id):''));
            $match=strpos($hay,$needle)!==false;
            if (!$match && function_exists('tfp_facilitator_cohort_students')) {
                foreach (tfp_facilitator_cohort_students($cohort_id) as $sid) {
                    $u=get_userdata($sid);
                    if ($u && strpos(strtolower($u->display_name.' '.$u->user_email),$needle)!==false) {$match=true;break;}
                }
            }
            if (!$match) continue;
        }
        $out[]=$cohort;
    }
    return $out;
}

function tfp_facilitator_grades_weeks($course_id) {
    return ($course_id && function_exists('tfp_ld_get_weeks')) ? array_values((array)tfp_ld_get_weeks($course_id)) : [];
}

function tfp_facilitator_grades_current_lesson($cohort_id,$course_id) {
    $weeks=tfp_facilitator_grades_weeks($course_id);
    $n=function_exists('tfp_facilitator_cohort_week') ? (int)tfp_facilitator_cohort_week($cohort_id,$course_id) : 1;
    return isset($weeks[$n-1]) ? $weeks[$n-1] : null;
}

function tfp_facilitator_grades_letter_avg($student_ids,$course_id) {
    if (!function_exists('tfp_grade_scale')) return '';
    $scale=tfp_grade_scale(); $points=[];
    foreach(array_unique(array_map('absint',(array)$student_ids)) as $sid){
        $g=function_exists('tfp_grade_overall')?tfp_grade_overall($sid,$course_id):'';
        if($g!=='' && isset($scale[$g])) $points[]=$scale[$g];
    }
    if(!$points) return '';
    return array_flip($scale)[(int)round(array_sum($points)/count($points))] ?? '';
}

function tfp_facilitator_grades_letter($pct) {
    if($pct===null || !is_numeric($pct)) return '';
    $pct=(float)$pct;
    return $pct>=90?'A':($pct>=80?'B':($pct>=70?'C':($pct>=60?'D':'F')));
}

function tfp_facilitator_grades_hw_pct($sid,$lesson_id) {
    return function_exists('tfp_grades_get_homework_percent') ? tfp_grades_get_homework_percent($sid,$lesson_id) : null;
}

function tfp_facilitator_grades_attendance($sid,$lesson_id) {
    return function_exists('tfp_facilitator_attendance_get_record') ? strtoupper((string)(tfp_facilitator_attendance_get_record($sid,$lesson_id)['status'] ?? '')) : '';
}

function tfp_facilitator_grades_badge($letter) {
    $letter=strtoupper((string)$letter);
    if($letter==='') return '<span class="tfp-fg-pill tfp-fg-pill--empty">—</span>';
    $cls=in_array($letter,['A','B','C','D','F'],true)?strtolower($letter):'default';
    return '<span class="tfp-fg-pill tfp-fg-pill--'.$cls.'">'.esc_html($letter).'</span>';
}

function tfp_facilitator_grades_attendance_badge($status) {
    $map=['P'=>['PRE','pre'],'EA'=>['EA','ea'],'UA'=>['UA','ua']];
    if(!isset($map[$status])) return '<span class="tfp-fg-attendance tfp-fg-attendance--empty">—</span>';
    return '<span class="tfp-fg-attendance tfp-fg-attendance--'.$map[$status][1].'">'.$map[$status][0].'</span>';
}

function tfp_facilitator_grades_week_stats($student_ids,$lesson_id) {
    $total=count($student_ids); $p=$ea=$ua=0; $home_total=$home_done=0; $test_scores=[];
    foreach(array_unique(array_map('absint',(array)$student_ids)) as $sid){
        $a=tfp_facilitator_grades_attendance($sid,$lesson_id);
        if($a==='P')$p++; elseif($a==='EA')$ea++; elseif($a==='UA')$ua++;
        if($lesson_id && function_exists('tfp_week_homework_progress')){
            $hp=tfp_week_homework_progress($sid,$lesson_id);
            if(($hp['total']??0)>0){$home_total++; if(($hp['completed']??0)>=($hp['total']??0))$home_done++;}
        }
        if($lesson_id && function_exists('tfp_week_get_test_result')){
            $tr=tfp_week_get_test_result($sid,$lesson_id);
            if(is_array($tr)&&isset($tr['score'])) $test_scores[]=(float)$tr['score'];
        }
    }
    return [
        'attendance_main'=>$total?sprintf('%d/%d',$p,$total):'—',
        'attendance_sub'=>sprintf('%d Present • %d Excused • %d Unexcused',$p,$ea,$ua),
        'homework'=>$home_total?(int)round($home_done/$home_total*100):null,
        'test'=>$test_scores?(int)round(array_sum($test_scores)/count($test_scores)):null,
        'present'=>$p,'excused'=>$ea,'unexcused'=>$ua,'total'=>$total
    ];
}

function tfp_facilitator_grades_alerts($student_ids,$lesson_id) {
    $stalled=$blocked=0;
    foreach(array_unique(array_map('absint',(array)$student_ids)) as $sid){
        if(!$lesson_id || !function_exists('tfp_ld_get_week_progress')) continue;
        $pr=(array)tfp_ld_get_week_progress($sid,$lesson_id); $signals=0;
        foreach(['reading','homework','quiz','test'] as $k) if(!empty($pr[$k])) $signals++;
        if($signals===0){$blocked++;continue;}
        if(!empty($pr['homework'])&&is_array($pr['homework'])){
            $h=$pr['homework']; if((int)($h['total']??0)>0 && (int)($h['completed']??0)<(int)($h['total']??0)) $stalled++;
        }
    }
    return ['stalled'=>$stalled,'blocked'=>$blocked,'total'=>$stalled+$blocked];
}

function tfp_facilitator_grades_cohort_summary($cohort,$week_num=0) {
    $cid=(int)$cohort->ID; $course=(int)get_post_meta($cid,'_related_course',true);
    $students=function_exists('tfp_facilitator_cohort_students')?(array)tfp_facilitator_cohort_students($cid):[];
    $weeks=tfp_facilitator_grades_weeks($course);
    $current=function_exists('tfp_facilitator_cohort_week')?(int)tfp_facilitator_cohort_week($cid,$course):1;
    $selected=$week_num>0?$week_num:$current;
    $lesson=isset($weeks[$selected-1])?$weeks[$selected-1]:null;
    $lesson_id=$lesson?(int)$lesson->ID:0;
    $stats=tfp_facilitator_grades_week_stats($students,$lesson_id);
    $avg=tfp_facilitator_grades_letter_avg($students,$course);
    $test_scores=[];
    foreach($students as $sid) foreach($week_num>0?[$lesson_id]:array_map(fn($w)=>(int)$w->ID,$weeks) as $lid){
        if($lid&&function_exists('tfp_week_get_test_result')){$r=tfp_week_get_test_result($sid,$lid);if(is_array($r)&&isset($r['score']))$test_scores[]=(float)$r['score'];}
    }
    if($week_num===0 && $test_scores)$stats['test']=(int)round(array_sum($test_scores)/count($test_scores));
    if($week_num===0 && function_exists('tfp_facilitator_attendance_report')){
        $rep=tfp_facilitator_attendance_report([$cohort]); $stats['attendance_main']=!empty($rep['avg_attendance'])?$rep['avg_attendance'].'%':'—'; $stats['attendance_sub']=__('Across recorded attendance','tfp-dashboard');
    }
    $alerts=tfp_facilitator_grades_alerts($students,$lesson_id);
    return compact('cid','course','students','weeks','selected','lesson','lesson_id','stats','avg','alerts');
}

function tfp_facilitator_grades_student_rows($cohort,$week_num) {
    $s=tfp_facilitator_grades_cohort_summary($cohort,$week_num);
    $rows=[];
    foreach($s['students'] as $sid){
        $u=get_userdata($sid); if(!$u||!$s['lesson_id'])continue;
        $tr=function_exists('tfp_week_get_quiz_result')?tfp_week_get_quiz_result($sid,$s['lesson_id']):null;
        $te=function_exists('tfp_week_get_test_result')?tfp_week_get_test_result($sid,$s['lesson_id']):null;
        $rows[]=[
            'id'=>$sid,'name'=>$u->display_name?:$u->user_email,
            'attendance'=>tfp_facilitator_grades_attendance($sid,$s['lesson_id']),
            'homework'=>tfp_facilitator_grades_letter(tfp_facilitator_grades_hw_pct($sid,$s['lesson_id'])),
            'quiz'=>tfp_facilitator_grades_letter(is_array($tr)?($tr['score']??null):null),
            'test'=>tfp_facilitator_grades_letter(is_array($te)?($te['score']??null):null),
            'overall'=>function_exists('tfp_grade_overall')?tfp_grade_overall($sid,$s['course']):''
        ];
    }
    usort($rows,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
    return [$s,$rows];
}

function tfp_facilitator_grades_render_header($items) {
    $home=tfp_dashboard_get_url('tfp-dashboard-facilitator-home'); $grades=tfp_dashboard_get_url('tfp-dashboard-facilitator-grades');
    echo '<header class="tfp-dash-pageheader tfp-facilitator-header tfp-facilitator-grades-header">';
    echo '<button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="'.esc_attr__('Open facilitator menu','tfp-dashboard').'">'.tfp_dashboard_icon('toggle').'</button>';
    echo '<div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">';
    foreach($items as $i=>$it){if($i)echo '<span aria-hidden="true">/</span>'; if(!empty($it['url']))echo '<a href="'.esc_url($it['url']).'">'.esc_html($it['label']).'</a>';else echo '<strong>'.esc_html($it['label']).'</strong>';}
    echo '</div><h1 class="tfp-dash-pageheader__title">Grades</h1>';
    echo '<p class="tfp-dash-pageheader__subtitle">'.esc_html($items[2]['subtitle']??'High-level performance tracking across all active cohorts.').'</p>';
    echo '</header>';
}

function tfp_dashboard_render_facilitator_grades_content() {
    $all=function_exists('tfp_facilitator_current_cohorts')?(array)tfp_facilitator_current_cohorts():[];
    $state=tfp_facilitator_grades_state();
    $courses=tfp_facilitator_grades_course_options($all);
    $cohort_opts=tfp_facilitator_grades_cohort_options($all,$state['course']);
    $visible=tfp_facilitator_grades_visible_cohorts($all,$state);
    $selected=null;
    foreach($all as $c)if((int)$c->ID===(int)$state['cohort']){$selected=$c;break;}
    if($selected){
        $course=(int)get_post_meta($selected->ID,'_related_course',true); $weeks=tfp_facilitator_grades_weeks($course);
        if($state['week']>count($weeks))$state['week']=0;
        [$sum,$rows]=tfp_facilitator_grades_student_rows($selected,$state['week']);
        $home=tfp_dashboard_get_url('tfp-dashboard-facilitator-grades');
        $crumbs=[
            ['label'=>'Facilitator','url'=>tfp_dashboard_get_url('tfp-dashboard-facilitator-home')],
            ['label'=>'Grades','url'=>$home],
            ['label'=>$selected->post_title,'url'=>null,'subtitle'=>($course?get_the_title($course):'').' • '.$selected->post_title]
        ];
        if($state['week'])$crumbs[]=['label'=>'Week '.$state['week'],'url'=>null,'subtitle'=>$crumbs[2]['subtitle']];
        ?>
        <div class="tfp-facilitator-grades-page">
            <?php tfp_facilitator_grades_render_header($crumbs); ?>
            <form class="tfp-facilitator-filters tfp-fg-filters" method="get">
                <select name="tfp_fg_course" aria-label="Course"><?php foreach($courses as $id=>$title): ?><option value="<?php echo esc_attr($id); ?>" <?php selected($course,$id); ?>><?php echo esc_html($title); ?></option><?php endforeach; ?></select>
                <select name="tfp_fg_cohort" aria-label="Cohort"><?php foreach($cohort_opts as $id=>$title): ?><option value="<?php echo esc_attr($id); ?>" <?php selected($selected->ID,$id); ?>><?php echo esc_html($title); ?></option><?php endforeach; ?></select>
                <select name="tfp_fg_week" aria-label="Week"><option value="0">All Weeks</option><?php foreach($weeks as $i=>$w): ?><option value="<?php echo $i+1; ?>" <?php selected($state['week'],$i+1); ?>>Week <?php echo $i+1; ?></option><?php endforeach; ?></select>
            </form>
            <section class="tfp-fg-metrics">
                <?php if($state['week']): ?>
                    <article><span>Cohort Avg Grade</span><strong><?php echo esc_html($sum['avg']?:'—'); ?></strong><small>Current Week Assigned</small></article>
                    <article class="is-gold"><span>Alerts (Stalled • Blocked)</span><strong><?php echo esc_html($sum['alerts']['total']); ?></strong><small><?php echo esc_html($sum['alerts']['stalled'].' Stalled • '.$sum['alerts']['blocked'].' Blocked'); ?></small></article>
                    <article><span>Homework Completion</span><strong><?php echo $sum['stats']['homework']!==null?esc_html($sum['stats']['homework'].'%'):'—'; ?></strong><small>Current Week Assigned</small></article>
                    <article><span>Attendance Rate</span><strong><?php echo esc_html($sum['stats']['attendance_main']); ?></strong><small><?php echo esc_html($sum['stats']['attendance_sub']); ?></small></article>
                <?php else: ?>
                    <article><span>Cohort Avg Grade</span><strong><?php echo esc_html($sum['avg']?:'—'); ?></strong><small>Top 10% of all Cohorts</small></article>
                    <article><span>Avg Test Grades</span><strong><?php echo $sum['stats']['test']!==null?esc_html($sum['stats']['test'].'%'):'—'; ?></strong><small>Across graded weeks</small></article>
                    <article><span>Homework Completion</span><strong><?php echo $sum['stats']['homework']!==null?esc_html($sum['stats']['homework'].'%'):'—'; ?></strong><small>Current Week Assigned</small></article>
                    <article><span>Attendance Rate</span><strong><?php echo esc_html($sum['stats']['attendance_main']); ?></strong><small><?php echo esc_html($sum['stats']['attendance_sub']); ?></small></article>
                <?php endif; ?>
            </section>
            <section class="tfp-fg-card">
                <div class="tfp-fg-card-head"><h2><?php echo esc_html($state['week']?('Week '.$state['week'].': '.($sum['lesson']?$sum['lesson']->post_title:'')):$selected->post_title.' Overall Performance Index'); ?></h2></div>
                <div class="tfp-fg-table-wrap"><table class="tfp-fg-table"><thead><tr><th>Disciples</th><th>Attendance</th><th>Homework Grade</th><th>Quiz Grade</th><th>Test Grade</th><th>Action</th></tr></thead><tbody>
                <?php foreach($rows as $r): $url=add_query_arg(['tfp_student_id'=>$r['id'],'tfp_student_course'=>$course,'tfp_student_cohort'=>$selected->ID,'tfp_student_week'=>$state['week']],tfp_dashboard_get_url('tfp-dashboard-facilitator-student')); ?>
                    <tr><td><?php echo esc_html($r['name']); ?></td><td><?php echo tfp_facilitator_grades_attendance_badge($r['attendance']); ?></td><td><?php echo tfp_facilitator_grades_badge($r['homework']); ?></td><td><?php echo tfp_facilitator_grades_badge($r['quiz']); ?></td><td><?php echo tfp_facilitator_grades_badge($r['test']); ?></td><td><a class="tfp-fg-action <?php echo $r['overall']===''?'is-add':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo $r['overall']===''?'Add':'View'; ?></a></td></tr>
                <?php endforeach; ?></tbody></table></div>
            </section>
        </div>
        <?php return;
    }

    $student_ids=[];
    foreach($visible as $c)foreach(function_exists('tfp_facilitator_cohort_students')?tfp_facilitator_cohort_students($c->ID):[] as $sid)$student_ids[]=(int)$sid;
    $student_ids=array_values(array_unique($student_ids));
    $points=[]; $stalled=$blocked=0; $completion=[];
    foreach($visible as $c){$s=tfp_facilitator_grades_cohort_summary($c,0);$stalled+=$s['alerts']['stalled'];$blocked+=$s['alerts']['blocked'];if($s['stats']['homework']!==null)$completion[]=$s['stats']['homework']; foreach($s['students'] as $sid){$g=tfp_grade_overall($sid,$s['course']); if(function_exists('tfp_grade_scale')&&$g!=='')$points[]=tfp_grade_scale()[$g]??null;}}
    $points=array_values(array_filter($points,fn($x)=>$x!==null)); $global=$points?array_flip(tfp_grade_scale())[(int)round(array_sum($points)/count($points))]:'';
    $global_completion=$completion?(int)round(array_sum($completion)/count($completion)):null;
    ?>
    <div class="tfp-facilitator-grades-page">
        <?php tfp_facilitator_grades_render_header([['label'=>'Facilitator','url'=>tfp_dashboard_get_url('tfp-dashboard-facilitator-home')],['label'=>'Grades','url'=>null,'subtitle'=>'High-level performance tracking across all active cohorts.']]); ?>
        <form class="tfp-facilitator-filters tfp-fg-filters tfp-fg-filters--global" method="get">
            <select name="tfp_fg_course" aria-label="All Courses"><option value="">All Courses</option><?php foreach($courses as $id=>$title): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></option><?php endforeach; ?></select>
            <select name="tfp_fg_cohort" aria-label="All Cohorts"><option value="">All Cohorts</option><?php foreach($cohort_opts as $id=>$title): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></option><?php endforeach; ?></select>
            <div class="tfp-dash-search tfp-facilitator-search">
                <span class="tfp-dash-search__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.40973 16.2842C1.21799 15.9079 1 15.4805 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1 2.73341 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.5905 1.40973 17 2.5192 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
                <input type="search" name="tfp_fg_search" value="<?php echo esc_attr($state['search']); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search grades', 'tfp-dashboard'); ?>">
            </div>
        </form>
        <section class="tfp-fg-metrics">
            <article class="tfp-fac-grades-metric">
                <span>Total Enrollment</span>
                <strong><?php echo esc_html(count($student_ids)); ?></strong>
                <small>Total Active Disciples</small>
            </article>
            <article class="tfp-fac-grades-metric">
                <span>Average Grade (Global)</span>
                <strong><?php echo esc_html($global ?: '—'); ?></strong>
                <small>Across All Course</small>
            </article>
            <article class="tfp-fac-grades-metric tfp-fac-grades-metric--danger">
                <span>Alerts (Stalled • Blocked)</span>
                <strong><?php echo esc_html($stalled + $blocked); ?></strong>
                <small><?php echo esc_html($stalled . ' Stalled • ' . $blocked . ' Blocked'); ?></small>
            </article>
            <article class="tfp-fac-grades-metric">
                <span>Homework Completion</span>
                <strong><?php echo $global_completion !== null ? esc_html($global_completion . '%') : '—'; ?></strong>
                <small>Current Week Assigned</small>
            </article>
        </section>
        <section class="tfp-fg-card"><div class="tfp-fg-card-head"><h2>Cohort Performance Index</h2></div>
        <div class="tfp-fg-table-wrap"><table class="tfp-fg-table tfp-fg-table--global"><thead><tr><th>Cohort Name</th><th>Disciples</th><th>Avg. Grade</th><th>Alerts</th><th>Completion</th><th>Action</th></tr></thead><tbody>
        <?php foreach($visible as $c): $s=tfp_facilitator_grades_cohort_summary($c,0); $url=add_query_arg(['tfp_fg_course'=>$s['course'],'tfp_fg_cohort'=>$c->ID],tfp_dashboard_get_url('tfp-dashboard-facilitator-grades')); ?>
            <tr><td><?php echo esc_html($c->post_title); ?></td><td><?php echo esc_html($s['students']?count($s['students']):0); ?></td><td><?php echo tfp_facilitator_grades_badge($s['avg']); ?></td><td><span class="tfp-fg-alert-text"><?php echo esc_html($s['alerts']['stalled'].' Stalled'); ?> • <strong><?php echo esc_html($s['alerts']['blocked'].' Blocked'); ?></strong></span></td><td><strong><?php echo $s['stats']['homework']!==null?esc_html($s['stats']['homework'].'%'):'—'; ?></strong></td><td><a class="tfp-fg-action <?php echo $s['avg']===''?'is-add':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo $s['avg']===''?'Add':'View'; ?></a></td></tr>
        <?php endforeach; ?></tbody></table></div></section>
    </div>
    <?php
}
