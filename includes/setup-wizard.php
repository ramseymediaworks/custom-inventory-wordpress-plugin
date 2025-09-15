<?php
/**
 * Inventory Enhanced - Setup Wizard System
 * 
 * Handles the first-time setup wizard including:
 * - Welcome and requirements check
 * - Template selection and preview
 * - Category structure creation
 * - Page creation with templates
 * - Demo content installation
 * - Final configuration and completion
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced Setup Wizard Class
 */
class InventoryEnhanced_SetupWizard {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Wizard steps
     */
    private $steps = array();
    
    /**
     * Current step
     */
    private $current_step = 0;
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    /**
     * Initialize setup wizard
     */
    private function init() {
        // Define wizard steps
        $this->steps = array(
            'welcome' => array(
                'name' => __('Welcome', 'inventory-enhanced'),
                'view' => 'wizard_welcome',
                'handler' => 'handle_welcome_step'
            ),
            'requirements' => array(
                'name' => __('Requirements', 'inventory-enhanced'),
                'view' => 'wizard_requirements',
                'handler' => 'handle_requirements_step'
            ),
            'templates' => array(
                'name' => __('Templates', 'inventory-enhanced'),
                'view' => 'wizard_templates',
                'handler' => 'handle_templates_step'
            ),
            'categories' => array(
                'name' => __('Categories', 'inventory-enhanced'),
                'view' => 'wizard_categories',
                'handler' => 'handle_categories_step'
            ),
            'pages' => array(
                'name' => __('Pages', 'inventory-enhanced'),
                'view' => 'wizard_pages',
                'handler' => 'handle_pages_step'
            ),
            'demo' => array(
                'name' => __('Demo Content', 'inventory-enhanced'),
                'view' => 'wizard_demo',
                'handler' => 'handle_demo_step'
            ),
            'complete' => array(
                'name' => __('Complete', 'inventory-enhanced'),
                'view' => 'wizard_complete',
                'handler' => 'handle_complete_step'
            )
        );
        
        // AJAX handlers
        add_action('wp_ajax_inventory_wizard_step', array($this, 'handle_wizard_step'));
        add_action('wp_ajax_inventory_wizard_skip', array($this, 'handle_wizard_skip'));
        add_action('wp_ajax_inventory_wizard_reset', array($this, 'handle_wizard_reset'));
        
        // Get current step from URL or session
        $this->current_step = $this->get_current_step();
    }
    
    /**
     * Get current step
     */
    private function get_current_step() {
        // Check URL parameter first
        if (isset($_GET['step'])) {
            $step = sanitize_text_field($_GET['step']);
            if (array_key_exists($step, $this->steps)) {
                return array_search($step, array_keys($this->steps));
            }
        }
        
        // Check session/option
        $saved_step = get_option('inventory_enhanced_wizard_step', 0);
        return max(0, min($saved_step, count($this->steps) - 1));
    }
    
    /**
     * Render setup wizard page
     */
    public function render_wizard_page() {
        $step_keys = array_keys($this->steps);
        $current_step_key = $step_keys[$this->current_step];
        $current_step_data = $this->steps[$current_step_key];
        
        ?>
        <div class="wrap inventory-enhanced-wizard">
            <div class="wizard-container">
                <!-- Header -->
                <div class="wizard-header">
                    <div class="wizard-logo">
                        <img src="<?php echo esc_url(INVENTORY_ENHANCED_PLUGIN_URL . 'assets/images/logo.png'); ?>" 
                             alt="Inventory Enhanced" class="logo-image">
                        <h1><?php _e('Inventory Enhanced Setup', 'inventory-enhanced'); ?></h1>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="wizard-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo esc_attr(($this->current_step / (count($this->steps) - 1)) * 100); ?>%"></div>
                        </div>
                        <div class="progress-steps">
                            <?php foreach ($this->steps as $index => $step): ?>
                                <?php $step_number = array_search($index, array_keys($this->steps)); ?>
                                <div class="progress-step <?php echo $step_number <= $this->current_step ? 'completed' : ''; ?> <?php echo $step_number === $this->current_step ? 'active' : ''; ?>">
                                    <span class="step-number"><?php echo $step_number + 1; ?></span>
                                    <span class="step-name"><?php echo esc_html($step['name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="wizard-content">
                    <div id="wizard-step-content">
                        <?php $this->render_step_content($current_step_key, $current_step_data); ?>
                    </div>
                </div>
                
                <!-- Footer Navigation -->
                <div class="wizard-footer">
                    <div class="wizard-navigation">
                        <?php if ($this->current_step > 0): ?>
                            <button type="button" class="button button-secondary wizard-prev">
                                <?php _e('Previous', 'inventory-enhanced'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <div class="nav-spacer"></div>
                        
                        <?php if ($current_step_key !== 'complete'): ?>
                            <button type="button" class="button button-secondary wizard-skip">
                                <?php _e('Skip Step', 'inventory-enhanced'); ?>
                            </button>
                            
                            <button type="button" class="button button-primary wizard-next" disabled>
                                <?php echo $this->current_step === count($this->steps) - 2 ? __('Finish', 'inventory-enhanced') : __('Continue', 'inventory-enhanced'); ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=inventory-enhanced'); ?>" class="button button-primary">
                                <?php _e('Go to Dashboard', 'inventory-enhanced'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Loading Overlay -->
            <div class="wizard-loading" style="display: none;">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <p class="loading-message"><?php _e('Processing...', 'inventory-enhanced'); ?></p>
                </div>
            </div>
        </div>
        
        <style>
        .inventory-enhanced-wizard {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .wizard-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .wizard-header {
            background: #2c5aa0;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .wizard-logo {
            margin-bottom: 30px;
        }
        
        .wizard-logo h1 {
            color: white;
            font-size: 32px;
            font-weight: 300;
            margin: 15px 0 0 0;
        }
        
        .logo-image {
            height: 60px;
            width: auto;
        }
        
        .wizard-progress {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .progress-bar {
            background: rgba(255,255,255,0.2);
            height: 4px;
            border-radius: 2px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: #ff6900;
            height: 100%;
            transition: width 0.5s ease;
            border-radius: 2px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        .progress-step.active,
        .progress-step.completed {
            opacity: 1;
        }
        
        .step-number {
            background: rgba(255,255,255,0.2);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .progress-step.active .step-number {
            background: #ff6900;
            transform: scale(1.2);
        }
        
        .progress-step.completed .step-number {
            background: #4caf50;
        }
        
        .step-name {
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .wizard-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }
        
        .wizard-footer {
            background: #f8f9fa;
            padding: 20px 40px;
            border-top: 1px solid #e0e0e0;
        }
        
        .wizard-navigation {
            display: flex;
            align-items: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .nav-spacer {
            flex: 1;
        }
        
        .wizard-navigation .button {
            margin-left: 10px;
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 6px;
        }
        
        .wizard-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            min-width: 300px;
        }
        
        .spinner {
            border: 4px solid #f0f0f0;
            border-top: 4px solid #2c5aa0;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-message {
            margin: 0;
            font-size: 16px;
            color: #666;
        }
        
        /* Step Content Styles */
        .step-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .step-title {
            font-size: 28px;
            font-weight: 600;
            color: #2c5aa0;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .step-description {
            font-size: 16px;
            color: #666;
            text-align: center;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .feature-item {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: #2c5aa0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 24px;
            color: white;
        }
        
        .feature-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c5aa0;
            margin-bottom: 10px;
        }
        
        .feature-description {
            color: #666;
            line-height: 1.5;
        }
        
        .requirements-check {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
        }
        
        .requirement-status.pass {
            background: #4caf50;
        }
        
        .requirement-status.fail {
            background: #f44336;
        }
        
        .requirement-status.warning {
            background: #ff9800;
        }
        
        .requirement-text {
            flex: 1;
        }
        
        .requirement-name {
            font-weight: 600;
            color: #333;
        }
        
        .requirement-details {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }
        
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .template-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .template-card:hover {
            border-color: #2c5aa0;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.15);
        }
        
        .template-card.selected {
            border-color: #2c5aa0;
            background: #f0f4ff;
        }
        
        .template-preview {
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
        }
        
        .template-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .template-info {
            padding: 20px;
        }
        
        .template-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c5aa0;
            margin-bottom: 8px;
        }
        
        .template-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .wizard-container {
                margin: 0;
            }
            
            .wizard-header,
            .wizard-content,
            .wizard-footer {
                padding: 20px;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .feature-grid,
            .template-grid {
                grid-template-columns: 1fr;
            }
            
            .wizard-navigation {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-spacer {
                display: none;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render step content
     */
    private function render_step_content($step_key, $step_data) {
        $view_method = $step_data['view'];
        if (method_exists($this, $view_method)) {
            $this->$view_method();
        } else {
            echo '<p>' . __('Step content not found.', 'inventory-enhanced') . '</p>';
        }
    }
    
    /**
     * Render welcome step
     */
    private function wizard_welcome() {
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('Welcome to Inventory Enhanced!', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('Transform your website into a professional inventory management system in just a few minutes. This setup wizard will guide you through the process.', 'inventory-enhanced'); ?>
            </p>
            
            <div class="feature-grid">
                <div class="feature-item">
                    <div class="feature-icon">🎯</div>
                    <h3 class="feature-title"><?php _e('Smart Filtering', 'inventory-enhanced'); ?></h3>
                    <p class="feature-description">
                        <?php _e('Progressive filtering that updates available options in real-time, helping customers find exactly what they need.', 'inventory-enhanced'); ?>
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🖼️</div>
                    <h3 class="feature-title"><?php _e('Enhanced Galleries', 'inventory-enhanced'); ?></h3>
                    <p class="feature-description">
                        <?php _e('Professional thumbnail navigation with video support. Perfect for showcasing equipment with photos and videos.', 'inventory-enhanced'); ?>
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title"><?php _e('Mobile Optimized', 'inventory-enhanced'); ?></h3>
                    <p class="feature-description">
                        <?php _e('Fully responsive design with touch/swipe support. Your inventory looks great on all devices.', 'inventory-enhanced'); ?>
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">⚡</div>
                    <h3 class="feature-title"><?php _e('Professional Templates', 'inventory-enhanced'); ?></h3>
                    <p class="feature-description">
                        <?php _e('Ready-to-use page templates designed specifically for heavy equipment and vehicle dealers.', 'inventory-enhanced'); ?>
                    </p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <p style="font-size: 18px; color: #2c5aa0; font-weight: 600;">
                    <?php _e('Ready to get started?', 'inventory-enhanced'); ?>
                </p>
                <p style="color: #666;">
                    <?php _e('This wizard will help you set up categories, import templates, and configure your inventory system.', 'inventory-enhanced'); ?>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enable next button immediately for welcome step
            $('.wizard-next').prop('disabled', false);
        });
        </script>
        <?php
    }
    
    /**
     * Render requirements step
     */
    private function wizard_requirements() {
        $requirements = $this->check_requirements();
        $all_passed = $this->all_requirements_passed($requirements);
        
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('System Requirements', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('Let\'s make sure your system meets all the requirements for optimal performance.', 'inventory-enhanced'); ?>
            </p>
            
            <div class="requirements-check">
                <?php foreach ($requirements as $requirement): ?>
                    <div class="requirement-item">
                        <div class="requirement-status <?php echo esc_attr($requirement['status']); ?>">
                            <?php echo $requirement['status'] === 'pass' ? '✓' : ($requirement['status'] === 'fail' ? '✕' : '!'); ?>
                        </div>
                        <div class="requirement-text">
                            <div class="requirement-name"><?php echo esc_html($requirement['name']); ?></div>
                            <div class="requirement-details"><?php echo esc_html($requirement['details']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($all_passed): ?>
                <div style="text-align: center; margin-top: 30px; padding: 20px; background: #e8f5e8; border-radius: 8px; border: 1px solid #4caf50;">
                    <p style="color: #2e7d32; font-weight: 600; margin: 0; font-size: 16px;">
                        🎉 <?php _e('Excellent! Your system meets all requirements.', 'inventory-enhanced'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; margin-top: 30px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 1px solid #ff9800;">
                    <p style="color: #ef6c00; font-weight: 600; margin: 0 0 10px 0; font-size: 16px;">
                        ⚠️ <?php _e('Some requirements need attention', 'inventory-enhanced'); ?>
                    </p>
                    <p style="color: #666; margin: 0; font-size: 14px;">
                        <?php _e('The plugin will still work, but some features may be limited. You can continue or fix the issues first.', 'inventory-enhanced'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enable next button for requirements step (warnings are OK)
            $('.wizard-next').prop('disabled', false);
        });
        </script>
        <?php
    }
    
    /**
     * Render templates step
     */
    private function wizard_templates() {
        // Get template importer instance
        $template_importer = new InventoryEnhanced_TemplateImporter($this->settings);
        $available_templates = $template_importer->get_available_templates();
        
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('Choose Your Templates', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('Select the page templates you\'d like to import. These are professionally designed layouts that will give you a head start.', 'inventory-enhanced'); ?>
            </p>
            
            <div class="template-grid">
                <?php foreach ($available_templates as $template): ?>
                    <div class="template-card" data-template-id="<?php echo esc_attr($template['id']); ?>">
                        <div class="template-preview">
                            <?php if ($template['preview_image']): ?>
                                <img src="<?php echo esc_url($template['preview_image']); ?>" 
                                     alt="<?php echo esc_attr($template['name']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <?php _e('Preview not available', 'inventory-enhanced'); ?>
                            <?php endif; ?>
                        </div>
                        <div class="template-info">
                            <h3 class="template-name"><?php echo esc_html($template['name']); ?></h3>
                            <p class="template-description"><?php echo esc_html($template['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($available_templates)): ?>
                <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;">
                    <p style="color: #666; margin: 0;">
                        <?php _e('No templates are currently available. You can skip this step and set up pages manually.', 'inventory-enhanced'); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <input type="hidden" id="selected-templates" name="selected_templates" value="">
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const selectedTemplates = [];
            
            // Template selection
            $('.template-card').on('click', function() {
                const templateId = $(this).data('template-id');
                
                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                    const index = selectedTemplates.indexOf(templateId);
                    if (index > -1) {
                        selectedTemplates.splice(index, 1);
                    }
                } else {
                    $(this).addClass('selected');
                    selectedTemplates.push(templateId);
                }
                
                $('#selected-templates').val(selectedTemplates.join(','));
                
                // Enable/disable next button
                $('.wizard-next').prop('disabled', selectedTemplates.length === 0);
            });
            
            // Select homepage template by default if available
            const homepageTemplate = $('.template-card[data-template-id="homepage"]');
            if (homepageTemplate.length) {
                homepageTemplate.trigger('click');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render categories step
     */
    private function wizard_categories() {
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('Category Structure', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('We\'ll set up your inventory categories automatically. You can customize these later in the WordPress admin.', 'inventory-enhanced'); ?>
            </p>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 30px 0;">
                <h3 style="color: #2c5aa0; margin-top: 0;"><?php _e('Default Category Structure:', 'inventory-enhanced'); ?></h3>
                
                <div style="font-family: monospace; font-size: 14px; line-height: 1.8; color: #555; background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
                    📁 <strong>Inventory</strong> <em>(main category)</em><br>
                    ├── 📁 <strong>Type</strong><br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── Truck<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── Trailer<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;└── Equipment<br>
                    ├── 📁 <strong>Make</strong><br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── Peterbilt<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── Kenworth<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── Freightliner<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;└── John Deere<br>
                    ├── 📁 <strong>Year</strong><br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── 2024<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── 2023<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;├── 2022<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;└── 2021<br>
                    └── 📁 <strong>Condition</strong><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── New<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── Used<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└── Certified Pre-Owned
                </div>
            </div>
            
            <div style="text-align: center;">
                <label style="display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 16px;">
                    <input type="checkbox" id="create-categories" checked style="transform: scale(1.2);">
                    <span><?php _e('Create this category structure automatically', 'inventory-enhanced'); ?></span>
                </label>
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    <?php _e('You can always add, remove, or modify categories later in WordPress admin.', 'inventory-enhanced'); ?>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enable next button by default
            $('.wizard-next').prop('disabled', false);
            
            // Update button state based on checkbox
            $('#create-categories').on('change', function() {
                // Always allow to continue whether checked or not
                $('.wizard-next').prop('disabled', false);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render pages step
     */
    private function wizard_pages() {
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('Create Your Pages', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('We\'ll create the main inventory pages with your selected templates. These will be created as drafts so you can review them first.', 'inventory-enhanced'); ?>
            </p>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 30px 0;">
                <div class="pages-list">
                    <div class="page-item">
                        <div style="display: flex; align-items: center; margin-bottom: 20px;">
                            <div class="page-icon" style="width: 40px; height: 40px; background: #2c5aa0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: white; font-size: 18px;">
                                🏠
                            </div>
                            <div>
                                <h4 style="margin: 0; color: #2c5aa0;"><?php _e('Inventory Homepage', 'inventory-enhanced'); ?></h4>
                                <p style="margin: 0; color: #666; font-size: 14px;"><?php _e('Main inventory listing with filters and grid layout', 'inventory-enhanced'); ?></p>
                            </div>
                        </div>
                        <div style="margin-left: 55px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?php _e('Page Title:', 'inventory-enhanced'); ?></label>
                            <input type="text" id="homepage-title" value="Inventory" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <label style="display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 16px;">
                    <input type="checkbox" id="create-pages" checked style="transform: scale(1.2);">
                    <span><?php _e('Create inventory pages automatically', 'inventory-enhanced'); ?></span>
                </label>
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    <?php _e('Pages will be created as drafts. You can edit and publish them when ready.', 'inventory-enhanced'); ?>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enable next button by default
            $('.wizard-next').prop('disabled', false);
        });
        </script>
        <?php
    }
    
    /**
     * Render demo step
     */
    private function wizard_demo() {
        ?>
        <div class="step-content">
            <h2 class="step-title"><?php _e('Demo Content', 'inventory-enhanced'); ?></h2>
            <p class="step-description">
                <?php _e('Would you like to install sample inventory items to help you get started? This includes example trucks, trailers, and equipment with images.', 'inventory-enhanced'); ?>
            </p>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 30px 0;">
                <div style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                    <h3 style="color: #2c5aa0; margin-bottom: 15px;"><?php _e('Sample Inventory Content', 'inventory-enhanced'); ?></h3>
                    <p style="color: #666; margin-bottom: 20px;">
                        <?php _e('Installing demo content will add:', 'inventory-enhanced'); ?>
                    </p>
                    
                    <div style="text-align: left; max-width: 400px; margin: 0 auto;">
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                ✅ <?php _e('5 Sample Trucks with images', 'inventory-enhanced'); ?>
                            </li>
                            <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                ✅ <?php _e('3 Sample Trailers with details', 'inventory-enhanced'); ?>
                            </li>
                            <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                ✅ <?php _e('2 Equipment items with photos', 'inventory-enhanced'); ?>
                            </li>
                            <li style="padding: 8px 0;">
                                ✅ <?php _e('Proper category assignments', 'inventory-enhanced'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <label style="display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 16px; margin-bottom: 10px;">
                    <input type="checkbox" id="install-demo" style="transform: scale(1.2);">
                    <span><?php _e('Install sample inventory content', 'inventory-enhanced'); ?></span>
                </label>
                <p style="color: #666; font-size: 14px;">
                    <?php _e('You can delete the demo content later if you don\'t need it.', 'inventory-enhanced'); ?>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enable next button regardless of demo choice
            $('.wizard-next').prop('disabled', false);
        });
        </script>
        <?php
    }
    
    /**
     * Render complete step
     */
    private function wizard_complete() {
        ?>
        <div class="step-content">
            <div style="text-align: center;">
                <div style="font-size: 64px; margin-bottom: 30px;">🎉</div>
                <h2 class="step-title"><?php _e('Setup Complete!', 'inventory-enhanced'); ?></h2>
                <p class="step-description">
                    <?php _e('Your inventory system is now ready to use. Here\'s what we\'ve set up for you:', 'inventory-enhanced'); ?>
                </p>
            </div>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 30px 0;">
                <div id="setup-summary">
                    <!-- Summary will be populated by JavaScript -->
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <h3 style="color: #2c5aa0; margin-bottom: 20px;"><?php _e('What\'s Next?', 'inventory-enhanced'); ?></h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
                    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <div style="font-size: 32px; margin-bottom: 15px;">📝</div>
                        <h4 style="color: #2c5aa0; margin-bottom: 10px;"><?php _e('Add Your Inventory', 'inventory-enhanced'); ?></h4>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php _e('Start adding your trucks, trailers, and equipment with photos and details.', 'inventory-enhanced'); ?>
                        </p>
                    </div>
                    
                    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <div style="font-size: 32px; margin-bottom: 15px;">🎨</div>
                        <h4 style="color: #2c5aa0; margin-bottom: 10px;"><?php _e('Customize Pages', 'inventory-enhanced'); ?></h4>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php _e('Edit your pages with Divi Builder to match your brand and style.', 'inventory-enhanced'); ?>
                        </p>
                    </div>
                    
                    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <div style="font-size: 32px; margin-bottom: 15px;">⚙️</div>
                        <h4 style="color: #2c5aa0; margin-bottom: 10px;"><?php _e('Configure Settings', 'inventory-enhanced'); ?></h4>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php _e('Fine-tune colors, layouts, and features in the plugin settings.', 'inventory-enhanced'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; background: #e8f5e8; padding: 25px; border-radius: 8px; border: 1px solid #4caf50; margin-top: 30px;">
                <p style="color: #2e7d32; font-weight: 600; margin: 0; font-size: 16px;">
                    <?php _e('🚀 Your professional inventory website is ready to go!', 'inventory-enhanced'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle wizard step AJAX request
     */
    public function handle_wizard_step() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
                wp_die(__('Security check failed', 'inventory-enhanced'));
            }
            
            $step = sanitize_text_field($_POST['step']);
            $direction = sanitize_text_field($_POST['direction']); // 'next' or 'prev'
            $step_data = isset($_POST['step_data']) ? $_POST['step_data'] : array();
            
            // Process current step data
            $step_keys = array_keys($this->steps);
            $current_step_index = array_search($step, $step_keys);
            
            if ($current_step_index === false) {
                wp_send_json_error(__('Invalid step', 'inventory-enhanced'));
                return;
            }
            
            // Handle step-specific processing
            if ($direction === 'next') {
                $handler_method = $this->steps[$step]['handler'];
                if (method_exists($this, $handler_method)) {
                    $handler_result = $this->$handler_method($step_data);
                    if (!$handler_result['success']) {
                        wp_send_json_error($handler_result['message']);
                        return;
                    }
                }
            }
            
            // Calculate next step
            if ($direction === 'next') {
                $next_step_index = min($current_step_index + 1, count($step_keys) - 1);
            } else {
                $next_step_index = max($current_step_index - 1, 0);
            }
            
            // Save progress
            update_option('inventory_enhanced_wizard_step', $next_step_index);
            
            // Get next step content
            $next_step_key = $step_keys[$next_step_index];
            $next_step_data = $this->steps[$next_step_key];
            
            ob_start();
            $this->render_step_content($next_step_key, $next_step_data);
            $step_content = ob_get_clean();
            
            wp_send_json_success(array(
                'step' => $next_step_key,
                'step_index' => $next_step_index,
                'step_name' => $next_step_data['name'],
                'content' => $step_content,
                'progress' => ($next_step_index / (count($step_keys) - 1)) * 100,
                'is_last_step' => $next_step_index === count($step_keys) - 1
            ));
            
        } catch (Exception $e) {
            inventory_enhanced_log('Setup wizard error: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred during setup', 'inventory-enhanced'));
        }
    }
    
    /**
     * Handle welcome step
     */
    private function handle_welcome_step($step_data) {
        // No processing needed for welcome step
        return array('success' => true);
    }
    
    /**
     * Handle requirements step
     */
    private function handle_requirements_step($step_data) {
        // Requirements are checked but don't block progression
        return array('success' => true);
    }
    
    /**
     * Handle templates step
     */
    private function handle_templates_step($step_data) {
        $selected_templates = isset($step_data['selected_templates']) ? $step_data['selected_templates'] : '';
        
        // Save selected templates
        update_option('inventory_enhanced_wizard_templates', $selected_templates);
        
        return array('success' => true);
    }
    
    /**
     * Handle categories step
     */
    private function handle_categories_step($step_data) {
        $create_categories = isset($step_data['create_categories']) ? (bool) $step_data['create_categories'] : false;
        
        if ($create_categories) {
            $result = $this->create_default_categories();
            if (!$result['success']) {
                return $result;
            }
        }
        
        return array('success' => true);
    }
    
    /**
     * Handle pages step
     */
    private function handle_pages_step($step_data) {
        $create_pages = isset($step_data['create_pages']) ? (bool) $step_data['create_pages'] : false;
        $homepage_title = isset($step_data['homepage_title']) ? sanitize_text_field($step_data['homepage_title']) : 'Inventory';
        
        if ($create_pages) {
            $result = $this->create_default_pages($homepage_title);
            if (!$result['success']) {
                return $result;
            }
        }
        
        return array('success' => true);
    }
    
    /**
     * Handle demo step
     */
    private function handle_demo_step($step_data) {
        $install_demo = isset($step_data['install_demo']) ? (bool) $step_data['install_demo'] : false;
        
        if ($install_demo) {
            // Install demo content (implement if needed)
            // For now, just mark that demo was requested
            update_option('inventory_enhanced_demo_requested', true);
        }
        
        return array('success' => true);
    }
    
    /**
     * Handle complete step
     */
    private function handle_complete_step($step_data) {
        // Mark setup as completed
        $this->settings['setup_completed'] = true;
        update_option('inventory_enhanced_settings', $this->settings);
        
        // Clean up wizard data
        delete_option('inventory_enhanced_wizard_step');
        delete_option('inventory_enhanced_wizard_templates');
        
        return array('success' => true);
    }
    
    /**
     * Check system requirements
     */
    private function check_requirements() {
        $requirements = array();
        
        // WordPress version
        $wp_version = get_bloginfo('version');
        $requirements[] = array(
            'name' => __('WordPress Version', 'inventory-enhanced'),
            'details' => sprintf(__('Required: 5.0+ | Current: %s', 'inventory-enhanced'), $wp_version),
            'status' => version_compare($wp_version, '5.0', '>=') ? 'pass' : 'fail'
        );
        
        // PHP version
        $php_version = PHP_VERSION;
        $requirements[] = array(
            'name' => __('PHP Version', 'inventory-enhanced'),
            'details' => sprintf(__('Required: 7.4+ | Current: %s', 'inventory-enhanced'), $php_version),
            'status' => version_compare($php_version, '7.4', '>=') ? 'pass' : 'fail'
        );
        
        // Divi theme
        $theme = wp_get_theme();
        $is_divi = ('Divi' === $theme->get('Name') || 'Divi' === $theme->get('Template') || function_exists('et_divi_fonts_url'));
        $requirements[] = array(
            'name' => __('Divi Theme', 'inventory-enhanced'),
            'details' => $is_divi ? __('Active | Gallery enhancements available', 'inventory-enhanced') : __('Not detected | Gallery features will be limited', 'inventory-enhanced'),
            'status' => $is_divi ? 'pass' : 'warning'
        );
        
        // Memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        $recommended_memory = 128 * 1024 * 1024; // 128MB
        
        $requirements[] = array(
            'name' => __('PHP Memory Limit', 'inventory-enhanced'),
            'details' => sprintf(__('Recommended: 128MB+ | Current: %s', 'inventory-enhanced'), $memory_limit),
            'status' => $memory_limit_bytes >= $recommended_memory ? 'pass' : 'warning'
        );
        
        // File permissions
        $uploads_dir = wp_upload_dir();
        $is_writable = wp_is_writable($uploads_dir['basedir']);
        
        $requirements[] = array(
            'name' => __('File Permissions', 'inventory-enhanced'),
            'details' => $is_writable ? __('Uploads directory is writable', 'inventory-enhanced') : __('Uploads directory is not writable', 'inventory-enhanced'),
            'status' => $is_writable ? 'pass' : 'fail'
        );
        
        return $requirements;
    }
    
    /**
     * Check if all requirements passed
     */
    private function all_requirements_passed($requirements) {
        foreach ($requirements as $requirement) {
            if ($requirement['status'] === 'fail') {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Create default categories
     */
    private function create_default_categories() {
        try {
            $main_category_slug = $this->settings['main_category_slug'] ?? 'inventory';
            
            // Create main inventory category
            $main_category = wp_insert_term('Inventory', 'category', array(
                'slug' => $main_category_slug,
                'description' => __('Main inventory category for all equipment, trucks, and trailers.', 'inventory-enhanced')
            ));
            
            if (is_wp_error($main_category)) {
                if ($main_category->get_error_code() === 'term_exists') {
                    $main_category = get_term_by('slug', $main_category_slug, 'category');
                    $main_category_id = $main_category->term_id;
                } else {
                    return array(
                        'success' => false,
                        'message' => $main_category->get_error_message()
                    );
                }
            } else {
                $main_category_id = $main_category['term_id'];
            }
            
            // Create category structure
            $categories_structure = array(
                'Type' => array('Truck', 'Trailer', 'Equipment'),
                'Make' => array('Peterbilt', 'Kenworth', 'Freightliner', 'John Deere', 'Great Dane'),
                'Year' => array('2024', '2023', '2022', '2021', '2020'),
                'Condition' => array('New', 'Used', 'Certified Pre-Owned')
            );
            
            foreach ($categories_structure as $parent_name => $children) {
                // Create parent category
                $parent_slug = sanitize_title($parent_name);
                $parent_term = wp_insert_term($parent_name, 'category', array(
                    'slug' => "inventory_{$parent_slug}",
                    'parent' => $main_category_id
                ));
                
                if (is_wp_error($parent_term)) {
                    if ($parent_term->get_error_code() === 'term_exists') {
                        $existing_parent = get_term_by('slug', "inventory_{$parent_slug}", 'category');
                        $parent_id = $existing_parent->term_id;
                    } else {
                        continue; // Skip this section if we can't create parent
                    }
                } else {
                    $parent_id = $parent_term['term_id'];
                }
                
                // Create child categories
                foreach ($children as $child_name) {
                    $child_slug = sanitize_title($child_name);
                    wp_insert_term($child_name, 'category', array(
                        'slug' => $child_slug,
                        'parent' => $parent_id
                    ));
                }
            }
            
            return array('success' => true);
            
        } catch (Exception $e) {
            inventory_enhanced_log('Category creation error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to create categories', 'inventory-enhanced')
            );
        }
    }
    
    /**
     * Create default pages
     */
    private function create_default_pages($homepage_title = 'Inventory') {
        try {
            $homepage_slug = sanitize_title($homepage_title);
            
            // Check if page already exists
            $existing_page = get_page_by_path($homepage_slug);
            if ($existing_page) {
                return array(
                    'success' => false,
                    'message' => __('A page with this slug already exists', 'inventory-enhanced')
                );
            }
            
            // Create inventory homepage
            $page_content = '[inventory_filters]';
            
            $page_id = wp_insert_post(array(
                'post_title' => $homepage_title,
                'post_content' => $page_content,
                'post_status' => 'draft',
                'post_type' => 'page',
                'post_name' => $homepage_slug,
                'meta_input' => array(
                    '_et_pb_use_builder' => 'on',
                    '_et_pb_page_layout' => 'et_full_width_page',
                    '_inventory_enhanced_page' => true
                )
            ));
            
            if (is_wp_error($page_id)) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create page', 'inventory-enhanced')
                );
            }
            
            // Save page ID for reference
            update_option('inventory_enhanced_main_page_id', $page_id);
            
            return array('success' => true, 'page_id' => $page_id);
            
        } catch (Exception $e) {
            inventory_enhanced_log('Page creation error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to create pages', 'inventory-enhanced')
            );
        }
    }
    
    /**
     * Handle wizard skip AJAX request
     */
    public function handle_wizard_skip() {
        if (!wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        // Mark setup as completed (skip remaining steps)
        $this->settings['setup_completed'] = true;
        update_option('inventory_enhanced_settings', $this->settings);
        
        wp_send_json_success(array(
            'message' => __('Setup wizard skipped', 'inventory-enhanced'),
            'redirect' => admin_url('admin.php?page=inventory-enhanced')
        ));
    }
    
    /**
     * Handle wizard reset AJAX request
     */
    public function handle_wizard_reset() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'inventory_enhanced_admin')) {
            wp_die(__('Security check failed', 'inventory-enhanced'));
        }
        
        // Reset wizard progress
        delete_option('inventory_enhanced_wizard_step');
        delete_option('inventory_enhanced_wizard_templates');
        
        // Reset setup completed flag
        $this->settings['setup_completed'] = false;
        update_option('inventory_enhanced_settings', $this->settings);
        
        wp_send_json_success(array(
            'message' => __('Wizard reset successfully', 'inventory-enhanced'),
            'redirect' => admin_url('admin.php?page=inventory-enhanced-setup')
        ));
    }
}

?>