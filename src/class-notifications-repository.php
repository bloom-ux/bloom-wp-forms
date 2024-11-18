<?php

namespace Bloom_UX\WP_Forms;

use wpdb;
use Queulat\Singleton;

class Notifications_Repository {
	use Singleton;

	const PER_PAGE_DEFAULT = 25;

	/**
	 *
	 * @var wpdb
	 */
	private $wpdb;
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}
	private function get_column_formats() {
		return array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );
	}

	public function find_by_id( int $id ): ?Notification {
		$query  = $this->wpdb->prepare( "SELECT * FROM {$this->wpdb->bloom_forms_notifications} WHERE id = %d", $id );
		$result = $this->wpdb->get_row( $query );
		if ( ! $result ) {
			return null;
		}
		return new Notification( $result );
	}

	public function find_by_query( $args = array() ) : ?array {
		$query  = "SELECT * FROM {$this->wpdb->bloom_forms_notifications} WHERE 1 = %d ";
		$params = array( 1 );
		if ( ! empty( $args['entry_id'] ) ) {
			$query   .= ' AND entry_id = %d ';
			$params[] = (int) $args['entry_id'];
		}
		$all_status = Notification::get_status_labels();
		if ( ! empty( $args['status'] ) && isset( $all_status[ $args['status'] ] ) ) {
			$query   .= " AND JSON_VALUE( status_log, '$[0].status' ) = %s ";
			$params[] = $args['status'];
		}
		$query   .= ' ORDER BY id DESC';

		$per_page = ! empty( $args['per_page'] ) ? (int) $args['per_page'] : static::PER_PAGE_DEFAULT;
		if ( ! empty( $args['page'] ) ) {
			$offset = $per_page * ( (int) $args['page'] - 1 );
		} else {
			$offset = 0;
		}
		$query   .= ' LIMIT %d, %d';
		$params[] = $offset;
		$params[] = $per_page;

		$prepared = $this->wpdb->prepare( $query, $params );
		$results  = $this->wpdb->get_results( $prepared );
		if ( ! $results ) {
			return null;
		}
		return array_map(
			function( $result ) {
				return new Notification( $result );
			},
			$results
		);
	}

	public function create( Notification $notification ) {
		$serialized = $this->serialize_notification( $notification );
		unset( $serialized['id'] );
		$formats = $this->get_column_formats();
		array_shift( $formats );
		$this->wpdb->insert(
			$this->wpdb->bloom_forms_notifications,
			$serialized,
			$formats
		);
		return (int) $this->wpdb->insert_id;
	}
	public function save( Notification $notification ) {
		$serialized = $this->serialize_notification( $notification );
		$this->wpdb->update(
			$this->wpdb->bloom_forms_notifications,
			$serialized,
			array(
				'id' => $notification->get_id(),
			),
			$this->get_column_formats(),
			array( '%d' )
		);
	}
	private function serialize_notification( Notification $notification ) {
		return array(
			'id'         => $notification->get_id(),
			'form'       => $notification->get_form() ? $notification->get_form()->get_slug() : '',
			'entry_id'   => $notification->get_entry() ? $notification->get_entry()->get_id() : 0,
			'email'      => $notification->get_email(),
			'created_on' => $notification->get_created_on() ? $notification->get_created_on()->format( 'Y-m-d H:i:s' ) : wp_date( 'Y-m-d H:i:s' ),
			'status_log' => wp_json_encode( $notification->get_status_log() ),
			'meta'       => wp_json_encode( (object) $notification->get_meta() ),
		);
	}

	public function get_total() : int {
		$query = "SELECT COUNT(*) FROM {$this->wpdb->bloom_forms_notifications}";
		return (int) $this->wpdb->get_var( $query );
	}
}
