<?php
if (!defined('ABSPATH')) exit;
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro'] ?? '')); ?>
</p>

<!-- Visit Details Box -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border-radius: 12px; margin: 25px 0; overflow: hidden;">
	<tr>
		<td style="padding: 0;">
			<!-- Header -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #1e40af;">
				<tr>
					<td style="padding: 15px 20px;">
						<p style="margin: 0; font-size: 14px; color: #ffffff; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
							📋 <?php echo esc_html($t['details_header'] ?? 'Detaily návštěvy'); ?>
						</p>
					</td>
				</tr>
			</table>
			
			<!-- Content -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding: 20px;">
				<tr>
					<td style="padding: 20px;">
						<?php if (!empty($t['branch_name'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 15px; color: #374151;">
							<strong style="color: #1e40af;">📍 <?php echo esc_html($t['branch_label'] ?? 'Pobočka'); ?>:</strong><br>
							<span style="margin-left: 24px;"><?php echo esc_html($t['branch_name']); ?></span>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['date_formatted'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 15px; color: #374151;">
							<strong style="color: #1e40af;">📅 <?php echo esc_html($t['date_label'] ?? 'Termín'); ?>:</strong><br>
							<span style="margin-left: 24px;"><?php echo esc_html($t['date_formatted']); ?></span>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['pin_code'])): ?>
						<p style="margin: 0; font-size: 15px; color: #374151;">
							<strong style="color: #1e40af;">🔐 <?php echo esc_html($t['pin_label'] ?? 'PIN kód'); ?>:</strong><br>
							<span style="margin-left: 24px; font-size: 24px; font-weight: 700; color: #166534; letter-spacing: 4px; font-family: 'Courier New', monospace;">
								<?php echo esc_html($t['pin_code']); ?>
							</span>
						</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php if (!empty($t['link_intro'])): ?>
<p style="margin: 25px 0 20px 0; font-size: 16px; color: #374151;">
	<?php echo esc_html($t['link_intro']); ?>
</p>
<?php endif; ?>

<!-- CTA Button -->
<?php if (!empty($t['invitation_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 25px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#1e40af" style="border-radius: 8px; box-shadow: 0 4px 6px rgba(30, 64, 175, 0.25);">
						<a href="<?php echo esc_url($t['invitation_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 16px 40px; font-size: 16px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Vyplnit registraci'); ?> →
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if (!empty($t['link_validity'])): ?>
<p style="margin: 15px 0 0 0; font-size: 13px; color: #6b7280; text-align: center;">
	<?php echo esc_html($t['link_validity']); ?>
</p>
<?php endif; ?>

<!-- PIN Warning -->
<?php if (!empty($t['pin_code']) && !empty($t['pin_warning'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; margin: 30px 0 0 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 14px; color: #92400e; font-weight: 500;">
				⚠️ <?php echo esc_html($t['pin_warning']); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>