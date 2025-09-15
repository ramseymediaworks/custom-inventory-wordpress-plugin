<?php
/**
 * Inventory Enhanced - Templates Page
 * 
 * Template library and import management page
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$plugin = inventory_enhanced_get_instance();
$template_importer = new InventoryEnhanced_TemplateImporter($plugin->settings);
$available_templates = $template_importer->get_available_templates();
?>

<div class="wrap inventory-templates-page">
    <h1>
        <span class="dashicons dashicons-layout"></span>
        <?php _e('Inventory Templates', 'inventory-enhanced'); ?>
    </h1>
    
    <p class="description">
        <?php _e('Professional page templates designed specifically for inventory websites. Import these layouts to get started quickly with a polished design.', 'inventory-enhanced'); ?>
    </p>
    
    <!-- Template Categories -->
    <div class="template-categories">
        <button class="category-filter active" data-category="all"><?php _e('All Templates', 'inventory-enhanced'); ?></button>
        <button class="category-filter" data-category="homepage"><?php _e('Homepage', 'inventory-enhanced'); ?></button>
        <button class="category-filter" data-category="single"><?php _e('Single Page', 'inventory-enhanced'); ?></button>
        <button class="category-filter" data-category="category"><?php _e('Category Pages', 'inventory-enhanced'); ?></button>
    </div>
    
    <!-- Templates Grid -->
    <div class="templates-grid">
        <?php if (empty($available_templates)): ?>
            <div class="no-templates">
                <div class="no-templates-icon">📦</div>
                <h3><?php _e('No Templates Available', 'inventory-enhanced'); ?></h3>
                <p><?php _e('Templates are not currently available. Check back later or contact support.', 'inventory-enhanced'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($available_templates as $template): ?>
                <div class="template-card" data-category="<?php echo esc_attr($template['type'] ?? 'general'); ?>" data-template-id="<?php echo esc_attr($template['id']); ?>">
                    <div class="template-preview">
                        <?php if ($template['preview_image']): ?>
                            <img src="<?php echo esc_url($template['preview_image']); ?>" 
                                 alt="<?php echo esc_attr($template['name']); ?>" 
                                 loading="lazy">
                        <?php else: ?>
                            <div class="no-preview">
                                <span class="dashicons dashicons-format-image"></span>
                                <p><?php _e('Preview not available', 'inventory-enhanced'); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="template-overlay">
                            <button class="preview-template" data-template-id="<?php echo esc_attr($template['id']); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Preview', 'inventory-enhanced'); ?>
                            </button>
                            
                            <button class="import-template" data-template-id="<?php echo esc_attr($template['id']); ?>">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Import', 'inventory-enhanced'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="template-info">
                        <h3 class="template-name"><?php echo esc_html($template['name']); ?></h3>
                        <p class="template-description"><?php echo esc_html($template['description']); ?></p>
                        
                        <div class="template-meta">
                            <span class="template-type"><?php echo esc_html(ucfirst($template['type'] ?? 'General')); ?></span>
                            <span class="template-version">v<?php echo esc_html($template['version'] ?? '1.0.0'); ?></span>
                            
                            <?php if ($template['source'] === 'builtin'): ?>
                                <span class="template-badge builtin"><?php _e('Built-in', 'inventory-enhanced'); ?></span>
                            <?php else: ?>
                                <span class="template-badge custom"><?php _e('Custom', 'inventory-enhanced'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($template['features'])): ?>
                            <div class="template-features">
                                <strong><?php _e('Features:', 'inventory-enhanced'); ?></strong>
                                <ul>
                                    <?php foreach (array_slice($template['features'], 0, 3) as $feature): ?>
                                        <li><?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($template['features']) > 3): ?>
                                        <li class="more-features">+ <?php printf(__('%d more', 'inventory-enhanced'), count($template['features']) - 3); ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Export Current Page -->
    <div class="export-section">
        <h2><?php _e('Export Current Page as Template', 'inventory-enhanced'); ?></h2>
        <p><?php _e('Save any page as a reusable template for future use.', 'inventory-enhanced'); ?></p>
        
        <div class="export-form">
            <label for="page-select"><?php _e('Select Page:', 'inventory-enhanced'); ?></label>
            <select id="page-select">
                <option value=""><?php _e('Choose a page...', 'inventory-enhanced'); ?></option>
                <?php
                $pages = get_pages(array('post_status' => 'publish,draft'));
                foreach ($pages as $page): ?>
                    <option value="<?php echo $page->ID; ?>"><?php echo esc_html($page->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" id="template-name" placeholder="<?php esc_attr_e('Template Name', 'inventory-enhanced'); ?>">
            <textarea id="template-description" placeholder="<?php esc_attr_e('Template Description (optional)', 'inventory-enhanced'); ?>"></textarea>
            
            <button type="button" id="export-template" class="button button-primary">
                <?php _e('Export as Template', 'inventory-enhanced'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Template Preview Modal -->
<div id="template-preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Template Preview', 'inventory-enhanced'); ?></h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="template-preview-content">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="button button-secondary close-modal"><?php _e('Close', 'inventory-enhanced'); ?></button>
            <button class="button button-primary import-from-preview" data-template-id="">
                <?php _e('Import This Template', 'inventory-enhanced'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Template Import Modal -->
<div id="template-import-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Import Template', 'inventory-enhanced'); ?></h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="import-options">
                <label>
                    <input type="checkbox" id="create-page" checked>
                    <?php _e('Create new page with this template', 'inventory-enhanced'); ?>
                </label>
                
                <div class="page-details">
                    <label for="page-title"><?php _e('Page Title:', 'inventory-enhanced'); ?></label>
                    <input type="text" id="page-title" placeholder="<?php esc_attr_e('Enter page title', 'inventory-enhanced'); ?>">
                    
                    <label for="page-slug"><?php _e('Page Slug:', 'inventory-enhanced'); ?></label>
                    <input type="text" id="page-slug" placeholder="<?php esc_attr_e('page-slug', 'inventory-enhanced'); ?>">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="button button-secondary close-modal"><?php _e('Cancel', 'inventory-enhanced'); ?></button>
            <button class="button button-primary confirm-import" data-template-id="">
                <?php _e('Import Template', 'inventory-enhanced'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.inventory-templates-page {
    max-width: 1200px;
}

.template-categories {
    margin: 30px 0;
}

.category-filter {
    background: white;
    border: 1px solid #ddd;
    padding: 10px 20px;
    margin-right: 10px;
    cursor: pointer;
    border-radius: 4px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.category-filter:hover,
.category-filter.active {
    background: #2c5aa0;
    color: white;
    border-color: #2c5aa0;
}

.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.template-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.template-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.template-preview {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: #f8f9fa;
}

.template-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
}

.no-preview .dashicons {
    font-size: 40px;
    margin-bottom: 10px;
}

.template-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(44, 90, 160, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.template-card:hover .template-overlay {
    opacity: 1;
}

.template-overlay button {
    background: white;
    color: #2c5aa0;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.template-overlay button:hover {
    background: #ff6900;
    color: white;
}

.template-info {
    padding: 25px;
}

.template-name {
    color: #2c5aa0;
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 10px 0;
}

.template-description {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.5;
}

.template-meta {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.template-type,
.template-version {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: #666;
}

.template-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.template-badge.builtin {
    background: #e8f5e8;
    color: #2e7d32;
}

.template-badge.custom {
    background: #fff3e0;
    color: #ef6c00;
}

.template-features ul {
    margin: 5px 0 0 0;
    padding-left: 15px;
    font-size: 14px;
    color: #666;
}

.template-features li {
    margin-bottom: 2px;
}

.more-features {
    font-style: italic;
    color: #999;
}

.no-templates {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 40px;
    color: #666;
}

.no-templates-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.export-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid #e0e0e0;
}

.export-section h2 {
    color: #2c5aa0;
    margin-top: 0;
}

.export-form {
    display: grid;
    gap: 15px;
    max-width: 500px;
}

.export-form label {
    font-weight: 600;
    color: #333;
}

.export-form input,
.export-form select,
.export-form textarea {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.export-form textarea {
    height: 80px;
    resize: vertical;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 800px;
    max-height: 90vh;
    width: 90%;
    overflow: hidden;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
}

.modal-header {
    background: #2c5aa0;
    color: white;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    color: white;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 30px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    background: #f8f9fa;
    padding: 20px 30px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.import-options {
    display: grid;
    gap: 20px;
}

.page-details {
    display: grid;
    gap: 10px;
    margin-left: 25px;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.page-details.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.page-details label {
    font-weight: 600;
    color: #333;
}

.page-details input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .templates-grid {
        grid-template-columns: 1fr;
    }
    
    .template-categories {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .category-filter {
        margin-right: 0;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Category filtering
    $('.category-filter').on('click', function() {
        const category = $(this).data('category');
        
        $('.category-filter').removeClass('active');
        $(this).addClass('active');
        
        if (category === 'all') {
            $('.template-card').show();
        } else {
            $('.template-card').hide();
            $('.template-card[data-category="' + category + '"]').show();
        }
    });
    
    // Template preview
    $('.preview-template').on('click', function() {
        const templateId = $(this).data('template-id');
        
        $('#template-preview-content').html('<div class="loading">Loading preview...</div>');
        $('#template-preview-modal').show();
        $('.import-from-preview').data('template-id', templateId);
        
        $.post(ajaxurl, {
            action: 'get_template_preview',
            template_id: templateId,
            nonce: '<?php echo wp_create_nonce('inventory_enhanced_admin'); ?>'
        }, function(response) {
            if (response.success) {
                $('#template-preview-content').html(response.data.preview_html);
            } else {
                $('#template-preview-content').html('<div class="error">Failed to load preview</div>');
            }
        });
    });
    
    // Template import
    $('.import-template, .import-from-preview').on('click', function() {
        const templateId = $(this).data('template-id');
        const templateName = $(this).closest('.template-card').find('.template-name').text() || 'Template';
        
        $('#page-title').val(templateName);
        $('#page-slug').val(templateName.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
        $('#template-import-modal').show();
        $('.confirm-import').data('template-id', templateId);
        
        // Hide preview modal if open
        $('#template-preview-modal').hide();
    });
    
    // Toggle page details based on create page checkbox
    $('#create-page').on('change', function() {
        if ($(this).is(':checked')) {
            $('.page-details').removeClass('disabled');
        } else {
            $('.page-details').addClass('disabled');
        }
    });
    
    // Confirm import
    $('.confirm-import').on('click', function() {
        const templateId = $(this).data('template-id');
        const createPage = $('#create-page').is(':checked');
        const pageTitle = $('#page-title').val();
        const pageSlug = $('#page-slug').val();
        
        if (createPage && !pageTitle) {
            alert('<?php echo esc_js(__('Please enter a page title', 'inventory-enhanced')); ?>');
            return;
        }
        
        $(this).prop('disabled', true).text('<?php echo esc_js(__('Importing...', 'inventory-enhanced')); ?>');
        
        $.post(ajaxurl, {
            action: 'import_inventory_template',
            template_id: templateId,
            create_page: createPage,
            page_title: pageTitle,
            page_slug: pageSlug,
            nonce: '<?php echo wp_create_nonce('inventory_enhanced_admin'); ?>'
        }, function(response) {
            if (response.success) {
                $('#template-import-modal').hide();
                
                let message = response.data.message;
                if (response.data.edit_url) {
                    message += '\n\n<?php echo esc_js(__('Would you like to edit the new page?', 'inventory-enhanced')); ?>';
                    if (confirm(message)) {
                        window.open(response.data.edit_url, '_blank');
                    }
                } else {
                    alert(message);
                }
            } else {
                alert('<?php echo esc_js(__('Import failed:', 'inventory-enhanced')); ?> ' + response.data.message);
            }
            
            $('.confirm-import').prop('disabled', false).text('<?php echo esc_js(__('Import Template', 'inventory-enhanced')); ?>');
        });
    });
    
    // Export template
    $('#export-template').on('click', function() {
        const pageId = $('#page-select').val();
        const templateName = $('#template-name').val();
        const templateDescription = $('#template-description').val();
        
        if (!pageId) {
            alert('<?php echo esc_js(__('Please select a page to export', 'inventory-enhanced')); ?>');
            return;
        }
        
        if (!templateName) {
            alert('<?php echo esc_js(__('Please enter a template name', 'inventory-enhanced')); ?>');
            return;
        }
        
        $(this).prop('disabled', true).text('<?php echo esc_js(__('Exporting...', 'inventory-enhanced')); ?>');
        
        $.post(ajaxurl, {
            action: 'export_inventory_template',
            page_id: pageId,
            template_name: templateName,
            template_description: templateDescription,
            nonce: '<?php echo wp_create_nonce('inventory_enhanced_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Template exported successfully!', 'inventory-enhanced')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Export failed:', 'inventory-enhanced')); ?> ' + response.data);
            }
            
            $('#export-template').prop('disabled', false).text('<?php echo esc_js(__('Export as Template', 'inventory-enhanced')); ?>');
        });
    });
    
    // Modal close handlers
    $('.modal-close, .close-modal').on('click', function() {
        $(this).closest('.modal').hide();
    });
    
    // Close modal on backdrop click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Auto-generate slug from title
    $('#page-title').on('input', function() {
        const title = $(this).val();
        const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        $('#page-slug').val(slug);
    });
});
</script>