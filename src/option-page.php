<?php
/**
 * Custom Option Page
 *
 * @package Wpinc Sys
 * @author Takuto Yanagida
 * @version 2024-03-13
 */

declare(strict_types=1);

namespace wpinc\sys\option_page;

/** phpcs:ignore
 * Activates custom option page.
 *
 * phpcs:ignore
 * @param array{
 *     page_title?  : string,
 *     menu_title?  : string,
 *     slug?        : string,
 *     option_key?  : string,
 *     as_menu_page?: bool,
 *     sections?    : array<string, array{ label: string, fields: array<string, array{ type: string, label: string, description: string|null, filter: callable|null }> }>,
 * } $args Arguments.
 * $args {
 *     Arguments.
 *
 *     @type string 'page_title'   The text to be displayed in the title tags of the page when the menu is selected.
 *     @type string 'menu_title'   The text to be used for the menu.
 *     @type string 'slug'         Slug used as the menu name and the option name.
 *     @type string 'option_key'   Option key.
 *     @type bool   'as_menu_page' Whether to add the menu item to side menu. Default false.
 *     @type array  'sections'     Section parameters.
 * }
 */
function activate( array $args ): void {
	$args += array(
		'page_title'   => '',
		'menu_title'   => '',
		'slug'         => '',
		'option_key'   => '',
		'as_menu_page' => false,
		'sections'     => array(),
	);
	if ( '' === $args['page_title'] && '' !== $args['menu_title'] ) {
		$args['page_title'] = $args['menu_title'];
	}
	if ( '' !== $args['page_title'] && '' === $args['menu_title'] ) {
		$args['menu_title'] = $args['page_title'];
	}
	foreach ( $args['sections'] as $_sid => &$cont ) {
		$cont += array(
			'label'  => '',
			'fields' => array(),
		);
		foreach ( $cont['fields'] as $_key => &$params ) {
			$params += array(
				'type'        => '',
				'label'       => '',
				'description' => null,
				'filter'      => null,
			);
		}
	}

	$inst = _get_instance();

	$inst->page_title   = $args['page_title'];    // @phpstan-ignore-line
	$inst->menu_title   = $args['menu_title'];    // @phpstan-ignore-line
	$inst->slug         = $args['slug'];          // @phpstan-ignore-line
	$inst->option_key   = $args['option_key'];    // @phpstan-ignore-line
	$inst->as_menu_page = $args['as_menu_page'];  // @phpstan-ignore-line
	$inst->sections     = $args['sections'];      // @phpstan-ignore-line

	add_action( 'admin_menu', '\wpinc\sys\option_page\_cb_admin_menu', 10, 0 );
	add_action( 'admin_init', '\wpinc\sys\option_page\_cb_admin_init', 10, 0 );

	if ( $inst->as_menu_page ) {
		add_filter( "option_page_capability_{$inst->slug}", '\wpinc\sys\option_page\_cb_option_page_capability' );
	}
}

/**
 * Callback function for 'admin_menu' action.
 *
 * @access private
 */
function _cb_admin_menu(): void {
	$inst = _get_instance();
	if ( $inst->as_menu_page ) {
		add_menu_page(
			$inst->page_title,
			$inst->menu_title,
			'edit_pages',
			$inst->slug,
			'\wpinc\sys\option_page\_cb_output_html'
		);
	} else {
		add_submenu_page(
			'options-general.php',
			$inst->page_title,
			$inst->menu_title,
			'manage_options',
			$inst->slug,
			'\wpinc\sys\option_page\_cb_output_html'
		);
	}
}

/**
 * Callback function for outputting HTML.
 *
 * @access private
 */
function _cb_output_html(): void {
	$inst = _get_instance();
	?>
	<div class="wrap">
		<h2><?php echo esc_html( $inst->page_title ); ?></h2>
		<form method="post" action="options.php">
	<?php
			settings_fields( $inst->slug );
			do_settings_sections( $inst->slug );
			submit_button();
	?>
		</form>
	</div>
	<?php
}

/**
 * Callback function for 'admin_init' action.
 *
 * @access private
 */
function _cb_admin_init(): void {
	$inst = _get_instance();
	$vals = get_option( $inst->option_key );

	register_setting(
		$inst->slug,
		$inst->option_key,
		array(
			'sanitize_callback' => '\wpinc\sys\option_page\_cb_sanitize',
		)
	);
	foreach ( $inst->sections as $sid => $cont ) {
		add_settings_section( $sid, $cont['label'], '__return_false', $inst->slug );

		foreach ( $cont['fields'] as $key => $params ) {
			/** @psalm-suppress InvalidArgument */  // phpcs:ignore
			add_settings_field(
				$key,
				$params['label'],
				'\wpinc\sys\option_page\_cb_output_html_field',
				$inst->slug,
				$sid,
				array( $key, $params, $vals )
			);
		}
	}
}

/**
 * Callback function for 'option_page_capability_{$option_page}' filter.
 *
 * @param string $capability The capability used for the page, which is manage_options by default.
 * @return string
 */
function _cb_option_page_capability( string $capability ): string {
	$inst = _get_instance();
	return $inst->as_menu_page ? 'edit_pages' : $capability;
}

/**
 * Callback function for sanitizing input data.
 *
 * @access private
 *
 * @param array<string, mixed> $input Input data.
 * @return array<string, mixed> Sanitized data.
 */
function _cb_sanitize( array $input ): array {
	$inst = _get_instance();
	$new  = array();

	foreach ( $inst->sections as $_sid => $cont ) {
		foreach ( $cont['fields'] as $key => $params ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}
			$filter = $params['filter'];
			if ( is_callable( $filter ) ) {
				$new[ $key ] = call_user_func( $filter, $input[ $key ] );
			} else {
				$new[ $key ] = $input[ $key ];
			}
		}
	}
	return $new;
}

/** phpcs:ignore
 * Callback function for outputting HTML fields.
 *
 * @access private
 * phpcs:ignore
 * @param array{
 *     string,
 *     array{ type: string, label: string, description?: string, filter?: callable, choices?: array<string, string> },
 *     array<string, string|null>,
 * } $args Arguments.
 * $args {
 *     Arguments.
 *
 *     @type string 0 Sub key of the option.
 *     @type array  1 Parameters of the field.
 *     @type array  2 Array of values.
 * }
 */
function _cb_output_html_field( array $args ): void {
	list( $key, $params, $vals ) = $args;

	$inst = _get_instance();
	$name = "{$inst->option_key}[{$key}]";
	$desc = $params['description'] ?? '';
	$val  = $vals[ $key ] ?? null;
	$chs  = $params['choices'] ?? array();

	switch ( $params['type'] ) {
		case 'checkbox':
			_echo_checkbox( $val, $key, $name, $desc );
			break;
		case 'radio_buttons':
			_echo_radio_buttons( $val, $key, $name, $chs );
			break;
		case 'textarea':
			_echo_textarea( $val, $key, $name, $desc );
			break;
		default:
			_echo_input( $val, $key, $name, $params['type'], $desc );
			break;
	}
}

/**
 * Displays input fields.
 *
 * @access private
 *
 * @param string|null $val  Current value.
 * @param string      $key  Sub key of the option.
 * @param string      $name Name attribute.
 * @param string      $type Type attribute.
 * @param string      $desc (Optional) Description. Default ''.
 */
function _echo_input( ?string $val, string $key, string $name, string $type, string $desc = '' ): void {
	printf(
		'<input type="%s" id="%s" name="%s" value="%s" class="regular-text" aria-describedby="%s-description">',
		esc_attr( $type ),
		esc_attr( $key ),
		esc_attr( $name ),
		esc_attr( $val ?? '' ),
		esc_attr( $key )
	);
	if ( '' !== $desc ) {
		printf(
			'<p class="description" id="%s-description">%s</p>',
			esc_attr( $key ),
			esc_html( $desc )
		);
	}
}

/**
 * Displays textarea fields.
 *
 * @access private
 *
 * @param string|null $val  Current value.
 * @param string      $key  Sub key of the option.
 * @param string      $name Name attribute.
 * @param string      $desc (Optional) Description. Default ''.
 */
function _echo_textarea( ?string $val, string $key, string $name, string $desc = '' ): void {
	if ( '' !== $desc ) {
		printf(
			'<label for="%s">%s</label>',
			esc_attr( $key ),
			esc_html( $desc )
		);
	}
	printf(
		'<p><textarea id="%s" name="%s" rows="10" class="large-text" aria-describedby="%s-description">%s</textarea></p>',
		esc_attr( $key ),
		esc_attr( $name ),
		esc_attr( $key ),
		esc_attr( $val ?? '' )
	);
}

/**
 * Displays checkbox fields.
 *
 * @access private
 *
 * @param string|null $val  Current value.
 * @param string      $_key Sub key of the option.
 * @param string      $name Name attribute.
 * @param string      $desc (Optional) Description. Default ''.
 */
function _echo_checkbox( ?string $val, string $_key, string $name, string $desc = '' ): void {
	printf(
		'<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
		esc_attr( $name ),
		'1' === ( $val ?? '' ) ? ' checked' : '',
		esc_html( $desc )
	);
}

/**
 * Displays radio button fields.
 *
 * @access private
 *
 * @param string|null           $val  Current value.
 * @param string                $_key Sub key of the option.
 * @param string                $name Name attribute.
 * @param array<string, string> $chs  Array of choices to labels.
 */
function _echo_radio_buttons( ?string $val, string $_key, string $name, array $chs ): void {
	foreach ( $chs as $ch => $label ) {
		printf(
			'<p><label><input type="radio" name="%s" value="%s"%s> %s</label></p>',
			esc_attr( $name ),
			esc_attr( $ch ),
			( $val ?? '' ) === $ch ? ' checked' : '',
			esc_html( $label )
		);
	}
}


// -----------------------------------------------------------------------------


/**
 * Gets instance.
 *
 * @access private
 *
 * @return object{
 *     page_title  : string,
 *     menu_title  : string,
 *     slug        : string,
 *     option_key  : string,
 *     as_menu_page: bool,
 *     sections    : array<string, array{ label: string, fields: array<string, array{ type: string, label: string, description: string|null, filter: callable|null }> }>,
 * } Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {
		/**
		 * The text to be displayed in the title tags of the page.
		 *
		 * @var string
		 */
		public $page_title = '';

		/**
		 * The text to be used for the menu.
		 *
		 * @var string
		 */
		public $menu_title = '';

		/**
		 * The slug name to refer to this menu by.
		 *
		 * @var string
		 */
		public $slug = '';

		/**
		 * Name of the option to retrieve.
		 *
		 * @var string
		 */
		public $option_key = '';

		/**
		 * Whether to add the option as menu page.
		 *
		 * @var bool
		 */
		public $as_menu_page = false;

		/**
		 * Sections of the option page.
		 *
		 * @var array<string, array{ label: string, fields: array<string, array{ type: string, label: string, description: string|null, filter: callable|null }> }>
		 */
		public $sections = array();
	};
	return $values;
}
