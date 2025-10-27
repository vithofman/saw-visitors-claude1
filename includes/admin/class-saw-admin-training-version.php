<?php
/**
 * Super Admin - Training Version Management (Phase 5 - NEW v4.6.1)
 * 
 * Správa verzí školení + reset verze (force re-training)
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
	 * Display training version management page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		if ( ! $customer_id ) {
			self::render_no_customer_selected();
			return;
		}

		// Handle version reset
		if ( isset( $_POST['saw_reset_version'] ) ) {
			check_admin_referer( 'saw_reset_version' );
			$result = self::reset_training_version( $customer_id );

			if ( $result['success'] ) {
				wp_redirect( admin_url( 'admin.php?page=saw-training-version&reset_success=1' ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=saw-training-version&error=' . urlencode( $result['message'] ) ) );
			}
			exit;
		}

		$training_config = self::get_training_config( $customer_id );
		$customer = self::get_customer( $customer_id );
		$version_history = self::get_version_history( $customer_id );
		$stats = self::get_version_stats( $customer_id, $training_config->current_version );

		self::render_page( $customer, $training_config, $version_history, $stats );
	}

	/**
	 * Render main page
	 */
	private static function render_page( $customer, $training_config, $version_history, $stats ) {
		?>
		<div class="wrap">
			<h1>
				Verze školení
				<span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
			</h1>

			<?php self::render_notices(); ?>

			<!-- Current Version Info -->
			<div class="saw-version-info-box">
				<h2>📊 Aktuální verze školení</h2>
				<div class="version-display">
					<div class="version-number">v<?php echo esc_html( $training_config->current_version ); ?></div>
					<div class="version-meta">
						<p><strong>Naposledy změněno:</strong> <?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $training_config->updated_at ) ) ); ?></p>
						<?php if ( $training_config->last_reset_reason ) : ?>
							<p><strong>Důvod poslední změny:</strong> <?php echo esc_html( $training_config->last_reset_reason ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="version-stats">
					<div class="stat-box">
						<div class="stat-value"><?php echo esc_html( $stats->total_visitors ); ?></div>
						<div class="stat-label">Celkem návštěvníků</div>
					</div>
					<div class="stat-box">
						<div class="stat-value stat-success"><?php echo esc_html( $stats->current_version_visitors ); ?></div>
						<div class="stat-label">Na aktuální verzi</div>
					</div>
					<div class="stat-box">
						<div class="stat-value stat-warning"><?php echo esc_html( $stats->outdated_visitors ); ?></div>
						<div class="stat-label">Na staré verzi</div>
					</div>
				</div>
			</div>

			<!-- Reset Version Form -->
			<div class="saw-version-reset-box">
				<h2>🔄 Reset verze školení</h2>
				
				<div class="reset-warning">
					<h3>⚠️ Upozornění</h3>
					<p>Reset verze školení způsobí následující:</p>
					<ul>
						<li>Všichni návštěvníci budou muset projít školením znovu (skip training nebude fungovat)</li>
						<li>Číslo verze se zvýší o 1</li>
						<li>Návštěvníci dostanou email s informací o nové verzi školení</li>
						<li>Statistiky budou rozlišovat mezi verzemi</li>
					</ul>
					<p><strong>Používejte tuto funkci pouze při významných změnách v obsahu školení!</strong></p>
				</div>

				<form method="post" onsubmit="return confirm('Opravdu chcete resetovat verzi školení? Všichni návštěvníci budou muset projít školením znovu!');">
					<?php wp_nonce_field( 'saw_reset_version' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="reset_reason">Důvod resetu <span class="required">*</span></label>
							</th>
							<td>
								<textarea id="reset_reason" 
										  name="reset_reason" 
										  rows="4" 
										  class="large-text" 
										  required 
										  placeholder="Např: Aktualizace bezpečnostních postupů, Nové požární předpisy, atd."></textarea>
								<p class="description">Tento důvod bude zaznamenán v historii a odeslán návštěvníkům v emailu.</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" name="saw_reset_version" class="button button-primary button-large">
							🔄 Resetovat verzi (aktuálně v<?php echo esc_html( $training_config->current_version ); ?> → v<?php echo esc_html( $training_config->current_version + 1 ); ?>)
						</button>
					</p>
				</form>
			</div>

			<!-- Version History -->
			<div class="saw-version-history-box">
				<h2>📋 Historie verzí</h2>
				
				<?php if ( empty( $version_history ) ) : ?>
					<p>Zatím nebyla provedena žádná změna verze.</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Verze</th>
								<th>Datum</th>
								<th>Důvod</th>
								<th>Provedl</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $version_history as $history ) : ?>
								<tr>
									<td><strong>v<?php echo esc_html( $history->version ); ?></strong></td>
									<td><?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $history->created_at ) ) ); ?></td>
									<td><?php echo esc_html( $history->reason ); ?></td>
									<td><?php echo esc_html( $history->admin_name ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.saw-customer-badge {
				background: #2271b1;
				color: white;
				padding: 5px 15px;
				border-radius: 4px;
				font-size: 14px;
				font-weight: 600;
				margin-left: 10px;
			}
			.saw-version-info-box,
			.saw-version-reset-box,
			.saw-version-history-box {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				margin-bottom: 20px;
			}
			.saw-version-info-box h2,
			.saw-version-reset-box h2,
			.saw-version-history-box h2 {
				margin-top: 0;
				padding-bottom: 10px;
				border-bottom: 2px solid #2271b1;
			}
			.version-display {
				display: flex;
				align-items: center;
				gap: 30px;
				margin: 20px 0;
			}
			.version-number {
				font-size: 72px;
				font-weight: 700;
				color: #2271b1;
			}
			.version-meta p {
				margin: 5px 0;
			}
			.version-stats {
				display: flex;
				gap: 20px;
				margin-top: 20px;
			}
			.stat-box {
				flex: 1;
				background: #f0f0f1;
				padding: 20px;
				border-radius: 4px;
				text-align: center;
			}
			.stat-value {
				font-size: 48px;
				font-weight: 700;
				color: #2271b1;
				margin-bottom: 10px;
			}
			.stat-value.stat-success {
				color: #00a32a;
			}
			.stat-value.stat-warning {
				color: #dba617;
			}
			.stat-label {
				font-size: 14px;
				color: #646970;
				text-transform: uppercase;
				font-weight: 600;
			}
			.reset-warning {
				background: #fff3cd;
				border-left: 4px solid #dba617;
				padding: 15px;
				margin: 20px 0;
			}
			.reset-warning h3 {
				margin-top: 0;
				color: #856404;
			}
			.reset-warning ul {
				margin-left: 20px;
			}
		</style>
		<?php
	}

	/**
	 * Render notices
	 */
	private static function render_notices() {
		if ( isset( $_GET['reset_success'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Verze školení byla úspěšně resetována!</strong> Návštěvníci budou informováni emailem.</p>
			</div>
			<?php
		}

		if ( isset( $_GET['error'] ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong>❌ Chyba:</strong> <?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render no customer selected
	 */
	private static function render_no_customer_selected() {
		?>
		<div class="wrap">
			<h1>Verze školení</h1>
			<div class="notice notice-warning">
				<p><strong>⚠️ Nejprve vyberte zákazníka</strong> z dropdownu v horní liště.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Reset training version
	 */
	private static function reset_training_version( $customer_id ) {
		global $wpdb;

		try {
			// Validace
			if ( empty( $_POST['reset_reason'] ) ) {
				return array(
					'success' => false,
					'message' => 'Důvod resetu je povinný.',
				);
			}

			$reason = sanitize_textarea_field( $_POST['reset_reason'] );

			// Získat aktuální verzi
			$training_config = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}saw_training_config WHERE customer_id = %d",
				$customer_id
			) );

			if ( ! $training_config ) {
				return array(
					'success' => false,
					'message' => 'Training config nenalezen.',
				);
			}

			$old_version = $training_config->current_version;
			$new_version = $old_version + 1;

			// Aktualizovat verzi
			$wpdb->update(
				$wpdb->prefix . 'saw_training_config',
				array(
					'current_version'     => $new_version,
					'last_reset_reason'   => $reason,
					'last_reset_at'       => current_time( 'mysql' ),
					'updated_at'          => current_time( 'mysql' ),
				),
				array( 'customer_id' => $customer_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Zaznamenat do historie (audit log)
			$admin = wp_get_current_user();
			SAW_Audit::log( array(
				'action'      => 'training_version_reset',
				'customer_id' => $customer_id,
				'details'     => sprintf(
					'Version reset from v%d to v%d by %s. Reason: %s',
					$old_version,
					$new_version,
					$admin->display_name,
					$reason
				),
			) );

			// Získat všechny návštěvníky se starou verzí
			$visitors = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT v.id, v.email, v.first_name, v.last_name
				FROM {$wpdb->prefix}saw_visitors v
				WHERE v.customer_id = %d 
				AND v.training_completed = 1
				AND v.training_version < %d
				AND v.email IS NOT NULL
				AND v.email != ''",
				$customer_id,
				$new_version
			) );

			// Odeslat notifikační emaily (přes email queue)
			foreach ( $visitors as $visitor ) {
				self::queue_version_notification_email( $visitor, $customer_id, $new_version, $reason );
			}

			return array( 'success' => true );

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Chyba při resetování verze: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Queue version notification email
	 */
	private static function queue_version_notification_email( $visitor, $customer_id, $new_version, $reason ) {
		global $wpdb;

		$customer = self::get_customer( $customer_id );

		$subject = sprintf( '[%s] Nová verze školení pro návštěvníky', $customer->name );
		
		$message = sprintf(
			"Dobrý den %s,\n\n" .
			"informujeme Vás, že došlo k aktualizaci školení pro návštěvníky společnosti %s.\n\n" .
			"Nová verze: v%d\n" .
			"Důvod aktualizace: %s\n\n" .
			"Při Vaší příští návštěvě budete muset projít aktualizovaným školením.\n\n" .
			"S pozdravem,\n%s",
			$visitor->first_name . ' ' . $visitor->last_name,
			$customer->name,
			$new_version,
			$reason,
			$customer->name
		);

		$wpdb->insert(
			$wpdb->prefix . 'saw_email_queue',
			array(
				'customer_id' => $customer_id,
				'to_email'    => $visitor->email,
				'subject'     => $subject,
				'body'        => $message,
				'status'      => 'pending',
				'priority'    => 'low',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
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
	 * Get customer
	 */
	private static function get_customer( $customer_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );
	}

	/**
	 * Get version history from audit log
	 */
	private static function get_version_history( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				a.details,
				a.created_at,
				u.display_name as admin_name,
				SUBSTRING_INDEX(SUBSTRING_INDEX(a.details, 'v', -2), ' ', 1) as version,
				SUBSTRING_INDEX(SUBSTRING_INDEX(a.details, 'Reason: ', -1), '', 1) as reason
			FROM {$wpdb->prefix}saw_audit_log a
			LEFT JOIN {$wpdb->users} u ON a.admin_user_id = u.ID
			WHERE a.customer_id = %d 
			AND a.action = 'training_version_reset'
			ORDER BY a.created_at DESC
			LIMIT 20",
			$customer_id
		) );
	}

	/**
	 * Get version statistics
	 */
	private static function get_version_stats( $customer_id, $current_version ) {
		global $wpdb;

		$total_visitors = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors 
			WHERE customer_id = %d AND training_completed = 1",
			$customer_id
		) );

		$current_version_visitors = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors 
			WHERE customer_id = %d AND training_completed = 1 AND training_version = %d",
			$customer_id,
			$current_version
		) );

		$outdated_visitors = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors 
			WHERE customer_id = %d AND training_completed = 1 AND training_version < %d",
			$customer_id,
			$current_version
		) );

		return (object) array(
			'total_visitors'           => $total_visitors,
			'current_version_visitors' => $current_version_visitors,
			'outdated_visitors'        => $outdated_visitors,
		);
	}

	/**
	 * Get selected customer ID
	 */
	private static function get_selected_customer() {
		if ( ! session_id() ) {
			session_start();
		}
		return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	}
}
