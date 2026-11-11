<?php
/**
 * Visualización de una entrada de formulario en el panel de administración
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Form_View;
use Queulat\Forms\Element\Input;

/**
 * Visualización de una entrada de formulario en el panel de administración
 *
 * @package Bloom_UX\WP_Forms
 */
class Admin_View extends Form_View {

	/**
	 * Construir visualización del formulario
	 *
	 * @return string Visualización de datos del formulario
	 */
	public function __toString() {
		$out  = '<div class="container" style="font-size:1rem;line-height:1.25;">';
		$out .= '<h1 style="font-weight:700;margin:1rem 0;font-size:1.75rem;">' . esc_html( $this->form->get_property( 'title' ) ) . '</h1>';
		if ( 'created-success' === filter_input( INPUT_GET, 'status' ) ) :
			$out .= '<div class="notice notice-success"><p>' . esc_html__( 'El envío se ha creado correctamente', 'bloom-wp-forms' ) . '</p></div>';
		elseif ( 'resend-success' === filter_input( INPUT_GET, 'status' ) ) :
			$out .= '<div class="notice notice-success"><p>' . esc_html__( 'La notificación se ha reenviado correctamente', 'bloom-wp-forms' ) . '</p></div>';
		elseif ( 'edit-entry-success' === filter_input( INPUT_GET, 'status' ) ) :
			$out .= '<div class="notice notice-success"><p>' . esc_html__( 'Entrada editada correctamente', 'bloom-wp-forms' ) . '</p></div>';
		endif;
		$out .= '<div class="description" style="text-transform:uppercase;margin:1.5rem 0;opacity:.75;font-weight:500">';
		$out .= '#' . esc_html( filter_input( INPUT_GET, 'entry_id', FILTER_SANITIZE_NUMBER_INT ) ) . ' / ' . esc_html( wp_date( 'd F Y - H:i:s', $this->form->get_property( 'submitted_date' )->format( 'U' ) ) );
		$out .= '</div>';

		ob_start();
		do_action( 'bloom_forms_admin_entry_detail_before', $this );
		$out .= ob_get_clean();

		$entry_data = (array) ( $this->form->get_property( 'entry_data' ) ?? array() );
		$rows       = View_Values::rows( iterator_to_array( $this->form->get_children() ), $entry_data );
		foreach ( $rows as $row ) {
			$value              = $row['value'];
			$out               .= '<div style="display:grid;grid-template-columns:20rem 1fr;gap:1rem;margin:0;padding:1rem 0;border-bottom:1px solid rgba(0, 0, 0, 0.15);line-height:1.85">';
			$out               .= '<div><b style="font-weight:500">' . esc_html( $row['label'] ) . '</b></div>';
			$field_value_output = apply_filters( 'bloom_forms_admin_view_element_value', '', $row['element'], $this );
			if ( $field_value_output ) {
				$out .= $field_value_output;
			} elseif ( $row['element'] instanceof Input && 'date' === $row['element']->get_attribute( 'type' ) && is_string( $value ) && '' !== $value ) {
				$out .= '<div>' . esc_html( wp_date( 'd F Y', strtotime( $value . ' 12:00:00' ) ) ) . '</div>';
			} else {
				$out .= '<div>' . $row['html'] . '</div>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
