<?php
if (!defined('ABSPATH')) exit;
?>

<!-- Success Icon -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 20px 0 30px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0fdf4; border-radius: 50%; width: 80px; height: 80px;">
				<tr>
					<td align="center" valign="middle" style="font-size: 36px;">
						✅
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- Success Message -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; margin: 0 0 25px 0;">
	<tr>
		<td style="padding: 24px; text-align: center;">
			<p style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #166534;">
				<?php echo esc_html($t['success_title'] ?? 'Heslo bylo úspěšně změněno'); ?>
			</p>
			<p style="margin: 0; font-size: 14px; color: #15803d;">
				<?php echo esc_html($t['intro'] ?? ''); ?>
			</p>
		</td>
	</tr>
</table>

<!-- Change Details -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
	<tr>
		<td style="padding: 20px;">
			<p style="margin: 0 0 8px 0; font-size: 14px; color: #374151;">
				<strong>📅 <?php echo esc_html($t['changed_label'] ?? 'Změněno'); ?>:</strong> 
				<?php echo esc_html($t['changed_at'] ?? current_time('d.m.Y H:i')); ?>
			</p>
			<p style="margin: 0; font-size: 14px; color: #374151;">
				<strong>💻 <?php echo esc_html($t['device_label'] ?? 'Zařízení'); ?>:</strong> 
				<?php echo esc_html($t['device'] ?? 'Webový prohlížeč'); ?>
			</p>
		</td>
	</tr>
</table>

<!-- Security Warning -->
<?php if (!empty($t['security_notice'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 0 8px 8px 0; margin: 25px 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 14px; color: #991b1b; font-weight: 500;">
				⚠️ <?php echo esc_html($t['security_notice']); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- Help Text -->
<p style="margin: 25px 0 0 0; font-size: 13px; color: #6b7280; text-align: center;">
	<?php echo esc_html($t['help_text'] ?? 'Pokud máte jakékoli dotazy, kontaktujte nás.'); ?>
</p>