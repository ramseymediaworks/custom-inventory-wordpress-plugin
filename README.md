# Inventory Enhanced Plugin

A complete WordPress solution for heavy equipment dealers that provides advanced filtering, enhanced galleries, AND professional page templates. Transform your inventory website from setup to launch in under an hour.

## 🚀 Features

### **Smart Inventory Filtering**
- Progressive filtering that updates available options in real-time
- AJAX-powered filtering with no page reloads
- Automatic category-based organization (Type, Make, Model, Year, Condition)
- Load more functionality with pagination
- Mobile-responsive sidebar layout
- Visual feedback for unavailable filter combinations

### **Enhanced Divi Gallery Override**
- Replaces default Divi Gallery dots with professional thumbnail navigation
- Seamless image and video integration (MP4, YouTube, Vimeo)
- Touch/swipe support for mobile devices
- Keyboard navigation (arrow keys)
- Responsive design with multiple breakpoints
- Only applies to inventory posts - regular galleries unchanged

### **Complete Page Templates**
- **Inventory Homepage Layout** - Pre-designed with filter sidebar + inventory grid
- **Inventory Single Page Layout** - Optimized for individual equipment display
- **One-click template import** - Professional layouts in seconds
- **Mobile-optimized designs** - Tested responsive layouts
- **Divi-native templates** - Fully editable in Divi Builder

### **Setup Wizard**
- **Guided installation** - Step-by-step setup process
- **Template preview** - See layouts before importing
- **Category structure creation** - Automatic taxonomy setup
- **Demo content option** - Sample inventory for testing
- **Zero coding required** - Complete solution for non-developers

## 📁 Plugin Structure

```
inventory-enhanced/
├── inventory-enhanced.php              # Main plugin file
├── includes/
│   ├── filters.php                     # Inventory filtering system
│   ├── gallery.php                     # Enhanced Divi gallery override
│   ├── admin.php                       # Settings page & admin interface
│   ├── ajax.php                        # AJAX handlers & responses
│   ├── template-importer.php           # JSON template import functionality
│   ├── setup-wizard.php                # First-time setup wizard
│   ├── admin-page.php                  # Main dashboard interface
│   ├── settings-page.php               # Plugin settings page
│   └── templates-page.php              # Template management page
├── assets/
│   ├── css/
│   │   ├── inventory-filters.css       # Filter sidebar styling
│   │   ├── inventory-gallery.css       # Gallery enhancement styles
│   │   └── admin.css                   # Admin interface styling
│   ├── js/
│   │   ├── inventory-filters.js        # Filter interactions & AJAX
│   │   ├── inventory-gallery.js        # Gallery navigation & touch support
│   │   └── admin.js                    # Admin interface functionality
│   └── images/
│       ├── template-previews/          # Screenshots of templates
│       └── plugin-assets/              # Plugin icons and graphics
├── templates/
│   ├── divi-inventory-layout.json      # Complete homepage layout & Single item page layout
│   └── demo-content/
│       ├── sample-inventory.xml        # Demo inventory posts
│       └── sample-images/              # Sample equipment images
├── languages/                          # Translation files
├── readme.txt                          # WordPress plugin directory format
└── README.md                           # This file
```

## 🎯 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Divi Theme**: Required for gallery enhancements
- **Category Structure**: Hierarchical categories under main 'inventory' category

### Required Category Structure
```
Inventory (main category)
├── Type
│   ├── Truck
│   ├── Trailer
│   └── Equipment
├── Make
│   ├── Peterbilt
│   ├── Kenworth
│   ├── Freightliner
│   └── [Other Makes]
├── Model
│   ├── 389
│   ├── 579
│   └── [Other Models]
├── Year
│   ├── 2024
│   ├── 2023
│   └── [Other Years]
└── Condition
    ├── New
    ├── Used
    └── Certified Pre-Owned
```

## ⚡ Installation & Setup

### **Quick Start (5 Minutes)**
1. **Upload** plugin to `/wp-content/plugins/inventory-enhanced/`
2. **Activate** plugin through WordPress admin
3. **Run Setup Wizard** - Appears automatically after activation
4. **Choose Templates** - Select homepage and single page layouts
5. **Import & Go** - One-click import creates your inventory pages

### **Setup Wizard Steps**
1. **Welcome** - Plugin introduction and requirements check
2. **Requirements** - System requirements validation
3. **Templates** - Preview and select page layouts
4. **Categories** - Auto-create inventory category structure
5. **Pages** - Import templates and create inventory pages
6. **Demo Content** - Optional sample inventory for testing
7. **Complete** - Your inventory website is ready!

### **Manual Setup** (Advanced Users)
1. Create category structure manually
2. Add `[inventory_filters]` shortcode to any page
3. Configure settings at Settings → Inventory Enhanced
4. Import templates individually from Templates page

## 🔧 Usage

### **Setting Up Filters**
1. Create your category structure (see requirements above)
2. Assign appropriate categories to your inventory posts
3. Add the `[inventory_filters]` shortcode to your inventory page
4. Position Divi Blog Module to the right of the shortcode

### **Template Management**
- **Template Library** - Visual preview of all available layouts
- **One-Click Import** - Install complete page layouts instantly
- **Custom Templates** - Save your own layouts as templates
- **Template Export** - Share layouts between sites
- **Version Control** - Track template changes and updates

### **Page Templates Included**
- **Inventory Homepage** - Filter sidebar + inventory grid layout
- **Single Inventory Item** - Enhanced gallery + details layout
- **Category Pages** - Filtered views for specific equipment types
- **Search Results** - Optimized layout for search results
- **Contact/Inquiry** - Lead capture forms integrated with inventory

## ⚙️ Configuration

### **Plugin Settings**
Access via WordPress Admin → Settings → Inventory Enhanced

- **Main Category Slug**: Set your main inventory category (default: 'inventory')
- **Posts Per Page**: Control how many items load initially
- **Enable Gallery Override**: Toggle Divi gallery enhancements
- **Filter Layout**: Choose sidebar vs. top layout
- **Styling Options**: Customize colors and fonts
- **Performance Settings**: Enable caching and optimization

### **Shortcode Parameters**
```php
[inventory_filters]                          // Basic usage
[inventory_filters category="inventory"]     // Custom main category
[inventory_filters per_page="12"]           // Custom items per page
[inventory_filters layout="top"]            // Top filter layout
```

## 🎨 Customization

### **CSS Customization**
Override plugin styles in your theme:
```css
/* Filter Sidebar */
.inventory-filter-sidebar { 
    /* Your custom styles */ 
}

/* Gallery Enhancements */
.inventory-enhanced-gallery { 
    /* Your custom styles */ 
}

/* Custom Colors */
:root {
    --inventory-primary: #2c5aa0;
    --inventory-secondary: #ff6900;
}
```

### **Hook Integration**
```php
// Modify filter results
add_filter('inventory_enhanced_filter_results', 'your_custom_function');

// Customize gallery output
add_filter('inventory_enhanced_gallery_output', 'your_gallery_function');

// Add custom filter sections
add_action('inventory_enhanced_after_filters', 'your_additional_filters');
```

### **Template Customization**
- Export existing templates as JSON for backup
- Modify templates in Divi Builder
- Re-export as new custom templates
- Share templates between sites

## 🛠 Troubleshooting

### **Common Issues**

**Setup Wizard Not Appearing**
- Clear browser cache and reload admin
- Check PHP error logs for conflicts
- Ensure proper file permissions

**Divi Builder Timeout**
- Plugin includes backend protection to prevent conflicts
- Gallery enhancements only run on frontend

**Filters Not Showing**
- Verify posts have 'inventory' category assigned
- Check category structure matches requirements
- Ensure shortcode is placed correctly

**Gallery Not Enhanced**
- Confirm post has 'inventory' category
- Verify Divi Gallery Module is being used
- Check browser console for JavaScript errors

**Progressive Filtering Not Working**
- Verify hierarchical category relationships
- Check that posts have multiple relevant categories assigned
- Ensure AJAX is enabled on your site

**Template Import Fails**
- Check server PHP memory limit (recommend 256MB+)
- Verify file permissions in wp-content directory
- Ensure Divi theme is active for Divi templates

## 📊 Performance

- **Optimized AJAX**: Minimal server load with efficient queries
- **Conditional Loading**: Assets only load on inventory pages
- **Caching Friendly**: Compatible with most caching plugins
- **Database Efficient**: Streamlined category queries
- **Image Optimization**: Lazy loading and responsive images
- **Mobile Optimized**: Touch-friendly interfaces

## 🔒 Compatibility

- **Themes**: Works with any theme, optimized for Divi
- **Plugins**: Compatible with most WordPress plugins
- **Caching**: Works with WP Rocket, W3 Total Cache, LiteSpeed, etc.
- **SEO**: Maintains SEO-friendly URLs and meta data
- **Multisite**: Full multisite network support
- **Translation Ready**: Full internationalization support

## 📈 Future Enhancements

- **Advanced Search**: Text-based search within filters
- **Price Filtering**: Range sliders for pricing
- **Map Integration**: Location-based filtering  
- **Export Functionality**: CSV/PDF export of filtered results
- **Multi-language**: Complete translation support
- **Custom Fields**: Integration with ACF and other field plugins
- **Lead Management**: CRM integration for inquiries
- **Inventory Sync**: Import/export from dealer management systems
- **Analytics Dashboard**: Track inventory performance and popular items
- **Email Notifications**: Alert system for new inventory
- **Advanced Templates**: More layout options and designs

## 🌟 Perfect For

- **Heavy Equipment Dealers** - Excavators, bulldozers, cranes
- **Truck Dealerships** - Semi-trucks, commercial vehicles
- **Trailer Sales** - Flatbeds, tankers, specialized trailers
- **Construction Equipment** - Rentals and sales
- **Agricultural Machinery** - Tractors, harvesters, implements
- **Marine Equipment** - Boats, marine engines, accessories
- **Any Inventory Business** - Adaptable category structure

## 🎯 Business Benefits

- **Faster Sales** - Customers find equipment quickly with smart filtering
- **Professional Image** - Modern, responsive design builds trust
- **Mobile Optimized** - 60%+ of traffic comes from mobile devices
- **SEO Friendly** - Proper structure improves search rankings
- **Lead Generation** - Enhanced galleries and clear CTAs increase inquiries
- **Time Savings** - Setup in hours, not weeks of development
- **Cost Effective** - No monthly SaaS fees or per-listing charges

## 💰 ROI Features

- **Reduced Bounce Rate** - Smart filtering keeps visitors engaged
- **Increased Conversions** - Professional gallery presentation
- **Lower Development Costs** - No custom coding required
- **Faster Time to Market** - Launch professional inventory site immediately
- **Scalable Solution** - Grows with your inventory and business
- **Competitive Advantage** - Stand out from basic inventory sites

## 🤝 Support & Documentation

For support, feature requests, or bug reports:
- **Full Documentation** - Comprehensive guides included
- **Video Tutorials** - Step-by-step setup videos
- **Community Forum** - Connect with other users
- **Priority Support** - Direct developer contact for issues
- **Regular Updates** - Ongoing feature improvements

## 📄 License

GPL v2 or later - Free to use, modify, and distribute

## 🏷️ Version Information

**Current Version**: 1.0.0  
**WordPress Tested**: 6.4  
**PHP Tested**: 8.2  
**Divi Tested**: 4.23  
**Release Date**: 2024  
**Update Frequency**: Monthly feature updates, weekly bug fixes

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Smart inventory filtering system
- Enhanced Divi gallery override
- Complete admin interface
- Setup wizard implementation
- Template import/export system
- Mobile responsive design
- Performance optimizations

---

**Built for heavy equipment dealers who demand professional inventory management tools.**

*Transform your inventory website today with the most comprehensive inventory solution for WordPress.*