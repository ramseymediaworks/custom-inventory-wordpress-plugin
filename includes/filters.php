<?php
/**
 * Inventory Enhanced - Smart Filtering System
 * 
 * Handles the inventory filtering functionality including:
 * - Dynamic filter generation from categories
 * - Progressive filtering with smart availability
 * - AJAX-powered filtering with no page reloads
 * - Mobile-responsive sidebar layout
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced Filters Class
 */
class InventoryEnhanced_Filters {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Main inventory category term
     */
    private $main_category;
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    /**
     * Initialize filters
     */
    private function init() {
        // Register shortcode
        add_shortcode('inventory_filters', array($this, 'render_filters_shortcode'));
        
        // Get main category
        $this->main_category = $this->get_main_category();
        
        // Enqueue assets when shortcode is used
        add_action('wp_enqueue_scripts', array($this, 'conditional_enqueue_assets'));
        
        // Add inline styles if no external CSS
        add_action('wp_head', array($this, 'inline_styles'));
        
        // Add inline scripts if no external JS
        add_action('wp_footer', array($this, 'inline_scripts'));
    }
    
    /**
     * Get main inventory category
     */
    private function get_main_category() {
        $category_slug = isset($this->settings['main_category_slug']) ? 
                        $this->settings['main_category_slug'] : 'inventory';
                        
        return get_term_by('slug', $category_slug, 'category');
    }
    
    /**
     * Render filters shortcode
     */
    public function render_filters_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => $this->settings['main_category_slug'] ?? 'inventory',
            'layout' => $this->settings['filter_layout'] ?? 'sidebar',
            'per_page' => $this->settings['posts_per_page'] ?? 8
        ), $atts);
        
        // Get main category
        $main_category = get_term_by('slug', $atts['category'], 'category');
        
        if (!$main_category) {
            return '<p class="inventory-error">' . 
                   __('Inventory category not found. Please check your settings.', 'inventory-enhanced') . 
                   '</p>';
        }
        
        // Get filter sections (direct children of main category)
        $filter_sections = get_terms(array(
            'taxonomy' => 'category',
            'parent' => $main_category->term_id,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (empty($filter_sections)) {
            return '<p class="inventory-error">' . 
                   __('No filter categories found. Please set up your category structure.', 'inventory-enhanced') . 
                   '</p>';
        }
        
        ob_start();
        ?>
        
        <div class="inventory-layout" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            <!-- Filter Sidebar -->
            <div class="inventory-filter-sidebar">
                <h3><?php _e('Filter Options', 'inventory-enhanced'); ?></h3>
                
                <?php foreach ($filter_sections as $section): ?>
                    <?php
                    // Get children of this section (the actual filter options)
                    $filter_options = get_terms(array(
                        'taxonomy' => 'category',
                        'parent' => $section->term_id,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC'
                    ));
                    
                    if (empty($filter_options)) continue;
                    ?>
                    
                    <div class="filter-section" data-section="<?php echo esc_attr($section->slug); ?>">
                        <h4><?php echo esc_html($section->name); ?></h4>
                        <div class="filter-options">
                            <?php foreach ($filter_options as $option): ?>
                                <?php
                                // Get post count for this category
                                $post_count = $this->get_category_post_count($option->term_id, $main_category->term_id);
                                ?>
                                <div class="filter-option">
                                    <input 
                                        type="checkbox" 
                                        id="filter-<?php echo esc_attr($option->slug); ?>" 
                                        value="<?php echo esc_attr($option->term_id); ?>"
                                        data-slug="<?php echo esc_attr($option->slug); ?>"
                                        data-section="<?php echo esc_attr($section->slug); ?>"
                                        data-name="<?php echo esc_attr($option->name); ?>"
                                    >
                                    <label for="filter-<?php echo esc_attr($option->slug); ?>">
                                        <?php echo esc_html($option->name); ?> 
                                        <span class="filter-count">(<?php echo $post_count; ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button class="clear-filters" type="button">
                    <?php _e('Clear All Filters', 'inventory-enhanced'); ?>
                </button>
                
                <div class="filter-loading" style="display: none;">
                    <p><?php _e('Loading inventory...', 'inventory-enhanced'); ?></p>
                </div>
            </div>
            
            <!-- Results container -->
            <div class="inventory-results">
                <div class="results-header" style="display: none;">
                    <div class="results-count"></div>
                    <div class="active-filters"></div>
                </div>
                
                <div class="inventory-grid-container">
                    <!-- Initial content or AJAX results will be loaded here -->
                </div>
                
                <div class="load-more-container" style="display: none;">
                    <button class="load-more-inventory" type="button">
                        <?php _e('Load More Inventory', 'inventory-enhanced'); ?>
                    </button>
                </div>
                
                <div class="no-results" style="display: none;">
                    <h3><?php _e('No inventory found', 'inventory-enhanced'); ?></h3>
                    <p><?php _e('Try adjusting your filters to see more results.', 'inventory-enhanced'); ?></p>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get category post count within inventory
     */
    private function get_category_post_count($category_id, $main_category_id) {
        $count = wp_cache_get("inventory_count_{$category_id}_{$main_category_id}", 'inventory-enhanced');
        
        if (false === $count) {
            $query = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'category',
                        'field' => 'term_id',
                        'terms' => $main_category_id
                    ),
                    array(
                        'taxonomy' => 'category',
                        'field' => 'term_id',
                        'terms' => $category_id
                    )
                )
            ));
            
            $count = $query->found_posts;
            wp_cache_set("inventory_count_{$category_id}_{$main_category_id}", $count, 'inventory-enhanced', HOUR_IN_SECONDS);
        }
        
        return $count;
    }
    
    /**
     * Conditionally enqueue assets
     */
    public function conditional_enqueue_assets() {
        global $post;
        
        // Check if shortcode is used on current page
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'inventory_filters')) {
            wp_enqueue_style(
                'inventory-enhanced-filters',
                INVENTORY_ENHANCED_PLUGIN_URL . 'assets/css/inventory-filters.css',
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
            
            // Localize script for AJAX
            wp_localize_script('inventory-enhanced-filters', 'inventoryFilterAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('inventory_filter_nonce'),
                'settings' => array(
                    'postsPerPage' => $this->settings['posts_per_page'] ?? 8,
                    'mainCategory' => $this->settings['main_category_slug'] ?? 'inventory',
                    'enableProgressiveFiltering' => $this->settings['enable_progressive_filtering'] ?? true
                ),
                'strings' => array(
                    'loading' => __('Loading...', 'inventory-enhanced'),
                    'loadMore' => __('Load More Inventory', 'inventory-enhanced'),
                    'noResults' => __('No inventory found matching your filters.', 'inventory-enhanced'),
                    'showingResults' => __('Showing %s results', 'inventory-enhanced'),
                    'clearFilters' => __('Clear All Filters', 'inventory-enhanced')
                )
            ));
        }
    }
    
    /**
     * Add inline styles if external CSS doesn't exist
     */
    public function inline_styles() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'inventory_filters')) {
            return;
        }
        
        if (!file_exists(INVENTORY_ENHANCED_PLUGIN_DIR . 'assets/css/inventory-filters.css')) {
            ?>
            <style id="inventory-enhanced-filters-inline">
            /* Inventory Enhanced - Filter Styles */
            
            /* Layout */
            .inventory-layout {
                display: grid;
                grid-template-columns: 300px 1fr;
                gap: 30px;
                max-width: 1200px;
                margin: 0 auto;
                font-size: 18px;
            }
            
            .inventory-layout[data-layout="top"] {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            /* Filter Sidebar */
            .inventory-filter-sidebar {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                padding: 25px;
                height: fit-content;
                position: sticky;
                top: 20px;
            }
            
            .inventory-layout[data-layout="top"] .inventory-filter-sidebar {
                position: static;
                max-width: none;
            }
            
            .inventory-filter-sidebar h3 {
                color: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                font-size: 28px;
                margin-bottom: 20px;
                font-weight: 600;
                margin-top: 0;
            }
            
            .filter-section {
                margin-bottom: 25px;
                border-bottom: 1px solid #eee;
                padding-bottom: 20px;
            }
            
            .filter-section:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .filter-section h4 {
                color: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                font-size: 22px;
                margin-bottom: 12px;
                font-weight: 600;
                margin-top: 0;
            }
            
            .filter-options {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .inventory-layout[data-layout="top"] .filter-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }
            
            .filter-option {
                display: flex;
                align-items: center;
                cursor: pointer;
                padding: 5px 0;
                transition: all 0.2s ease;
            }
            
            .filter-option input[type="checkbox"] {
                margin-right: 10px;
                accent-color: <?php echo esc_attr($this->settings['secondary_color'] ?? '#ff6900'); ?>;
                width: 18px;
                height: 18px;
                cursor: pointer;
            }
            
            .filter-option label {
                cursor: pointer;
                font-size: 18px;
                color: #666;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .filter-option:hover label {
                color: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
            }
            
            .filter-count {
                font-weight: 600;
                color: #999;
            }
            
            /* Unavailable filter state */
            .filter-option.unavailable {
                opacity: 0.4;
                pointer-events: none;
            }
            
            .filter-option.unavailable input[type="checkbox"] {
                opacity: 0.3;
            }
            
            .filter-option.unavailable label {
                color: #999;
                text-decoration: line-through;
            }
            
            .clear-filters {
                background: <?php echo esc_attr($this->settings['secondary_color'] ?? '#ff6900'); ?>;
                color: white;
                border: none;
                padding: 12px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 18px;
                font-weight: 600;
                margin-top: 15px;
                width: 100%;
                transition: background-color 0.3s ease;
            }
            
            .clear-filters:hover {
                background: #e55a00;
                transform: translateY(-1px);
            }
            
            .filter-loading {
                text-align: center;
                padding: 20px;
                color: #666;
                font-size: 18px;
            }
            
            .filter-loading::after {
                content: "";
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid #ddd;
                border-top: 2px solid <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                border-radius: 50%;
                animation: inventory-spin 1s linear infinite;
                margin-left: 10px;
                vertical-align: middle;
            }
            
            @keyframes inventory-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Results Area */
            .inventory-results {
                background: white;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                min-height: 400px;
            }
            
            .results-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #eee;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .results-count {
                font-size: 20px;
                color: #666;
                font-weight: 600;
            }
            
            .active-filters {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .filter-tag {
                background: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                color: white;
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 16px;
                display: flex;
                align-items: center;
                gap: 5px;
                font-weight: 500;
            }
            
            .filter-tag .remove {
                cursor: pointer;
                font-weight: bold;
                margin-left: 5px;
                padding: 0 5px;
                border-radius: 50%;
                transition: background-color 0.2s ease;
            }
            
            .filter-tag .remove:hover {
                background: rgba(255,255,255,0.2);
            }
            
            /* Inventory Grid */
            .inventory-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 25px;
                margin-bottom: 30px;
            }
            
            .inventory-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                border: 1px solid #eee;
            }
            
            .inventory-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            
            .card-image {
                width: 100%;
                height: 200px;
                background: #f0f0f0;
                position: relative;
                overflow: hidden;
            }
            
            .card-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .card-content {
                padding: 20px;
            }
            
            .card-title {
                font-size: 24px;
                font-weight: 600;
                color: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                margin-bottom: 10px;
                margin-top: 0;
            }
            
            .card-details {
                font-size: 18px;
                color: #666;
                line-height: 1.4;
                margin-bottom: 15px;
            }
            
            .card-details span {
                margin-right: 15px;
                font-weight: 500;
            }
            
            .read-more {
                color: <?php echo esc_attr($this->settings['secondary_color'] ?? '#ff6900'); ?>;
                text-decoration: none;
                font-weight: 600;
                font-size: 18px;
                transition: color 0.3s ease;
            }
            
            .read-more:hover {
                color: #e55a00;
                text-decoration: underline;
            }
            
            /* Load More Button */
            .load-more-container {
                text-align: center;
                margin-top: 30px;
            }
            
            .load-more-inventory {
                background: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                color: white;
                border: none;
                padding: 15px 40px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 20px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .load-more-inventory:hover {
                background: #1e3f73;
                transform: translateY(-2px);
            }
            
            .load-more-inventory:disabled {
                background: #999;
                cursor: not-allowed;
                transform: none;
            }
            
            /* No Results */
            .no-results {
                text-align: center;
                padding: 60px 20px;
                color: #666;
            }
            
            .no-results h3 {
                color: <?php echo esc_attr($this->settings['primary_color'] ?? '#2c5aa0'); ?>;
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .no-results p {
                font-size: 18px;
                margin: 0;
            }
            
            /* Error State */
            .inventory-error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px 20px;
                border-radius: 6px;
                border: 1px solid #f5c6cb;
                font-size: 16px;
                margin: 20px 0;
            }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                .inventory-layout {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }
                
                .inventory-filter-sidebar {
                    position: static;
                    margin-bottom: 20px;
                }
                
                .results-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 10px;
                }
                
                .inventory-grid {
                    grid-template-columns: 1fr;
                }
                
                .filter-section h4 {
                    font-size: 20px;
                }
                
                .inventory-filter-sidebar h3 {
                    font-size: 24px;
                }
                
                .filter-options {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
            }
            
            @media (max-width: 480px) {
                .inventory-layout {
                    margin: 0 10px;
                }
                
                .inventory-filter-sidebar,
                .inventory-results {
                    padding: 20px;
                }
                
                .card-title {
                    font-size: 20px;
                }
                
                .card-details {
                    font-size: 16px;
                }
            }
            </style>
            <?php
        }
    }
    
    /**
     * Add inline scripts if external JS doesn't exist
     */
    public function inline_scripts() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'inventory_filters')) {
            return;
        }
        
        if (!file_exists(INVENTORY_ENHANCED_PLUGIN_DIR . 'assets/js/inventory-filters.js')) {
            ?>
            <script id="inventory-enhanced-filters-inline">
            jQuery(document).ready(function($) {
                // Initialize inventory filters
                const InventoryFilters = {
                    currentPage: 1,
                    isLoading: false,
                    maxPages: 1,
                    settings: window.inventoryFilterAjax ? window.inventoryFilterAjax.settings : {},
                    strings: window.inventoryFilterAjax ? window.inventoryFilterAjax.strings : {},
                    
                    init: function() {
                        this.bindEvents();
                        this.loadAllInventory();
                    },
                    
                    bindEvents: function() {
                        // Filter change event
                        $('.filter-option input[type="checkbox"]').on('change', () => {
                            this.currentPage = 1;
                            this.updateFilters();
                        });
                        
                        // Clear filters
                        $('.clear-filters').on('click', () => {
                            $('.filter-option input[type="checkbox"]').prop('checked', false);
                            this.currentPage = 1;
                            this.loadAllInventory();
                        });
                        
                        // Remove filter tags
                        $(document).on('click', '.filter-tag .remove', (e) => {
                            const filterTag = $(e.target).closest('.filter-tag');
                            const filterText = filterTag.text().replace('×', '').trim();
                            
                            // Find and uncheck corresponding checkbox
                            $('.filter-option input[type="checkbox"]').each(function() {
                                const label = $(this).next('label').text();
                                if (label.toLowerCase().includes(filterText.toLowerCase())) {
                                    $(this).prop('checked', false);
                                }
                            });
                            
                            this.currentPage = 1;
                            
                            // Check if any filters are still active
                            const hasActiveFilters = $('.filter-option input[type="checkbox"]:checked').length > 0;
                            if (hasActiveFilters) {
                                this.updateFilters();
                            } else {
                                this.loadAllInventory();
                            }
                        });
                        
                        // Load more button
                        $(document).on('click', '.load-more-inventory', () => {
                            if (!this.isLoading && this.currentPage < this.maxPages) {
                                this.currentPage++;
                                
                                const hasActiveFilters = $('.filter-option input[type="checkbox"]:checked').length > 0;
                                if (hasActiveFilters) {
                                    this.updateFilters(true);
                                } else {
                                    this.loadAllInventory(true);
                                }
                            }
                        });
                    },
                    
                    loadAllInventory: function(append = false) {
                        if (this.isLoading) return;
                        
                        this.showLoading();
                        this.isLoading = true;
                        
                        $.ajax({
                            url: window.inventoryFilterAjax ? window.inventoryFilterAjax.ajaxUrl : '/wp-admin/admin-ajax.php',
                            type: 'POST',
                            data: {
                                action: 'filter_inventory',
                                nonce: window.inventoryFilterAjax ? window.inventoryFilterAjax.nonce : '',
                                categories: [],
                                page: this.currentPage,
                                per_page: this.settings.postsPerPage || 8
                            },
                            success: (response) => {
                                if (response.success) {
                                    this.displayResults(response.data, append);
                                    if (!append) {
                                        this.hideResultsHeader();
                                        this.resetFilterAvailability();
                                    }
                                    $('body').addClass('filters-active');
                                } else {
                                    console.error('Load inventory request failed');
                                }
                            },
                            error: () => {
                                console.error('AJAX request failed');
                            },
                            complete: () => {
                                this.hideLoading();
                                this.isLoading = false;
                            }
                        });
                    },
                    
                    updateFilters: function(append = false) {
                        if (this.isLoading) return;
                        
                        const selectedCategories = [];
                        $('.filter-option input[type="checkbox"]:checked').each(function() {
                            selectedCategories.push($(this).val());
                        });
                        
                        if (selectedCategories.length === 0) {
                            this.loadAllInventory();
                            return;
                        }
                        
                        this.showLoading();
                        this.isLoading = true;
                        
                        $.ajax({
                            url: window.inventoryFilterAjax ? window.inventoryFilterAjax.ajaxUrl : '/wp-admin/admin-ajax.php',
                            type: 'POST',
                            data: {
                                action: 'filter_inventory',
                                nonce: window.inventoryFilterAjax ? window.inventoryFilterAjax.nonce : '',
                                categories: selectedCategories,
                                page: this.currentPage,
                                per_page: this.settings.postsPerPage || 8
                            },
                            success: (response) => {
                                if (response.success) {
                                    this.displayResults(response.data, append);
                                    this.updateActiveFilters();
                                    if (this.settings.enableProgressiveFiltering && response.data.available_filters) {
                                        this.updateFilterAvailability(response.data.available_filters);
                                    }
                                    this.showResultsHeader();
                                    $('.results-count').text(this.strings.showingResults ? 
                                        this.strings.showingResults.replace('%s', response.data.found_posts) :
                                        `Showing ${response.data.found_posts} results`);
                                    $('body').addClass('filters-active');
                                } else {
                                    console.error('Filter request failed');
                                }
                            },
                            error: () => {
                                console.error('AJAX request failed');
                            },
                            complete: () => {
                                this.hideLoading();
                                this.isLoading = false;
                            }
                        });
                    },
                    
                    displayResults: function(data, append = false) {
                        const resultsContainer = $('.inventory-grid-container');
                        const loadMoreContainer = $('.load-more-container');
                        const noResults = $('.no-results');
                        
                        this.maxPages = data.max_pages;
                        
                        let html = '';
                        
                        if (data.posts.length > 0) {
                            noResults.hide();
                            
                            if (!append) {
                                html = '<div class="inventory-grid">';
                            }
                            
                            data.posts.forEach((post) => {
                                const imageHtml = post.featured_image ? 
                                    `<img src="${post.featured_image}" alt="${post.title}" loading="lazy">` : 
                                    '<div style="background: #f0f0f0; height: 100%; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>';
                                    
                                const categoryHtml = post.categories.map(cat => `<span><strong>${cat}</strong></span>`).join('');
                                
                                html += `
                                    <div class="inventory-card">
                                        <div class="card-image">
                                            ${imageHtml}
                                        </div>
                                        <div class="card-content">
                                            <h3 class="card-title">${post.title}</h3>
                                            <div class="card-details">
                                                ${categoryHtml}
                                            </div>
                                            <a href="${post.permalink}" class="read-more">${this.strings.readMore || 'read more'}</a>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            if (!append) {
                                html += '</div>';
                                resultsContainer.html(html);
                            } else {
                                $('.inventory-grid').append(html);
                            }
                            
                            // Show/hide load more button
                            if (this.currentPage < this.maxPages) {
                                loadMoreContainer.show();
                                $('.load-more-inventory').text(this.strings.loadMore || 'Load More Inventory').prop('disabled', false);
                            } else {
                                loadMoreContainer.hide();
                            }
                            
                        } else {
                            resultsContainer.empty();
                            loadMoreContainer.hide();
                            noResults.show();
                        }
                    },
                    
                    updateActiveFilters: function() {
                        const activeFiltersContainer = $('.active-filters');
                        activeFiltersContainer.empty();
                        
                        $('.filter-option input[type="checkbox"]:checked').each(function() {
                            const label = $(this).next('label').text().split('(')[0].trim();
                            const filterTag = $(`
                                <div class="filter-tag">
                                    ${label} <span class="remove">×</span>
                                </div>
                            `);
                            activeFiltersContainer.append(filterTag);
                        });
                    },
                    
                    updateFilterAvailability: function(availableFilters) {
                        // Reset all filters to available state
                        $('.filter-option').removeClass('unavailable');
                        
                        if (!availableFilters || Object.keys(availableFilters).length === 0) {
                            return;
                        }
                        
                        // Update each filter section
                        $('.filter-section').each(function() {
                            const sectionSlug = $(this).data('section');
                            const sectionData = availableFilters[sectionSlug];
                            
                            if (sectionData && sectionData.options) {
                                $(this).find('.filter-option').each(function() {
                                    const checkbox = $(this).find('input[type="checkbox"]');
                                    const termId = parseInt(checkbox.val());
                                    
                                    // Find this option in the available data
                                    const optionData = sectionData.options.find(opt => opt.term_id === termId);
                                    
                                    if (optionData) {
                                        if (!optionData.available && !optionData.selected) {
                                            // Mark as unavailable if not available and not selected
                                            $(this).addClass('unavailable');
                                        } else {
                                            $(this).removeClass('unavailable');
                                        }
                                        
                                        // Update the count in the label
                                        const countSpan = $(this).find('.filter-count');
                                        countSpan.text(`(${optionData.count})`);
                                    }
                                });
                            }
                        });
                    },
                    
                    resetFilterAvailability: function() {
                        $('.filter-option').removeClass('unavailable');
                    },
                    
                    showResultsHeader: function() {
                        $('.results-header').show();
                    },
                    
                    hideResultsHeader: function() {
                        $('.results-header').hide();
                        $('.active-filters').empty();
                    },
                    
                    showLoading: function() {
                        $('.filter-loading').show();
                        $('.load-more-inventory').text(this.strings.loading || 'Loading...').prop('disabled', true);
                    },
                    
                    hideLoading: function() {
                        $('.filter-loading').hide();
                    }
                };
                
                // Initialize the filters
                InventoryFilters.init();
            });
            </script>
            <?php
        }
    }
}

?>