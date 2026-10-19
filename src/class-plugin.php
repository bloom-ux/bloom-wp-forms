<?php
/**
 * Controlador principal del plugin
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use WP_CLI;
use Monolog\Level;
use Monolog\Logger;
use Queulat\Singleton;
use Queulat\Helpers\Webpack_Asset_Loader;
use WP_Role;

use function Bloom_UX\WPDB_Monolog\set_channel_level;
use function Bloom_UX\WPDB_Monolog\get_logger_for_channel;

/**
 * Clase principal del plugin
 *
 * Inicializa servicios e integración funcional con WordPress
 */
class Plugin {

	use Singleton;

	const VERSION                              = '0.3.0';
	const INSTALLER_VERSION_OPT_NAME           = 'bloom_forms_version';
	const ENTRIES_TABLE_NAME                   = 'bloom_forms_entries';
	const NOTIFICATIONS_TABLE_NAME             = 'bloom_forms_notifications';
	const DEFAULT_READ_CAPABILITY              = 'bloom_forms_read_entries';

	/**
	 * Formularios registrados
	 *
	 * @var array|Form[]
	 */
	private array $forms = array();

	/**
	 * Errores de validación del último POST, por slug de formulario
	 *
	 * @var array
	 */
	private array $submit_errors = array();

	/**
	 * Pantalla de administración de envíos de formulario
	 *
	 * @var Entries_Admin
	 */
	public $entries_admin;

	/**
	 * Pantalla de administración de notificaciones
	 *
	 * @var Notifications_Admin
	 */
	public $notifications_admin;

	/**
	 * Pantalla de administración de ajustes
	 *
	 * @var Settings_Admin
	 */
	public $settings_admin;

	/**
	 * Servicio de log
	 *
	 * @var \Monolog\Logger
	 */
	private $logger = null;

	/**
	 * Instancia de la conexión a base de datos
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Tarea asíncrona para envío de notificaciones
	 *
	 * @var Send_Notification_Task
	 */
	private $async_notification_task;

	/**
	 * Cargador de archivos JS y CSS
	 *
	 * @var Webpack_Asset_Loader
	 */
	private $asset_loader;

	/**
	 * Obtener instancia de tarea asíncrona de envío de notificaciones
	 *
	 * @return Send_Notification_Task
	 */
	public function get_async_notification_task() {
		return $this->async_notification_task;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb                            = $wpdb;
		$this->wpdb->bloom_forms_entries       = $wpdb->prefix . static::ENTRIES_TABLE_NAME;
		$this->wpdb->bloom_forms_notifications = $wpdb->prefix . static::NOTIFICATIONS_TABLE_NAME;

		$this->async_notification_task = new Send_Notification_Task();

		$this->asset_loader = new Webpack_Asset_Loader(
			'bloom-forms',
			plugin_dir_path( __DIR__ ) . 'assets/dist/',
			plugins_url( 'assets/dist/', __DIR__ )
		);

		$this->init();
		$this->init_logger();
		$this->init_admin();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'bloom-forms', new CLI() );
		}
	}

	/**
	 * Inicializar integración con WordPress
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'maybe_process_form' ) );
		add_action( 'init', array( $this, 'maybe_resend_notification' ) );
		add_action( 'init', array( $this, 'load_translations' ) );
		add_action( 'bloom_forms__retry_scheduled', array( $this, 'retry_scheduled' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'init', array( $this, 'maybe_admin_redirect' ) );
		add_action( 'switch_blog', array( $this, 'switched_blog' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_filter( 'site_status_tests', array( static::class, 'add_site_health_test' ) );
	}

	/**
	 * Cargar traducciones del plugin
	 *
	 * @return void
	 */
	public function load_translations() {
		load_plugin_textdomain( 'bloom-wp-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Corregir prefijos de tablas al cambiar de blog
	 *
	 * @return void
	 */
	public function switched_blog() {
		$this->wpdb->bloom_forms_entries       = $this->wpdb->prefix . static::ENTRIES_TABLE_NAME;
		$this->wpdb->bloom_forms_notifications = $this->wpdb->prefix . static::NOTIFICATIONS_TABLE_NAME;
	}

	/**
	 * Redirigir ingresos desde links a página de detalle de envío sin parámetro "action"
	 *
	 * @return void
	 */
	public function maybe_admin_redirect() {
		if ( ! is_admin() ) {
			return;
		}
		if ( 'bloomform_entries_admin' !== filter_input( INPUT_GET, 'page' ) ) {
			return;
		}
		if ( empty( filter_input( INPUT_GET, 'entry_id' ) ) || ! empty( filter_input( INPUT_GET, 'action' ) ) ) {
			return;
		}
		$redirect_url = add_query_arg(
			array(
				'action'   => 'bloom_forms_admin__view',
				'entry_id' => filter_input( INPUT_GET, 'entry_id' ),
				'page'     => 'bloomform_entries_admin',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
	}

	/**
	 * Obtener instancia del cargador de assets
	 *
	 * @return Webpack_Asset_Loader
	 */
	public function get_asset_loader(): Webpack_Asset_Loader {
		return $this->asset_loader;
	}

	/**
	 * Encolar scripts y estilos en el backend
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( ! function_exists( 'get_current_screen' ) || ! get_current_screen() ) {
			return;
		}
		if ( 'toplevel_page_bloom_forms_entries_admin' !== get_current_screen()->id ) {
			return;
		}
		$this->asset_loader->enqueue_style( 'backend-styles.css' );
		wp_enqueue_script( 'htmx.org', plugins_url( 'assets/js/htmx.min.js', dirname( __DIR__ ) . '/bloom-wp-forms.php' ), array(), '1.9.10', array( 'strategy' => 'defer' ) );
	}

	/**
	 * Encolar htmx en el front cuando la página tiene formularios
	 *
	 * Los formularios emiten atributos hx-* que requieren htmx; sin el script
	 * degradan a POST normal. La detección mira el post actual: formularios
	 * renderizados solo desde templates no disparan el encolado.
	 *
	 * @return void
	 */
	public function enqueue_front_scripts() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$has_block     = has_block( 'bloom-forms/form-picker', $post );
		$has_shortcode = array_reduce(
			$this->get_registered_forms_slugs(),
			fn( $carry, $slug ) => $carry || has_shortcode( $post->post_content, $slug ),
			false
		);
		if ( ! $has_block && ! $has_shortcode ) {
			return;
		}
		wp_enqueue_script(
			'htmx.org',
			plugins_url( 'assets/js/htmx.min.js', dirname( __DIR__ ) . '/bloom-wp-forms.php' ),
			array(),
			'1.9.10',
			array( 'strategy' => 'defer' )
		);
	}

	/**
	 * Registrar los bloques del plugin
	 *
	 * El bloque Formulario vive en build/blocks/form-picker (generado con
	 * npm run build:blocks). Sin build el sitio sigue funcionando: solo se
	 * registra un aviso en el log.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$block_json = dirname( __DIR__ ) . '/build/blocks/form-picker/block.json';
		if ( ! is_readable( $block_json ) ) {
			$this->logger?->warning( 'Bloque bloom-forms/form-picker sin build; ejecuta npm run build:blocks' );
			return;
		}
		register_block_type(
			dirname( __DIR__ ) . '/build/blocks/form-picker',
			array( 'render_callback' => array( $this, 'render_form_picker' ) )
		);
	}

	/**
	 * Renderizar el bloque Formulario
	 *
	 * El bloque renderiza el shortcode cuyo tag es el slug del formulario;
	 * cada implementación registra ese shortcode (equal al slug registrado
	 * con Plugin::register_form()).
	 *
	 * @param array $attributes Atributos del bloque.
	 * @return string HTML del formulario o aviso para editores.
	 */
	public function render_form_picker( array $attributes ): string {
		$slug = sanitize_key( $attributes['form_slug'] ?? '' );
		if ( '' === $slug ) {
			return '';
		}
		$form = $this->get_form( $slug );
		if ( ! $form || ! shortcode_exists( $slug ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return '';
			}
			return '<p class="bloom-forms-block-missing">' . sprintf(
				/* translators: %s: slug del formulario solicitado. */
				esc_html__( 'El formulario %s no está disponible: no hay un shortcode registrado con ese slug.', 'bloom-wp-forms' ),
				'<code>' . esc_html( $slug ) . '</code>'
			) . '</p>';
		}
		return do_shortcode( "[{$slug}]" );
	}

	/**
	 * Entregar los formularios activos al editor del bloque Formulario
	 *
	 * Los datos viajan como variable global previa al script del bloque; el
	 * handle lo deriva WordPress del editorScript "file:./index.js".
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! wp_script_is( 'bloom-forms-form-picker-editor-script', 'registered' ) ) {
			return; // Bloque sin build.
		}
		wp_enqueue_script( 'bloom-forms-form-picker-editor-script' );
		wp_add_inline_script(
			'bloom-forms-form-picker-editor-script',
			'window.bloomFormsData = ' . wp_json_encode(
				array(
					'forms' => array_map(
						fn( $form ) => array(
							'slug'  => $form->get_slug(),
							'title' => $form->get_title(),
						),
						array_values( $this->get_forms() )
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Reintentar envío de notificaciones programadas
	 *
	 * Se ejecuta automáticamente cada hora
	 *
	 * @return void
	 */
	public function retry_scheduled() {
		$scheduled = Notifications_Repository::get_instance()->find_by_query(
			array(
				'status' => 'scheduled',
			)
		);
		if ( ! $scheduled ) {
			return;
		}
		foreach ( $scheduled as $notification ) {
			$notification->send();
		}
	}

	/**
	 * Reenviar notificación
	 *
	 * @return void
	 */
	public function maybe_resend_notification() {
		if ( ! is_admin() ) {
			return;
		}
		if ( 'bloom_forms__resend_notification' !== sanitize_key( filter_input( INPUT_GET, 'action' ) ) ) {
			return;
		}
		$notification_id = (int) filter_input( INPUT_GET, 'notification_id', FILTER_SANITIZE_NUMBER_INT );
		if ( ! $notification_id ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( filter_input( INPUT_GET, '_wpnonce' ) ), 'bloom_forms__resend_notification--' . $notification_id ) ) {
			wp_die( esc_html__( 'No tienes autorización para hacer eso', 'bloom-wp-forms' ) );
		}
		$notification = Notification::get( $notification_id );
		if ( ! $notification ) {
			wp_die( esc_html__( 'No existe la notificación indicada', 'bloom-wp-forms' ) );
		}
		$notification->send();
		$referer  = wp_get_referer();
		$redirect = add_query_arg(
			array(
				'status' => 'resend-success',
			),
			$referer
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Obtener conteo de notificaciones por estado
	 *
	 * @return array Conteo de notificaciones por estado
	 */
	public function get_notifications_counts_by_status(): array {
		$all_count        = "select count( id ) as q from {$this->wpdb->bloom_forms_notifications}";
		$counts_query     = "select count( id ) as q, JSON_VALUE( status_log, '$[0].status' ) as status from {$this->wpdb->bloom_forms_notifications} group by status";
		$counts_by_status = $this->wpdb->get_results( $counts_query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$counts           = array_reduce(
			$counts_by_status,
			function ( $carry, $item ) {
				$carry[ $item->status ] = $item->q;
				return $carry;
			},
			array(
				'all' => (int) $this->wpdb->get_var( $all_count ), //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);
		return $counts;
	}

	/**
	 * Obtener URL para intentar reenvío de notificación
	 *
	 * @param Notification $notification Instancia de la notificación.
	 * @return string
	 */
	public function get_resend_url( Notification $notification ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'notification_id' => $notification->get_id(),
					'action'          => 'bloom_forms__resend_notification',
				),
				admin_url( 'admin.php' )
			),
			'bloom_forms__resend_notification--' . $notification->get_id()
		);
	}

	/**
	 * Inicializar log de desarrollo
	 *
	 * @return Logger|null
	 */
	public function init_logger(): ?Logger {
		if ( ! $this->logger ) {
			$logger       = function_exists( '\Bloom_UX\WPDB_Monolog\get_logger_for_channel' ) ? get_logger_for_channel( 'bloom_forms' ) : null;
			$this->logger = $logger;
			if ( $logger && function_exists( '\Bloom_UX\WPDB_Monolog\set_channel_level' ) ) {
				set_channel_level( $logger, Level::Debug );
			}
		}
		return $this->logger;
	}

	/**
	 * Obtener instancia de log
	 *
	 * @return Logger|null
	 */
	public function get_logger(): ?Logger {
		return $this->logger;
	}

	/**
	 * Inicializar pantallas de administración
	 *
	 * @return void
	 */
	public function init_admin() {
		// Envíos por formulario.
		$this->entries_admin = new Entries_Admin();
		$this->entries_admin->init();

		// Notificaciones por envío.
		$this->notifications_admin = new Notifications_Admin();
		$this->notifications_admin->init();

		// Ajustes.
		$this->settings_admin = new Settings_Admin();
		$this->settings_admin->init();
	}

	/**
	 * Activación del plugin
	 *
	 * Agregar reglas de reescritura, configurar base de datos y programar tareas asíncronas
	 *
	 * @param bool $networkwide Si la activación es en red.
	 * @return void
	 */
	public function activation_hook( $networkwide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $networkwide ) {
				$wpdb = $this->wpdb;
				$blog_ids = $this->wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE archived = %d and deleted = %d", 0, 0 ) );
				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->activate_for_blog();
					restore_current_blog();
				}
			} else {
				$this->activate_for_blog();
			}
		} else {
			$this->activate_for_blog();
		}
	}

	/**
	 * Desactivación del plugin
	 *
	 * Retirar la capacidad otorgada en la activación y cancelar la tarea programada de reintentos
	 *
	 * @return void
	 */
	public function deactivation_hook() {
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->remove_cap( static::DEFAULT_READ_CAPABILITY );
		}
		wp_clear_scheduled_hook( 'bloom_forms__retry_scheduled' );
	}

	/**
	 * Activar plugin para un blog
	 *
	 * @return void
	 */
	private function activate_for_blog() {
		$this->setup_database();
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->add_cap( static::DEFAULT_READ_CAPABILITY );
		}
		if ( ! wp_next_scheduled( 'bloom_forms__retry_scheduled' ) ) {
			wp_schedule_event(
				time(),
				'hourly',
				'bloom_forms__retry_scheduled'
			);
		}
		flush_rewrite_rules();
	}

	/**
	 * Definir esquema de base de datos
	 *
	 * @return void
	 */
	private function setup_database() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$installed_version = get_option( static::INSTALLER_VERSION_OPT_NAME, '0.0.0' );
		if ( $installed_version >= static::VERSION ) {
			return;
		}
		$charset       = $this->wpdb->get_charset_collate();
		$entries_table = $this->wpdb->bloom_forms_entries;

		// Datos de los formularios.
		$entries_sql = "CREATE TABLE $entries_table (
			id BIGINT( 20 ) UNSIGNED NOT NULL AUTO_INCREMENT,
			form VARCHAR( 255 ) NOT NULL,
			submitted_on DATETIME NOT NULL,
			form_data JSON,
			meta JSON,
			PRIMARY KEY  ( id ),
			KEY form ( form )
		) $charset";
		dbDelta( $entries_sql );

		// Notificaciones.
		$notifications_table = $this->wpdb->bloom_forms_notifications;
		$notifications_sql   = "CREATE TABLE $notifications_table (
			id BIGINT( 20 ) UNSIGNED NOT NULL AUTO_INCREMENT,
			form VARCHAR( 255 ) NOT NULL,
			entry_id BIGINT( 20 ) UNSIGNED NOT NULL,
			email VARCHAR( 255 ) NOT NULL,
			created_on DATETIME NOT NULL,
			status_log JSON,
			meta JSON,
			PRIMARY KEY  ( id ),
			KEY form ( form ),
			KEY entry_id ( entry_id ),
			KEY email ( email )
		) $charset";
		dbDelta( $notifications_sql );

		update_option( static::INSTALLER_VERSION_OPT_NAME, static::VERSION );
	}

	/**
	 * Registrar controlador de formulario
	 *
	 * @param Form $form Instancia del controlador de un formulario.
	 * @return void
	 */
	public function register_form( Form $form ) {
		$this->forms[ $form->get_slug() ] = $form;
	}

	/**
	 * Obtener formularios registrados
	 *
	 * @return array
	 */
	public function get_forms(): array {
		return $this->forms;
	}

	/**
	 * Obtener instancia de un formulario
	 *
	 * @param string $slug Slug del formulario.
	 * @return Form|null Instancia de controlador de formulario o nulo
	 */
	public function get_form( string $slug ): ?Form {
		return $this->forms[ $slug ] ?? null;
	}

	/**
	 * Obtener slugs de formularios registrados
	 *
	 * @return array
	 */
	public function get_registered_forms_slugs(): array {
		return array_keys( $this->forms );
	}

	/**
	 * Obtener los errores de validación del último POST de un formulario
	 *
	 * A diferencia de revalidar el POST, estos errores se calcularon con los
	 * archivos subidos ya procesados.
	 *
	 * @param string $slug Slug del formulario.
	 * @return array Errores indexados por campo (vacío si no hubo).
	 */
	public function get_submit_errors( string $slug ): array {
		return $this->submit_errors[ $slug ] ?? array();
	}

	/**
	 * Agregar el test de destinatarios a Salud del sitio
	 *
	 * @param array $tests Tests registrados (direct/async).
	 * @return array
	 */
	public static function add_site_health_test( array $tests ): array {
		$tests['direct']['bloom_forms_recipients'] = array(
			'label' => __( 'Destinatarios de formularios', 'bloom-wp-forms' ),
			'test'  => array( static::class, 'test_form_recipients' ),
		);
		return $tests;
	}

	/**
	 * Ejecutar el test de destinatarios contra los formularios activos
	 *
	 * @return array Resultado en formato de test directo de Site Health.
	 */
	public static function test_form_recipients(): array {
		return static::get_form_recipients_test_result(
			static::get_instance()->get_forms(),
			Settings_Admin::get_recipients()
		);
	}

	/**
	 * Calcular el resultado del test de destinatarios (puro, testeable)
	 *
	 * @param array $forms      Formularios registrados.
	 * @param array $recipients Mapa slug => correos configurados.
	 * @return array Resultado en formato de test directo de Site Health.
	 */
	public static function get_form_recipients_test_result( array $forms, array $recipients ): array {
		$missing = array_reduce(
			$forms,
			function ( $carry, $form ) use ( $recipients ) {
				$slug = $form->get_slug();
				if ( empty( $recipients[ $slug ] ) ) {
					$carry[] = array(
						'slug'  => $slug,
						'title' => $form->get_title(),
					);
				}
				return $carry;
			},
			array()
		);

		$result = array(
			'test'        => 'bloom_forms_recipients',
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Formularios', 'bloom-wp-forms' ),
				'color' => 'blue',
			),
			'label'       => empty( $forms )
				? __( 'No hay formularios activos', 'bloom-wp-forms' )
				: __( 'Los formularios activos tienen destinatarios configurados', 'bloom-wp-forms' ),
			'description' => '<p>' . esc_html__( 'Los envíos de cada formulario se notificarán a los destinatarios definidos en la pestaña del formulario en Formularios → Ajustes.', 'bloom-wp-forms' ) . '</p>',
			'actions'     => '',
		);
		if ( empty( $missing ) ) {
			return $result;
		}

		$result['status']      = 'critical';
		$result['label']       = __( 'Formularios sin destinatarios configurados', 'bloom-wp-forms' );
		$result['description'] = '<p>' . esc_html__( 'Los siguientes formularios no tienen destinatarios configurados; sus envíos no llegarán a nadie:', 'bloom-wp-forms' ) . '</p><ul>'
			. implode(
				'',
				array_map(
					fn( $item ) => '<li><a href="' . esc_url( static::get_settings_tab_url( $item['slug'] ) ) . '">'
						. esc_html( $item['title'] ) . '</a> <code>' . esc_html( $item['slug'] ) . '</code></li>',
					$missing
				)
			)
			. '</ul>';
		$result['actions']     = '<p><a class="button button-primary" href="' . esc_url( static::get_settings_tab_url( $missing[0]['slug'] ) ) . '">'
			. esc_html__( 'Configurar destinatarios', 'bloom-wp-forms' ) . '</a></p>';
		return $result;
	}

	/**
	 * Obtener la URL de una pestaña de la página de ajustes
	 *
	 * @param string $slug Slug de la pestaña (slug del formulario).
	 * @return string URL de la pestaña.
	 */
	public static function get_settings_tab_url( string $slug ): string {
		return add_query_arg(
			array(
				'page' => Settings_Admin::OPTION_NAME,
				'tab'  => $slug,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Obtener tamaño de archivo en formato legible
	 *
	 * @param int $bytes Tamaño en bytes.
	 * @param int $precision Cantidad de decimales.
	 * @return string Tamaño en formato legible
	 */
	public function human_filesize( $bytes, $precision = 2 ) {
		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	/**
	 * Procesar un envío de formulario
	 *
	 * @return void Si hay errores, se vuelve a mostrar formulario, caso contrario se redirige a URL éxito.
	 */
	public function maybe_process_form() {
		// Comprobar si corresponde procesar los datos.
		$form_slug = sanitize_text_field( filter_input( INPUT_POST, 'bloom_form_slug' ) );
		if ( ! $form_slug ) {
			return;
		}
		$form = $this->get_form( $form_slug );
		if ( ! $form ) {
			return;
		}
		$should_process = "bloom_forms_{$form->get_slug()}__submit" === filter_input( INPUT_POST, 'action' );
		if ( ! $should_process ) {
			return;
		}

		// Intentando engañar.
		$passes_nonce = wp_verify_nonce(
			sanitize_text_field( filter_input( INPUT_POST, "bloom_forms_{$form->get_slug()}__submit-nonce" ) ),
			"bloom_forms_{$form->get_slug()}__submit"
		);
		if ( ! $passes_nonce ) {
			if ( $this->logger ) {
				$this->logger->notice(
					'Error de validación de nonce en envío de formulario ({formname})',
					array(
						'formname' => $form->get_slug(),
						'_post'    => wp_unslash( $_POST ),
						'_server'  => wp_unslash( $_SERVER ),
					)
				);
			}
			wp_die( esc_html__( 'No es posible procesar el formulario. Por favor recarga la página e intenta nuevamente.', 'bloom-wp-forms' ) );
		}

		$postdata = wp_unslash( $_POST );

		foreach ( $_FILES as $key => $uploaded_file ) {
			// Un campo puede traer un archivo o varios (name="campo[]").
			$files = array();
			if ( is_array( $uploaded_file['name'] ?? null ) ) {
				foreach ( array_keys( $uploaded_file['name'] ) as $index ) {
					$files[] = array(
						'name'     => $uploaded_file['name'][ $index ],
						'type'     => $uploaded_file['type'][ $index ],
						'tmp_name' => $uploaded_file['tmp_name'][ $index ],
						'error'    => $uploaded_file['error'][ $index ],
						'size'     => $uploaded_file['size'][ $index ],
					);
				}
			} else {
				$files[] = $uploaded_file;
			}

			$results = array();
			foreach ( $files as $file ) {
				// No se sube archivo en ese campo del formulario, saltar.
				if ( 4 === (int) $file['error'] ) {
					continue;
				}
				$wp_upload = wp_handle_upload(
					$file,
					array(
						'action' => "bloom_forms_{$form->get_slug()}__submit",
					)
				);
				if ( ! empty( $wp_upload['error'] ) ) {
					if ( $this->logger ) {
						$this->logger->error(
							'Error al subir archivo {filename}',
							array(
								'file_key'      => $key,
								'filename'      => $file['name'],
								'uploaded_file' => $file,
								'error'         => $wp_upload,
							)
						);
					}
					wp_die( wp_kses_post( "{$wp_upload['error']} <b>" . esc_html__( 'Archivo:', 'bloom-wp-forms' ) . " {$file['name']}</b>" ) );
				}

				$results[] = $this->process_upload( $wp_upload );
			}

			if ( ! $results ) {
				continue;
			}
			// Un solo archivo conserva su forma; varios se guardan como lista.
			$postdata[ $key ] = is_array( $uploaded_file['name'] ?? null ) ? $results : $results[0];
		}

		$values          = $form->sanitize_data( $postdata );
		$validate_errors = $form->get_validation_errors( $values );

		if ( ! empty( $validate_errors ) ) {
			// El re-render del formulario reutiliza estos errores: la subida de
			// archivos ya se procesó y $_FILES no vuelve a estar disponible.
			$this->submit_errors[ $form->get_slug() ] = $validate_errors;
			// Continúa ejecución de render para mostrar errores a usuario.
			return;
		}

		$entries_repo = Entries_Repository::get_instance();
		$created      = $entries_repo->create(
			$form->get_slug(),
			$values
		);
		if ( ! $created ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Error al guardar datos de formulario ({$form})',
					array(
						'form'   => $form->get_slug(),
						'values' => $values,
					)
				);
			}
			wp_die( esc_html__( 'Error al guardar datos del formulario', 'bloom-wp-forms' ) );
		}

		if ( $this->logger ) {
			$this->logger->info(
				'Datos del formulario guardados correctamente ({form})',
				array(
					'form'   => $form->get_slug(),
					'values' => $values,
				)
			);
		}

		/**
		 * Utilizar esta acción para disparar notificaciones u otros eventos asociados a un envío
		 * exitosamente procesado.
		 */
		do_action( "bloom_forms_{$form->get_slug()}__submit_success", $form, $values, $created );

		$this->notify_form_recipients( $form, $values, (int) $created );

		if ( is_admin() ) {
			$redirect_url = add_query_arg(
				array(
					'entry_id' => $created,
					'action'   => 'bloom_forms_admin__view',
					'page'     => 'bloomform_entries_admin',
					'status'   => 'created-success',
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect_url );
		} else {
			$redirect_url = filter_input( INPUT_POST, '_wp_http_referer', FILTER_SANITIZE_URL );
			if ( ! $redirect_url ) {
				// Sin referer se vuelve a la URL actual, que puede incluir
				// parámetros de contexto (p. ej. previsualización de borrador).
				$redirect_url = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			}
			wp_safe_redirect(
				add_query_arg(
					'status',
					'success',
					$redirect_url
				),
				303
			);
		}

		exit;
	}

	/**
	 * Notificar a los destinatarios configurados de un formulario por cada envío exitoso
	 *
	 * Crea una notificación por destinatario (una fila por correo, igual que
	 * Send_Notification_Task y el reenvío manual/CLI) y la envía con el modo
	 * definido en Notification::send() (hoy síncrono). Sin destinatarios
	 * configurados no se registra nada: el estado queda visible en Salud del
	 * sitio y queda registro en el log del canal bloom_forms.
	 *
	 * @param Form  $form     Formulario del envío.
	 * @param array $values   Valores saneados del envío.
	 * @param int   $entry_id ID del envío creado.
	 * @return void
	 */
	public function notify_form_recipients( Form $form, array $values, int $entry_id ): void {
		$emails = $form->get_notification_emails( $values );
		if ( empty( $emails ) ) {
			$this->logger?->warning(
				'Envío de formulario {form} sin destinatarios configurados; no se envió notificación',
				array(
					'form'     => $form->get_slug(),
					'entry_id' => $entry_id,
				)
			);
			return;
		}
		foreach ( $emails as $email ) {
			$notification = Notification::make(
				array(
					'form'     => $form->get_slug(),
					'entry_id' => $entry_id,
					'email'    => $email,
				)
			);
			if ( ! $notification ) {
				$this->logger?->error(
					'No se pudo registrar la notificación de {form} para {email}',
					array(
						'form'     => $form->get_slug(),
						'email'    => $email,
						'entry_id' => $entry_id,
					)
				);
				continue;
			}
			$notification->send();
		}
	}

	/**
	 * Obtener correos desde un string, sanitizados
	 *
	 * @param string $emails String con direcciones de email separados por ",", ";" o espacios.
	 * @return array Direcciones de e-mail, sanitizadas
	 */
	public static function parse_string_emails( string $emails ): array {
		$re = '/[^,;\s]+/';
		preg_match_all( $re, $emails, $matches, PREG_SET_ORDER, 0 );
		$sanitized_emails = array_reduce(
			$matches,
			function ( $carry, $item ) {
				$sanitized = sanitize_email( $item[0] );
				if ( ! empty( $sanitized ) && filter_var( $sanitized, FILTER_VALIDATE_EMAIL ) ) {
					$carry[ $sanitized ] = $sanitized;
				}
				return $carry;
			},
			array()
		);
		return $sanitized_emails;
	}

	/**
	 * Procesar un upload
	 *
	 * @param array $wp_upload Datos del upload ya manejado por WordPress.
	 * @return array Datos para incrustar en envío del formulario
	 */
	private function process_upload( array $wp_upload ): array {
		$filename       = $wp_upload['file'];
		$parent_post_id = 0;
		$filetype       = wp_check_filetype( basename( $filename ), null );
		$wp_upload_dir  = wp_upload_dir();
		$attachment     = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id      = wp_insert_attachment( $attachment, $filename, $parent_post_id );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		$form_upload = array(
			'id'        => $attach_id,
			'type'      => 'attachment',
			'url'       => $attachment['guid'],
			'mime_type' => $filetype['type'],
			'extension' => $filetype['ext'],
			'size'      => $attach_data['filesize'] ?? 0,
		);
		return $form_upload;
	}

	/**
	 * Añadir hash a archivos subidos a formularios
	 *
	 * No son estrictamente confidenciales pero es mejor no dejarlos expuestos de forma pública.
	 *
	 * @param array $file Datos de archivo subido.
	 * @return array Datos de archivo subido con nombre modificado
	 */
	public function hash_filenames( array $file ): array {
		if ( ! empty( $file['error'] ) ) {
			return $file;
		}
		$file['name'] = substr( md5_file( $file['tmp_name'] ), 0, 8 ) . '-' . $file['name'];
		return $file;
	}
}
