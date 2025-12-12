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
	<!--[if mso]>
	<style type="text/css">
		table { border-collapse: collapse; }
		.column { width: 48% !important; display: inline-block !important; }
	</style>
	<![endif]-->
	<style type="text/css">
		body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table { border-collapse: collapse; }
		a { color: <?php echo esc_attr($primary_color); ?>; }
		
		/* Mobile styles - maximální jednoduchost */
		@media only screen and (max-width: 680px) {
			body {
				background-color: #ffffff !important;
			}
			.outer-wrapper {
				background-color: #ffffff !important;
				padding: 0 !important;
			}
			.container { 
				width: 100% !important; 
				padding: 0 !important;
				margin: 0 !important;
			}
			.main-card {
				border-radius: 0 !important;
				box-shadow: none !important;
				padding: 15px !important;
			}
			.column { 
				display: block !important; 
				width: 100% !important;
				max-width: 100% !important;
				padding: 0 0 20px 0 !important;
			}
			.column:last-child {
				padding-bottom: 0 !important;
			}
			.lang-box {
				border: none !important;
				border-radius: 0 !important;
			}
			.lang-header {
				border-radius: 0 !important;
				margin: 0 -15px !important;
				padding: 12px 15px !important;
			}
			.lang-content {
				padding: 15px 0 !important;
			}
			.header-section {
				padding: 15px 0 !important;
			}
			.footer-section {
				padding: 15px !important;
			}
			.company-footer {
				margin: 15px 0 0 0 !important;
				padding-top: 15px !important;
				border-top: 1px solid #e5e7eb !important;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
	
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="outer-wrapper" style="background-color: #f4f4f5;">
		<tr>
			<td align="center" style="padding: 40px 10px;">
				
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="1200" class="container" style="max-width: 1200px; width: 100%;">
					
					<!-- Header -->
					<tr>
						<td align="center" class="header-section" style="padding: 20px 0;">
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
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="main-card" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
								<tr>
									<td style="padding: 20px;">
										
										<!--[if mso]>
										<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
										<tr>
										<td width="48%" valign="top">
										<![endif]-->
										
										<!-- CZECH -->
										<div class="column" style="display: inline-block; width: 48%; max-width: 48%; vertical-align: top; padding: 10px; box-sizing: border-box;">
											<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="lang-box" style="border: 1px solid #e5e7eb; border-radius: 6px;">
												<tr>
													<td class="lang-header" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); padding: 12px 16px; border-radius: 6px 6px 0 0;">
														<span style="font-size: 14px; font-weight: bold; color: #ffffff;">🇨🇿 ČESKY</span>
													</td>
												</tr>
												<tr>
													<td class="lang-content" style="padding: 20px; font-size: 14px; line-height: 1.6; color: #374151;">
														<?php if (!empty($cs_content['greeting'])): ?>
														<p style="margin: 0 0 12px 0; color: #1f2937;">
															<?php echo esc_html($cs_content['greeting']); ?>
														</p>
														<?php endif; ?>
														
														<div>
															<?php echo $cs_content['body']; ?>
														</div>
														
														<?php if (!empty($cs_content['footer'])): ?>
														<p style="margin: 16px 0 0 0; color: #1f2937;">
															<?php echo nl2br(esc_html($cs_content['footer'])); ?>
														</p>
														<?php endif; ?>
													</td>
												</tr>
											</table>
										</div>
										
										<!--[if mso]>
										</td>
										<td width="4%"></td>
										<td width="48%" valign="top">
										<![endif]-->
										
										<!-- ENGLISH -->
										<div class="column" style="display: inline-block; width: 48%; max-width: 48%; vertical-align: top; padding: 10px; box-sizing: border-box;">
											<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="lang-box" style="border: 1px solid #e5e7eb; border-radius: 6px;">
												<tr>
													<td class="lang-header" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); padding: 12px 16px; border-radius: 6px 6px 0 0;">
														<span style="font-size: 14px; font-weight: bold; color: #ffffff;">🇬🇧 ENGLISH</span>
													</td>
												</tr>
												<tr>
													<td class="lang-content" style="padding: 20px; font-size: 14px; line-height: 1.6; color: #374151;">
														<?php if (!empty($en_content['greeting'])): ?>
														<p style="margin: 0 0 12px 0; color: #1f2937;">
															<?php echo esc_html($en_content['greeting']); ?>
														</p>
														<?php endif; ?>
														
														<div>
															<?php echo $en_content['body']; ?>
														</div>
														
														<?php if (!empty($en_content['footer'])): ?>
														<p style="margin: 16px 0 0 0; color: #1f2937;">
															<?php echo nl2br(esc_html($en_content['footer'])); ?>
														</p>
														<?php endif; ?>
													</td>
												</tr>
											</table>
										</div>
										
										<!--[if mso]>
										</td>
										</tr>
										</table>
										<![endif]-->
										
										<!-- Company -->
										<p class="company-footer" style="margin: 20px 0 0 0; font-size: 14px; font-weight: bold; color: #1f2937; text-align: center; clear: both;">
											<?php echo esc_html($customer['name']); ?>
										</p>
										
									</td>
								</tr>
							</table>
						</td>
					</tr>
					
					<!-- Footer -->
					<tr>
						<td align="center" class="footer-section" style="padding: 20px;">
							<p style="margin: 0; font-size: 12px; color: #6b7280;">
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