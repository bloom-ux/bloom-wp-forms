<?php
/**
 * Clase para manejar notificaciones de envío de formularios
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use stdClass;
use DateTimeImmutable;

/**
 * Clase para manejar notificaciones de envío de formularios
 */
class Notification {
	/**
	 * ID de la notificación
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Correo electrónico de destino de la notificación
	 *
	 * @var string
	 */
	private $email = '';

	/**
	 * Texto/html del mensaje
	 *
	 * @var string
	 */
	private $message = '';

	/**
	 * Modo de envío de la notificación
	 *
	 * @internal Modo 'sync' no implementado
	 * @var string 'async'
	 */
	private $send_mode = 'async';

	/**
	 * Formulario asociado a la notificación
	 *
	 * @var ?Form
	 */
	private $form;

	/**
	 * Datos del envío
	 *
	 * @var ?Entry
	 */
	private $entry;

	/**
	 * Asunto del correo de notificación
	 *
	 * @var string
	 */
	private $subject = '';

	/**
	 * Fecha de creación de la notificación
	 *
	 * @var ?DateTimeImmutable
	 */
	private $created_on;

	/**
	 * Registro de estados de la notificación
	 *
	 * @var stdClass[]|array
	 */
	private $status_log = array();

	/**
	 * Metadatos de la notificación
	 *
	 * @var stdClass
	 */
	private $meta;

	/**
	 * ID del envío asociado a la notificación
	 *
	 * @var int
	 */
	private $entry_id = 0;

	/**
	 * Construir una notificación
	 *
	 * @param array $data Datos de la notificación.
	 * @return void
	 */
	public function __construct( $data = array() ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->send_mode = 'sync';
		}
		if ( ! $data ) {
			return;
		}
		$data       = (object) $data;
		$this->id   = ! empty( $data->id ) ? (int) $data->id : 0;
		$this->form = Plugin::get_instance()->get_form( $data->form );
		if ( ! empty( $data->entry_id ) ) {
			$this->entry = Entries_Repository::get_instance()->find_by_id( $data->entry_id );
		}
		if ( ! empty( $data->email ) ) {
			$this->set_email( $data->email );
		}
		if ( ! empty( $data->created_on ) ) {
			try {
				$creation_data    = new DateTimeImmutable( $data->created_on, wp_timezone() );
				$this->created_on = $creation_data;
			} catch ( \Exception $e ) {
				// fecha inválida.
				$this->created_on = null;
			}
		}
		$this->status_log = array();
		if ( ! empty( $data->status_log ) ) {
			$status_log = json_decode( $data->status_log );
			if ( ! json_last_error() ) {
				$this->status_log = $status_log;
			}
		}
		$this->meta = array();
		if ( ! empty( $data->meta ) ) {
			$meta = json_decode( $data->meta );
			if ( ! json_last_error() ) {
				$this->meta = (object) $meta;
			}
		}
	}

	/**
	 * Obtener el ID de la notificación
	 *
	 * @return int ID de la notificación
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Obtener el formulario asociado a la notificación.
	 *
	 * @return null|Form Formulario al cual se enviaron los datos.
	 */
	public function get_form(): ?Form {
		return $this->form;
	}

	/**
	 * Obtener el envío asociado a la notificación
	 *
	 * @return null|Entry Datos de envío del formulario
	 */
	public function get_entry(): ?Entry {
		return $this->entry;
	}

	/**
	 * Obtener el correo electrónico de destino de la notificación
	 *
	 * @return string Correo electrónico de destino
	 */
	public function get_email(): string {
		return $this->email;
	}

	/**
	 * Obtener el registro de status de la notificación
	 *
	 * Indica los estados por los que ha pasado la notificación.
	 *
	 * @return array Registros de status
	 */
	public function get_status_log(): array {
		return $this->status_log;
	}

	/**
	 * Obtener la fecha de creación de la notificación
	 *
	 * @return null|DateTimeImmutable Fecha de creación o nulo
	 */
	public function get_created_on(): ?DateTimeImmutable {
		return $this->created_on;
	}

	/**
	 * Obtener un metadato de la notificación
	 *
	 * @param string $key Clave del metadato.
	 * @return mixed Valor del metadato o null si no existe
	 */
	public function get_meta( $key = '' ) {
		if ( $key ) {
			return $this->meta->$key ?? null;
		}
		return (object) $this->meta;
	}

	/**
	 * Establecer todos los metadatos de la notificación, sin persistencia.
	 *
	 * Elimina metadatos existentes. Llamar a save() para guardar datos en ddbb.
	 *
	 * @param stdClass $meta Hash de claves a valores metadatos.
	 * @return void
	 */
	public function set_meta( stdClass $meta ) {
		$this->meta = $meta;
	}

	/**
	 * Actualizar un metadato de la notificación (sin persistencia).
	 *
	 * @param string $key Nombre del metadato.
	 * @param mixed  $value Valor del metadato.
	 * @return void
	 */
	public function update_meta( $key, $value ) {
		$this->meta->$key = $value;
	}

	/**
	 * Obtener una notificación por su ID
	 *
	 * @param int $id ID de la notificación.
	 * @return null|static
	 */
	public static function get( int $id ): ?Notification {
		// Obtiene desde Notifications_Repository.
		return Notifications_Repository::get_instance()->find_by_id( $id );
	}

	/**
	 * Crear una nueva notificación y guardar en base de datos
	 *
	 * @param array $data Datos de la notificación.
	 * @return static
	 */
	public static function make( $data ) {
		$notification = new static( $data );
		$id           = Notifications_Repository::get_instance()->create(
			$notification
		);
		return static::get( $id );
	}

	/**
	 * Guardar la notificación a base de datos.
	 *
	 * @return void
	 */
	public function save() {
		// A través del repository.
		Notifications_Repository::get_instance()->save( $this );
	}

	/**
	 * Establecer el correo electrónico de destino de la notificación
	 *
	 * @param string $email Dirección de correo electrónico.
	 * @return void
	 */
	public function set_email( $email ) {
		$this->email = filter_var( $email, FILTER_SANITIZE_EMAIL );
	}

	/**
	 * Establecer el asunto del correo de notificación
	 *
	 * @param string $subject Nuevo asunto del correo.
	 * @return void
	 */
	public function set_subject( string $subject ) {
		$this->subject = sanitize_text_field( $subject );
		$this->update_meta( 'subject', $this->subject );
	}

	/**
	 * Enviar notificación
	 *
	 * @todo Implementar envío sincrónico.
	 * @return void
	 */
	public function send() {
		// $this->send_async();
		$this->send_sync();
	}

	/**
	 * Construye el mensaje de notificación a partir de los datos enviados al formulario
	 *
	 * Utiliza la plantilla de correo de notificación. El cuerpo del mensaje se genera como una
	 * vista del formulario (Notification_View)
	 *
	 * @return string HTML del mensaje de notificación.
	 */
	public function get_message(): string {
		$entry                   = $this->get_entry();
		$form                    = $entry ? $entry->get_form() : null;
		$view_class              = apply_filters( 'bloom_forms_notification_class', '\Bloom_UX\WP_Forms\Notification_View', $this );
		$view                    = new $view_class();
		$view->title             = $form ? $form->get_title() : '';
		$view->submitted_date    = $entry ? $entry->get_submitted_on() : '';
		$view->entry_id          = $entry ? $entry->get_id() : '';
		$view->notification_id   = $this->get_id();
		$view->fields            = $form ? $form->get_fields() : array();
		$view->values            = $entry ? (array) $entry->get_data() : array();
		$view->action_link       = $this->get_action_link();
		$view->notification_type = $this->get_meta( 'type' );
		// Respuesta.
		ob_start();
		$notification_template = apply_filters(
			'bloom_forms_notification_template_path',
			__DIR__ . '/email/notification-template.php',
			$this
		);
		require $notification_template;
		$output = ob_get_clean();
		/**
		* Permitir filtrar el output HTML del mensaje antes de retornarlo
		*
		* @param string $output HTML del mensaje.
		* @param Notification $notification Instancia de la notificación.
		* @param Notification_View $view Vista de la notificación.
		*/
		$output = apply_filters( 'bloom_forms_notification_message_output', $output, $this, $view );
		return $output;
	}

	/**
	 * Obtener enlace para acceder a los datos del formulario y marcar notificación como leída
	 *
	 * @return string URL de acceso a datos y registro de acceso
	 */
	public function get_action_link() {
		return add_query_arg(
			array(
				'action'          => 'bloom_forms_admin__view',
				'entry_id'        => $this->get_entry()->get_id(),
				'page'            => 'bloom_forms_entries_admin',
				'notification_id' => $this->get_id(),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Envía la notificación de forma asíncrona
	 *
	 * @return void
	 */
	public function send_async() {
		$task = Plugin::get_instance()->get_async_notification_task();
		$data = array(
			'notification_id' => $this->get_id(),
		);
		$task->data( $data );
		$this->register_status( 'scheduled' );
		$this->save();
		$task->dispatch();
	}

	/**
	 * Construye el asunto del correo de notificación
	 *
	 * @return string
	 */
	public function get_subject(): string {
		$custom_subject = $this->get_meta( 'subject' );
		if ( ! empty( $custom_subject ) ) {
			return $custom_subject;
		}
		$form   = $this->get_form() ? $this->get_form()->get_title() : '';
		$sender = $this->get_entry() ? $this->get_entry()->get_data()->from_name : '';
		return "[{$form}] Envío de {$sender}";
	}

	/**
	 * Envía la notificación de forma síncrona
	 *
	 * @todo
	 * @return void
	 */
	public function send_sync() {
		// Construir los datos del correo
		$to      = $this->get_email();
		$subject = $this->get_subject();
		$message = $this->get_message();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Permitir modificar los parámetros del correo antes de enviar.
		 */
		$to      = apply_filters( 'bloom_forms_notification_mail_to', $to, $this );
		$subject = apply_filters( 'bloom_forms_notification_mail_subject', $subject, $this );
		$message = apply_filters( 'bloom_forms_notification_mail_message', $message, $this );
		$headers = apply_filters( 'bloom_forms_notification_mail_headers', $headers, $this );

		// Enviar correo.
		$result = wp_mail( $to, $subject, $message, $headers );

		if ( $result ) {
			$this->register_status( 'send_success' );
		} else {
			$this->register_status( 'send_error' );
		}
		$this->save();
	}

	/**
	 * Obtener etiquetas de los estados válidos para las notificaciones
	 *
	 * @return string[] Etiquetas según valor de status
	 */
	public static function get_status_labels(): array {
		$labels = array(
			'scheduled'    => 'Envío programado',
			'send_error'   => 'Error al enviar',
			'send_success' => 'Enviado correctamente',
		);
		return $labels;
	}

	/**
	 * Obtener etiqueta de estado
	 *
	 * @param string $status Estado que se busca.
	 * @return string Etiqueta o vacío
	 */
	public function get_status_label( string $status ): string {
		$labels = static::get_status_labels();
		return $labels[ $status ] ?: $status; //phpcs:ignore
	}

	/**
	 * Obtener etiqueta del último estado registrado en la notificación
	 *
	 * @return string P.ej: 'Enviado correctamente', 'Error al enviar', 'Envío programado'
	 */
	public function get_last_status_label(): string {
		$status_log = $this->get_status_log();
		if ( empty( $status_log ) ) {
			return '';
		}
		$last_status = (array) array_shift( $status_log );
		return $this->get_status_label( $last_status['status'] );
	}

	/**
	 * Obtener el úlimo estado registrado en la notificación
	 *
	 * @return string P.ej: 'scheduled', 'send_error', 'send_success'
	 */
	public function get_last_status(): string {
		$status_log = $this->get_status_log();
		if ( empty( $status_log ) ) {
			return '';
		}
		return $status_log[0]->status ?: ''; //phpcs:ignore
	}

	/**
	 * Agrega un nuevo status a la notificación
	 *
	 * @param string $status Nuevo status que se agrega.
	 */
	public function register_status( string $status ) {
		$add_status = (object) array(
			'status'   => sanitize_key( $status ),
			'datetime' => wp_date( 'Y-m-d H:i:s' ),
		);
		$old_status = $this->get_status_log();
		if ( empty( $old_status ) ) {
			$new_status = array( $add_status );
		} else {
			array_unshift( $old_status, $add_status );
			$new_status = $old_status;
		}
		$this->status_log = $new_status;
	}

	/**
	 * Actualiza el estado de la notificación y guarda en base de datos
	 *
	 * @param string $status Nuevo status que se añade a la notificación.
	 */
	public function update_status( string $status ) {
		$this->register_status( $status );
		$this->save();
	}
}
