<?php
/**
 * Pantalla de administración de notificaciones
 *
 * La visualización de la tabla en sí se contruye en notifications-table.php
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

$n_per_page        = 25;
$notifications     = Notifications_Repository::get_instance()->find_by_query(
	array(
		'per_page' => $n_per_page,
		'paged'    => filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) ? filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) : 1,
		'status'   => sanitize_key( filter_input( INPUT_GET, 'status' ) ),
	)
);
$all_link          = admin_url( 'admin.php?page=bloom_forms_notifications_admin' );
$pending_link      = admin_url( 'admin.php?page=bloom_forms_notifications_admin&status=scheduled' );
$failed_link       = admin_url( 'admin.php?page=bloom_forms_notifications_admin&status=send_error' );
$sent_link         = admin_url( 'admin.php?page=bloom_forms_notifications_admin&status=send_success' );
$counts            = Plugin::get_instance()->get_notifications_counts_by_status();
$current_page      = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) ? (int) filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ) : 1;
$total_entries     = Notifications_Repository::get_instance()->get_total();
$total_pages       = ceil( $total_entries / $n_per_page );
$first_page_url    = add_query_arg( array( 'paged' => 1 ), admin_url( 'admin.php?page=bloom_forms_notifications_admin' ) );
$previous_page_url = add_query_arg( array( 'paged' => $current_page - 1 ), admin_url( 'admin.php?page=bloom_forms_notifications_admin' ) );
$next_page_url     = add_query_arg( array( 'paged' => $current_page + 1 ), admin_url( 'admin.php?page=bloom_forms_notifications_admin' ) );
$last_page_url     = add_query_arg( array( 'paged' => $total_pages ), admin_url( 'admin.php?page=bloom_forms_notifications_admin' ) );
?>
<div class="wrap">
	<h1>Notificaciones</h1>
	<ul class="subsubsub">
		<li>
			<a <?php echo ( ! filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING ) ? 'class="current" aria-current="page"' : '' ); ?> href="<?php echo esc_url( $all_link ); ?>">Todo
			<?php if ( ! empty( $counts['all'] ) ) : ?>
			<span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span>
			<?php endif; ?>
			</a> |
		</li>
		<?php if ( ! empty( $counts['scheduled'] ) ) : ?>
		<li>
			<a <?php echo ( 'scheduled' === filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING ) ? 'class="current" aria-current="page"' : '' ); ?>href="<?php echo esc_url( $pending_link ); ?>"> Pendientes
			<span class="count">(<?php echo esc_html( $counts['scheduled'] ); ?>)</span></a> |
		</li>
		<?php endif; ?>
		<?php if ( ! empty( $counts['send_error'] ) ) : ?>
		<li>
			<a <?php echo ( 'send_error' === filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING ) ? 'class="current" aria-current="page"' : '' ); ?>href="<?php echo esc_url( $failed_link ); ?>"> Con errores
			<span class="count">(<?php echo esc_html( $counts['send_error'] ); ?>)</span></a> |
		</li>
		<?php endif; ?>
		<?php if ( ! empty( $counts['send_success'] ) ) : ?>
		<li>
			<a <?php echo ( 'send_success' === filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING ) ? 'class="current" aria-current="page"' : '' ); ?>href="<?php echo esc_url( $sent_link ); ?>"> Enviadas
			<span class="count">(<?php echo esc_html( $counts['send_success'] ); ?>)</span></a>
		</li>
		<?php endif; ?>
	</ul>
	<div class="tablenav-pages alignright">
		<span class="displaying-num"><?php echo esc_html( $total_entries ); ?> notificaciones</span>
		<span class="pagination-links">
			<?php if ( $current_page > 1 ) : ?>
			<a class="first-page button" href="<?php echo esc_url( $first_page_url ); ?>">
				<span class="screen-reader-text">Primera página</span><span aria-hidden="true">«</span>
			</a>
			<a class="prev-page button" href="<?php echo esc_url( $previous_page_url ); ?>">
				<span class="screen-reader-text">Página anterior</span><span aria-hidden="true">‹</span>
			</a>
			<?php else : ?>
			<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
			<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
			<?php endif; ?>
			<span class="paging-input">
				<label for="current-page-selector" class="screen-reader-text">Página actual</label>
				<input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $current_page ); ?>" size="2" aria-describedby="table-paging">
				<span class="tablenav-paging-text"> de <span class="total-pages"><?php echo esc_attr( $total_pages ); ?></span></span>
			</span>
			<?php if ( $current_page < $total_pages ) : ?>
			<a class="next-page button" href="<?php echo esc_url( $next_page_url ); ?>">
				<span class="screen-reader-text">Página siguiente</span><span aria-hidden="true">›</span>
			</a>
			<a class="last-page button" href="<?php echo esc_url( $last_page_url ); ?>">
				<span class="screen-reader-text">Última página</span><span aria-hidden="true">»</span></a>
			</span>
			<?php else : ?>
			<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
			<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
			<?php endif; ?>
	</div>
	<div class="clear"></div>
	<?php require __DIR__ . '/notifications-table.php'; ?>
</div>
