<?php
/**
 * Componente reCAPTCHA para formularios
 *
 * Renderiza el widget según el tipo configurado: v2 (checkbox de Google) o v3
 * (token invisible que recaptcha-v3.js mantiene vigente en el campo
 * g-recaptcha-response). La site key se resuelve con la constante, la variable
 * de entorno o la pestaña General, en ese orden (ver
 * Queulat\Forms\Element\Recaptcha).
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Element\Recaptcha as Queulat_Recaptcha;

/**
 * Componente reCAPTCHA
 */
class Recaptcha extends Queulat_Recaptcha {

	const RESPONSE_FIELD_NAME = 'g-recaptcha-response';

	/**
	 * Renderizar el widget según el tipo configurado
	 *
	 * @return string
	 */
	public function __toString() {
		$site_key = static::get_site_key();

		// Sin site key no hay widget; el formulario funciona sin captcha.
		if ( '' === $site_key ) {
			return '';
		}

		if ( 'v2' === Settings_Admin::get_recaptcha_type() ) {
			return parent::__toString();
		}

		// v3: la api se carga con render=KEY y el token lo inyecta el script.
		// Google gestiona el cacheo de api.js: no se declara versión propia.
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true );
		Plugin::get_instance()->get_asset_loader()->enqueue_script( 'recaptcha-v3.js' );

		return '<input type="hidden" name="' . esc_attr( static::RESPONSE_FIELD_NAME ) . '" class="bloom-forms-recaptcha-response" data-bloom-forms-recaptcha-sitekey="' . esc_attr( $site_key ) . '" value="">';
	}

	/**
	 * Obtener el nombre del control en el formulario
	 *
	 * @return string
	 */
	public function get_name(): string {
		return static::RESPONSE_FIELD_NAME;
	}
}
