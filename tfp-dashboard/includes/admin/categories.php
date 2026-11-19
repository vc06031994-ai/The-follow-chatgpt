<?php
/**
 * Admin Curriculum Categories.
 * Manages LearnDash course categories using the registered taxonomy.
 */
if (!defined('ABSPATH')) {
    exit;
}

function tfp_admin_categories_page_url($args = []) {
    $url = home_url('/admin-curriculum-categories/');
    return $args ? add_query_arg($args, $url) : $url;
}

/** Return the LearnDash course-category taxonomy only when registered. */
function tfp_admin_categories_taxonomy() {
    return taxonomy_exists('ld_course_category') ? 'ld_course_category' : '';
}

/** Handle category create, update and delete actions securely. */
function tfp_admin_categories_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['tfp_category_action'])) {
        return;
    }

    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to manage course categories.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    check_admin_referer('tfp_admin_course_category_action', 'tfp_category_nonce');

    $taxonomy = tfp_admin_categories_taxonomy();
    if (!$taxonomy) {
        wp_die(esc_html__('The LearnDash course category taxonomy is not available.', 'tfp-dashboard'));
    }

    $action = sanitize_key(wp_unslash($_POST['tfp_category_action']));
    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;

    if ($action === 'delete' && $term_id) {
        $result = wp_delete_term($term_id, $taxonomy);
    } else {
        $name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';
        $slug = isset($_POST['category_slug']) ? sanitize_title(wp_unslash($_POST['category_slug'])) : '';
        $description = isset($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';

        if ($name === '') {
            wp_safe_redirect(tfp_admin_categories_page_url(['category_notice' => 'name-required']));
            exit;
        }

        $args = ['description' => $description];
        if ($slug !== '') {
            $args['slug'] = $slug;
        }

        if ($action === 'update' && $term_id) {
            $result = wp_update_term($term_id, $taxonomy, array_merge($args, ['name' => $name]));
        } elseif ($action === 'create') {
            $result = wp_insert_term($name, $taxonomy, $args);
        } else {
            return;
        }
    }

    $notice = is_wp_error($result) ? 'error' : ($action === 'delete' ? 'deleted' : ($action === 'update' ? 'updated' : 'created'));
    wp_safe_redirect(tfp_admin_categories_page_url(['category_notice' => $notice]));
    exit;
}

/** Render the Categories tab within the existing Admin shell. */
function tfp_admin_categories_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    tfp_admin_categories_handle_post();

    $taxonomy = tfp_admin_categories_taxonomy();
    $search = isset($_GET['category_search']) ? sanitize_text_field(wp_unslash($_GET['category_search'])) : '';
    $edit_id = isset($_GET['edit_category']) ? absint($_GET['edit_category']) : 0;
    $editing = ($taxonomy && $edit_id) ? get_term($edit_id, $taxonomy) : null;
    if (is_wp_error($editing)) {
        $editing = null;
    }

    $terms = [];
    if ($taxonomy) {
        $term_args = ['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'];
        if ($search !== '') {
            $term_args['search'] = $search;
        }
        $terms = get_terms($term_args);
        if (is_wp_error($terms)) {
            $terms = [];
        }
    }

    $all_terms = $taxonomy ? get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) : [];
    if (is_wp_error($all_terms)) {
        $all_terms = [];
    }
    $total = count($all_terms);
    $assigned = count(array_filter($all_terms, static function ($term) {
        return (int) $term->count > 0;
    }));
    $unassigned = max(0, $total - $assigned);

    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        unset($item['active']);
        if (($item['slug'] ?? '') === 'curriculum') {
            $item['url'] = tfp_admin_categories_page_url();
            $item['active'] = true;
        }
    }
    unset($item);

    $notice = isset($_GET['category_notice']) ? sanitize_key(wp_unslash($_GET['category_notice'])) : '';
    $show_form = isset($_GET['category_action']) && $_GET['category_action'] === 'add';
    $show_form = $show_form || (bool) $editing;
    ?>
    <div class="tfp-admin-app tfp-admin-categories-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="">
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a>
            </div>
        </aside>

        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span>
            </div>
            <div class="tfp-admin-breadcrumb">
                <span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Categories', 'tfp-dashboard'); ?></strong>
            </div>
            <header class="tfp-admin-page-header tfp-admin-categories-header">
                <div>
                    <h1><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></h1>
                    <p><?php esc_html_e('Create, edit, and organize course categories.', 'tfp-dashboard'); ?></p>
                </div>
                <a class="tfp-admin-categories-add" href="<?php echo esc_url(tfp_admin_categories_page_url(['category_action' => 'add'])); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Category', 'tfp-dashboard'); ?></a>
            </header>

            <main class="tfp-admin-content tfp-admin-categories-content">
                <nav class="tfp-admin-curriculum-tabs" aria-label="<?php esc_attr_e('Curriculum sections', 'tfp-dashboard'); ?>">
                    <a class="is-active" href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Categories', 'tfp-dashboard'); ?></a>
                    <a href="<?php echo esc_url(tfp_admin_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-lessons')); ?>"><?php esc_html_e('Modules', 'tfp-dashboard'); ?></a>
                </nav>

                <?php if ($notice && $notice !== 'error') : ?>
                    <div class="tfp-admin-categories-notice" role="status">
                        <?php
                        $messages = [
                            'created' => __('Category created successfully.', 'tfp-dashboard'),
                            'updated' => __('Category updated successfully.', 'tfp-dashboard'),
                            'deleted' => __('Category deleted successfully.', 'tfp-dashboard'),
                            'name-required' => __('Please enter a category name.', 'tfp-dashboard'),
                        ];
                        echo esc_html($messages[$notice] ?? '');
                        ?>
                    </div>
                <?php elseif ($notice === 'error') : ?>
                    <div class="tfp-admin-categories-notice is-error" role="alert"><?php esc_html_e('The category could not be saved. Please check that the name or slug is unique.', 'tfp-dashboard'); ?></div>
                <?php endif; ?>

                <section class="tfp-admin-categories-stats" aria-label="<?php esc_attr_e('Category metrics', 'tfp-dashboard'); ?>">
                    <article><small><?php esc_html_e('Total Categories', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($total)); ?></strong><p><?php esc_html_e('Across all course tracks', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Active Categories', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($assigned)); ?></strong><p><?php esc_html_e('With courses assigned', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Unused Categories', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($unassigned)); ?></strong><p><?php esc_html_e('No courses assigned yet', 'tfp-dashboard'); ?></p></article>
                </section>

                <?php if ($show_form) : ?>
                    <section class="tfp-admin-panel tfp-admin-categories-form-panel">
                        <div class="tfp-admin-panel-heading"><h2><?php echo $editing ? esc_html__('Edit Category', 'tfp-dashboard') : esc_html__('Add New Category', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></a></div>
                        <?php if (!$taxonomy) : ?>
                            <p class="tfp-admin-categories-empty"><?php esc_html_e('LearnDash course categories are unavailable because the ld_course_category taxonomy is not registered.', 'tfp-dashboard'); ?></p>
                        <?php else : ?>
                            <form method="post" class="tfp-admin-categories-form">
                                <?php wp_nonce_field('tfp_admin_course_category_action', 'tfp_category_nonce'); ?>
                                <input type="hidden" name="tfp_category_action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                                <?php if ($editing) : ?><input type="hidden" name="term_id" value="<?php echo esc_attr($editing->term_id); ?>"><?php endif; ?>
                                <label><span><?php esc_html_e('Category Name', 'tfp-dashboard'); ?></span><input type="text" name="category_name" required maxlength="200" value="<?php echo $editing ? esc_attr($editing->name) : ''; ?>" placeholder="<?php esc_attr_e('Enter category name', 'tfp-dashboard'); ?>"></label>
                                <label><span><?php esc_html_e('Slug', 'tfp-dashboard'); ?></span><input type="text" name="category_slug" value="<?php echo $editing ? esc_attr($editing->slug) : ''; ?>" placeholder="<?php esc_attr_e('Leave blank to generate automatically', 'tfp-dashboard'); ?>"></label>
                                <label class="is-full"><span><?php esc_html_e('Description', 'tfp-dashboard'); ?></span><textarea name="category_description" rows="3" placeholder="<?php esc_attr_e('Add a short description (optional)', 'tfp-dashboard'); ?>"><?php echo $editing ? esc_textarea($editing->description) : ''; ?></textarea></label>
                                <div class="tfp-admin-categories-form-actions"><button type="submit"><?php echo $editing ? esc_html__('Save Changes', 'tfp-dashboard') : esc_html__('Create Category', 'tfp-dashboard'); ?></button><a href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></a></div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="tfp-admin-panel tfp-admin-categories-list-panel">
                    <div class="tfp-admin-categories-list-toolbar">
                        <form method="get" action="<?php echo esc_url(tfp_admin_categories_page_url()); ?>" class="tfp-admin-categories-search">
                            <label class="screen-reader-text" for="tfp-category-search"><?php esc_html_e('Search categories', 'tfp-dashboard'); ?></label>
                            <span aria-hidden="true">⌕</span>
                            <input id="tfp-category-search" type="search" name="category_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search Categories', 'tfp-dashboard'); ?>">
                            <button type="submit"><?php esc_html_e('Search', 'tfp-dashboard'); ?></button>
                        </form>
                        <span class="tfp-admin-categories-result-count"><?php echo esc_html(sprintf(_n('%s category', '%s categories', count($terms), 'tfp-dashboard'), number_format_i18n(count($terms)))); ?></span>
                    </div>

                    <?php if (!$taxonomy) : ?>
                        <p class="tfp-admin-categories-empty"><?php esc_html_e('LearnDash course categories are not available. Activate LearnDash to manage course categories here.', 'tfp-dashboard'); ?></p>
                    <?php elseif (empty($terms)) : ?>
                        <p class="tfp-admin-categories-empty"><?php echo $search ? esc_html__('No categories match your search.', 'tfp-dashboard') : esc_html__('No categories have been created yet. Use Add Category to create your first category.', 'tfp-dashboard'); ?></p>
                    <?php else : ?>
                        <div class="tfp-admin-categories-table-wrap">
                            <table class="tfp-admin-categories-table">
                                <thead><tr><th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Slug', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Courses', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Description', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Actions', 'tfp-dashboard'); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($terms as $term) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($term->name); ?></strong></td>
                                        <td><code><?php echo esc_html($term->slug); ?></code></td>
                                        <td><span class="tfp-admin-categories-count"><?php echo esc_html(number_format_i18n((int) $term->count)); ?></span></td>
                                        <td><?php echo $term->description !== '' ? esc_html(wp_trim_words($term->description, 14)) : '<span class="tfp-admin-categories-muted">—</span>'; ?></td>
                                        <td class="tfp-admin-categories-actions">
                                            <a href="<?php echo esc_url(tfp_admin_categories_page_url(['edit_category' => $term->term_id])); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></a>
                                            <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Delete this category? Courses will remain, but this category assignment will be removed.', 'tfp-dashboard')); ?>');">
                                                <?php wp_nonce_field('tfp_admin_course_category_action', 'tfp_category_nonce'); ?>
                                                <input type="hidden" name="tfp_category_action" value="delete">
                                                <input type="hidden" name="term_id" value="<?php echo esc_attr($term->term_id); ?>">
                                                <button type="submit"><?php esc_html_e('Delete', 'tfp-dashboard'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
    <?php
}

/** Register the front-end Categories route. */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-curriculum-categories') {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-curriculum-categories') {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-categories.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 1);
