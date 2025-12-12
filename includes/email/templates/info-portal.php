<?php
if (!defined('ABSPATH')) exit;
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- Info Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 0 8px 8px 0; margin: 25px 0;">
	<tr>
		<td style="padding: 20px;">
			<?php if (!empty($t['branch_name'])): ?>
			<p style="margin: 0 0 8px 0; font-size: 15px; color: #1e40af;">
				<strong>📍 <?php echo esc_html($t['branch_label'] ?? 'Pobočka'); ?>:</strong> 
				<?php echo esc_html($t['branch_name']); ?>
			</p>
			<?php endif; ?>
			
			<?php if (!empty($t['date_formatted'])): ?>
			<p style="margin: 0; font-size: 15px; color: #1e40af;">
				<strong>📅 <?php echo esc_html($t['date_label'] ?? 'Termín'); ?>:</strong> 
				<?php echo esc_html($t['date_formatted']); ?>
			</p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php if (!empty($t['link_intro'])): ?>
<p style="margin: 25px 0 20px 0; font-size: 16px; color: #374151;">
	<?php echo esc_html($t['link_intro']); ?>
</p>
<?php endif; ?>

<!-- CTA Button -->
<?php if (!empty($t['info_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 25px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#1e40af" style="border-radius: 8px;">
						<a href="<?php echo esc_url($t['info_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 16px 32px; font-size: 16px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Otevřít školení'); ?> →
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if (!empty($t['link_description'])): ?>
<p style="margin: 20px 0 0 0; font-size: 14px; color: #6b7280; text-align: center;">
	<?php echo esc_html($t['link_description']); ?>
</p>
<?php endif; ?>