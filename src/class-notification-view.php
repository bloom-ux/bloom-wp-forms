<?php
/**
 * Visualización de una notificación de envío de formulario que se envía por correo electrónico
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

/**
 * Visualización de una notificación de envío de formulario que se envía por correo electrónico
 *
 * @package Bloom_UX\WP_Forms
 */
class Notification_View {
	/**
	 * Título del formulario
	 * @var string
	 */
	public $title = '';

	/**
	 * Fecha de envío
	 * @var string|\DateTimeInterface|null
	 */
	public $submitted_date = '';

	/**
	 * ID del envío
	 * @var int|string
	 */
	public $entry_id = '';

	/**
	 * ID de la notificación
	 * @var int|string
	 */
	public $notification_id = '';

	/**
	 * Campos del formulario
	 * @var array
	 */
	public $fields = array();

	/**
	 * Valores del envío
	 * @var array
	 */
	public $values = array();

	/**
	 * Enlace de acción
	 * @var string
	 */
	public $action_link = '';

	/**
	 * Tipo de notificación
	 * @var string|null
	 */
	public $notification_type = '';

	/**
	 * Construir visualización de la notificación de un envío del formulario
	 *
	 * @return string Visualización de notificación enviada por correo electrónico
	 */
   	public function __toString() {
		$out  = '<div class="container" style="font-size:16px;line-height:1.25;">';
		$out .= '<h1 style="font-weight:700;margin:16px 0;font-size:28px;">' . esc_html( $this->title ) . '</h1>';
		$out .= '<div class="description" style="text-transform:uppercase;margin:16px 0 24px;opacity:.75;font-weight:500">';
		$out .= '#' . esc_html( $this->entry_id ) . ' / ';
		if ( $this->submitted_date instanceof \DateTimeInterface ) {
			$out .= esc_html( date_i18n( 'd F Y - H:i:s', $this->submitted_date->getTimestamp() ) );
		} elseif ( ! empty( $this->submitted_date ) ) {
			$out .= esc_html( $this->submitted_date );
		}
		$out .= '</div>';

		// Aquí podrías aplicar un filtro si lo necesitas, por ejemplo:
		// $out .= apply_filters( 'bloom_forms_form_notification_pre_fields', '', $this );

		foreach ( $this->fields as $field ) {
			$name = is_callable( array( $field, 'get_name' ) ) ? $field->get_name() : ( is_string( $field ) ? $field : '' );
			if ( ! $name || ! isset( $this->values[ $name ] ) || '' === $this->values[ $name ] ) {
				continue;
			}
			$label = is_callable( array( $field, 'get_label' ) ) ? $field->get_label() : $name;
			$value = $this->values[ $name ];
			$out .= '<div style="margin:0;padding:16px 0;border-bottom:1px dotted rgba(0, 0, 0, 0.15);">';
			if ( ! empty( $label ) ) {
				$out .= '<div><b style="font-weight:500">' . esc_html( $label ) . '</b></div>';
			}
			if ( is_array( $value ) ) {
				$out .= '<div>' . esc_html( wp_json_encode( $value ) ) . '</div>';
			} else {
				$out .= '<div>' . nl2br( esc_html( $value ) ) . '</div>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
