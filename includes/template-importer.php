<?php
/**
 * Inventory Enhanced - Template Importer System
 * 
 * Handles importing of JSON page templates including:
 * - Divi page layout imports
 * - Template validation and processing
 * - Page creation with imported layouts
 * - Template library management
 * - Demo content installation
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced Template Importer Class
 */
class InventoryEnhanced_TemplateImporter {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Templates directory
     */
    private $templates_dir;
    
    /**
     * Supported template types
     */
    private $supported_types = array(
        'homepage' => 'Inventory Homepage',
        'single' => 'Single Item Page', 
        'category' => 'Category Page',
        'search' => 'Search Results Page'
    );
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->templates_dir = INVENTORY_ENHANCED_PLUGIN_DIR . 'templates/';
        $this->init();
    }
    
    /**
     * Initialize template importer
     */
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_import_inventory_template', array($this, 'handle_import_template'));
        add_action('wp_ajax_get_template_preview', array($this, 'handle_get_template_preview'));
        add_action('wp_ajax_export_inventory_template', array($this, 'handle_export_template'));
        add_action('wp_ajax_delete_inventory_template', array($this, 'handle_delete_template'));
        
        // Add admin notices for import results
        add_action('admin_notices', array($this, 'show_import_notices'));
        
        // Register custom post type for template storage
        add_action('init', array($this, 'register_template_post_type'));
        
        // Create templates directory if it doesn't exist
        $this->ensure_templates_directory();
    }
    
    /**
     * Register custom post type for storing templates
     */
    public function register_template_post_type() {
        register_post_type('inventory_template', array(
            'labels' => array(
                'name' => __('Inventory Templates', 'inventory-enhanced'),
                'singular_name' => __('Inventory Template', 'inventory-enhanced'),
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'editor'),
            'can_export' => false
        ));
    }
    
    /**
     * Ensure templates directory exists
     */
    private function ensure_templates_directory() {
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
        
        // Create demo content directory
        $demo_dir = $this->templates_dir . 'demo-content/';
        if (!file_exists($demo_dir)) {
            wp_mkdir_p($demo_dir);
        }
    }
    
    /**
     * Get available templates
     */
    public function get_available_templates() {
        $templates = array();
        
        // Get built-in templates from filesystem
        $built_in_templates = $this->get_builtin_templates();
        
        // Get custom templates from database
        $custom_templates = $this->get_custom_templates();
        
        return array_merge($built_in_templates, $custom_templates);
    }
    
    /**
     * Get built-in templates from filesystem
     */
    private function get_builtin_templates() {
        $templates = array();
        
        foreach ($this->supported_types as $type => $label) {
            $template_file = $this->templates_dir . "inventory-{$type}.json";
            
            if (file_exists($template_file)) {
                $template_data = $this->parse_template_file($template_file);
                
                if ($template_data) {
                    $templates[] = array(
                        'id' => $type,
                        'name' => $label,
                        'type' => $type,
                        'source' => 'builtin',
                        'file' => $template_file,
                        'description' => $template_data['description'] ?? sprintf(__('Professional %s layout', 'inventory-enhanced'), strtolower($label)),
                        'preview_image' => $this->get_template_preview_image($type),
                        'version' => $template_data['version'] ?? '1.0.0',
                        'author' => $template_data['author'] ?? 'Inventory Enhanced',
                        'created_date' => date('Y-m-d', filemtime($template_file)),
                        'compatible_version' => $template_data['compatible_version'] ?? INVENTORY_ENHANCED_VERSION,
                        'features' => $template_data['features'] ?? array(),
                        'requirements' => $template_data['requirements'] ?? array()
                    );
                }
            }
        }
        
        return $templates;
    }
    
    /**
     * Get custom templates from database
     */
    private function get_custom_templates() {
        $templates = array();
        
        $custom_templates = get_posts(array(
            'post_type' => 'inventory_template',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_template_data',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        foreach ($custom_templates as $template_post) {
            $template_data = get_post_meta($template_post->ID, '_template_data', true);
            $template_meta = get_post_meta($template_post->ID, '_template_meta', true);
            
            if ($template_data) {
                $templates[] = array(
                    'id' => 'custom_' . $template_post->ID,
                    'post_id' => $template_post->ID,
                    'name' => $template_post->post_title,
                    'type' => $template_meta['type'] ?? 'custom',
                    'source' => 'custom',
                    'description' => $template_post->post_excerpt ?: __('Custom template', 'inventory-enhanced'),
                    'preview_image' => $template_meta['preview_image'] ?? '',
                    'version' => $template_meta['version'] ?? '1.0.0',
                    'author' => $template_meta['author'] ?? get_userdata($template_post->post_author)->display_name,
                    'created_date' => $template_post->post_date,
                    'data' => $template_data,
                    'features' => $template_meta['features'] ?? array(),
                    'requirements' => $template_meta['requirements'] ?? array()
                );
            }
        }
        
        return $templates;
    }
    
    /**
     * Parse template file
     */
    private function parse_template_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        $template_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            inventory_enhanced_log("Failed to parse template file: {$file_path}. JSON Error: " . json_last_error_msg());
            return false;
        }
        
        return $template_data;
    }
    
    /**
     * Get template preview image
     */
    private function get_template_preview_image($template_type) {
        $preview_file = INVENTORY_ENHANCED_PLUGIN_URL . "assets/images/template-previews/{$template_type}-preview.jpg";
        
        // Check if preview image exists
        $preview_path = INVENTORY_ENHANCED_PLUGIN_DIR . "assets/images/template-previews/{$template_type}-preview.jpg";
        if (file_exists($preview_path)) {
            return $preview_file;
        }
        
        // Return default preview image
        return INVENTORY_ENHANCED_PLUGIN_URL . 'assets/images/template-previews/default-preview.jpg';
    }
    
    /**
     * Handle import template AJAX request
     */
    public function handle_import_template() {
        try {
            // Check permissions
            if (!current_user_can('edit_pages')) {
                wp_die(__('Insufficient permissions to import templates', 'inventory-enhanced'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
                wp_die(__('Security check failed', 'inventory-enhanced'));
            }
            
            $template_id = sanitize_text_field($_POST['template_id']);
            $create_page = isset($_POST['create_page']) ? (bool) $_POST['create_page'] : true;
            $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
            $page_slug = isset($_POST['page_slug']) ? sanitize_title($_POST['page_slug']) : '';
            
            // Get template data
            $template = $this->get_template_by_id($template_id);
            if (!$template) {
                wp_send_json_error(__('Template not found', 'inventory-enhanced'));
                return;
            }
            
            // Validate template requirements
            $validation_result = $this->validate_template_requirements($template);
            if (!$validation_result['valid']) {
                wp_send_json_error(array(
                    'message' => __('Template requirements not met', 'inventory-enhanced'),
                    'requirements' => $validation_result['missing_requirements']
                ));
                return;
            }
            
            // Import the template
            $import_result = $this->import_template($template, array(
                'create_page' => $create_page,
                'page_title' => $page_title,
                'page_slug' => $page_slug
            ));
            
            if ($import_result['success']) {
                // Log successful import
                inventory_enhanced_log("Template '{$template['name']}' imported successfully");
                
                wp_send_json_success(array(
                    'message' => sprintf(__('Template "%s" imported successfully', 'inventory-enhanced'), $template['name']),
                    'page_id' => $import_result['page_id'] ?? null,
                    'page_url' => $import_result['page_url'] ?? null,
                    'edit_url' => $import_result['edit_url'] ?? null
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $import_result['message'] ?? __('Failed to import template', 'inventory-enhanced')
                ));
            }
            
        } catch (Exception $e) {
            inventory_enhanced_log('Template import error: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while importing the template', 'inventory-enhanced'));
        }
    }
    
    /**
     * Get template by ID
     */
    private function get_template_by_id($template_id) {
        $available_templates = $this->get_available_templates();
        
        foreach ($available_templates as $template) {
            if ($template['id'] === $template_id) {
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * Validate template requirements
     */
    private function validate_template_requirements($template) {
        $missing_requirements = array();
        
        if (isset($template['requirements'])) {
            // Check WordPress version
            if (isset($template['requirements']['wordpress_version'])) {
                $required_wp_version = $template['requirements']['wordpress_version'];
                if (version_compare(get_bloginfo('version'), $required_wp_version, '<')) {
                    $missing_requirements[] = sprintf(
                        __('WordPress %s or higher (currently %s)', 'inventory-enhanced'),
                        $required_wp_version,
                        get_bloginfo('version')
                    );
                }
            }
            
            // Check Divi theme
            if (isset($template['requirements']['divi']) && $template['requirements']['divi']) {
                if (!$this->is_divi_active()) {
                    $missing_requirements[] = __('Divi theme must be active', 'inventory-enhanced');
                }
            }
            
            // Check plugins
            if (isset($template['requirements']['plugins']) && is_array($template['requirements']['plugins'])) {
                foreach ($template['requirements']['plugins'] as $plugin) {
                    if (!is_plugin_active($plugin)) {
                        $missing_requirements[] = sprintf(
                            __('Plugin required: %s', 'inventory-enhanced'),
                            $plugin
                        );
                    }
                }
            }
            
            // Check PHP version
            if (isset($template['requirements']['php_version'])) {
                $required_php_version = $template['requirements']['php_version'];
                if (version_compare(PHP_VERSION, $required_php_version, '<')) {
                    $missing_requirements[] = sprintf(
                        __('PHP %s or higher (currently %s)', 'inventory-enhanced'),
                        $required_php_version,
                        PHP_VERSION
                    );
                }
            }
        }
        
        return array(
            'valid' => empty($missing_requirements),
            'missing_requirements' => $missing_requirements
        );
    }
    
    /**
     * Import template
     */
    private function import_template($template, $options = array()) {
        // Get template data
        $template_data = $this->get_template_data($template);
        if (!$template_data) {
            return array(
                'success' => false,
                'message' => __('Failed to load template data', 'inventory-enhanced')
            );
        }
        
        $page_id = null;
        $page_url = null;
        $edit_url = null;
        
        // Create page if requested
        if ($options['create_page']) {
            $page_title = $options['page_title'] ?: $template['name'];
            $page_slug = $options['page_slug'] ?: sanitize_title($page_title);
            
            // Check if page already exists
            $existing_page = get_page_by_path($page_slug);
            if ($existing_page) {
                return array(
                    'success' => false,
                    'message' => __('A page with this slug already exists', 'inventory-enhanced')
                );
            }
            
            // Create the page
            $page_content = $this->process_template_content($template_data['content'] ?? '');
            
            $page_id = wp_insert_post(array(
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'draft', // Start as draft for safety
                'post_type' => 'page',
                'post_name' => $page_slug,
                'meta_input' => array(
                    '_et_pb_use_builder' => 'on',
                    '_et_pb_page_layout' => 'et_full_width_page',
                    '_inventory_enhanced_template' => $template['id'],
                    '_inventory_enhanced_template_version' => $template['version'] ?? '1.0.0'
                )
            ));
            
            if (is_wp_error($page_id)) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create page', 'inventory-enhanced')
                );
            }
            
            // Set page URLs
            $page_url = get_permalink($page_id);
            $edit_url = admin_url("post.php?post={$page_id}&action=edit");
            
            // Add template meta data
            update_post_meta($page_id, '_template_imported_date', current_time('mysql'));
            update_post_meta($page_id, '_template_source', $template['source']);
            
            // Set up additional page settings if specified in template
            if (isset($template_data['page_settings'])) {
                foreach ($template_data['page_settings'] as $meta_key => $meta_value) {
                    update_post_meta($page_id, $meta_key, $meta_value);
                }
            }
        }
        
        // Import additional data (widgets, customizer settings, etc.)
        if (isset($template_data['additional_data'])) {
            $this->import_additional_data($template_data['additional_data'], $page_id);
        }
        
        // Mark template as used
        $this->mark_template_as_used($template['id']);
        
        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_url' => $page_url,
            'edit_url' => $edit_url,
            'message' => __('Template imported successfully', 'inventory-enhanced')
        );
    }
    
    /**
     * Get template data
     */
    private function get_template_data($template) {
        if ($template['source'] === 'builtin' && isset($template['file'])) {
            return $this->parse_template_file($template['file']);
        } elseif ($template['source'] === 'custom' && isset($template['data'])) {
            return $template['data'];
        }
        
        return null;
    }
    
    /**
     * Process template content (replace placeholders, etc.)
     */
    private function process_template_content($content) {
        // Replace common placeholders
        $replacements = array(
            '{{SITE_URL}}' => home_url(),
            '{{SITE_NAME}}' => get_bloginfo('name'),
            '{{PLUGIN_URL}}' => INVENTORY_ENHANCED_PLUGIN_URL,
            '{{PRIMARY_COLOR}}' => $this->settings['primary_color'] ?? '#2c5aa0',
            '{{SECONDARY_COLOR}}' => $this->settings['secondary_color'] ?? '#ff6900',
            '{{INVENTORY_CATEGORY}}' => $this->settings['main_category_slug'] ?? 'inventory'
        );
        
        foreach ($replacements as $placeholder => $replacement) {
            $content = str_replace($placeholder, $replacement, $content);
        }
        
        // Process shortcodes in template content
        $content = str_replace('[inventory_filters]', '[inventory_filters]', $content);
        
        return $content;
    }
    
    /**
     * Import additional data (widgets, options, etc.)
     */
    private function import_additional_data($additional_data, $page_id = null) {
        // Import theme customizer settings
        if (isset($additional_data['customizer_settings'])) {
            foreach ($additional_data['customizer_settings'] as $setting_name => $setting_value) {
                set_theme_mod($setting_name, $setting_value);
            }
        }
        
        // Import WordPress options
        if (isset($additional_data['wp_options'])) {
            foreach ($additional_data['wp_options'] as $option_name => $option_value) {
                update_option($option_name, $option_value);
            }
        }
        
        // Import menu locations
        if (isset($additional_data['menu_locations'])) {
            set_theme_mod('nav_menu_locations', $additional_data['menu_locations']);
        }
    }
    
    /**
     * Mark template as used (for analytics/tracking)
     */
    private function mark_template_as_used($template_id) {
        $usage_stats = get_option('inventory_enhanced_template_usage', array());
        
        if (!isset($usage_stats[$template_id])) {
            $usage_stats[$template_id] = array(
                'first_used' => current_time('mysql'),
                'usage_count' => 0
            );
        }
        
        $usage_stats[$template_id]['usage_count']++;
        $usage_stats[$template_id]['last_used'] = current_time('mysql');
        
        update_option('inventory_enhanced_template_usage', $usage_stats);
    }
    
    /**
     * Handle get template preview AJAX request
     */
    public function handle_get_template_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $template = $this->get_template_by_id($template_id);
        
        if (!$template) {
            wp_send_json_error(__('Template not found', 'inventory-enhanced'));
            return;
        }
        
        // Get detailed template info
        $template_data = $this->get_template_data($template);
        
        wp_send_json_success(array(
            'template' => $template,
            'preview_html' => $this->generate_template_preview_html($template, $template_data)
        ));
    }
    
    /**
     * Generate template preview HTML
     */
    private function generate_template_preview_html($template, $template_data) {
        ob_start();
        ?>
        <div class="template-preview">
            <div class="template-preview-header">
                <h3><?php echo esc_html($template['name']); ?></h3>
                <span class="template-version">v<?php echo esc_html($template['version']); ?></span>
            </div>
            
            <?php if ($template['preview_image']): ?>
                <div class="template-preview-image">
                    <img src="<?php echo esc_url($template['preview_image']); ?>" 
                         alt="<?php echo esc_attr($template['name']); ?>"
                         loading="lazy">
                </div>
            <?php endif; ?>
            
            <div class="template-info">
                <p class="template-description"><?php echo esc_html($template['description']); ?></p>
                
                <?php if (!empty($template['features'])): ?>
                    <div class="template-features">
                        <h4><?php _e('Features:', 'inventory-enhanced'); ?></h4>
                        <ul>
                            <?php foreach ($template['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="template-meta">
                    <p><strong><?php _e('Author:', 'inventory-enhanced'); ?></strong> <?php echo esc_html($template['author']); ?></p>
                    <p><strong><?php _e('Created:', 'inventory-enhanced'); ?></strong> <?php echo esc_html(date('F j, Y', strtotime($template['created_date']))); ?></p>
                    <?php if ($template['source'] === 'builtin'): ?>
                        <p><strong><?php _e('Type:', 'inventory-enhanced'); ?></strong> <?php _e('Built-in Template', 'inventory-enhanced'); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($template['requirements'])): ?>
                    <div class="template-requirements">
                        <h4><?php _e('Requirements:', 'inventory-enhanced'); ?></h4>
                        <ul>
                            <?php foreach ($template['requirements'] as $requirement => $value): ?>
                                <li>
                                    <?php 
                                    switch ($requirement) {
                                        case 'wordpress_version':
                                            echo sprintf(__('WordPress %s+', 'inventory-enhanced'), $value);
                                            break;
                                        case 'divi':
                                            echo __('Divi Theme', 'inventory-enhanced');
                                            break;
                                        case 'php_version':
                                            echo sprintf(__('PHP %s+', 'inventory-enhanced'), $value);
                                            break;
                                        default:
                                            echo esc_html($requirement . ': ' . $value);
                                    }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle export template AJAX request
     */
    public function handle_export_template() {
        if (!current_user_can('export')) {
            wp_die(__('Insufficient permissions to export templates', 'inventory-enhanced'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        $page_id = intval($_POST['page_id']);
        $template_name = sanitize_text_field($_POST['template_name']);
        $template_description = sanitize_textarea_field($_POST['template_description']);
        
        // Export the page as a template
        $export_result = $this->export_page_as_template($page_id, $template_name, $template_description);
        
        if ($export_result['success']) {
            wp_send_json_success(array(
                'message' => __('Template exported successfully', 'inventory-enhanced'),
                'template_id' => $export_result['template_id']
            ));
        } else {
            wp_send_json_error($export_result['message']);
        }
    }
    
    /**
     * Export page as template
     */
    private function export_page_as_template($page_id, $template_name, $template_description = '') {
        $page = get_post($page_id);
        if (!$page) {
            return array(
                'success' => false,
                'message' => __('Page not found', 'inventory-enhanced')
            );
        }
        
        // Prepare template data
        $template_data = array(
            'version' => '1.0.0',
            'name' => $template_name,
            'description' => $template_description,
            'author' => wp_get_current_user()->display_name,
            'created_date' => current_time('mysql'),
            'content' => $page->post_content,
            'page_settings' => array(),
            'compatible_version' => INVENTORY_ENHANCED_VERSION
        );
        
        // Get page meta data
        $page_meta = get_post_meta($page_id);
        foreach ($page_meta as $meta_key => $meta_values) {
            if (strpos($meta_key, '_et_pb') === 0 || strpos($meta_key, '_inventory_enhanced') === 0) {
                $template_data['page_settings'][$meta_key] = $meta_values[0];
            }
        }
        
        // Save as custom template
        $template_post_id = wp_insert_post(array(
            'post_title' => $template_name,
            'post_excerpt' => $template_description,
            'post_type' => 'inventory_template',
            'post_status' => 'publish',
            'meta_input' => array(
                '_template_data' => $template_data,
                '_template_meta' => array(
                    'type' => 'custom',
                    'version' => '1.0.0',
                    'author' => wp_get_current_user()->display_name,
                    'source_page_id' => $page_id
                )
            )
        ));
        
        if (is_wp_error($template_post_id)) {
            return array(
                'success' => false,
                'message' => __('Failed to save template', 'inventory-enhanced')
            );
        }
        
        return array(
            'success' => true,
            'template_id' => 'custom_' . $template_post_id,
            'post_id' => $template_post_id
        );
    }
    
    /**
     * Handle delete template AJAX request
     */
    public function handle_delete_template() {
        if (!current_user_can('delete_posts')) {
            wp_die(__('Insufficient permissions to delete templates', 'inventory-enhanced'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        
        // Only allow deletion of custom templates
        if (strpos($template_id, 'custom_') !== 0) {
            wp_send_json_error(__('Only custom templates can be deleted', 'inventory-enhanced'));
            return;
        }
        
        $post_id = intval(str_replace('custom_', '', $template_id));
        
        if (wp_delete_post($post_id, true)) {
            wp_send_json_success(__('Template deleted successfully', 'inventory-enhanced'));
        } else {
            wp_send_json_error(__('Failed to delete template', 'inventory-enhanced'));
        }
    }
    
    /**
     * Show import notices
     */
    public function show_import_notices() {
        if (isset($_GET['template_imported'])) {
            $template_name = sanitize_text_field($_GET['template_name'] ?? 'Template');
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('Template "%s" imported successfully!', 'inventory-enhanced'), $template_name) . '</p>';
            echo '</div>';
        }
        
        if (isset($_GET['template_import_error'])) {
            $error_message = sanitize_text_field($_GET['error_message'] ?? 'Unknown error');
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . sprintf(__('Template import failed: %s', 'inventory-enhanced'), $error_message) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Check if Divi is active
     */
    private function is_divi_active() {
        $theme = wp_get_theme();
        return (
            'Divi' === $theme->get('Name') || 
            'Divi' === $theme->get('Template') ||
            function_exists('et_divi_fonts_url')
        );
    }
    
    /**
     * Install demo content
     */
    public function install_demo_content() {
        if (!current_user_can('edit_posts')) {
            return false;
        }
        
        $demo_file = $this->templates_dir . 'demo-content/sample-inventory.xml';
        
        if (!file_exists($demo_file)) {
            return false;
        }
        
        // Import demo posts using WordPress importer
        if (!class_exists('WP_Import')) {
            $importer_path = ABSPATH . 'wp-admin/includes/import.php';
            if (file_exists($importer_path)) {
                require_once $importer_path;
            }
        }
        
        if (class_exists('WP_Import')) {
            $wp_import = new WP_Import();
            $wp_import->fetch_attachments = true;
            
            ob_start();
            $result = $wp_import->import($demo_file);
            ob_end_clean();
            
            if (!is_wp_error($result)) {
                // Mark demo content as installed
                update_option('inventory_enhanced_demo_installed', true);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get template usage statistics
     */
    public function get_template_usage_stats() {
        return get_option('inventory_enhanced_template_usage', array());
    }
}

?>