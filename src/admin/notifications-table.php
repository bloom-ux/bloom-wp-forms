<?php
/**
 * Mostrar tabla con notificaciones
 *
 * @var Bloom_UX\WP_Forms\Notification[] $notifications Notificaciones a mostrar
 * @package Bloom_UX\WP_Forms
 */

use Bloom_UX\WP_Forms\Plugin;

?>
<table class="widefat striped" style="margin-top:1rem;">
	<thead>
		<tr>
			<th scope="col">ID</th>
			<th scope="col">Formulario</th>
			<th scope="col">Solicitante</th>
			<th scope="col">Destinatario</th>
			<th scope="col">Fecha</th>
			<th scope="col">Status</th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! empty( $notifications ) ) : ?>
		<?php foreach ( $notifications as $n ) : ?>
			<tr>
				<td>
					<?php echo esc_html( $n->get_id() ); ?>
				</td>
				<td>
					<a href="<?php echo esc_url( $n->get_entry() ? $n->get_entry()->get_admin_link() : '' ); ?>">
						<?php echo esc_html( $n->get_form() ? $n->get_form()->get_title() : '—' ); ?>
					</a>
				</td>
				<td>
					<?php echo esc_html( $n->get_entry() ? $n->get_entry()->get_sender_name() : '—' ); ?>
				</td>
				<td>
					<?php echo esc_html( $n->get_email() ); ?>
				</td>
				<td>
					<?php echo esc_html( $n->get_created_on()->format( 'Y-m-d H:i:s' ) ); ?>
				</td>
				<td>
					<?php if ( $n->was_read() ) : ?>
						Leída <span class="dashicons dashicons-yes"></span><span class="dashicons dashicons-yes" style="margin-left:-.5em"></span>
					<?php else : ?>
						<?php echo esc_html( $n->get_last_status_label() ); ?>
					<?php endif; ?>
					<?php if ( $n->get_last_status() === 'send_error' ) : ?>
					- <a href="<?php echo esc_url( Plugin::get_instance()->get_resend_url( $n ) ); ?>">Reenviar</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	<?php else : ?>
		<tr>
			<td colspan="6" style="padding:5rem 0;text-align:center;">No hay notificaciones</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>
