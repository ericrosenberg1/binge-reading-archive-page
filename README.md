# Binge Reading Archive Page

**Version:** 0.65
**Author:** [Eric Rosenberg](https://ericrosenberg.com)
**WordPress Plugin URL:** [https://wordpress.org/plugins/all-posts-archive-page/](https://wordpress.org/plugins/all-posts-archive-page/)
**Official Plugin Page:** [https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/](https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/)

> A WordPress plugin to create an "all posts since this site started by month" listing. Works with **all themes** using a simple shortcode.

---

## ✨ Features

- Display all posts grouped by **month** using `[binge_archive]`
- **Performance caching** that works with external object caches (Redis, Memcached)
- **Sort order** toggle — newest first or oldest first
- **Any public post type** — posts, pages, or custom types
- **Category filtering** via shortcode or settings
- Optional **year headings** (with heading size H1–H6)
- Optional **month headings** with flexible date formatting and an optional year
- Optional **post counts** next to year and month headings
- Optional **jump-to-year navigation** for long archives
- **Show/hide post dates**, with a custom date format and date/title separator
- Settings page under **Settings → Binge Reading Archive**
- Automatically inherits your theme's typography and layout
- Compatible with **WordPress 5.0+** and **PHP 7.0+**
- Recommended: **WordPress 6.5+** and **PHP 8.1+**
- Tested up to **WordPress 7.0** and **PHP 8.5**
- Clean uninstall option (choose to keep or remove settings)
- No theme dependencies

---

## 🧩 How to Use

1. Install and activate the plugin.
2. Add the shortcode `[binge_archive]` to any WordPress page or post.
3. Visit **Settings → Binge Reading Archive** to configure:
   - Post type and sort order (newest or oldest first)
   - Heading levels for months and years
   - Date format styles (`Jan` vs `01`, `2023` vs `23`)
   - Whether the year shows in each month heading
   - Post counts next to headings
   - Jump-to-year navigation
   - Show or hide post dates, with a custom date format and separator
   - Enable caching for performance and set its duration
   - Filter by category
   - Uninstall behavior (keep or delete settings)

### Shortcode Examples

```
[binge_archive]
[binge_archive category="news"]
[binge_archive post_type="page" order="ASC"]
```

The `category`, `post_type`, and `order` attributes override the saved settings for that one placement.

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

1. Front-end archive output, grouped by month and year and styled by your theme
2. The settings page (Settings → Binge Reading Archive)

---

## ❓ Frequently Asked Questions

### How do I display the archive?
Insert `[binge_archive]` into any post or page. That's it!

### Are there any settings?
Yes! Head to **Settings → Binge Reading Archive** to:
- Pick the post type and sort order
- Toggle year/month headings
- Choose heading sizes (H1–H6)
- Format month/year labels
- Include or hide the year in each month heading
- Add post counts to headings
- Turn on jump-to-year navigation
- Show or hide post dates, with a custom date format and separator
- Enable performance caching
- Configure cache duration
- Filter posts by category
- Control uninstall behavior

### How does caching work?
When enabled, the plugin caches the archive output for faster page loads. The cache clears automatically when you publish, update, or delete posts, and the version-based key works with external object caches like Redis and Memcached. You can set the cache duration from 5 minutes to 7 days.

### Can I show pages or a custom post type instead of posts?
Yes. Set the post type on the settings page, or pass it per placement with `[binge_archive post_type="page"]`. Category filtering applies to standard posts only.

---

## 🔄 Changelog

### 0.65
- **Fix**: Scheduled posts now clear the archive cache the moment they go live. Cache invalidation moved from `save_post` to `transition_post_status`, so a post published by WordPress cron appears in the archive right away instead of waiting for the cache to expire.
- **Accessibility**: The settings page now recommends heading levels that keep a logical document outline (e.g. year `H2`, month `H3`) so screen readers can follow the structure.
- **Styling**: Added a hyphenated `binge-archive-post-date` CSS class alongside the original `archive_post_date` so styling matches the other `binge-archive-*` classes. Existing CSS keeps working.
- **Housekeeping**: The settings table now declares a proper `PRIMARY KEY` with a dbDelta-friendly schema. No action needed on existing installs.

### 0.64
- **Fix**: Resolved a fatal error ("Cannot load all-posts-archive-page") that could appear on non-English sites after updating to 0.63. A community translation with a mismatched placeholder threw an `ArgumentCountError`, tripping WordPress's fatal-error protection and pausing the plugin. All formatted strings are now placeholder-mismatch-proof, and the shortcode and settings page are wrapped in a safety net so a bad translation can't disable the plugin again. Thanks to tompasworld for the report.

### 0.63
- **NEW**: Sort order toggle — list posts newest first or oldest first
- **NEW**: Pick any public post type (posts, pages, or custom types), with a `post_type` shortcode attribute
- **NEW**: Custom post date format (defaults to your site's format) and a configurable date/title separator
- **NEW**: Optional post counts next to year and month headings
- **NEW**: Optional jump-to-year navigation for long archives
- **NEW**: `order` shortcode attribute to override sort order per placement
- **Security**: The archive query now restricts to published posts, so private posts can't appear in cached output
- **Fix**: Cache invalidation now uses a version-based key, so it works with external object caches (Redis, Memcached) where the old direct-database clear missed entries
- **Performance**: Settings load in a single query instead of one per setting, and the archive query no longer loads unused post meta
- Tested up to WordPress 7.0 and PHP 8.5

### 0.62
- **NEW**: Option to leave the year off each month heading. Turn it off to show just the month name (e.g. "January" under a "2026" year heading instead of "January 2026"). Thanks to Thomas for the suggestion.

### 0.61
- Added Settings link to plugin list page for easier access to configuration
- Updated plugin icon/logo with new branding

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

### 0.63
This release adds post type and sort-order options, post counts, jump-to-year navigation, a custom date format and separator, plus a security fix that keeps private posts out of cached output. Cache invalidation now works with external object caches. Tested up to WordPress 7.0 and PHP 8.5. Visit **Settings → Binge Reading Archive** to try the new options.

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
