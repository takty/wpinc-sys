<?php
/**
 * IP Restriction (IPv4)
 *
 * @package Wpinc Sys
 * @author Takuto Yanagida
 * @version 2023-11-04
 */

declare(strict_types=1);

namespace wpinc\sys\ip_restriction;

require_once __DIR__ . '/post-status.php';

const PMK_IP_RESTRICTION = '_ip_restriction';

/**
 * Initialize IP restriction.
 */
function initialize(): void {
	$args = array(
		'meta_key'   => PMK_IP_RESTRICTION,  // phpcs:ignore
		'label'      => _x( 'Restrict display of this post by IP', 'ip restriction', 'wpinc_sys' ),
		'post_state' => _x( 'IP Restriction', 'ip restriction', 'wpinc_sys' ),
	);
	\wpinc\sys\post_status\initialize( $args );
}

/**
 * Adds post type as IP restricted.
 *
 * @param string|string[] $post_type_s Post types.
 */
function add_post_type( $post_type_s ): void {
	if ( ! \wpinc\sys\post_status\is_initialized( PMK_IP_RESTRICTION ) ) {
		initialize();
	}
	\wpinc\sys\post_status\add_post_type( $post_type_s, PMK_IP_RESTRICTION );

	$inst = _get_instance();
	if ( empty( $inst->post_types ) && ! is_admin() ) {
		add_action( 'pre_get_posts', '\wpinc\sys\ip_restriction\_cb_pre_get_posts' );
		add_filter( 'body_class', '\wpinc\sys\ip_restriction\_cb_body_class' );
	}
	$pts = (array) $post_type_s;
	if ( ! empty( $pts ) ) {
		array_push( $inst->post_types, ...$pts );
	}
}


// -----------------------------------------------------------------------------


/**
 * Adds CIDR.
 *
 * @param string $cidr Allowed CIDR.
 * @param string $cls  CSS class.
 */
function add_allowed_cidr( string $cidr, string $cls = '' ): void {
	$inst = _get_instance();

	$inst->whites[] = array(  // @phpstan-ignore-line
		'cidr' => $cidr,
		'cls'  => $cls,
	);
}

/**
 * Check whether the current IP is allowed.
 *
 * @return bool True if the IP is OK.
 */
function is_allowed(): bool {
	$inst = _get_instance();

	static $checked = 0;
	if ( $checked++ ) {
		return $inst->is_allowed;
	}
	$inst->is_allowed = false;  // @phpstan-ignore-line

	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = $_SERVER['REMOTE_ADDR'];  // phpcs:ignore

		foreach ( $inst->whites as $w ) {
			if ( _in_cidr( $ip, $w['cidr'] ) ) {
				$inst->is_allowed = true;  // @phpstan-ignore-line
				if ( ! empty( $w['cls'] ) ) {
					$inst->current_body_classes[] = $w['cls'];  // @phpstan-ignore-line
				}
			}
		}
	}
	return $inst->is_allowed;
}

/**
 * Check whether the current query is restricted and the current IP is not allowed.
 *
 * @return bool True if the current query is restricted and the current IP is not allowed.
 */
function is_restricted(): bool {
	$inst = _get_instance();
	return $inst->is_restricted;
}

/**
 * Check IP.
 *
 * @access private
 *
 * @param string $ip   Current IP.
 * @param string $cidr CIDR.
 * @return bool True if the IP matches.
 */
function _in_cidr( string $ip, string $cidr ): bool {
	list( $network, $mask_bit_len ) = explode( '/', $cidr );

	$host   = 32 - (int) $mask_bit_len;
	$net    = ip2long( $network ) >> $host << $host;
	$ip_net = ip2long( $ip ) >> $host << $host;
	return $net === $ip_net;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'pre_get_posts' action.
 *
 * @access private
 *
 * @param \WP_Query $query The WP_Query instance (passed by reference).
 */
function _cb_pre_get_posts( \WP_Query $query ): void {
	static $bypass = false;
	if ( $bypass ) {
		return;
	}
	if ( is_user_logged_in() || is_allowed() || is_404() ) {
		return;
	}
	$inst        = _get_instance();
	$post_type_s = $query->get( 'post_type', array() );
	$pts         = is_array( $post_type_s ) ? $post_type_s : array( $post_type_s );

	$pts = array_filter(
		$pts,
		function ( $e ) {
			return is_string( $e );
		}
	);

	if ( ! empty( $pts ) && empty( array_intersect( $pts, $inst->post_types ) ) ) {
		return;
	}
	$bypass = true;
	/**
	 * Post IDs. This is determined by $args['fields'] being 'ids'.
	 *
	 * @var int[] $ex_ps
	 */
	$ex_ps  = get_posts(
		array(
			'post_type'      => empty( $pts ) ? $inst->post_types : $pts,
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(  // phpcs:ignore
				array(
					'key'     => PMK_IP_RESTRICTION,
					'compare' => '=',
					'value'   => '1',
				),
			),
		)
	);
	$bypass = false;

	if ( ! empty( $ex_ps ) ) {
		$query->set( 'post__not_in', $ex_ps );
		$inst->is_restricted = true;  // @phpstan-ignore-line

		$p = $query->get( 'p' );
		if ( in_array( $p, $ex_ps, true ) ) {
			$query->set_404();
		}
	}
}

/**
 * Callback function for 'body_class' filter.
 *
 * @access private
 *
 * @param string[] $classes Classes.
 * @return string[] Classes.
 */
function _cb_body_class( array $classes ): array {
	$inst = _get_instance();
	if ( is_allowed() ) {
		foreach ( $inst->current_body_classes as $cls ) {
			$classes[] = $cls;
		}
	}
	return $classes;
}


// -----------------------------------------------------------------------------


/**
 * Gets instance.
 *
 * @access private
 *
 * @return object{
 *     whites              : array{ cidr: string, cls: string }[],
 *     post_types          : string[],
 *     current_body_classes: string[],
 *     is_allowed          : bool,
 *     is_restricted       : bool,
 * } Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {
		/**
		 * White list of allowed IPs.
		 *
		 * @var array{ cidr: string, cls: string }[]
		 */
		public $whites = array();

		/**
		 * The target post types.
		 *
		 * @var string[]
		 */
		public $post_types = array();

		/**
		 * CSS classes to be added.
		 *
		 * @var string[]
		 */
		public $current_body_classes = array();

		/**
		 * Whether the current post is allowed to be shown.
		 *
		 * @var bool
		 */
		public $is_allowed = false;

		/**
		 * Whether the current post is IP restricted.
		 *
		 * @var bool
		 */
		public $is_restricted = false;
	};
	return $values;
}
