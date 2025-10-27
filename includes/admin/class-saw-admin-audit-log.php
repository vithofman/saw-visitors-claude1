<?php
/**
 * Super Admin - Audit Log Viewer (Phase 5 - NEW v4.6.1)
 * 
 * Zobrazení audit logu s filtry a exportem
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Audit_Log {

	/**
	 * Items per page
	 */
	const ITEMS_PER_PAGE = 50;

	/**
	 * Display audit log page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		// Získat filtry
		$filters = self::get_filters();
		
		// Získat záznamy
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * self::ITEMS_PER_PAGE;
		
		$logs = self::get_logs( $customer_id, $filters, self::ITEMS_PER_PAGE, $offset );
		$total = self::get_logs_count( $customer_id, $filters );
		$total_pages = ceil( $total / self::ITEMS_PER_PAGE );

		// Handle CSV export
		if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
			self::export_csv( $customer_id, $filters );
			exit;
		}

		$customer = $customer_id ? self::get_customer( $customer_id ) : null;

		self::render_page( $customer, $logs, $filters, $current_page, $total_pages, $total );
	}

	/**
	 * Render main page
	 */
	private static function render_page( $customer, $logs, $filters, $current_page, $total_pages, $total ) {
		?>
		<div class="wrap">
			<h1>
				Audit Log
				<?php if ( $customer ) : ?>
					<span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
				<?php endif; ?>
			</h1>

			<!-- Filters -->
			<div class="saw-audit-filters">
				<form method="get">
					<input type="hidden" name="page" value="saw-audit-log">
					
					<div class="filter-row">
						<div class="filter-field">
							<label for="action">Akce</label>
							<select id="action" name="action">
								<option value="">Všechny akce</option>
								<option value="customer_created" <?php selected( $filters['action'], 'customer_created' ); ?>>Zákazník vytvořen</option>
								<option value="customer_updated" <?php selected( $filters['action'], 'customer_updated' ); ?>>Zákazník upraven</option>
								<option value="customer_deleted" <?php selected( $filters['action'], 'customer_deleted' ); ?>>Zákazník smazán</option>
								<option value="customer_switched" <?php selected( $filters['action'], 'customer_switched' ); ?>>Přepnutí zákazníka</option>
								<option value="content_updated" <?php selected( $filters['action'], 'content_updated' ); ?>>Obsah upraven</option>
								<option value="material_deleted" <?php selected( $filters['action'], 'material_deleted' ); ?>>Materiál smazán</option>
								<option value="document_deleted" <?php selected( $filters['action'], 'document_deleted' ); ?>>Dokument smazán</option>
								<option value="training_version_reset" <?php selected( $filters['action'], 'training_version_reset' ); ?>>Reset verze školení</option>
								<option value="user_login" <?php selected( $filters['action'], 'user_login' ); ?>>Přihlášení</option>
								<option value="user_logout" <?php selected( $filters['action'], 'user_logout' ); ?>>Odhlášení</option>
							</select>
						</div>

						<div class="filter-field">
							<label for="date_from">Datum od</label>
							<input type="date" 
								   id="date_from" 
								   name="date_from" 
								   value="<?php echo esc_attr( $filters['date_from'] ); ?>">
						</div>

						<div class="filter-field">
							<label for="date_to">Datum do</label>
							<input type="date" 
								   id="date_to" 
								   name="date_to" 
								   value="<?php echo esc_attr( $filters['date_to'] ); ?>">
						</div>

						<div class="filter-field">
							<label for="search">Hledat</label>
							<input type="text" 
								   id="search" 
								   name="search" 
								   value="<?php echo esc_attr( $filters['search'] ); ?>" 
								   placeholder="IP, admin, detaily...">
						</div>

						<div class="filter-field" style="padding-top: 20px;">
							<button type="submit" class="button">Filtrovat</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-audit-log' ) ); ?>" class="button">Reset</a>
							<a href="<?php echo esc_url( add_query_arg( array_merge( $_GET, array( 'export' => 'csv' ) ) ) ); ?>" class="button">📥 Export CSV</a>
						</div>
					</div>
				</form>
			</div>

			<!-- Results count -->
			<p class="saw-results-info">
				Zobrazeno <strong><?php echo esc_html( count( $logs ) ); ?></strong> z celkem <strong><?php echo esc_html( $total ); ?></strong> záznamů.
			</p>

			<!-- Logs table -->
			<?php if ( empty( $logs ) ) : ?>
				<div class="notice notice-info">
					<p>Nebyly nalezeny žádné záznamy odpovídající filtrům.</p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 140px;">Datum a čas</th>
							<th style="width: 150px;">Akce</th>
							<th>Detaily</th>
							<th style="width: 150px;">Admin/User</th>
							<th style="width: 100px;">IP adresa</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( 'j. n. Y H:i:s', strtotime( $log->created_at ) ) ); ?></td>
								<td>
									<span class="saw-action-badge <?php echo esc_attr( self::get_action_class( $log->action ) ); ?>">
										<?php echo esc_html( self::get_action_label( $log->action ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log->details ); ?></td>
								<td>
									<?php if ( $log->admin_user_id ) : ?>
										<strong><?php echo esc_html( get_userdata( $log->admin_user_id )->display_name ?? 'N/A' ); ?></strong><br>
										<small>Super Admin</small>
									<?php elseif ( $log->saw_user_id ) : ?>
										<?php
										global $wpdb;
										$user = $wpdb->get_row( $wpdb->prepare(
											"SELECT u.email, r.role FROM {$wpdb->prefix}saw_users u 
											LEFT JOIN {$wpdb->prefix}saw_users r ON u.id = r.id
											WHERE u.id = %d",
											$log->saw_user_id
										) );
										?>
										<strong><?php echo esc_html( $user->email ?? 'N/A' ); ?></strong><br>
										<small><?php echo esc_html( ucfirst( $user->role ?? 'N/A' ) ); ?></small>
									<?php else : ?>
										<em>Systém</em>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $log->ip_address ?? '-' ); ?></code></td>
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
			<?php endif; ?>
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
			.saw-audit-filters {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				margin: 20px 0;
			}
			.filter-row {
				display: flex;
				gap: 15px;
				flex-wrap: wrap;
				align-items: flex-end;
			}
			.filter-field {
				flex: 0 0 auto;
			}
			.filter-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 5px;
				font-size: 13px;
			}
			.filter-field input,
			.filter-field select {
				min-width: 150px;
			}
			.saw-results-info {
				background: #f0f0f1;
				padding: 10px 15px;
				border-radius: 4px;
				margin: 10px 0;
			}
			.saw-action-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.saw-action-badge.action-create {
				background: #d4edda;
				color: #155724;
			}
			.saw-action-badge.action-update {
				background: #d1ecf1;
				color: #0c5460;
			}
			.saw-action-badge.action-delete {
				background: #f8d7da;
				color: #721c24;
			}
			.saw-action-badge.action-auth {
				background: #fff3cd;
				color: #856404;
			}
			.saw-action-badge.action-other {
				background: #e2e3e5;
				color: #383d41;
			}
		</style>
		<?php
	}

	/**
	 * Get filters from request
	 */
	private static function get_filters() {
		return array(
			'action'    => isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '',
		);
	}

	/**
	 * Get logs
	 */
	private static function get_logs( $customer_id, $filters, $limit, $offset ) {
		global $wpdb;

		$where = array();
		$params = array();

		// Customer filter
		if ( $customer_id ) {
			$where[] = 'customer_id = %d';
			$params[] = $customer_id;
		}

		// Action filter
		if ( ! empty( $filters['action'] ) ) {
			$where[] = 'action = %s';
			$params[] = $filters['action'];
		}

		// Date from filter
		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'DATE(created_at) >= %s';
			$params[] = $filters['date_from'];
		}

		// Date to filter
		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'DATE(created_at) <= %s';
			$params[] = $filters['date_to'];
		}

		// Search filter
		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(details LIKE %s OR ip_address LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[] = $search_term;
			$params[] = $search_term;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$params[] = $limit;
		$params[] = $offset;

		$query = "SELECT * FROM {$wpdb->prefix}saw_audit_log $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Get logs count
	 */
	private static function get_logs_count( $customer_id, $filters ) {
		global $wpdb;

		$where = array();
		$params = array();

		if ( $customer_id ) {
			$where[] = 'customer_id = %d';
			$params[] = $customer_id;
		}

		if ( ! empty( $filters['action'] ) ) {
			$where[] = 'action = %s';
			$params[] = $filters['action'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'DATE(created_at) >= %s';
			$params[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'DATE(created_at) <= %s';
			$params[] = $filters['date_to'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(details LIKE %s OR ip_address LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[] = $search_term;
			$params[] = $search_term;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log $where_clause";

		return $wpdb->get_var( empty( $params ) ? $query : $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Export to CSV
	 */
	private static function export_csv( $customer_id, $filters ) {
		global $wpdb;

		// Získat všechny záznamy bez limitu
		$logs = self::get_logs( $customer_id, $filters, 999999, 0 );

		// Headers pro CSV download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="audit-log-' . date( 'Y-m-d-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		// BOM pro UTF-8
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		// Header řádek
		fputcsv( $output, array( 'Datum a čas', 'Akce', 'Detaily', 'Admin/User', 'IP adresa' ), ';' );

		// Data řádky
		foreach ( $logs as $log ) {
			$admin_name = '';
			if ( $log->admin_user_id ) {
				$admin_name = get_userdata( $log->admin_user_id )->display_name ?? 'N/A';
			} elseif ( $log->saw_user_id ) {
				$user = $wpdb->get_row( $wpdb->prepare(
					"SELECT email FROM {$wpdb->prefix}saw_users WHERE id = %d",
					$log->saw_user_id
				) );
				$admin_name = $user->email ?? 'N/A';
			} else {
				$admin_name = 'Systém';
			}

			fputcsv( $output, array(
				date_i18n( 'j. n. Y H:i:s', strtotime( $log->created_at ) ),
				self::get_action_label( $log->action ),
				$log->details,
				$admin_name,
				$log->ip_address ?? '-',
			), ';' );
		}

		fclose( $output );
	}

	/**
	 * Get action label
	 */
	private static function get_action_label( $action ) {
		$labels = array(
			'customer_created'        => 'Zákazník vytvořen',
			'customer_updated'        => 'Zákazník upraven',
			'customer_deleted'        => 'Zákazník smazán',
			'customer_switched'       => 'Přepnutí zákazníka',
			'content_updated'         => 'Obsah upraven',
			'material_deleted'        => 'Materiál smazán',
			'document_deleted'        => 'Dokument smazán',
			'training_version_reset'  => 'Reset verze školení',
			'user_login'              => 'Přihlášení',
			'user_logout'             => 'Odhlášení',
		);

		return $labels[ $action ] ?? $action;
	}

	/**
	 * Get action CSS class
	 */
	private static function get_action_class( $action ) {
		if ( strpos( $action, 'created' ) !== false ) {
			return 'action-create';
		}
		if ( strpos( $action, 'updated' ) !== false || strpos( $action, 'reset' ) !== false ) {
			return 'action-update';
		}
		if ( strpos( $action, 'deleted' ) !== false ) {
			return 'action-delete';
		}
		if ( strpos( $action, 'login' ) !== false || strpos( $action, 'logout' ) !== false ) {
			return 'action-auth';
		}
		return 'action-other';
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
