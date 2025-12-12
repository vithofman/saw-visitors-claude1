<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($language); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html($content['subject']); ?></title>
	<style type="text/css">
		body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
		a { color: <?php echo esc_attr($primary_color); ?>; }
		@media only screen and (max-width: 600px) {
			.container { width: 100% !important; padding: 10px !important; }
			.content { padding: 20px !important; }
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
									<td class="content" style="padding: 40px;">
										
										<?php if (!empty($content['greeting'])): ?>
										<p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #1f2937;">
											<?php echo esc_html($content['greeting']); ?>
										</p>
										<?php endif; ?>
										
										<div style="font-size: 16px; line-height: 1.6; color: #374151;">
											<?php echo $content['body']; ?>
										</div>
										
										<?php if (!empty($content['footer'])): ?>
										<p style="margin: 30px 0 0 0; font-size: 16px; line-height: 1.6; color: #1f2937;">
											<?php echo nl2br(esc_html($content['footer'])); ?>
										</p>
										<?php endif; ?>
										
										<p style="margin: 10px 0 0 0; font-size: 16px; font-weight: bold; color: #1f2937;">
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
							</p>
							<?php if (!empty($customer['address_street']) || !empty($customer['address_city'])): ?>
							<p style="margin: 5px 0 0 0; font-size: 13px; color: #6b7280;">
								<?php 
								$address_parts = array_filter(array(
									$customer['address_street'] ?? '',
									$customer['address_city'] ?? '',
									$customer['address_zip'] ?? '',
								));
								echo esc_html(implode(', ', $address_parts));
								?>
							</p>
							<?php endif; ?>
							<?php if (!empty($customer['contact_email'])): ?>
							<p style="margin: 5px 0 0 0; font-size: 13px; color: #6b7280;">
								<a href="mailto:<?php echo esc_attr($customer['contact_email']); ?>" style="color: #6b7280;">
									<?php echo esc_html($customer['contact_email']); ?>
								</a>
							</p>
							<?php endif; ?>
						</td>
					</tr>
					
				</table>
				
			</td>
		</tr>
	</table>
	
</body>
</html>