<?php
/**
 * Página de administración que muestra envíos de formularios
 *
 * @var int $total_entries Total de envíos
 * @var int $current_page Página actual de resultados
 * @var int $total_pages Total de páginas de resultados
 * @var array $table_columns Columnas de la tabla
 * @var Bloom_UX\WP_Forms\Entry[] $entries Envíos de formularios
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use DateTimeImmutable;

global $wpdb;

$now = new DateTimeImmutable( 'now', wp_timezone() );

//phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( ! empty( $_GET['action'] ) ) :
	if ( 'bloom_forms_admin__create' === filter_input( INPUT_GET, 'action' ) ) :
		require __DIR__ . '/entry-new.php';
		return;
	elseif ( 'bloom_forms_admin__view' === filter_input( INPUT_GET, 'action' ) && ! empty( $_GET['entry_id'] ) ) :
		require __DIR__ . '/entry-detail.php';
		return;
	elseif ( 'bloom_forms_admin__edit' === filter_input( INPUT_GET, 'action' ) && ! empty( $_GET['entry_id'] ) ) :
		$entry = Entries_Repository::get_instance()->find_by_id( filter_input( INPUT_GET, 'entry_id', FILTER_SANITIZE_NUMBER_INT ) );
		require __DIR__ . '/entry-new.php';
		return;
	endif;
endif;
//phpcs:enable

?>
<div class="wrap" id="bloom-forms-admin-main">
	<h1 id="bloom-forms-admin-title" class="bloom-forms-admin__title">Envíos de formularios</h1>
	<div class="tablenav top">
		<div id="bloom-forms-admin-filter" class="alignleft actions bulkactions" hx-preserve>
			<form
				id="bloom-forms-admin-filter-form"
				method="GET"
				action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
				hx-boost="true"
				hx-select="#bloom-forms-admin-main"
				hx-target="#bloom-forms-admin-main"
				hx-indicator="#bloom-forms-admin-title"
			>
				<label for="form_slug" class="screen-reader-text">Filtrar por formulario</label>
				<select name="form_slug" id="bloom_forms-form-slug">
					<option value="">Filtrar por formulario</option>
					<?php foreach ( Plugin::get_instance()->get_registered_forms_slugs() as $form_slug ) : ?>
						<option value="<?php echo esc_attr( $form_slug ); ?>" <?php echo sanitize_key( filter_input( INPUT_GET, 'form_slug' ) ) === $form_slug ? ' selected="selected"' : ''; ?>>
							<?php echo esc_html( Plugin::get_instance()->get_form( $form_slug )->get_title() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="hidden" name="page" value="bloom_forms_entries_admin">
				<input type="text" name="search" size="35" placeholder="Buscar por cualquier dato del formulario" value="<?php echo esc_attr( filter_input( INPUT_GET, 'search' ) ); ?>">
				<button type="submit" class="button">
					Buscar
				</button>
			</form>
		</div>
		<?php do_action( 'blom_forms_admin_entries_top_actions' ); ?>
		<div
			class="tablenav-pages"
			hx-boost="true"
			hx-select="#bloom-forms-admin-main"
			hx-target="#bloom-forms-admin-main"
			hx-swap="outerHTML"
			hx-indicator="#bloom-forms-admin-title"
		>
			<span class="displaying-num"><?php echo esc_html( $total_entries ); ?> envíos</span>
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
					<input form="bloom-forms-admin-filter-form" class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $current_page ); ?>" size="2" aria-describedby="table-paging">
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
	</div>
	<table class="widefat striped bloom-forms-admin__table">
		<thead>
			<tr>
				<?php foreach ( $table_columns as $key => $val ) : ?>
					<th scope="col" class="bloom-forms-admin__th <?php echo esc_attr( "bloom-forms-admin__th--{$key}" ); ?>"><?php echo esc_html( $val ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! empty( $entries ) ) : ?>
			<?php foreach ( $entries as $entry ) : ?>
				<tr>
					<?php foreach ( $table_columns as $key => $val ) : ?>
						<td class="bloom-form-admin__cell <?php echo esc_attr( "bloom-form-admin__cell--{$key}" ); ?>">
							<?php do_action( 'bloom_forms_admin_entries_cell', $entry, $key, $val ); ?>
						</td>
					<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="7">
					<div style="margin:2rem;padding:1rem;text-align:center">
						<p>No existen resultados para esta búsqueda/filtro.</p>
					</div>
				</td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
