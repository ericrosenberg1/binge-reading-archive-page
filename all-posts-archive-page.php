<?php
/**
 * Plugin Name: Binge Reading Archive Page
 * Plugin URI:  https://ericrosenberg.com/binge-reading-archive-page-template-for-wordpress/
 * Description: A plugin to display all posts month-by-month for binge reading. Uses your theme's styling by default.
 * Version:     0.50
 * Author:      Eric Rosenberg
 * Author URI:  https://ericrosenberg.com
 * Text Domain: all-posts-archive-page
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct file access
}

/**
 * Activation Hook: Create/upgrade settings table if it doesn't exist.
 */
register_activation_hook( __FILE__, 'brap_activate' );
function brap_activate() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'brap_settings';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		setting_name varchar(50) NOT NULL,
		setting_value varchar(50) NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// Insert default settings if they don't already exist.
	$default_settings = array(
		'add_year_header'            => 'off',
		'year_header_level'          => 'h2',
		'add_month_header'           => 'on',
		'month_header_level'         => 'h3',
		'month_format'               => 'MMM',
		'year_format'                => 'YYYY',
		'remove_db_table_on_uninstall' => 'no',
	);

	foreach ( $default_settings as $name => $value ) {
		// Check if exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE setting_name = %s",
				$name
			)
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
		$wpdb->prepare(
			"SELECT setting_value FROM $table_name WHERE setting_name = %s",
			'remove_db_table_on_uninstall'
		)
	);

	// If user has chosen 'yes', drop the table.
	if ( $remove_data === 'yes' ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
}

/**
 * Helper functions to get and update settings.
 */
function brap_get_setting( $name ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'brap_settings';

	$value = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT setting_value FROM $table_name WHERE setting_name = %s",
			$name
		)
	);

	return ( ! empty( $value ) ) ? $value : false;
}

function brap_update_setting( $name, $value ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'brap_settings';

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE setting_name = %s",
			$name
		)
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
 * Generates the month-by-month post listing using your stored settings.
 *
 * @return string HTML output of the binge reading archive.
 */
function brap_display_posts_by_month() {
	// Fetch user settings from our table.
	$add_year_header   = brap_get_setting( 'add_year_header' );
	$year_header_level = brap_get_setting( 'year_header_level' );
	$add_month_header  = brap_get_setting( 'add_month_header' );
	$month_header_level = brap_get_setting( 'month_header_level' );
	$month_format      = brap_get_setting( 'month_format' ); // 'MM' or 'MMM'
	$year_format       = brap_get_setting( 'year_format' );  // 'YY' or 'YYYY'

	// Make sure we have fallback defaults.
	$add_year_header   = ( $add_year_header ) ? $add_year_header : 'off';
	$year_header_level = ( $year_header_level ) ? $year_header_level : 'h2';
	$add_month_header  = ( $add_month_header ) ? $add_month_header : 'on';
	$month_header_level = ( $month_header_level ) ? $month_header_level : 'h3';
	$month_format      = ( $month_format ) ? $month_format : 'MMM';
	$year_format       = ( $year_format ) ? $year_format : 'YYYY';

	// Query all published posts, most recent first.
	$post_items = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'order'          => 'DESC',
		'orderby'        => 'date',
	) );

	// Track last month/year so we know when to start new sections/headings.
	$current_month = '';
	$current_year  = '';
	$output        = '';

	if ( $post_items->have_posts() ) {
		ob_start();

		while ( $post_items->have_posts() ) {
			$post_items->the_post();

			$post_year  = get_the_date( 'Y' );
			$post_month = get_the_date( 'n' ); // numeric month

			// Build format based on user setting for the heading text.
			// e.g., 'Feb 23' or '02 2023', etc.
			$display_year  = get_the_date( ( $year_format === 'YYYY' ? 'Y' : 'y' ) );
			$display_month = get_the_date( ( $month_format === 'MM' ? 'm' : 'M' ) ); // 'm' or 'M'

			// If the year changes, close old section/UL if needed, then possibly print a new year heading.
			if ( $post_year !== $current_year ) {
				// Close old UL and section if they exist
				if ( ! empty( $current_year ) ) {
					echo '</ul></section>';
				}

				// Print year heading if turned on
				if ( 'on' === $add_year_header ) {
					// e.g. <h2>2023</h2> or <h1>23</h1>
					echo '<section class="binge-archive-year">';
					echo '<' . esc_html( $year_header_level ) . '>' . esc_html( $display_year ) . '</' . esc_html( $year_header_level ) . '>';
					echo '</section>';
				}

				$current_year = $post_year;
				$current_month = ''; // reset current month for new year
			}

			// If the month changes, close old UL if needed and print a new month heading if turned on.
			if ( $post_month !== $current_month ) {
				if ( ! empty( $current_month ) ) {
					echo '</ul></section>';
				}

				if ( 'on' === $add_month_header ) {
					echo '<section class="binge-archive-month">';
					echo '<' . esc_html( $month_header_level ) . '>' . esc_html( $display_month . ' ' . $display_year ) . '</' . esc_html( $month_header_level ) . '>';
				} else {
					// If not adding month heading, still open a <section> so we can put <ul> in it
					echo '<section class="binge-archive-month">';
				}

				echo '<ul>';

				$current_month = $post_month;
			}

			// List each post with date and title.
			printf(
				'<li><a href="%s"><span class="archive_post_date">%s - </span>%s</a></li>',
				esc_url( get_the_permalink() ),
				esc_html( get_the_date( 'F j, Y' ) ),
				esc_html( get_the_title() )
			);
		}

		// Close final UL and section after the loop ends
		echo '</ul></section>';

		wp_reset_postdata();
		$output = ob_get_clean();
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
 * Adds a settings page under "Settings" with our new options.
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

function brap_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'all-posts-archive-page' ) );
	}

	// Process form submission
	if ( isset( $_POST['brap_save_settings'] ) && check_admin_referer( 'brap_save_settings_action', 'brap_save_settings_nonce' ) ) {
		brap_update_setting( 'add_year_header',            isset( $_POST['add_year_header'] ) ? 'on' : 'off' );
		brap_update_setting( 'year_header_level',          sanitize_text_field( $_POST['year_header_level'] ) );
		brap_update_setting( 'add_month_header',           isset( $_POST['add_month_header'] ) ? 'on' : 'off' );
		brap_update_setting( 'month_header_level',         sanitize_text_field( $_POST['month_header_level'] ) );
		brap_update_setting( 'month_format',               sanitize_text_field( $_POST['month_format'] ) );
		brap_update_setting( 'year_format',                sanitize_text_field( $_POST['year_format'] ) );
		brap_update_setting( 'remove_db_table_on_uninstall', isset( $_POST['remove_db_table_on_uninstall'] ) ? 'yes' : 'no' );

		echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'all-posts-archive-page' ) . '</p></div>';
	}

	// Fetch current settings
	$add_year_header   = brap_get_setting( 'add_year_header' );
	$year_header_level = brap_get_setting( 'year_header_level' );
	$add_month_header  = brap_get_setting( 'add_month_header' );
	$month_header_level = brap_get_setting( 'month_header_level' );
	$month_format      = brap_get_setting( 'month_format' );
	$year_format       = brap_get_setting( 'year_format' );
	$remove_data       = brap_get_setting( 'remove_db_table_on_uninstall' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Binge Reading Archive Settings', 'all-posts-archive-page' ); ?></h1>
		<form method="POST" action="">
			<?php wp_nonce_field( 'brap_save_settings_action', 'brap_save_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'Year Header Settings', 'all-posts-archive-page' ); ?></h2>
			<label for="add_year_header">
				<input type="checkbox" name="add_year_header" id="add_year_header" <?php checked( $add_year_header, 'on' ); ?> />
				<?php esc_html_e( 'Add a heading for each year?', 'all-posts-archive-page' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, a heading will appear for each new year. Example: 2023.', 'all-posts-archive-page' ); ?></p>

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
			<p class="description"><?php esc_html_e( 'If checked, a heading will appear for each new month. Example: Jan 2023 or 01 23.', 'all-posts-archive-page' ); ?></p>

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
			<?php esc_html_e(
				'Thank you for installing the Binge Reading Archive plugin. 
				Use the shortcode below on any page or post to display a reverse-chronological archive of all posts, 
				grouped by month (and optionally by year). Your theme’s styling applies automatically.',
				'all-posts-archive-page'
			); ?>
		</p>
		<p><?php esc_html_e( 'Shortcode example:', 'all-posts-archive-page' ); ?></p>
		<code>[binge_archive]</code>
		<hr />
		<ul>
			<li>
				<?php esc_html_e( 'Submit a Bug or Feature Request:', 'all-posts-archive-page' ); ?>
				<a href="https://wordpress.org/support/plugin/all-posts-archive-page" target="_blank">
					<?php esc_html_e( 'WordPress.org Forums', 'all-posts-archive-page' ); ?>
				</a>
			</li>
			<li>
				<?php esc_html_e( 'Connect with the author around the web:', 'all-posts-archive-page' ); ?>
				<a href="https://eric.money/" target="_blank">https://eric.money/</a>
			</li>
		</ul>
	</div>
	<?php
}
