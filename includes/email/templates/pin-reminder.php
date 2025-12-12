<?php
if (!defined('ABSPATH')) exit;
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- PIN Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; margin: 25px 0;">
	<tr>
		<td align="center" style="padding: 30px;">
			<p style="margin: 0 0 10px 0; font-size: 14px; color: #166534; text-transform: uppercase; letter-spacing: 1px;">
				<?php echo esc_html($t['pin_label'] ?? 'Váš PIN kód'); ?>
			</p>
			<p style="margin: 0; font-size: 42px; font-weight: 700; color: #166534; letter-spacing: 8px; font-family: 'Courier New', monospace;">
				<?php echo esc_html($t['pin_code'] ?? ''); ?>
			</p>
		</td>
	</tr>
</table>

<!-- Visit Info -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
	<tr>
		<td style="padding: 20px;">
			<?php if (!empty($t['branch_name'])): ?>
			<p style="margin: 0 0 8px 0; font-size: 15px; color: #374151;">
				<strong>📍 <?php echo esc_html($t['branch_label'] ?? 'Pobočka'); ?>:</strong> 
				<?php echo esc_html($t['branch_name']); ?>
			</p>
			<?php endif; ?>
			
			<?php if (!empty($t['date']) || !empty($t['date_formatted'])): ?>
			<p style="margin: 0; font-size: 15px; color: #374151;">
				<strong>📅 <?php echo esc_html($t['date_label'] ?? 'Termín'); ?>:</strong> 
				<?php echo esc_html($t['date'] ?? $t['date_formatted'] ?? ''); ?>
			</p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php if (!empty($t['info'])): ?>
<p style="margin: 20px 0; font-size: 15px; color: #374151;">
	<?php echo esc_html($t['info']); ?>
</p>
<?php endif; ?>

<!-- Warning -->
<?php if (!empty($t['warning'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; margin: 25px 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 14px; color: #92400e; font-weight: 500;">
				⚠️ <?php echo esc_html($t['warning']); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>