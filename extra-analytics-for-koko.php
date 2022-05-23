<?php
/**
 * Plugin Name: Extra Analytics for Koko
 * Description: Provides extra analytics for Koko Analytics
 * Version: 1
 * Author: Mikko Saari
 * Author URI: http://www.mikkosaari.fi/
 *
 * @package extra-analytics-for-koko
 */

namespace extra_analytics_for_koko;

/**
 * Register a custom menu page.
 */
function add_plugin_menu_page() {
	add_menu_page(
		__( 'Extra analytics', 'extra_analytics' ),
		'Extra analytics',
		'manage_options',
		'extra_analytics',
		__NAMESPACE__ . '\\plugin_menu_page',
		'dashicons-chart-bar',
		90
	);
	$sub_pages = array(
		__( 'User stats', 'extra_analytics' ) => 'user_stats',
		__( 'Term stats', 'extra_analytics' ) => 'term_stats',
	);
	foreach ( $sub_pages as $page_name => $page_slug ) {
		add_submenu_page(
			'extra_analytics',
			$page_name,
			$page_name,
			'manage_options',
			'extra_analytics_' . $page_slug,
			__NAMESPACE__ . '\\' . $page_slug
		);
	}
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_plugin_menu_page' );

/**
 * Display a custom menu page
 */
function plugin_menu_page() {
	header();

	if ( ! defined( 'KOKO_ANALYTICS_VERSION' ) ) {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Koko Analytics is not installed. Please install it first.', 'extra_analytics' ); ?></p>
		</div>
		<?php
		return;
	}

	?>
<ul>
	<li><h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=extra_analytics_user_stats' ) ); ?>">User stats</a></h2></li>
	<li><h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=extra_analytics_term_stats' ) ); ?>">Term stats</a></h2></li>
</ul>

	<?php

	footer();
}

/**
 * Display user stats.
 */
function user_stats() {
	header();

	global $wpdb;

	$sql = "SELECT u.id, u.display_name, SUM(koko.pageviews) AS views, count(distinct(p.ID)) AS posts
	FROM $wpdb->posts AS p,
		{$wpdb->prefix}koko_analytics_post_stats AS koko,
		$wpdb->users AS u
	WHERE p.ID = koko.id
		AND p.post_author = u.id
	GROUP BY u.id
	ORDER BY views DESC";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	echo '<h2>' . esc_html__( 'User pageviews', 'extra_analytics' ) . '</h2>';
	echo '<table class="widefat">';
	echo '<thead><tr><th>'
		. esc_html__( 'User', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		echo '<tr>';
		echo '<td><a class="row-title" href="' . esc_url( get_edit_user_link( $row->id ) ) . '">' . esc_html( $row->display_name ) . '</a> ';
		echo '<div class="row-actions"><a href="' . esc_url( get_author_posts_url( $row->id ) ) . '">' . esc_html__( 'Show', 'extra_analytics' ) . '</a> | ';
		echo '<a href="' . esc_url( admin_url( 'edit.php?author=' . $row->id ) ) . '">' . esc_html__( 'See posts', 'extra_analytics' ) . '</a></div></td>';
		echo '<td>' . esc_html( $row->views ) . '</td>';
		echo '<td>' . esc_html( $row->posts ) . '</td>';
		echo '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		echo '</tr>';
	}
	echo '</table>';

	footer();
}

/**
 * Display term stats.
 */
function term_stats() {
	header();

	global $wpdb;

	$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$offset = ( $page - 1 ) * 25;
	if ( $offset > 0 ) {
		$offset = 'OFFSET ' . $offset;
	} else {
		$offset = '';
	}

	$sql = "SELECT t.term_id, t.slug, t.name, SUM(koko.pageviews) AS views, count(distinct(p.ID)) AS posts
	FROM $wpdb->posts AS p,
		{$wpdb->prefix}koko_analytics_post_stats AS koko,
		$wpdb->terms AS t,
		$wpdb->term_taxonomy AS tt,
		$wpdb->term_relationships AS tr
	WHERE p.ID = koko.id
		AND p.ID = tr.object_id
		AND tr.term_taxonomy_id = tt.term_taxonomy_id
		AND tt.term_id = t.term_id
		AND tt.taxonomy = 'post_tag'
	GROUP BY t.term_id
	ORDER BY views DESC
	LIMIT 25 $offset";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	echo '<h2>' . esc_html__( 'Term pageviews', 'extra_analytics' ) . '</h2>';

	echo '<table class="widefat">';
	echo '<thead><tr><th>'
		. esc_html__( 'Term', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		echo '<tr>';
		echo '<td><a class="row-title" href="' . esc_url( get_edit_term_link( $row->term_id ) ) . '">' . esc_html( $row->name ) . '</a> ';
		echo '<div class="row-actions"><a href="' . esc_url( get_term_link( intval( $row->term_id ), 'post_tag' ) ) . '">' . esc_html__( 'Show', 'extra_analytics' ) . '</a> | ';
		echo '<a href="' . esc_url( admin_url( 'edit.php?tag=' . $row->slug ) ) . '">' . esc_html__( 'See posts', 'extra_analytics' ) . '</a></div></td>';
		echo '<td>' . esc_html( $row->views ) . '</td>';
		echo '<td>' . esc_html( $row->posts ) . '</td>';
		echo '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		echo '</tr>';
	}
	echo '</table>';

	footer();
}

/**
 * Display menu page header.
 */
function header() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Extra Analytics for Koko Analytics', 'extra_analytics' ); ?></h1>
	<?php
}

/**
 * Display menu page footer.
 */
function footer() {
	?>
	</div>
	<?php
}
