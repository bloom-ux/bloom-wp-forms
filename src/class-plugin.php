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
use Queulat\Validator;
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

	const VERSION                              = '0.1.0';
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
		add_action( 'bloom_forms__retry_scheduled', array( $this, 'retry_scheduled' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'init', array( $this, 'maybe_admin_redirect' ) );
		add_action( 'switch_blog', array( $this, 'switched_blog' ) );
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
		if ( get_current_screen()->id !== 'toplevel_page_bloom_forms_entries_admin' ) {
			return;
		}
		$this->asset_loader->enqueue_style( 'backend-styles.css' );
		// $this->asset_loader->enqueue_script( 'backend-scripts.js', array( 'jquery' ) );
		wp_enqueue_script( 'htmx.org', 'https://cdn.jsdelivr.net/npm/htmx.org@1.9.10/dist/htmx.min.js', array(), '1.9.10', array( 'strategy' => 'defer' ) );
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
			wp_die( 'No tienes autorización para hacer eso' );
		}
		$notification = Notification::get( $notification_id );
		if ( ! $notification ) {
			wp_die( 'No existe la notificación indicada' );
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
			set_channel_level( $logger, Level::Debug );
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
	 * Obtener errores de validación
	 *
	 * @param Form  $form Instancia de formulario.
	 * @param array $values Valores enviados.
	 * @return array Mensajes de error de validación indexados por name del campo
	 */
	private function get_validation_errors( Form $form, $values ) {
		if ( empty( $values ) ) {
			return;
		}
		$validation = new Validator(
			$values,
			$form->get_validation_rules()
		);
		$is_valid   = $validation->is_valid();
		$errors     = $validation->get_error_messages();
		return $errors;
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
			$this->logger->notice(
				'Error de validación de nonce en envío de formulario ({formname})',
				array(
					'formname' => $form->get_slug(),
					'_post'    => wp_unslash( $_POST ),
					'_server'  => wp_unslash( $_SERVER ),
				)
			);
			wp_die( 'No es posible procesar el formulario. Por favor recarga la página e intenta nuevamente.' );
		}

		$postdata = wp_unslash( $_POST );

		foreach ( $_FILES as $key => $uploaded_file ) {
			// No se sube archivo en ese campo del formulario, saltar.
			if ( isset( $_FILES[ $key ]['error'] ) && 4 === $_FILES[ $key ]['error'] ) {
				continue;
			}
			$wp_upload = wp_handle_upload(
				$uploaded_file,
				array(
					'action' => "bloom_form_{$form->get_slug()}__submit",
				)
			);
			if ( ! empty( $wp_upload['error'] ) ) {
				$this->logger->error(
					'Error al subir archivo {filename}',
					array(
						'file_key'      => $key,
						'filename'      => $uploaded_file['name'],
						'uploaded_file' => $uploaded_file,
						'error'         => $wp_upload,
					)
				);
				wp_die( wp_kses_post( "{$wp_upload['error']} <b>Archivo: {$uploaded_file['name']}</b>" ) );
			}

			$postdata[ $key ] = $this->process_upload( $wp_upload );
		}

		$values          = $form->sanitize_data( $postdata );
		$validate_errors = $this->get_validation_errors( $form, $values );

		if ( ! empty( $validate_errors ) ) {
			// Continúa ejecución de render para mostrar errores a usuario.
			return;
		}

		$entries_repo = Entries_Repository::get_instance();
		$created      = $entries_repo->create(
			$form->get_slug(),
			$values
		);
		if ( ! $created ) {
			$this->logger->error(
				'Error al guardar datos de formulario ({$form})',
				array(
					'form'   => $form->get_slug(),
					'values' => $values,
				)
			);
			wp_die( 'Error al guardar datos del formulario' );
		}

		$this->logger->info(
			'Datos del formulario guardados correctamente ({form})',
			array(
				'form'   => $form->get_slug(),
				'values' => $values,
			)
		);

		/**
		 * Utilizar esta acción para disparar notificaciones u otros eventos asociados a un envío
		 * exitosamente procesado.
		 */
		do_action( "bloom_forms_{$form->get_slug()}__submit_success", $form, $values, $created );

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
			wp_safe_redirect(
				add_query_arg(
					'status',
					'success',
					filter_input( INPUT_POST, '_wp_http_referer', FILTER_SANITIZE_URL )
				),
				303
			);
		}

		exit;
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
