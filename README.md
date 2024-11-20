# WP Forms

Scaffolding para formularios personalizados en WordPress

# Qué proporciona el plugin

* Almacenamiento en una tabla personalizada, con columnas JSON para "form_data" y "meta".
* Clase `Entries_Repository` para leer/escribir a base de datos.
* UI de administración para listar elementos y ver detalle.
* CLI para listar o ver datos de un envío específico: `wp bloom-forms

# Cómo agregar un formulario

* Crear una clase que implemente la interfaz `Bloom_UX\WP_Forms\Form` o que extienda `Bloom_UX\WP_Forms\Abstract_Form`.
* Cada formulario debe contener un campo `"bloom_forms_{$form->get_slug()}__submit"`.
* Cada formulario debe contener un campo `"bloom_forms_{$form->get_slug()}__submit-nonce"` con un nonce válido.

# Extender o adaptar el plugin

Puedes usar las acciones o filtros definidos en el plugin para integraciones más específicas.

Identifica los lugares en que se definen buscando: `(?:do_action|apply_filters)\(\s*(?:'|")bloom_forms`
