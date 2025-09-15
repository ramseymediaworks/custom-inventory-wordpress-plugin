/**
 * Inventory Enhanced - Filter JavaScript
 * 
 * Handles all filter interactions, AJAX requests, and progressive filtering
 */

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
                    this.showError('Failed to load inventory. Please try again.');
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
                        this.showError('Failed to filter inventory. Please try again.');
                    }
                },
                error: () => {
                    console.error('AJAX request failed');
                    this.showError('Failed to filter inventory. Please try again.');
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
                        `<img src="${post.featured_image}" alt="${post.title}" loading="lazy" ${post.featured_image_srcset ? `srcset="${post.featured_image_srcset}"` : ''}>` : 
                        '<div style="background: #f0f0f0; height: 100%; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>';
                        
                    const categoryHtml = post.categories.map(cat => `<span><strong>${cat}</strong></span>`).join('');
                    
                    const excerptHtml = post.excerpt ? `<div class="card-excerpt">${post.excerpt}</div>` : '';
                    
                    html += `
                        <div class="inventory-card ${post.post_class || ''}">
                            <div class="card-image">
                                ${imageHtml}
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">${post.title}</h3>
                                <div class="card-details">
                                    ${categoryHtml}
                                </div>
                                ${excerptHtml}
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
            
            // Trigger custom event for other scripts
            $(document).trigger('inventoryResultsUpdated', [data, append]);
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
                            if (countSpan.length) {
                                countSpan.text(`(${optionData.count})`);
                            } else {
                                // Add count if it doesn't exist
                                const label = $(this).find('label');
                                const labelText = label.text().replace(/\(\d+\)/, '');
                                label.html(`${labelText} <span class="filter-count">(${optionData.count})</span>`);
                            }
                        }
                    });
                }
            });
            
            // Trigger custom event
            $(document).trigger('filterAvailabilityUpdated', [availableFilters]);
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
        },
        
        showError: function(message) {
            const errorHtml = `<div class="inventory-error">${message}</div>`;
            $('.inventory-grid-container').html(errorHtml);
            $('.load-more-container').hide();
        },
        
        // Public methods for external use
        refreshFilters: function() {
            this.currentPage = 1;
            this.updateFilters();
        },
        
        clearAllFilters: function() {
            $('.clear-filters').trigger('click');
        },
        
        getSelectedFilters: function() {
            const selected = [];
            $('.filter-option input[type="checkbox"]:checked').each(function() {
                selected.push({
                    id: $(this).val(),
                    slug: $(this).data('slug'),
                    name: $(this).data('name'),
                    section: $(this).data('section')
                });
            });
            return selected;
        },
        
        setFilters: function(filterIds) {
            $('.filter-option input[type="checkbox"]').prop('checked', false);
            filterIds.forEach(id => {
                $(`.filter-option input[value="${id}"]`).prop('checked', true);
            });
            this.updateFilters();
        }
    };
    
    // Initialize the filters
    InventoryFilters.init();
    
    // Make InventoryFilters globally accessible
    window.InventoryFilters = InventoryFilters;
    
    // Add smooth scrolling for filter interactions
    $('.filter-option').on('change', function() {
        // Small delay to allow filter processing
        setTimeout(() => {
            if ($('.inventory-results').length) {
                $('html, body').animate({
                    scrollTop: $('.inventory-results').offset().top - 100
                }, 500);
            }
        }, 300);
    });
    
    // Add keyboard navigation support
    $('.filter-option input[type="checkbox"]').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
    
    // Accessibility improvements
    $('.filter-option').each(function() {
        const checkbox = $(this).find('input[type="checkbox"]');
        const label = $(this).find('label');
        
        // Ensure proper labeling
        if (!checkbox.attr('id')) {
            const id = 'filter_' + Math.random().toString(36).substr(2, 9);
            checkbox.attr('id', id);
            label.attr('for', id);
        }
    });
});