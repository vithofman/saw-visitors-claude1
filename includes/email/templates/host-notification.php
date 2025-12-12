<?php
if (!defined('ABSPATH')) exit;

// Určení barvy podle typu eventu
$event_colors = array(
	'new' => array('bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af', 'icon' => '📅'),
	'updated' => array('bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e', 'icon' => '✏️'),
	'walkin' => array('bg' => '#f0fdf4', 'border' => '#22c55e', 'text' => '#166534', 'icon' => '🚶'),
	'checkin' => array('bg' => '#f0fdf4', 'border' => '#22c55e', 'text' => '#166534', 'icon' => '✅'),
);
$event = $t['event_type'] ?? 'new';
$colors = $event_colors[$event] ?? $event_colors['new'];
?>

<p style="margin: 0 0 20px 0;">
	<?php echo nl2br(esc_html($t['intro_' . $event] ?? $t['intro_new'] ?? '')); ?>
</p>

<!-- Event Banner -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: <?php echo $colors['bg']; ?>; border-left: 4px solid <?php echo $colors['border']; ?>; border-radius: 0 8px 8px 0; margin: 25px 0;">
	<tr>
		<td style="padding: 16px 20px;">
			<p style="margin: 0; font-size: 15px; color: <?php echo $colors['text']; ?>; font-weight: 600;">
				<?php echo $colors['icon']; ?> 
				<?php 
				$event_labels = array(
					'new' => 'Nová návštěva',
					'updated' => 'Návštěva aktualizována',
					'walkin' => 'Walk-in návštěva',
					'checkin' => 'Návštěvník přišel',
				);
				echo esc_html($event_labels[$event] ?? 'Návštěva');
				?>
			</p>
		</td>
	</tr>
</table>

<!-- Visit Details Card -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 25px 0; overflow: hidden;">
	<tr>
		<td style="padding: 0;">
			
			<!-- Visitors Header -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f9fafb; border-bottom: 1px solid #e5e7eb;">
				<tr>
					<td style="padding: 16px 20px;">
						<p style="margin: 0; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">
							👤 <?php echo esc_html($t['visitor_label'] ?? 'Návštěvník'); ?>
						</p>
						<p style="margin: 4px 0 0 0; font-size: 18px; font-weight: 600; color: #111827;">
							<?php echo esc_html($t['visitor_names'] ?? ''); ?>
							<?php if (($t['visitor_count'] ?? 0) > 1): ?>
							<span style="font-size: 14px; font-weight: 400; color: #6b7280;">
								(<?php echo esc_html($t['visitor_count']); ?> <?php echo ($t['visitor_count'] > 4) ? 'osob' : 'osoby'; ?>)
							</span>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>
			
			<!-- Details -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td style="padding: 20px;">
						
						<?php if (!empty($t['company_name'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
							<strong>🏢 <?php echo esc_html($t['company_label'] ?? 'Firma'); ?>:</strong> 
							<?php echo esc_html($t['company_name']); ?>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['date_formatted']) || !empty($t['date_from'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
							<strong>📅 <?php echo esc_html($t['date_label'] ?? 'Termín'); ?>:</strong> 
							<?php echo esc_html($t['date_formatted'] ?? date_i18n('d.m.Y', strtotime($t['date_from']))); ?>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['time_from'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
							<strong>🕐 <?php echo esc_html($t['time_label'] ?? 'Čas'); ?>:</strong> 
							<?php echo esc_html($t['time_from']); ?>
							<?php if (!empty($t['time_to'])): ?> - <?php echo esc_html($t['time_to']); ?><?php endif; ?>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['purpose'])): ?>
						<p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
							<strong>📝 <?php echo esc_html($t['purpose_label'] ?? 'Účel'); ?>:</strong> 
							<?php echo esc_html($t['purpose']); ?>
						</p>
						<?php endif; ?>
						
						<?php if (!empty($t['branch_name'])): ?>
						<p style="margin: 0; font-size: 14px; color: #374151;">
							<strong>📍 <?php echo esc_html($t['branch_label'] ?? 'Pobočka'); ?>:</strong> 
							<?php echo esc_html($t['branch_name']); ?>
						</p>
						<?php endif; ?>
						
					</td>
				</tr>
			</table>
			
		</td>
	</tr>
</table>

<!-- CTA Button -->
<?php if (!empty($t['detail_url'])): ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 25px 0;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td align="center" bgcolor="#1e40af" style="border-radius: 8px;">
						<a href="<?php echo esc_url($t['detail_url']); ?>" target="_blank" 
						   style="display: inline-block; padding: 14px 28px; font-size: 15px; 
								  color: #ffffff; text-decoration: none; font-weight: 600;
								  border-radius: 8px;">
							<?php echo esc_html($t['cta_text'] ?? 'Zobrazit detail'); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<p style="margin: 25px 0 0 0; font-size: 12px; color: #9ca3af; text-align: center;">
	<?php echo esc_html($t['footer'] ?? 'Tato notifikace byla vygenerována automaticky.'); ?>
</p>