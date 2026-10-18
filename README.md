# Bloom WP Forms

Scaffolding para formularios personalizados en WordPress.

# Qué proporciona el plugin

* Almacenamiento en tablas personalizadas, con columnas JSON para "form_data" y "meta" (`bloom_forms_entries` y `bloom_forms_notifications`).
* Clases `Entries_Repository` y `Notifications_Repository` para leer/escribir a base de datos.
* UI de administración: menú top-level "Formularios" con listado de envíos (`Entries_Admin`), detalle de envío (`Admin_View`), notificaciones (`Notifications_Admin`) y página de ajustes con pestañas (`Settings_Admin`).
* Envío de notificaciones por correo por cada envío procesado: cada envío exitoso crea y envía una notificación por destinatario configurado (ver "Página de ajustes"), con reintentos programados (`Send_Notification_Task`).
* Bloque de editor "Formulario" (`bloom-forms/form-picker`) para insertar un formulario activo por su tipo.
* Soporte de reCAPTCHA (v2/v3) con claves por constante, variable de entorno o pestaña General; el token se valida contra siteverify y no se guarda con el envío.
* Encolado de htmx en el front solo en páginas que contienen formularios (bloque o shortcode con el slug del formulario).
* Test de Salud del sitio `bloom_forms_recipients`: queda en estado crítico mientras algún formulario activo no tenga destinatarios.
* CLI para listar o ver datos de un envío específico: `wp bloom-forms`.
* Página de ajustes de formularios en "Formularios → Ajustes" (ver sección más abajo).

# Requisitos

* PHP >= 8.2.
* Dependencias declaradas en `composer.json`: `bloom-ux/queulat` y `bloom-ux/wpdb-monolog`.

# Cómo agregar un formulario

* Crear una clase que implemente la interfaz `Bloom_UX\WP_Forms\Form` o que extienda `Bloom_UX\WP_Forms\Abstract_Form` (que implementa la validación y la resolución de etiquetas).
* Registrar la instancia con `\Bloom_UX\WP_Forms\Plugin::get_instance()->register_form( $form )`; el slug del formulario (`get_slug()`) identifica cada envío.
* El formulario renderizado debe incluir:
  * un campo oculto `bloom_form_slug` con el slug del formulario;
  * un campo oculto `action` con el valor `bloom_forms_{slug}__submit`;
  * un nonce con nombre `bloom_forms_{slug}__submit-nonce` y acción `bloom_forms_{slug}__submit`.
* Los correos de notificación se configuran en la pestaña que el formulario agrega automáticamente en "Formularios → Ajustes" y se leen en `get_notification_emails()` con `Settings_Admin::get_form_recipients( $this->get_slug() )`. Un formulario sin destinatarios no genera notificaciones (queda en evidencia en Salud del sitio y en el log del canal `bloom_forms`).
* Para que el bloque "Formulario" pueda insertarlo, la implementación debe registrar un shortcode cuyo tag sea el slug del formulario; el bloque renderiza `do_shortcode( "[{slug}]" )`.

Ejemplo mínimo de `get_notification_emails()`:

```php
public function get_notification_emails( array $data ): array {
	return \Bloom_UX\WP_Forms\Settings_Admin::get_form_recipients( $this->get_slug() );
}
```

# Bloque Formulario

* Nombre: `bloom-forms/form-picker`; atributo `form_slug` (slug de un formulario registrado).
* En el editor muestra un placeholder con un selector entre los formularios activos (los entrega el servidor como `window.bloomFormsData`); al elegir uno resume el título + slug y permite cambiarlo.
* En el front renderiza el shortcode cuyo tag es el slug, y hace que las páginas con el bloque encolen htmx.
* Fuente: `src/blocks/form-picker/`; compilar con `npm run build:blocks` (salida en `build/blocks/form-picker/`, generada con `@wordpress/scripts`). Sin build el sitio no se rompe: el bloque simplemente no se registra.

# Página de ajustes

Ubicación: menú "Formularios" → "Ajustes" (`admin.php?page=bloom_forms_settings`).

* Todo se guarda en una única opción no-autoload: `bloom_forms_settings`.
* El option_value está keyeado por slug de pestaña:

```php
array(
	'general' => array(
		'recaptcha_type'        => 'v3',
		'recaptcha_site_key'    => '',
		'recaptcha_site_secret' => '',
	),
	'newsletter' => array(                    // pestaña automática de un formulario registrado.
		'recipients' => array( 'correo@ejemplo.cl', 'otro@ejemplo.cl' ),
	),
	'contact' => array(                       // pestaña propia de una implementación.
		'subjects' => array(
			array( 'label' => 'Tema', 'emails' => array( 'correo@ejemplo.cl' ) ),
		),
	),
)
```

* `recaptcha_type` acepta `v3` (invisible, sin checkbox; valor por defecto) o `v2` (checkbox "No soy un robot"); cualquier otro valor cae en `v3`. Las claves deben corresponder al tipo elegido.

* La página muestra un listado de pestañas y solo el contenido de la pestaña activa, seleccionada por el parámetro GET `tab`.
* Semántica de mezcla al guardar: solo la pestaña activa se postea; `sanitize_settings()` mezcla con el valor guardado a nivel de keys top-level, de modo que las demás pestañas se conservan intactas.

Para extenderla con una pestaña propia:

* Filtro `bloom_forms_settings_tabs`: recibe el array `slug => label` de pestañas; el slug es la key dentro del option_value.
* Acción `bloom_forms_settings_tab__{slug}`: renderiza el panel de la pestaña (sin argumentos).
* Filtro `bloom_forms_settings_sanitize( $settings, $input )`: sanitiza las claves propias sobre los ajustes ya mezclados.

# Pestañas por formulario

* Cada formulario registrado agrega automáticamente su pestaña a "Formularios → Ajustes": slug de pestaña = `get_slug()`, label = `get_title()`, y su payload vive en `bloom_forms_settings[<slug>]['recipients']` (lista de correos).
* El panel por defecto es un campo de correos separados por coma; una implementación puede reemplazarlo registrando la acción `bloom_forms_settings_tab__{slug}`.
* Al guardar, el saneamiento solo toca las keys de formularios registrados; las pestañas propias de una implementación siguen a cargo del filtro `bloom_forms_settings_sanitize`.
* Los correos inválidos o duplicados se descartan al guardar y se avisa con un settings error (el resto del guardado se conserva). El saneamiento es idempotente (la API de Settings puede invocarlo más de una vez por guardado).
* Accessors: `Settings_Admin::get_recipients()` (mapa de formularios con correos) y `Settings_Admin::get_form_recipients( $slug )` (lista de un formulario). Los formularios los usan en `get_notification_emails()`.
* El cableado del envío vive en `Plugin::notify_form_recipients()`: cada envío exitoso crea una notificación por destinatario y la envía; sin destinatarios no se registra nada.

# Salud del sitio

* Test directo `bloom_forms_recipients` (filtro `site_status_tests`): estado `critical` si algún formulario activo no tiene destinatarios (cada faltante enlaza a su pestaña y el botón apunta a la primera), `good` si todos los tienen o si no hay formularios.

# reCAPTCHA

* Tipo: `RECAPTCHA_VERSION` (constante o variable de entorno) o la pestaña General (`v3` por defecto); cualquier otro valor cae en `v3`. Claves: `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SITE_SECRET` (constantes o variables de entorno) o la pestaña General; constante y entorno tienen prioridad sobre la pestaña.
* `Bloom_UX\WP_Forms\Recaptcha` renderiza el widget según el tipo: v2 (checkbox de Google) o v3 (campo oculto `g-recaptcha-response` más `assets/dist/recaptcha-v3.js`, que renueva el token cada 90 segundos). El JS se compila con `npm run build`.
* La implementación del formulario agrega el componente a sus campos (o a su builder), conserva el token en `sanitize_data()` y declara `Bloom_UX\WP_Forms\Recaptcha_Validator` en `get_validation_rules()`.
* `Recaptcha_Validator` consulta siteverify con el site secret efectivo; sin reCAPTCHA configurado no hace nada (la regla puede declararse siempre). El token no se almacena con los datos del envío.
* Sin site key no se renderiza widget y el formulario funciona sin captcha.

# Referencia de acciones y filtros

## Acciones

| Hook | Argumentos |
| --- | --- |
| `bloom_forms_{slug}__submit_success` | `$form`, `$values`, `$created` — envío exitosamente procesado |
| `bloom_forms_admin_entry_detail_before` | `Admin_View $view` — antes de renderizar el detalle de un envío |
| `bloom_forms_admin_entry_detail_manage` | `Entry $entry` — zona de acciones del detalle de un envío |
| `bloom_forms_admin_entries_cell` | `$entry`, `$column`, `$column_label` — celda del listado de envíos |
| `blom_forms_admin_entries_top_actions` | (sin argumentos) — acciones superiores del listado; nota: errata histórica en el prefijo `blom_forms` |
| `bloom_forms_settings_tab__{slug}` | (sin argumentos) — panel de una pestaña de la página de ajustes |

## Filtros

| Hook | Argumentos |
| --- | --- |
| `bloom_forms_admin_entries_capability` | `string $capability` — capacidad requerida para el listado de envíos |
| `bloom_forms_admin_view_element_value` | `$value`, `$element`, `$view` — valor mostrado de un elemento en la vista de administración |
| `bloom_forms_create_entry_data` | `$entry_data`, `$form` — datos antes de insertar un envío |
| `bloom_forms_notification_class` | `$class`, `Notification $notification` — clase de vista de notificación |
| `bloom_forms_notification_template_path` | `$path`, `$notification` — ruta del template de notificación |
| `bloom_forms_notification_message_output` | `$output`, `$notification`, `$view` — mensaje renderizado de la notificación |
| `bloom_forms_notification_mail_to` / `bloom_forms_notification_mail_subject` / `bloom_forms_notification_mail_message` / `bloom_forms_notification_mail_headers` | `$value`, `$notification` — partes del correo antes de enviar |
| `bloom_forms_settings_tabs` | `array $tabs` — pestañas de la página de ajustes (`slug => label`) |
| `bloom_forms_settings_sanitize` | `$settings`, `$input` — ajustes ya mezclados antes de guardar |
| `bloom_forms_recaptcha_type` | `string $type` — tipo de reCAPTCHA (`v3` o `v2`); prioridad constante/variable `RECAPTCHA_VERSION`, luego pestaña General; valores inválidos caen en `v3` |
| `queulat/forms/element/recaptcha__site-key` / `queulat/forms/element/recaptcha__site-secret` | `string $value` — keys del widget reCAPTCHA de Queulat (alimentadas desde la pestaña General) |

# Changelog

## Sin publicar

* Una pestaña de destinatarios por formulario en la página de ajustes (storage `bloom_forms_settings[<slug>]['recipients']`), con saneamiento idempotente y aviso por correos descartados.
* Cableado de notificaciones: cada envío exitoso crea y envía una notificación por destinatario configurado; sin destinatarios no se registra nada y queda aviso en el log del canal `bloom_forms`.
* Test directo de Salud del sitio `bloom_forms_recipients` (crítico mientras un formulario activo no tenga destinatarios).
* htmx se encola en el front solo en páginas con formularios (bloque o shortcode con el slug); antes solo se encolaba en la pantalla de envíos.
* Bloque de editor "Formulario" (`bloom-forms/form-picker`) para insertar un formulario activo por su tipo; se compila con `npm run build:blocks`.
* Soporte de reCAPTCHA v2/v3: componente `Recaptcha` (widget v2 o campo de token v3 con `recaptcha-v3.js`), regla `Recaptcha_Validator` (siteverify con el site secret efectivo) y resolución de tipo y claves por constante/variable de entorno/pestaña General. El token no se almacena con los datos del envío.
* `Notification::get_subject()` ya no emite un aviso por leer una propiedad inexistente cuando el envío no incluye `from_name`.

## 0.3.0

* Ajuste de tipo de reCAPTCHA en la pestaña General: `v3` sin checkbox (valor por defecto) o `v2` con checkbox; las claves deben corresponder al tipo elegido.
* Nuevo filtro `bloom_forms_recaptcha_type`.

# Evento programado y CLI

* Cron `bloom_forms__retry_scheduled` (hourly): reintenta el envío de notificaciones en estado `scheduled`.
* CLI `wp bloom-forms`:
  * `wp bloom-forms get-entry <id>|--latest`
  * `wp bloom-forms list-entries [--form=<slug>] [--from=<fecha>] [--to=<fecha>] [--per_page=<n>] [--sender_email=<email>] [--page=<n>]`
  * `wp bloom-forms resend-notification <id>`
  * `wp bloom-forms list-notifications`
  * `wp bloom-forms get-notification <id>`
