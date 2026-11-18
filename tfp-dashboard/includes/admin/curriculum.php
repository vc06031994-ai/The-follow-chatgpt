<?php
/**
 * Admin Curriculum — Categories overview.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_curriculum_page_url() {
    return home_url('/admin-curriculum/');
}

function tfp_admin_curriculum_get_categories() {
    $taxonomy = taxonomy_exists('ld_course_category') ? 'ld_course_category' : '';
    if (!$taxonomy) return [];
    $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
    if (is_wp_error($terms)) return [];
    $result = [];
    foreach ($terms as $term) {
        $courses = get_posts([
            'post_type'=>'sfwd-courses',
            'post_status'=>['publish','draft','pending','private'],
            'posts_per_page'=>-1,
            'orderby'=>'title','order'=>'ASC',
            'tax_query'=>[['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>[$term->term_id]]],
        ]);
        $result[] = ['term'=>$term,'courses'=>$courses];
    }
    return $result;
}

function tfp_admin_curriculum_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.','tfp-dashboard'), esc_html__('Access denied','tfp-dashboard'), ['response'=>403]);
    }
    if (function_exists('tfp_admin_dashboard_enqueue_assets')) tfp_admin_dashboard_enqueue_assets();
    $user=wp_get_current_user();
    $nav=function_exists('tfp_admin_dashboard_nav_items')?tfp_admin_dashboard_nav_items():[];
    foreach($nav as &$item){unset($item['active']);if(($item['slug']??'')==='curriculum'){$item['url']=tfp_admin_curriculum_page_url();$item['active']=true;}} unset($item);
    $categories=tfp_admin_curriculum_get_categories();
    $taxonomy=taxonomy_exists('ld_course_category')?'ld_course_category':'category';
    $add_category_url=admin_url('edit-tags.php?taxonomy='.$taxonomy.'&post_type=sfwd-courses');
    $add_course_url=admin_url('post-new.php?post_type=sfwd-courses');
    ?>
    <div class="tfp-admin-app tfp-admin-curriculum-app">
      <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation','tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url')?tfp_admin_dashboard_page_url():home_url('/admin-dashboard/')); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL.'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL.'assets/images/logo-collapsed.svg'); ?>" alt=""></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation','tfp-dashboard'); ?>">
        <?php foreach($nav as $item): ?><a class="tfp-admin-nav-link<?php echo !empty($item['active'])?' is-active':''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($user->ID,['size'=>96])); ?>" alt=""><span><strong><?php echo esc_html($user->display_name?:__('Administrator','tfp-dashboard')); ?></strong><small><?php esc_html_e('Administrator','tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout','tfp-dashboard'); ?></span></a></div>
      </aside>
      <div class="tfp-admin-main">
        <div class="tfp-admin-mobilebar"><button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu','tfp-dashboard'); ?>">☰</button><span><?php esc_html_e('Admin Console','tfp-dashboard'); ?></span></div>
        <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin','tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Curriculum','tfp-dashboard'); ?></strong><b>/</b><strong><?php esc_html_e('Categories','tfp-dashboard'); ?></strong></div>
        <header class="tfp-admin-page-header tfp-admin-curriculum-header"><div><h1><?php esc_html_e('Curriculum','tfp-dashboard'); ?></h1><p><?php esc_html_e('Manage categories, courses, modules and manage their structural organization.','tfp-dashboard'); ?></p></div><a class="tfp-admin-curriculum-primary" href="<?php echo esc_url($add_category_url); ?>"><span>＋</span><?php esc_html_e('Add Category','tfp-dashboard'); ?></a></header>
        <main class="tfp-admin-content tfp-admin-curriculum-content">
          <nav class="tfp-admin-curriculum-tabs" aria-label="<?php esc_attr_e('Curriculum sections','tfp-dashboard'); ?>"><a class="is-active" href="<?php echo esc_url(tfp_admin_curriculum_page_url()); ?>"><?php esc_html_e('Categories','tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-courses')); ?>"><?php esc_html_e('Courses','tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-lessons')); ?>"><?php esc_html_e('Modules','tfp-dashboard'); ?></a></nav>
          <section class="tfp-admin-curriculum-category-list">
          <?php if(!empty($categories)): foreach($categories as $group): $term=$group['term']; $term_edit=get_edit_term_link($term->term_id,'ld_course_category','sfwd-courses'); ?>
            <article class="tfp-admin-curriculum-category">
              <header class="tfp-admin-curriculum-category-head"><div class="tfp-admin-curriculum-category-info"><div class="tfp-admin-curriculum-category-title"><h2><?php echo esc_html($term->name); ?></h2><span><?php echo esc_html(sprintf(_n('%s Course','%s Courses',count($group['courses']),'tfp-dashboard'),number_format_i18n(count($group['courses'])))); ?></span></div><?php if($term->description!==''): ?><p><?php echo esc_html($term->description); ?></p><?php endif; ?></div><div class="tfp-admin-curriculum-category-actions"><a class="tfp-admin-curriculum-add-course" href="<?php echo esc_url(add_query_arg('ld_course_category',$term->slug,$add_course_url)); ?>"><span>＋</span><?php esc_html_e('Add Course','tfp-dashboard'); ?></a><a class="tfp-admin-curriculum-edit-category" href="<?php echo esc_url($term_edit?:$add_category_url); ?>"><?php esc_html_e('Edit','tfp-dashboard'); ?></a></div></header>
              <?php if(!empty($group['courses'])): ?><div class="tfp-admin-curriculum-course-list"><?php foreach($group['courses'] as $course): $status=get_post_status($course); ?><article class="tfp-admin-curriculum-course"><div><strong><?php echo esc_html(get_the_title($course)); ?></strong><small><?php echo esc_html($term->name); ?><?php $steps=function_exists('tfp_admin_courses_get_course_steps_count')?tfp_admin_courses_get_course_steps_count($course->ID):0; ?> · <?php echo esc_html(sprintf(_n('%s Module','%s Modules',$steps,'tfp-dashboard'),number_format_i18n($steps))); ?></small></div><div class="tfp-admin-curriculum-course-actions"><span class="tfp-admin-curriculum-status <?php echo esc_attr($status==='publish'?'is-active':'is-inactive'); ?>"><?php echo esc_html($status==='publish'?__('Active','tfp-dashboard'):ucfirst($status)); ?></span><a href="<?php echo esc_url(get_edit_post_link($course->ID)); ?>"><?php esc_html_e('Edit','tfp-dashboard'); ?></a></div></article><?php endforeach; ?></div>
              <?php else: ?><div class="tfp-admin-curriculum-empty"><?php esc_html_e('No courses yet.','tfp-dashboard'); ?></div><?php endif; ?>
            </article>
          <?php endforeach; else: ?><div class="tfp-admin-curriculum-no-categories"><?php esc_html_e('No curriculum categories found. Add a category to organize your courses.','tfp-dashboard'); ?></div><?php endif; ?>
          </section>
        </main>
      </div>
    </div>
    <?php
}

add_filter('pre_handle_404',function($preempt,$wp_query){$path=trim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');if(basename($path)==='admin-curriculum'){if($wp_query){$wp_query->is_404=false;$wp_query->is_page=true;}status_header(200);return true;}return $preempt;},13,2);
add_action('template_redirect',function(){ $path=trim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');if(basename($path)!=='admin-curriculum')return;status_header(200);nocache_headers();$file=TFP_DASH_PATH.'templates/template-admin-curriculum.php';if(is_readable($file)){include $file;exit;}},1);
