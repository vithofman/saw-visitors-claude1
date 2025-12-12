<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html($cs_content['subject']); ?></title>
	<style type="text/css">
		body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table { border-collapse: collapse; }
		a { color: <?php echo esc_attr($primary_color); ?>; }
		@media only screen and (max-width: 600px) {
			.container { width: 100% !important; padding: 10px !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
	
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f4f4f5;">
		<tr>
			<td align="center" style="padding: 40px 10px;">
				
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="container" style="max-width: 600px; width: 100%;">
					
					<!-- Header -->
					<tr>
						<td align="center" style="padding: 20px 0;">
							<?php if (!empty($logo_url)): ?>
								<img src="<?php echo esc_url($logo_url); ?>" 
									 alt="<?php echo esc_attr($customer['name']); ?>" 
									 style="max-height: 60px; max-width: 200px; width: auto;">
							<?php else: ?>
								<h2 style="margin: 0; color: <?php echo esc_attr($primary_color); ?>; font-size: 24px;">
									<?php echo esc_html($customer['name']); ?>
								</h2>
							<?php endif; ?>
						</td>
					</tr>
					
					<!-- Content -->
					<tr>
						<td>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
								<tr>
									<td style="padding: 30px;">
										
										<!-- CZECH -->
										<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e5e7eb; border-radius: 4px; margin-bottom: 20px;">
											<tr>
												<td style="background-color: #f3f4f6; padding: 10px 20px; font-size: 14px; font-weight: bold; color: #4b5563; border-radius: 4px 4px 0 0;">
													🇨🇿 ČESKY
												</td>
											</tr>
											<tr>
												<td style="padding: 25px;">
													<?php if (!empty($cs_content['greeting'])): ?>
													<p style="margin: 0 0 15px 0; font-size: 16px; color: #1f2937;">
														<?php echo esc_html($cs_content['greeting']); ?>
													</p>
													<?php endif; ?>
													
													<div style="font-size: 16px; line-height: 1.6; color: #374151;">
														<?php echo $cs_content['body']; ?>
													</div>
													
													<?php if (!empty($cs_content['footer'])): ?>
													<p style="margin: 20px 0 0 0; font-size: 16px; color: #1f2937;">
														<?php echo nl2br(esc_html($cs_content['footer'])); ?>
													</p>
													<?php endif; ?>
												</td>
											</tr>
										</table>
										
										<!-- Divider -->
										<hr style="border: 0; border-top: 2px dashed #d1d5db; margin: 30px 0;">
										
										<!-- ENGLISH -->
										<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e5e7eb; border-radius: 4px;">
											<tr>
												<td style="background-color: #f3f4f6; padding: 10px 20px; font-size: 14px; font-weight: bold; color: #4b5563; border-radius: 4px 4px 0 0;">
													🇬🇧 ENGLISH
												</td>
											</tr>
											<tr>
												<td style="padding: 25px;">
													<?php if (!empty($en_content['greeting'])): ?>
													<p style="margin: 0 0 15px 0; font-size: 16px; color: #1f2937;">
														<?php echo esc_html($en_content['greeting']); ?>
													</p>
													<?php endif; ?>
													
													<div style="font-size: 16px; line-height: 1.6; color: #374151;">
														<?php echo $en_content['body']; ?>
													</div>
													
													<?php if (!empty($en_content['footer'])): ?>
													<p style="margin: 20px 0 0 0; font-size: 16px; color: #1f2937;">
														<?php echo nl2br(esc_html($en_content['footer'])); ?>
													</p>
													<?php endif; ?>
												</td>
											</tr>
										</table>
										
										<!-- Company -->
										<p style="margin: 20px 0 0 0; font-size: 16px; font-weight: bold; color: #1f2937; text-align: center;">
											<?php echo esc_html($customer['name']); ?>
										</p>
										
									</td>
								</tr>
							</table>
						</td>
					</tr>
					
					<!-- Footer -->
					<tr>
						<td align="center" style="padding: 30px 20px;">
							<p style="margin: 0; font-size: 13px; color: #6b7280;">
								<?php echo esc_html($customer['name']); ?>
								<?php if (!empty($customer['contact_email'])): ?>
								| <a href="mailto:<?php echo esc_attr($customer['contact_email']); ?>" style="color: #6b7280;">
									<?php echo esc_html($customer['contact_email']); ?>
								</a>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					
				</table>
				
			</td>
		</tr>
	</table>
	
</body>
</html>