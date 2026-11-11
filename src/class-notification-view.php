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
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Fecha de envío
	 *
	 * @var string|\DateTimeInterface|null
	 */
	public $submitted_date = '';

	/**
	 * ID del envío
	 *
	 * @var int|string
	 */
	public $entry_id = '';

	/**
	 * ID de la notificación
	 *
	 * @var int|string
	 */
	public $notification_id = '';

	/**
	 * Campos del formulario
	 *
	 * @var array
	 */
	public $fields = array();

	/**
	 * Valores del envío
	 *
	 * @var array
	 */
	public $values = array();

	/**
	 * Enlace de acción
	 *
	 * @var string
	 */
	public $action_link = '';

	/**
	 * Tipo de notificación
	 *
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

		foreach ( View_Values::rows( (array) $this->fields, (array) $this->values ) as $row ) {
			$out .= '<div style="margin:0;padding:16px 0;border-bottom:1px dotted rgba(0, 0, 0, 0.15);">';
			$out .= '<div><b style="font-weight:500">' . esc_html( $row['label'] ) . '</b></div>';
			$out .= '<div>' . $row['html'] . '</div>';
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
