<?php
/**
 * Pantalla de administración con listado de envíos de formulario
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use DateTime;
use DateInterval;
use DateTimeImmutable;
use Queulat\Helpers\Abstract_Admin;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Administración de envíos de formularios
 */
class Entries_Admin extends Abstract_Admin {

	/**
	 * Inicializar acciones y filtros de la clase
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'bloom_forms_admin_entries_cell', array( $this, 'render_entries_cell' ), 10, 2 );
		parent::init();
	}

	/**
	 * Obtener capacidad requerida para acceder a la página de administración
	 *
	 * @return strin Capacidad requerida
	 */
	public function get_required_capability(): string {
		return apply_filters( 'bloom_forms_admin_entries_capability', Plugin::DEFAULT_READ_CAPABILITY );
	}

	/**
	 * Obtener posición del menú de administración
	 *
	 * @return int
	 */
	public function get_position(): int {
		return 100;
	}

	/**
	 * Obtener ID del menú de administración
	 *
	 * @return string Slug del menú de administración
	 */
	public function get_id(): string {
		return 'bloom_forms_entries_admin';
	}

	/**
	 * Obtener título de la página de administración
	 *
	 * @return string Título de la página de administración
	 */
	public function get_title(): string {
		return 'Envíos de formulario';
	}

	/**
	 * Obtener título para el menú
	 *
	 * @return string Título del menú de administración
	 */
	public function get_menu_title(): string {
		return 'Formularios';
	}

	/**
	 * Obtener elementos del formulario para la página
	 *
	 * @return array Elementos del formulario
	 */
	public function get_form_elements(): array {
		return array();
	}

	/**
	 * Sanitizar datos
	 *
	 * @param array $input Datos enviados por el formulario.
	 * @return array
	 */
	public function sanitize_data( $input ): array {
		return array();
	}

	/**
	 * Obtener reglas de validación de los datos
	 *
	 * @param array $sanitized_data Datos sanitizados.
	 * @return array
	 */
	public function get_validation_rules( array $sanitized_data ): array {
		return array();
	}

	/**
	 * Obtener icono para menú de administración
	 *
	 * @return string Icono Dashicons de "retroalimentación"
	 */
	public function get_icon(): string {
		return 'dashicons-feedback';
	}

	/**
	 * Procesar datos del formulario
	 *
	 * En este caso no hay procesamiento de datos de formulario
	 *
	 * @param array $data Datos enviados por el formulario.
	 * @return bool Estado de procesamiento de datos
	 */
	public function process_data( array $data ): bool {
		return true;
	}

	/**
	 * Página de administración de envíos de formularios
	 *
	 * @return void
	 */
	public function admin_page() {
		$per_page      = 25;
		$current_page  = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) ? (int) filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) : 1;
		$query         = array(
			'form'     => sanitize_key( filter_input( INPUT_GET, 'form_slug' ) ),
			'per_page' => $per_page,
			'page'     => $current_page,
			'status'   => sanitize_text_field( filter_input( INPUT_GET, 'form_status' ) ),
			'search'   => sanitize_text_field( filter_input( INPUT_GET, 'search' ) ),
		);
		$entries       = Entries_Repository::get_instance()->find_by_query( $query );
		$total_entries = Entries_Repository::get_instance()->get_total();
		$total_pages   = ceil( $total_entries / $per_page );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_url          = add_query_arg( wp_unslash( $_GET ), admin_url( 'admin.php?page=bloom_forms_entries_admin' ) );
		$first_page_url    = add_query_arg( array( 'paged' => 1 ), $base_url );
		$previous_page_url = add_query_arg( array( 'paged' => $current_page - 1 ), $base_url );
		$next_page_url     = add_query_arg( array( 'paged' => $current_page + 1 ), $base_url );
		$last_page_url     = add_query_arg( array( 'paged' => $total_pages ), $base_url );
		$month_options     = $this->get_month_options();
		$table_columns     = array(
			'id'            => 'ID',
			'form'          => 'Formulario',
			'date'          => 'Fecha de recepción',
			'notifications' => 'Notificaciones',
		);
		require __DIR__ . '/admin/entries.php';
	}

	/**
	 * Generar output en tabla de envíos
	 *
	 * @param Entry  $entry Envío del formulario.
	 * @param string $column "Slug" de la columna.
	 * @return void
	 */
	public function render_entries_cell( Entry $entry, string $column ) {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		switch ( $column ) {
			case 'id':
				echo '<a href="' . esc_url( $entry->get_admin_link() ) . '">';
				echo esc_html( $entry->get_id() );
				echo '</a>';
				break;
			case 'form':
				echo esc_html( $entry->get_form()->get_title() );
				break;
			case 'date':
				echo esc_html( wp_date( 'j F Y, H:i:s', $entry->get_submitted_on()->format( 'U' ) ) );
				if ( $now->format( 'U' ) - $entry->get_submitted_on()->format( 'U' ) < DAY_IN_SECONDS ) :
					echo '<br>(hace ' . esc_html( human_time_diff( $entry->get_submitted_on()->format( 'U' ) ) ) . ')';
				endif;
				break;
			case 'notifications':
				$notifications = $entry->get_notifications();
				$by_status     = $entry->get_notifications_by_status();
				foreach ( $by_status as $send_status => $notifications ) :
					switch ( $send_status ) {
						case 'scheduled':
							echo '<details><summary>';
							echo 'Programadas (' . count( $notifications ) . ') <span class="dashicons dashicons-clock"></span></summary>';
							echo '<ul>';
							foreach ( $notifications as $notification ) :
								echo '<li>' . esc_html( $notification->get_email() ) . '</li>';
							endforeach;
							echo '</ul>';
							echo '</details>';
							break;
						case 'send_error':
							echo '<details><summary>';
							echo 'Con errores (' . count( $notifications ) . ') <span class="dashicons dashicons-no"></span></summary>';
							echo '<ul>';
							foreach ( $notifications as $notification ) :
								echo '<li>' . esc_html( $notification->get_email() );
								echo ' - <a href="' . esc_url( Plugin::get_instance()->get_resend_url( $notification ) ) . '">Reenviar</a>';
								echo '</li>';
							endforeach;
							echo '</ul>';
							echo '</details>';
							break;
						case 'send_success':
							echo '<details><summary>';
							echo 'Enviadas (' . count( $notifications ) . ') <span class="dashicons dashicons-yes"></span></summary>';
							echo '<ul>';
							foreach ( $notifications as $notification ) :
								echo '<li>' . esc_html( $notification->get_email() ) . '</li>';
							endforeach;
							echo '</ul>';
							echo '</details>';
							break;
						case 'read':
							echo '<details><summary>';
							echo 'Leída (' . count( $notifications ) . ') <span class="dashicons dashicons-yes"></span><span class="dashicons dashicons-yes" style="margin-right:-.5em"></span></summary>';
							echo '<ul>';
							foreach ( $notifications as $notification ) :
								echo '<li>' . esc_html( $notification->get_email() ) . '</li>';
							endforeach;
							echo '</ul>';
							echo '</details>';
							break;
					}
				endforeach;
				break;
		}
	}

	/**
	 * Obtener listado de meses con envíos de formularios
	 *
	 * @return array Array con claves en formato 'YYYY-MM' y valores en formato 'F Y' (Ej: '2021-01' => 'Enero 2021')
	 */
	private function get_month_options(): array {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( "SELECT DISTINCT DATE_FORMAT(submitted_on, '%Y-%m') as month FROM {$wpdb->bloom_forms_entries} ORDER BY month DESC" );
		return array_reduce(
			$results,
			function ( $carry, $item ) {
				$date                  = DateTime::createFromFormat( 'Y-m', $item->month, wp_timezone() );
				$carry[ $item->month ] = wp_date( 'F Y', $date->getTimestamp() );
				return $carry;
			},
			array()
		);
	}
}
