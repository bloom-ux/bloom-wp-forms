<?php
/**
 * Página de ajustes de formularios con pestañas
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Helpers\Abstract_Admin;

/**
 * Página de ajustes del plugin
 *
 * Submenú "Ajustes" dentro del menú "Formularios". La página muestra un
 * listado de pestañas (array filtrable) y solo el contenido de la pestaña
 * activa, seleccionada por parámetro GET. El slug de cada pestaña sirve como
 * key dentro del option_value. Al guardar, los ajustes nuevos se mezclan con
 * los anteriores (solo la pestaña activa se postea).
 */
class Settings_Admin extends Abstract_Admin {

	const OPTION_NAME = 'bloom_forms_settings';

	/**
	 * Obtener el ID de la página de administración
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'bloom_forms_settings';
	}

	/**
	 * Obtener el título de la página de administración
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Ajustes de formularios', 'bloom-wp-forms' );
	}

	/**
	 * Obtener el título mostrado en el menú
	 *
	 * @return string
	 */
	public function get_menu_title(): string {
		return __( 'Ajustes', 'bloom-wp-forms' );
	}

	/**
	 * Obtener el slug de la página padre
	 *
	 * @return string
	 */
	public function get_parent_page(): string {
		return 'bloom_forms_entries_admin';
	}

	/**
	 * Obtener la capacidad requerida para acceder a la página
	 *
	 * @return string
	 */
	public function get_required_capability(): string {
		return 'manage_options';
	}

	/**
	 * Obtener los elementos del formulario de administración
	 *
	 * La página no usa el formulario de Queulat de Abstract_Admin::admin_page().
	 *
	 * @return array
	 */
	public function get_form_elements(): array {
		return array();
	}

	/**
	 * Sanitizar datos del formulario de administración
	 *
	 * @param array $input Datos enviados por el formulario de administración.
	 * @return array
	 */
	public function sanitize_data( $input ): array {
		return array();
	}

	/**
	 * Obtener reglas de validación del formulario de administración
	 *
	 * @param array $sanitized_data Datos saneados.
	 * @return array
	 */
	public function get_validation_rules( array $sanitized_data ): array {
		return array();
	}

	/**
	 * Obtener el ícono de la página de administración
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return '';
	}

	/**
	 * Procesar datos del formulario de administración
	 *
	 * @param array $data Datos validados y saneados.
	 * @return bool
	 */
	public function process_data( array $data ): bool {
		return true;
	}

	/**
	 * Inicializar acciones de la página de administración
	 *
	 * No llama a parent::init() porque la página no usa el formulario de
	 * Queulat (evita el process_form de Abstract_Admin).
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'bloom_forms_settings_tab__general', array( static::class, 'render_general_tab' ) );
		add_filter( 'queulat/forms/element/recaptcha__site-key', array( static::class, 'filter_recaptcha_site_key' ) );
		add_filter( 'queulat/forms/element/recaptcha__site-secret', array( static::class, 'filter_recaptcha_site_secret' ) );
	}

	/**
	 * Registrar la opción y el grupo de ajustes
	 *
	 * @return void
	 */
	public function register_settings() {
		static::ensure_option();
		register_setting(
			'bloom_forms_settings_group',
			static::OPTION_NAME,
			array(
				'default'           => static::get_defaults(),
				'sanitize_callback' => array( static::class, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Obtener valores por defecto de la opción
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'general' => array(
				'recaptcha_site_key'    => '',
				'recaptcha_site_secret' => '',
			),
		);
	}

	/**
	 * Asegurar que la opción exista con su valor por defecto
	 *
	 * Crea la opción con autoload desactivado si aún no existe.
	 *
	 * @return void
	 */
	public static function ensure_option(): void {
		if ( false === get_option( static::OPTION_NAME, false ) ) {
			add_option( static::OPTION_NAME, static::get_defaults(), '', false );
		}
	}

	/**
	 * Obtener ajustes del plugin con valores por defecto
	 *
	 * @return array Ajustes del plugin
	 */
	public static function get_settings(): array {
		return wp_parse_args(
			get_option( static::OPTION_NAME, array() ),
			static::get_defaults()
		);
	}

	/**
	 * Comprobar si reCAPTCHA está configurado
	 *
	 * @return bool
	 */
	public static function is_recaptcha_configured(): bool {
		$settings = static::get_settings();
		return ! empty( $settings['general']['recaptcha_site_key'] ) && ! empty( $settings['general']['recaptcha_site_secret'] );
	}

	/**
	 * Sanitizar ajustes enviados desde el formulario
	 *
	 * Solo la pestaña activa se postea; las demás keys del option_value se
	 * conservan del valor guardado (mezcla a nivel de keys top-level).
	 *
	 * @param array $input Datos enviados por el formulario de ajustes.
	 * @return array Datos saneados
	 */
	public static function sanitize_settings( array $input ): array {
		$existing            = get_option( static::OPTION_NAME, array() );
		$settings            = wp_parse_args( (array) $input, $existing );
		$settings['general'] = array(
			'recaptcha_site_key'    => sanitize_text_field( $settings['general']['recaptcha_site_key'] ?? '' ),
			'recaptcha_site_secret' => sanitize_text_field( $settings['general']['recaptcha_site_secret'] ?? '' ),
		);
		return apply_filters( 'bloom_forms_settings_sanitize', $settings, $input );
	}

	/**
	 * Filtrar la site key hacia el elemento Recaptcha de Queulat
	 *
	 * @param string $site_key Valor entrante del filtro de Queulat.
	 * @return string Site key configurada o valor entrante
	 */
	public static function filter_recaptcha_site_key( string $site_key ): string {
		$settings = static::get_settings();
		return ! empty( $settings['general']['recaptcha_site_key'] ) ? $settings['general']['recaptcha_site_key'] : $site_key;
	}

	/**
	 * Filtrar el site secret hacia el elemento Recaptcha de Queulat
	 *
	 * @param string $site_secret Valor entrante del filtro de Queulat.
	 * @return string Site secret configurado o valor entrante
	 */
	public static function filter_recaptcha_site_secret( string $site_secret ): string {
		$settings = static::get_settings();
		return ! empty( $settings['general']['recaptcha_site_secret'] ) ? $settings['general']['recaptcha_site_secret'] : $site_secret;
	}

	/**
	 * Obtener las pestañas de la página de ajustes
	 *
	 * El slug de cada pestaña es la key dentro del option_value.
	 *
	 * @return array Pestañas en formato slug => label
	 */
	public static function get_tabs(): array {
		$tabs = array( 'general' => __( 'General', 'bloom-wp-forms' ) );
		return apply_filters( 'bloom_forms_settings_tabs', $tabs );
	}

	/**
	 * Obtener la pestaña activa
	 *
	 * @return string Slug de la pestaña activa
	 */
	public static function get_current_tab(): string {
		$tab = sanitize_key( filter_input( INPUT_GET, 'tab' ) ?? '' );
		if ( '' === $tab || ! array_key_exists( $tab, static::get_tabs() ) ) {
			return (string) array_key_first( static::get_tabs() );
		}
		return $tab;
	}

	/**
	 * Renderizar la página de administración
	 *
	 * Solo se renderiza la pestaña activa; la mezcla al guardar la garantiza
	 * sanitize_settings().
	 *
	 * @return void
	 */
	public function admin_page() {
		$tabs    = static::get_tabs();
		$current = static::get_current_tab();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Ajustes de formularios', 'bloom-wp-forms' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => static::OPTION_NAME,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$class = 'nav-tab' . ( $slug === $current ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'bloom_forms_settings_group' );
		echo '<div class="bloom-forms-settings-tab-panel" data-tab="' . esc_attr( $current ) . '">';
		do_action( "bloom_forms_settings_tab__{$current}" );
		echo '</div>';
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Renderizar la pestaña General
	 *
	 * @return void
	 */
	public static function render_general_tab() {
		$settings = static::get_settings();
		?>
		<h2><?php echo esc_html__( 'reCAPTCHA v2', 'bloom-wp-forms' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Configura las claves del widget "No soy un robot" (reCAPTCHA v2 checkbox). Se obtienen en Google reCAPTCHA Admin (https://www.google.com/recaptcha/admin).', 'bloom-wp-forms' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bloom-forms-recaptcha-site-key"><?php echo esc_html__( 'Site key', 'bloom-wp-forms' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" id="bloom-forms-recaptcha-site-key" name="bloom_forms_settings[general][recaptcha_site_key]" value="<?php echo esc_attr( $settings['general']['recaptcha_site_key'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bloom-forms-recaptcha-site-secret"><?php echo esc_html__( 'Site secret', 'bloom-wp-forms' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" id="bloom-forms-recaptcha-site-secret" name="bloom_forms_settings[general][recaptcha_site_secret]" value="<?php echo esc_attr( $settings['general']['recaptcha_site_secret'] ); ?>">
				</td>
			</tr>
		</table>
		<p class="description">
			<?php echo esc_html__( 'Sin claves configuradas el formulario funciona sin captcha. También se pueden definir vía constantes RECAPTCHA_SITE_KEY / RECAPTCHA_SITE_SECRET o variables de entorno.', 'bloom-wp-forms' ); ?>
		</p>
		<?php
	}
}
