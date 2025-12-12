<?php
if (!defined('ABSPATH')) exit;
?>

<!-- Welcome Banner -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); border-radius: 12px; margin: 0 0 30px 0;">
	<tr>
		<td align="center" style="padding: 40px 30px;">
			<p style="margin: 0 0 8px 0; font-size: 36px;">
				👋
			</p>
			<p style="margin: 0; font-size: 24px; font-weight: 600; color: #ffffff;">
				<?php echo esc_html($t['welcome_title'] ?? 'Vítejte!'); ?>
			</p>
		</td>
	</tr>
</table>

<p style="margin: 0 0 25px 0; font-size: 16px; color: #374151;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- Login Credentials Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin: 25px 0; overflow: hidden;">
	<tr>
		<td style="padding: 0;">
			
			<!-- Header -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #1e40af;">
				<tr>
					<td style="padding: 14px 20px;">
						<p style="margin: 0; font-size: 14px; color: #ffffff; font-weight: 600;">
							🔐 <?php echo esc_html($t['login_info'] ?? 'Přihlašovací údaje'); ?>
						</p>
					</td>
				</tr>
			</table>
			
			<!-- Credentials -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td style="padding: 24px;">
						
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 16px;">
							<tr>
								<td width="40%" style="font-size: 14px; color: #6b7280; padding: 8px 0;">
									<?php echo esc_html($t['username_label'] ?? 'Uživatelské jméno'); ?>:
								</td>
								<td style="font-size: 15px; font-weight: 600; color: #111827; padding: 8px 0;">
									<?php echo esc_html($t['username'] ?? ''); ?>
								</td>
							</tr>
						</table>
						
						<?php if (!empty($t['temporary_password'])): ?>
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef3c7; border-radius: 8px;">
							<tr>
								<td style="padding: 16px;">
									<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
										<tr>
											<td width="40%" style="font-size: 14px; color: #92400e; padding: 0;">
												<?php echo esc_html($t['password_label'] ?? 'Dočasné heslo'); ?>:
											</td>
											<td style="font-size: 16px; font-weight: 700; color: #92400e; font-family: 'Courier New', monospace; letter-spacing: 1px; padding: 0;">
												<?php echo esc_html($t['temporary_password']); ?>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
						<?php endif; ?>
						
					</td>
				</tr>
			</table>
			
		</td>
	</tr>
</table>

<!-- CTA Button -->
<?php if (!empty($t['login_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 30px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#1e40af" style="border-radius: 8px; box-shadow: 0 4px 6px rgba(30, 64, 175, 0.25);">
						<a href="<?php echo esc_url($t['login_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 16px 48px; font-size: 16px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Přihlásit se'); ?> →
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- Password Change Notice -->
<?php if (!empty($t['password_notice'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 0 8px 8px 0; margin: 25px 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 14px; color: #1e40af;">
				💡 <?php echo esc_html($t['password_notice']); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- Help Section -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 30px 0 0 0; border-top: 1px solid #e5e7eb;">
	<tr>
		<td style="padding: 20px 0 0 0; text-align: center;">
			<p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #374151;">
				<?php echo esc_html($t['need_help'] ?? 'Potřebujete pomoc?'); ?>
			</p>
			<p style="margin: 0; font-size: 13px; color: #6b7280;">
				<?php echo esc_html($t['help_text'] ?? 'Kontaktujte nás na podpora@example.com'); ?>
			</p>
		</td>
	</tr>
</table>