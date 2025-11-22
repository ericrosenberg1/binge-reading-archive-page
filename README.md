# Binge Reading Archive Page

**Version:** 0.60
**Author:** [Eric Rosenberg](https://ericrosenberg.com)
**WordPress Plugin URL:** [https://wordpress.org/plugins/all-posts-archive-page/](https://wordpress.org/plugins/all-posts-archive-page/)
**Official Plugin Page:** [https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/](https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/)

> A WordPress plugin to create an "all posts since this site started by month" listing. Works with **all themes** using a simple shortcode.

---

## ✨ Features

- Display all posts grouped by **month** using `[binge_archive]`
- **Performance caching** for lightning-fast page loads
- **Show/hide post dates** in archive listings
- **Category filtering** via shortcode or settings
- Optional **year headings** (with heading size H1–H6)
- Optional **month headings** with flexible date formatting
- Settings page under **Settings → Binge Reading Archive**
- Automatically inherits your theme's typography and layout
- Compatible with **WordPress 5.0+** and **PHP 7.0+**
- Tested up to **WordPress 6.8**
- Clean uninstall option (choose to keep or remove settings)
- No theme dependencies

---

## 🧩 How to Use

1. Install and activate the plugin.
2. Add the shortcode `[binge_archive]` to any WordPress page or post.
3. Visit **Settings → Binge Reading Archive** to configure:
   - Heading levels for months and years
   - Date format styles (`Jan` vs `01`, `2023` vs `23`)
   - Show or hide post dates
   - Enable caching for performance
   - Configure cache duration
   - Filter by category
   - Uninstall behavior (keep or delete settings)

### Shortcode Examples

```
[binge_archive]
[binge_archive category="news"]
```

---

## 🛠 Installation

### From the WordPress Plugin Repository:
1. Go to **Plugins → Add New**
2. Search for "Binge Reading Archive Page"
3. Click **Install Now**, then **Activate**

### Manually:
1. Download `all-posts-archive-page.zip`
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate from the Plugins dashboard

### Using FTP:
1. Extract the ZIP
2. Upload the `/all-posts-archive-page` folder to `/wp-content/plugins/`
3. Activate in the dashboard

---

## 📸 Screenshots

1. Shortcode in the block editor
2. Live example of month-by-month archive output using your theme's styling

---

## ❓ Frequently Asked Questions

### How do I display the archive?
Insert `[binge_archive]` into any post or page. That's it!

### Are there any settings?
Yes! Head to **Settings → Binge Reading Archive** to:
- Toggle year/month headings
- Choose heading sizes (H1–H6)
- Format month/year labels
- Show or hide post dates
- Enable performance caching
- Configure cache duration
- Filter posts by category
- Control uninstall behavior

### How does caching work?
When enabled, the plugin caches the archive output for faster page loads. The cache automatically clears when you publish, update, or delete posts. You can configure the cache duration from 5 minutes to 7 days.

---

## 🔄 Changelog

### 0.60
- **NEW**: Performance caching system with configurable duration for faster page loads
- **NEW**: Cache automatically clears when posts are published, updated, or deleted
- **NEW**: Option to show or hide post dates in the archive list
- Enhanced security with improved input validation and sanitization
- Added WordPress version requirements (5.0+) and PHP version requirements (7.0+)
- Optimized database queries for better performance
- Improved compatibility with modern WordPress versions (tested up to 6.8)
- Code quality improvements and WordPress coding standards compliance

### 0.56
- Added option to show or hide post dates in the archive list

### 0.55
- Added option to filter by category using a shortcode or plugin settings
- Added month text formatting options for M and MMMM

### 0.50
- Added optional year headings with selectable heading sizes (H1–H6)
- Added optional month headings with custom format settings
- Created a dedicated settings table in the database
- Option to retain or remove settings on uninstall
- Tested with WordPress 6.2

### 0.4
- Rebuilt as a shortcode-based plugin
- Removed Genesis theme dependency
- Theme-style compatible output (uses `<h2>`, `<ul>`, etc.)

### 0.3 and Earlier
- Legacy page-template-based version with Genesis support
- Admin settings menu added

---

## 📦 Upgrade Notice

### 0.60
This release introduces **performance caching**, **post date visibility controls**, and enhanced security. After upgrading, visit **Settings → Binge Reading Archive** to configure the new caching options for optimal performance.

---

## 🤝 Support & Contributions

Bug reports, feature requests, or PRs are welcome here on GitHub.
For professional help, plugin customizations, or custom WordPress development...

👉 [Hire Eric Rosenberg](https://ericrosenberg.com/contact/)
👉 [Explore more at Eric.money](https://eric.money/)

---

## 📄 License

GPLv2 or later
[https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
