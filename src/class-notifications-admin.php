<?php

namespace Bloom_UX\WP_Forms;

use Queulat\Helpers\Abstract_Admin;

class Notifications_Admin extends Abstract_Admin {

	public function get_id(): string {
		return 'bloom_forms_notifications_admin';
	}

	public function get_title(): string {
		return 'Notificaciones';
	}

	public function get_menu_title(): string {
		return $this->get_title();
	}

	public function get_form_elements(): array {
		return array();
	}

	public function sanitize_data( $input ): array {
		return array();
	}

	public function get_validation_rules( array $sanitized_data ): array {
		return array();
	}

	public function get_icon(): string {
		return '';
	}

	public function get_parent_page(): string {
		return 'bloom_forms_entries_admin';
	}

	public function process_data( array $data ): bool {
		return true;
	}

	public function admin_page() {
		require __DIR__ . '/admin/notifications.php';
	}

}
