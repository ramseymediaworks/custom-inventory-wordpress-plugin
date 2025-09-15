<?php
/**
 * Inventory Enhanced - Divi Gallery Enhancement System
 * 
 * Enhances Divi Gallery Module for inventory posts with:
 * - Professional thumbnail navigation instead of dots
 * - Seamless image and video integration
 * - Touch/swipe support for mobile devices
 * - Responsive design with multiple breakpoints
 * - Smart video detection and handling
 * 
 * @package InventoryEnhanced
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory Enhanced Gallery Class
 */
class InventoryEnhanced_Gallery {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Main inventory category
     */
    private $main_category_slug;
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->main_category_slug = $settings['main_category_slug'] ?? 'inventory';
        $this->init();
    }
    
    /**
     * Initialize gallery enhancements
     */
    private function init() {
        // Only proceed if Divi is active
        if (!$this->is_divi_active()) {
            return;
        }
        
        // Override Divi Gallery Module for inventory posts
        add_filter('et_module_shortcode_output', array($this, 'override_divi_gallery'), 10, 3);
        
        // Enqueue gallery assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_gallery_assets'));
        
        // Add inline styles if no external CSS
        add_action('wp_head', array($this, 'inline_gallery_styles'));
        
        // Add inline scripts if no external JS
        add_action('wp_footer', array($this, 'inline_gallery_scripts'));
        
        // Add video meta box for inventory posts (if enabled in settings)
        if ($this->settings['enable_video_metabox'] ?? false) {
            add_action('add_meta_boxes', array($this, 'add_video_meta_box'));
            add_action('save_post', array($this, 'save_video_meta'));
        }
    }
    
    /**
     * Check if Divi theme is active
     */
    private function is_divi_active() {
        $theme = wp_get_theme();
        return (
            'Divi' === $theme->get('Name') || 
            'Divi' === $theme->get('Template') ||
            function_exists('et_divi_fonts_url') ||
            class_exists('ET_Builder_Module')
        );
    }
    
    /**
     * Override Divi Gallery Module for inventory posts
     */
    public function override_divi_gallery($output, $render_slug, $module) {
        // Only apply to gallery modules
        if ($render_slug !== 'et_pb_gallery') {
            return $output;
        }
        
        // Don't run in Divi Builder or admin area
        if ($this->is_divi_builder_active()) {
            return $output;
        }
        
        // Only apply to inventory posts
        global $post;
        if (!$post || !is_single() || !has_category($this->main_category_slug, $post)) {
            return $output;
        }
        
        // Get the module's attributes/settings
        $gallery_ids = isset($module->props['gallery_ids']) ? $module->props['gallery_ids'] : '';
        
        if (empty($gallery_ids)) {
            return $output; // Return default if no gallery IDs
        }
        
        // Parse gallery IDs and separate images from videos
        $media_items = $this->parse_gallery_media($gallery_ids);
        
        if (empty($media_items['images']) && empty($media_items['videos'])) {
            return $output; // Return default if no valid media
        }
        
        // Add videos from post meta if available
        $meta_videos = $this->get_post_meta_videos($post->ID);
        $media_items['videos'] = array_merge($media_items['videos'], $meta_videos);
        
        // Return enhanced gallery HTML
        return $this->render_enhanced_gallery($media_items, $module->props);
    }
    
    /**
     * Check if Divi Builder is active
     */
    private function is_divi_builder_active() {
        return (
            is_admin() || 
            (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) ||
            (isset($_GET['et_fb']) && $_GET['et_fb'] === '1') ||
            (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'et_') === 0) ||
            (isset($_GET['et_pb_preview']) && $_GET['et_pb_preview'] === 'true')
        );
    }
    
    /**
     * Parse gallery media and separate images from videos
     */
    private function parse_gallery_media($gallery_ids) {
        $image_ids = array_filter(explode(',', $gallery_ids));
        $images = array();
        $videos = array();
        
        foreach ($image_ids as $id) {
            $id = trim($id);
            if (!is_numeric($id)) continue;
            
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
                        'title' => $video_title ?: __('Video', 'inventory-enhanced'),
                        'thumbnail' => $this->get_video_thumbnail($id)
                    );
                }
            } else {
                // It's an image
                $image_data = $this->get_image_data($id);
                if ($image_data) {
                    $images[] = $image_data;
                }
            }
        }
        
        return array(
            'images' => $images,
            'videos' => $videos
        );
    }
    
    /**
     * Get image data with multiple sizes
     */
    private function get_image_data($image_id) {
        $image_large = wp_get_attachment_image_src($image_id, 'large');
        $image_medium = wp_get_attachment_image_src($image_id, 'medium');
        $image_thumb = wp_get_attachment_image_src($image_id, 'thumbnail');
        
        if (!$image_large) {
            return null;
        }
        
        $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $title = get_the_title($image_id);
        $caption = wp_get_attachment_caption($image_id);
        
        return array(
            'id' => $image_id,
            'url' => $image_large[0],
            'width' => $image_large[1],
            'height' => $image_large[2],
            'medium_url' => $image_medium ? $image_medium[0] : $image_large[0],
            'thumb_url' => $image_thumb ? $image_thumb[0] : $image_large[0],
            'srcset' => wp_get_attachment_image_srcset($image_id, 'large'),
            'sizes' => wp_get_attachment_image_sizes($image_id, 'large'),
            'alt' => $alt ?: $title,
            'title' => $title,
            'caption' => $caption
        );
    }
    
    /**
     * Get video thumbnail
     */
    private function get_video_thumbnail($video_id) {
        // Try to get featured image/thumbnail for the video
        $thumb_id = get_post_thumbnail_id($video_id);
        if ($thumb_id) {
            $thumb_url = wp_get_attachment_image_url($thumb_id, 'medium');
            if ($thumb_url) {
                return $thumb_url;
            }
        }
        
        // Return default video thumbnail
        return $this->get_default_video_thumbnail();
    }
    
    /**
     * Get default video thumbnail SVG
     */
    private function get_default_video_thumbnail() {
        $svg = '<svg width="120" height="90" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="120" height="90" fill="#444"/>
            <circle cx="60" cy="45" r="20" fill="rgba(255,255,255,0.9)"/>
            <path d="M52 35l22 10-22 10v-20z" fill="#444"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Get videos from post meta
     */
    private function get_post_meta_videos($post_id) {
        $videos_meta = get_post_meta($post_id, '_inventory_gallery_videos', true);
        $videos = array();
        
        if (empty($videos_meta)) {
            return $videos;
        }
        
        $video_entries = array_filter(explode(',', str_replace(array("\r\n", "\n", "\r"), ',', $videos_meta)));
        
        foreach ($video_entries as $video_entry) {
            $video_entry = trim($video_entry);
            
            // Check if it's a numeric ID (WordPress media)
            if (is_numeric($video_entry)) {
                $video_url = wp_get_attachment_url($video_entry);
                $video_mime = get_post_mime_type($video_entry);
                
                if ($video_url && strpos($video_mime, 'video/') === 0) {
                    $videos[] = array(
                        'type' => 'wordpress',
                        'id' => $video_entry,
                        'url' => $video_url,
                        'mime' => $video_mime,
                        'title' => get_the_title($video_entry) ?: __('Video', 'inventory-enhanced'),
                        'thumbnail' => $this->get_video_thumbnail($video_entry)
                    );
                }
            } else {
                // External URL (YouTube/Vimeo)
                $external_video = $this->parse_external_video($video_entry);
                if ($external_video) {
                    $videos[] = $external_video;
                }
            }
        }
        
        return $videos;
    }
    
    /**
     * Parse external video URL
     */
    private function parse_external_video($video_url) {
        $video_url = trim($video_url);
        
        // YouTube
        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            $video_id = '';
            if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $video_url, $id)) {
                $video_id = $id[1];
            } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $video_url, $id)) {
                $video_id = $id[1];
            }
            
            if ($video_id) {
                return array(
                    'type' => 'youtube',
                    'id' => $video_id,
                    'url' => $video_url,
                    'embed_url' => "https://www.youtube.com/embed/{$video_id}?rel=0&modestbranding=1",
                    'title' => __('YouTube Video', 'inventory-enhanced'),
                    'thumbnail' => "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg"
                );
            }
        }
        
        // Vimeo
        else if (strpos($video_url, 'vimeo.com') !== false) {
            if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $id)) {
                $video_id = $id[1];
                return array(
                    'type' => 'vimeo',
                    'id' => $video_id,
                    'url' => $video_url,
                    'embed_url' => "https://player.vimeo.com/video/{$video_id}?title=0&byline=0&portrait=0",
                    'title' => __('Vimeo Video', 'inventory-enhanced'),
                    'thumbnail' => $this->get_default_video_thumbnail()
                );
            }
        }
        
        return null;
    }
    
    /**
     * Render enhanced gallery
     */
    private function render_enhanced_gallery($media_items, $module_props = array()) {
        $images = $media_items['images'];
        $videos = $media_items['videos'];
        $total_items = count($images) + count($videos);
        $unique_id = 'gallery_' . wp_rand(1000, 9999);
        
        ob_start();
        ?>
        
        <div class="et_pb_module et_pb_gallery inventory-enhanced-gallery" id="<?php echo esc_attr($unique_id); ?>">
            <div class="et_pb_gallery_items">
                <!-- Main Display -->
                <div class="inventory-gallery-main">
                    <?php if ($total_items > 1): ?>
                        <div class="inventory-slide-counter">
                            <span class="current-slide">1</span> / <span class="total-slides"><?php echo $total_items; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php $slide_index = 0; ?>
                    
                    <!-- Image Slides -->
                    <?php foreach ($images as $image): ?>
                        <div class="inventory-gallery-slide <?php echo $slide_index === 0 ? 'active' : ''; ?>" 
                             data-type="image" data-index="<?php echo $slide_index; ?>">
                            <img src="<?php echo esc_url($image['url']); ?>" 
                                 <?php if ($image['srcset']): ?>srcset="<?php echo esc_attr($image['srcset']); ?>"<?php endif; ?>
                                 <?php if ($image['sizes']): ?>sizes="<?php echo esc_attr($image['sizes']); ?>"<?php endif; ?>
                                 alt="<?php echo esc_attr($image['alt']); ?>" 
                                 title="<?php echo esc_attr($image['title']); ?>"
                                 loading="lazy">
                            <?php if ($image['caption']): ?>
                                <div class="gallery-caption">
                                    <?php echo wp_kses_post($image['caption']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $slide_index++; ?>
                    <?php endforeach; ?>
                    
                    <!-- Video Slides -->
                    <?php foreach ($videos as $video): ?>
                        <div class="inventory-gallery-slide <?php echo $slide_index === 0 ? 'active' : ''; ?>" 
                             data-type="video" data-index="<?php echo $slide_index; ?>">
                            <div class="inventory-video-container">
                                <?php echo $this->render_video_player($video); ?>
                            </div>
                        </div>
                        <?php $slide_index++; ?>
                    <?php endforeach; ?>
                    
                    <?php if ($total_items > 1): ?>
                        <!-- Navigation arrows -->
                        <button class="inventory-gallery-nav inventory-gallery-prev" 
                                aria-label="<?php esc_attr_e('Previous image', 'inventory-enhanced'); ?>">‹</button>
                        <button class="inventory-gallery-nav inventory-gallery-next" 
                                aria-label="<?php esc_attr_e('Next image', 'inventory-enhanced'); ?>">›</button>
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
                                 data-type="image"
                                 title="<?php echo esc_attr($image['title']); ?>">
                                <img src="<?php echo esc_url($image['thumb_url']); ?>" 
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     loading="lazy">
                            </div>
                            <?php $thumb_index++; ?>
                        <?php endforeach; ?>
                        
                        <!-- Video Thumbnails -->
                        <?php foreach ($videos as $video): ?>
                            <div class="inventory-thumb-item video <?php echo $thumb_index === 0 ? 'active' : ''; ?>" 
                                 data-slide="<?php echo $thumb_index; ?>" 
                                 data-type="video"
                                 title="<?php echo esc_attr($video['title']); ?>">
                                <img src="<?php echo esc_url($video['thumbnail']); ?>" 
                                     alt="<?php echo esc_attr($video['title']); ?>"
                                     loading="lazy">
                            </div>
                            <?php $thumb_index++; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render video player based on type
     */
    private function render_video_player($video) {
        switch ($video['type']) {
            case 'wordpress':
                return sprintf(
                    '<video controls preload="metadata" style="width: 100%%; height: 100%%; object-fit: contain;">
                        <source src="%s" type="%s">
                        %s
                    </video>',
                    esc_url($video['url']),
                    esc_attr($video['mime']),
                    __('Your browser does not support the video tag.', 'inventory-enhanced')
                );
                
            case 'youtube':
                return sprintf(
                    '<iframe width="100%%" height="100%%" src="%s" 
                             frameborder="0" allowfullscreen 
                             allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>',
                    esc_url($video['embed_url'])
                );
                
            case 'vimeo':
                return sprintf(
                    '<iframe width="100%%" height="100%%" src="%s" 
                             frameborder="0" allowfullscreen 
                             allow="autoplay; fullscreen; picture-in-picture">
                    </iframe>',
                    esc_url($video['embed_url'])
                );
                
            default:
                return '<div class="video-error">' . __('Unsupported video format', 'inventory-enhanced') . '</div>';
        }
    }
    
    /**
     * Enqueue gallery assets
     */
    public function enqueue_gallery_assets() {
        if (!$this->should_load_gallery_assets()) {
            return;
        }
        
        wp_enqueue_style(
            'inventory-enhanced-gallery',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/css/inventory-gallery.css',
            array(),
            INVENTORY_ENHANCED_VERSION
        );
        
        wp_enqueue_script(
            'inventory-enhanced-gallery',
            INVENTORY_ENHANCED_PLUGIN_URL . 'assets/js/inventory-gallery.js',
            array('jquery'),
            INVENTORY_ENHANCED_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('inventory-enhanced-gallery', 'inventoryGallery', array(
            'strings' => array(
                'loading' => __('Loading...', 'inventory-enhanced'),
                'error' => __('Error loading media', 'inventory-enhanced'),
                'prevImage' => __('Previous image', 'inventory-enhanced'),
                'nextImage' => __('Next image', 'inventory-enhanced'),
                'playVideo' => __('Play video', 'inventory-enhanced'),
                'pauseVideo' => __('Pause video', 'inventory-enhanced')
            ),
            'settings' => array(
                'autoplay' => $this->settings['gallery_autoplay'] ?? false,
                'loop' => $this->settings['gallery_loop'] ?? true,
                'showCaptions' => $this->settings['gallery_show_captions'] ?? true,
                'swipeEnabled' => $this->settings['gallery_swipe_enabled'] ?? true
            )
        ));
    }
    
    /**
     * Check if gallery assets should be loaded
     */
    private function should_load_gallery_assets() {
        global $post;
        
        // Load on inventory posts
        if (is_single() && $post && has_category($this->main_category_slug, $post)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Add inline gallery styles
     */
    public function inline_gallery_styles() {
        if (!$this->should_load_gallery_assets()) {
            return;
        }
        
        if (!file_exists(INVENTORY_ENHANCED_PLUGIN_DIR . 'assets/css/inventory-gallery.css')) {
            $primary_color = $this->settings['primary_color'] ?? '#2c5aa0';
            $secondary_color = $this->settings['secondary_color'] ?? '#ff6900';
            ?>
            <style id="inventory-enhanced-gallery-inline">
            /* Inventory Enhanced Gallery Styles */
            
            .inventory-enhanced-gallery {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                overflow: hidden;
                margin-bottom: 30px;
                max-width: 100%;
            }
            
            .inventory-enhanced-gallery .et_pb_gallery_items {
                padding: 0;
                margin: 0;
            }
            
            /* Hide default Divi gallery items */
            .inventory-enhanced-gallery .et_pb_gallery_item {
                display: none !important;
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
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .inventory-video-container iframe,
            .inventory-video-container video {
                width: 100%;
                height: 100%;
                border: none;
                max-width: 100%;
                max-height: 100%;
            }
            
            /* Gallery Caption */
            .gallery-caption {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: linear-gradient(transparent, rgba(0,0,0,0.8));
                color: white;
                padding: 20px;
                font-size: 16px;
                line-height: 1.4;
            }
            
            /* Navigation Arrows */
            .inventory-gallery-nav {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(<?php echo $this->hex_to_rgb($primary_color); ?>, 0.8);
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
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .inventory-gallery-nav:hover {
                background: rgba(<?php echo $this->hex_to_rgb($primary_color); ?>, 1);
                transform: translateY(-50%) scale(1.1);
            }
            
            .inventory-gallery-nav:focus {
                outline: 2px solid <?php echo esc_attr($secondary_color); ?>;
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
                scrollbar-color: <?php echo esc_attr($primary_color); ?> #f0f0f0;
            }
            
            .inventory-gallery-thumbs::-webkit-scrollbar {
                height: 8px;
            }
            
            .inventory-gallery-thumbs::-webkit-scrollbar-track {
                background: #f0f0f0;
                border-radius: 4px;
            }
            
            .inventory-gallery-thumbs::-webkit-scrollbar-thumb {
                background: <?php echo esc_attr($primary_color); ?>;
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
                border-color: <?php echo esc_attr($secondary_color); ?>;
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            }
            
            .inventory-thumb-item.active {
                border-color: <?php echo esc_attr($primary_color); ?>;
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(<?php echo $this->hex_to_rgb($primary_color); ?>, 0.3);
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
            
            /* Loading States */
            .inventory-gallery-slide.loading {
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0f0f0;
            }
            
            .inventory-gallery-slide.loading::after {
                content: "<?php esc_attr_e('Loading...', 'inventory-enhanced'); ?>";
                color: #666;
                font-size: 18px;
            }
            
            /* Error States */
            .video-error {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                background: #f8d7da;
                color: #721c24;
                font-size: 16px;
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
                
                .gallery-caption {
                    font-size: 14px;
                    padding: 15px;
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
            
            /* Accessibility */
            .inventory-thumb-item:focus {
                outline: 2px solid <?php echo esc_attr($secondary_color); ?>;
                outline-offset: 2px;
            }
            
            .inventory-gallery-slide:focus {
                outline: none;
            }
            
            /* Animation for slide transitions */
            .inventory-gallery-slide {
                transition: opacity 0.3s ease;
            }
            
            .inventory-gallery-slide:not(.active) {
                opacity: 0;
            }
            
            .inventory-gallery-slide.active {
                opacity: 1;
            }
            </style>
            <?php
        }
    }
    
    /**
     * Add inline gallery scripts
     */
    public function inline_gallery_scripts() {
        if (!$this->should_load_gallery_assets()) {
            return;
        }
        
        if (!file_exists(INVENTORY_ENHANCED_PLUGIN_DIR . 'assets/js/inventory-gallery.js')) {
            ?>
            <script id="inventory-enhanced-gallery-inline">
            jQuery(document).ready(function($) {
                $('.inventory-enhanced-gallery').each(function() {
                    const gallery = $(this);
                    const slides = gallery.find('.inventory-gallery-slide');
                    const thumbs = gallery.find('.inventory-thumb-item');
                    const totalSlides = slides.length;
                    let currentSlide = 0;
                    
                    if (totalSlides <= 1) return;
                    
                    // Update counter
                    gallery.find('.total-slides').text(totalSlides);
                    
                    // Navigation functions
                    function showSlide(index) {
                        if (index < 0 || index >= totalSlides) return;
                        
                        // Pause any playing videos
                        pauseAllVideos();
                        
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
                            const containerWidth = thumbsContainer.width();
                            const thumbWidth = activeThumb.outerWidth(true);
                            const thumbPosition = activeThumb.position().left;
                            const currentScroll = thumbsContainer.scrollLeft();
                            
                            let targetScroll = currentScroll + thumbPosition - (containerWidth / 2) + (thumbWidth / 2);
                            
                            thumbsContainer.animate({
                                scrollLeft: Math.max(0, targetScroll)
                            }, 300);
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
                    
                    function pauseAllVideos() {
                        // Pause HTML5 videos
                        gallery.find('video').each(function() {
                            if (this.pause) {
                                this.pause();
                            }
                        });
                        
                        // Pause YouTube videos (if any)
                        gallery.find('iframe[src*="youtube"]').each(function() {
                            const src = $(this).attr('src');
                            $(this).attr('src', src); // Reload iframe to stop video
                        });
                    }
                    
                    // Event listeners
                    gallery.find('.inventory-gallery-next').on('click', function(e) {
                        e.preventDefault();
                        nextSlide();
                    });
                    
                    gallery.find('.inventory-gallery-prev').on('click', function(e) {
                        e.preventDefault();
                        prevSlide();
                    });
                    
                    // Thumbnail clicks
                    thumbs.on('click', function() {
                        const index = $(this).data('slide');
                        showSlide(index);
                    });
                    
                    // Keyboard navigation
                    gallery.on('keydown', function(e) {
                        switch(e.key) {
                            case 'ArrowRight':
                            case 'ArrowDown':
                                e.preventDefault();
                                nextSlide();
                                break;
                            case 'ArrowLeft':
                            case 'ArrowUp':
                                e.preventDefault();
                                prevSlide();
                                break;
                            case 'Home':
                                e.preventDefault();
                                showSlide(0);
                                break;
                            case 'End':
                                e.preventDefault();
                                showSlide(totalSlides - 1);
                                break;
                        }
                    });
                    
                    // Make gallery focusable for keyboard navigation
                    gallery.attr('tabindex', '0');
                    
                    // Touch/swipe support
                    let startX = 0;
                    let startY = 0;
                    let isDragging = false;
                    const mainGallery = gallery.find('.inventory-gallery-main');
                    
                    mainGallery.on('touchstart mousedown', function(e) {
                        isDragging = true;
                        const touch = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;
                        startX = touch.clientX;
                        startY = touch.clientY;
                        
                        // Prevent default for mouse events to avoid conflicts
                        if (e.type === 'mousedown') {
                            e.preventDefault();
                        }
                    });
                    
                    mainGallery.on('touchend mouseup', function(e) {
                        if (!isDragging) return;
                        isDragging = false;
                        
                        const touch = e.originalEvent.changedTouches ? e.originalEvent.changedTouches[0] : e.originalEvent;
                        const endX = touch.clientX;
                        const endY = touch.clientY;
                        const diffX = startX - endX;
                        const diffY = startY - endY;
                        
                        // Only process horizontal swipes (ignore vertical scrolling)
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
                    
                    // Handle mouse leave to stop dragging
                    mainGallery.on('mouseleave', function() {
                        isDragging = false;
                    });
                    
                    // Prevent text selection during drag
                    mainGallery.on('selectstart dragstart', function(e) {
                        if (isDragging) {
                            e.preventDefault();
                        }
                    });
                    
                    // Auto-pause videos when changing slides
                    slides.each(function(index) {
                        const slide = $(this);
                        const video = slide.find('video')[0];
                        
                        if (video) {
                            // Add event listeners for video events
                            video.addEventListener('loadstart', function() {
                                slide.addClass('loading');
                            });
                            
                            video.addEventListener('loadeddata', function() {
                                slide.removeClass('loading');
                            });
                            
                            video.addEventListener('error', function() {
                                slide.removeClass('loading');
                                console.error('Video failed to load:', video.src);
                            });
                        }
                    });
                    
                    // Initialize first slide
                    showSlide(0);
                    
                    // Add gallery loaded class for styling
                    setTimeout(function() {
                        gallery.addClass('gallery-loaded');
                    }, 100);
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Add video meta box for inventory posts
     */
    public function add_video_meta_box() {
        global $post;
        if ($post && has_category($this->main_category_slug, $post)) {
            add_meta_box(
                'inventory_gallery_videos',
                __('Gallery Videos (Optional)', 'inventory-enhanced'),
                array($this, 'video_meta_box_callback'),
                'post',
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Video meta box callback
     */
    public function video_meta_box_callback($post) {
        wp_nonce_field('inventory_gallery_meta_box', 'inventory_gallery_nonce');
        $videos = get_post_meta($post->ID, '_inventory_gallery_videos', true);
        ?>
        <p>
            <label for="inventory_gallery_videos">
                <strong><?php _e('Video URLs or Media IDs', 'inventory-enhanced'); ?></strong><br>
                <small><?php _e('Add videos to your gallery using media IDs or external URLs', 'inventory-enhanced'); ?></small>
            </label>
        </p>
        <textarea name="inventory_gallery_videos" id="inventory_gallery_videos" 
                  rows="4" cols="50" style="width: 100%;"><?php echo esc_textarea($videos); ?></textarea>
        <p class="description">
            <strong><?php _e('Examples:', 'inventory-enhanced'); ?></strong><br>
            <?php _e('WordPress Media IDs:', 'inventory-enhanced'); ?> <code>123, 456, 789</code><br>
            <?php _e('YouTube:', 'inventory-enhanced'); ?> <code>https://www.youtube.com/watch?v=ABC123</code><br>
            <?php _e('Vimeo:', 'inventory-enhanced'); ?> <code>https://vimeo.com/123456789</code><br>
            <?php _e('Mixed:', 'inventory-enhanced'); ?> <code>123, https://youtube.com/watch?v=ABC123, 456</code>
        </p>
        <?php
    }
    
    /**
     * Save video meta
     */
    public function save_video_meta($post_id) {
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
            // Convert line breaks to commas for consistency
            $videos = str_replace(array("\r\n", "\n", "\r"), ',', $videos);
            update_post_meta($post_id, '_inventory_gallery_videos', $videos);
        } else {
            delete_post_meta($post_id, '_inventory_gallery_videos');
        }
    }
    
    /**
     * Convert hex color to RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        if (strlen($hex) !== 6) {
            return '44, 90, 160'; // Default blue
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "{$r}, {$g}, {$b}";
    }
}

?>