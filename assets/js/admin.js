/**
 * Inventory Enhanced - Admin JavaScript
 * 
 * Handles admin interface functionality including settings, template management,
 * and wizard interactions
 */

jQuery(document).ready(function($) {
    
    // Initialize admin functionality
    init();
    
    function init() {
        // Color picker initialization
        if (typeof $.fn.wpColorPicker !== 'undefined') {
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Auto-save color changes after short delay
                    clearTimeout(window.colorChangeTimeout);
                    window.colorChangeTimeout = setTimeout(() => {
                        showNotice('Color updated', 'success', 2000);
                    }, 1000);
                }
            });
        }
        
        // Settings form handling
        setupSettingsForm();
        
        // Template management
        setupTemplateManagement();
        
        // Modal functionality
        setupModals();
        
        // AJAX form handling
        setupAjaxForms();
        
        // Dashboard widgets
        setupDashboardWidgets();
        
        // Tooltips
        setupTooltips();
    }
    
    function setupSettingsForm() {
        const $form = $('form[action=""]').has('[name="inventory_enhanced_settings_nonce"]');
        
        if ($form.length) {
            // Auto-save functionality for certain fields
            $form.find('input[type="checkbox"], select').on('change', debounce(() => {
                if (window.inventoryEnhanced && window.inventoryEnhanced.autoSave) {
                    saveSettings(true);
                }
            }, 1000));
            
            // Validate form before submit
            $form.on('submit', function(e) {
                if (!validateSettingsForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                $(this).find('[type="submit"]').prop('disabled', true).text('Saving...');
            });
        }
        
        // Settings page specific functionality
        $('#reset-settings').on('click', function() {
            if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                resetSettings();
            }
        });
        
        $('#clear-cache').on('click', function() {
            clearCache();
        });
        
        // Export/Import settings
        $('#export-settings').on('click', exportSettings);
        $('#import-settings-file').on('change', handleSettingsImport);
    }
    
    function setupTemplateManagement() {
        // Template category filtering
        $('.category-filter').on('click', function(e) {
            e.preventDefault();
            const category = $(this).data('category');
            
            $('.category-filter').removeClass('active');
            $(this).addClass('active');
            
            filterTemplates(category);
        });
        
        // Template preview
        $('.preview-template').on('click', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            showTemplatePreview(templateId);
        });
        
        // Template import
        $('.import-template').on('click', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            const templateName = $(this).closest('.template-card').find('.template-name').text();
            showImportModal(templateId, templateName);
        });
        
        // Template export
        $('#export-template').on('click', function() {
            const pageId = $('#page-select').val();
            const templateName = $('#template-name').val();
            const templateDescription = $('#template-description').val();
            
            if (!pageId || !templateName) {
                showNotice('Please select a page and enter a template name', 'error');
                return;
            }
            
            exportTemplate(pageId, templateName, templateDescription);
        });
        
        // Auto-generate slug from title
        $('#page-title').on('input', function() {
            const title = $(this).val();
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s]/g, '')
                .replace(/\s+/g, '-')
                .replace(/^-+|-+$/g, '');
            $('#page-slug').val(slug);
        });
    }
    
    function setupModals() {
        // Modal open/close handlers
        $(document).on('click', '[data-modal]', function(e) {
            e.preventDefault();
            const modalId = $(this).data('modal');
            openModal(modalId);
        });
        
        $('.modal-close, .close-modal').on('click', function() {
            closeModal($(this).closest('.modal'));
        });
        
        // Close modal on backdrop click
        $('.modal').on('click', function(e) {
            if (e.target === this) {
                closeModal($(this));
            }
        });
        
        // Close modal on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal($('.modal:visible'));
            }
        });
        
        // Handle modal form submissions
        $('.modal form').on('submit', function(e) {
            e.preventDefault();
            handleModalForm($(this));
        });
    }
    
    function setupAjaxForms() {
        // Generic AJAX form handler
        $('[data-ajax-form]').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const action = $form.data('ajax-form');
            const data = $form.serialize();
            
            submitAjaxForm(action, data, $form);
        });
    }
    
    function setupDashboardWidgets() {
        // Refresh dashboard stats
        $('.refresh-stats').on('click', function() {
            refreshDashboardStats();
        });
        
        // Quick actions
        $('.quick-action').on('click', function() {
            const action = $(this).data('action');
            executeQuickAction(action);
        });
    }
    
    function setupTooltips() {
        // Simple tooltip functionality
        $('[data-tooltip]').on('mouseenter', function() {
            const tooltip = $(this).data('tooltip');
            showTooltip($(this), tooltip);
        }).on('mouseleave', function() {
            hideTooltip();
        });
    }
    
    // Settings Functions
    function validateSettingsForm() {
        let isValid = true;
        const errors = [];
        
        // Validate main category slug
        const categorySlug = $('[name="inventory_enhanced_settings[main_category_slug]"]').val();
        if (!categorySlug || !/^[a-z0-9-]+$/.test(categorySlug)) {
            errors.push('Main category slug must contain only lowercase letters, numbers, and hyphens');
            isValid = false;
        }
        
        // Validate posts per page
        const postsPerPage = parseInt($('[name="inventory_enhanced_settings[posts_per_page]"]').val());
        if (!postsPerPage || postsPerPage < 1 || postsPerPage > 50) {
            errors.push('Posts per page must be between 1 and 50');
            isValid = false;
        }
        
        // Validate colors
        const primaryColor = $('[name="inventory_enhanced_settings[primary_color]"]').val();
        const secondaryColor = $('[name="inventory_enhanced_settings[secondary_color]"]').val();
        
        if (primaryColor && !/^#[0-9A-F]{6}$/i.test(primaryColor)) {
            errors.push('Primary color must be a valid hex color');
            isValid = false;
        }
        
        if (secondaryColor && !/^#[0-9A-F]{6}$/i.test(secondaryColor)) {
            errors.push('Secondary color must be a valid hex color');
            isValid = false;
        }
        
        if (errors.length > 0) {
            showNotice(errors.join('<br>'), 'error');
        }
        
        return isValid;
    }
    
    function saveSettings(silent = false) {
        const formData = $('form[action=""]').has('[name="inventory_enhanced_settings_nonce"]').serialize();
        
        if (!silent) {
            showLoading('Saving settings...');
        }
        
        $.ajax({
            url: window.inventoryEnhanced ? window.inventoryEnhanced.ajaxUrl : ajaxurl,
            type: 'POST',
            data: formData + '&action=inventory_save_settings',
            success: function(response) {
                if (response.success) {
                    if (!silent) {
                        showNotice('Settings saved successfully!', 'success');
                    }
                } else {
                    showNotice('Failed to save settings: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Error saving settings. Please try again.', 'error');
            },
            complete: function() {
                hideLoading();
            }
        });
    }
    
    function resetSettings() {
        showLoading('Resetting settings...');
        
        $.post(ajaxurl, {
            action: 'inventory_reset_settings',
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                showNotice('Settings reset successfully! Reloading page...', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotice('Failed to reset settings', 'error');
            }
        }).always(() => hideLoading());
    }
    
    function clearCache() {
        const $button = $('#clear-cache');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Clearing...');
        
        $.post(ajaxurl, {
            action: 'inventory_clear_cache',
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                showNotice('Cache cleared successfully!', 'success');
            } else {
                showNotice('Failed to clear cache', 'error');
            }
        }).always(() => {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    // Template Functions
    function filterTemplates(category) {
        const $cards = $('.template-card');
        
        if (category === 'all') {
            $cards.show();
        } else {
            $cards.hide();
            $cards.filter('[data-category="' + category + '"]').show();
        }
        
        // Animate the filtering
        $cards.each(function(index) {
            $(this).delay(index * 50).fadeIn(200);
        });
    }
    
    function showTemplatePreview(templateId) {
        const $modal = $('#template-preview-modal');
        const $content = $('#template-preview-content');
        
        $content.html('<div class="loading-spinner"></div><p>Loading preview...</p>');
        openModal($modal);
        
        $.post(ajaxurl, {
            action: 'get_template_preview',
            template_id: templateId,
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                $content.html(response.data.preview_html);
                $('.import-from-preview').data('template-id', templateId);
            } else {
                $content.html('<div class="error">Failed to load preview: ' + response.data + '</div>');
            }
        });
    }
    
    function showImportModal(templateId, templateName) {
        $('#page-title').val(templateName);
        $('#page-slug').val(templateName.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
        $('#template-import-modal').show().addClass('show');
        $('.confirm-import').data('template-id', templateId);
    }
    
    function importTemplate(templateId, options) {
        showLoading('Importing template...');
        
        $.post(ajaxurl, {
            action: 'import_inventory_template',
            template_id: templateId,
            create_page: options.createPage,
            page_title: options.pageTitle,
            page_slug: options.pageSlug,
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                showNotice('Template imported successfully!', 'success');
                
                if (response.data.edit_url) {
                    const openEditor = confirm('Template imported! Would you like to edit the new page?');
                    if (openEditor) {
                        window.open(response.data.edit_url, '_blank');
                    }
                }
                
                closeModal($('#template-import-modal'));
            } else {
                showNotice('Import failed: ' + response.data.message, 'error');
            }
        }).always(() => hideLoading());
    }
    
    function exportTemplate(pageId, templateName, templateDescription) {
        showLoading('Exporting template...');
        
        $.post(ajaxurl, {
            action: 'export_inventory_template',
            page_id: pageId,
            template_name: templateName,
            template_description: templateDescription,
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                showNotice('Template exported successfully!', 'success');
                // Refresh the page to show the new template
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotice('Export failed: ' + response.data, 'error');
            }
        }).always(() => hideLoading());
    }
    
    // Modal Functions
    function openModal(modal) {
        const $modal = typeof modal === 'string' ? $('#' + modal) : $(modal);
        $modal.show().addClass('show');
        
        // Focus trap
        const focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusableElements.length) {
            focusableElements.first().focus();
        }
        
        // Prevent body scrolling
        $('body').addClass('modal-open');
    }
    
    function closeModal(modal) {
        const $modal = $(modal);
        $modal.removeClass('show');
        
        setTimeout(() => {
            $modal.hide();
        }, 300);
        
        // Re-enable body scrolling
        $('body').removeClass('modal-open');
    }
    
    function handleModalForm($form) {
        const modalId = $form.closest('.modal').attr('id');
        
        switch (modalId) {
            case 'template-import-modal':
                const templateId = $('.confirm-import').data('template-id');
                const options = {
                    createPage: $('#create-page').is(':checked'),
                    pageTitle: $('#page-title').val(),
                    pageSlug: $('#page-slug').val()
                };
                importTemplate(templateId, options);
                break;
                
            default:
                // Generic form submission
                submitAjaxForm($form.attr('action'), $form.serialize(), $form);
        }
    }
    
    // AJAX Functions
    function submitAjaxForm(action, data, $form) {
        const $submitButton = $form.find('[type="submit"]');
        const originalText = $submitButton.text();
        
        $submitButton.prop('disabled', true).text('Processing...');
        
        $.post(ajaxurl, data + '&action=' + action, function(response) {
            if (response.success) {
                showNotice(response.data.message || 'Action completed successfully', 'success');
                
                // Handle any additional response data
                if (response.data.redirect) {
                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1500);
                }
            } else {
                showNotice(response.data || 'Action failed', 'error');
            }
        }).fail(function() {
            showNotice('Request failed. Please try again.', 'error');
        }).always(function() {
            $submitButton.prop('disabled', false).text(originalText);
        });
    }
    
    // Dashboard Functions
    function refreshDashboardStats() {
        showLoading('Refreshing stats...');
        
        $.post(ajaxurl, {
            action: 'inventory_refresh_stats',
            nonce: window.inventoryEnhanced ? window.inventoryEnhanced.nonce : ''
        }, function(response) {
            if (response.success) {
                // Update stats display
                $('.stat-number').each(function() {
                    const stat = $(this).data('stat');
                    if (response.data[stat]) {
                        animateNumber($(this), response.data[stat]);
                    }
                });
            }
        }).always(() => hideLoading());
    }
    
    function executeQuickAction(action) {
        switch (action) {
            case 'clear_cache':
                clearCache();
                break;
            case 'refresh_stats':
                refreshDashboardStats();
                break;
            default:
                console.warn('Unknown quick action:', action);
        }
    }
    
    // UI Helper Functions
    function showNotice(message, type = 'info', duration = 5000) {
        const noticeClass = `inventory-notice ${type}`;
        const $notice = $(`<div class="${noticeClass}">${message}</div>`);
        
        // Remove existing notices of the same type
        $(`.inventory-notice.${type}`).remove();
        
        // Add new notice
        if ($('.wrap h1').length) {
            $('.wrap h1').after($notice);
        } else {
            $('.wrap').prepend($notice);
        }
        
        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, duration);
        }
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 500);
    }
    
    function showLoading(message = 'Loading...') {
        if (!$('.loading-overlay').length) {
            const $overlay = $(`
                <div class="loading-overlay">
                    <div class="loading-spinner"></div>
                    <p class="loading-message">${message}</p>
                </div>
            `);
            $('body').append($overlay);
        }
    }
    
    function hideLoading() {
        $('.loading-overlay').remove();
    }
    
    function showTooltip($element, text) {
        const $tooltip = $(`<div class="inventory-tooltip">${text}</div>`);
        $('body').append($tooltip);
        
        const rect = $element[0].getBoundingClientRect();
        $tooltip.css({
            top: rect.top - $tooltip.outerHeight() - 5,
            left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2)
        });
    }
    
    function hideTooltip() {
        $('.inventory-tooltip').remove();
    }
    
    function animateNumber($element, targetNumber) {
        const startNumber = parseInt($element.text().replace(/,/g, '')) || 0;
        const duration = 1000;
        const steps = 60;
        const stepValue = (targetNumber - startNumber) / steps;
        let currentStep = 0;
        
        const timer = setInterval(() => {
            currentStep++;
            const currentNumber = Math.floor(startNumber + (stepValue * currentStep));
            $element.text(currentNumber.toLocaleString());
            
            if (currentStep >= steps) {
                clearInterval(timer);
                $element.text(targetNumber.toLocaleString());
            }
        }, duration / steps);
    }
    
    // Event Handlers
    $('.confirm-import').on('click', function() {
        const templateId = $(this).data('template-id');
        const options = {
            createPage: $('#create-page').is(':checked'),
            pageTitle: $('#page-title').val(),
            pageSlug: $('#page-slug').val()
        };
        
        if (options.createPage && !options.pageTitle) {
            showNotice('Please enter a page title', 'error');
            return;
        }
        
        importTemplate(templateId, options);
    });
    
    $('#create-page').on('change', function() {
        $('.page-details').toggleClass('disabled', !$(this).is(':checked'));
    });
    
    // Utility Functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // Global API
    window.InventoryAdmin = {
        showNotice,
        showLoading,
        hideLoading,
        openModal,
        closeModal,
        importTemplate,
        exportTemplate,
        clearCache,
        refreshStats: refreshDashboardStats
    };
});