=== Binge Reading Archive Page ===
Contributors: Eric1985
Tags: archive, posts listing, binge reading, all themes
Donate link: http://narrowbridgemedia.com/
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 7.0
Stable tag: 0.65
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin to create an "all posts since this site started by month" listing. Works on all themes with a shortcode.

== Description ==
This plugin displays all posts by month in a chronological format for easy binge reading. You can simply add the shortcode `[binge_archive]` anywhere on your site to create a month-by-month archive of every post. It's a great way for new readers to dive into your entire blog history!

Requires WordPress 5.0+ and PHP 7.0+ (WordPress 6.5+ and PHP 8.1+ recommended). Tested up to WordPress 7.0 and PHP 8.5.

For more details, visit the official plugin page here:
[https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/](https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/)

== Installation ==
From the WordPress Plugin Repository:
1. Search for "Binge Reading Archive Page"
2. Click "Install," then "Activate"

Manually Using The WordPress Dashboard:
1. Download `all-posts-archive-page.zip` to your computer
2. Navigate to the 'Add New' Plugin screen in WordPress
3. Choose the upload option, and select `all-posts-archive-page.zip`
4. Activate the plugin on the WordPress "Plugins" screen

Using FTP:
1. Download `all-posts-archive-page.zip` to your computer
2. Extract `all-posts-archive-page.zip` to your computer
3. Upload the `all-posts-archive-page` directory to your `wp-content/plugins` directory
4. Activate the plugin on the WordPress "Plugins" screen

== Frequently Asked Questions ==
= How do I display the archive? =
Just insert `[binge_archive]` into any page or post using the block editor or classic editor. That’s it! The plugin automatically creates a listing of all posts by month.

= Does the plugin add any settings to the dashboard now? =
Yes, there is a settings page under "Settings" → "Binge Reading Archive" where you can:
- Choose the post type (posts, pages, or a custom type) and sort order (newest or oldest first)
- Toggle year headings on/off and choose the heading size (H1–H6)
- Toggle month headings on/off and choose the heading size (H1–H6)
- Pick how months and years are formatted (e.g., `Jan 2023` or `01 23`)
- Choose whether each month heading includes the year (e.g., `January 2026` or just `January`)
- Add post counts and a jump-to-year navigation list
- Set a custom post date format and a date/title separator
- Filter standard posts by category
- Decide whether to remove the plugin’s database table when uninstalling

== Screenshots ==
1. Front-end archive output, grouped by month and year and styled by your theme.
2. The settings page under Settings → Binge Reading Archive.

== Upgrade Notice ==
= 0.65 =
Scheduled posts now appear in the archive as soon as they go live. Recommended update if you publish posts on a schedule.

= 0.64 =
Fixes a fatal error ("Cannot load all-posts-archive-page") that could appear on translated, non-English sites after updating to 0.63. Please update.

= 0.63 =
This release adds post type and sort-order options, post counts, jump-to-year navigation, a custom date format and separator, and a security fix that keeps private posts out of cached output. Tested up to WordPress 7.0 and PHP 8.5.

= 0.50 =
Significant improvements have been introduced, including year/month heading controls and formatting options. Please visit the new settings page under "Settings" → "Binge Reading Archive" to configure or update your preferences. You can also choose whether to remove plugin data from your database upon uninstall.

== Changelog ==
= 0.65 =
* Fixed: scheduled posts now clear the archive cache when they go live. The cache now keys off post status changes (transition_post_status) instead of save_post, so a post published by WordPress cron shows up right away instead of waiting for the cache to expire.
* Accessibility: the settings page now suggests heading levels that keep a logical outline (for example, year H2 and month H3) so screen readers can follow along.
* Added a hyphenated CSS class (binge-archive-post-date) alongside the original archive_post_date class for easier, consistent styling. Existing styles keep working.
* Housekeeping: the settings table now uses a proper PRIMARY KEY and a dbDelta-friendly schema. No action needed on existing sites.

= 0.64 =
* Fixed a fatal error ("Cannot load all-posts-archive-page") that could appear on translated, non-English sites after updating to 0.63. A translation with a mismatched placeholder could crash the plugin and pause it. Formatted strings are now mismatch-proof, and the archive and settings page are wrapped so a bad translation can no longer disable the plugin. Thanks to tompasworld for the report.

= 0.63 =
* Added a sort order option (newest first or oldest first), with an `order` shortcode attribute
* Added support for any public post type (posts, pages, custom types), with a `post_type` shortcode attribute
* Added a custom post date format (defaults to the site format) and a configurable date/title separator
* Added optional post counts next to year and month headings
* Added an optional jump-to-year navigation list for long archives
* Security: the archive query now restricts to published posts, so private posts cannot appear in cached output
* Fixed cache invalidation to use a version-based key that works with external object caches (Redis, Memcached)
* Performance: settings now load in a single query, and the archive query no longer loads unused post meta
* Tested up to WordPress 7.0 and PHP 8.5

= 0.62 =
* Added an option to leave the year off each month heading. Turn it off to show just the month name (for example, “January” under a “2026” year heading instead of “January 2026”). Thanks to Thomas for the suggestion.

= 0.61 =
* Added a Settings link on the plugin list page for quicker access
* Updated the plugin icon and branding

= 0.60 =
* Added performance caching with a configurable duration for faster page loads
* Cache now clears automatically when posts are published, updated, or deleted
* Added an option to show or hide post dates in the archive list
* Improved input validation and sanitization
* Added WordPress 5.0+ and PHP 7.0+ requirements
* Optimized database queries
* Tested up to WordPress 6.8

= 0.56 =
* Added an option to show or hide post dates in the archive list

= 0.55 =
* Added option to filter by category using a shortcode or plugin settings
* Added month text formatting options for M and MMMM

= 0.50 =
* Added optional year headings (with selectable H1–H6 size)
* Added optional month headings (with selectable H1–H6 size)
* Added date formatting options for months (MM or MMM) and years (YY or YYYY)
* Created a new database table for plugin settings
* Option to remove or retain the plugin settings table on uninstall
* Ensured compatibility with WordPress 6.2

= 0.4 =
* Rebuilt to use a shortcode instead of a theme-specific page template
* Removed Genesis framework requirements
* Verified compatibility with WordPress 6.2
* Ensures headings, lists, and text follow your active theme’s styling

= 0.3 =
* 10/23/2016
* Remove extra blank lines from code
* Add WordPress administration menu
* Remove unneeded breaks “\” from readme.txt
* Remove unused development file ‘binge-reading-category.html’

= 0.2 =
* 10/21/2016
* Add Genesis theme check and warning message
* Added new name for “PageTemplater” function to avoid conflicts

= 0.1 =
* 10/17/2016
* Initial version