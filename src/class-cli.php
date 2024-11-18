<?php
/**
 * CLI for WP Forms
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use function WP_CLI\Utils\format_items;

/**
 * CLI WP Forms
 */
class CLI {

	/**
	 * Obtener datos de un envío por id (o --latest)
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : ID del envío
	 *
	 * [--latest]
	 * : Obtener el último envío
	 *
	 * @param array $args Argumentos.
	 * @param array $assoc_args Argumentos asociativos.
	 * @subcommand get-entry
	 */
	public function get_entry( $args, $assoc_args ) {
		if ( isset( $assoc_args['latest'] ) ) {
			$entries = Entries_Repository::get_instance()->find_by_query(
				array(
					'per_page' => 1,
				)
			);
			$entry   = $entries[0] ?? null;
		} else {
			list( $id ) = $args;
			$entry      = Entries_Repository::get_instance()->find_by_id( (int) $id );
		}
		$data = (object) $this->serialize_entry( $entry );
		print_r( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); //phpcs:ignore
		exit;
	}

	/**
	 * Listar envíos de formularios
	 *
	 * ## OPTIONS
	 *
	 * [--form=<form>]
	 * : Filtrar por slug de formulario
	 *
	 * [--from=<from>]
	 * : Filtrar por fecha de envío desde
	 *
	 * [--to=<to>]
	 * : Filtrar por fecha de envío hasta
	 *
	 * [--per_page=<per_page>]
	 * : Cantidad de resultados por página
	 *
	 * [--sender_email=<sender_email>]
	 * : Filtrar por email del remitente
	 *
	 * [--page=<page>]
	 * : Página a mostrar
	 *
	 * @subcommand list-entries
	 * @param array $args Argumentos.
	 * @param array $assoc_args Argumentos asociativos.
	 * @return void
	 */
	public function list_entries( $args, $assoc_args ) {
		$allowed_params = array(
			'form',
			'from',
			'to',
			'per_page',
			'sender_email',
			'page',
		);
		$query_params   = array_intersect_key(
			$assoc_args,
			array_combine( $allowed_params, $allowed_params )
		);
		$entries        = Entries_Repository::get_instance()->find_by_query( $query_params );
		if ( ! $entries ) {
			die( 'No hay' );
		}
		$data    = $this->serialize_entries( $entries );
		$headers = array_keys( $data[0] );
		format_items( $assoc_args['format'] ?? 'table', $data, $headers );
	}

	/**
	 * Serializar un array de entradas
	 *
	 * @param Entry[] $entries Array de objetos de entrada.
	 * @return array Datos serializados
	 */
	private function serialize_entries( $entries ): array {
		return array_map(
			function ( Entry $entry ) {
				$data = $entry->get_data();
				return array(
					'id'           => $entry->get_id(),
					'form'         => $entry->get_form()->get_slug(),
					'from_name'    => $data->from_name,
					'from_email'   => $data->from_email,
					'submitted_on' => $entry->get_submitted_on()->format( 'c' ),
				);
			},
			$entries
		);
	}

	/**
	 * Serializar un objeto de entrada
	 *
	 * @param Entry $entry Objeto de entrada.
	 * @return array Datos serializados
	 */
	private function serialize_entry( Entry $entry ): array {
		return array(
			'id'           => $entry->get_id(),
			'form'         => $entry->get_form()->get_slug(),
			'submitted_on' => $entry->get_submitted_on()->format( 'c' ),
			'data'         => $entry->get_data(),
			'meta'         => $entry->get_meta(),
		);
	}

	/**
	 * Reenviar una notificación por id
	 *
	 * ## OPTIONS
	 *
	 * <notification_id>
	 * : ID de la notificación
	 *
	 * @subcommand resend-notification
	 * @param mixed $args Argumentos.
	 * @return void
	 */
	public function resend_notification( $args ) {
		$notification = Notifications_Repository::get_instance()->find_by_id( $args[0] );
		if ( ! $notification ) {
			wp_die( 'No existe' );
		}
		$notification->send_async();
	}

	/**
	 * Mostrar lista de notificaciones recientes
	 *
	 * @subcommand list-notifications
	 * @return void
	 */
	public function list_notifications() {
		$notifications = Notifications_Repository::get_instance()->find_by_query( array() );
		$data          = $this->serialize_notifications( $notifications );
		$headers       = array_keys( $data[0] );
		format_items( 'table', $data, $headers );
	}

	/**
	 * Mostrar la info de una notificación por id
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID de la notificación
	 *
	 * @subcommand get-notification
	 * @param array $args Argumentos.
	 */
	public function get_notification( $args ) {
		list( $id )   = $args;
		$notification = Notifications_Repository::get_instance()->find_by_id( (int) $id );
		$data         = array(
			'id'         => $notification->get_id(),
			'form'       => $notification->get_form() ? $notification->get_form()->get_slug() : '',
			'entry_id'   => $notification->get_entry() ? $notification->get_entry()->get_id() : '',
			'email'      => $notification->get_email(),
			'created_on' => $notification->get_created_on() ? $notification->get_created_on()->format( 'Y-m-d H:i:s' ) : '',
			'status_log' => $notification->get_status_log(),
			'meta'       => $notification->get_meta(),
		);
		print_r( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); //phpcs:ignore
		exit;
	}

	/**
	 * Obtener algunos datos de la notificación para mostrar en pantalla
	 *
	 * @param Notification[] $notifications Objetos de notificaciones.
	 * @return array Datos serializados
	 */
	private function serialize_notifications( array $notifications ): array {
		return array_map(
			function ( Notification $notification ) {
				$status      = $notification->get_status_log();
				$last_status = (array) array_shift( $status );
				return array(
					'id'         => $notification->get_id(),
					'form'       => $notification->get_form() ? $notification->get_form()->get_slug() : '',
					'entry_id'   => $notification->get_entry() ? $notification->get_entry()->get_id() : '',
					'email'      => $notification->get_email(),
					'created_on' => $notification->get_created_on() ? $notification->get_created_on()->format( 'Y-m-d H:i:s' ) : '',
					'subject'    => $notification->get_subject(),
					'status'     => implode( '; ', $last_status ),
				);
			},
			$notifications
		);
	}
}
