<?php 

/**
 * Inventory Filter System for WordPress with Divi
 * Created by Ramsey MediaWorks Devs
 */

// Enqueue scripts and styles
function inventory_filter_enqueue_scripts() {
    wp_enqueue_script('jquery');
    
    // Enqueue our custom script
    wp_enqueue_script(
        'inventory-filter-js',
        get_template_directory_uri() . '/js/inventory-filter.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('inventory-filter-js', 'inventory_filter_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('inventory_filter_nonce'),
    ));
    
    // Enqueue our custom styles
    wp_enqueue_style(
        'inventory-filter-css',
        get_template_directory_uri() . '/css/inventory-filter.css',
        array(),
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'inventory_filter_enqueue_scripts');

// Create the shortcode for inventory filters
function inventory_filter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'inventory_category' => 'inventory', // Default main category slug
    ), $atts);
    
    // Get the main inventory category
    $main_category = get_term_by('slug', $atts['inventory_category'], 'category');
    
    if (!$main_category) {
        return '<p>Inventory category not found.</p>';
    }
    
    // Get direct children of inventory category
    $filter_sections = get_terms(array(
        'taxonomy' => 'category',
        'parent' => $main_category->term_id,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (empty($filter_sections)) {
        return '<p>No filter categories found.</p>';
    }
    
    ob_start();
    ?>
    
    <div class="inventory-layout">
        <!-- Filter Sidebar -->
        <div class="inventory-filter-sidebar">
            <h3>Filter Options</h3>
            
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
                            $post_count = $option->count;
                            ?>
                            <div class="filter-option">
                                <input 
                                    type="checkbox" 
                                    id="filter-<?php echo esc_attr($option->slug); ?>" 
                                    value="<?php echo esc_attr($option->term_id); ?>"
                                    data-slug="<?php echo esc_attr($option->slug); ?>"
                                    data-section="<?php echo esc_attr($section->slug); ?>"
                                >
                                <label for="filter-<?php echo esc_attr($option->slug); ?>">
                                    <?php echo esc_html($option->name); ?> (<?php echo $post_count; ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button class="clear-filters">Clear All Filters</button>
            
            <div class="filter-loading" style="display: none;">
                <p>Loading inventory...</p>
            </div>
        </div>
        
        <!-- Results container - will be populated by AJAX -->
        <div class="inventory-results">
            <div class="results-header" style="display: none;">
                <div class="results-count"></div>
                <div class="active-filters"></div>
            </div>
            
            <div class="inventory-grid-container">
                <!-- Original Divi blog module content will be here -->
                <!-- Default inventory posts will load here on page load -->
            </div>
            
            <div class="load-more-container" style="display: none;">
                <button class="load-more-inventory">Load More Inventory</button>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('inventory_filters', 'inventory_filter_shortcode');

// AJAX handler for filtering inventory
function handle_inventory_filter_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'inventory_filter_nonce')) {
        wp_die('Security check failed');
    }
    
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : array();
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $posts_per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 8;
    
    // Build tax query for selected categories
    $tax_query = array('relation' => 'AND');
    
    if (!empty($selected_categories)) {
        foreach ($selected_categories as $category_id) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => intval($category_id),
            );
        }
    } else {
        // If no filters selected, show all inventory posts
        $inventory_category = get_term_by('slug', 'inventory', 'category');
        if ($inventory_category) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $inventory_category->term_id,
            );
        }
    }
    
    // Query posts
    $query_args = array(
        'post_type' => 'post',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish',
        'tax_query' => $tax_query
    );
    
    $inventory_query = new WP_Query($query_args);
    
    // Get available filter options based on current selection
    $available_filters = get_available_filter_options($selected_categories);
    
    $response = array(
        'posts' => array(),
        'found_posts' => $inventory_query->found_posts,
        'max_pages' => $inventory_query->max_num_pages,
        'current_page' => $page,
        'available_filters' => $available_filters
    );
    
    if ($inventory_query->have_posts()) {
        while ($inventory_query->have_posts()) {
            $inventory_query->the_post();
            
            // Get post categories for display
            $post_categories = get_the_category();
            $category_names = array();
            
            foreach ($post_categories as $cat) {
                if ($cat->slug !== 'inventory') {
                    $category_names[] = $cat->name;
                }
            }
            
            $response['posts'][] = array(
                'ID' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'medium_large'),
                'categories' => $category_names,
                'excerpt' => get_the_excerpt()
            );
        }
    }
    
    wp_reset_postdata();
    
    wp_send_json_success($response);
}

// New function to get available filter options based on current selection
function get_available_filter_options($selected_categories = array()) {
    // Get the main inventory category
    $inventory_category = get_term_by('slug', 'inventory', 'category');
    if (!$inventory_category) {
        return array();
    }
    
    // Build query for posts that match current filters
    $tax_query = array('relation' => 'AND');
    $tax_query[] = array(
        'taxonomy' => 'category',
        'field'    => 'term_id',
        'terms'    => $inventory_category->term_id,
    );
    
    if (!empty($selected_categories)) {
        foreach ($selected_categories as $category_id) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => intval($category_id),
            );
        }
    }
    
    // Get posts that match current filters
    $matching_posts = new WP_Query(array(
        'post_type' => 'post',
        'posts_per_page' => -1, // Get all matching posts
        'post_status' => 'publish',
        'tax_query' => $tax_query,
        'fields' => 'ids' // Only get IDs for performance
    ));
    
    $available_options = array();
    
    if ($matching_posts->have_posts()) {
        // Get all categories used by the matching posts
        $all_categories = array();
        foreach ($matching_posts->posts as $post_id) {
            $post_categories = wp_get_post_categories($post_id);
            $all_categories = array_merge($all_categories, $post_categories);
        }
        $all_categories = array_unique($all_categories);
        
        // Get direct children of inventory category (filter sections)
        $filter_sections = get_terms(array(
            'taxonomy' => 'category',
            'parent' => $inventory_category->term_id,
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
                
                $section_options[] = array(
                    'term_id' => $option->term_id,
                    'slug' => $option->slug,
                    'name' => $option->name,
                    'count' => $option->count,
                    'available' => $is_available,
                    'selected' => $is_selected
                );
            }
            
            if (!empty($section_options)) {
                $available_options[$section->slug] = array(
                    'name' => $section->name,
                    'options' => $section_options
                );
            }
        }
    }
    
    wp_reset_postdata();
    return $available_options;
}
add_action('wp_ajax_filter_inventory', 'handle_inventory_filter_ajax');
add_action('wp_ajax_nopriv_filter_inventory', 'handle_inventory_filter_ajax');

// Add custom CSS inline if external file doesn't exist
function inventory_filter_inline_styles() {
    if (!file_exists(get_template_directory() . '/css/inventory-filter.css')) {
        ?>
        <style>
        /* Inventory Filter Layout */
        .inventory-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Inventory Filter Styles */
        .inventory-filter-sidebar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
            font-size: 18px;
        }

        .inventory-filter-sidebar h3 {
            color: #2c5aa0;
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .filter-section {
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .filter-section h4 {
            color: #2c5aa0;
            font-size: 22px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px 0;
        }

        .filter-option input[type="checkbox"] {
            margin-right: 10px;
            accent-color: #ff6900;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .filter-option label {
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }

        .filter-option:hover label {
            color: #2c5aa0;
        }

        .clear-filters {
            background: #ff6900;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            margin-top: 15px;
            width: 100%;
        }

        .clear-filters:hover {
            background: #e55a00;
        }

        .filter-loading {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 18px;
        }

        /* Results Area */
        .inventory-results {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        }

        .active-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-tag {
            background: #2c5aa0;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-tag .remove {
            cursor: pointer;
            font-weight: bold;
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
            border-radius: 8px;
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
            color: #2c5aa0;
            margin-bottom: 10px;
        }

        .card-details {
            font-size: 18px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .card-details span {
            margin-right: 15px;
        }

        .read-more {
            color: #ff6900;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
        }

        .read-more:hover {
            color: #e55a00;
        }

        /* Load More Button */
        .load-more-container {
            text-align: center;
            margin-top: 30px;
        }

        .load-more-inventory {
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 600;
        }

        .load-more-inventory:hover {
            background: #1e3f73;
        }

        .load-more-inventory:disabled {
            background: #999;
            cursor: not-allowed;
        }

        /* No results */
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .inventory-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .inventory-filter-sidebar {
                position: static;
            }
            
            .results-header {
                flex-direction: column;
                align-items: flex-start;
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
        }

        /* Hide original Divi blog when filters are active */
        .filters-active .et_pb_blog_grid {
            display: none;
        }
        </style>
        <?php
    }
}
add_action('wp_head', 'inventory_filter_inline_styles');

// Add JavaScript inline if external file doesn't exist
function inventory_filter_inline_scripts() {
    if (!file_exists(get_template_directory() . '/js/inventory-filter.js')) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let isLoading = false;
            let maxPages = 1;
            
            // Initialize - Load all inventory posts on page load
            initializeFilters();
            loadAllInventory();
            
            function initializeFilters() {
                // Filter change event
                $('.filter-option input[type="checkbox"]').on('change', function() {
                    currentPage = 1;
                    updateFilters();
                });
                
                // Clear filters
                $('.clear-filters').on('click', function() {
                    $('.filter-option input[type="checkbox"]').prop('checked', false);
                    currentPage = 1;
                    loadAllInventory(); // Load all inventory when clearing filters
                });
                
                // Remove filter tags
                $(document).on('click', '.filter-tag .remove', function() {
                    const filterTag = $(this).closest('.filter-tag');
                    const filterText = filterTag.text().replace('×', '').trim();
                    
                    // Find and uncheck corresponding checkbox
                    $('.filter-option input[type="checkbox"]').each(function() {
                        const label = $(this).next('label').text();
                        if (label.toLowerCase().includes(filterText.toLowerCase())) {
                            $(this).prop('checked', false);
                        }
                    });
                    
                    currentPage = 1;
                    
                    // Check if any filters are still active
                    const hasActiveFilters = $('.filter-option input[type="checkbox"]:checked').length > 0;
                    if (hasActiveFilters) {
                        updateFilters();
                    } else {
                        loadAllInventory();
                    }
                });
                
                // Load more button
                $(document).on('click', '.load-more-inventory', function() {
                    if (!isLoading && currentPage < maxPages) {
                        currentPage++;
                        
                        // Check if we have active filters
                        const hasActiveFilters = $('.filter-option input[type="checkbox"]:checked').length > 0;
                        if (hasActiveFilters) {
                            updateFilters(true);
                        } else {
                            loadAllInventory(true);
                        }
                    }
                });
            }
            
            function loadAllInventory(append = false) {
                if (isLoading) return;
                
                showLoading();
                isLoading = true;
                
                $.ajax({
                    url: inventory_filter_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'filter_inventory',
                        nonce: inventory_filter_ajax.nonce,
                        categories: [], // Empty array means show all inventory
                        page: currentPage,
                        per_page: 8
                    },
                    success: function(response) {
                        if (response.success) {
                            displayResults(response.data, append);
                            updateActiveFilters();
                            updateFilterAvailability(response.data.available_filters);
                            $('.results-header').hide(); // Hide header when showing all
                            $('.active-filters').empty();
                            $('body').addClass('filters-active');
                        } else {
                            console.error('Load inventory request failed');
                        }
                    },
                    error: function() {
                        console.error('AJAX request failed');
                    },
                    complete: function() {
                        hideLoading();
                        isLoading = false;
                    }
                });
            }
            
            function updateFilters(append = false) {
                if (isLoading) return;
                
                const selectedCategories = [];
                $('.filter-option input[type="checkbox"]:checked').each(function() {
                    selectedCategories.push($(this).val());
                });
                
                // Show loading state
                showLoading();
                
                // If no filters selected, show all inventory
                if (selectedCategories.length === 0) {
                    loadAllInventory();
                    return;
                }
                
                // Make AJAX request
                isLoading = true;
                
                $.ajax({
                    url: inventory_filter_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'filter_inventory',
                        nonce: inventory_filter_ajax.nonce,
                        categories: selectedCategories,
                        page: currentPage,
                        per_page: 8
                    },
                    success: function(response) {
                        if (response.success) {
                            displayResults(response.data, append);
                            updateActiveFilters();
                            updateFilterAvailability(response.data.available_filters);
                            $('.results-header').show(); // Show header for filtered results
                            $('.results-count').text(`Showing ${response.data.found_posts} results`);
                            $('body').addClass('filters-active');
                        } else {
                            console.error('Filter request failed');
                        }
                    },
                    error: function() {
                        console.error('AJAX request failed');
                    },
                    complete: function() {
                        hideLoading();
                        isLoading = false;
                    }
                });
            }
            
            function displayResults(data, append = false) {
                const resultsContainer = $('.inventory-grid-container');
                const loadMoreContainer = $('.load-more-container');
                
                // Update max pages
                maxPages = data.max_pages;
                
                let html = '';
                
                if (data.posts.length > 0) {
                    if (!append) {
                        html = '<div class="inventory-grid">';
                    }
                    
                    data.posts.forEach(function(post) {
                        const imageHtml = post.featured_image ? 
                            `<img src="${post.featured_image}" alt="${post.title}">` : 
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
                                    <a href="${post.permalink}" class="read-more">read more</a>
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
                    if (currentPage < maxPages) {
                        loadMoreContainer.show();
                        $('.load-more-inventory').text('Load More Inventory').prop('disabled', false);
                    } else {
                        loadMoreContainer.hide();
                    }
                    
                } else {
                    resultsContainer.html('<div class="no-results">No inventory found matching your filters.</div>');
                    loadMoreContainer.hide();
                }
            }
            
            function updateActiveFilters() {
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
            }
            
            function updateFilterAvailability(availableFilters) {
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
                                const label = $(this).find('label');
                                const labelText = label.text().replace(/\(\d+\)/, `(${optionData.count})`);
                                label.text(labelText);
                            }
                        });
                    }
                });
            }
            
            function resetFilterAvailability() {
                // Reset all filters to available state
                $('.filter-option').removeClass('unavailable');
            }
            
            function resetToOriginal() {
                $('.results-header').hide();
                $('.inventory-grid-container').empty();
                $('.load-more-container').hide();
                $('body').removeClass('filters-active');
                resetFilterAvailability();
                currentPage = 1;
                maxPages = 1;
            }
            
            function showLoading() {
                $('.filter-loading').show();
                $('.load-more-inventory').text('Loading...').prop('disabled', true);
            }
            
            function hideLoading() {
                $('.filter-loading').hide();
            }
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'inventory_filter_inline_scripts');


/**
 * Divi Gallery Module Override for Inventory Posts Only
 */

// Override Divi Gallery Module for inventory posts
function inventory_divi_gallery_override($output, $render_slug, $module) {
    // Only apply to gallery modules on inventory posts
    if ($render_slug !== 'et_pb_gallery' || !is_single()) {
        return $output;
    }
    
    // Don't run in Divi Builder or admin area
    if (is_admin() || 
        (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) ||
        (isset($_GET['et_fb']) && $_GET['et_fb'] === '1') ||
        (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'et_') === 0)) {
        return $output;
    }
    
    global $post;
    if (!$post || !has_category('inventory', $post)) {
        return $output; // Return default for non-inventory posts
    }
    
    // Get the module's attributes/settings
    $gallery_ids = isset($module->props['gallery_ids']) ? $module->props['gallery_ids'] : '';
    $gallery_orderby = isset($module->props['gallery_orderby']) ? $module->props['gallery_orderby'] : '';
    
    if (empty($gallery_ids)) {
        return $output; // Return default if no gallery IDs
    }
    
    // Parse gallery IDs and separate images from videos
    $image_ids = array_filter(explode(',', $gallery_ids));
    $images = array();
    $videos = array();
    
    foreach ($image_ids as $id) {
        $id = trim($id);
        if (is_numeric($id)) {
            $mime_type = get_post_mime_type($id);
            
            // Check if it's a video file
            if (strpos($mime_type, 'video/') === 0) {
                $video_url = wp_get_attachment_url($id);
                $video_title = get_the_title($id);
                
                if ($video_url) {
                    $videos[] = array(
                        'type' => 'wordpress',
                        'id' => $id,
                        'url' => $video_url,
                        'mime' => $mime_type,
                        'title' => $video_title
                    );
                }
            } else {
                // It's an image
                $image = wp_get_attachment_image_src($id, 'large');
                $thumb = wp_get_attachment_image_src($id, 'medium');
                $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
                $title = get_the_title($id);
                
                if ($image) {
                    $images[] = array(
                        'id' => $id,
                        'url' => $image[0],
                        'thumb' => $thumb[0],
                        'alt' => $alt ? $alt : $title,
                        'title' => $title
                    );
                }
            }
        }
    }
    
    if (empty($images) && empty($videos)) {
        return $output; // Return default if no valid images or videos
    }
    
    // Return our enhanced gallery HTML
    return render_inventory_divi_gallery($images, $videos, $module->props);
}

// Hook into Divi's module rendering
add_filter('et_module_shortcode_output', 'inventory_divi_gallery_override', 10, 3);

// Function to render the enhanced gallery
function render_inventory_divi_gallery($images, $videos = array(), $props = array()) {
    $total_items = count($images) + count($videos);
    $unique_id = 'gallery_' . wp_rand(1000, 9999);
    
    ob_start();
    ?>
    
    <div class="et_pb_module et_pb_gallery inventory-enhanced-gallery" id="<?php echo $unique_id; ?>">
        <div class="et_pb_gallery_items">
            <!-- Main Display -->
            <div class="inventory-gallery-main">
                <div class="inventory-slide-counter">
                    <span class="current-slide">1</span> / <span class="total-slides"><?php echo $total_items; ?></span>
                </div>
                
                <?php $slide_index = 0; ?>
                
                <!-- Image Slides -->
                <?php foreach ($images as $image): ?>
                    <div class="inventory-gallery-slide <?php echo $slide_index === 0 ? 'active' : ''; ?>" data-type="image">
                        <img src="<?php echo esc_url($image['url']); ?>" 
                             alt="<?php echo esc_attr($image['alt']); ?>" 
                             title="<?php echo esc_attr($image['title']); ?>">
                    </div>
                    <?php $slide_index++; ?>
                <?php endforeach; ?>
                
                <!-- Video Slides -->
                <?php foreach ($videos as $video): ?>
                    <?php 
                    $embed_code = '';
                    $video_title = 'Video';
                    
                    if ($video['type'] === 'wordpress') {
                        // WordPress uploaded video
                        $video_title = $video['title'] ?: 'Video';
                        $embed_code = '<video controls preload="metadata" style="width: 100%; height: 100%; object-fit: contain;">
                            <source src="' . esc_url($video['url']) . '" type="' . esc_attr($video['mime']) . '">
                            Your browser does not support the video tag.
                        </video>';
                    } else {
                        // External video (YouTube/Vimeo)
                        $video_url = $video['url'];
                        
                        // Handle YouTube URLs
                        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                            $video_id = '';
                            if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $video_url, $id)) {
                                $video_id = $id[1];
                            } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $video_url, $id)) {
                                $video_id = $id[1];
                            }
                            
                            if ($video_id) {
                                $embed_code = '<iframe width="100%" height="100%" src="https://www.youtube.com/embed/' . $video_id . '?rel=0" frameborder="0" allowfullscreen></iframe>';
                                $video_title = 'YouTube Video';
                            }
                        }
                        
                        // Handle Vimeo URLs  
                        else if (strpos($video_url, 'vimeo.com') !== false) {
                            if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $id)) {
                                $video_id = $id[1];
                                $embed_code = '<iframe width="100%" height="100%" src="https://player.vimeo.com/video/' . $video_id . '" frameborder="0" allowfullscreen></iframe>';
                                $video_title = 'Vimeo Video';
                            }
                        }
                    }
                    
                    if ($embed_code): ?>
                        <div class="inventory-gallery-slide <?php echo $slide_index === 0 ? 'active' : ''; ?>" data-type="video">
                            <div class="inventory-video-container">
                                <?php echo $embed_code; ?>
                            </div>
                        </div>
                        <?php $slide_index++; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if ($total_items > 1): ?>
                    <!-- Navigation arrows -->
                    <button class="inventory-gallery-nav inventory-gallery-prev" aria-label="Previous image">‹</button>
                    <button class="inventory-gallery-nav inventory-gallery-next" aria-label="Next image">›</button>
                <?php endif; ?>
            </div>
            
            <?php if ($total_items > 1): ?>
                <!-- Thumbnail strip -->
                <div class="inventory-gallery-thumbs">
                    <?php $thumb_index = 0; ?>
                    
                    <!-- Image Thumbnails -->
                    <?php foreach ($images as $image): ?>
                        <div class="inventory-thumb-item <?php echo $thumb_index === 0 ? 'active' : ''; ?>" 
                             data-slide="<?php echo $thumb_index; ?>" 
                             data-type="image">
                            <img src="<?php echo esc_url($image['thumb']); ?>" 
                                 alt="<?php echo esc_attr($image['alt']); ?>">
                        </div>
                        <?php $thumb_index++; ?>
                    <?php endforeach; ?>
                    
                    <!-- Video Thumbnails -->
                    <?php foreach ($videos as $video): ?>
                        <?php 
                        $thumb_url = '';
                        $video_title = 'Video';
                        
                        if ($video['type'] === 'wordpress') {
                            // WordPress uploaded video - try to get video thumbnail
                            $video_title = $video['title'] ?: 'Video';
                            
                            // Try to get video thumbnail from WordPress
                            $thumb_id = get_post_thumbnail_id($video['id']);
                            if ($thumb_id) {
                                $thumb_url = wp_get_attachment_image_src($thumb_id, 'thumbnail')[0];
                            } else {
                                // Generate a clean dark gray video thumbnail
                                $thumb_url = 'data:image/svg+xml;base64,' . base64_encode('
                                    <svg width="120" height="90" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="120" height="90" fill="#444"/>
                                        <circle cx="60" cy="45" r="20" fill="rgba(255,255,255,0.9)"/>
                                        <path d="M52 35l22 10-22 10v-20z" fill="#444"/>
                                    </svg>
                                ');
                            }
                        } else {
                            // External video thumbnails
                            $video_url = $video['url'];
                            
                            // Get YouTube thumbnail
                            if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $video_url, $id) || 
                                preg_match('/youtu\.be\/([^\&\?\/]+)/', $video_url, $id)) {
                                $video_id = $id[1];
                                $thumb_url = "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
                                $video_title = 'YouTube Video';
                            }
                            
                            // Get Vimeo thumbnail (basic implementation)
                            else if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $id)) {
                                $thumb_url = 'data:image/svg+xml;base64,' . base64_encode('
                                    <svg width="120" height="90" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="120" height="90" fill="#444"/>
                                        <circle cx="60" cy="45" r="20" fill="rgba(255,255,255,0.9)"/>
                                        <path d="M52 35l22 10-22 10v-20z" fill="#444"/>
                                    </svg>
                                ');
                                $video_title = 'Vimeo Video';
                            }
                        }
                        
                        if ($thumb_url): ?>
                            <div class="inventory-thumb-item video" 
                                 data-slide="<?php echo $thumb_index; ?>" 
                                 data-type="video"
                                 title="<?php echo esc_attr($video_title); ?>">
                                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($video_title); ?>">
                            </div>
                            <?php $thumb_index++; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

// Enqueue enhanced gallery styles and scripts for inventory posts
function inventory_divi_gallery_enqueue_assets() {
    if (is_single() && has_category('inventory')) {
        wp_enqueue_style(
            'inventory-divi-gallery-css',
            get_template_directory_uri() . '/css/inventory-divi-gallery.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'inventory-divi-gallery-js',
            get_template_directory_uri() . '/js/inventory-divi-gallery.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'inventory_divi_gallery_enqueue_assets');

// Add inline styles if external CSS file doesn't exist
function inventory_divi_gallery_inline_styles() {
    if (is_single() && has_category('inventory')) {
        if (!file_exists(get_template_directory() . '/css/inventory-divi-gallery.css')) {
            ?>
            <style>
            /* Inventory Enhanced Divi Gallery Styles */
            .inventory-enhanced-gallery {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                overflow: hidden;
                margin-bottom: 30px;
            }

            .inventory-enhanced-gallery .et_pb_gallery_items {
                padding: 0;
                margin: 0;
            }

            /* Hide default Divi gallery items when enhanced version is active */
            .inventory-enhanced-gallery .et_pb_gallery_item {
                display: none;
            }

            /* Main Display Area */
            .inventory-gallery-main {
                position: relative;
                width: 100%;
                height: 500px;
                background: #000;
                overflow: hidden;
            }

            .inventory-gallery-slide {
                display: none;
                width: 100%;
                height: 100%;
                position: relative;
            }

            .inventory-gallery-slide.active {
                display: block;
            }

            .inventory-gallery-slide img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                background: #000;
            }

            /* Video Container */
            .inventory-video-container {
                width: 100%;
                height: 100%;
                background: #000;
            }

            .inventory-video-container iframe {
                width: 100%;
                height: 100%;
                border: none;
            }

            /* Navigation Arrows */
            .inventory-gallery-nav {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(44, 90, 160, 0.8);
                color: white;
                border: none;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 24px;
                font-weight: bold;
                line-height: 1;
                transition: all 0.3s ease;
                z-index: 10;
            }

            .inventory-gallery-nav:hover {
                background: rgba(44, 90, 160, 1);
                transform: translateY(-50%) scale(1.1);
            }

            .inventory-gallery-nav:focus {
                outline: 2px solid #ff6900;
                outline-offset: 2px;
            }

            .inventory-gallery-prev {
                left: 15px;
            }

            .inventory-gallery-next {
                right: 15px;
            }

            /* Slide Counter */
            .inventory-slide-counter {
                position: absolute;
                top: 15px;
                right: 15px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 16px;
                font-weight: 600;
                z-index: 10;
            }

            /* Thumbnail Strip */
            .inventory-gallery-thumbs {
                display: flex;
                gap: 10px;
                padding: 20px;
                background: #f8f9fa;
                overflow-x: auto;
                scrollbar-width: thin;
                scrollbar-color: #2c5aa0 #f0f0f0;
            }

            .inventory-gallery-thumbs::-webkit-scrollbar {
                height: 8px;
            }

            .inventory-gallery-thumbs::-webkit-scrollbar-track {
                background: #f0f0f0;
                border-radius: 4px;
            }

            .inventory-gallery-thumbs::-webkit-scrollbar-thumb {
                background: #2c5aa0;
                border-radius: 4px;
            }

            .inventory-thumb-item {
                flex-shrink: 0;
                width: 90px;
                height: 70px;
                border-radius: 8px;
                overflow: hidden;
                cursor: pointer;
                border: 3px solid transparent;
                transition: all 0.3s ease;
                position: relative;
                background: #f0f0f0;
            }

            .inventory-thumb-item:hover {
                border-color: #ff6900;
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            }

            .inventory-thumb-item.active {
                border-color: #2c5aa0;
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(44, 90, 160, 0.3);
            }

            .inventory-thumb-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            /* Video thumbnail indicator */
            .inventory-thumb-item.video::after {
                content: '▶';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: white;
                background: rgba(0, 0, 0, 0.8);
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                padding-left: 2px;
                font-weight: bold;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .inventory-gallery-main {
                    height: 300px;
                }

                .inventory-gallery-nav {
                    width: 45px;
                    height: 45px;
                    font-size: 20px;
                }

                .inventory-gallery-prev {
                    left: 10px;
                }

                .inventory-gallery-next {
                    right: 10px;
                }

                .inventory-thumb-item {
                    width: 70px;
                    height: 55px;
                }

                .inventory-gallery-thumbs {
                    padding: 15px;
                    gap: 8px;
                }

                .inventory-slide-counter {
                    font-size: 14px;
                    padding: 6px 12px;
                }
            }

            @media (max-width: 480px) {
                .inventory-gallery-main {
                    height: 250px;
                }

                .inventory-thumb-item {
                    width: 60px;
                    height: 45px;
                }

                .inventory-gallery-thumbs {
                    padding: 12px;
                    gap: 6px;
                }
            }

            /* Focus styles for accessibility */
            .inventory-thumb-item:focus {
                outline: 2px solid #ff6900;
                outline-offset: 2px;
            }
            </style>
            <?php
        }
    }
}
add_action('wp_head', 'inventory_divi_gallery_inline_styles');

// Add inline JavaScript if external JS file doesn't exist
function inventory_divi_gallery_inline_scripts() {
    if (is_single() && has_category('inventory')) {
        if (!file_exists(get_template_directory() . '/js/inventory-divi-gallery.js')) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.inventory-enhanced-gallery').each(function() {
                    const gallery = $(this);
                    const slides = gallery.find('.inventory-gallery-slide');
                    const thumbs = gallery.find('.inventory-thumb-item');
                    const totalSlides = slides.length;
                    let currentSlide = 0;
                    
                    if (totalSlides <= 1) return; // Skip if only one slide
                    
                    // Update counter
                    gallery.find('.total-slides').text(totalSlides);
                    
                    // Navigation functions
                    function showSlide(index) {
                        if (index < 0 || index >= totalSlides) return;
                        
                        // Hide all slides and thumbs
                        slides.removeClass('active');
                        thumbs.removeClass('active');
                        
                        // Show selected slide and thumb
                        slides.eq(index).addClass('active');
                        thumbs.eq(index).addClass('active');
                        
                        // Update counter
                        gallery.find('.current-slide').text(index + 1);
                        currentSlide = index;
                        
                        // Scroll thumbnail into view
                        const activeThumb = thumbs.eq(index);
                        if (activeThumb.length) {
                            const thumbsContainer = gallery.find('.inventory-gallery-thumbs');
                            const scrollLeft = activeThumb.position().left + thumbsContainer.scrollLeft() - 
                                             (thumbsContainer.width() / 2) + (activeThumb.width() / 2);
                            thumbsContainer.animate({ scrollLeft: scrollLeft }, 300);
                        }
                    }
                    
                    function nextSlide() {
                        const next = (currentSlide + 1) % totalSlides;
                        showSlide(next);
                    }
                    
                    function prevSlide() {
                        const prev = (currentSlide - 1 + totalSlides) % totalSlides;
                        showSlide(prev);
                    }
                    
                    // Event listeners
                    gallery.find('.inventory-gallery-next').on('click', nextSlide);
                    gallery.find('.inventory-gallery-prev').on('click', prevSlide);
                    
                    // Thumbnail clicks
                    thumbs.on('click', function() {
                        const index = $(this).data('slide');
                        showSlide(index);
                    });
                    
                    // Keyboard navigation
                    gallery.on('keydown', function(e) {
                        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            nextSlide();
                        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            prevSlide();
                        }
                    });
                    
                    // Make gallery focusable for keyboard navigation
                    gallery.attr('tabindex', '0');
                    
                    // Touch/swipe support
                    let startX = 0;
                    let startY = 0;
                    const mainGallery = gallery.find('.inventory-gallery-main');
                    
                    mainGallery.on('touchstart', function(e) {
                        startX = e.originalEvent.touches[0].clientX;
                        startY = e.originalEvent.touches[0].clientY;
                    });
                    
                    mainGallery.on('touchend', function(e) {
                        if (!startX || !startY) return;
                        
                        const endX = e.originalEvent.changedTouches[0].clientX;
                        const endY = e.originalEvent.changedTouches[0].clientY;
                        const diffX = startX - endX;
                        const diffY = startY - endY;
                        
                        // Only process horizontal swipes
                        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                            if (diffX > 0) {
                                nextSlide(); // Swipe left - next slide
                            } else {
                                prevSlide(); // Swipe right - previous slide
                            }
                        }
                        
                        startX = 0;
                        startY = 0;
                    });
                    
                    // Initialize first slide
                    showSlide(0);
                });
            });
            </script>
            <?php
        }
    }
}
add_action('wp_footer', 'inventory_divi_gallery_inline_scripts');

// Add admin field for video URLs (optional)
function inventory_divi_gallery_meta_box() {
    add_meta_box(
        'inventory_gallery_videos',
        'Gallery Videos (Optional)',
        'inventory_divi_gallery_meta_box_callback',
        'post',
        'normal',
        'default'
    );
}

function inventory_divi_gallery_meta_box_callback($post) {
    // Only show for inventory posts
    if (!has_category('inventory', $post)) {
        return;
    }
    
    wp_nonce_field('inventory_gallery_meta_box', 'inventory_gallery_nonce');
    $videos = get_post_meta($post->ID, '_inventory_gallery_videos', true);
    ?>
    <p>
        <label for="inventory_gallery_videos">
            <strong>Video URLs (YouTube/Vimeo)</strong><br>
            <small>Enter one URL per line to add videos to your gallery</small>
        </label>
    </p>
    <p>
        <label for="inventory_gallery_videos">
            <strong>Gallery Videos (Optional)</strong><br>
            <small>Add videos to your gallery using any of these methods:</small>
        </label>
    </p>
    <textarea name="inventory_gallery_videos" id="inventory_gallery_videos" 
              rows="4" cols="50" style="width: 100%;"><?php echo esc_textarea($videos); ?></textarea>
    <p><em><strong>Option 1 - WordPress Media IDs:</strong><br>
    Upload MP4 files to Media Library, then enter their IDs: <code>123, 456, 789</code><br><br>
    <strong>Option 2 - External URLs:</strong><br>
    https://www.youtube.com/watch?v=ABC123<br>
    https://vimeo.com/123456789<br><br>
    <strong>Option 3 - Mix both:</strong><br>
    123, https://youtube.com/watch?v=ABC123, 456</em></p>
    
    <script>
    // Helper to get Media IDs easily
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('inventory_gallery_videos');
        if (textarea) {
            const helpText = document.createElement('p');
            helpText.innerHTML = '<strong>💡 Tip:</strong> To find Media IDs, go to <a href="' + 
                '<?php echo admin_url('upload.php'); ?>' + '" target="_blank">Media Library</a>, ' +
                'click on a video, and look for the ID in the URL: <code>post.php?post=<strong>123</strong></code>';
            helpText.style.background = '#e7f3ff';
            helpText.style.border = '1px solid #2c5aa0';
            helpText.style.padding = '10px';
            helpText.style.borderRadius = '4px';
            textarea.parentNode.insertBefore(helpText, textarea.nextSibling);
        }
    });
    </script>
    <?php
}

function save_inventory_divi_gallery_meta($post_id) {
    if (!isset($_POST['inventory_gallery_nonce']) || 
        !wp_verify_nonce($_POST['inventory_gallery_nonce'], 'inventory_gallery_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['inventory_gallery_videos'])) {
        $videos = sanitize_textarea_field($_POST['inventory_gallery_videos']);
        // Convert line breaks to commas
        $videos = str_replace(array("\r\n", "\n", "\r"), ',', $videos);
        update_post_meta($post_id, '_inventory_gallery_videos', $videos);
    }
}

// Only add meta box for inventory posts
add_action('add_meta_boxes', function() {
    global $post;
    if ($post && has_category('inventory', $post)) {
        inventory_divi_gallery_meta_box();
    }
});
add_action('save_post', 'save_inventory_divi_gallery_meta');
