<?php
/**
 * Plantilla de notificaciones
 *
 * @var string $template_path Ruta a la plantilla de correo
 * @var string $action_link Enlace a la administración
 * @var Entry $entry Datos del envío de formulario
 * @var \Queulat\Forms\Element\Form $form Formulario con los datos pre-llenados
 * @var array $values Valores del envío de formulario
 * @var ?string $notification_type Tipo de notificación
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html lang="es">
<head>
	<!--[if gte mso 15]>
		<xml>
			<o:OfficeDocumentSettings>
				<o:AllowPNG/>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	<![endif]-->
	<!--[if mso]>
		<style type="text/css">
		body,table,td { font-family: 'Ubuntu',Helvetica,Arial,sans-serif !important; }
		table { border-collapse: collapse !important; border-spacing: 0 !important; }
		</style>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?php echo esc_html( $form->get_property( 'title' ) ); ?></title>
</head>
<body style="margin: 0; background-color: #f6f6f6; color: #222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 22px; min-width: 100%; padding: 0; max-width: 650px;">
	<table align="center" style="margin: 0 auto; border-spacing: 0; color: #222; font-family: Arial, Helvetica, sans-serif; width: 100%; max-width: 650px;">
		<tr style="margin-top: 0; margin-right: 0; margin-bottom: 0; margin-left: 0; padding-top: 0; padding-right: 0; padding-bottom: 0; padding-left: 0;">
			<td style="margin-top: 0; margin-right: 0; margin-bottom: 0; margin-left: 0; padding-top: 0; padding-right: 0; padding-bottom: 0; padding-left: 0;">
				<!--[if (gte mso 9)|(IE)]>
					<table width="650" align="center">
					<tr>
					<td>
					<![endif]-->
					<table cellspacing="0" cellpadding="0" border="0" align="center" bgcolor="#ffffff" style="margin: 0 auto; background-color: #fff; border-spacing: 0; color: #222; font-family: Arial, Helvetica, sans-serif; max-width: 650px; min-width: 320px; width: 100%; padding: 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
					<tr>
						<td align="center">
							<table align="center" style="margin-left: auto; margin-right: auto; border-spacing: 0; max-width: 650px; width: 100%;">
								<tr>
									<td align="center" bgcolor="#ffffff" style="font-family: Arial, Helvetica, sans-serif; padding: 32px 20px 0 20px; text-align: center; background-color: #fff;">
										<table cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto; border-spacing: 0; width: 100%; max-width: 470px;">
											<tr>
												<td style="text-align: left; color: #222; font-size: 16px; line-height: 24px;">
													<?php
														// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														echo $form;
													?>
												</td>
											</tr>
										</table>
									</td>
								</tr> <!-- /main genérico -->
							</table>
						</td>
					</tr>
				</table>
				<!--[if (gte mso 9)|(IE)]>
					</td>
					</tr>
					</table>
					<![endif]-->
			</td>
		</tr>
	</table>
</body>
</html>
