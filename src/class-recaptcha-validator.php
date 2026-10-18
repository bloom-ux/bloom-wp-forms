<?php
/**
 * Validador de reCAPTCHA
 *
 * Verifica el token g-recaptcha-response contra la API siteverify de Google
 * usando el site secret efectivo (constante, variable de entorno o pestaña
 * General, en ese orden). Sin reCAPTCHA configurado la validación es inocua,
 * de modo que los formularios pueden declarar la regla siempre.
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Validator\Validator_Interface;

/**
 * Validador de reCAPTCHA
 */
class Recaptcha_Validator implements Validator_Interface {

	const SITEVERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Resultados por token en el request actual
	 *
	 * El mismo token se valida más de una vez por envío (procesamiento y
	 * re-render del formulario con errores); siteverify se consulta una sola
	 * vez por token.
	 *
	 * @var array
	 */
	private static array $results = array();

	/**
	 * Comprobar el token contra siteverify
	 *
	 * @param mixed $value Token g-recaptcha-response del envío.
	 * @return bool
	 */
	public function is_valid( $value ): bool {
		if ( ! Settings_Admin::is_recaptcha_configured() ) {
			return true;
		}
		$token = (string) $value;
		if ( array_key_exists( $token, static::$results ) ) {
			return static::$results[ $token ];
		}
		$response = wp_remote_post(
			static::SITEVERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => Recaptcha::get_site_secret(),
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			static::$results[ $token ] = false;
			return false;
		}
		$body                      = json_decode( wp_remote_retrieve_body( $response ) );
		static::$results[ $token ] = ! empty( $body->success );
		return static::$results[ $token ];
	}

	/**
	 * Obtener el mensaje de error
	 *
	 * @return string
	 */
	public function get_message(): string {
		return __( 'No pudimos verificar que no eres un robot. Vuelve a intentarlo.', 'bloom-wp-forms' );
	}
}
