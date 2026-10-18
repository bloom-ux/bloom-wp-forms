/**
 * reCAPTCHA v3: mantiene vigente el token g-recaptcha-response.
 *
 * Los tokens de v3 expiran a los 2 minutos (se renueva al cargar, tras
 * contenido nuevo de htmx y cada 90 segundos) y son de un solo uso, así que:
 *
 * - Hasta que el campo no tiene token, el botón de envío queda deshabilitado
 *   (evita el envío sin token cuando la página acaba de cargar o cuando htmx
 *   reemplazó el formulario tras un envío).
 * - La renovación periódica no bloquea el envío: mantiene el token vigente en
 *   silencio hasta que llega el nuevo.
 * - Si grecaptcha no está disponible (bloqueado o con error), el bloqueo se
 *   libera pasado un tiempo para no dejar el formulario inutilizable; el
 *   servidor rechazará igualmente los envíos sin token válido.
 */
( function () {
	'use strict';

	var REFRESH_INTERVAL = 90000;
	var GATE_TIMEOUT = 10000;
	var states = new WeakMap();

	/**
	 * Obtener los formularios con campo de token en el documento.
	 *
	 * @return {HTMLFormElement[]} Formularios con reCAPTCHA v3.
	 */
	function getForms() {
		var inputs = document.querySelectorAll( 'input.bloom-forms-recaptcha-response[data-bloom-forms-recaptcha-sitekey]' );
		var forms = [];
		Array.prototype.forEach.call( inputs, function ( input ) {
			if ( input.form && forms.indexOf( input.form ) === -1 ) {
				forms.push( input.form );
			}
		} );
		return forms;
	}

	/**
	 * Obtener el campo de token de un formulario.
	 *
	 * @param {HTMLFormElement} form Formulario.
	 * @return {HTMLInputElement|null} Campo de token o null.
	 */
	function getInput( form ) {
		return form.querySelector( 'input.bloom-forms-recaptcha-response[data-bloom-forms-recaptcha-sitekey]' );
	}

	/**
	 * Habilitar o bloquear los botones de envío del formulario.
	 *
	 * @param {HTMLFormElement} form     Formulario.
	 * @param {boolean}         disabled Estado a aplicar.
	 * @return {void}
	 */
	function setSubmitsDisabled( form, disabled ) {
		var submits = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );
		Array.prototype.forEach.call( submits, function ( submit ) {
			if ( disabled ) {
				submit.setAttribute( 'disabled', 'disabled' );
			} else {
				submit.removeAttribute( 'disabled' );
			}
		} );
	}

	/**
	 * Obtener (y crear si hace falta) el estado de un formulario.
	 *
	 * @param {HTMLFormElement} form Formulario.
	 * @return {{pending: boolean, timer: number}} Estado del formulario.
	 */
	function getState( form ) {
		if ( ! states.has( form ) ) {
			states.set( form, { pending: false, timer: 0 } );
		}
		return states.get( form );
	}

	/**
	 * Liberar el bloqueo si pasado el tiempo no llegó ningún token.
	 *
	 * @param {HTMLFormElement} form  Formulario.
	 * @param {HTMLInputElement} input Campo de token.
	 * @param {Object}          state Estado del formulario.
	 * @return {void}
	 */
	function armRelease( form, input, state ) {
		window.clearTimeout( state.timer );
		state.timer = window.setTimeout( function () {
			if ( ! input.value ) {
				setSubmitsDisabled( form, false );
			}
		}, GATE_TIMEOUT );
	}

	/**
	 * Ejecutar grecaptcha y volcar el token en el campo.
	 *
	 * @param {HTMLFormElement}  form  Formulario.
	 * @param {HTMLInputElement} input Campo de token.
	 * @param {Object}           state Estado del formulario.
	 * @return {void}
	 */
	function execute( form, input, state ) {
		if ( typeof window.grecaptcha === 'undefined' || typeof window.grecaptcha.ready !== 'function' ) {
			return;
		}
		state.pending = true;
		var siteKey = input.getAttribute( 'data-bloom-forms-recaptcha-sitekey' );
		window.grecaptcha.ready( function () {
			window.grecaptcha
				.execute( siteKey, { action: 'bloom_forms' } )
				.then( function ( token ) {
					input.value = token;
					state.pending = false;
					window.clearTimeout( state.timer );
					setSubmitsDisabled( form, false );
				} )
				.catch( function () {
					// Sin token: el temporizador de liberación del bloqueo decide.
					state.pending = false;
				} );
		} );
	}

	/**
	 * Asegurar un token: bloquea el envío hasta tenerlo.
	 *
	 * @param {HTMLFormElement} form Formulario.
	 * @return {void}
	 */
	function ensureToken( form ) {
		var input = getInput( form );
		if ( ! input ) {
			return;
		}
		var state = getState( form );
		if ( input.value ) {
			setSubmitsDisabled( form, false );
			return;
		}
		if ( state.pending ) {
			return;
		}
		setSubmitsDisabled( form, true );
		armRelease( form, input, state );
		execute( form, input, state );
	}

	/**
	 * Renovar el token en silencio, sin bloquear el envío.
	 *
	 * @param {HTMLFormElement} form Formulario.
	 * @return {void}
	 */
	function renewToken( form ) {
		var input = getInput( form );
		if ( ! input ) {
			return;
		}
		var state = getState( form );
		if ( state.pending || ! input.value ) {
			return;
		}
		execute( form, input, state );
	}

	/**
	 * Aplicar una acción a cada formulario con reCAPTCHA del documento.
	 *
	 * @param {Function} callback Acción por formulario.
	 * @return {void}
	 */
	function eachForm( callback ) {
		getForms().forEach( callback );
	}

	window.addEventListener( 'load', function () {
		eachForm( ensureToken );
	} );
	window.setInterval( function () {
		eachForm( renewToken );
	}, REFRESH_INTERVAL );
	// Contenido nuevo tras un swap de htmx: recarga el token del formulario.
	document.addEventListener( 'htmx:load', function () {
		eachForm( ensureToken );
	} );
} )();
