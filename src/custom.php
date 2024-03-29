<?php
/**
 * Custom Settings.
 *
 * @package Wpinc Sys
 * @author Takuto Yanagida
 * @version 2024-03-14
 */

declare(strict_types=1);

namespace wpinc\sys;

/**
 * Activates document title with post type.
 */
function activate_document_title_with_post_type(): void {
	if ( is_admin() ) {
		return;
	}
	add_filter(
		'document_title_parts',
		function ( $tps ) {
			if ( is_year() ) {
				$tps['title'] = (string) get_the_date( _x( 'Y', 'yearly archives date format' ) );
			} elseif ( is_month() ) {
				$tps['title'] = (string) get_the_date( _x( 'F Y', 'monthly archives date format' ) );
			} elseif ( is_day() ) {
				$tps['title'] = (string) get_the_date();
			} elseif ( is_tax() ) {
				$tps['title'] = (string) single_term_title( '', false );
			}
			if ( is_date() || is_tax() ) {
				$pt = get_post_type();
				if ( is_string( $pt ) ) {
					$pto = get_post_type_object( $pt );
					if ( $pto && $pto->label ) {
						$sep = apply_filters( 'document_title_separator', '-' );

						$tps['title'] .= " $sep " . $pto->label;
					}
				}
			}
			return $tps;
		}
	);
}

/**
 * Activates simple default slugs.
 *
 * @psalm-suppress RedundantCast
 *
 * @param string|string[] $post_type_s Post types. Default array() (all post types).
 */
function activate_simple_default_slug( $post_type_s = array() ): void {
	if ( ! is_admin() ) {
		return;
	}
	$pts = (array) $post_type_s;
	add_filter(
		'wp_unique_post_slug',
		function ( $slug, $post_id, $_post_status, $post_type ) use ( $pts ) {
			$post = get_post( $post_id );
			if (
				$post instanceof \WP_Post &&
				( '0000-00-00 00:00:00' === $post->post_date_gmt ) &&
				( empty( $pts ) || in_array( $post_type, $pts, true ) ) &&
				( preg_match( '/%/u', (string) $slug ) )  // String cast is needed due to 'wp_insert_post'.
			) {
				$slug = (string) preg_replace( '/[^a-zA-Z0-9_-]/u', '_', urldecode( (string) $slug ) );
				if ( 0 === strlen( $slug ) || ! preg_match( '/[^_]/u', $slug ) ) {
					$slug = (string) $post_id;
				}
			}
			return $slug;
		},
		10,
		4
	);
}

/**
 * Activates 'enter title here' label.
 */
function activate_enter_title_here_label(): void {
	if ( ! is_admin() ) {
		return;
	}
	add_filter(
		'enter_title_here',
		function ( $enter_title_here, $post ) {
			$pto = get_post_type_object( $post->post_type );
			$lab = $pto->labels->enter_title_here ?? '';
			if ( is_string( $lab ) && '' !== $lab ) {  // Check for non-empty-string.
				$enter_title_here = esc_html( $lab );
			}
			return $enter_title_here;
		},
		10,
		2
	);
}


// -----------------------------------------------------------------------------


/**
 * Activates password from template.
 */
function activate_password_form_template(): void {
	if ( ! is_admin() ) {
		add_filter( 'the_password_form', '\wpinc\sys\_cb_the_password_form', 10 );
	}
}

/**
 * Callback function for 'the_password_form' hook.
 *
 * @access private
 * @psalm-suppress UnresolvableInclude
 *
 * @param string $output The password form HTML output.
 * @return string The password form.
 */
function _cb_the_password_form( string $output ): string {
	$password_form_template = locate_template( 'passwordform.php' );

	if ( '' !== $password_form_template ) {
		ob_start();
		require $password_form_template;
		$output = str_replace( "\n", '', (string) ob_get_clean() );
	}
	return $output;
}


// -----------------------------------------------------------------------------


/**
 * Removes indications from post titles.
 *
 * @param bool $protected Whether to remove 'Protected'.
 * @param bool $private   Whether to remove 'Private'.
 */
function remove_post_title_indication( bool $protected, bool $private ): void {  // phpcs:ignore
	if ( ! is_admin() ) {
		if ( $protected ) {
			add_filter( 'protected_title_format', '\wpinc\sys\_cb_title_format', 10 );
		}
		if ( $private ) {
			add_filter( 'private_title_format', '\wpinc\sys\_cb_title_format', 10 );
		}
	}
}

/**
 * Callback function for 'protected_title_format' and 'private_title_format' filter.
 *
 * @param string $_prepend Dummy.
 * @return string Format.
 */
function _cb_title_format( string $_prepend ): string {  // phpcs:ignore
	return '%s';
}

/**
 * Removes prefixes from archive titles.
 */
function remove_archive_title_prefix(): void {
	if ( ! is_admin() ) {
		/** @psalm-suppress PossiblyInvalidArgument */  // phpcs:ignore
		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
	}
}

/**
 * Removes separators from document title.
 */
function remove_document_title_separator(): void {
	add_filter(
		'document_title_parts',
		function ( $title ) {
			if ( is_front_page() ) {
				$title['title'] = _strip_custom_tags( $title['title'] );
			} else {
				$title['site'] = _strip_custom_tags( $title['site'] );
			}
			return $title;
		}
	);
}

/**
 * Strips all tags and custom 'br'.
 *
 * @access private
 *
 * @param string $text The text.
 * @return string The stripped text.
 */
function _strip_custom_tags( string $text ): string {
	// Replace double full-width spaces and br tags to single space.
	$text = preg_replace( '/　　|<\s*br\s*\/?>/ui', ' ', $text ) ?? $text;
	$text = wp_strip_all_tags( $text, true );
	return $text;
}
