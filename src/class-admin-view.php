<?php
/**
 * Visualización de una entrada de formulario en el panel de administración
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Form_View;
use Queulat\Forms\Element\Div;
use Queulat\Forms\Element\Input;
use Queulat\Forms\Element\Textarea;

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
			$out .= '<div class="notice notice-success"><p>El envío se ha creado correctamente</p></div>';
		elseif ( 'resend-success' === filter_input( INPUT_GET, 'status' ) ) :
			$out .= '<div class="notice notice-success"><p>La notificación se ha reenviado correctamente</p></div>';
		elseif ( 'edit-entry-success' === filter_input( INPUT_GET, 'status' ) ) :
			$out .= '<div class="notice notice-success"><p>Entrada editada correctamente</p></div>';
		endif;
		$out .= '<div class="description" style="text-transform:uppercase;margin:1.5rem 0;opacity:.75;font-weight:500">';
		$out .= '#' . esc_html( filter_input( INPUT_GET, 'entry_id', FILTER_SANITIZE_NUMBER_INT ) ) . ' / ' . esc_html( wp_date( 'd F Y - H:i:s', $this->form->get_property( 'submitted_date' )->format( 'U' ) ) );
		$out .= '</div>';

		ob_start();
		do_action( 'bloom_forms_admin_entry_detail_before', $this );
		$out .= ob_get_clean();

		foreach ( $this->form->get_children() as $element ) {
			$value = is_callable( array( $element, 'get_value' ) ) ? $element->get_value() : null;
			if (
				( ! $element instanceof Div && ! $value )
			) {
				continue;
			}
			$out .= '<div style="display:grid;grid-template-columns:20rem 1fr;gap:1rem;margin:0;padding:1rem 0;border-bottom:1px solid rgba(0, 0, 0, 0.15);line-height:1.85">';
			if ( $element instanceof Div ) {
				$out .= $element;
			} else {
				if ( is_callable( array( $element, 'get_label' ) ) && ! empty( $element->get_label() ) ) {
					$out .= '<div><b style="font-weight:500">' . esc_html( $element->get_label() ) . '</b></div>';
				}
				if ( is_callable( array( $element, 'get_value' ) ) ) {
					$field_value_output = apply_filters( 'bloom_forms_admin_view_element_value', '', $element, $this );
					if ( $field_value_output ) {
						$out .= $field_value_output;
					} elseif ( $element instanceof Textarea ) {
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

	/**
	 * Construir output para elementos input radio
	 *
	 * @param Custom_Radio $element Elemento input radio.
	 * @return string Visualización de opción seleccionada
	 */
	public function custom_radio_output( Custom_Radio $element ): string {
		$value      = json_decode( $element->get_value() );
		$json_error = json_last_error();
		if ( $json_error ) {
			$value = (array) $element->get_value();
			if ( ! $value ) {
				return '&mdash;';
			}
		}
		$element_options = $element->get_options();
		$is_associative  = \Minwork\Helper\Arr::isAssoc( $element_options );
		if ( $is_associative ) {
			$value = array_intersect_key( $element_options, array_combine( $value, $value ) );
		}
		return '<div>' . implode( '<br>', array_map( 'esc_html', (array) $value ) ) . '</div>';
	}

	/**
	 * Construir output para elementos checkbox o radio
	 *
	 * @param Custom_Checkbox $element Elemento del formulario.
	 * @return string Output HTML
	 */
	public function custom_checkbox_output( Custom_Checkbox $element ): string {
		$value = $element->get_value();
		if ( ! $value ) {
			return '&mdash;';
		}
		$element_options = $element->get_options();
		$is_associative  = \Minwork\Helper\Arr::isAssoc( $element_options );
		if ( $is_associative ) {
			$value = array_intersect_key( $element_options, array_combine( $value, $value ) );
		}
		return '<div>' . implode( '<br>', array_map( 'esc_html', $value ) ) . '</div>';
	}

	/**
	 * Construir output para elementos de subida de archivos
	 *
	 * @param Custom_Upload $element Elemento de subida de archivo.
	 * @return string Link al archivo subido
	 */
	public function custom_upload_output( $element ): string {
		$value = $element->get_value();
		if ( ! $value ) {
			return '';
		}
		$url = $value->url;
		return '<div><a href="' . esc_url( $url ) . '" target="_blank" rel="noreferer noopener">' . esc_html( basename( $url ) ) . '</a> ' . Plugin::get_instance()->human_filesize( $value->size, 2 ) . '</div>';
	}
}
