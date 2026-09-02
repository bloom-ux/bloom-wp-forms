# Bloom WP Forms

Scaffolding para formularios personalizados en WordPress.

# Qué proporciona el plugin

* Almacenamiento en tablas personalizadas, con columnas JSON para "form_data" y "meta" (`bloom_forms_entries` y `bloom_forms_notifications`).
* Clases `Entries_Repository` y `Notifications_Repository` para leer/escribir a base de datos.
* UI de administración: menú top-level "Formularios" con listado de envíos (`Entries_Admin`), detalle de envío (`Admin_View`), notificaciones (`Notifications_Admin`) y página de ajustes con pestañas (`Settings_Admin`).
* Envío de notificaciones por correo por cada envío procesado, con reintentos programados (`Send_Notification_Task`).
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

Ejemplo mínimo de `get_notification_emails()`:

```php
public function get_notification_emails( array $data ): array {
	$emails = array( get_bloginfo( 'admin_email' ) );
	$emails = apply_filters( 'mi_form_notification_emails', $emails, $data );
	return $emails;
}
```

# Página de ajustes

Ubicación: menú "Formularios" → "Ajustes" (`admin.php?page=bloom_forms_settings`).

* Todo se guarda en una única opción no-autoload: `bloom_forms_settings`.
* El option_value está keyeado por slug de pestaña:

```php
array(
	'general' => array(
		'recaptcha_site_key'    => '',
		'recaptcha_site_secret' => '',
	),
	'contact' => array(
		'subjects' => array(
			array( 'label' => 'Tema', 'emails' => array( 'correo@ejemplo.cl' ) ),
		),
	),
)
```

* La página muestra un listado de pestañas y solo el contenido de la pestaña activa, seleccionada por el parámetro GET `tab`.
* Semántica de mezcla al guardar: solo la pestaña activa se postea; `sanitize_settings()` mezcla con el valor guardado a nivel de keys top-level, de modo que las demás pestañas se conservan intactas.

Para extenderla con una pestaña propia:

* Filtro `bloom_forms_settings_tabs`: recibe el array `slug => label` de pestañas; el slug es la key dentro del option_value.
* Acción `bloom_forms_settings_tab__{slug}`: renderiza el panel de la pestaña (sin argumentos).
* Filtro `bloom_forms_settings_sanitize( $settings, $input )`: sanitiza las claves propias sobre los ajustes ya mezclados.

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
| `queulat/forms/element/recaptcha__site-key` / `queulat/forms/element/recaptcha__site-secret` | `string $value` — keys del widget reCAPTCHA de Queulat (alimentadas desde la pestaña General) |

# Evento programado y CLI

* Cron `bloom_forms__retry_scheduled` (hourly): reintenta el envío de notificaciones en estado `scheduled`.
* CLI `wp bloom-forms`:
  * `wp bloom-forms get-entry <id>|--latest`
  * `wp bloom-forms list-entries [--form=<slug>] [--from=<fecha>] [--to=<fecha>] [--per_page=<n>] [--sender_email=<email>] [--page=<n>]`
  * `wp bloom-forms resend-notification <id>`
  * `wp bloom-forms list-notifications`
  * `wp bloom-forms get-notification <id>`
