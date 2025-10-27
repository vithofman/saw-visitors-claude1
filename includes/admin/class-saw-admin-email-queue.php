<?php
/**
 * Super Admin - Email Queue Monitor (Phase 5 - NEW v4.6.1)
 * 
 * Monitoring email fronty + retry failed emails
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
	 * Items per page
	 */
	const ITEMS_PER_PAGE = 50;

	/**
	 * Display email queue page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		// Handle retry
		if ( isset( $_GET['retry'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'retry_email_' . $_GET['retry'] ) ) {
				self::retry_email( intval( $_GET['retry'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-email-queue&retry_success=1' ) );
				exit;
			}
		}

		// Handle bulk retry
		if ( isset( $_POST['bulk_action'] ) && $_POST['bulk_action'] === 'retry' && isset( $_POST['email_ids'] ) ) {
			check_admin_referer( 'bulk_emails' );
			self::bulk_retry( $_POST['email_ids'] );
			wp_redirect( admin_url( 'admin.php?page=saw-email-queue&bulk_retry=1' ) );
			exit;
		}

		// Handle delete
		if ( isset( $_GET['delete'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_email_' . $_GET['delete'] ) ) {
				self::delete_email( intval( $_GET['delete'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-email-queue&deleted=1' ) );
				exit;
			}
		}

		// Get filters
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * self::ITEMS_PER_PAGE;

		$emails = self::get_emails( $customer_id, $status, self::ITEMS_PER_PAGE, $offset );
		$total = self::get_emails_count( $customer_id, $status );
		$total_pages = ceil( $total / self::ITEMS_PER_PAGE );

		$stats = self::get_stats( $customer_id );
		$customer = $customer_id ? self::get_customer( $customer_id ) : null;

		self::render_page( $customer, $emails, $stats, $status, $current_page, $total_pages, $total );
	}

	/**
	 * Render main page
	 */
	private static function render_page( $customer, $emails, $stats, $status, $current_page, $total_pages, $total ) {
		?>
		<div class="wrap">
			<h1>
				Email Queue
				<?php if ( $customer ) : ?>
					<span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
				<?php endif; ?>
			</h1>

			<?php self::render_notices(); ?>

			<!-- Stats -->
			<div class="saw-email-stats">
				<div class="saw-email-stat pending">
					<div class="stat-number"><?php echo esc_html( $stats->pending ); ?></div>
					<div class="stat-label">Čekající</div>
				</div>
				<div class="saw-email-stat sent">
					<div class="stat-number"><?php echo esc_html( $stats->sent ); ?></div>
					<div class="stat-label">Odesláno</div>
				</div>
				<div class="saw-email-stat failed">
					<div class="stat-number"><?php echo esc_html( $stats->failed ); ?></div>
					<div class="stat-label">Chyba</div>
				</div>
			</div>

			<!-- Filters -->
			<div class="subsubsub">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue' ) ); ?>" 
				   class="<?php echo $status === '' ? 'current' : ''; ?>">
					Všechny <span class="count">(<?php echo esc_html( $stats->total ); ?>)</span>
				</a> |
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=pending' ) ); ?>" 
				   class="<?php echo $status === 'pending' ? 'current' : ''; ?>">
					Čekající <span class="count">(<?php echo esc_html( $stats->pending ); ?>)</span>
				</a> |
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=sent' ) ); ?>" 
				   class="<?php echo $status === 'sent' ? 'current' : ''; ?>">
					Odesláno <span class="count">(<?php echo esc_html( $stats->sent ); ?>)</span>
				</a> |
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue&status=failed' ) ); ?>" 
				   class="<?php echo $status === 'failed' ? 'current' : ''; ?>">
					Chyba <span class="count">(<?php echo esc_html( $stats->failed ); ?>)</span>
				</a>
			</div>

			<!-- Emails table -->
			<?php if ( empty( $emails ) ) : ?>
				<div class="notice notice-info">
					<p>Žádné emaily ve frontě.</p>
				</div>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'bulk_emails' ); ?>

					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="bulk_action">
								<option value="">Hromadné akce</option>
								<option value="retry">Zkusit znovu</option>
							</select>
							<input type="submit" class="button action" value="Použít">
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="cb-select-all">
								</td>
								<th style="width: 140px;">Vytvořeno</th>
								<th style="width: 100px;">Stav</th>
								<th style="width: 200px;">Příjemce</th>
								<th>Předmět</th>
								<th style="width: 80px;">Priorita</th>
								<th style="width: 80px;">Pokusy</th>
								<th style="width: 150px;">Akce</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $emails as $email ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="email_ids[]" value="<?php echo esc_attr( $email->id ); ?>">
									</th>
									<td><?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $email->created_at ) ) ); ?></td>
									<td>
										<span class="saw-status-badge status-<?php echo esc_attr( $email->status ); ?>">
											<?php echo esc_html( self::get_status_label( $email->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $email->to_email ); ?></td>
									<td>
										<strong><?php echo esc_html( $email->subject ); ?></strong>
										<?php if ( $email->error_message ) : ?>
											<br><small class="error-message">⚠️ <?php echo esc_html( $email->error_message ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<span class="saw-priority-badge priority-<?php echo esc_attr( $email->priority ); ?>">
											<?php echo esc_html( strtoupper( $email->priority ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $email->attempts ); ?> / 3</td>
									<td>
										<a href="#" 
										   class="button button-small" 
										   onclick="showEmailBody(<?php echo esc_attr( $email->id ); ?>); return false;">
											Zobrazit
										</a>
										<?php if ( $email->status === 'failed' ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-email-queue&retry=' . $email->id ), 'retry_email_' . $email->id ) ); ?>" 
											   class="button button-small">
												🔄 Retry
											</a>
										<?php endif; ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-email-queue&delete=' . $email->id ), 'delete_email_' . $email->id ) ); ?>" 
										   class="button button-small" 
										   onclick="return confirm('Opravdu smazat tento email?');">
											Smazat
										</a>
									</td>
								</tr>
								<tr id="email-body-<?php echo esc_attr( $email->id ); ?>" style="display: none;">
									<td colspan="8">
										<div class="email-body-preview">
											<h4>Tělo emailu:</h4>
											<pre><?php echo esc_html( $email->body ); ?></pre>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<?php
								echo paginate_links( array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo; Předchozí',
									'next_text' => 'Další &raquo;',
									'total'     => $total_pages,
									'current'   => $current_page,
								) );
								?>
							</div>
						</div>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>

		<script>
		function showEmailBody(emailId) {
			var row = document.getElementById('email-body-' + emailId);
			if (row.style.display === 'none') {
				row.style.display = 'table-row';
			} else {
				row.style.display = 'none';
			}
		}

		jQuery(document).ready(function($) {
			$('#cb-select-all').on('click', function() {
				$('input[name="email_ids[]"]').prop('checked', this.checked);
			});
		});
		</script>

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
			.saw-email-stats {
				display: flex;
				gap: 20px;
				margin: 20px 0;
			}
			.saw-email-stat {
				flex: 1;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				text-align: center;
			}
			.saw-email-stat .stat-number {
				font-size: 48px;
				font-weight: 700;
				margin-bottom: 10px;
			}
			.saw-email-stat.pending .stat-number {
				color: #2271b1;
			}
			.saw-email-stat.sent .stat-number {
				color: #00a32a;
			}
			.saw-email-stat.failed .stat-number {
				color: #d63638;
			}
			.saw-email-stat .stat-label {
				font-size: 14px;
				color: #646970;
				text-transform: uppercase;
				font-weight: 600;
			}
			.saw-status-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.saw-status-badge.status-pending {
				background: #d1ecf1;
				color: #0c5460;
			}
			.saw-status-badge.status-sent {
				background: #d4edda;
				color: #155724;
			}
			.saw-status-badge.status-failed {
				background: #f8d7da;
				color: #721c24;
			}
			.saw-priority-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
			}
			.saw-priority-badge.priority-high {
				background: #f8d7da;
				color: #721c24;
			}
			.saw-priority-badge.priority-normal {
				background: #d1ecf1;
				color: #0c5460;
			}
			.saw-priority-badge.priority-low {
				background: #e2e3e5;
				color: #383d41;
			}
			.error-message {
				color: #d63638;
			}
			.email-body-preview {
				background: #f0f0f1;
				padding: 15px;
				border-radius: 4px;
			}
			.email-body-preview pre {
				background: white;
				padding: 15px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				white-space: pre-wrap;
				word-wrap: break-word;
			}
		</style>
		<?php
	}

	/**
	 * Render notices
	 */
	private static function render_notices() {
		if ( isset( $_GET['retry_success'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Email byl znovu zařazen do fronty.</strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['bulk_retry'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Vybrané emaily byly znovu zařazeny do fronty.</strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['deleted'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Email byl smazán.</strong></p>
			</div>
			<?php
		}
	}

	/**
	 * Get emails
	 */
	private static function get_emails( $customer_id, $status, $limit, $offset ) {
		global $wpdb;

		$where = array();
		$params = array();

		if ( $customer_id ) {
			$where[] = 'customer_id = %d';
			$params[] = $customer_id;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$params[] = $status;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$params[] = $limit;
		$params[] = $offset;

		$query = "SELECT * FROM {$wpdb->prefix}saw_email_queue $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Get emails count
	 */
	private static function get_emails_count( $customer_id, $status ) {
		global $wpdb;

		$where = array();
		$params = array();

		if ( $customer_id ) {
			$where[] = 'customer_id = %d';
			$params[] = $customer_id;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$params[] = $status;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue $where_clause";

		return $wpdb->get_var( empty( $params ) ? $query : $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Get statistics
	 */
	private static function get_stats( $customer_id ) {
		global $wpdb;

		$where = $customer_id ? 'WHERE customer_id = ' . intval( $customer_id ) : '';

		return (object) array(
			'total'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue $where" ),
			'pending' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue $where " . ( $where ? 'AND' : 'WHERE' ) . " status = 'pending'" ),
			'sent'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue $where " . ( $where ? 'AND' : 'WHERE' ) . " status = 'sent'" ),
			'failed'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_email_queue $where " . ( $where ? 'AND' : 'WHERE' ) . " status = 'failed'" ),
		);
	}

	/**
	 * Retry email
	 */
	private static function retry_email( $email_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'saw_email_queue',
			array(
				'status'        => 'pending',
				'attempts'      => 0,
				'error_message' => null,
				'sent_at'       => null,
			),
			array( 'id' => $email_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Bulk retry
	 */
	private static function bulk_retry( $email_ids ) {
		foreach ( $email_ids as $email_id ) {
			self::retry_email( intval( $email_id ) );
		}
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

	/**
	 * Get status label
	 */
	private static function get_status_label( $status ) {
		$labels = array(
			'pending' => 'Čekající',
			'sent'    => 'Odesláno',
			'failed'  => 'Chyba',
		);
		return $labels[ $status ] ?? $status;
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
}
