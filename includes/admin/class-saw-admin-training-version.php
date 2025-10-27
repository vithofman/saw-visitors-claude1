<?php
/**
 * Super Admin - Training Version Reset
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Training_Version {

	/**
	 * Display training version page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		if ( ! $customer_id ) {
			echo '<div class="wrap"><h1>Verze školení</h1><p>Nejprve vyberte zákazníka z dropdownu v horní liště.</p></div>';
			return;
		}
		
		// Handle version reset
		if ( isset( $_POST['saw_reset_version'] ) ) {
			check_admin_referer( 'saw_reset_version' );
			self::reset_version( $customer_id );
			wp_redirect( admin_url( 'admin.php?page=saw-training-version&reset=1' ) );
			exit;
		}
		
		$config = self::get_training_config( $customer_id );
		$history = self::get_version_history( $customer_id );
		
		?>
		<div class="wrap">
			<h1>Verze školení</h1>
			
			<?php if ( isset( $_GET['reset'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Verze školení byla resetována. Všichni návštěvníci budou muset absolvovat školení znovu.</p>
				</div>
			<?php endif; ?>
			
			<div class="saw-version-info" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
				<h2>Aktuální stav</h2>
				
				<p style="font-size: 18px;">
					<strong>Aktuální verze školení:</strong> 
					<span style="color: #2271b1; font-size: 24px; font-weight: bold;">v<?php echo intval( $config->training_version ?? 1 ); ?></span>
				</p>
				
				<?php if ( $config && $config->version_updated_at ) : ?>
					<p>
						<strong>Poslední aktualizace:</strong> 
						<?php echo esc_html( mysql2date( 'd.m.Y H:i', $config->version_updated_at ) ); ?>
					</p>
					
					<?php if ( $config->version_reset_reason ) : ?>
						<p>
							<strong>Důvod:</strong> 
							<?php echo esc_html( $config->version_reset_reason ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			
			<div class="saw-version-reset" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
				<h2>⚠️ Force Re-training</h2>
				
				<div class="notice notice-warning inline" style="margin: 15px 0;">
					<p><strong>Varování:</strong> Tato akce je nevratná!</p>
				</div>
				
				<p>
					Pokud jste změnili důležitý obsah školení (video, PDF, bezpečnostní pokyny), 
					můžete vynutit opakování školení pro VŠECHNY návštěvníky.
				</p>
				
				<form method="post">
					<?php wp_nonce_field( 'saw_reset_version' ); ?>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="reason">Důvod změny *</label>
							</th>
							<td>
								<textarea name="reason" id="reason" rows="4" class="large-text" required placeholder="Např: Aktualizace bezpečnostních pokynů pro nové stroje"></textarea>
								<p class="description">Tento důvod bude zobrazen v audit logu a historii verzí.</p>
							</td>
						</tr>
					</table>
					
					<div class="saw-reset-info" style="background: #f0f0f1; padding: 15px; border-left: 4px solid #d63638; margin-bottom: 20px;">
						<p><strong>Tato akce:</strong></p>
						<ul style="margin-left: 20px;">
							<li>Zvýší verzi školení na <strong>v<?php echo intval( $config->training_version ?? 1 ) + 1; ?></strong></li>
							<li><strong>Všichni návštěvníci budou muset absolvovat školení znovu</strong> (i když absolvovali do 1 roku)</li>
							<li>Odešle email notifikaci všem adminům/manažerům</li>
							<li>Zaznamená důvod do audit logu</li>
						</ul>
					</div>
					
					<p class="submit">
						<input 
							type="submit" 
							name="saw_reset_version" 
							class="button button-primary" 
							value="🔄 Resetovat verzi - vynutit opakování" 
							onclick="return confirm('Opravdu chcete resetovat verzi školení? Všichni návštěvníci budou muset absolvovat školení znovu!');"
							style="background: #d63638; border-color: #d63638;"
						>
					</p>
				</form>
			</div>
			
			<div class="saw-version-history" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
				<h2>Historie verzí</h2>
				
				<?php if ( empty( $history ) ) : ?>
					<p>Zatím žádná historie.</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Akce</th>
								<th>Datum</th>
								<th>Uživatel</th>
								<th>Detaily</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry->event_type ); ?></td>
									<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $entry->created_at ) ); ?></td>
									<td>
										<?php
										if ( $entry->user_id ) {
											$user = get_userdata( $entry->user_id );
											echo esc_html( $user ? $user->display_name : 'Neznámý' );
										} else {
											echo '—';
										}
										?>
									</td>
									<td><?php echo esc_html( $entry->event_description ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get training config
	 */
	private static function get_training_config( $customer_id ) {
		global $wpdb;
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_training_config WHERE customer_id = %d",
			$customer_id
		) );
	}

	/**
	 * Get version history from audit log
	 */
	private static function get_version_history( $customer_id ) {
		global $wpdb;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_audit_log 
			WHERE customer_id = %d 
			AND event_type IN ('training_version_reset', 'department_version_reset')
			ORDER BY created_at DESC
			LIMIT 20",
			$customer_id
		) );
	}

	/**
	 * Reset training version
	 */
	private static function reset_version( $customer_id ) {
		global $wpdb;
		
		$reason = sanitize_textarea_field( $_POST['reason'] );
		
		// Increase version
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}saw_training_config
			SET training_version = training_version + 1,
				version_updated_at = %s,
				version_reset_reason = %s
			WHERE customer_id = %d",
			current_time( 'mysql' ),
			$reason,
			$customer_id
		) );
		
		// Get new version
		$new_version = $wpdb->get_var( $wpdb->prepare(
			"SELECT training_version FROM {$wpdb->prefix}saw_training_config WHERE customer_id = %d",
			$customer_id
		) );
		
		// Log audit
		SAW_Audit::log( array(
			'action'      => 'training_version_reset',
			'customer_id' => $customer_id,
			'details'     => "Training version reset to v{$new_version}. Reason: {$reason}",
			'severity'    => 'important',
		) );
		
		// Send email notifications
		self::send_version_reset_notifications( $customer_id, $new_version, $reason );
	}

	/**
	 * Send email notifications to admins/managers
	 */
	private static function send_version_reset_notifications( $customer_id, $new_version, $reason ) {
		global $wpdb;
		
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT name FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );
		
		$users = $wpdb->get_results( $wpdb->prepare(
			"SELECT email, first_name, last_name FROM {$wpdb->prefix}saw_users 
			WHERE customer_id = %d AND role IN ('admin', 'manager')",
			$customer_id
		) );
		
		$subject = sprintf( '[SAW Visitors] Nová verze školení - %s', $customer->name );
		
		$message = sprintf(
			"Dobrý den,\n\n" .
			"byla vytvořena nová verze školení pro zákazníka: %s\n\n" .
			"Nová verze: v%d\n" .
			"Důvod: %s\n\n" .
			"Všichni návštěvníci budou muset absolvovat školení znovu.\n\n" .
			"S pozdravem,\n" .
			"SAW Visitors",
			$customer->name,
			$new_version,
			$reason
		);
		
		foreach ( $users as $user ) {
			wp_mail( $user->email, $subject, $message );
		}
	}

	/**
	 * Get selected customer from session
	 */
	private static function get_selected_customer() {
		return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	}
}
