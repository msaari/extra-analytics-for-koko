<?php
/**
 * Plugin Name: Extra Analytics for Koko
 * Description: Provides extra analytics for Koko Analytics
 * Version: 0.1
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
	foreach ( get_sub_pages() as $page_name => $page_slug ) {
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
 * Return list of sub pages.
 *
 * @return array List of sub pages, name => slug.
 */
function get_sub_pages() {
	$sub_pages = array(
		__( 'Authors', 'extra_analytics' )    => 'user_stats',
		__( 'Terms', 'extra_analytics' )      => 'term_stats',
		__( 'Years', 'extra_analytics' )      => 'year_stats',
		__( 'Post types', 'extra_analytics' ) => 'post_type_stats',
	);
	return $sub_pages;
}

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

	echo '<ul>';
	foreach ( get_sub_pages() as $page_name => $page_slug ) {
		echo '<li><h2><a href="' . esc_url( admin_url( 'admin.php?page=extra_analytics_' . $page_slug ) ) . '">' . esc_html( $page_name ) . '</a></h2></li>';
	}
	echo '</ul>';

	footer();
}

/**
 * Display user stats.
 */
function user_stats() {
	header();

	global $wpdb;

	$transient = 'extra_analytics_user_stats';
	$html      = get_transient( $transient );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		footer();
		return;
	}

	$sql = "SELECT u.id, u.display_name, SUM(koko.pageviews) AS views, count(distinct(p.ID)) AS posts
	FROM $wpdb->posts AS p,
		{$wpdb->prefix}koko_analytics_post_stats AS koko,
		$wpdb->users AS u
	WHERE p.ID = koko.id
		AND p.post_author = u.id
	GROUP BY u.id
	ORDER BY views DESC";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$html .= '<h2>' . esc_html__( 'User pageviews', 'extra_analytics' ) . '</h2>';
	$html .= '<table class="widefat">';
	$html .= '<thead><tr><th>'
		. esc_html__( 'User', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		$html .= '<tr>';
		$html .= '<td><a class="row-title" href="' . esc_url( get_edit_user_link( $row->id ) ) . '">' . esc_html( $row->display_name ) . '</a> ';
		$html .= '<div class="row-actions"><a href="' . esc_url( get_author_posts_url( $row->id ) ) . '">' . esc_html__( 'Show', 'extra_analytics' ) . '</a> | ';
		$html .= '<a href="' . esc_url( admin_url( 'edit.php?author=' . $row->id ) ) . '">' . esc_html__( 'See posts', 'extra_analytics' ) . '</a></div></td>';
		$html .= '<td>' . esc_html( $row->views ) . '</td>';
		$html .= '<td>' . esc_html( $row->posts ) . '</td>';
		$html .= '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		$html .= '</tr>';
	}
	$html .= '</table>';

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	set_transient( $transient, $html, HOUR_IN_SECONDS );

	footer();
}

/**
 * Display term stats.
 */
function term_stats() {
	header();

	global $wpdb;

	$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : 'post_tag'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$transient = 'extra_analytics_term_stats_' . $taxonomy . '_' . $page;
	$html      = get_transient( $transient );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		footer();
		return;
	}

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
		AND tt.taxonomy = '$taxonomy'
	GROUP BY t.term_id
	ORDER BY views DESC
	LIMIT 25 $offset";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$html = '';

	$html .= '<h2>' . esc_html__( 'Term pageviews', 'extra_analytics' ) . '</h2>';

	$taxonomies = get_taxonomies(
		array(
			'public' => true,
		),
		'objects'
	);

	$html .= <<<EOH
<form method="get">
<input type="hidden" name="page" value="extra_analytics_term_stats" />
<label for="taxonomy">
EOH;
	$html .= __( 'Choose taxonomy:', 'extra_analytics' );
	$html .= <<<EOH
</label>
<select name="taxonomy">
EOH;

	foreach ( $taxonomies as $taxonomy_term ) {
		$html .= '<option value="' . esc_attr( $taxonomy_term->name ) . '" ' . selected( $taxonomy_term->name, $taxonomy, false ) . '>' . esc_html( $taxonomy_term->label ) . '</option>';
	}
	$html .= <<<EOH
</select>
<input type="submit" value="Select" />
</form>
EOH;

	if ( $page > 1 ) {
		$html .= prev_page(
			array(
				'page'     => 'extra_analytics_term_stats',
				'taxonomy' => $taxonomy,
			),
			$page
		);
	}
	if ( count( $results ) === 25 ) {
		$html .= next_page(
			array(
				'page'     => 'extra_analytics_term_stats',
				'taxonomy' => $taxonomy,
			),
			$page
		);
	}
	$html .= '<table class="widefat">';
	$html .= '<thead><tr><th>'
		. esc_html__( 'Term', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		$html .= '<tr>';
		$html .= '<td><a class="row-title" href="' . esc_url( get_edit_term_link( $row->term_id ) ) . '">' . esc_html( $row->name ) . '</a> ';
		$html .= '<div class="row-actions"><a href="' . esc_url( get_term_link( intval( $row->term_id ), $taxonomy ) ) . '">' . esc_html__( 'Show', 'extra_analytics' ) . '</a> | ';
		$html .= '<a href="' . esc_url( admin_url( 'edit.php?tag=' . $row->slug ) ) . '">' . esc_html__( 'See posts', 'extra_analytics' ) . '</a></div></td>';
		$html .= '<td>' . esc_html( $row->views ) . '</td>';
		$html .= '<td>' . esc_html( $row->posts ) . '</td>';
		$html .= '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		$html .= '</tr>';
	}
	$html .= '</table>';
	if ( $page > 1 ) {
		$html .= prev_page(
			array(
				'page'     => 'extra_analytics_term_stats',
				'taxonomy' => $taxonomy,
			),
			$page
		);
	}
	if ( count( $results ) === 25 ) {
		$html .= next_page(
			array(
				'page'     => 'extra_analytics_term_stats',
				'taxonomy' => $taxonomy,
			),
			$page
		);
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	set_transient( $transient, $html, HOUR_IN_SECONDS );

	footer();
}

/**
 * Display year stats.
 */
function year_stats() {
	header();

	$transient = 'extra_analytics_year_stats';
	$html      = get_transient( $transient );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		footer();
		return;
	}

	global $wpdb;

	$sql = "SELECT YEAR(p.post_date) AS years, SUM(koko.pageviews) AS views, count(distinct(p.ID)) AS posts
	FROM $wpdb->posts AS p,
		{$wpdb->prefix}koko_analytics_post_stats AS koko
	WHERE p.ID = koko.id
	GROUP BY years
	ORDER BY views DESC";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$html .= '<h2>' . esc_html__( 'Yearly pageviews', 'extra_analytics' ) . '</h2>';
	$html .= '<table class="widefat">';
	$html .= '<thead><tr><th>'
		. esc_html__( 'Publication year', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		$html .= '<tr>';
		$html .= '<td><span class="row-title">' . esc_html( $row->years ) . '</span> ';
		$html .= '<div class="row-actions"><a href="' . esc_url( get_year_link( $row->years ) ) . '">' . esc_html__( 'See posts', 'extra_analytics' ) . '</a></div></td>';
		$html .= '<td>' . esc_html( $row->views ) . '</td>';
		$html .= '<td>' . esc_html( $row->posts ) . '</td>';
		$html .= '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		$html .= '</tr>';
	}
	$html .= '</table>';

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	set_transient( $transient, $html, HOUR_IN_SECONDS );

	footer();
}

/**
 * Display post type stats.
 */
function post_type_stats() {
	header();

	$transient = 'extra_analytics_post_type_stats';
	$html      = get_transient( $transient );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		footer();
		return;
	}

	$html = '';

	global $wpdb;

	$sql = "SELECT p.post_type, SUM(koko.pageviews) AS views, count(distinct(p.ID)) AS posts
	FROM $wpdb->posts AS p,
		{$wpdb->prefix}koko_analytics_post_stats AS koko
	WHERE p.ID = koko.id
	GROUP BY p.post_type
	ORDER BY views DESC";

	$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$html .= '<h2>' . esc_html__( 'Post type pageviews', 'extra_analytics' ) . '</h2>';
	$html .= '<table class="widefat">';
	$html .= '<thead><tr><th>'
		. esc_html__( 'Post type', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Posts', 'extra_analytics' ) . '</th><th>'
		. esc_html__( 'Views / post', 'extra_analytics' ) . '</tr></thead>';
	foreach ( $results as $row ) {
		$html .= '<tr>';
		$html .= '<td><span class="row-title">' . esc_html( $row->post_type ) . '</span> ';
		$html .= '<div class="row-actions">';
		if ( get_post_type_archive_link( $row->post_type ) ) {
			$html .= '<a href="' . esc_url( get_post_type_archive_link( $row->post_type ) ) . '">' . esc_html__( 'Show', 'extra_analytics' ) . '</a> | ';
		}
		$html .= '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . $row->post_type ) ) . '">See posts</a></div></td>';
		$html .= '<td>' . esc_html( $row->views ) . '</td>';
		$html .= '<td>' . esc_html( $row->posts ) . '</td>';
		$html .= '<td>' . esc_html( round( $row->views / $row->posts, 1 ) ) . '</td>';
		$html .= '</tr>';
	}
	$html .= '</table>';

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	set_transient( $transient, $html, HOUR_IN_SECONDS );

	footer();
}

/**
 * Return the link style for prev/next page links.
 *
 * @return string CSS inline style.
 */
function get_link_style() {
	$style = <<<EOCSS
background-color: white;
border: 1px solid #c3c4c7;
box-shadow: 0 1px 1px rgb(0 0 0 / 4%);
padding: 5px;
display: inline-block;
margin-right: 10px;
margin-top: 10px;
EOCSS;
	return $style;
}

/**
 * Return next page link.
 *
 * @param array $args Arguments: 'page' for the page slug, 'taxonomy' for the
 * taxonomy.
 * @param int   $page The current page number.
 *
 * @return string The HTML link.
 */
function next_page( $args, $page ) {
	if ( ! isset( $args['page'] ) ) {
		return '';
	}

	$url = 'admin.php?page=' . $args['page'] . '&paged=' . ( $page + 1 );
	if ( isset( $args['taxonomy'] ) ) {
		$url .= '&taxonomy=' . $args['taxonomy'];
	}

	return '<a style="' . get_link_style() . '" href="' . esc_url( admin_url( $url ) ) . '">' . esc_html__( 'Next page', 'extra_analytics' ) . '</a>';
}

/**
 * Return prev page link.
 *
 * @param array $args Arguments: 'page' for the page slug, 'taxonomy' for the
 * taxonomy.
 * @param int   $page The current page number.
 *
 * @return string The HTML link.
 */
function prev_page( $args, $page ) {
	if ( ! isset( $args['page'] ) ) {
		return '';
	}

	$url = 'admin.php?page=' . $args['page'] . '&paged=' . ( $page - 1 );
	if ( isset( $args['taxonomy'] ) ) {
		$url .= '&taxonomy=' . $args['taxonomy'];
	}

	return '<a style="' . get_link_style() . '" href="' . esc_url( admin_url( $url ) ) . '">' . esc_html__( 'Previous page', 'extra_analytics' ) . '</a>';
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
