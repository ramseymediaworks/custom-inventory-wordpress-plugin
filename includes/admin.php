<?php
/**
 * Inventory Enhanced - Admin Interface Core
 * 
 * Handles the admin interface functionality including:
 * - Settings management and validation
 * - Admin notices and messaging
 * - Plugin status monitoring
 * - Cache management
 * - System information
 * - Import/export functionality
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced Admin Class
 */
class InventoryEnhanced_Admin {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Plugin instance
     */
    private $plugin;
    
    /**
     * Admin notices
     */
    private $notices = array();
    
    /**
     * Constructor
     */
    public function __construct($plugin_instance) {
        $this->plugin = $plugin_instance;
        $this->settings = $plugin_instance->settings;
        $this->init();
    }
    
    /**
     * Initialize admin interface
     */
    private function init() {
        // Admin hooks
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Settings hooks
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_inventory_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_inventory_reset_settings', array($this, 'handle_reset_settings'));
        add_action('wp_ajax_inventory_clear_cache', array($this, 'handle_clear_cache'));
        add_action('wp_ajax_inventory_export_settings', array($this, 'handle_export_settings'));
        add_action('wp_ajax_inventory_import_settings', array($this, 'handle_import_settings'));
        add_action('wp_ajax_inventory_system_info', array($this, 'handle_system_info'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . INVENTORY_ENHANCED_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // Admin bar
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Check for setup completion
        $this->check_setup_status();
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Handle settings updates
        if (isset($_POST['inventory_enhanced_settings_nonce'])) {
            $this->process_settings_form();
        }
        
        // Add admin notices based on plugin state
        $this->check_admin_notices();
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register main settings group
        register_setting(
            'inventory_enhanced_settings',
            'inventory_enhanced_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings()
            )
        );
        
        // General settings section
        add_settings_section(
            'inventory_enhanced_general',
            __('General Settings', 'inventory-enhanced'),
            array($this, 'general_settings_section_callback'),
            'inventory_enhanced_settings'
        );
        
        // Filter settings section
        add_settings_section(
            'inventory_enhanced_filters',
            __('Filter Settings', 'inventory-enhanced'),
            array($this, 'filter_settings_section_callback'),
            'inventory_enhanced_settings'
        );
        
        // Gallery settings section
        add_settings_section(
            'inventory_enhanced_gallery',
            __('Gallery Settings', 'inventory-enhanced'),
            array($this, 'gallery_settings_section_callback'),
            'inventory_enhanced_settings'
        );
        
        // Performance settings section
        add_settings_section(
            'inventory_enhanced_performance',
            __('Performance Settings', 'inventory-enhanced'),
            array($this, 'performance_settings_section_callback'),
            'inventory_enhanced_settings'
        );
        
        // Add individual settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings fields
        add_settings_field(
            'main_category_slug',
            __('Main Category Slug', 'inventory-enhanced'),
            array($this, 'text_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_general',
            array(
                'field' => 'main_category_slug',
                'description' => __('The slug of your main inventory category (default: inventory)', 'inventory-enhanced'),
                'default' => 'inventory'
            )
        );
        
        add_settings_field(
            'posts_per_page',
            __('Posts Per Page', 'inventory-enhanced'),
            array($this, 'number_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_general',
            array(
                'field' => 'posts_per_page',
                'description' => __('Number of inventory items to show per page', 'inventory-enhanced'),
                'min' => 1,
                'max' => 50,
                'default' => 8
            )
        );
        
        add_settings_field(
            'primary_color',
            __('Primary Color', 'inventory-enhanced'),
            array($this, 'color_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_general',
            array(
                'field' => 'primary_color',
                'description' => __('Main brand color used throughout the interface', 'inventory-enhanced'),
                'default' => '#2c5aa0'
            )
        );
        
        add_settings_field(
            'secondary_color',
            __('Secondary Color', 'inventory-enhanced'),
            array($this, 'color_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_general',
            array(
                'field' => 'secondary_color',
                'description' => __('Accent color for buttons and highlights', 'inventory-enhanced'),
                'default' => '#ff6900'
            )
        );
        
        // Filter settings fields
        add_settings_field(
            'enable_progressive_filtering',
            __('Progressive Filtering', 'inventory-enhanced'),
            array($this, 'checkbox_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_filters',
            array(
                'field' => 'enable_progressive_filtering',
                'description' => __('Enable smart filtering that shows/hides options based on availability', 'inventory-enhanced'),
                'default' => true
            )
        );
        
        add_settings_field(
            'filter_layout',
            __('Filter Layout', 'inventory-enhanced'),
            array($this, 'select_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_filters',
            array(
                'field' => 'filter_layout',
                'description' => __('Choose how filters are displayed', 'inventory-enhanced'),
                'options' => array(
                    'sidebar' => __('Sidebar (Recommended)', 'inventory-enhanced'),
                    'top' => __('Top Bar', 'inventory-enhanced')
                ),
                'default' => 'sidebar'
            )
        );
        
        // Gallery settings fields
        add_settings_field(
            'enable_gallery_override',
            __('Enhanced Galleries', 'inventory-enhanced'),
            array($this, 'checkbox_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_gallery',
            array(
                'field' => 'enable_gallery_override',
                'description' => __('Replace Divi gallery dots with thumbnail navigation (requires Divi)', 'inventory-enhanced'),
                'default' => true
            )
        );
        
        add_settings_field(
            'gallery_show_captions',
            __('Show Image Captions', 'inventory-enhanced'),
            array($this, 'checkbox_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_gallery',
            array(
                'field' => 'gallery_show_captions',
                'description' => __('Display image captions in the gallery', 'inventory-enhanced'),
                'default' => true
            )
        );
        
        add_settings_field(
            'gallery_swipe_enabled',
            __('Touch/Swipe Navigation', 'inventory-enhanced'),
            array($this, 'checkbox_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_gallery',
            array(
                'field' => 'gallery_swipe_enabled',
                'description' => __('Enable touch and swipe navigation on mobile devices', 'inventory-enhanced'),
                'default' => true
            )
        );
        
        // Performance settings fields
        add_settings_field(
            'enable_caching',
            __('Enable Caching', 'inventory-enhanced'),
            array($this, 'checkbox_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_performance',
            array(
                'field' => 'enable_caching',
                'description' => __('Cache filter results and category counts for better performance', 'inventory-enhanced'),
                'default' => true
            )
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (minutes)', 'inventory-enhanced'),
            array($this, 'number_field_callback'),
            'inventory_enhanced_settings',
            'inventory_enhanced_performance',
            array(
                'field' => 'cache_duration',
                'description' => __('How long to cache filter results', 'inventory-enhanced'),
                'min' => 1,
                'max' => 1440,
                'default' => 60
            )
        );
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            'main_category_slug' => 'inventory',
            'posts_per_page' => 8,
            'primary_color' => '#2c5aa0',
            'secondary_color' => '#ff6900',
            'enable_progressive_filtering' => true,
            'filter_layout' => 'sidebar',
            'enable_gallery_override' => true,
            'gallery_show_captions' => true,
            'gallery_swipe_enabled' => true,
            'enable_caching' => true,
            'cache_duration' => 60,
            'setup_completed' => false
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $clean_settings = array();
        $defaults = $this->get_default_settings();
        
        // Main category slug
        $clean_settings['main_category_slug'] = isset($settings['main_category_slug']) ? 
            sanitize_title($settings['main_category_slug']) : $defaults['main_category_slug'];
        
        // Posts per page
        $clean_settings['posts_per_page'] = isset($settings['posts_per_page']) ? 
            max(1, min(50, intval($settings['posts_per_page']))) : $defaults['posts_per_page'];
        
        // Colors
        $clean_settings['primary_color'] = isset($settings['primary_color']) ? 
            sanitize_hex_color($settings['primary_color']) : $defaults['primary_color'];
        $clean_settings['secondary_color'] = isset($settings['secondary_color']) ? 
            sanitize_hex_color($settings['secondary_color']) : $defaults['secondary_color'];
        
        // Boolean settings
        $boolean_fields = array(
            'enable_progressive_filtering',
            'enable_gallery_override', 
            'gallery_show_captions',
            'gallery_swipe_enabled',
            'enable_caching',
            'setup_completed'
        );
        
        foreach ($boolean_fields as $field) {
            $clean_settings[$field] = isset($settings[$field]) ? (bool) $settings[$field] : $defaults[$field];
        }
        
        // Filter layout
        $clean_settings['filter_layout'] = isset($settings['filter_layout']) && 
            in_array($settings['filter_layout'], array('sidebar', 'top')) ? 
            $settings['filter_layout'] : $defaults['filter_layout'];
        
        // Cache duration
        $clean_settings['cache_duration'] = isset($settings['cache_duration']) ? 
            max(1, min(1440, intval($settings['cache_duration']))) : $defaults['cache_duration'];
        
        return $clean_settings;
    }
    
    /**
     * Process settings form
     */
    private function process_settings_form() {
        if (!wp_verify_nonce($_POST['inventory_enhanced_settings_nonce'], 'inventory_enhanced_settings')) {
            $this->add_notice('error', __('Security check failed. Please try again.', 'inventory-enhanced'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            $this->add_notice('error', __('You do not have permission to update settings.', 'inventory-enhanced'));
            return;
        }
        
        $settings = isset($_POST['inventory_enhanced_settings']) ? $_POST['inventory_enhanced_settings'] : array();
        $clean_settings = $this->sanitize_settings($settings);
        
        // Update settings
        update_option('inventory_enhanced_settings', $clean_settings);
        $this->plugin->settings = $clean_settings;
        
        // Clear cache if caching settings changed
        if (isset($settings['enable_caching']) || isset($settings['cache_duration'])) {
            $this->clear_plugin_cache();
        }
        
        $this->add_notice('success', __('Settings saved successfully!', 'inventory-enhanced'));
        
        // Redirect to prevent form resubmission
        wp_redirect(admin_url('admin.php?page=inventory-enhanced-settings&updated=1'));
        exit;
    }
    
    /**
     * Check admin notices
     */
    private function check_admin_notices() {
        // Setup incomplete notice
        if (!$this->plugin->get_setting('setup_completed', false)) {
            $setup_url = admin_url('admin.php?page=inventory-enhanced-setup');
            $this->add_notice('warning', sprintf(
                __('Inventory Enhanced setup is not complete. <a href="%s">Run the setup wizard</a> to get started.', 'inventory-enhanced'),
                $setup_url
            ));
        }
        
        // Divi not active notice
        if ($this->plugin->get_setting('enable_gallery_override', true) && !$this->is_divi_active()) {
            $this->add_notice('info', __('Gallery enhancements require Divi theme. Some features may not be available.', 'inventory-enhanced'));
        }
        
        // Main category missing notice
        $main_category_slug = $this->plugin->get_setting('main_category_slug', 'inventory');
        if (!get_term_by('slug', $main_category_slug, 'category')) {
            $categories_url = admin_url('edit-tags.php?taxonomy=category');
            $this->add_notice('error', sprintf(
                __('Main inventory category "%s" not found. <a href="%s">Create it manually</a> or run the setup wizard.', 'inventory-enhanced'),
                $main_category_slug,
                $categories_url
            ));
        }
        
        // Cache status notice
        if ($this->plugin->get_setting('enable_caching', true) && !wp_using_ext_object_cache()) {
            $this->add_notice('info', __('Consider installing a caching plugin like Redis or Memcached for better performance.', 'inventory-enhanced'));
        }
    }
    
    /**
     * Add admin notice
     */
    private function add_notice($type, $message) {
        $this->notices[] = array(
            'type' => $type,
            'message' => $message
        );
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        foreach ($this->notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            if ($notice['type'] === 'success') {
                $class .= ' is-dismissible';
            }
            printf('<div class="%s"><p>%s</p></div>', esc_attr($class), wp_kses_post($notice['message']));
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'inventory-enhanced') === false) {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        // Plugin admin styles and scripts are enqueued by main plugin class
    }
    
    /**
     * Handle save settings AJAX
     */
    public function handle_save_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $clean_settings = $this->sanitize_settings($settings);
        
        update_option('inventory_enhanced_settings', $clean_settings);
        $this->plugin->settings = $clean_settings;
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully!', 'inventory-enhanced'),
            'settings' => $clean_settings
        ));
    }
    
    /**
     * Handle reset settings AJAX
     */
    public function handle_reset_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        $default_settings = $this->get_default_settings();
        update_option('inventory_enhanced_settings', $default_settings);
        $this->plugin->settings = $default_settings;
        
        // Clear cache
        $this->clear_plugin_cache();
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults successfully!', 'inventory-enhanced'),
            'settings' => $default_settings
        ));
    }
    
    /**
     * Handle clear cache AJAX
     */
    public function handle_clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        $cleared = $this->clear_plugin_cache();
        
        if ($cleared) {
            wp_send_json_success(__('Cache cleared successfully!', 'inventory-enhanced'));
        } else {
            wp_send_json_error(__('Failed to clear cache', 'inventory-enhanced'));
        }
    }
    
    /**
     * Handle export settings AJAX
     */
    public function handle_export_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('export')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        $export_data = array(
            'plugin' => 'inventory-enhanced',
            'version' => INVENTORY_ENHANCED_VERSION,
            'exported' => current_time('mysql'),
            'settings' => $this->plugin->settings,
            'categories' => $this->export_categories(),
            'usage_stats' => get_option('inventory_enhanced_template_usage', array())
        );
        
        $filename = 'inventory-enhanced-settings-' . date('Y-m-d-H-i-s') . '.json';
        
        wp_send_json_success(array(
            'filename' => $filename,
            'data' => wp_json_encode($export_data, JSON_PRETTY_PRINT),
            'message' => __('Settings exported successfully!', 'inventory-enhanced')
        ));
    }
    
    /**
     * Handle import settings AJAX
     */
    public function handle_import_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('import')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded', 'inventory-enhanced'));
        }
        
        $file = $_FILES['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'inventory-enhanced'));
        }
        
        $content = file_get_contents($file['tmp_name']);
        $import_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON file', 'inventory-enhanced'));
        }
        
        if (!isset($import_data['plugin']) || $import_data['plugin'] !== 'inventory-enhanced') {
            wp_send_json_error(__('Invalid export file', 'inventory-enhanced'));
        }
        
        // Import settings
        if (isset($import_data['settings'])) {
            $clean_settings = $this->sanitize_settings($import_data['settings']);
            update_option('inventory_enhanced_settings', $clean_settings);
            $this->plugin->settings = $clean_settings;
        }
        
        // Clear cache after import
        $this->clear_plugin_cache();
        
        wp_send_json_success(__('Settings imported successfully!', 'inventory-enhanced'));
    }
    
    /**
     * Handle system info AJAX
     */
    public function handle_system_info() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        $system_info = $this->get_system_info();
        
        wp_send_json_success($system_info);
    }
    
    /**
     * Get system information
     */
    private function get_system_info() {
        global $wpdb;
        
        $theme = wp_get_theme();
        
        return array(
            'plugin_version' => INVENTORY_ENHANCED_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'theme_name' => $theme->get('Name'),
            'theme_version' => $theme->get('Version'),
            'is_divi_active' => $this->is_divi_active(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'object_cache' => wp_using_ext_object_cache(),
            'active_plugins' => get_option('active_plugins'),
            'multisite' => is_multisite(),
            'inventory_posts_count' => $this->get_inventory_posts_count(),
            'cache_status' => $this->get_cache_status()
        );
    }
    
    /**
     * Get inventory posts count
     */
    private function get_inventory_posts_count() {
        $main_category_slug = $this->plugin->get_setting('main_category_slug', 'inventory');
        $category = get_term_by('slug', $main_category_slug, 'category');
        
        if (!$category) {
            return 0;
        }
        
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $category->term_id
                )
            )
        ));
        
        return $query->found_posts;
    }
    
    /**
     * Get cache status
     */
    private function get_cache_status() {
        return array(
            'enabled' => $this->plugin->get_setting('enable_caching', true),
            'duration' => $this->plugin->get_setting('cache_duration', 60),
            'object_cache' => wp_using_ext_object_cache(),
            'cache_plugin' => $this->detect_cache_plugin()
        );
    }
    
    /**
     * Detect cache plugin
     */
    private function detect_cache_plugin() {
        if (function_exists('wp_cache_flush')) {
            if (class_exists('WP_Rocket')) {
                return 'WP Rocket';
            } elseif (function_exists('w3tc_flush_all')) {
                return 'W3 Total Cache';
            } elseif (function_exists('wp_cache_clear_cache')) {
                return 'WP Super Cache';
            } elseif (class_exists('LiteSpeed_Cache')) {
                return 'LiteSpeed Cache';
            }
        }
        return __('None detected', 'inventory-enhanced');
    }
    
    /**
     * Export categories
     */
    private function export_categories() {
        $main_category_slug = $this->plugin->get_setting('main_category_slug', 'inventory');
        $main_category = get_term_by('slug', $main_category_slug, 'category');
        
        if (!$main_category) {
            return array();
        }
        
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'child_of' => $main_category->term_id,
            'hide_empty' => false
        ));
        
        $export_categories = array();
        foreach ($categories as $category) {
            $export_categories[] = array(
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent' => $category->parent
            );
        }
        
        return $export_categories;
    }
    
    /**
     * Clear plugin cache
     */
    private function clear_plugin_cache() {
        // Clear WordPress object cache
        wp_cache_flush_group('inventory-enhanced');
        
        // Clear transients
        delete_transient('inventory_enhanced_categories');
        delete_transient('inventory_enhanced_filter_counts');
        
        // Clear any other plugin-specific cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        return true;
    }
    
    /**
     * Check setup status
     */
    private function check_setup_status() {
        // Mark setup as completed if main category exists and we have inventory posts
        if (!$this->plugin->get_setting('setup_completed', false)) {
            $main_category_slug = $this->plugin->get_setting('main_category_slug', 'inventory');
            $main_category = get_term_by('slug', $main_category_slug, 'category');
            
            if ($main_category && $this->get_inventory_posts_count() > 0) {
                $this->plugin->update_setting('setup_completed', true);
            }
        }
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=inventory-enhanced-settings') . '">' . __('Settings', 'inventory-enhanced') . '</a>',
            'templates' => '<a href="' . admin_url('admin.php?page=inventory-enhanced-templates') . '">' . __('Templates', 'inventory-enhanced') . '</a>'
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === INVENTORY_ENHANCED_PLUGIN_BASENAME) {
            $row_meta = array(
                'docs' => '<a href="https://docs.example.com/inventory-enhanced" target="_blank">' . __('Documentation', 'inventory-enhanced') . '</a>',
                'support' => '<a href="https://support.example.com" target="_blank">' . __('Support', 'inventory-enhanced') . '</a>'
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Admin bar menu
     */
    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id' => 'inventory-enhanced',
            'title' => '<span class="ab-icon dashicons-grid-view"></span>' . __('Inventory', 'inventory-enhanced'),
            'href' => admin_url('admin.php?page=inventory-enhanced')
        ));
        
        $wp_admin_bar->add_node(array(
            'id' => 'inventory-enhanced-settings',
            'parent' => 'inventory-enhanced',
            'title' => __('Settings', 'inventory-enhanced'),
            'href' => admin_url('admin.php?page=inventory-enhanced-settings')
        ));
        
        $wp_admin_bar->add_node(array(
            'id' => 'inventory-enhanced-templates',
            'parent' => 'inventory-enhanced',
            'title' => __('Templates', 'inventory-enhanced'),
            'href' => admin_url('admin.php?page=inventory-enhanced-templates')
        ));
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'inventory_enhanced_stats',
            __('Inventory Enhanced Stats', 'inventory-enhanced'),
            array($this, 'dashboard_widget_stats')
        );
    }
    
    /**
     * Dashboard widget stats
     */
    public function dashboard_widget_stats() {
        $inventory_count = $this->get_inventory_posts_count();
        $main_category_slug = $this->plugin->get_setting('main_category_slug', 'inventory');
        $setup_completed = $this->plugin->get_setting('setup_completed', false);
        
        ?>
        <div class="inventory-enhanced-dashboard-widget">
            <div class="widget-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($inventory_count); ?></span>
                    <span class="stat-label"><?php _e('Inventory Items', 'inventory-enhanced'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-icon <?php echo $setup_completed ? 'completed' : 'pending'; ?>">
                        <?php echo $setup_completed ? '✓' : '⏳'; ?>
                    </span>
                    <span class="stat-label"><?php _e('Setup Status', 'inventory-enhanced'); ?></span>
                </div>
            </div>
            
            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=inventory-enhanced'); ?>" class="button button-primary">
                    <?php _e('View Dashboard', 'inventory-enhanced'); ?>
                </a>
                
                <?php if (!$setup_completed): ?>
                    <a href="<?php echo admin_url('admin.php?page=inventory-enhanced-setup'); ?>" class="button">
                        <?php _e('Complete Setup', 'inventory-enhanced'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .inventory-enhanced-dashboard-widget .widget-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .inventory-enhanced-dashboard-widget .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .inventory-enhanced-dashboard-widget .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
        }
        
        .inventory-enhanced-dashboard-widget .stat-icon {
            display: block;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .inventory-enhanced-dashboard-widget .stat-icon.completed {
            color: #4caf50;
        }
        
        .inventory-enhanced-dashboard-widget .stat-icon.pending {
            color: #ff9800;
        }
        
        .inventory-enhanced-dashboard-widget .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .inventory-enhanced-dashboard-widget .widget-actions {
            display: flex;
            gap: 10px;
        }
        
        .inventory-enhanced-dashboard-widget .widget-actions .button {
            flex: 1;
            text-align: center;
        }
        </style>
        <?php
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
    
    // Settings field callbacks
    public function general_settings_section_callback() {
        echo '<p>' . __('General plugin settings and configuration.', 'inventory-enhanced') . '</p>';
    }
    
    public function filter_settings_section_callback() {
        echo '<p>' . __('Configure how the inventory filtering system works.', 'inventory-enhanced') . '</p>';
    }
    
    public function gallery_settings_section_callback() {
        echo '<p>' . __('Settings for the enhanced gallery features (requires Divi).', 'inventory-enhanced') . '</p>';
    }
    
    public function performance_settings_section_callback() {
        echo '<p>' . __('Performance and caching settings to optimize your site.', 'inventory-enhanced') . '</p>';
    }
    
    public function text_field_callback($args) {
        $field = $args['field'];
        $value = $this->plugin->get_setting($field, $args['default']);
        $description = $args['description'];
        
        echo '<input type="text" id="' . esc_attr($field) . '" name="inventory_enhanced_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function number_field_callback($args) {
        $field = $args['field'];
        $value = $this->plugin->get_setting($field, $args['default']);
        $description = $args['description'];
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        
        echo '<input type="number" id="' . esc_attr($field) . '" name="inventory_enhanced_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="small-text"';
        if ($min !== '') echo ' min="' . esc_attr($min) . '"';
        if ($max !== '') echo ' max="' . esc_attr($max) . '"';
        echo ' />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function color_field_callback($args) {
        $field = $args['field'];
        $value = $this->plugin->get_setting($field, $args['default']);
        $description = $args['description'];
        
        echo '<input type="text" id="' . esc_attr($field) . '" name="inventory_enhanced_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="color-picker" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function checkbox_field_callback($args) {
        $field = $args['field'];
        $value = $this->plugin->get_setting($field, $args['default']);
        $description = $args['description'];
        
        echo '<label><input type="checkbox" id="' . esc_attr($field) . '" name="inventory_enhanced_settings[' . esc_attr($field) . ']" value="1" ' . checked(1, $value, false) . ' /> ' . esc_html($description) . '</label>';
    }
    
    public function select_field_callback($args) {
        $field = $args['field'];
        $value = $this->plugin->get_setting($field, $args['default']);
        $description = $args['description'];
        $options = $args['options'];
        
        echo '<select id="' . esc_attr($field) . '" name="inventory_enhanced_settings[' . esc_attr($field) . ']">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
}

?>