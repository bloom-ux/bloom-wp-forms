<?php
/**
 * Visualización de una notificación de envío de formulario que se envía por correo electrónico
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Form_View;
use Queulat\Forms\Element\Div;
use Queulat\Forms\Element\Input;
use Queulat\Forms\Element\Textarea;

/**
 * Visualización de una notificación de envío de formulario que se envía por correo electrónico
 *
 * @package Bloom_UX\WP_Forms
 */
class Notification_View extends Form_View {

	/**
	 * Construir visualización de la notificación de un envío del formulario
	 *
	 * @return string Visualización de notificación enviada por correo electrónico
	 */
	public function __toString() {
		$out      = '<div class="container" style="font-size:1rem;line-height:1.25;">';
		$out     .= '<h1 style="font-weight:700;margin:1rem 0;font-size:1.75rem;">' . esc_html( $this->form->get_property( 'title' ) ) . '</h1>';
		$out     .= '<div class="description" style="text-transform:uppercase;margin:1rem 0 1.5rem;opacity:.75;font-weight:500">';
			$out .= '#' . esc_html( $this->form->get_property( 'entry_id' ) ) . ' / ' . esc_html( wp_date( 'd F Y - H:i:s', $this->form->get_property( 'submitted_date' )->format( 'U' ), false ) );
		$out     .= '</div>';
		$out     .= apply_filters( 'bloom_forms_form_notification_pre_fields', '', $this, $this->form );
		foreach ( $this->form->get_children() as $element ) {
			$value = is_callable( array( $element, 'get_value' ) ) ? $element->get_value() : null;
			if (
				( ! $element instanceof Div && ! $value ) ||
				str_contains( $element->get_class_name(), 'bloom-forms-form-terms' ) ||
				str_contains( $element->get_class_name(), 'bloom-forms-form-user-only-field' ) ||
				$element instanceof Accept_Terms_Component
			) {
				continue;
			}
			$out .= '<div style="margin:0;padding:1rem 0;border-bottom:1px dotted rgba(0, 0, 0, 0.15);">';
			if ( $element instanceof Div ) {
				$out .= $element;
			} else {
				if ( is_callable( array( $element, 'get_label' ) ) && ! empty( $element->get_label() ) ) {
					$out .= '<div><b style="font-weight:500">' . esc_html( $element->get_label() ) . '</b></div>';
				}
				if ( is_callable( array( $element, 'get_value' ) ) ) {
					if ( $element instanceof Textarea ) {
						$out .= nl2br( esc_html( $value ) );
					} elseif ( $element instanceof Input && $element->get_attribute( 'type' ) === 'date' ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$value = is_string( $element->get_value() ) ? $element->get_value() : '<pre>' . print_r( (array) $element->get_value(), true ) . '</pre>';
						$value = wp_date( 'd F Y', strtotime( $value . ' 12:00:00' ) );
						$out  .= '<div>' . esc_html( $value ) . '</div>';
					} else {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$value = is_string( $element->get_value() ) ? $element->get_value() : '<pre>' . print_r( (array) $element->get_value(), true ) . '</pre>';
						$out  .= '<div>' . esc_html( $value ) . '</div>';
					}
				}
			}
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
