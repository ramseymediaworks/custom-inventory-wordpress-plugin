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
│   └── setup-wizard.php                # First-time setup wizard
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
│   ├── inventory-homepage.json         # Complete homepage layout
│   ├── inventory-single.json           # Single item page layout
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
│   ├── 2023
│   ├── 2022
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
2. **Templates** - Preview and select page layouts
3. **Categories** - Auto-create inventory category structure
4. **Pages** - Import templates and create inventory pages
5. **Demo Content** - Optional sample inventory for testing
6. **Complete** - Your inventory website is ready!

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

### **Shortcode Parameters**
```php
[inventory_filters]                          // Basic usage
[inventory_filters category="inventory"]     // Custom main category
[inventory_filters per_page="12"]           // Custom items per page
```

## 🎨 Customization

### **CSS Customization**
Override plugin styles in your theme:
```css
/* Filter Sidebar */
.inventory-filter-sidebar { /* Your styles */ }

/* Gallery Enhancements */
.inventory-enhanced-gallery { /* Your styles */ }
```

### **Hook Integration**
```php
// Modify filter results
add_filter('inventory_enhanced_filter_results', 'your_custom_function');

// Customize gallery output
add_filter('inventory_enhanced_gallery_output', 'your_gallery_function');
```

## 🐛 Troubleshooting

### **Common Issues**

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

## 📊 Performance

- **Optimized AJAX**: Minimal server load with efficient queries
- **Conditional Loading**: Assets only load on inventory pages
- **Caching Friendly**: Compatible with most caching plugins
- **Database Efficient**: Streamlined category queries

## 🔒 Compatibility

- **Themes**: Works with any theme, optimized for Divi
- **Plugins**: Compatible with most WordPress plugins
- **Caching**: Works with WP Rocket, W3 Total Cache, etc.
- **SEO**: Maintains SEO-friendly URLs and meta data

## 📈 Future Enhancements

- **Advanced Search**: Text-based search within filters
- **Price Filtering**: Range sliders for pricing
- **Map Integration**: Location-based filtering  
- **Export Functionality**: CSV/PDF export of filtered results
- **Multi-language**: Translation support
- **Custom Fields**: Integration with ACF and other field plugins
- **Lead Management**: CRM integration for inquiries
- **Inventory Sync**: Import/export from dealer management systems
- **Analytics Dashboard**: Track inventory performance and popular items

## 🏢 Perfect For

- **Heavy Equipment Dealers** - Excavators, bulldozers, cranes
- **Truck Dealerships** - Semi-trucks, commercial vehicles
- **Trailer Sales** - Flatbeds, tankers, specialized trailers
- **Construction Equipment** - Rentals and sales
- **Agricultural Machinery** - Tractors, harvesters, implements
- **Any Inventory Business** - Adaptable category structure

## 🎯 Business Benefits

- **Faster Sales** - Customers find equipment quickly with smart filtering
- **Professional Image** - Modern, responsive design builds trust
- **Mobile Optimized** - 60%+ of traffic comes from mobile devices
- **SEO Friendly** - Proper structure improves search rankings
- **Lead Generation** - Enhanced galleries and clear CTAs increase inquiries
- **Time Savings** - Setup in hours, not weeks of development

## 💰 ROI Features

- **Reduced Bounce Rate** - Smart filtering keeps visitors engaged
- **Increased Conversions** - Professional gallery presentation
- **Lower Development Costs** - No custom coding required
- **Faster Time to Market** - Launch professional inventory site immediately
- **Scalable Solution** - Grows with your inventory and business

## 🤝 Support

For support, feature requests, or bug reports:
- **Documentation**: Full documentation included
- **GitHub Issues**: Report bugs and request features
- **Email Support**: Direct developer contact

## 📄 License

GPL v2 or later - Free to use, modify, and distribute

## 🏷️ Version

**Current Version**: 1.0.0  
**WordPress Tested**: 6.3  
**PHP Tested**: 8.2  
**Divi Tested**: 4.22

---

**Built for heavy equipment dealers who demand professional inventory management tools.**