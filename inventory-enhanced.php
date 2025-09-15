<?php
/**
 * Plugin Name: Inventory Enhanced
 * Plugin URI: https://github.com/ramseymediaworks/custom-inventory-wordpress-plugin
 * Description: Complete inventory solution with smart filtering, enhanced galleries, and professional page templates for heavy equipment dealers.
 * Version: 1.0.0
 * Author: Ramsey MediaWorks
 * Author URI: https://ramseymediaworks.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inventory-enhanced
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('INVENTORY_ENHANCED_VERSION', '1.0.0');
define('INVENTORY_ENHANCED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_ENHANCED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INVENTORY_ENHANCED_PLUGIN_FILE', __FILE__);
define('INVENTORY_ENHANCED_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Inventory Enhanced Class
 */
class InventoryEnhanced {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    public $settings = array();
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Check requirements
        add_action('admin_init', array($this, 'check_requirements'));
        
        // Load plugin settings
        $this->load_settings();
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Admin initialization
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        
        // Frontend initialization
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        }
        
        // Setup wizard redirect
        add_action('admin_init', array($this, 'setup_wizard_redirect'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'inventory-enhanced',
            false,
            dirname(INVENTORY_ENHANCED_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set activation flag for setup wizard
        add_option('inventory_enhanced_activation_redirect', true);
        
        // Set default settings
        $default_settings = array(
            'main_category_slug' => 'inventory',
            'posts_per_page' => 8,
            'enable_gallery_override' => true,
            'enable_progressive_filtering' => true,
            'filter_layout' => 'sidebar',
            'primary_color' => '#2c5aa0',
            'secondary_color' => '#ff6900',
            'setup_completed' => false
        );
        
        add_option('inventory_enhanced_settings', $default_settings);
        
        // Create required pages if they don't exist
        $this->create_default_pages();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('Inventory Enhanced Plugin Activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('inventory_enhanced_filter_cache');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Inventory Enhanced Plugin Deactivated');
    }
    
    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Inventory Enhanced requires PHP 7.4 or higher. You are running PHP ', 'inventory-enhanced') . PHP_VERSION;
                echo '</p></div>';
            });
            return false;
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Inventory Enhanced requires WordPress 5.0 or higher.', 'inventory-enhanced');
                echo '</p></div>';
            });
            return false;
        }
        
        // Check if Divi is active (for gallery features)
        if (!$this->is_divi_active()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo __('Inventory Enhanced works best with Divi theme. Gallery enhancements will not be available without Divi.', 'inventory-enhanced');
                echo '</p></div>';
            });
        }
        
        return true;
    }
    
    /**
     * Check if Divi theme is active
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
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = get_option('inventory_enhanced_settings', array());
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        $includes = array(
            'includes/filters.php',
            'includes/gallery.php', 
            'includes/ajax.php',
            'includes/template-importer.php'
        );
        
        foreach ($includes as $file) {
            $filepath = INVENTORY_ENHANCED_PLUGIN_DIR . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
        
        // Admin files
        if (is_admin()) {
            $admin_includes = array(
                'includes/admin.php',
                'includes/setup-wizard.php'
            );
            
            foreach ($admin_includes as $file) {
                $filepath = INVENTORY_ENHANCED_PLUGIN_DIR . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
        }
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize filters
        if (class_exists('InventoryEnhanced_Filters')) {
            new InventoryEnhanced_Filters($this->settings);
        }
        
        // Initialize gallery enhancements
        if (class_exists('InventoryEnhanced_Gallery') && $this->get_setting('enable_gallery_override', true)) {
            new InventoryEnhanced_Gallery($this->settings);
        }
        
        // Initialize AJAX handlers
        if (class_exists('InventoryEnhanced_Ajax')) {
            new InventoryEnhanced_Ajax($this->settings);
        }
        
        // Initialize template importer
        if (class_exists('InventoryEnhanced_TemplateImporter')) {
            new InventoryEnhanced_TemplateImporter($this->settings);
        }
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        // Main menu page
        add_menu_page(
            __('Inventory Enhanced', 'inventory-enhanced'),
            __('Inventory Enhanced', 'inventory-enhanced'),
            'manage_options',
            'inventory-enhanced',
            array($this, 'admin_page'),
            'dashicons-grid-view',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'inventory-enhanced',
            __('Settings', 'inventory-enhanced'),
            __('Settings', 'inventory-enhanced'),
            'manage_options',
            'inventory-enhanced-settings',
            array($this, 'settings_page')
        );
        
        // Templates submenu
        add_submenu_page(
            'inventory-enhanced',
            __('Templates', 'inventory-enhanced'),
            __('Templates', 'inventory-enhanced'),
            'manage_options',
            'inventory-enhanced-templates',
            array($this, 'templates_page')
        );
        
        // Setup wizard submenu (hidden from menu)
        add_submenu_page(
            null, // No parent - hidden from menu
            __('Setup Wizard', 'inventory-enhanced'),
            __('Setup Wizard', 'inventory-enhanced'),
            'manage_options',
            'inventory-enhanced-setup',
            array($this, 'setup_wizard_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'inventory-enhanced') === false) {
            return;
        }
        
        wp_enqueue_style(
            'inventory-enhanced-admin',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            INVENTORY_ENHANCED_VERSION
        );
        
        wp_enqueue_script(
            'inventory-enhanced-admin',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            INVENTORY_ENHANCED_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('inventory-enhanced-admin', 'inventoryEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('inventory_enhanced_admin'),
            'pluginUrl' => INVENTORY_ENHANCED_PLUGIN_URL,
            'strings' => array(
                'confirm_import' => __('Are you sure you want to import this template?', 'inventory-enhanced'),
                'importing' => __('Importing...', 'inventory-enhanced'),
                'import_success' => __('Template imported successfully!', 'inventory-enhanced'),
                'import_error' => __('Error importing template.', 'inventory-enhanced')
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        // Only load on pages that need it
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        wp_enqueue_style(
            'inventory-enhanced-filters',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/css/inventory-filters.css',
            array(),
            INVENTORY_ENHANCED_VERSION
        );
        
        wp_enqueue_style(
            'inventory-enhanced-gallery',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/css/inventory-gallery.css',
            array(),
            INVENTORY_ENHANCED_VERSION
        );
        
        wp_enqueue_script(
            'inventory-enhanced-filters',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/js/inventory-filters.js',
            array('jquery'),
            INVENTORY_ENHANCED_VERSION,
            true
        );
        
        wp_enqueue_script(
            'inventory-enhanced-gallery',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/js/inventory-gallery.js',
            array('jquery'),
            INVENTORY_ENHANCED_VERSION,
            true
        );
    }
    
    /**
     * Check if we should load frontend assets
     */
    private function should_load_frontend_assets() {
        global $post;
        
        // Load on inventory posts
        if (is_single() && has_category($this->get_setting('main_category_slug', 'inventory'), $post)) {
            return true;
        }
        
        // Load on pages with inventory shortcode
        if (is_page() && $post && has_shortcode($post->post_content, 'inventory_filters')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Setup wizard redirect
     */
    public function setup_wizard_redirect() {
        if (get_option('inventory_enhanced_activation_redirect', false)) {
            delete_option('inventory_enhanced_activation_redirect');
            
            // Only redirect if setup hasn't been completed
            if (!$this->get_setting('setup_completed', false)) {
                wp_safe_redirect(admin_url('admin.php?page=inventory-enhanced-setup'));
                exit;
            }
        }
    }
    
    /**
     * Create default pages
     */
    private function create_default_pages() {
        // Check if inventory page exists
        $inventory_page = get_page_by_path('inventory');
        if (!$inventory_page) {
            $page_id = wp_insert_post(array(
                'post_title' => __('Inventory', 'inventory-enhanced'),
                'post_content' => '[inventory_filters]',
                'post_status' => 'draft', // Draft until setup is complete
                'post_type' => 'page',
                'post_slug' => 'inventory'
            ));
            
            if ($page_id) {
                update_option('inventory_enhanced_main_page_id', $page_id);
            }
        }
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Update setting value
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        update_option('inventory_enhanced_settings', $this->settings);
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        include INVENTORY_ENHANCED_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        include INVENTORY_ENHANCED_PLUGIN_DIR . 'includes/settings-page.php';
    }
    
    /**
     * Templates page callback
     */
    public function templates_page() {
        include INVENTORY_ENHANCED_PLUGIN_DIR . 'includes/templates-page.php';
    }
    
    /**
     * Setup wizard page callback
     */
    public function setup_wizard_page() {
        include INVENTORY_ENHANCED_PLUGIN_DIR . 'includes/setup-wizard-page.php';
    }
}

/**
 * Initialize the plugin
 */
function inventory_enhanced() {
    return InventoryEnhanced::get_instance();
}

// Start the plugin
inventory_enhanced();

/**
 * Helper functions
 */

/**
 * Get plugin instance
 */
function inventory_enhanced_get_instance() {
    return InventoryEnhanced::get_instance();
}

/**
 * Get plugin setting
 */
function inventory_enhanced_get_setting($key, $default = null) {
    return inventory_enhanced()->get_setting($key, $default);
}

/**
 * Log debug information
 */
function inventory_enhanced_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Inventory Enhanced: ' . $message);
    }
}

?>