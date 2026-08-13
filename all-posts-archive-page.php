<?php
/**
 * Plugin Name: Binge Reading Archive Page
 * Plugin URI:  https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/
 * Description: Display all posts month-by-month for binge reading. Uses your theme's styling by default. Supports optional category filtering and flexible month formats.
 * Version:     0.66
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * PHP tested up to: 8.5
 * Recommended: WordPress 6.5+, PHP 8.2+
 * Author:      Eric Rosenberg
 * Author URI:  https://ericrosenberg.com
 * Text Domain: all-posts-archive-page
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BingeReadingArchivePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

/**
 * Load text domain for i18n.
 */
function brap_load_textdomain() {
	load_plugin_textdomain( 'all-posts-archive-page', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'brap_load_textdomain' );

/**
 * Activation Hook: Create/upgrade settings table if it doesn't exist.
 */
register_activation_hook( __FILE__, 'brap_activate' );
function brap_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'brap_settings';
	$charset_collate = $wpdb->get_charset_collate();

	// dbDelta is picky: no IF NOT EXISTS, two spaces after PRIMARY KEY, one field per line.
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		setting_name varchar(50) NOT NULL,
		setting_value varchar(50) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY setting_name (setting_name)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Insert default settings if they don't already exist.
	$default_settings = brap_default_settings();

	foreach ( $default_settings as $name => $value ) {
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE setting_name = %s", $name )
		);

		if ( ! $exists ) {
			$wpdb->insert(
				$table_name,
				array(
					'setting_name'  => $name,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	// Refresh the in-request settings cache after seeding defaults.
	brap_flush_settings_cache();
}

/**
 * Default settings and their fallback values.
 *
 * Used both to seed the table on activation and as the source of truth for
 * fallbacks when a setting row is missing (e.g. after an in-place upgrade).
 *
 * @return array<string,string>
 */
function brap_default_settings() {
	return array(
		'add_year_header'              => 'off',
		'year_header_level'            => 'h2',
		'add_month_header'             => 'on',
		'month_header_level'           => 'h3',
		// Show the year alongside the month in each month heading.
		'show_year_in_month_header'    => 'on',
		// Month format options: MM (01), MMM (Aug), M (8), MMMM (August).
		'month_format'                 => 'MMM',
		// Year format options: YY (23), YYYY (2023).
		'year_format'                  => 'YYYY',
		// Sort order for posts: DESC (newest first) or ASC (oldest first).
		'post_order'                   => 'DESC',
		// Post type to list. Defaults to standard posts.
		'post_type'                    => 'post',
		// Date format for each post (empty = use the site's date format).
		'post_date_format'             => '',
		// Separator printed between the post date and title.
		'date_title_separator'         => ' - ',
		// Show a jump-to-year navigation list above the archive.
		'show_year_nav'                => 'off',
		// Show post counts next to year/month headings.
		'show_post_count'              => 'off',
		'remove_db_table_on_uninstall' => 'no',
		// Default category slug; empty means "all categories".
		'category_filter'              => '',
		// Show/hide post dates in list.
		'show_post_date'               => 'on',
		// Enable transient caching for performance.
		'enable_cache'                 => 'on',
		// Cache duration in seconds (default 12 hours).
		'cache_duration'               => '43200',
	);
}

/**
 * Uninstall Hook: Allows table deletion if user chooses so in plugin settings.
 */
register_uninstall_hook( __FILE__, 'brap_uninstall' );
function brap_uninstall() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'brap_settings';

	// Check user's preference in our table for removing data.
	$remove_data = $wpdb->get_var(
		$wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_name = %s", 'remove_db_table_on_uninstall' )
	);

	// If user has chosen 'yes', drop the table and remove plugin options/caches.
	if ( 'yes' === $remove_data ) {
		// dbDelta() cannot drop; direct query required.
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( 'brap_cache_version' );
		brap_delete_cached_output();
	}
}

/**
 * Helper: load all plugin settings in a single query, memoized per request.
 *
 * @return array<string,string>
 */
function brap_get_all_settings() {
	if ( isset( $GLOBALS['brap_settings_cache'] ) && is_array( $GLOBALS['brap_settings_cache'] ) ) {
		return $GLOBALS['brap_settings_cache'];
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'brap_settings';
	$settings   = array();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT setting_name, setting_value FROM {$table_name}", ARRAY_A );
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$settings[ $row['setting_name'] ] = $row['setting_value'];
		}
	}

	$GLOBALS['brap_settings_cache'] = $settings;
	return $settings;
}

/**
 * Helper: clear the in-request settings cache (call after writes).
 *
 * @return void
 */
function brap_flush_settings_cache() {
	unset( $GLOBALS['brap_settings_cache'] );
}

/**
 * Helper: get a single setting, falling back to its default when missing.
 *
 * @param string $name Setting name.
 * @return string|false Setting value, default, or false if unknown.
 */
function brap_get_setting( $name ) {
	$settings = brap_get_all_settings();

	if ( isset( $settings[ $name ] ) && '' !== $settings[ $name ] && null !== $settings[ $name ] ) {
		return $settings[ $name ];
	}

	// Fall back to the documented default so in-place upgrades behave sanely.
	$defaults = brap_default_settings();
	if ( isset( $defaults[ $name ] ) && '' !== $defaults[ $name ] ) {
		return $defaults[ $name ];
	}

	return false;
}

/**
 * Helper: update/insert setting in custom table.
 *
 * @param string $name  Setting name.
 * @param string $value Setting value.
 * @return void
 */
function brap_update_setting( $name, $value ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'brap_settings';

	$exists = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE setting_name = %s", $name )
	);

	if ( $exists ) {
		$wpdb->update(
			$table_name,
			array( 'setting_value' => $value ),
			array( 'setting_name'  => $name ),
			array( '%s' ),
			array( '%s' )
		);
	} else {
		$wpdb->insert(
			$table_name,
			array(
				'setting_name'  => $name,
				'setting_value' => $value,
			),
			array( '%s', '%s' )
		);
	}

	brap_flush_settings_cache();
}

/**
 * Determine the effective category slug to filter by.
 *
 * Priority: shortcode attribute > saved setting. Returns '' if none/invalid.
 *
 * @param string $shortcode_slug Category slug from shortcode attribute (unsanitized).
 * @return string Valid slug or empty string.
 */
function brap_get_effective_category_slug( $shortcode_slug = '' ) {
	$slug = '';

	if ( is_string( $shortcode_slug ) && '' !== $shortcode_slug ) {
		$slug = sanitize_title( $shortcode_slug );
	} else {
		$setting_slug = brap_get_setting( 'category_filter' );
		if ( is_string( $setting_slug ) && '' !== $setting_slug ) {
			$slug = sanitize_title( $setting_slug );
		}
	}

	// Validate slug maps to an existing category term.
	if ( '' !== $slug ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}
	}

	return $slug;
}

/**
 * Determine the effective post type to list.
 *
 * Priority: shortcode attribute > saved setting > 'post'. Falls back to 'post'
 * if the requested type isn't a registered, public post type.
 *
 * @param string $shortcode_post_type Post type from shortcode attribute (unsanitized).
 * @return string A valid public post type.
 */
function brap_get_effective_post_type( $shortcode_post_type = '' ) {
	$post_type = '';

	if ( is_string( $shortcode_post_type ) && '' !== $shortcode_post_type ) {
		$post_type = sanitize_key( $shortcode_post_type );
	} else {
		$setting = brap_get_setting( 'post_type' );
		if ( is_string( $setting ) && '' !== $setting ) {
			$post_type = sanitize_key( $setting );
		}
	}

	if ( '' === $post_type ) {
		return 'post';
	}

	$obj = get_post_type_object( $post_type );
	if ( ! $obj || empty( $obj->public ) ) {
		return 'post';
	}

	return $post_type;
}

/**
 * Determine the effective sort order.
 *
 * Priority: shortcode attribute > saved setting > 'DESC'.
 *
 * @param string $shortcode_order Order from shortcode attribute (unsanitized).
 * @return string 'ASC' or 'DESC'.
 */
function brap_get_effective_order( $shortcode_order = '' ) {
	$order = '';

	if ( is_string( $shortcode_order ) && '' !== $shortcode_order ) {
		$order = strtoupper( trim( $shortcode_order ) );
	} else {
		$setting = brap_get_setting( 'post_order' );
		if ( is_string( $setting ) && '' !== $setting ) {
			$order = strtoupper( trim( $setting ) );
		}
	}

	return ( 'ASC' === $order ) ? 'ASC' : 'DESC';
}

/**
 * Map stored month/year formats to PHP date() tokens used by WP.
 *
 * @param string $month_format One of MM, MMM, M, MMMM.
 * @param string $year_format  One of YY, YYYY.
 * @return array { 'month' => 'm|M|n|F', 'year' => 'y|Y' }
 */
function brap_get_date_tokens( $month_format, $year_format ) {
	$allowed_month = array( 'MM', 'MMM', 'M', 'MMMM' );
	$allowed_year  = array( 'YY', 'YYYY' );

	if ( ! in_array( $month_format, $allowed_month, true ) ) {
		$month_format = 'MMM';
	}
	if ( ! in_array( $year_format, $allowed_year, true ) ) {
		$year_format = 'YYYY';
	}

	$month_token = 'M'; // default 'Aug'.
	switch ( $month_format ) {
		case 'MM':
			$month_token = 'm'; // 01-12.
			break;
		case 'M':
			$month_token = 'n'; // 1-12 no leading zero.
			break;
		case 'MMMM':
			$month_token = 'F'; // Full month name.
			break;
		case 'MMM':
		default:
			$month_token = 'M'; // Short month (Aug).
			break;
	}

	$year_token = ( 'YYYY' === $year_format ) ? 'Y' : 'y';

	return array(
		'month' => $month_token,
		'year'  => $year_token,
	);
}

/**
 * Get the current cache version. Lazily created (not autoloaded).
 *
 * The version is baked into every cache key, so bumping it invalidates all
 * cached output at once. This works with external object caches (Redis,
 * Memcached) where a direct SQL delete on the options table would not.
 *
 * @return string
 */
function brap_get_cache_version() {
	$version = get_option( 'brap_cache_version' );
	if ( false === $version ) {
		$version = '1';
		add_option( 'brap_cache_version', $version, '', false );
	}
	return (string) $version;
}

/**
 * Bump the cache version, invalidating all cached archive output.
 *
 * @return void
 */
function brap_bump_cache_version() {
	$version = (int) brap_get_cache_version();
	update_option( 'brap_cache_version', (string) ( $version + 1 ), false );
}

/**
 * Best-effort cleanup of any stored archive transients (used on uninstall).
 *
 * @return void
 */
function brap_delete_cached_output() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_brap_archive_%' OR option_name LIKE '_transient_timeout_brap_archive_%'" );
}

/**
 * Bump the cache version when a post is permanently deleted.
 *
 * @return void
 */
function brap_clear_cache_on_post_change() {
	brap_bump_cache_version();
}
add_action( 'delete_post', 'brap_clear_cache_on_post_change' );

/**
 * Bump the cache version on any status change that touches a published post.
 *
 * Using transition_post_status (rather than save_post) catches scheduled posts
 * that go live via cron, which call wp_publish_post() without firing save_post.
 * It also covers publish, unpublish, trash, and edits to an already-published
 * post. Revisions and autosaves carry the 'inherit'/'draft' status, so they are
 * ignored and do not needlessly invalidate the cache.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       The post being transitioned.
 * @return void
 */
function brap_clear_cache_on_transition( $new_status, $old_status, $post ) {
	unset( $post );
	if ( 'publish' === $new_status || 'publish' === $old_status ) {
		brap_bump_cache_version();
	}
}
add_action( 'transition_post_status', 'brap_clear_cache_on_transition', 10, 3 );

/**
 * Bump the cache version when a category term is renamed, re-slugged, or
 * re-parented via wp_update_term().
 *
 * The archive's cache key is built from the *validated* category slug
 * (see brap_get_effective_category_slug()), not the term ID. If a category
 * used as the default filter gets its slug changed, the old cache entries
 * are simply orphaned (harmless but wasted). Without this hook, visitors
 * could keep seeing output rendered against the pre-rename slug until the
 * transient's natural expiry. Bumping here makes the change take effect on
 * the next page view instead.
 *
 * @param int    $term_id  Term ID (unused).
 * @param string $taxonomy Taxonomy slug.
 * @return void
 */
function brap_clear_cache_on_term_edit( $term_id, $taxonomy ) {
	unset( $term_id );
	if ( 'category' === $taxonomy ) {
		brap_bump_cache_version();
	}
}
add_action( 'edited_terms', 'brap_clear_cache_on_term_edit', 10, 2 );

/**
 * Bump the cache version when a category term is deleted.
 *
 * @param int    $term_id      Term ID (unused).
 * @param int    $tt_id        Term taxonomy ID (unused).
 * @param string $taxonomy     Taxonomy slug.
 * @param mixed  $deleted_term Copy of the deleted term object (unused).
 * @return void
 */
function brap_clear_cache_on_term_delete( $term_id, $tt_id, $taxonomy, $deleted_term ) {
	unset( $term_id, $tt_id, $deleted_term );
	if ( 'category' === $taxonomy ) {
		brap_bump_cache_version();
	}
}
add_action( 'delete_term', 'brap_clear_cache_on_term_delete', 10, 4 );

/**
 * Format a "(N posts)" count label for headings.
 *
 * @param int $count Number of posts.
 * @return string Localized, escaped-safe label including a leading space.
 */
function brap_count_label( $count ) {
	$count = (int) $count;
	/* translators: %s: number of posts */
	$label = str_replace( '%s', number_format_i18n( $count ), _n( '%s post', '%s posts', $count, 'all-posts-archive-page' ) );
	return ' (' . $label . ')';
}

/**
 * Generates the month-by-month post listing using stored settings and optional overrides.
 *
 * Shortcode: [binge_archive category="news" post_type="post" order="ASC"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output of the binge reading archive.
 */
function brap_display_posts_by_month( $atts = array() ) {
	// Shortcode attributes.
	$atts = shortcode_atts(
		array(
			'category'  => '',
			'post_type' => '',
			'order'     => '',
		),
		$atts,
		'binge_archive'
	);

	// Resolve effective inputs (validated) for the query and the cache key.
	$category_slug = brap_get_effective_category_slug( is_string( $atts['category'] ) ? $atts['category'] : '' );
	$post_type     = brap_get_effective_post_type( is_string( $atts['post_type'] ) ? $atts['post_type'] : '' );
	$order         = brap_get_effective_order( is_string( $atts['order'] ) ? $atts['order'] : '' );

	// Caching settings.
	$enable_cache   = brap_get_setting( 'enable_cache' );
	$cache_duration = brap_get_setting( 'cache_duration' );
	$enable_cache   = $enable_cache ? $enable_cache : 'on';
	$cache_duration = $cache_duration ? absint( $cache_duration ) : 43200;

	// Only build a cache key when caching is actually enabled. Computing it
	// (which reads the cache-version option and hashes a JSON payload) is
	// wasted work on every render for sites that have turned caching off.
	$cache_key = '';

	if ( 'on' === $enable_cache ) {
		// Cache key varies only by what actually changes the output per instance.
		// Settings/post changes bump the cache version, so they need not be keyed.
		$cache_key = 'brap_archive_' . md5(
			wp_json_encode(
				array(
					'v'         => brap_get_cache_version(),
					'category'  => $category_slug,
					'post_type' => $post_type,
					'order'     => $order,
					'locale'    => get_locale(),
				)
			)
		);

		$cached_output = get_transient( $cache_key );
		if ( false !== $cached_output ) {
			return $cached_output;
		}
	}

	// Fetch display settings (one memoized DB read backs all of these).
	$add_year_header    = brap_get_setting( 'add_year_header' );
	$year_header_level  = brap_get_setting( 'year_header_level' );
	$add_month_header   = brap_get_setting( 'add_month_header' );
	$month_header_level = brap_get_setting( 'month_header_level' );
	$show_year_in_month = brap_get_setting( 'show_year_in_month_header' );
	$month_format       = brap_get_setting( 'month_format' );
	$year_format        = brap_get_setting( 'year_format' );
	$show_post_date     = brap_get_setting( 'show_post_date' );
	$post_date_format   = brap_get_setting( 'post_date_format' );
	$show_year_nav      = brap_get_setting( 'show_year_nav' );
	$show_post_count    = brap_get_setting( 'show_post_count' );

	// Fallback defaults.
	$add_year_header    = $add_year_header ? $add_year_header : 'off';
	$year_header_level  = $year_header_level ? $year_header_level : 'h2';
	$add_month_header   = $add_month_header ? $add_month_header : 'on';
	$month_header_level = $month_header_level ? $month_header_level : 'h3';
	$show_year_in_month = $show_year_in_month ? $show_year_in_month : 'on';
	$month_format       = $month_format ? $month_format : 'MMM';
	$year_format        = $year_format ? $year_format : 'YYYY';
	$show_post_date     = $show_post_date ? $show_post_date : 'on';
	$show_year_nav      = $show_year_nav ? $show_year_nav : 'off';
	$show_post_count    = $show_post_count ? $show_post_count : 'off';

	// Separator: false (unset) keeps the historical default; '' is honored if explicitly saved.
	$settings  = brap_get_all_settings();
	$separator = isset( $settings['date_title_separator'] ) ? $settings['date_title_separator'] : ' - ';

	// Date format for each post: empty means use the site's configured format.
	$post_date_format = ( is_string( $post_date_format ) && '' !== $post_date_format )
		? $post_date_format
		: get_option( 'date_format' );
	if ( ! is_string( $post_date_format ) || '' === $post_date_format ) {
		$post_date_format = 'F j, Y';
	}

	// Validate select fields against allowed lists.
	$allowed_heading_levels = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	if ( ! in_array( $year_header_level, $allowed_heading_levels, true ) ) {
		$year_header_level = 'h2';
	}
	if ( ! in_array( $month_header_level, $allowed_heading_levels, true ) ) {
		$month_header_level = 'h3';
	}

	// Convert to date() tokens used by WP's get_the_date().
	$tokens = brap_get_date_tokens( $month_format, $year_format );

	// Build WP_Query args.
	$query_args = array(
		'post_type'              => $post_type,
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'order'                  => $order,
		'orderby'                => 'date',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	);

	// Category filtering applies to standard posts (the "category" taxonomy).
	if ( '' !== $category_slug && 'post' === $post_type ) {
		$query_args['category_name'] = $category_slug;
	}

	$post_items = new WP_Query( $query_args );

	// Group posts into years -> months, preserving query order.
	$years = array();

	if ( $post_items->have_posts() ) {
		while ( $post_items->have_posts() ) {
			$post_items->the_post();

			$year_num  = get_the_date( 'Y' ); // 4-digit numeric for grouping/anchors.
			$month_num = get_the_date( 'n' ); // 1-12 for grouping.

			if ( ! isset( $years[ $year_num ] ) ) {
				$years[ $year_num ] = array(
					'display' => get_the_date( $tokens['year'] ),
					'count'   => 0,
					'months'  => array(),
				);
			}

			if ( ! isset( $years[ $year_num ]['months'][ $month_num ] ) ) {
				$years[ $year_num ]['months'][ $month_num ] = array(
					'display' => get_the_date( $tokens['month'] ),
					'posts'   => array(),
				);
			}

			$years[ $year_num ]['months'][ $month_num ]['posts'][] = array(
				'url'   => get_the_permalink(),
				'title' => get_the_title(),
				'date'  => get_the_date( $post_date_format ),
			);
			++$years[ $year_num ]['count'];
		}

		wp_reset_postdata();
	}

	if ( empty( $years ) ) {
		$output = ( '' !== $category_slug )
			? '<p>' . esc_html__( 'No posts found in the selected category.', 'all-posts-archive-page' ) . '</p>'
			: '<p>' . esc_html__( 'No posts found.', 'all-posts-archive-page' ) . '</p>';

		if ( 'on' === $enable_cache ) {
			set_transient( $cache_key, $output, $cache_duration );
		}

		return $output;
	}

	ob_start();

	// Optional jump-to-year navigation (needs year headings for anchor targets).
	if ( 'on' === $show_year_nav && 'on' === $add_year_header ) {
		echo '<nav class="binge-archive-nav" aria-label="' . esc_attr__( 'Jump to year', 'all-posts-archive-page' ) . '"><ul>';
		foreach ( $years as $year_num => $year_data ) {
			printf(
				'<li><a href="#binge-archive-year-%1$s">%2$s</a></li>',
				esc_attr( $year_num ),
				esc_html( $year_data['display'] )
			);
		}
		echo '</ul></nav>';
	}

	foreach ( $years as $year_num => $year_data ) {

		if ( 'on' === $add_year_header ) {
			$year_label = $year_data['display'];
			if ( 'on' === $show_post_count ) {
				$year_label .= brap_count_label( $year_data['count'] );
			}

			echo '<section class="binge-archive-year">';
			printf(
				'<%1$s id="binge-archive-year-%2$s">%3$s</%1$s>',
				esc_html( $year_header_level ),
				esc_attr( $year_num ),
				esc_html( $year_label )
			);
			echo '</section>';
		}

		foreach ( $year_data['months'] as $month_data ) {
			echo '<section class="binge-archive-month">';

			if ( 'on' === $add_month_header ) {
				if ( 'on' === $show_year_in_month ) {
					$month_heading = $month_data['display'] . ' ' . $year_data['display'];
				} else {
					$month_heading = $month_data['display'];
				}

				if ( 'on' === $show_post_count ) {
					$month_heading .= brap_count_label( count( $month_data['posts'] ) );
				}

				echo '<' . esc_html( $month_header_level ) . '>' . esc_html( $month_heading ) . '</' . esc_html( $month_header_level ) . '>';
			}

			echo '<ul>';

			foreach ( $month_data['posts'] as $post_data ) {
				if ( 'on' === $show_post_date ) {
					printf(
						'<li><a href="%1$s"><span class="archive_post_date binge-archive-post-date">%2$s%3$s</span>%4$s</a></li>',
						esc_url( $post_data['url'] ),
						esc_html( $post_data['date'] ),
						esc_html( $separator ),
						esc_html( $post_data['title'] )
					);
				} else {
					printf(
						'<li><a href="%1$s">%2$s</a></li>',
						esc_url( $post_data['url'] ),
						esc_html( $post_data['title'] )
					);
				}
			}

			echo '</ul></section>';
		}
	}

	$output = ob_get_clean();

	if ( 'on' === $enable_cache && '' !== $output ) {
		set_transient( $cache_key, $output, $cache_duration );
	}

	return $output;
}

/**
 * Registers a shortcode [binge_archive] that displays the archive.
 */
function brap_init_shortcodes() {
	add_shortcode( 'binge_archive', 'brap_shortcode_handler' );
}

/**
 * Shortcode handler: render the archive, but never let an unexpected error
 * crash the page or trip WordPress's fatal-error protection.
 *
 * @param array $atts Shortcode attributes.
 * @return string Archive HTML, or an empty string on error.
 */
function brap_shortcode_handler( $atts = array() ) {
	try {
		return brap_display_posts_by_month( $atts );
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Binge Reading Archive Page render error: ' . $e->getMessage() );
		}
		return '';
	}
}
add_action( 'init', 'brap_init_shortcodes' );

/**
 * Add settings link to plugin list page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function brap_add_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=all-posts-archive-page' ) ) . '">' . esc_html__( 'Settings', 'all-posts-archive-page' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'brap_add_settings_link' );

/**
 * Adds a settings page under "Settings" with our options.
 */
add_action( 'admin_menu', 'brap_plugin_menu' );
function brap_plugin_menu() {
	add_options_page(
		__( 'Binge Reading Archive Settings', 'all-posts-archive-page' ),
		__( 'Binge Reading Archive', 'all-posts-archive-page' ),
		'manage_options',
		'all-posts-archive-page',
		'brap_admin_page'
	);
}

/**
 * Admin settings page.
 */
function brap_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'all-posts-archive-page' ) );
	}

	try {
		ob_start();
		brap_render_admin_page();
		echo ob_get_clean();
	} catch ( \Throwable $e ) {
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Binge Reading Archive Page settings error: ' . $e->getMessage() );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Binge Reading Archive Settings', 'all-posts-archive-page' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'The settings page hit an unexpected error and could not fully load. Your site is unaffected. Please reload the page.', 'all-posts-archive-page' ) . '</p></div></div>';
	}
}

/**
 * Render the admin settings page body. Wrapped by brap_admin_page() so a
 * stray error can't pause the plugin.
 */
function brap_render_admin_page() {

	// Process form submission with nonce.
	if (
		isset( $_POST['brap_save_settings'] ) &&
		isset( $_POST['brap_save_settings_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['brap_save_settings_nonce'] ) ), 'brap_save_settings_action' )
	) {
		$add_year_header     = isset( $_POST['add_year_header'] ) ? 'on' : 'off';
		$add_month_header    = isset( $_POST['add_month_header'] ) ? 'on' : 'off';
		$show_year_in_month  = isset( $_POST['show_year_in_month_header'] ) ? 'on' : 'off';
		$show_post_date      = isset( $_POST['show_post_date'] ) ? 'on' : 'off';
		$show_year_nav       = isset( $_POST['show_year_nav'] ) ? 'on' : 'off';
		$show_post_count     = isset( $_POST['show_post_count'] ) ? 'on' : 'off';
		$enable_cache        = isset( $_POST['enable_cache'] ) ? 'on' : 'off';
		$remove_on_uninstall = isset( $_POST['remove_db_table_on_uninstall'] ) ? 'yes' : 'no';

		$year_header_level  = isset( $_POST['year_header_level'] ) ? sanitize_text_field( wp_unslash( $_POST['year_header_level'] ) ) : 'h2';
		$month_header_level = isset( $_POST['month_header_level'] ) ? sanitize_text_field( wp_unslash( $_POST['month_header_level'] ) ) : 'h3';

		$month_format = isset( $_POST['month_format'] ) ? sanitize_text_field( wp_unslash( $_POST['month_format'] ) ) : 'MMM';
		$year_format  = isset( $_POST['year_format'] ) ? sanitize_text_field( wp_unslash( $_POST['year_format'] ) ) : 'YYYY';

		$post_order = isset( $_POST['post_order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['post_order'] ) ) ) : 'DESC';
		$post_order = ( 'ASC' === $post_order ) ? 'ASC' : 'DESC';

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';
		$pt_obj    = get_post_type_object( $post_type );
		if ( ! $pt_obj || empty( $pt_obj->public ) ) {
			$post_type = 'post';
		}

		// Date format: keep it as a date() pattern; strip tags but preserve spacing.
		$post_date_format = isset( $_POST['post_date_format'] ) ? wp_strip_all_tags( wp_unslash( $_POST['post_date_format'] ) ) : '';
		$post_date_format = substr( $post_date_format, 0, 50 );

		// Separator: strip tags but DO NOT trim (leading/trailing spaces are meaningful).
		$date_title_separator = isset( $_POST['date_title_separator'] ) ? wp_kses( wp_unslash( $_POST['date_title_separator'] ), array() ) : ' - ';
		$date_title_separator = substr( $date_title_separator, 0, 20 );

		$cache_duration = isset( $_POST['cache_duration'] ) ? absint( wp_unslash( $_POST['cache_duration'] ) ) : 43200;
		if ( $cache_duration < 300 ) {
			$cache_duration = 300; // Minimum 5 minutes.
		}
		if ( $cache_duration > 604800 ) {
			$cache_duration = 604800; // Maximum 7 days.
		}

		$allowed_heading_levels = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
		if ( ! in_array( $year_header_level, $allowed_heading_levels, true ) ) {
			$year_header_level = 'h2';
		}
		if ( ! in_array( $month_header_level, $allowed_heading_levels, true ) ) {
			$month_header_level = 'h3';
		}

		$allowed_month_formats = array( 'MM', 'MMM', 'M', 'MMMM' );
		if ( ! in_array( $month_format, $allowed_month_formats, true ) ) {
			$month_format = 'MMM';
		}

		$allowed_year_formats = array( 'YY', 'YYYY' );
		if ( ! in_array( $year_format, $allowed_year_formats, true ) ) {
			$year_format = 'YYYY';
		}

		// Category filter from a dropdown of categories (slug).
		$category_filter_slug = '';
		if ( isset( $_POST['category_filter'] ) ) {
			$category_filter_slug = sanitize_title( wp_unslash( $_POST['category_filter'] ) );
			if ( '' !== $category_filter_slug ) {
				$term = get_term_by( 'slug', $category_filter_slug, 'category' );
				if ( ! $term || is_wp_error( $term ) ) {
					$category_filter_slug = '';
				}
			}
		}

		brap_update_setting( 'add_year_header', $add_year_header );
		brap_update_setting( 'year_header_level', $year_header_level );
		brap_update_setting( 'add_month_header', $add_month_header );
		brap_update_setting( 'month_header_level', $month_header_level );
		brap_update_setting( 'show_year_in_month_header', $show_year_in_month );
		brap_update_setting( 'month_format', $month_format );
		brap_update_setting( 'year_format', $year_format );
		brap_update_setting( 'post_order', $post_order );
		brap_update_setting( 'post_type', $post_type );
		brap_update_setting( 'post_date_format', $post_date_format );
		brap_update_setting( 'date_title_separator', $date_title_separator );
		brap_update_setting( 'show_year_nav', $show_year_nav );
		brap_update_setting( 'show_post_count', $show_post_count );
		brap_update_setting( 'show_post_date', $show_post_date );
		brap_update_setting( 'enable_cache', $enable_cache );
		brap_update_setting( 'cache_duration', (string) $cache_duration );
		brap_update_setting( 'remove_db_table_on_uninstall', $remove_on_uninstall );
		brap_update_setting( 'category_filter', $category_filter_slug );

		// Invalidate cached output now that settings changed.
		brap_bump_cache_version();

		echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'all-posts-archive-page' ) . '</p></div>';
	}

	// Fetch current settings.
	$add_year_header       = brap_get_setting( 'add_year_header' );
	$year_header_level     = brap_get_setting( 'year_header_level' );
	$add_month_header      = brap_get_setting( 'add_month_header' );
	$month_header_level    = brap_get_setting( 'month_header_level' );
	$show_year_in_month    = brap_get_setting( 'show_year_in_month_header' );
	$month_format          = brap_get_setting( 'month_format' );
	$year_format           = brap_get_setting( 'year_format' );
	$post_order            = brap_get_setting( 'post_order' );
	$post_type_setting     = brap_get_setting( 'post_type' );
	$show_post_date        = brap_get_setting( 'show_post_date' );
	$show_year_nav         = brap_get_setting( 'show_year_nav' );
	$show_post_count       = brap_get_setting( 'show_post_count' );
	$enable_cache          = brap_get_setting( 'enable_cache' );
	$cache_duration        = brap_get_setting( 'cache_duration' );
	$remove_data           = brap_get_setting( 'remove_db_table_on_uninstall' );
	$category_filter_value = brap_get_setting( 'category_filter' );

	// Separator/date format can legitimately be empty, so read them raw.
	$settings_raw         = brap_get_all_settings();
	$date_title_separator = isset( $settings_raw['date_title_separator'] ) ? $settings_raw['date_title_separator'] : ' - ';
	$post_date_format     = isset( $settings_raw['post_date_format'] ) ? $settings_raw['post_date_format'] : '';

	// Set defaults if not set.
	$enable_cache       = $enable_cache ? $enable_cache : 'on';
	$cache_duration     = $cache_duration ? absint( $cache_duration ) : 43200;
	$show_year_in_month = $show_year_in_month ? $show_year_in_month : 'on';
	$post_order         = $post_order ? $post_order : 'DESC';
	$post_type_setting  = $post_type_setting ? $post_type_setting : 'post';
	$show_year_nav      = $show_year_nav ? $show_year_nav : 'off';
	$show_post_count    = $show_post_count ? $show_post_count : 'off';

	// Categories list for dropdown.
	$categories = get_categories(
		array(
			'hide_empty' => false,
		)
	);

	// Public post types for dropdown.
	$post_type_objects = get_post_types( array( 'public' => true ), 'objects' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Binge Reading Archive Settings', 'all-posts-archive-page' ); ?></h1>

		<form method="POST" action="">
			<?php wp_nonce_field( 'brap_save_settings_action', 'brap_save_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'Content', 'all-posts-archive-page' ); ?></h2>

			<p>
				<label for="post_type"><?php esc_html_e( 'Post Type', 'all-posts-archive-page' ); ?></label><br />
				<select name="post_type" id="post_type">
					<?php foreach ( $post_type_objects as $pt ) : ?>
						<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $post_type_setting, $pt->name ); ?>>
							<?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Choose which content type to list. Defaults to standard posts. Category filtering applies to standard posts only.', 'all-posts-archive-page' ); ?></p>

			<p>
				<label for="post_order"><?php esc_html_e( 'Sort Order', 'all-posts-archive-page' ); ?></label><br />
				<select name="post_order" id="post_order">
					<option value="DESC" <?php selected( $post_order, 'DESC' ); ?>><?php esc_html_e( 'Newest first (DESC)', 'all-posts-archive-page' ); ?></option>
					<option value="ASC" <?php selected( $post_order, 'ASC' ); ?>><?php esc_html_e( 'Oldest first (ASC)', 'all-posts-archive-page' ); ?></option>
				</select>
			</p>

			<p>
				<label for="category_filter"><?php esc_html_e( 'Default Category (Optional)', 'all-posts-archive-page' ); ?></label><br />
				<select name="category_filter" id="category_filter">
					<option value=""><?php esc_html_e( '— All categories —', 'all-posts-archive-page' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $category_filter_value, $cat->slug ); ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description">
				<?php esc_html_e( 'Select a default category to filter standard posts. Leave as "All categories" to include every post. Override per page with the shortcode, e.g. [binge_archive category="news"].', 'all-posts-archive-page' ); ?>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Year Header Settings', 'all-posts-archive-page' ); ?></h2>
			<label for="add_year_header">
				<input type="checkbox" name="add_year_header" id="add_year_header" <?php checked( $add_year_header, 'on' ); ?> />
				<?php esc_html_e( 'Add a heading for each year?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, a heading will appear for each new year (e.g., 2023).', 'all-posts-archive-page' ); ?></p>

			<p>
				<label for="year_header_level">
					<?php esc_html_e( 'Year Heading Level (H1‒H6)', 'all-posts-archive-page' ); ?>
				</label>
				<select name="year_header_level" id="year_header_level">
					<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $year_header_level, $level ); ?>>
							<?php echo esc_html( strtoupper( $level ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'For accessibility, pick a level one step below your page title (most themes use H1 for the title, so H2 here). Avoid skipping levels.', 'all-posts-archive-page' ); ?></p>

			<label for="show_year_nav">
				<input type="checkbox" name="show_year_nav" id="show_year_nav" <?php checked( $show_year_nav, 'on' ); ?> />
				<?php esc_html_e( 'Show a jump-to-year navigation list at the top?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Adds quick links to each year. Requires the year heading above to be turned on.', 'all-posts-archive-page' ); ?></p>

			<hr />

			<h2><?php esc_html_e( 'Month Header Settings', 'all-posts-archive-page' ); ?></h2>
			<label for="add_month_header">
				<input type="checkbox" name="add_month_header" id="add_month_header" <?php checked( $add_month_header, 'on' ); ?> />
				<?php esc_html_e( 'Add a heading for each month?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'If checked, a heading will appear for each new month, using your chosen format (e.g., 08 2023, Aug 2023, 8 2023, August 2023).', 'all-posts-archive-page' ); ?>
			</p>

			<p>
				<label for="month_header_level">
					<?php esc_html_e( 'Month Heading Level (H1‒H6)', 'all-posts-archive-page' ); ?>
				</label>
				<select name="month_header_level" id="month_header_level">
					<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $month_header_level, $level ); ?>>
							<?php echo esc_html( strtoupper( $level ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Keep months one level below your year heading (for example, year H2 and month H3) so screen readers can follow the outline.', 'all-posts-archive-page' ); ?></p>

			<label for="show_year_in_month_header">
				<input type="checkbox" name="show_year_in_month_header" id="show_year_in_month_header" <?php checked( $show_year_in_month, 'on' ); ?> />
				<?php esc_html_e( 'Include the year in each month heading?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Uncheck to show only the month name (e.g., “January” instead of “January 2026”). Useful when you also turn on the year heading above.', 'all-posts-archive-page' ); ?>
			</p>

			<p>
				<label for="month_format">
					<?php esc_html_e( 'Month Format', 'all-posts-archive-page' ); ?>
				</label>
				<select name="month_format" id="month_format">
					<option value="MM" <?php selected( $month_format, 'MM' ); ?>><?php esc_html_e( 'MM (e.g. 01)', 'all-posts-archive-page' ); ?></option>
					<option value="MMM" <?php selected( $month_format, 'MMM' ); ?>><?php esc_html_e( 'MMM (e.g. Jan)', 'all-posts-archive-page' ); ?></option>
					<option value="M" <?php selected( $month_format, 'M' ); ?>><?php esc_html_e( 'M (e.g. 1)', 'all-posts-archive-page' ); ?></option>
					<option value="MMMM" <?php selected( $month_format, 'MMMM' ); ?>><?php esc_html_e( 'MMMM (e.g. January)', 'all-posts-archive-page' ); ?></option>
				</select>
			</p>

			<p>
				<label for="year_format">
					<?php esc_html_e( 'Year Format', 'all-posts-archive-page' ); ?>
				</label>
				<select name="year_format" id="year_format">
					<option value="YY" <?php selected( $year_format, 'YY' ); ?>><?php esc_html_e( 'YY (e.g. 23)', 'all-posts-archive-page' ); ?></option>
					<option value="YYYY" <?php selected( $year_format, 'YYYY' ); ?>><?php esc_html_e( 'YYYY (e.g. 2023)', 'all-posts-archive-page' ); ?></option>
				</select>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Post Counts', 'all-posts-archive-page' ); ?></h2>
			<label for="show_post_count">
				<input type="checkbox" name="show_post_count" id="show_post_count" <?php checked( $show_post_count, 'on' ); ?> />
				<?php esc_html_e( 'Show post counts next to year and month headings?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Appends a count to each heading, e.g., “August 2023 (12 posts)”.', 'all-posts-archive-page' ); ?></p>

			<hr />

			<h2><?php esc_html_e( 'Post Date Display', 'all-posts-archive-page' ); ?></h2>
			<label for="show_post_date">
				<input type="checkbox" name="show_post_date" id="show_post_date" <?php checked( $show_post_date, 'on' ); ?> />
				<?php esc_html_e( 'Show post dates in the list?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, each post shows its publication date before the title (e.g., "January 15, 2023 - Post Title").', 'all-posts-archive-page' ); ?></p>

			<p>
				<label for="post_date_format"><?php esc_html_e( 'Post Date Format', 'all-posts-archive-page' ); ?></label><br />
				<input type="text" name="post_date_format" id="post_date_format" value="<?php echo esc_attr( $post_date_format ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'date_format' ) ); ?>" />
			</p>
			<p class="description">
				<?php
				$brap_date_help = __( 'PHP <code>date()</code> format for each post. Leave blank to use your site\'s date format.', 'all-posts-archive-page' )
					. ' <a href="https://www.php.net/manual/en/datetime.format.php" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Format reference', 'all-posts-archive-page' )
					. '</a>';
				echo wp_kses(
					$brap_date_help,
					array(
						'code' => array(),
						'a'    => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				);
				?>
			</p>

			<p>
				<label for="date_title_separator"><?php esc_html_e( 'Date / Title Separator', 'all-posts-archive-page' ); ?></label><br />
				<input type="text" name="date_title_separator" id="date_title_separator" value="<?php echo esc_attr( $date_title_separator ); ?>" class="regular-text" />
			</p>
			<p class="description"><?php esc_html_e( 'Printed between the date and title when dates are shown. Spaces are preserved (default is " - ").', 'all-posts-archive-page' ); ?></p>

			<hr />

			<h2><?php esc_html_e( 'Performance & Caching', 'all-posts-archive-page' ); ?></h2>
			<label for="enable_cache">
				<input type="checkbox" name="enable_cache" id="enable_cache" <?php checked( $enable_cache, 'on' ); ?> />
				<?php esc_html_e( 'Enable output caching for faster page loads?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'When enabled, the archive output is cached to improve performance. Cache is automatically cleared when posts are published, updated, or deleted.', 'all-posts-archive-page' ); ?></p>

			<p>
				<label for="cache_duration">
					<?php esc_html_e( 'Cache Duration (seconds)', 'all-posts-archive-page' ); ?>
				</label><br />
				<input type="number" name="cache_duration" id="cache_duration" value="<?php echo esc_attr( $cache_duration ); ?>" min="300" max="604800" step="300" />
				<br />
				<span class="description">
					<?php
					/* translators: %s: number of hours */
					echo esc_html( str_replace( '%s', number_format( $cache_duration / 3600, 1 ), __( 'Current setting: %s hours. Minimum: 5 minutes (300), Maximum: 7 days (604800).', 'all-posts-archive-page' ) ) );
					?>
				</span>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Data Removal at Uninstall', 'all-posts-archive-page' ); ?></h2>
			<label for="remove_db_table_on_uninstall">
				<input type="checkbox" name="remove_db_table_on_uninstall" id="remove_db_table_on_uninstall" <?php checked( $remove_data, 'yes' ); ?> />
				<?php esc_html_e( 'Permanently remove plugin settings from the database upon uninstall?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, the settings table will be deleted when you uninstall the plugin.', 'all-posts-archive-page' ); ?></p>

			<hr />

			<?php submit_button( __( 'Save Settings', 'all-posts-archive-page' ), 'primary', 'brap_save_settings' ); ?>
		</form>

		<p>
			<?php
			echo esc_html__(
				'Use the shortcode on any page or post to display a reverse-chronological archive of posts (optionally filtered by category), grouped by month and optionally by year. Your theme’s styling applies automatically.',
				'all-posts-archive-page'
			);
			?>
		</p>

		<p><?php esc_html_e( 'Shortcode examples:', 'all-posts-archive-page' ); ?></p>
		<code>[binge_archive]</code><br/>
		<code>[binge_archive category="news"]</code><br/>
		<code>[binge_archive post_type="page" order="ASC"]</code>

		<hr />
		<ul>
			<li>
				<?php esc_html_e( 'Submit a Bug or Feature Request:', 'all-posts-archive-page' ); ?>
				<a href="https://wordpress.org/support/plugin/all-posts-archive-page" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'WordPress.org Forums', 'all-posts-archive-page' ); ?>
				</a>
			</li>
			<li>
				<?php esc_html_e( 'Connect with the author around the web:', 'all-posts-archive-page' ); ?>
				<a href="https://eric.money/" target="_blank" rel="noopener noreferrer">https://eric.money/</a>
			</li>
		</ul>
	</div>
	<?php
}
