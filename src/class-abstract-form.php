<?php
/**
 * Clase abstracta para formularios
 *
 * Implementa métodos comunes para cualquier form; debiendo implementar los métodos de la interfaz.
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Validator;

/**
 * Clase abstracta para formularios
 */
abstract class Abstract_Form implements Form {

	/**
	 * Obtener etiqueta de un campo del formulario
	 *
	 * @param string $name Etiqueta del campo.
	 * @return null|string null si no se encuentra el campo, de lo contrario la etiqueta del campo.
	 */
	public function get_field_label( string $name ): ?string {
		foreach ( $this->get_fields() as $field ) {
			if ( ! is_callable( array( $field, 'get_name' ) ) ) {
				continue;
			}
			if ( $field->get_name() === $name ) {
				return $field->get_label();
			}
		}
		return null;
	}

	/**
	 * Obtener errores de validación según los valores indicados
	 *
	 * @param array $values Valores del formularios, indexados por name.
	 * @return array Errores de validación o array vacío si no hay errores
	 */
	public function get_validation_errors( $values ): array {
		if ( empty( $values ) ) {
			return array();
		}
		$validation = new Validator(
			$values,
			$this->get_validation_rules()
		);
		$is_valid   = $validation->is_valid();
		$errors     = $validation->get_error_messages();
		return $errors;
	}
}
