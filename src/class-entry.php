<?php
/**
 * Clase para manejar las entradas de formularios
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use stdClass;
use DateTimeImmutable;

/**
 * Clase para manejar las entradas de formularios
 */
class Entry {

	/**
	 * ID de la entrada del formulario
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Fecha de envío
	 *
	 * @var DateTimeImmutable
	 */
	private $submitted_on;

	/**
	 * Formulario asociado a la entrada
	 *
	 * @var null|Form
	 */
	private $form;

	/**
	 * Datos de la entrada
	 *
	 * @var stdClass
	 */
	private $data;

	/**
	 * Metadatos de la entrada
	 *
	 * @var stdClass
	 */
	private $meta;

	/**
	 * Notificaciones asociadas a la entrada
	 *
	 * @var array
	 */
	private $notifications;

	/**
	 * Constructor
	 *
	 * @param array $entrydata Datos de la entrada.
	 */
	public function __construct( $entrydata ) {
		$entrydata          = (object) $entrydata;
		$this->id           = (int) $entrydata->id;
		$this->submitted_on = new DateTimeImmutable( $entrydata->submitted_on, wp_timezone() );
		$this->form         = Plugin::get_instance()->get_form( $entrydata->form );
		$this->data         = json_decode( $entrydata->form_data );
		$this->meta         = (object) json_decode( $entrydata->meta );
	}

	/**
	 * Obtener enlace de administración de la entrada
	 *
	 * @return string URL de administración de la entrada
	 */
	public function get_admin_link(): string {
		return add_query_arg(
			array(
				'action'   => 'bloom_forms_admin__view',
				'entry_id' => $this->id,
				'page'     => Plugin::get_instance()->entries_admin->get_id(),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Obtener ID de la entrada
	 *
	 * @return int ID de la entrada
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Obtener fecha de envío
	 *
	 * @return DateTimeImmutable Fecha de envío
	 */
	public function get_submitted_on(): DateTimeImmutable {
		return $this->submitted_on;
	}

	/**
	 * Obtener el formulario asociado a la entrada
	 *
	 * @return Form Instancia del controlador de formulario
	 */
	public function get_form(): Form {
		return $this->form;
	}

	/**
	 * Obtener datos de la entrada
	 *
	 * @return stdClass Datos de la entrada
	 */
	public function get_data(): stdClass {
		return (object) $this->data;
	}

	/**
	 * Obtener nombre del remitente
	 *
	 * @return string
	 */
	public function get_sender_name(): string {
		return $this->data->from_name ?? '';
	}

	/**
	 * Obtener el correo del remitente
	 *
	 * @return string
	 */
	public function get_sender_email(): string {
		return $this->data->from_email ?? '';
	}

	/**
	 * Obtener notificaciones asociadas a la entrada
	 *
	 * @return array Notificaciones asociadas a la entrada
	 */
	public function get_notifications(): array {
		if ( ! isset( $this->notifications ) ) {
			$this->notifications = (array) Notifications_Repository::get_instance()->find_by_query(
				array(
					'entry_id' => $this->id,
				)
			);
		}
		return $this->notifications;
	}

	/**
	 * Obtener notificaciones agrupadas por estado
	 *
	 * @return array Notificaciones agrupadas por estado
	 */
	public function get_notifications_by_status(): array {
		$notifications = $this->get_notifications();
		$grouped       = array();
		foreach ( $notifications as $notification ) {
			$status = $notification->get_last_status();
			$grouped[ $status ][] = $notification;
		}
		return $grouped;
	}

	/**
	 * Obtener valor de un campo de la entrada
	 *
	 * @param string $field Nombre del campo.
	 * @return mixed Valor del campo; null si no existe
	 */
	public function get_data_field( string $field ) {
		return $this->data->{$field} ?? null;
	}

	/**
	 * Establecer metadato de la entrada en el key indicado
	 *
	 * @param string $key Nombre del metadato.
	 * @param mixed  $value Valor del metadato.
	 * @return void
	 */
	public function set_meta( $key, $value ) {
		$this->meta->{$key} = $value;
	}

	/**
	 * Obtener metadatos de la entrada
	 *
	 * @return stdClass Metadatos de la entrada
	 */
	public function get_meta() {
		return $this->meta;
	}

	/**
	 * Obtener el valor de un campo meta
	 *
	 * @param string $key Nombre del metadato.
	 * @return mixed Valor del metadato; null si no existe
	 */
	public function get_meta_field( string $key ) {
		return $this->meta->{$key} ?? null;
	}
}
