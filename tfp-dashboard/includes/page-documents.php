<?php
if (!defined('ABSPATH'))
    exit;

function tfp_dashboard_render_documents_content()
{
    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $first_name = $current_user->first_name ? $current_user->first_name : $current_user->display_name;

    // Define tabs
    $tabs = [
        'agreements' => __('Agreements', 'tfp-dashboard'),
        'grades' => __('Grades', 'tfp-dashboard'),
        'certificates' => __('Certificates', 'tfp-dashboard'),
        'receipts' => __('Receipts', 'tfp-dashboard'),
    ];

    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_text_field($_GET['tab']) : 'agreements';

    $mock_documents = [
        [
            'title' => 'Platform NDA',
            'type' => 'Agreement',
            'date' => '2026-05-04',
            'status' => 'Active'
        ],
        [
            'title' => 'Course Agreement',
            'type' => 'Agreement',
            'date' => '2026-04-28',
            'status' => 'Active'
        ],
        [
            'title' => 'Program NDA',
            'type' => 'Agreement',
            'date' => '2026-04-03',
            'status' => 'Active'
        ]
    ];
    ?>
    <div class="tfp-dash-pageheader">
        <div class="tfp-dash-pageheader__crumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Discipleship', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <span class="tfp-dash-pageheader__current"><?php esc_html_e('Documents', 'tfp-dashboard'); ?></span>
        </div>
        <h1 class="tfp-dash-pageheader__title">
            <?php printf(esc_html__('%s, My Documents', 'tfp-dashboard'), esc_html($first_name)); ?>
        </h1>
        <p class="tfp-dash-pageheader__subtitle">
            <?php esc_html_e('Access your agreements, contracts, class materials, and certificates in one place.', 'tfp-dashboard'); ?>
        </p>
    </div>

    <!-- Tabs -->
    <div class="tfp-docs-tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?php echo esc_attr($key); ?>" class="tfp-docs-tabs__link <?php echo $active_tab === $key ? 'is-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab Content -->
    <div class="tfp-docs-panel">
        <h2 class="tfp-docs-panel__title">
            <?php esc_html_e('My Documents', 'tfp-dashboard'); ?>
        </h2>

        <?php if ($active_tab === 'agreements'): ?>
            <div class="tfp-docs-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Document', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Date Issued', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Actions', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mock_documents as $doc): ?>
                            <tr>
                                <td><?php echo esc_html($doc['title']); ?></td>
                                <td><?php echo esc_html($doc['type']); ?></td>
                                <td><?php echo esc_html($doc['date']); ?></td>
                                <td>
                                    <?php if ($doc['status'] === 'Active'): ?>
                                        <span class="tfp-docs-badge">
                                            <?php echo esc_html($doc['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="tfp-docs-actions">
                                        <a href="#" class="tfp-docs-btn-view"><?php esc_html_e('View/ Download', 'tfp-dashboard'); ?></a>
                                        <a href="#" class="tfp-docs-btn-sign"><?php esc_html_e('Sign', 'tfp-dashboard'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p><?php esc_html_e('No documents available in this section.', 'tfp-dashboard'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
