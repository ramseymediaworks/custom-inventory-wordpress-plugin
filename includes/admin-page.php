<?php
/**
 * Inventory Enhanced - Main Admin Dashboard Page
 * 
 * Main dashboard page for the plugin with overview, status, and quick actions
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$plugin = inventory_enhanced_get_instance();
$settings = $plugin->settings;

// Get stats
$main_category_slug = $plugin->get_setting('main_category_slug', 'inventory');
$main_category = get_term_by('slug', $main_category_slug, 'category');
$inventory_count = 0;

if ($main_category) {
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => array(
            array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $main_category->term_id
            )
        )
    ));
    $inventory_count = $query->found_posts;
}

$setup_completed = $plugin->get_setting('setup_completed', false);
$divi_active = function_exists('et_divi_fonts_url');
?>

<div class="wrap inventory-enhanced-dashboard">
    <h1>
        <span class="dashicons dashicons-grid-view"></span>
        <?php _e('Inventory Enhanced', 'inventory-enhanced'); ?>
    </h1>
    
    <!-- Status Cards -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($inventory_count); ?></div>
            <div class="stat-label"><?php _e('Inventory Items', 'inventory-enhanced'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon <?php echo $setup_completed ? 'completed' : 'pending'; ?>">
                <?php echo $setup_completed ? '✅' : '⏳'; ?>
            </div>
            <div class="stat-label"><?php _e('Setup Status', 'inventory-enhanced'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon <?php echo $divi_active ? 'active' : 'inactive'; ?>">
                <?php echo $divi_active ? '🎨' : '❌'; ?>
            </div>
            <div class="stat-label"><?php _e('Divi Theme', 'inventory-enhanced'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="dashboard-actions">
        <h2><?php _e('Quick Actions', 'inventory-enhanced'); ?></h2>
        
        <div class="action-buttons">
            <?php if (!$setup_completed): ?>
                <a href="<?php echo admin_url('admin.php?page=inventory-enhanced-setup'); ?>" class="button button-primary button-hero">
                    <?php _e('Complete Setup', 'inventory-enhanced'); ?>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('post-new.php'); ?>" class="button button-secondary">
                <?php _e('Add New Inventory Item', 'inventory-enhanced'); ?>
            </a>
            
            <a href="<?php echo admin_url('edit-tags.php?taxonomy=category'); ?>" class="button button-secondary">
                <?php _e('Manage Categories', 'inventory-enhanced'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=inventory-enhanced-templates'); ?>" class="button button-secondary">
                <?php _e('Browse Templates', 'inventory-enhanced'); ?>
            </a>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="dashboard-activity">
        <h2><?php _e('Recent Activity', 'inventory-enhanced'); ?></h2>
        
        <?php
        // Get recent inventory posts
        $recent_posts = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => $main_category_slug
                )
            )
        ));
        
        if ($recent_posts): ?>
            <div class="activity-list">
                <?php foreach ($recent_posts as $post): ?>
                    <div class="activity-item">
                        <div class="activity-title">
                            <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </div>
                        <div class="activity-meta">
                            <?php printf(__('Published %s ago', 'inventory-enhanced'), human_time_diff(strtotime($post->post_date))); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-activity"><?php _e('No inventory items found. Start by adding your first item!', 'inventory-enhanced'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- System Status -->
    <div class="dashboard-system">
        <h2><?php _e('System Status', 'inventory-enhanced'); ?></h2>
        
        <div class="system-checks">
            <div class="system-check <?php echo version_compare(get_bloginfo('version'), '5.0', '>=') ? 'pass' : 'fail'; ?>">
                <span class="check-icon"><?php echo version_compare(get_bloginfo('version'), '5.0', '>=') ? '✅' : '❌'; ?></span>
                <span class="check-label"><?php _e('WordPress Version', 'inventory-enhanced'); ?></span>
                <span class="check-value"><?php echo get_bloginfo('version'); ?></span>
            </div>
            
            <div class="system-check <?php echo version_compare(PHP_VERSION, '7.4', '>=') ? 'pass' : 'fail'; ?>">
                <span class="check-icon"><?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '✅' : '❌'; ?></span>
                <span class="check-label"><?php _e('PHP Version', 'inventory-enhanced'); ?></span>
                <span class="check-value"><?php echo PHP_VERSION; ?></span>
            </div>
            
            <div class="system-check <?php echo $divi_active ? 'pass' : 'warning'; ?>">
                <span class="check-icon"><?php echo $divi_active ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><?php _e('Divi Theme', 'inventory-enhanced'); ?></span>
                <span class="check-value"><?php echo $divi_active ? __('Active', 'inventory-enhanced') : __('Not Active', 'inventory-enhanced'); ?></span>
            </div>
            
            <div class="system-check <?php echo $main_category ? 'pass' : 'fail'; ?>">
                <span class="check-icon"><?php echo $main_category ? '✅' : '❌'; ?></span>
                <span class="check-label"><?php _e('Main Category', 'inventory-enhanced'); ?></span>
                <span class="check-value"><?php echo $main_category ? $main_category->name : __('Not Found', 'inventory-enhanced'); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.inventory-enhanced-dashboard {
    max-width: 1200px;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0 40px 0;
}

.stat-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    border: 1px solid #e0e0e0;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #2c5aa0;
    margin-bottom: 8px;
}

.stat-icon {
    font-size: 36px;
    margin-bottom: 8px;
    display: block;
}

.stat-icon.completed {
    color: #4caf50;
}

.stat-icon.pending {
    color: #ff9800;
}

.stat-icon.active {
    color: #4caf50;
}

.stat-icon.inactive {
    color: #f44336;
}

.stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.dashboard-actions,
.dashboard-activity,
.dashboard-system {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    border: 1px solid #e0e0e0;
}

.dashboard-actions h2,
.dashboard-activity h2,
.dashboard-system h2 {
    margin-top: 0;
    color: #2c5aa0;
    font-size: 24px;
    margin-bottom: 20px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-buttons .button {
    padding: 12px 24px;
    font-weight: 600;
}

.activity-list {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.activity-item {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-title a {
    text-decoration: none;
    color: #2c5aa0;
    font-weight: 600;
}

.activity-title a:hover {
    color: #1e3f73;
}

.activity-meta {
    color: #666;
    font-size: 14px;
}

.no-activity {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 30px;
}

.system-checks {
    display: grid;
    gap: 15px;
}

.system-check {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 4px solid #e0e0e0;
}

.system-check.pass {
    border-left-color: #4caf50;
}

.system-check.fail {
    border-left-color: #f44336;
}

.system-check.warning {
    border-left-color: #ff9800;
}

.check-icon {
    font-size: 18px;
}

.check-label {
    flex: 1;
    font-weight: 600;
    color: #333;
}

.check-value {
    color: #666;
    font-family: monospace;
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>