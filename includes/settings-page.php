<?php
/**
 * Inventory Enhanced - Settings Page
 * 
 * Admin settings page for configuring plugin options
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

// Handle form submission
if (isset($_POST['inventory_enhanced_settings_nonce'])) {
    if (wp_verify_nonce($_POST['inventory_enhanced_settings_nonce'], 'inventory_enhanced_settings') && current_user_can('manage_options')) {
        $new_settings = isset($_POST['inventory_enhanced_settings']) ? $_POST['inventory_enhanced_settings'] : array();
        
        // Sanitize settings
        $clean_settings = array(
            'main_category_slug' => sanitize_title($new_settings['main_category_slug'] ?? 'inventory'),
            'posts_per_page' => max(1, min(50, intval($new_settings['posts_per_page'] ?? 8))),
            'primary_color' => sanitize_hex_color($new_settings['primary_color'] ?? '#2c5aa0'),
            'secondary_color' => sanitize_hex_color($new_settings['secondary_color'] ?? '#ff6900'),
            'enable_progressive_filtering' => isset($new_settings['enable_progressive_filtering']),
            'filter_layout' => in_array($new_settings['filter_layout'] ?? 'sidebar', array('sidebar', 'top')) ? $new_settings['filter_layout'] : 'sidebar',
            'enable_gallery_override' => isset($new_settings['enable_gallery_override']),
            'gallery_show_captions' => isset($new_settings['gallery_show_captions']),
            'gallery_swipe_enabled' => isset($new_settings['gallery_swipe_enabled']),
            'enable_caching' => isset($new_settings['enable_caching']),
            'cache_duration' => max(1, min(1440, intval($new_settings['cache_duration'] ?? 60))),
            'setup_completed' => $settings['setup_completed'] ?? false
        );
        
        // Update settings
        update_option('inventory_enhanced_settings', $clean_settings);
        $plugin->settings = $clean_settings;
        $settings = $clean_settings;
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'inventory-enhanced') . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php _e('Inventory Enhanced Settings', 'inventory-enhanced'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('inventory_enhanced_settings', 'inventory_enhanced_settings_nonce'); ?>
        
        <div class="settings-container">
            <!-- General Settings -->
            <div class="settings-section">
                <h2><?php _e('General Settings', 'inventory-enhanced'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="main_category_slug"><?php _e('Main Category Slug', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="main_category_slug" name="inventory_enhanced_settings[main_category_slug]" 
                                   value="<?php echo esc_attr($settings['main_category_slug'] ?? 'inventory'); ?>" class="regular-text">
                            <p class="description"><?php _e('The slug of your main inventory category (default: inventory)', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="posts_per_page"><?php _e('Posts Per Page', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="posts_per_page" name="inventory_enhanced_settings[posts_per_page]" 
                                   value="<?php echo esc_attr($settings['posts_per_page'] ?? 8); ?>" min="1" max="50" class="small-text">
                            <p class="description"><?php _e('Number of inventory items to show per page', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="primary_color"><?php _e('Primary Color', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="primary_color" name="inventory_enhanced_settings[primary_color]" 
                                   value="<?php echo esc_attr($settings['primary_color'] ?? '#2c5aa0'); ?>" class="color-picker">
                            <p class="description"><?php _e('Main brand color used throughout the interface', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="secondary_color"><?php _e('Secondary Color', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="secondary_color" name="inventory_enhanced_settings[secondary_color]" 
                                   value="<?php echo esc_attr($settings['secondary_color'] ?? '#ff6900'); ?>" class="color-picker">
                            <p class="description"><?php _e('Accent color for buttons and highlights', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Filter Settings -->
            <div class="settings-section">
                <h2><?php _e('Filter Settings', 'inventory-enhanced'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Progressive Filtering', 'inventory-enhanced'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inventory_enhanced_settings[enable_progressive_filtering]" 
                                       value="1" <?php checked(1, $settings['enable_progressive_filtering'] ?? true); ?>>
                                <?php _e('Enable smart filtering that shows/hides options based on availability', 'inventory-enhanced'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="filter_layout"><?php _e('Filter Layout', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <select id="filter_layout" name="inventory_enhanced_settings[filter_layout]">
                                <option value="sidebar" <?php selected($settings['filter_layout'] ?? 'sidebar', 'sidebar'); ?>>
                                    <?php _e('Sidebar (Recommended)', 'inventory-enhanced'); ?>
                                </option>
                                <option value="top" <?php selected($settings['filter_layout'] ?? 'sidebar', 'top'); ?>>
                                    <?php _e('Top Bar', 'inventory-enhanced'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Choose how filters are displayed', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Gallery Settings -->
            <div class="settings-section">
                <h2><?php _e('Gallery Settings', 'inventory-enhanced'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enhanced Galleries', 'inventory-enhanced'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inventory_enhanced_settings[enable_gallery_override]" 
                                       value="1" <?php checked(1, $settings['enable_gallery_override'] ?? true); ?>>
                                <?php _e('Replace Divi gallery dots with thumbnail navigation (requires Divi)', 'inventory-enhanced'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Show Image Captions', 'inventory-enhanced'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inventory_enhanced_settings[gallery_show_captions]" 
                                       value="1" <?php checked(1, $settings['gallery_show_captions'] ?? true); ?>>
                                <?php _e('Display image captions in the gallery', 'inventory-enhanced'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Touch/Swipe Navigation', 'inventory-enhanced'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inventory_enhanced_settings[gallery_swipe_enabled]" 
                                       value="1" <?php checked(1, $settings['gallery_swipe_enabled'] ?? true); ?>>
                                <?php _e('Enable touch and swipe navigation on mobile devices', 'inventory-enhanced'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Performance Settings -->
            <div class="settings-section">
                <h2><?php _e('Performance Settings', 'inventory-enhanced'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Caching', 'inventory-enhanced'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inventory_enhanced_settings[enable_caching]" 
                                       value="1" <?php checked(1, $settings['enable_caching'] ?? true); ?>>
                                <?php _e('Cache filter results and category counts for better performance', 'inventory-enhanced'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cache_duration"><?php _e('Cache Duration (minutes)', 'inventory-enhanced'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cache_duration" name="inventory_enhanced_settings[cache_duration]" 
                                   value="<?php echo esc_attr($settings['cache_duration'] ?? 60); ?>" min="1" max="1440" class="small-text">
                            <p class="description"><?php _e('How long to cache filter results', 'inventory-enhanced'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'inventory-enhanced'), 'primary', 'submit', false); ?>
        
        <button type="button" class="button button-secondary" id="reset-settings" style="margin-left: 10px;">
            <?php _e('Reset to Defaults', 'inventory-enhanced'); ?>
        </button>
        
        <button type="button" class="button button-secondary" id="clear-cache" style="margin-left: 10px;">
            <?php _e('Clear Cache', 'inventory-enhanced'); ?>
        </button>
    </form>
</div>

<style>
.settings-container {
    max-width: 800px;
}

.settings-section {
    background: white;
    padding: 30px;
    margin-bottom: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e0e0e0;
}

.settings-section h2 {
    color: #2c5aa0;
    font-size: 24px;
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.form-table th {
    width: 200px;
    vertical-align: top;
    padding-top: 15px;
}

.form-table td {
    vertical-align: top;
    padding-top: 10px;
}

.color-picker {
    width: 100px !important;
}

.description {
    color: #666;
    font-size: 13px;
    margin-top: 5px !important;
    line-height: 1.4;
}

.button {
    padding: 10px 20px;
    font-weight: 600;
}

.button-primary {
    background: #2c5aa0;
    border-color: #2c5aa0;
}

.button-primary:hover {
    background: #1e3f73;
    border-color: #1e3f73;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Color picker
    $('.color-picker').wpColorPicker();
    
    // Reset settings
    $('#reset-settings').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to defaults?', 'inventory-enhanced')); ?>')) {
            $.post(ajaxurl, {
                action: 'inventory_reset_settings',
                nonce: '<?php echo wp_create_nonce('inventory_enhanced_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Failed to reset settings', 'inventory-enhanced')); ?>');
                }
            });
        }
    });
    
    // Clear cache
    $('#clear-cache').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'inventory-enhanced')); ?>');
        
        $.post(ajaxurl, {
            action: 'inventory_clear_cache',
            nonce: '<?php echo wp_create_nonce('inventory_enhanced_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Cache cleared successfully!', 'inventory-enhanced')); ?>');
            } else {
                alert('<?php echo esc_js(__('Failed to clear cache', 'inventory-enhanced')); ?>');
            }
            button.prop('disabled', false).text('<?php echo esc_js(__('Clear Cache', 'inventory-enhanced')); ?>');
        });
    });
});
</script>