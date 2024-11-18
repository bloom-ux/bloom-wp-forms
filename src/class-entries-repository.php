<?php
/**
 * Repositorio de entradas
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use wpdb;
use DateTimeImmutable;
use Queulat\Singleton;

/**
 * Repositorio de entradas
 */
class Entries_Repository {
	use Singleton;

	/**
	 * Instancia de wpdb para interactuar con base de datos
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Total de resultados para la última consulta
	 *
	 * @var int
	 */
	private $total_results = 0;

	const PER_PAGE_DEFAULT = 50;

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Crear nueva entrada en base de datos
	 *
	 * @param string $form Slug del formulario.
	 * @param array  $data Datos del formulario.
	 * @param array  $meta Metadatos de la entrada indexados por key.
	 * @return int|null ID de la entrada creada o null si falla
	 */
	public function create( string $form, array $data, array $meta = array() ): ?int {
		$insert = $this->wpdb->insert(
			$this->wpdb->bloom_forms_entries,
			array(
				'form'         => $form,
				'submitted_on' => wp_date( 'Y-m-d H:i:s.v' ),
				'form_data'    => wp_json_encode( $data ),
				'meta'         => wp_json_encode( (object) $meta ),
			),
			'%s'
		);
		if ( ! $insert ) {
			return null;
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Actualizar entrada
	 *
	 * @param Entry $entry Instancia de entrada actualizada.
	 * @return bool Verdadero si se actualiza correctamente
	 */
	public function update( Entry $entry ): bool {
		$update = $this->wpdb->update(
			$this->wpdb->bloom_forms_entries,
			array(
				'meta' => wp_json_encode( $entry->get_meta() ),
			),
			array( 'id' => $entry->get_id() ),
			array( '%s' )
		);
		return (bool) $update;
	}

	/**
	 * Sanitizar criterio de ordenamiento de resultados
	 *
	 * @param string $orderby Criterio de ordenamiento.
	 * @return string Criterio de ordenamiento sanitizado
	 */
	private function sanitize_orderby( string $orderby ): string {
		return in_array(
			$orderby,
			array(
				'id',
				'form',
				'submitted_on',
			),
			true
		) ? $orderby : '';
	}

	/**
	 * Sanitizar orden de consulta
	 *
	 * @param string $order Orden de consulta.
	 * @return string Orden de consulta sanitizado
	 */
	private function sanitize_order( string $order ): string {
		return in_array(
			mb_strtoupper( $order ),
			array( 'ASC', 'DESC', 'RAND()' ),
			true
		) ? mb_strtoupper( $order ) : '';
	}

	/**
	 * Sanitizar fecha y hora
	 *
	 * @param string $timestamp Fecha y hora.
	 * @return string Fecha y hora en formato Y-m-d H:i:s
	 */
	private function sanitize_datetime( $timestamp ): string {
		try {
			$datetime = new DateTimeImmutable( $timestamp, wp_timezone() );
			return $datetime->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Obtener envíos según parámetros de búsqueda
	 *
	 * @param array $args {
	 *    Argumentos de búsqueda.
	 *    @type string $form Slug del formulario.
	 *    @type string $from Fecha desde (formato Y-m-d H:i:s).
	 *    @type string $to Fecha hasta (formato Y-m-d H:i:s).
	 *    @type string $search Buscar por texto en form_data y meta.
	 * }
	 * @return Entry[]|array Entradas encontradas o array vacío
	 */
	public function find_by_query( array $args = array() ): array {
		$params = array();
		$query  = " SELECT SQL_CALC_FOUND_ROWS * FROM {$this->wpdb->bloom_forms_entries} WHERE 1 = 1 ";
		if ( ! empty( $args['form'] ) && in_array( $args['form'], Plugin::get_instance()->get_registered_forms_slugs(), true ) ) {
			$query   .= ' AND form = %s ';
			$params[] = $args['form'];
		}
		if ( ! empty( $args['from'] ) ) {
			$from = $this->sanitize_datetime( $args['from'] );
			if ( ! empty( $from ) ) {
				$query   .= ' AND submitted_on > %s ';
				$params[] = $from;
			}
		}
		if ( ! empty( $args['to'] ) ) {
			$from = $this->sanitize_datetime( $args['to'] );
			if ( ! empty( $to ) ) {
				$query   .= ' AND submitted_on < %s ';
				$params[] = $to;
			}
		}

		if ( ! empty( $args['search'] ) ) {
			/**
			 * La búsqueda se realiza en los campos form_data y meta de modo case insensitive
			 * (se convierte todo a minúsculas antes de buscar)
			 */
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$query   .= " AND ( JSON_SEARCH( LOWER( form_data ), 'one', %s ) IS NOT NULL OR JSON_SEARCH( LOWER( meta ), 'one', %s ) IS NOT NULL ) ";
			$params[] = mb_strtolower( $like );
			$params[] = mb_strtolower( $like );
		}

		$orderby = ! empty( $args['orderby'] ) ? $this->sanitize_orderby( $args['orderby'] ) : 'id';
		$order   = ! empty( $args['order'] ) ? $this->sanitize_order( $args['order'] ) : 'DESC';
		$query  .= " ORDER BY $orderby $order ";

		$per_page = ! empty( $args['per_page'] ) ? (int) $args['per_page'] : static::PER_PAGE_DEFAULT;
		if ( ! empty( $args['page'] ) ) {
			$offset = $per_page * ( (int) $args['page'] - 1 );
		} else {
			$offset = 0;
		}
		$query   .= 'LIMIT %d, %d';
		$params[] = $offset;
		$params[] = $per_page;

		$prepared            = $this->wpdb->prepare( $query, $params ); //phpcs:ignore
		$results             = $this->wpdb->get_results( $prepared ); //phpcs:ignore
		$entries             = array_map(
			function ( $entry ) {
				return new Entry( $entry );
			},
			$results
		);
		$this->total_results = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );
		return $entries;
	}

	/**
	 * Obtener envío por ID
	 *
	 * @param int $id ID del envío.
	 * @return Entry|null
	 */
	public function find_by_id( int $id ): ?Entry {
		$query  = "SELECT * FROM {$this->wpdb->bloom_forms_entries} WHERE id = %d";
		$result = $this->wpdb->get_row( $this->wpdb->prepare( $query, $id ) ); //phpcs:ignore
		if ( ! $result ) {
			return null;
		}
		return new Entry( $result );
	}

	/**
	 * Obtener total de resultados para la última consulta
	 *
	 * @return int
	 */
	public function get_total(): int {
		return (int) $this->total_results;
	}
}
