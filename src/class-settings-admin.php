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
 *
 * Cada formulario registrado agrega automáticamente su pestaña de
 * destinatarios: slug = slug del formulario, label = título del formulario y
 * payload `recipients` (lista de correos) dentro de esa key. Una
 * implementación puede reemplazar el panel por defecto registrando la acción
 * `bloom_forms_settings_tab__{slug}`.
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
				'recaptcha_type'        => 'v3',
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
	 * Usa la resolución efectiva de Queulat: constante RECAPTCHA_SITE_KEY /
	 * RECAPTCHA_SITE_SECRET, variable de entorno o pestaña General.
	 *
	 * @return bool
	 */
	public static function is_recaptcha_configured(): bool {
		if ( ! class_exists( '\Queulat\Forms\Element\Recaptcha' ) ) {
			return false;
		}
		return '' !== \Queulat\Forms\Element\Recaptcha::get_site_key() && '' !== \Queulat\Forms\Element\Recaptcha::get_site_secret();
	}

	/**
	 * Sanitizar ajustes enviados desde el formulario
	 *
	 * Solo la pestaña activa se postea; las demás keys del option_value se
	 * conservan del valor guardado (mezcla a nivel de keys top-level). El
	 * input puede llegar null cuando la pestaña no tiene campos postables
	 * (p. ej. todos fijados por entorno y deshabilitados).
	 *
	 * @param mixed $input Datos enviados por el formulario de ajustes.
	 * @return array Datos saneados
	 */
	public static function sanitize_settings( $input ): array {
		$input               = is_array( $input ) ? $input : array();
		$existing            = get_option( static::OPTION_NAME, array() );
		$settings            = wp_parse_args( (array) $input, $existing );
		$type                = $settings['general']['recaptcha_type'] ?? $existing['general']['recaptcha_type'] ?? 'v3';
		$settings['general'] = array(
			'recaptcha_type'        => in_array( $type, array( 'v2', 'v3' ), true ) ? $type : 'v3',
			'recaptcha_site_key'    => sanitize_text_field( $settings['general']['recaptcha_site_key'] ?? '' ),
			'recaptcha_site_secret' => sanitize_text_field( $settings['general']['recaptcha_site_secret'] ?? '' ),
		);
		// Cada formulario registrado es una pestaña cuyo payload es la lista
		// de destinatarios; solo se toca la key del formulario activo (las
		// pestañas propias de una implementación quedan a su cargo vía
		// bloom_forms_settings_sanitize).
		foreach ( Plugin::get_instance()->get_registered_forms_slugs() as $slug ) {
			if ( ! array_key_exists( $slug, $input ) ) {
				continue;
			}
			$form_input        = is_array( $input[ $slug ] ) ? $input[ $slug ] : array();
			$settings[ $slug ] = array(
				'recipients' => static::sanitize_recipients( $form_input['recipients'] ?? '' ),
			);
		}
		return apply_filters( 'bloom_forms_settings_sanitize', $settings, $input );
	}

	/**
	 * Sanitizar la lista de correos de la pestaña de un formulario.
	 *
	 * Los tokens inválidos o duplicados se descartan avisando con settings
	 * error: no se rechaza el guardado completo (la API de Settings no permite
	 * re-render del formulario con valores conservados; descartar lo inválido
	 * es preferible a perder el guardado por un typo). La API de Settings
	 * puede invocar el saneamiento más de una vez (update_option cae en
	 * add_option cuando el valor guardado iguala el default registrado): un
	 * array de correos ya saneado es una entrada válida, idempotente.
	 *
	 * @param mixed $raw String de correos separados por coma o lista ya saneada.
	 * @return array Lista de correos.
	 */
	public static function sanitize_recipients( $raw ): array {
		if ( is_array( $raw ) ) {
			$raw = implode( ', ', $raw );
		}
		$raw    = (string) $raw;
		$tokens = array_filter( preg_split( '/[,;\s]+/', $raw ), 'strlen' );
		$emails = array_values( Plugin::parse_string_emails( $raw ) );
		if ( count( $tokens ) !== count( $emails ) ) {
			add_settings_error(
				static::OPTION_NAME,
				'bloom_forms_recipients_invalid',
				__( 'Se descartaron correos electrónicos inválidos o duplicados al guardar los destinatarios.', 'bloom-wp-forms' ),
				'warning'
			);
		}
		return $emails;
	}

	/**
	 * Obtener el mapa de destinatarios configurados por formulario registrado.
	 *
	 * @return array Mapa slug => lista de correos (solo formularios con correos).
	 */
	public static function get_recipients(): array {
		$recipients = array();
		foreach ( Plugin::get_instance()->get_registered_forms_slugs() as $slug ) {
			$form_recipients = static::get_form_recipients( $slug );
			if ( $form_recipients ) {
				$recipients[ $slug ] = $form_recipients;
			}
		}
		return $recipients;
	}

	/**
	 * Obtener los destinatarios de un formulario.
	 *
	 * @param string $slug Slug del formulario.
	 * @return array Lista de correos (vacía si no está configurado).
	 */
	public static function get_form_recipients( string $slug ): array {
		$settings   = static::get_settings();
		$recipients = $settings[ $slug ]['recipients'] ?? null;
		return is_array( $recipients ) ? array_values( $recipients ) : array();
	}

	/**
	 * Obtener el tipo de reCAPTCHA configurado
	 *
	 * Valores posibles: 'v3' (invisible, sin checkbox) y 'v2' (checkbox "No soy
	 * un robot"). Prioridad: constante RECAPTCHA_VERSION, variable de entorno
	 * RECAPTCHA_VERSION, pestaña General. Cualquier otro valor cae en 'v3'.
	 *
	 * @return string Tipo de reCAPTCHA ('v3' o 'v2').
	 */
	public static function get_recaptcha_type(): string {
		if ( defined( 'RECAPTCHA_VERSION' ) ) {
			$type = (string) RECAPTCHA_VERSION;
		} elseif ( function_exists( 'env' ) && env( 'RECAPTCHA_VERSION' ) ) {
			$type = (string) env( 'RECAPTCHA_VERSION' );
		} else {
			$settings = static::get_settings();
			$type     = (string) ( $settings['general']['recaptcha_type'] ?? 'v3' );
		}
		$type = (string) apply_filters( 'bloom_forms_recaptcha_type', $type );
		return in_array( $type, array( 'v2', 'v3' ), true ) ? $type : 'v3';
	}

	/**
	 * Obtener el valor de reCAPTCHA fijado por constante o variable de entorno
	 *
	 * No reutiliza Queulat\Forms\Element\Recaptcha::get_site_key()/get_site_secret()
	 * porque esos aplican el filtro de la pestaña como último recurso y no
	 * permiten distinguir si el valor vino de entorno/constante.
	 *
	 * @param string $constant Nombre de la constante (p. ej. RECAPTCHA_SITE_KEY).
	 * @return string Valor fijado, o '' si no lo hay.
	 */
	public static function get_env_recaptcha_value( string $constant ): string {
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}
		if ( function_exists( 'env' ) && env( $constant ) ) {
			return (string) env( $constant );
		}
		return '';
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
	 * El slug de cada pestaña es la key dentro del option_value. Cada
	 * formulario registrado agrega su propia pestaña (label = título del
	 * formulario); un slug en colisión con una pestaña existente se respeta.
	 *
	 * @return array Pestañas en formato slug => label
	 */
	public static function get_tabs(): array {
		$tabs = array( 'general' => __( 'General', 'bloom-wp-forms' ) );
		foreach ( Plugin::get_instance()->get_forms() as $slug => $form ) {
			if ( ! isset( $tabs[ $slug ] ) ) {
				$tabs[ $slug ] = $form->get_title();
			}
		}
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
		if ( has_action( "bloom_forms_settings_tab__{$current}" ) ) {
			do_action( "bloom_forms_settings_tab__{$current}" );
		} else {
			// Pestaña automática de un formulario registrado.
			static::render_form_recipients_tab( $current );
		}
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
		$settings    = static::get_settings();
		$type        = static::get_recaptcha_type();
		$env_version = static::get_env_recaptcha_value( 'RECAPTCHA_VERSION' );
		$env_key     = static::get_env_recaptcha_value( 'RECAPTCHA_SITE_KEY' );
		$env_secret  = static::get_env_recaptcha_value( 'RECAPTCHA_SITE_SECRET' );
		?>
		<h2><?php echo esc_html__( 'reCAPTCHA', 'bloom-wp-forms' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Configura el tipo de captcha a usar y sus claves. Se obtienen en Google reCAPTCHA Admin (https://www.google.com/recaptcha/admin) y deben corresponder al tipo elegido.', 'bloom-wp-forms' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bloom-forms-recaptcha-type"><?php echo esc_html__( 'Tipo de reCAPTCHA', 'bloom-wp-forms' ); ?></label></th>
				<td>
					<select id="bloom-forms-recaptcha-type" name="bloom_forms_settings[general][recaptcha_type]" <?php disabled( '' !== $env_version ); ?>>
						<option value="v3" <?php selected( $type, 'v3' ); ?>><?php echo esc_html__( 'Sin checkbox (reCAPTCHA v3, invisible)', 'bloom-wp-forms' ); ?></option>
						<option value="v2" <?php selected( $type, 'v2' ); ?>><?php echo esc_html__( 'Con checkbox "No soy un robot" (reCAPTCHA v2)', 'bloom-wp-forms' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bloom-forms-recaptcha-site-key"><?php echo esc_html__( 'Site key', 'bloom-wp-forms' ); ?></label></th>
				<td>
					<?php if ( '' !== $env_key ) : ?>
						<input class="regular-text" type="password" id="bloom-forms-recaptcha-site-key" value="<?php echo esc_attr( $env_key ); ?>" disabled>
					<?php else : ?>
						<input class="regular-text" type="text" id="bloom-forms-recaptcha-site-key" name="bloom_forms_settings[general][recaptcha_site_key]" value="<?php echo esc_attr( $settings['general']['recaptcha_site_key'] ); ?>">
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bloom-forms-recaptcha-site-secret"><?php echo esc_html__( 'Site secret', 'bloom-wp-forms' ); ?></label></th>
				<td>
					<?php if ( '' !== $env_secret ) : ?>
						<input class="regular-text" type="password" id="bloom-forms-recaptcha-site-secret" value="<?php echo esc_attr( $env_secret ); ?>" disabled>
					<?php else : ?>
						<input class="regular-text" type="text" id="bloom-forms-recaptcha-site-secret" name="bloom_forms_settings[general][recaptcha_site_secret]" value="<?php echo esc_attr( $settings['general']['recaptcha_site_secret'] ); ?>">
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<p class="description">
			<?php echo esc_html__( 'Sin claves configuradas el formulario funciona sin captcha. Las claves se pueden definir vía constantes RECAPTCHA_SITE_KEY / RECAPTCHA_SITE_SECRET o variables de entorno (tienen prioridad sobre esta pestaña); el tipo se puede fijar con RECAPTCHA_VERSION.', 'bloom-wp-forms' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderizar la pestaña de destinatarios de un formulario
	 *
	 * El valor es el string de correos separados por coma que
	 * sanitize_recipients() vuelve a parsear al guardar.
	 *
	 * @param string $slug Slug del formulario (key de la pestaña).
	 * @return void
	 */
	public static function render_form_recipients_tab( string $slug ) {
		settings_errors( static::OPTION_NAME );
		$form = Plugin::get_instance()->get_form( $slug );
		if ( ! $form ) {
			return;
		}
		?>
		<h2><?php echo esc_html( $form->get_title() ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Correos a los que se notifica cada envío de este formulario; sepáralos con coma. Un formulario sin destinatarios no genera notificaciones.', 'bloom-wp-forms' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="bloom-forms-recipients-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html__( 'Destinatarios', 'bloom-wp-forms' ); ?></label>
				</th>
				<td>
					<input class="regular-text" type="text" id="bloom-forms-recipients-<?php echo esc_attr( $slug ); ?>" name="bloom_forms_settings[<?php echo esc_attr( $slug ); ?>][recipients]" value="<?php echo esc_attr( implode( ', ', static::get_form_recipients( $slug ) ) ); ?>" placeholder="correo@ejemplo.cl, otro@ejemplo.cl">
				</td>
			</tr>
		</table>
		<?php
	}
}
