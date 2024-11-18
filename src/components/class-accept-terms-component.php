<?php

namespace Bloom_UX\WP_Forms;

class Accept_Terms_Component extends Custom_Checkbox {
	public function get_options() {
		return array(
			__( 'Acepto los términos y condiciones', 'falabella_forms' ),
		);
	}
	public function get_name() : string {
		return 'accept_terms';
	}
	public function get_attribute( string $attr ) : string {
		return 'required' === $attr ? 'required' : parent::get_attribute( $attr );
	}
}
