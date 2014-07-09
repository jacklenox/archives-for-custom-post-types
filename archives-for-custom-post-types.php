<?php
/**
 * Plugin Name: Archives for Custom Post Types
 * Plugin URI: https://github.com/jacklenox/archives-for-custom-post-types
 * Description: A plugin that provides native-like support for dated archive pages of custom post types 
 * (e.g. http://yoursite.com/2014/{custom-post-type}/)
 * Version: 1.0.2
 * Author: Jack Lenox
 * Author URI: http://jacklenox.com
 * License: GPL2
 */

/*  Copyright 2014  Jack Lenox  (email : jack@automattic.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Display archive links based on type and format. (Based on wp_get_archives() in core, with some minor additions.)
 *
 * @since 1.0
 *
 * @see get_archives_link()
 *
 * @param string|array $args {
 *     Default archive links arguments. Optional.
 *
 *     @type string     $post_type       Post type to retrieve. Default 'post'.
 *     @type string     $type            Type of archive to retrieve. Accepts 'daily', 'weekly', 'monthly',
 *                                       'yearly', 'postbypost', or 'alpha'. Both 'postbypost' and 'alpha'
 *                                       display the same archive link list as well as post titles instead
 *                                       of displaying dates. The difference between the two is that 'alpha'
 *                                       will order by post title and 'postbypost' will order by post date.
 *                                       Default 'monthly'.
 *     @type string|int $limit           Number of links to limit the query to. Default empty (no limit).
 *     @type string     $format          Format each link should take using the $before and $after args.
 *                                       Accepts 'link' (`<link>` tag), 'option' (`<option>` tag), 'html'
 *                                       (`<li>` tag), or a custom format, which generates a link anchor
 *                                       with $before preceding and $after succeeding. Default 'html'.
 *     @type string     $before          Markup to prepend to the beginning of each link. Default empty.
 *     @type string     $after           Markup to append to the end of each link. Default empty.
 *     @type bool       $show_post_count Whether to display the post count alongside the link. Default false.
 *     @type bool       $echo            Whether to echo or return the links list. Default 1|true to echo.
 *     @type string     $order           Whether to use ascending or descending order. Accepts 'ASC', or 'DESC'.
 *                                       Default 'DESC'.
 * }
 * @return string|null String when retrieving, null when displaying.
 */
function wp_get_archives_cpt( $args = '' ) {
	global $wpdb, $wp_locale, $wp_rewrite;

	$defaults = array(
		'post_type' => 'post',
		'type' => 'monthly', 'limit' => '',
		'format' => 'html', 'before' => '',
		'after' => '', 'show_post_count' => false,
		'echo' => 1, 'order' => 'DESC',
	);

	$args = wp_parse_args( $args, $defaults );

	if ( '' == $args['type'] ) {
		$args['type'] = 'monthly';
	}

	if ( ! empty( $args['limit'] ) ) {
		$args['limit'] = absint( $args['limit'] );
		$args['limit'] = ' LIMIT ' . $args['limit'];
	}

	$order = strtoupper( $args['order'] );
	if ( $order !== 'ASC' ) {
		$order = 'DESC';
	}

	// this is what will separate dates on weekly archive links
	$archive_week_separator = '&#8211;';

	// over-ride general date format ? 0 = no: use the date format set in Options, 1 = yes: over-ride
	$archive_date_format_over_ride = 0;

	// options for daily archive (only if you over-ride the general date format)
	$archive_day_date_format = 'Y/m/d';

	// options for weekly archive (only if you over-ride the general date format)
	$archive_week_start_date_format = 'Y/m/d';
	$archive_week_end_date_format	= 'Y/m/d';

	if ( ! $archive_date_format_over_ride ) {
		$archive_day_date_format = get_option( 'date_format' );
		$archive_week_start_date_format = get_option( 'date_format' );
		$archive_week_end_date_format = get_option( 'date_format' );
	}

	/**
	 * Filter the SQL WHERE clause for retrieving archives.
	 *
	 * @since 2.2.0
	 *
	 * @param string $sql_where Portion of SQL query containing the WHERE clause.
	 * @param array  $args         An array of default arguments.
	 */
	$where = apply_filters( 'getarchives_where', "WHERE post_type = '" . $args['post_type'] . "' AND post_status = 'publish'", $args );

	/**
	 * Filter the SQL JOIN clause for retrieving archives.
	 *
	 * @since 2.2.0
	 *
	 * @param string $sql_join Portion of SQL query containing JOIN clause.
	 * @param array  $args        An array of default arguments.
	 */
	$join = apply_filters( 'getarchives_join', '', $args );

	$output = '';

	$last_changed = wp_cache_get( 'last_changed', 'posts' );
	if ( ! $last_changed ) {
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'posts' );
	}

	$limit = $args['limit'];

	// Check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// If we're not using rewrite rules, the post_type can simply be added to the query string, else it uses the format of adding the custom post type to the end of the URL
	if ( empty($rewrite) ) {
		$post_type = ( 'post' === $args['post_type'] ) ? '' : '&post_type=' . $args['post_type'];
	} else {
		$post_type = ( 'post' === $args['post_type'] ) ? '' : $args['post_type'] . '/';
	}

	if ( 'monthly' == $args['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $args['after'];
			foreach ( (array) $results as $result ) {
				$url = get_month_link( $result->year, $result->month ) . $post_type;
				/* translators: 1: month name, 2: 4-digit year */
				$text = sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $result->month ), $result->year );
				if ( $args['show_post_count'] ) {
					$args['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $args['format'], $args['before'], $args['after'] );
			}
		}
	} elseif ( 'yearly' == $args['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $args['after'];
			foreach ( (array) $results as $result ) {
				$url = get_year_link( $result->year ) . $post_type;
				$text = sprintf( '%d', $result->year );
				if ( $args['show_post_count'] ) {
					$args['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $args['format'], $args['before'], $args['after'] );
			}
		}
	} elseif ( 'daily' == $args['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, DAYOFMONTH(post_date) AS `dayofmonth`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date), DAYOFMONTH(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			$cache[ $key ] = $results;
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $args['after'];
			foreach ( (array) $results as $result ) {
				$url  = get_day_link( $result->year, $result->month, $result->dayofmonth ) . $post_type;
				$date = sprintf( '%1$d-%2$02d-%3$02d 00:00:00', $result->year, $result->month, $result->dayofmonth );
				$text = mysql2date( $archive_day_date_format, $date );
				if ( $args['show_post_count'] ) {
					$args['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $args['format'], $args['before'], $args['after'] );
			}
		}
	} elseif ( 'weekly' == $args['type'] ) {
		$week = _wp_mysql_week( '`post_date`' );
		$query = "SELECT DISTINCT $week AS `week`, YEAR( `post_date` ) AS `yr`, DATE_FORMAT( `post_date`, '%Y-%m-%d' ) AS `yyyymmdd`, count( `ID` ) AS `posts` FROM `$wpdb->posts` $join $where GROUP BY $week, YEAR( `post_date` ) ORDER BY `post_date` $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		$arc_w_last = '';
		if ( $results ) {
			$after = $args['after'];
			foreach ( (array) $results as $result ) {
				if ( $result->week != $arc_w_last ) {
					$arc_year       = $result->yr;
					$arc_w_last     = $result->week;
					$arc_week       = get_weekstartend( $result->yyyymmdd, get_option( 'start_of_week' ) );
					$arc_week_start = date_i18n( $archive_week_start_date_format, $arc_week['start'] );
					$arc_week_end   = date_i18n( $archive_week_end_date_format, $arc_week['end'] );
					$url            = sprintf( '%1$s/%2$s%3$sm%4$s%5$s%6$sw%7$s%8$d', home_url(), '', '?', '=', $arc_year, '&amp;', '=', $result->week ) . $post_type;
					$text           = $arc_week_start . $archive_week_separator . $arc_week_end;
					if ( $args['show_post_count'] ) {
						$args['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $args['format'], $args['before'], $args['after'] );
				}
			}
		}
	} elseif ( ( 'postbypost' == $args['type'] ) || ('alpha' == $args['type'] ) ) {
		$orderby = ( 'alpha' == $args['type'] ) ? 'post_title ASC ' : 'post_date DESC ';
		$query = "SELECT * FROM $wpdb->posts $join $where ORDER BY $orderby $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			foreach ( (array) $results as $result ) {
				if ( $result->post_date != '0000-00-00 00:00:00' ) {
					$url = get_permalink( $result ) . $post_type;
					if ( $result->post_title ) {
						/** This filter is documented in wp-includes/post-template.php */
						$text = strip_tags( apply_filters( 'the_title', $result->post_title, $result->ID ) );
					} else {
						$text = $result->ID;
					}
					$output .= get_archives_link( $url, $text, $args['format'], $args['before'], $args['after'] );
				}
			}
		}
	}
	if ( $args['echo'] ) {
		echo $output;
	} else {
		return $output;
	}
}

// Add our rewrite rules to the array and flush the cache on activation of the plugin.
add_filter( 'rewrite_rules_array', 'archives_for_custom_post_types_rewrite_rules' );
register_activation_hook( __FILE__, 'archives_for_custom_post_types_flush_rules' );

function archives_for_custom_post_types_flush_rules() {
	$rules = get_option( 'rewrite_rules' );

	$post_types = get_post_types( '', 'names' );
	$line_separated = implode( '|', $post_types );

	if ( ! isset( $rules['(\d*)/(' . $line_separated . ')$'] ) ) {
		global $wp_rewrite;
	   	$wp_rewrite->flush_rules();
	}
}

function archives_for_custom_post_types_rewrite_rules( $rules ) {
	$post_types = get_post_types( '', 'names' );
	$line_separated = implode( '|', $post_types );

	$newrules = array();
	$newrules['(\d*)/(' . $line_separated . ')$'] = 'index.php?m=$matches[1]&post_type=$matches[2]';
	$newrules['(\d*)/(\d*)/(' . $line_separated . ')$'] = 'index.php?m=$matches[1]$matches[2]&post_type=$matches[3]';
	$newrules['(\d*)/(\d*)/(\d*)/(' . $line_separated . ')$'] = 'index.php?m=$matches[1]$matches[2]$matches[3]&post_type=$matches[4]';
	$newrules['(\d*)/(' . $line_separated . ')/page/(\d*)$'] = 'index.php?m=$matches[1]&post_type=$matches[2]&paged=$matches[3]';
	$newrules['(\d*)/(\d*)/(' . $line_separated . ')/page/(\d*)$'] = 'index.php?m=$matches[1]$matches[2]&post_type=$matches[3]&paged=$matches[4]';
	$newrules['(\d*)/(\d*)/(\d*)/(' . $line_separated . ')/page/(\d*)$'] = 'index.php?m=$matches[1]$matches[2]$matches[3]&post_type=$matches[4]&paged=$matches[5]';
	return $newrules + $rules;
}