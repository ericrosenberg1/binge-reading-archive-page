<?php
/**
 * Plugin Name: Binge Reading Archive Page
 * Plugin URI:  https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/
 * Description: Display all posts month-by-month for binge reading. Uses your theme's styling by default. Supports optional category filtering and flexible month formats.
 * Version:     0.60
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.8
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

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		setting_name varchar(50) NOT NULL,
		setting_value varchar(50) NOT NULL,
		UNIQUE KEY id (id),
		UNIQUE KEY setting_name (setting_name)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Insert default settings if they don't already exist.
	$default_settings = array(
		'add_year_header'              => 'off',
		'year_header_level'            => 'h2',
		'add_month_header'             => 'on',
		'month_header_level'           => 'h3',
		// Month format options: MM (01), MMM (Aug), M (8), MMMM (August).
		'month_format'                 => 'MMM',
		// Year format options: YY (23), YYYY (2023).
		'year_format'                  => 'YYYY',
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
}

/**
 * Uninstall Hook: Allows table deletion if user chooses so in plugin settings.
 */
register_uninstall_hook( __FILE__, 'brap_uninstall' );
function brap_uninstall() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'brap_settings';

	// Check user’s preference in our table for removing data.
	$remove_data = $wpdb->get_var(
		$wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_name = %s", 'remove_db_table_on_uninstall' )
	);

	// If user has chosen 'yes', drop the table.
	if ( 'yes' === $remove_data ) {
		// dbDelta() cannot drop; direct query required.
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Helper: get setting from custom table.
 *
 * @param string $name Setting name.
 * @return string|false
 */
function brap_get_setting( $name ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'brap_settings';

	$value = $wpdb->get_var(
		$wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_name = %s", $name )
	);

	return ( '' !== $value && null !== $value ) ? $value : false;
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
 * Clear the archive cache.
 *
 * @return void
 */
function brap_clear_cache() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_brap_archive_%' OR option_name LIKE '_transient_timeout_brap_archive_%'" );
}

/**
 * Clear cache when a post is published, updated, or deleted.
 *
 * @return void
 */
function brap_clear_cache_on_post_change() {
	brap_clear_cache();
}
add_action( 'save_post', 'brap_clear_cache_on_post_change' );
add_action( 'delete_post', 'brap_clear_cache_on_post_change' );
add_action( 'wp_trash_post', 'brap_clear_cache_on_post_change' );

/**
 * Generates the month-by-month post listing using stored settings and optional category filter.
 *
 * Shortcode: [binge_archive category="news"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output of the binge reading archive.
 */
function brap_display_posts_by_month( $atts = array() ) {
	// Shortcode attributes (category is a slug).
	$atts = shortcode_atts(
		array(
			'category' => '',
		),
		$atts,
		'binge_archive'
	);

	// Determine effective category filter (slug) with validation first for cache key.
	$requested_slug = '';
	if ( isset( $atts['category'] ) ) {
		$requested_slug = is_string( $atts['category'] ) ? $atts['category'] : '';
	}
	$category_slug = brap_get_effective_category_slug( $requested_slug );

	// Check if caching is enabled.
	$enable_cache   = brap_get_setting( 'enable_cache' );
	$cache_duration = brap_get_setting( 'cache_duration' );
	$enable_cache   = $enable_cache ? $enable_cache : 'on';
	$cache_duration = $cache_duration ? absint( $cache_duration ) : 43200;

	// Generate unique cache key based on settings and category.
	$cache_key = 'brap_archive_' . md5( serialize( $atts ) . get_locale() );

	// Try to get cached output if caching is enabled.
	if ( 'on' === $enable_cache ) {
		$cached_output = get_transient( $cache_key );
		if ( false !== $cached_output ) {
			return $cached_output;
		}
	}

	// Fetch user settings from our table.
	$add_year_header    = brap_get_setting( 'add_year_header' );
	$year_header_level  = brap_get_setting( 'year_header_level' );
	$add_month_header   = brap_get_setting( 'add_month_header' );
	$month_header_level = brap_get_setting( 'month_header_level' );
	$month_format       = brap_get_setting( 'month_format' ); // 'MM', 'MMM', 'M', 'MMMM'.
	$year_format        = brap_get_setting( 'year_format' );  // 'YY' or 'YYYY'.
	$show_post_date     = brap_get_setting( 'show_post_date' );

	// Fallback defaults.
	$add_year_header    = $add_year_header ? $add_year_header : 'off';
	$year_header_level  = $year_header_level ? $year_header_level : 'h2';
	$add_month_header   = $add_month_header ? $add_month_header : 'on';
	$month_header_level = $month_header_level ? $month_header_level : 'h3';
	$month_format       = $month_format ? $month_format : 'MMM';
	$year_format        = $year_format ? $year_format : 'YYYY';
	$show_post_date     = $show_post_date ? $show_post_date : 'on';

	// Validate select fields against allowed lists.
	$allowed_heading_levels = array( 'h1','h2','h3','h4','h5','h6' );
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
		'post_type'              => 'post',
		'posts_per_page'         => -1,
		'order'                  => 'DESC',
		'orderby'                => 'date',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	);

	if ( '' !== $category_slug ) {
		// Filter by category slug.
		$query_args['category_name'] = $category_slug;
	}

	$post_items = new WP_Query( $query_args );

	// Track last month/year so we know when to start new sections/headings.
	$current_month = '';
	$current_year  = '';
	$output        = '';

	if ( $post_items->have_posts() ) {
		ob_start();

		while ( $post_items->have_posts() ) {
			$post_items->the_post();

			$post_year     = get_the_date( 'Y' );   // numeric 4-digit for grouping.
			$post_month    = get_the_date( 'n' );   // 1-12 for grouping.
			$display_year  = get_the_date( $tokens['year'] );  // 'y' or 'Y'.
			$display_month = get_the_date( $tokens['month'] ); // 'm', 'M', 'n', or 'F'.

			// If the year changes, close old section/UL if needed, then possibly print a new year heading.
			if ( $post_year !== $current_year ) {
				if ( '' !== $current_year ) {
					echo '</ul></section>'; // Close previous month section.
				}

				if ( 'on' === $add_year_header ) {
					echo '<section class="binge-archive-year">';
					echo '<' . esc_html( $year_header_level ) . '>' . esc_html( $display_year ) . '</' . esc_html( $year_header_level ) . '>';
					echo '</section>';
				}

				$current_year  = $post_year;
				$current_month = '';
			}

			// If the month changes, close old UL if needed and print a new month heading if turned on.
			if ( $post_month !== $current_month ) {
				if ( '' !== $current_month ) {
					echo '</ul></section>';
				}

				echo '<section class="binge-archive-month">';

				if ( 'on' === $add_month_header ) {
					/* translators: 1: month, 2: year */
					$month_year = sprintf( __( '%1$s %2$s', 'all-posts-archive-page' ), $display_month, $display_year );
					echo '<' . esc_html( $month_header_level ) . '>' . esc_html( $month_year ) . '</' . esc_html( $month_header_level ) . '>';
				}

				echo '<ul>';

				$current_month = $post_month;
			}

			// List each post with date and title.
			if ( 'on' === $show_post_date ) {
				printf(
					'<li><a href="%1$s"><span class="archive_post_date">%2$s - </span>%3$s</a></li>',
					esc_url( get_the_permalink() ),
					esc_html( get_the_date( 'F j, Y' ) ),
					esc_html( get_the_title() )
				);
			} else {
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( get_the_permalink() ),
					esc_html( get_the_title() )
				);
			}
		}

		// Close final UL and section after the loop ends.
		echo '</ul></section>';

		wp_reset_postdata();
		$output = ob_get_clean();
	} else {
		// Helpful message when no posts (especially when filtering by category).
		$output = ( '' !== $category_slug )
			? '<p>' . esc_html__( 'No posts found in the selected category.', 'all-posts-archive-page' ) . '</p>'
			: '<p>' . esc_html__( 'No posts found.', 'all-posts-archive-page' ) . '</p>';
	}

	// Store in cache if caching is enabled.
	if ( 'on' === $enable_cache && '' !== $output ) {
		set_transient( $cache_key, $output, $cache_duration );
	}

	return $output;
}

/**
 * Registers a shortcode [binge_archive] that displays the archive.
 */
function brap_init_shortcodes() {
	add_shortcode( 'binge_archive', 'brap_display_posts_by_month' );
}
add_action( 'init', 'brap_init_shortcodes' );

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

	// Process form submission with nonce.
	if (
		isset( $_POST['brap_save_settings'] ) &&
		isset( $_POST['brap_save_settings_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['brap_save_settings_nonce'] ) ), 'brap_save_settings_action' )
	) {
		$add_year_header     = isset( $_POST['add_year_header'] ) ? 'on' : 'off';
		$add_month_header    = isset( $_POST['add_month_header'] ) ? 'on' : 'off';
		$show_post_date      = isset( $_POST['show_post_date'] ) ? 'on' : 'off';
		$enable_cache        = isset( $_POST['enable_cache'] ) ? 'on' : 'off';
		$remove_on_uninstall = isset( $_POST['remove_db_table_on_uninstall'] ) ? 'yes' : 'no';

		$year_header_level = isset( $_POST['year_header_level'] ) ? sanitize_text_field( wp_unslash( $_POST['year_header_level'] ) ) : 'h2';
		$month_header_level = isset( $_POST['month_header_level'] ) ? sanitize_text_field( wp_unslash( $_POST['month_header_level'] ) ) : 'h3';

		$month_format = isset( $_POST['month_format'] ) ? sanitize_text_field( wp_unslash( $_POST['month_format'] ) ) : 'MMM';
		$year_format  = isset( $_POST['year_format'] ) ? sanitize_text_field( wp_unslash( $_POST['year_format'] ) ) : 'YYYY';

		$cache_duration = isset( $_POST['cache_duration'] ) ? absint( wp_unslash( $_POST['cache_duration'] ) ) : 43200;
		if ( $cache_duration < 300 ) {
			$cache_duration = 300; // Minimum 5 minutes.
		}
		if ( $cache_duration > 604800 ) {
			$cache_duration = 604800; // Maximum 7 days.
		}

		$allowed_heading_levels = array( 'h1','h2','h3','h4','h5','h6' );
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
		brap_update_setting( 'month_format', $month_format );
		brap_update_setting( 'year_format', $year_format );
		brap_update_setting( 'show_post_date', $show_post_date );
		brap_update_setting( 'enable_cache', $enable_cache );
		brap_update_setting( 'cache_duration', (string) $cache_duration );
		brap_update_setting( 'remove_db_table_on_uninstall', $remove_on_uninstall );
		brap_update_setting( 'category_filter', $category_filter_slug );

		// Clear cache when settings are saved.
		brap_clear_cache();

		echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'all-posts-archive-page' ) . '</p></div>';
	}

	// Fetch current settings.
	$add_year_header       = brap_get_setting( 'add_year_header' );
	$year_header_level     = brap_get_setting( 'year_header_level' );
	$add_month_header      = brap_get_setting( 'add_month_header' );
	$month_header_level    = brap_get_setting( 'month_header_level' );
	$month_format          = brap_get_setting( 'month_format' );
	$year_format           = brap_get_setting( 'year_format' );
	$show_post_date        = brap_get_setting( 'show_post_date' );
	$enable_cache          = brap_get_setting( 'enable_cache' );
	$cache_duration        = brap_get_setting( 'cache_duration' );
	$remove_data           = brap_get_setting( 'remove_db_table_on_uninstall' );
	$category_filter_value = brap_get_setting( 'category_filter' );

	// Set defaults if not set.
	$enable_cache   = $enable_cache ? $enable_cache : 'on';
	$cache_duration = $cache_duration ? absint( $cache_duration ) : 43200;

	// Categories list for dropdown.
	$categories = get_categories(
		array(
			'hide_empty' => false,
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Binge Reading Archive Settings', 'all-posts-archive-page' ); ?></h1>

		<form method="POST" action="">
			<?php wp_nonce_field( 'brap_save_settings_action', 'brap_save_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'Category Filter (Optional)', 'all-posts-archive-page' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Select a default category to filter posts in the archive. Leave as "All categories" to include every post. You can override this per page using the shortcode attribute, e.g. [binge_archive category="news"].', 'all-posts-archive-page' ); ?>
			</p>
			<p>
				<label for="category_filter"><?php esc_html_e( 'Default Category', 'all-posts-archive-page' ); ?></label><br />
				<select name="category_filter" id="category_filter">
					<option value=""><?php esc_html_e( '— All categories —', 'all-posts-archive-page' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $category_filter_value, $cat->slug ); ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
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
					<?php foreach ( array( 'h1','h2','h3','h4','h5','h6' ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $year_header_level, $level ); ?>>
							<?php echo esc_html( strtoupper( $level ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

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
					<?php foreach ( array( 'h1','h2','h3','h4','h5','h6' ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $month_header_level, $level ); ?>>
							<?php echo esc_html( strtoupper( $level ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
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

			<h2><?php esc_html_e( 'Post Date Display', 'all-posts-archive-page' ); ?></h2>
			<label for="show_post_date">
				<input type="checkbox" name="show_post_date" id="show_post_date" <?php checked( $show_post_date, 'on' ); ?> />
				<?php esc_html_e( 'Show post dates in the list?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, each post will display its publication date before the title (e.g., "January 15, 2023 - Post Title").', 'all-posts-archive-page' ); ?></p>

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
					echo esc_html( sprintf( __( 'Current setting: %s hours. Minimum: 5 minutes (300), Maximum: 7 days (604800).', 'all-posts-archive-page' ), number_format( $cache_duration / 3600, 1 ) ) );
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
		<code>[binge_archive category="news"]</code>

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
