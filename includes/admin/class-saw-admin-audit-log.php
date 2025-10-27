<?php
/**
 * Super Admin - Audit Log Viewer
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
	 * Display audit log page
	 */
	public static function main_page() {
		// Filters
		$customer_id = isset( $_GET['customer_id'] ) ? intval( $_GET['customer_id'] ) : 0;
		$event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( $_GET['event_type'] ) : '';
		$severity = isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		
		$per_page = 50;
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		
		$logs = self::get_logs( $customer_id, $event_type, $severity, $date_from, $date_to, $per_page, $offset );
		$total = self::count_logs( $customer_id, $event_type, $severity, $date_from, $date_to );
		
		?>
		<div class="wrap">
			<h1>Audit Log</h1>
			
			<form method="get" style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
				<input type="hidden" name="page" value="saw-audit-log">
				
				<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
					<div>
						<label for="customer_id" style="display: block; font-weight: 600; margin-bottom: 5px;">Zákazník</label>
						<select name="customer_id" id="customer_id" style="min-width: 200px;">
							<option value="0">Všichni</option>
							<?php
							global $wpdb;
							$customers = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}saw_customers ORDER BY name" );
							foreach ( $customers as $c ) {
								printf(
									'<option value="%d"%s>%s</option>',
									$c->id,
									selected( $customer_id, $c->id, false ),
									esc_html( $c->name )
								);
							}
							?>
						</select>
					</div>
					
					<div>
						<label for="event_type" style="display: block; font-weight: 600; margin-bottom: 5px;">Typ události</label>
						<select name="event_type" id="event_type" style="min-width: 200px;">
							<option value="">Vše</option>
							<option value="login_success"<?php selected( $event_type, 'login_success' ); ?>>Přihlášení</option>
							<option value="login_failed"<?php selected( $event_type, 'login_failed' ); ?>>Neúspěšné přihlášení</option>
							<option value="training_completed"<?php selected( $event_type, 'training_completed' ); ?>>Školení dokončeno</option>
							<option value="training_skipped"<?php selected( $event_type, 'training_skipped' ); ?>>Školení přeskočeno</option>
							<option value="training_version_reset"<?php selected( $event_type, 'training_version_reset' ); ?>>Reset verze</option>
							<option value="check_in"<?php selected( $event_type, 'check_in' ); ?>>Check-in</option>
							<option value="check_out"<?php selected( $event_type, 'check_out' ); ?>>Check-out</option>
						</select>
					</div>
					
					<div>
						<label for="severity" style="display: block; font-weight: 600; margin-bottom: 5px;">Závažnost</label>
						<select name="severity" id="severity">
							<option value="">Vše</option>
							<option value="info"<?php selected( $severity, 'info' ); ?>>Info</option>
							<option value="warning"<?php selected( $severity, 'warning' ); ?>>Varování</option>
							<option value="error"<?php selected( $severity, 'error' ); ?>>Chyba</option>
							<option value="critical"<?php selected( $severity, 'critical' ); ?>>Kritické</option>
						</select>
					</div>
					
					<div>
						<label for="date_from" style="display: block; font-weight: 600; margin-bottom: 5px;">Datum od</label>
						<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">
					</div>
					
					<div>
						<label for="date_to" style="display: block; font-weight: 600; margin-bottom: 5px;">Datum do</label>
						<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">
					</div>
					
					<div>
						<button type="submit" class="button button-primary">Filtrovat</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-audit-log' ) ); ?>" class="button">Reset</a>
					</div>
				</div>
			</form>
			
			<p style="color: #666;">Celkem záznamů: <strong><?php echo intval( $total ); ?></strong></p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 150px;">Datum</th>
						<th style="width: 150px;">Zákazník</th>
						<th style="width: 150px;">Typ události</th>
						<th>Popis</th>
						<th style="width: 100px;">Závažnost</th>
						<th style="width: 120px;">IP adresa</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="6">Žádné záznamy.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $log->created_at ) ); ?></td>
								<td>
									<?php
									if ( $log->customer_name ) {
										echo esc_html( $log->customer_name );
									} else {
										echo '<em>Systémová</em>';
									}
									?>
								</td>
								<td>
									<code><?php echo esc_html( $log->event_type ); ?></code>
								</td>
								<td>
									<?php echo esc_html( $log->event_description ); ?>
									<?php if ( $log->visitor_id ) : ?>
										<br><small style="color: #666;">Návštěvník ID: <?php echo intval( $log->visitor_id ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$severity_colors = array(
										'info'     => '#2271b1',
										'warning'  => '#dba617',
										'error'    => '#d63638',
										'critical' => '#8b0000',
									);
									$color = $severity_colors[ $log->severity ] ?? '#666';
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
										<?php echo esc_html( strtoupper( $log->severity ) ); ?>
									</span>
								</td>
								<td>
									<code style="font-size: 11px;"><?php echo esc_html( $log->ip_address ?: '—' ); ?></code>
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
	 * Get logs with filters
	 */
	private static function get_logs( $customer_id, $event_type, $severity, $date_from, $date_to, $limit, $offset ) {
		global $wpdb;
		
		$where = array( '1=1' );
		
		if ( $customer_id ) {
			$where[] = $wpdb->prepare( 'al.customer_id = %d', $customer_id );
		}
		
		if ( $event_type ) {
			$where[] = $wpdb->prepare( 'al.event_type = %s', $event_type );
		}
		
		if ( $severity ) {
			$where[] = $wpdb->prepare( 'al.severity = %s', $severity );
		}
		
		if ( $date_from ) {
			$where[] = $wpdb->prepare( 'DATE(al.created_at) >= %s', $date_from );
		}
		
		if ( $date_to ) {
			$where[] = $wpdb->prepare( 'DATE(al.created_at) <= %s', $date_to );
		}
		
		$where_sql = implode( ' AND ', $where );
		
		return $wpdb->get_results( "
			SELECT 
				al.*,
				c.name as customer_name
			FROM {$wpdb->prefix}saw_audit_log al
			LEFT JOIN {$wpdb->prefix}saw_customers c ON c.id = al.customer_id
			WHERE {$where_sql}
			ORDER BY al.created_at DESC
			LIMIT {$limit} OFFSET {$offset}
		" );
	}

	/**
	 * Count logs with filters
	 */
	private static function count_logs( $customer_id, $event_type, $severity, $date_from, $date_to ) {
		global $wpdb;
		
		$where = array( '1=1' );
		
		if ( $customer_id ) {
			$where[] = $wpdb->prepare( 'customer_id = %d', $customer_id );
		}
		
		if ( $event_type ) {
			$where[] = $wpdb->prepare( 'event_type = %s', $event_type );
		}
		
		if ( $severity ) {
			$where[] = $wpdb->prepare( 'severity = %s', $severity );
		}
		
		if ( $date_from ) {
			$where[] = $wpdb->prepare( 'DATE(created_at) >= %s', $date_from );
		}
		
		if ( $date_to ) {
			$where[] = $wpdb->prepare( 'DATE(created_at) <= %s', $date_to );
		}
		
		$where_sql = implode( ' AND ', $where );
		
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log WHERE {$where_sql}" );
	}
}
