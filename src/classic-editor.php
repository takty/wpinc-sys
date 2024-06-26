<?php
/**
 * Classic Editor Settings
 *
 * @package Wpinc Sys
 * @author Takuto Yanagida
 * @version 2024-04-18
 */

/*  // phpcs:disable
cspell:disable
Advanced Editor Tools Setting:
{
	"settings": {
		"toolbar_1": "formatselect,bold,italic,underline,strikethrough,superscript,subscript,bullist,numlist,alignleft,aligncenter,alignright,link,unlink",
		"toolbar_2": "undo,redo,styleselect,removeformat,forecolor,backcolor,blockquote",
		"toolbar_3": "",
		"toolbar_4": "",
		"toolbar_classic_block": "formatselect,bold,italic,underline,strikethrough,superscript,subscript,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,styleselect,removeformat,forecolor,backcolor,blockquote",
		"options": "menubar_block,menubar,merge_toolbars,advlist",
		"plugins": "advlist"
	},
	"admin_settings": {
		"options": "classic_paragraph_block",
		"disabled_editors": ""
	}
}
cspell:enable
*/  // phpcs:enable

declare(strict_types=1);

namespace wpinc\sys\classic_editor;

require_once __DIR__ . '/assets/asset-url.php';

/**
 * Add buttons to the tool bar.
 *
 * @param string|null $url_to    URL to this script.
 * @param int         $row_index Tool bar row.
 */
function add_buttons( ?string $url_to = null, int $row_index = 2 ): void {
	if ( ! is_admin() ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$url_to = untrailingslashit( $url_to ?? \wpinc\get_file_uri( __DIR__ ) );

	_call_on_post_screen(
		function () use ( $url_to, $row_index ) {
			add_filter(
				'mce_external_plugins',
				function ( array $plugins ) use ( $url_to ) {
					$plugins['columns'] = \wpinc\abs_url( $url_to, './assets/js/classic-editor-command.min.js' );
					return $plugins;
				}
			);
			/** @psalm-suppress HookNotFound */  // phpcs:ignore
			add_filter(
				"mce_buttons_$row_index",
				function ( array $buttons ) {
					array_push( $buttons, 'styleselect', 'column_2', 'column_3', 'column_4' );  // cspell:disable-line.
					return $buttons;
				}
			);
		}
	);
}

/**
 * Adds style formats.
 *
 * @param array<string, mixed> $args {
 *     Arguments.
 *
 *     @type array 'labels'  Labels of styles.
 *     @type array 'formats' Additional formats.
 * }
 */
function add_style_formats( array $args = array() ): void {
	if ( ! is_admin() ) {
		return;
	}
	_call_on_post_screen(
		function () use ( $args ) {
			add_filter(
				'tiny_mce_before_init',
				function ( $mce_init ) use ( $args ) {
					return _cb_tiny_mce_before_init( $mce_init, $args );
				}
			);
		}
	);
}

/**
 * Callback function for 'tiny_mce_before_init' filter.
 *
 * @param array<string, mixed> $mce_init Settings.
 * @param array<string, mixed> $args     Arguments.
 * @return array<string, mixed> Settings.
 */
function _cb_tiny_mce_before_init( array $mce_init, array $args ): array {
	$ls  = $args['labels'] ?? array();
	$ls += array(
		'button'          => _x( 'Link Button', 'classic editor', 'wpinc_sys' ),
		'frame'           => _x( 'Frame', 'classic editor', 'wpinc_sys' ),
		'frame-alt'       => _x( 'Frame Alt', 'classic editor', 'wpinc_sys' ),
		'tab-scroll'      => _x( 'Tab Scroll', 'classic editor', 'wpinc_sys' ),
		'tab-stack'       => _x( 'Tab Stack', 'classic editor', 'wpinc_sys' ),
		'clear'           => _x( 'Clear Float', 'classic editor', 'wpinc_sys' ),
		'tab-page'        => _x( 'Tab Pages (Old)', 'classic editor', 'wpinc_sys' ),
		'pseudo-tab-page' => _x( 'Pseudo Tab Pages (Old)', 'classic editor', 'wpinc_sys' ),
	);

	$formats = array();
	if ( isset( $mce_init['style_formats'] ) && is_string( $mce_init['style_formats'] ) ) {
		$f = json_decode( $mce_init['style_formats'] );
		if ( is_array( $f ) ) {
			$formats = $f;
		}
	}
	$formats += array(
		array(
			'title'    => $ls['button'],
			'selector' => 'a',
			'classes'  => 'button',
		),
		array(
			'title'   => $ls['frame'],
			'block'   => 'div',
			'classes' => 'frame',
			'wrapper' => true,
		),
		array(
			'title'   => $ls['frame-alt'],
			'block'   => 'div',
			'classes' => 'frame-alt',
			'wrapper' => true,
		),
		array(
			'title'   => $ls['tab-scroll'],
			'block'   => 'div',
			'classes' => 'tab-scroll',
			'wrapper' => true,
		),
		array(
			'title'   => $ls['tab-stack'],
			'block'   => 'div',
			'classes' => 'tab-stack',
			'wrapper' => true,
		),
		array(
			'title'   => $ls['clear'],
			'block'   => 'div',
			'classes' => 'clear',
		),
		array(
			'title'   => $ls['tab-page'],
			'block'   => 'div',
			'classes' => 'tab-page',
			'wrapper' => true,
		),
		array(
			'title'   => $ls['pseudo-tab-page'],
			'block'   => 'div',
			'classes' => 'pseudo-tab-page',
			'wrapper' => true,
		),
	);
	if ( isset( $args['formats'] ) ) {
		$formats += $args['formats'];
	}
	$mce_init['style_formats'] = wp_json_encode( $formats );
	return $mce_init;
}

/**
 * Sets used heading tags.
 *
 * @param int $first_level First level of heading tag.
 * @param int $count       Count of headings.
 */
function set_used_heading( int $first_level = 2, int $count = 3 ): void {
	if ( ! is_admin() ) {
		return;
	}
	$hs = array_map(
		function ( $l ) {
			return "Heading $l=h$l";
		},
		range( $first_level, $first_level + $count - 1 )
	);
	_call_on_post_screen(
		function () use ( $hs ) {
			add_filter(
				'tiny_mce_before_init',
				function ( $mce_init ) use ( $hs ) {
					// Original from tinymce.min.js "Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre".
					$mce_init['block_formats'] = 'Paragraph=p;' . implode( ';', $hs ) . ';Preformatted=pre';
					return $mce_init;
				}
			);
		}
	);
}

/**
 * Disables table resizing function.
 */
function disable_table_resizing(): void {
	if ( ! is_admin() ) {
		return;
	}
	_call_on_post_screen(
		function () {
			add_filter(
				'tiny_mce_before_init',
				function ( $mce_init ) {
					$mce_init['table_resize_bars'] = false;
					$mce_init['object_resizing']   = 'img';
					return $mce_init;
				}
			);
		}
	);
}


// -----------------------------------------------------------------------------


/**
 * Adds quick tags.
 */
function add_quick_tags(): void {
	if ( ! is_admin() ) {
		return;
	}
	_call_on_post_screen(
		function () {
			add_action( 'admin_print_footer_scripts', '\wpinc\sys\classic_editor\_cb_admin_print_footer_scripts', 99, 0 );
		},
		true
	);
}

/**
 * Callback function for 'admin_print_footer_scripts' action.
 *
 * @access private
 */
function _cb_admin_print_footer_scripts(): void {
	if ( wp_script_is( 'quicktags' ) ) {  // cspell:disable-line.
		?>
		<script>
		QTags.addButton('qt-small', 'small', '<small>', '</small>');
		QTags.addButton('qt-h4', 'h4', '<h4>', '</h4>');
		QTags.addButton('qt-h5', 'h5', '<h5>', '</h5>');
		QTags.addButton('qt-h6', 'h6', '<h6>', '</h6>');
		</script>
		<?php
	}
}


// -----------------------------------------------------------------------------


/**
 * Calls a function on the post screens.
 *
 * @access private
 *
 * @param callable $f            A function to be called.
 * @param bool     $only_classic Whether it is called only when the classic editor.
 */
function _call_on_post_screen( callable $f, bool $only_classic = false ): void {
	if ( did_action( 'current_screen' ) ) {
		_cb_current_screen( $f, $only_classic );
	} else {
		add_action(
			'current_screen',
			function () use ( $f, $only_classic ) {
				_cb_current_screen( $f, $only_classic );
			}
		);
	}
}

/**
 * Callback function for 'current_screen' hook.
 *
 * @access private
 * @global string $pagenow
 *
 * @param callable $f            A function to be called.
 * @param bool     $only_classic Whether it is called only when the classic editor.
 */
function _cb_current_screen( callable $f, bool $only_classic ): void {
	global $pagenow;
	if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		if ( $only_classic ) {
			$cs = get_current_screen();
			if ( $cs && ! $cs->is_block_editor() ) {
				$f();
			}
		} else {
			$f();
		}
	}
}
