<?php
/**
 * Página de administración para consultar los detalles de un envío
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

use Queulat\Forms\Element\Form;

$entry   = Entries_Repository::get_instance()->find_by_id( filter_input( INPUT_GET, 'entry_id', FILTER_SANITIZE_NUMBER_INT ) );
$theform = new Form();
$theform->set_view( Admin_View::class );
$theform->set_property( 'title', $entry->get_form()->get_title() );
$theform->set_property( 'submitted_date', $entry->get_submitted_on() );
$values = (array) $entry->get_data();
foreach ( $entry->get_form()->get_fields() as $field ) {
	$can_set_value = is_callable( array( $field, 'get_name' ) ) && is_callable( array( $field, 'set_value' ) ) ? $field->get_name() : null;
	if ( $can_set_value && ! empty( $values[ $can_set_value ] ) ) {
		$field->set_value( $values[ $can_set_value ] );
	}
	$theform->append_child( $field );
}

// @todo No implementado de momento pero se podría habilitar (quizás más elegantemente como método de la clase).
$entry_edit_link = add_query_arg(
	array(
		'page'     => 'bloom_forms_entries_admin',
		'action'   => 'bloom_forms_admin__edit',
		'new_form' => $entry->get_form()->get_slug(),
		'entry_id' => $entry->get_id(),
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap">
	<p style="font-size:23px;line-height:1.3;color:#1d2327;margin:9px 0 1rem;">Envíos de formularios</p>
	<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=bloom_forms_entries_admin' ) ); ?>">&lsaquo; Volver a listado de envíos</a></div>
	<div class="bloom-forms-entry">
		<div class="bloom-forms-entry__data">
			<?php echo $theform; //phpcs:ignore ?>
			<h2 style="font-size:1.3rem;margin: calc( 1em + 1rem ) 0;">Notificaciones</h2>
			<?php
				$notifications = $entry->get_notifications();
				require __DIR__ . '/notifications-table.php';
			?>
		</div>
		<div class="bloom-forms-entry__manage">
			<?php do_action( 'bloom_forms_admin_entry_detail_manage', $entry ); ?>
		</div>
	</div>
</div>
