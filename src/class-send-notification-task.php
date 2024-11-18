<?php

namespace Bloom_UX\WP_Forms;

use WP_Async_Request;

class Send_Notification_Task extends WP_Async_Request {
	protected $action = 'bloom_forms_send_notification';

	protected function handle() {
		$notification_id = (int) filter_input( INPUT_POST, 'notification_id', FILTER_SANITIZE_NUMBER_INT );
		$notification    = Notification::get( $notification_id );
		if ( ! $notification ) {
			Plugin::get_instance()->get_logger()->error( 'No se encontró la notificación con ID: ' . $notification_id );
		}
		$email   = $notification->get_email();
		$subject = $notification->get_subject();
		$msg     = $notification->get_message();
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		add_action(
			'wp_mail_failed',
			function ( $error_message, $mail_error_data = array() ) use ( $notification_id ) {
				$notification = Notification::get( $notification_id );
				$notification->set_meta(
					(object) array(
						'error_message'   => $error_message,
						'mail_error_data' => $mail_error_data,
					)
				);
				$notification->register_status( 'wp_mail_failed' );
				$notification->save();
			},
			10,
			2
		);
		add_action(
			'wp_mail_succeeded',
			function ( $mail_data ) use ( $notification_id ) {
				$notification = Notification::get( $notification_id );
				$notification->set_meta(
					(object) array(
						'mail_data' => $mail_data,
					)
				);
				$notification->register_status( 'wp_mail_succeeded' );
				$notification->save();
			}
		);
		$mail_submit = wp_mail( $email, $subject, $msg, $headers );
		if ( $mail_submit ) {
			// Guardar dato de envío exitoso.
			$notification->update_status( 'send_success' );
		} else {
			$notification->update_status( 'send_error' );
		}
		return $mail_submit;
	}
}
