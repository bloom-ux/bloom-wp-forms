<?php

namespace Bloom_UX\WP_Forms;

interface Form {
	public function get_title() : string;
	public function get_slug() : string;
	public function get_fields() : array;
	public function get_validation_rules() : array;
	public function sanitize_data( array $input ) : array;
	public function get_notification_emails( array $data ): array;
}
