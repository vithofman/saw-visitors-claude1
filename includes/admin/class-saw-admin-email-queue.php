<?php
/**
 * Super Admin - Email Queue Monitoring
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Email_Queue {

	/**
	 * Display email queue page
	 */
	public static function main_page() {
		global $wpdb;
		
		// Handle retry action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'retry' && isset( $_GET['email_id'] ) ) {
			check_admin_referer( 'saw_retry_email_' . $_GET['email_id'] );
			self::retry_email( intval( $_GET['email_id'] ) );
			wp_redirect( admin_url( 'admin.php?page=saw-email-queue&retried=1' ) );
			exit;
		}
		
		// Handle delete action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['email_id'] ) ) {
			check_admin_referer( 'saw_delete_email_' . $_GET['email_id'] );
			self::delete_email( intval( $_GET['email_id'] ) );
			wp_redirect( admin_url( 'admin.php?page=saw-email-queue&deleted=1' ) );
			exit;
		}
		
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		
		$per_page = 50;
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		
		$emails = self::get_emails( $status, $per_page, $offset );
		$total = self::count_emails( $status );
		$stats = self::get_stats();
		
		?>
		<div class="wrap">
			<h1>Email Queue</h1>
			
			<?php if ( isset( $_GET['retried'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Email byl zařazen k opětovnému odeslání.</p>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Email byl smazán.</p>
				</div>
			<?php endif; ?>
			
			<!-- Stats -->
			<div style="display: flex; gap: 15px; margin-bottom: 20px;">
				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">
					<div style="font-size: 32px; font-weight: 600; color: #2271b1;"><?php echo intval( $stats->pending ); ?></div>
					<div style="color: #666;">Čekající</div>
				</div>
				
				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">
					<div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo intval( $stats->sent ); ?></div>
					<div style="color: #666;">Odesláno</div>
				</div>
				
				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">
					<div style="font-size: 32px; font-weight: 600; color: #d63638;"><?php echo intval( $stats->failed ); ?></div>
					<div style="color: #666;">Chyba</div>
				</div>
			</div>
			
			<!-- Filters -->
			<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
				<div style="display: flex; gap: 10px; align-items: center;">
					<strong>Filtr:</strong>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue' ) ); ?>" class="button<?php echo empty( $status ) ? ' button-primary' : ''; ?>">Vše (<?php echo intval( $total ); ?>)</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=pending' ) ); ?>" class="button<?php echo $status === 'pending' ? ' button-primary' : ''; ?>">Čekající (<?php echo intval( $stats->pending ); ?>)</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=sent' ) ); ?>" class="button<?php echo $status === 'sent' ? ' button-primary' : ''; ?>">Odesláno (<?php echo intval( $stats->sent ); ?>)</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=failed' ) ); ?>" class="button<?php echo $status === 'failed' ? ' button-primary' : ''; ?>">Chyba (<?php echo intval( $stats->failed ); ?>)</a>
				</div>
			</div>
			
			<!-- Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 150px;">Vytvořeno</th>
						<th style="width: 80px;">Status</th>
						<th style="width: 200px;">Příjemce</th>
						<th>Předmět</th>
						<th style="width: 150px;">Poslední pokus</th>
						<th style="width: 60px;">Pokusy</th>
						<th style="width: 120px;">Akce</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $emails ) ) : ?>
						<tr>
							<td colspan="7">Žádné emaily.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $emails as $email ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $email->created_at ) ); ?></td>
								<td>
									<?php
									$status_colors = array(
										'pending' => '#2271b1',
										'sent'    => '#00a32a',
										'failed'  => '#d63638',
									);
									$status_labels = array(
										'pending' => 'Čekající',
										'sent'    => 'Odesláno',
										'failed'  => 'Chyba',
									);
									$color = $status_colors[ $email->status ] ?? '#666';
									$label = $status_labels[ $email->status ] ?? $email->status;
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
										<?php echo esc_html( $label ); ?>
									</span>
								</td>
								<td>
									<code style="font-size: 11px;"><?php echo esc_html( $email->recipient_email ); ?></code>
								</td>
								<td>
									<strong><?php echo esc_html( $email->subject ); ?></strong>
									<?php if ( $email->error_message ) : ?>
										<br><small style="color: #d63638;">Chyba: <?php echo esc_html( $email->error_message ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( $email->last_attempt_at ) {
										echo esc_html( mysql2date( 'd.m.Y H:i', $email->last_attempt_at ) );
									} else {
										echo '—';
									}
									?>
								</td>
								<td><?php echo intval( $email->attempts ); ?> / 3</td>
								<td>
									<a href="#" onclick="jQuery('#email-<?php echo intval( $email->id ); ?>').toggle(); return false;" class="button button-small">Detail</a>
									
									<?php if ( $email->status === 'failed' ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-email-queue&action=retry&email_id=' . $email->id ), 'saw_retry_email_' . $email->id ) ); ?>" class="button button-small">Opakovat</a>
									<?php endif; ?>
									
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-email-queue&action=delete&email_id=' . $email->id ), 'saw_delete_email_' . $email->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('Opravdu smazat?');">Smazat</a>
								</td>
							</tr>
							<tr id="email-<?php echo intval( $email->id ); ?>" style="display: none;">
								<td colspan="7" style="background: #f9f9f9; padding: 15px;">
									<strong>Obsah emailu:</strong>
									<div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin-top: 10px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px;">
										<?php echo esc_html( $email->body ); ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php if ( $total > $per_page ) : ?>
				<div class="tablenav bottom" style="margin-top: 20px;">
					<?php
					$total_pages = ceil( $total / $per_page );
					
					echo '<div class="tablenav-pages">';
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					) );
					echo '</div>';
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get emails with filter
	 */
	private static function get_emails( $status, $limit, $offset ) {
		global $wpdb;
		
		$where = '1=1';
		
		if ( $status ) {
			$where = $wpdb->prepare( 'status = %s', $status );
		}
		
		return $wpdb->get_results( "
			SELECT * FROM {$wpdb->prefix}saw_email_queue
			WHERE {$where}
			ORDER BY created_at DESC
			LIMIT {$limit} OFFSET {$offset}
		" );
	}

	/**
	 * Count emails with filter
	 */
	private static function count_emails( $status ) {
		global $wpdb;
		
		$where = '1=1';
		
		if ( $status ) {
			$where = $wpdb->prepare( 'status = %s', $status );
		}
		
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue WHERE {$where}" );
	}

	/**
	 * Get stats
	 */
	private static function get_stats() {
		global $wpdb;
		
		return $wpdb->get_row( "
			SELECT
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
			FROM {$wpdb->prefix}saw_email_queue
		" );
	}

	/**
	 * Retry failed email
	 */
	private static function retry_email( $email_id ) {
		global $wpdb;
		
		$wpdb->update(
			$wpdb->prefix . 'saw_email_queue',
			array(
				'status'   => 'pending',
				'attempts' => 0,
			),
			array( 'id' => $email_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete email
	 */
	private static function delete_email( $email_id ) {
		global $wpdb;
		
		$wpdb->delete(
			$wpdb->prefix . 'saw_email_queue',
			array( 'id' => $email_id ),
			array( '%d' )
		);
	}
}
