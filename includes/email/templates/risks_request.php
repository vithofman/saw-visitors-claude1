<?php
if (!defined('ABSPATH')) exit;
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- Alert Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; margin: 25px 0;">
	<tr>
		<td style="padding: 24px;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td width="50" valign="top">
						<span style="font-size: 32px;">⚠️</span>
					</td>
					<td valign="top">
						<p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #991b1b;">
							<?php echo esc_html($t['alert_title'] ?? 'Vyžadováno doplnění informací'); ?>
						</p>
						<p style="margin: 0; font-size: 14px; color: #b91c1c;">
							<?php echo esc_html($t['alert_description'] ?? 'Pro dokončení registrace k návštěvě je nutné doplnit informace o pracovních rizicích.'); ?>
						</p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- Visit Info -->
<?php if (!empty($t['branch_name']) || !empty($t['customer_name'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
	<tr>
		<td style="padding: 20px;">
			<?php if (!empty($t['customer_name'])): ?>
			<p style="margin: 0 0 8px 0; font-size: 15px; color: #374151;">
				<strong>🏢 <?php echo esc_html($t['company_label'] ?? 'Společnost'); ?>:</strong> 
				<?php echo esc_html($t['customer_name']); ?>
			</p>
			<?php endif; ?>
			
			<?php if (!empty($t['branch_name'])): ?>
			<p style="margin: 0; font-size: 15px; color: #374151;">
				<strong>📍 <?php echo esc_html($t['branch_label'] ?? 'Pobočka'); ?>:</strong> 
				<?php echo esc_html($t['branch_name']); ?>
			</p>
			<?php endif; ?>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if (!empty($t['link_intro'])): ?>
<p style="margin: 25px 0 20px 0; font-size: 16px; color: #374151;">
	<?php echo esc_html($t['link_intro']); ?>
</p>
<?php endif; ?>

<!-- CTA Button -->
<?php if (!empty($t['risks_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 25px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#dc2626" style="border-radius: 8px; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.25);">
						<a href="<?php echo esc_url($t['risks_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 16px 40px; font-size: 16px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Doplnit informace'); ?> →
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if (!empty($t['help_text'])): ?>
<p style="margin: 20px 0 0 0; font-size: 14px; color: #6b7280; text-align: center;">
	<?php echo esc_html($t['help_text']); ?>
</p>
<?php endif; ?>