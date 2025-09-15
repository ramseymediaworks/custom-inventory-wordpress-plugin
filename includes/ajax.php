<?php
/**
 * Inventory Enhanced - AJAX Handlers
 * 
 * Handles all AJAX requests for the inventory system including:
 * - Filter processing with progressive availability
 * - Inventory loading and pagination
 * - Smart category analysis
 * - Performance optimization with caching
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced AJAX Class
 */
class InventoryEnhanced_Ajax {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Cache group for inventory data
     */
    private $cache_group = 'inventory-enhanced';
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    /**
     * Initialize AJAX handlers
     */
    private function init() {
        // Main filter handler
        add_action('wp_ajax_filter_inventory', array($this, 'handle_filter_inventory'));
        add_action('wp_ajax_nopriv_filter_inventory', array($this, 'handle_filter_inventory'));
        
        // Get available filters (for progressive filtering)
        add_action('wp_ajax_get_available_filters', array($this, 'handle_get_available_filters'));
        add_action('wp_ajax_nopriv_get_available_filters', array($this, 'handle_get_available_filters'));
        
        // Clear filter cache (admin only)
        add_action('wp_ajax_clear_filter_cache', array($this, 'handle_clear_filter_cache'));
        
        // Auto-clear cache when posts are updated
        add_action('save_post', array($this, 'clear_cache_on_post_update'));
        add_action('delete_post', array($this, 'clear_cache_on_post_update'));
        
        // Clear cache when categories are updated
        add_action('created_term', array($this, 'clear_cache_on_term_update'));
        add_action('edited_term', array($this, 'clear_cache_on_term_update'));
        add_action('deleted_term', array($this, 'clear_cache_on_term_update'));
    }
    
    /**
     * Handle inventory filtering AJAX request
     */
    public function handle_filter_inventory() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'inventory_filter_nonce')) {
                wp_die(__('Security check failed', 'inventory-enhanced'));
            }
            
            // Sanitize input parameters
            $selected_categories = isset($_POST['categories']) ? 
                array_map('intval', (array) $_POST['categories']) : array();
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $posts_per_page = isset($_POST['per_page']) ? 
                max(1, min(50, intval($_POST['per_page']))) : 
                ($this->settings['posts_per_page'] ?? 8);
            
            // Get main category
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            $main_category = get_term_by('slug', $main_category_slug, 'category');
            
            if (!$main_category) {
                wp_send_json_error(__('Main inventory category not found', 'inventory-enhanced'));
                return;
            }
            
            // Build tax query for posts
            $tax_query = array('relation' => 'AND');
            
            // Always include main inventory category
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $main_category->term_id,
            );
            
            // Add selected category filters
            if (!empty($selected_categories)) {
                foreach ($selected_categories as $category_id) {
                    if ($category_id > 0) {
                        $tax_query[] = array(
                            'taxonomy' => 'category',
                            'field'    => 'term_id',
                            'terms'    => $category_id,
                        );
                    }
                }
            }
            
            // Build cache key for this query
            $cache_key = $this->build_cache_key('inventory_query', array(
                'categories' => $selected_categories,
                'page' => $page,
                'per_page' => $posts_per_page,
                'main_category' => $main_category->term_id
            ));
            
            // Try to get cached results
            $cached_result = wp_cache_get($cache_key, $this->cache_group);
            if (false !== $cached_result && !$this->is_debug_mode()) {
                wp_send_json_success($cached_result);
                return;
            }
            
            // Query posts
            $query_args = array(
                'post_type' => 'post',
                'posts_per_page' => $posts_per_page,
                'paged' => $page,
                'post_status' => 'publish',
                'tax_query' => $tax_query,
                'meta_query' => array(
                    // Ensure posts have some content
                    array(
                        'key' => '_thumbnail_id',
                        'compare' => 'EXISTS'
                    )
                ),
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            $inventory_query = new WP_Query($query_args);
            
            // Get available filters for progressive filtering
            $available_filters = array();
            if ($this->settings['enable_progressive_filtering'] ?? true) {
                $available_filters = $this->get_available_filter_options($selected_categories, $main_category->term_id);
            }
            
            // Build response
            $response = array(
                'posts' => array(),
                'found_posts' => $inventory_query->found_posts,
                'max_pages' => $inventory_query->max_num_pages,
                'current_page' => $page,
                'available_filters' => $available_filters,
                'query_time' => 0,
                'cache_used' => false
            );
            
            // Process posts
            if ($inventory_query->have_posts()) {
                while ($inventory_query->have_posts()) {
                    $inventory_query->the_post();
                    
                    // Get post categories for display (excluding main inventory category)
                    $post_categories = get_the_category();
                    $category_names = array();
                    
                    foreach ($post_categories as $cat) {
                        if ($cat->slug !== $main_category_slug && $cat->term_id !== $main_category->term_id) {
                            $category_names[] = $cat->name;
                        }
                    }
                    
                    // Get featured image with fallback sizes
                    $featured_image = $this->get_responsive_featured_image(get_the_ID());
                    
                    $response['posts'][] = array(
                        'ID' => get_the_ID(),
                        'title' => get_the_title(),
                        'permalink' => get_permalink(),
                        'featured_image' => $featured_image['url'],
                        'featured_image_srcset' => $featured_image['srcset'],
                        'categories' => $category_names,
                        'excerpt' => $this->get_smart_excerpt(get_the_ID()),
                        'date' => get_the_date('c'),
                        'post_class' => implode(' ', get_post_class('inventory-item'))
                    );
                }
            }
            
            wp_reset_postdata();
            
            // Cache the results for 15 minutes
            wp_cache_set($cache_key, $response, $this->cache_group, 15 * MINUTE_IN_SECONDS);
            
            // Log performance data in debug mode
            if ($this->is_debug_mode()) {
                $response['debug'] = array(
                    'query_args' => $query_args,
                    'selected_categories' => $selected_categories,
                    'cache_key' => $cache_key,
                    'sql_query' => $inventory_query->request
                );
                
                inventory_enhanced_log('Filter query executed: ' . print_r($query_args, true));
            }
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            inventory_enhanced_log('AJAX Error in handle_filter_inventory: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while filtering inventory', 'inventory-enhanced'));
        }
    }
    
    /**
     * Get available filter options based on current selection
     */
    private function get_available_filter_options($selected_categories = array(), $main_category_id = null) {
        // Build cache key
        $cache_key = $this->build_cache_key('available_filters', array(
            'selected' => $selected_categories,
            'main_category' => $main_category_id
        ));
        
        // Try cached version first
        $cached_filters = wp_cache_get($cache_key, $this->cache_group);
        if (false !== $cached_filters && !$this->is_debug_mode()) {
            return $cached_filters;
        }
        
        if (!$main_category_id) {
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            $main_category = get_term_by('slug', $main_category_slug, 'category');
            $main_category_id = $main_category ? $main_category->term_id : 0;
        }
        
        if (!$main_category_id) {
            return array();
        }
        
        // Build query for posts that match current filters
        $tax_query = array('relation' => 'AND');
        $tax_query[] = array(
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => $main_category_id,
        );
        
        if (!empty($selected_categories)) {
            foreach ($selected_categories as $category_id) {
                if ($category_id > 0) {
                    $tax_query[] = array(
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => intval($category_id),
                    );
                }
            }
        }
        
        // Get posts that match current filters (IDs only for performance)
        $matching_posts = new WP_Query(array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => $tax_query,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ));
        
        $available_options = array();
        
        if (!empty($matching_posts->posts)) {
            // Get all categories used by the matching posts
            $all_categories = array();
            foreach ($matching_posts->posts as $post_id) {
                $post_categories = wp_get_post_categories($post_id, array('fields' => 'ids'));
                $all_categories = array_merge($all_categories, $post_categories);
            }
            $all_categories = array_unique($all_categories);
            
            // Get direct children of inventory category (filter sections)
            $filter_sections = get_terms(array(
                'taxonomy' => 'category',
                'parent' => $main_category_id,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
            
            foreach ($filter_sections as $section) {
                $section_options = array();
                
                // Get children of this section (the actual filter options)
                $filter_options = get_terms(array(
                    'taxonomy' => 'category',
                    'parent' => $section->term_id,
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC'
                ));
                
                foreach ($filter_options as $option) {
                    $is_available = in_array($option->term_id, $all_categories);
                    $is_selected = in_array($option->term_id, $selected_categories);
                    
                    // Get accurate post count for this option
                    $post_count = $this->get_category_post_count_in_context(
                        $option->term_id, 
                        $main_category_id, 
                        $selected_categories
                    );
                    
                    $section_options[] = array(
                        'term_id' => $option->term_id,
                        'slug' => $option->slug,
                        'name' => $option->name,
                        'count' => $post_count,
                        'available' => $is_available,
                        'selected' => $is_selected
                    );
                }
                
                if (!empty($section_options)) {
                    $available_options[$section->slug] = array(
                        'name' => $section->name,
                        'term_id' => $section->term_id,
                        'options' => $section_options
                    );
                }
            }
        }
        
        wp_reset_postdata();
        
        // Cache for 30 minutes
        wp_cache_set($cache_key, $available_options, $this->cache_group, 30 * MINUTE_IN_SECONDS);
        
        return $available_options;
    }
    
    /**
     * Get category post count within specific context
     */
    private function get_category_post_count_in_context($category_id, $main_category_id, $exclude_categories = array()) {
        $cache_key = $this->build_cache_key('category_count', array(
            'category' => $category_id,
            'main' => $main_category_id,
            'exclude' => $exclude_categories
        ));
        
        $count = wp_cache_get($cache_key, $this->cache_group);
        if (false !== $count && !$this->is_debug_mode()) {
            return intval($count);
        }
        
        // Build tax query excluding the current category from selected filters
        $tax_query = array('relation' => 'AND');
        $tax_query[] = array(
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $main_category_id
        );
        
        $tax_query[] = array(
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $category_id
        );
        
        // Add other selected categories (but not the one we're counting)
        foreach ($exclude_categories as $exclude_id) {
            if ($exclude_id != $category_id && $exclude_id > 0) {
                $tax_query[] = array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => intval($exclude_id)
                );
            }
        }
        
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => $tax_query,
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ));
        
        $count = $query->found_posts;
        
        // Cache for 1 hour
        wp_cache_set($cache_key, $count, $this->cache_group, HOUR_IN_SECONDS);
        
        return $count;
    }
    
    /**
     * Handle get available filters AJAX request
     */
    public function handle_get_available_filters() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'inventory_filter_nonce')) {
                wp_die(__('Security check failed', 'inventory-enhanced'));
            }
            
            $selected_categories = isset($_POST['categories']) ? 
                array_map('intval', (array) $_POST['categories']) : array();
            
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            $main_category = get_term_by('slug', $main_category_slug, 'category');
            
            if (!$main_category) {
                wp_send_json_error(__('Main inventory category not found', 'inventory-enhanced'));
                return;
            }
            
            $available_filters = $this->get_available_filter_options($selected_categories, $main_category->term_id);
            
            wp_send_json_success(array(
                'available_filters' => $available_filters,
                'cache_used' => false
            ));
            
        } catch (Exception $e) {
            inventory_enhanced_log('AJAX Error in handle_get_available_filters: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while getting available filters', 'inventory-enhanced'));
        }
    }
    
    /**
     * Handle clear filter cache AJAX request (admin only)
     */
    public function handle_clear_filter_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'inventory-enhanced'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        $this->clear_all_cache();
        
        wp_send_json_success(array(
            'message' => __('Filter cache cleared successfully', 'inventory-enhanced')
        ));
    }
    
    /**
     * Get responsive featured image
     */
    private function get_responsive_featured_image($post_id) {
        $image_id = get_post_thumbnail_id($post_id);
        
        if (!$image_id) {
            return array(
                'url' => '',
                'srcset' => ''
            );
        }
        
        $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
        $image_srcset = wp_get_attachment_image_srcset($image_id, 'medium_large');
        
        return array(
            'url' => $image_url ?: '',
            'srcset' => $image_srcset ?: ''
        );
    }
    
    /**
     * Get smart excerpt for inventory posts
     */
    private function get_smart_excerpt($post_id, $length = 150) {
        $post = get_post($post_id);
        
        if (!$post) {
            return '';
        }
        
        // Use manual excerpt if available
        if (!empty($post->post_excerpt)) {
            return wp_trim_words($post->post_excerpt, $length / 8, '...');
        }
        
        // Generate from content
        $content = strip_shortcodes($post->post_content);
        $content = wp_strip_all_tags($content);
        
        return wp_trim_words($content, $length / 8, '...');
    }
    
    /**
     * Build cache key
     */
    private function build_cache_key($prefix, $data) {
        $key_data = array_merge(array('v' => INVENTORY_ENHANCED_VERSION), $data);
        return $prefix . '_' . md5(serialize($key_data));
    }
    
    /**
     * Clear cache when posts are updated
     */
    public function clear_cache_on_post_update($post_id) {
        // Only clear cache for published posts in inventory category
        if (get_post_status($post_id) === 'publish') {
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            if (has_category($main_category_slug, $post_id)) {
                $this->clear_all_cache();
                inventory_enhanced_log("Cache cleared due to post update: {$post_id}");
            }
        }
    }
    
    /**
     * Clear cache when terms are updated
     */
    public function clear_cache_on_term_update($term_id) {
        $term = get_term($term_id, 'category');
        if ($term && !is_wp_error($term)) {
            // Check if this term is related to inventory
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            $main_category = get_term_by('slug', $main_category_slug, 'category');
            
            if ($main_category && ($term_id == $main_category->term_id || term_is_ancestor_of($main_category->term_id, $term_id, 'category'))) {
                $this->clear_all_cache();
                inventory_enhanced_log("Cache cleared due to term update: {$term_id}");
            }
        }
    }
    
    /**
     * Clear all plugin cache
     */
    private function clear_all_cache() {
        wp_cache_flush_group($this->cache_group);
        
        // Also clear any WordPress transients we might have set
        delete_transient('inventory_enhanced_categories');
        delete_transient('inventory_enhanced_filter_counts');
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG && defined('INVENTORY_ENHANCED_DEBUG') && INVENTORY_ENHANCED_DEBUG;
    }
    
    /**
     * Get cache statistics (for admin use)
     */
    public function get_cache_stats() {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        // This is a placeholder - in a real implementation you'd track cache hits/misses
        return array(
            'cache_group' => $this->cache_group,
            'cache_enabled' => wp_using_ext_object_cache(),
            'debug_mode' => $this->is_debug_mode()
        );
    }
    
    /**
     * Preload common filter combinations (run via cron)
     */
    public function preload_filter_cache() {
        if (!wp_doing_cron()) {
            return;
        }
        
        $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
        $main_category = get_term_by('slug', $main_category_slug, 'category');
        
        if (!$main_category) {
            return;
        }
        
        // Preload empty filter state
        $this->get_available_filter_options(array(), $main_category->term_id);
        
        // Preload common single-category filters
        $common_categories = get_terms(array(
            'taxonomy' => 'category',
            'parent' => $main_category->term_id,
            'hide_empty' => true,
            'number' => 5,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        foreach ($common_categories as $category) {
            $child_terms = get_terms(array(
                'taxonomy' => 'category',
                'parent' => $category->term_id,
                'hide_empty' => true,
                'number' => 3
            ));
            
            foreach ($child_terms as $child) {
                $this->get_available_filter_options(array($child->term_id), $main_category->term_id);
            }
        }
        
        inventory_enhanced_log('Filter cache preloaded');
    }
}

// Schedule cache preloading (if not already scheduled)
if (!wp_next_scheduled('inventory_enhanced_preload_cache')) {
    wp_schedule_event(time(), 'hourly', 'inventory_enhanced_preload_cache');
}

// Hook the preload function
add_action('inventory_enhanced_preload_cache', function() {
    if (class_exists('InventoryEnhanced_Ajax')) {
        $settings = get_option('inventory_enhanced_settings', array());
        $ajax_handler = new InventoryEnhanced_Ajax($settings);
        $ajax_handler->preload_filter_cache();
    }
});

?>