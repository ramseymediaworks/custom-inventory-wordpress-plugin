/**
 * Inventory Enhanced - Gallery JavaScript
 * 
 * Handles enhanced Divi gallery functionality with thumbnail navigation,
 * video support, touch/swipe interactions, and keyboard navigation
 */

jQuery(document).ready(function($) {
    $('.inventory-enhanced-gallery').each(function() {
        const gallery = $(this);
        const slides = gallery.find('.inventory-gallery-slide');
        const thumbs = gallery.find('.inventory-thumb-item');
        const totalSlides = slides.length;
        let currentSlide = 0;
        let autoplayInterval = null;
        let isDragging = false;
        
        // Gallery settings from localized data
        const settings = window.inventoryGallery ? window.inventoryGallery.settings : {};
        const strings = window.inventoryGallery ? window.inventoryGallery.strings : {};
        
        if (totalSlides <= 1) return; // Skip if only one slide
        
        // Initialize gallery
        init();
        
        function init() {
            // Update counter
            gallery.find('.total-slides').text(totalSlides);
            
            // Set up event listeners
            bindEvents();
            
            // Initialize first slide
            showSlide(0);
            
            // Start autoplay if enabled
            if (settings.autoplay && totalSlides > 1) {
                startAutoplay();
            }
            
            // Add gallery loaded class for styling
            setTimeout(() => {
                gallery.addClass('gallery-loaded');
            }, 100);
            
            // Preload next few images
            preloadImages();
        }
        
        function bindEvents() {
            // Navigation buttons
            gallery.find('.inventory-gallery-next').on('click', function(e) {
                e.preventDefault();
                nextSlide();
                pauseAutoplay();
            });
            
            gallery.find('.inventory-gallery-prev').on('click', function(e) {
                e.preventDefault();
                prevSlide();
                pauseAutoplay();
            });
            
            // Thumbnail clicks
            thumbs.on('click', function() {
                const index = $(this).data('slide');
                showSlide(index);
                pauseAutoplay();
            });
            
            // Keyboard navigation
            gallery.on('keydown', function(e) {
                if (!$(e.target).is('input, textarea, select')) {
                    switch(e.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            e.preventDefault();
                            nextSlide();
                            pauseAutoplay();
                            break;
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            e.preventDefault();
                            prevSlide();
                            pauseAutoplay();
                            break;
                        case 'Home':
                            e.preventDefault();
                            showSlide(0);
                            pauseAutoplay();
                            break;
                        case 'End':
                            e.preventDefault();
                            showSlide(totalSlides - 1);
                            pauseAutoplay();
                            break;
                        case ' ':
                            e.preventDefault();
                            if (autoplayInterval) {
                                pauseAutoplay();
                            } else {
                                startAutoplay();
                            }
                            break;
                    }
                }
            });
            
            // Make gallery focusable for keyboard navigation
            gallery.attr('tabindex', '0');
            
            // Touch/swipe support
            if (settings.swipeEnabled !== false) {
                setupTouchEvents();
            }
            
            // Video handling
            setupVideoEvents();
            
            // Resize handling
            $(window).on('resize', debounce(handleResize, 250));
            
            // Intersection observer for lazy loading
            if ('IntersectionObserver' in window) {
                setupLazyLoading();
            }
        }
        
        function showSlide(index) {
            if (index < 0 || index >= totalSlides || index === currentSlide) return;
            
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
            scrollThumbnailIntoView(index);
            
            // Update ARIA attributes
            updateAriaAttributes(index);
            
            // Trigger custom event
            gallery.trigger('slideChanged', [index, slides.eq(index)]);
            
            // Load slide content if needed
            loadSlideContent(index);
        }
        
        function nextSlide() {
            const next = settings.loop !== false ? 
                (currentSlide + 1) % totalSlides : 
                Math.min(currentSlide + 1, totalSlides - 1);
            showSlide(next);
        }
        
        function prevSlide() {
            const prev = settings.loop !== false ? 
                (currentSlide - 1 + totalSlides) % totalSlides : 
                Math.max(currentSlide - 1, 0);
            showSlide(prev);
        }
        
        function scrollThumbnailIntoView(index) {
            const activeThumb = thumbs.eq(index);
            if (activeThumb.length) {
                const thumbsContainer = gallery.find('.inventory-gallery-thumbs');
                const containerWidth = thumbsContainer.width();
                const thumbWidth = activeThumb.outerWidth(true);
                const thumbPosition = activeThumb.position().left;
                const currentScroll = thumbsContainer.scrollLeft();
                
                let targetScroll = currentScroll + thumbPosition - (containerWidth / 2) + (thumbWidth / 2);
                targetScroll = Math.max(0, targetScroll);
                
                thumbsContainer.animate({
                    scrollLeft: targetScroll
                }, 300);
            }
        }
        
        function setupTouchEvents() {
            let startX = 0;
            let startY = 0;
            const mainGallery = gallery.find('.inventory-gallery-main');
            
            mainGallery.on('touchstart mousedown', function(e) {
                // Prevent default for mouse events but not touch events
                if (e.type === 'mousedown') {
                    e.preventDefault();
                }
                
                isDragging = true;
                const touch = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;
                startX = touch.clientX;
                startY = touch.clientY;
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
                    pauseAutoplay();
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
        }
        
        function setupVideoEvents() {
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
                        slide.addClass('error');
                        console.error('Video failed to load:', video.src);
                    });
                    
                    // Auto-pause when slide becomes inactive
                    video.addEventListener('play', function() {
                        pauseAutoplay();
                    });
                }
                
                // Handle iframe videos (YouTube, Vimeo)
                const iframe = slide.find('iframe')[0];
                if (iframe) {
                    iframe.addEventListener('load', function() {
                        slide.removeClass('loading');
                    });
                }
            });
        }
        
        function pauseAllVideos() {
            // Pause HTML5 videos
            gallery.find('video').each(function() {
                if (this.pause) {
                    this.pause();
                }
            });
            
            // Pause YouTube videos using postMessage API
            gallery.find('iframe[src*="youtube"]').each(function() {
                this.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
            });
            
            // Pause Vimeo videos using postMessage API
            gallery.find('iframe[src*="vimeo"]').each(function() {
                this.contentWindow.postMessage('{"method":"pause"}', '*');
            });
        }
        
        function startAutoplay() {
            if (autoplayInterval) return;
            
            const delay = settings.autoplayDelay || 5000;
            autoplayInterval = setInterval(() => {
                nextSlide();
            }, delay);
            
            gallery.addClass('autoplay-active');
        }
        
        function pauseAutoplay() {
            if (autoplayInterval) {
                clearInterval(autoplayInterval);
                autoplayInterval = null;
                gallery.removeClass('autoplay-active');
            }
        }
        
        function updateAriaAttributes(index) {
            slides.each(function(i) {
                $(this).attr('aria-hidden', i !== index);
            });
            
            thumbs.each(function(i) {
                $(this).attr('aria-selected', i === index);
            });
        }
        
        function loadSlideContent(index) {
            const slide = slides.eq(index);
            const img = slide.find('img');
            
            // Lazy load images
            if (img.length && img.attr('data-src')) {
                img.attr('src', img.attr('data-src')).removeAttr('data-src');
            }
        }
        
        function preloadImages() {
            // Preload current and next few images
            for (let i = 0; i < Math.min(3, totalSlides); i++) {
                loadSlideContent(i);
            }
        }
        
        function setupLazyLoading() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = $(entry.target).find('img[data-src]');
                        if (img.length) {
                            img.attr('src', img.attr('data-src')).removeAttr('data-src');
                        }
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            slides.each(function() {
                observer.observe(this);
            });
        }
        
        function handleResize() {
            // Recalculate thumbnail scrolling
            scrollThumbnailIntoView(currentSlide);
        }
        
        // Public API for external control
        gallery.data('inventoryGallery', {
            next: nextSlide,
            prev: prevSlide,
            goTo: showSlide,
            play: startAutoplay,
            pause: pauseAutoplay,
            getCurrentSlide: () => currentSlide,
            getTotalSlides: () => totalSlides
        });
    });
    
    // Utility functions
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
    
    // Global gallery controls
    window.InventoryGallery = {
        getGallery: function(selector) {
            return $(selector).data('inventoryGallery');
        },
        
        pauseAll: function() {
            $('.inventory-enhanced-gallery').each(function() {
                const api = $(this).data('inventoryGallery');
                if (api) api.pause();
            });
        },
        
        playAll: function() {
            $('.inventory-enhanced-gallery').each(function() {
                const api = $(this).data('inventoryGallery');
                if (api) api.play();
            });
        }
    };
});