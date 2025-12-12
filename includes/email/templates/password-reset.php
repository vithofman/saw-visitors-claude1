<?php
if (!defined('ABSPATH')) exit;
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- Security Icon Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 30px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="background-color: #eff6ff; border-radius: 50%; width: 80px; height: 80px;">
				<tr>
					<td align="center" valign="middle" style="font-size: 36px;">
						🔑
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php if (!empty($t['link_intro'])): ?>
<p style="margin: 20px 0; font-size: 16px; color: #374151; text-align: center;">
	<?php echo esc_html($t['link_intro']); ?>
</p>
<?php endif; ?>

<!-- CTA Button -->
<?php if (!empty($t['reset_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 30px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#1e40af" style="border-radius: 8px; box-shadow: 0 4px 6px rgba(30, 64, 175, 0.25);">
						<a href="<?php echo esc_url($t['reset_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 16px 48px; font-size: 16px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Nastavit nové heslo'); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if (!empty($t['link_validity'])): ?>
<p style="margin: 20px 0; font-size: 14px; color: #6b7280; text-align: center;">
	⏱️ <?php echo esc_html($t['link_validity']); ?>
</p>
<?php endif; ?>

<!-- Warning Box -->
<?php if (!empty($t['ignore_notice'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 8px; margin: 30px 0 0 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 13px; color: #6b7280;">
				💡 <?php echo esc_html($t['ignore_notice']); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- Security Notice -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 20px 0 0 0;">
	<tr>
		<td style="padding: 16px 0; border-top: 1px solid #e5e7eb;">
			<p style="margin: 0; font-size: 12px; color: #9ca3af; text-align: center;">
				🔒 <?php echo esc_html($t['security_notice'] ?? 'Tento odkaz nikdy nesdílejte s nikým jiným.'); ?>
			</p>
		</td>
	</tr>
</table>