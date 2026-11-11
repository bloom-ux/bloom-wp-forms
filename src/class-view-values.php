<?php
/**
 * Renderizado compartido de los valores de un envío para el detalle en el
 * panel de administración y para las notificaciones por correo.
 *
 * Ambas vistas necesitan la misma semántica: todos los campos (vacíos
 * incluidos), archivos como enlaces al archivo subido, opciones de
 * select/radio/checkbox con su etiqueta visible y las claves de datos que no
 * tienen campo en el formulario actual (campos condicionales que dependen del
 * contexto de la petición, como el tipo de cargo de una postulación).
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Element\Div;
use Queulat\Forms\Element\Fieldset;
use Queulat\Forms\Element\Repeater;
use Queulat\Forms\Option_Node_Interface;

/**
 * Renderizado compartido de los valores de un envío.
 */
class View_Values {

	/**
	 * Aplanar un árbol de nodos en la lista de campos que producen una fila.
	 *
	 * Los contenedores (fieldset, div, repetidor) no aportan valor propio: sus
	 * hijos son las filas. Un contenedor vacío no aporta filas.
	 *
	 * @param iterable $nodes Nodos del formulario.
	 * @return array Lista de nodos hoja.
	 */
	public static function flatten_fields( iterable $nodes ): array {
		$fields = array();
		foreach ( $nodes as $node ) {
			if ( $node instanceof Fieldset || $node instanceof Div || $node instanceof Repeater ) {
				$fields = array_merge( $fields, static::flatten_fields( $node->get_children() ) );
				continue;
			}
			$fields[] = $node;
		}
		return $fields;
	}

	/**
	 * Obtener la clave de datos de un campo.
	 *
	 * Los campos múltiples se nombran con corchetes (`titulos[]`), pero los
	 * datos del envío se guardan con el nombre base (`titulos`).
	 *
	 * @param string $name Nombre del campo.
	 * @return string Nombre base del campo.
	 */
	public static function base_name( string $name ): string {
		return (string) preg_replace( '/\[\d*\]$/', '', $name );
	}

	/**
	 * Renderizar un valor del envío como HTML seguro.
	 *
	 * El resultado se imprime sin escapar de nuevo en las vistas.
	 *
	 * @param mixed                      $value   Valor guardado.
	 * @param Option_Node_Interface|null $element Campo de origen, si tiene opciones.
	 * @return string HTML del valor; cadena vacía si no hay valor.
	 */
	public static function render( $value, ?Option_Node_Interface $element = null ): string {
		if ( null === $value || '' === $value || array() === $value ) {
			return '';
		}
		if ( static::is_upload( $value ) ) {
			$value = array( $value );
		}
		if ( is_array( $value ) ) {
			$uploads = array();
			$scalars = array();
			$is_list = true;
			foreach ( $value as $item ) {
				if ( static::is_upload( $item ) ) {
					$uploads[] = $item;
					continue;
				}
				if ( null === $item || is_scalar( $item ) || $item instanceof \Stringable ) {
					$scalars[] = $item;
					continue;
				}
				$is_list = false;
			}
			if ( $uploads && ! $scalars && $is_list ) {
				$links = array();
				foreach ( $uploads as $upload ) {
					$link = static::render_file_link( $upload );
					if ( '' !== $link ) {
						$links[] = $link;
					}
				}
				return implode( '<br>', $links );
			}
			if ( $is_list ) {
				$labels = array();
				foreach ( $scalars as $item ) {
					$label = static::render( $item, $element );
					if ( '' !== $label ) {
						$labels[] = $label;
					}
				}
				return implode( ', ', $labels );
			}
			return esc_html( (string) wp_json_encode( $value ) );
		}
		if ( ! is_scalar( $value ) && ! $value instanceof \Stringable ) {
			return '';
		}
		$string = (string) $value;
		if ( '' === $string ) {
			return '';
		}
		$label = static::option_label( $value, $element );
		if ( null !== $label ) {
			return esc_html( $label );
		}
		return nl2br( esc_html( $string ) );
	}

	/**
	 * Construir las filas de valores de un envío.
	 *
	 * Cada fila trae la etiqueta, el HTML del valor, el valor crudo (para
	 * formato condicional de la vista) y el campo de origen, si lo hay. Las
	 * claves de datos sin campo se resuelven con dos filtros para que el
	 * formulario pueda aportar sus etiquetas fuera del contexto de la petición.
	 *
	 * @param array $fields Campos del formulario.
	 * @param array $values Valores del envío, indexados por nombre de campo.
	 * @return array Lista de filas (label, html, value, element).
	 */
	public static function rows( array $fields, array $values ): array {
		$rows    = array();
		$covered = array();
		foreach ( static::flatten_fields( $fields ) as $field ) {
			$name = is_callable( array( $field, 'get_name' ) ) ? (string) $field->get_name() : '';
			if ( '' === $name ) {
				continue;
			}
			$key             = static::base_name( $name );
			$covered[ $key ] = true;
			$field_label     = is_callable( array( $field, 'get_label' ) ) ? (string) $field->get_label() : '';
			$value           = array_key_exists( $key, $values ) ? $values[ $key ] : null;
			$html            = static::render( $value, $field instanceof Option_Node_Interface ? $field : null );
			$rows[]          = array(
				'label'   => '' !== $field_label ? $field_label : $name,
				'html'    => '' !== $html ? $html : static::empty_mark(),
				'value'   => $value,
				'element' => $field,
			);
		}
		foreach ( $values as $name => $value ) {
			if ( ! is_string( $name ) && ! is_int( $name ) ) {
				continue;
			}
			if ( isset( $covered[ $name ] ) ) {
				continue;
			}
			$html = static::render( $value, null );
			/**
			 * Filtrar la etiqueta de una clave de datos que no tiene campo en el formulario actual.
			 *
			 * Permite a cada formulario nombrar sus campos condicionales sin
			 * depender del contexto de la petición.
			 *
			 * @param string|null $label  Etiqueta; null para no mostrar la clave si está vacía.
			 * @param string      $name   Nombre de la clave en los datos del envío.
			 * @param array       $values Datos completos del envío.
			 */
			$label = apply_filters( 'bloom_forms_view_field_label', null, (string) $name, $values );
			if ( null === $label && '' === $html ) {
				continue;
			}
			if ( null === $label ) {
				$label = ucwords( str_replace( array( '_', '-' ), ' ', (string) $name ) );
			}
			/**
			 * Filtrar la etiqueta visible del valor de una clave de datos sin campo.
			 *
			 * @param string|null $label  Etiqueta del valor; null para usar el valor guardado.
			 * @param string      $name   Nombre de la clave en los datos del envío.
			 * @param mixed       $value  Valor guardado.
			 * @param array       $values Datos completos del envío.
			 */
			$value_label = apply_filters( 'bloom_forms_view_value_label', null, (string) $name, $value, $values );
			if ( is_string( $value_label ) ) {
				$html = '' !== $value_label ? esc_html( $value_label ) : '';
			}
			$rows[] = array(
				'label'   => $label,
				'html'    => '' !== $html ? $html : static::empty_mark(),
				'value'   => $value,
				'element' => null,
			);
		}
		return $rows;
	}

	/**
	 * Marca visible para un campo sin valor.
	 *
	 * @return string
	 */
	private static function empty_mark(): string {
		return esc_html__( '—', 'bloom-wp-forms' );
	}

	/**
	 * Determinar si un valor es el resultado de una subida.
	 *
	 * El plugin base guarda las subidas como
	 * {id, type, url, mime_type, extension, size}.
	 *
	 * @param mixed $value Valor guardado.
	 * @return bool
	 */
	private static function is_upload( $value ): bool {
		if ( $value instanceof \stdClass ) {
			$value = (array) $value;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		return array_key_exists( 'url', $value ) || array_key_exists( 'id', $value );
	}

	/**
	 * Construir el enlace al archivo de una subida.
	 *
	 * @param array|object $upload Resultado de la subida.
	 * @return string Enlace HTML o cadena vacía si no hay archivo que enlazar.
	 */
	private static function render_file_link( $upload ): string {
		$data = (array) $upload;
		$url  = isset( $data['url'] ) && is_string( $data['url'] ) ? $data['url'] : '';
		if ( '' === $url && ! empty( $data['id'] ) ) {
			$url = (string) wp_get_attachment_url( (int) $data['id'] );
		}
		if ( '' === $url ) {
			return '';
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = '' !== $path ? basename( $path ) : '';
		if ( '' === $name ) {
			$name = $url;
		}
		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			esc_html( $name )
		);
	}

	/**
	 * Obtener la etiqueta visible de una opción.
	 *
	 * @param mixed                      $value   Valor guardado.
	 * @param Option_Node_Interface|null $element Campo con opciones.
	 * @return string|null Etiqueta de la opción o null si no está configurada.
	 */
	private static function option_label( $value, ?Option_Node_Interface $element ): ?string {
		if ( null === $element ) {
			return null;
		}
		$options = $element->get_options();
		if ( ! is_array( $options ) ) {
			return null;
		}
		if ( array_key_exists( $value, $options ) && is_scalar( $options[ $value ] ) ) {
			return (string) $options[ $value ];
		}
		$key = (string) $value;
		if ( array_key_exists( $key, $options ) && is_scalar( $options[ $key ] ) ) {
			return (string) $options[ $key ];
		}
		return null;
	}
}
