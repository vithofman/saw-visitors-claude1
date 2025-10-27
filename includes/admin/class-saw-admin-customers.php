<?php
/**
 * Super Admin - Customers CRUD
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Customers {

	/**
	 * Display customers list page
	 */
	public static function list_page() {
		global $wpdb;
		
		// Handle delete action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['customer_id'] ) ) {
			check_admin_referer( 'saw_delete_customer_' . $_GET['customer_id'] );
			self::delete_customer( intval( $_GET['customer_id'] ) );
			wp_redirect( admin_url( 'admin.php?page=saw-customers&deleted=1' ) );
			exit;
		}
		
		$customers = $wpdb->get_results( "
			SELECT 
				c.*,
				COUNT(DISTINCT u.id) as user_count,
				COUNT(DISTINCT d.id) as department_count
			FROM {$wpdb->prefix}saw_customers c
			LEFT JOIN {$wpdb->prefix}saw_users u ON u.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}saw_departments d ON d.customer_id = c.id
			GROUP BY c.id
			ORDER BY c.name ASC
		" );
		
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Zákazníci</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers-new' ) ); ?>" class="page-title-action">Přidat nového</a>
			
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Zákazník byl smazán.</p>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Zákazník byl uložen.</p>
				</div>
			<?php endif; ?>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Logo</th>
						<th>Název</th>
						<th>IČO</th>
						<th>Email</th>
						<th>Uživatelé</th>
						<th>Oddělení</th>
						<th>Vytvořeno</th>
						<th>Akce</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $customers ) ) : ?>
						<tr>
							<td colspan="8">Žádní zákazníci.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $customers as $customer ) : ?>
							<tr>
								<td>
									<?php if ( $customer->logo_url ) : ?>
										<img src="<?php echo esc_url( $customer->logo_url ); ?>" alt="Logo" style="max-width: 50px; max-height: 50px;">
									<?php else : ?>
										<span class="dashicons dashicons-building" style="font-size: 40px; color: #ccc;"></span>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $customer->name ); ?></strong>
									<?php if ( $customer->primary_color ) : ?>
										<br><span class="color-badge" style="display: inline-block; width: 30px; height: 15px; background: <?php echo esc_attr( $customer->primary_color ); ?>; border: 1px solid #ddd; vertical-align: middle;"></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $customer->ico ?: '—' ); ?></td>
								<td><?php echo esc_html( $customer->email ?: '—' ); ?></td>
								<td><?php echo intval( $customer->user_count ); ?></td>
								<td><?php echo intval( $customer->department_count ); ?></td>
								<td><?php echo esc_html( mysql2date( 'd.m.Y', $customer->created_at ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers-edit&customer_id=' . $customer->id ) ); ?>" class="button button-small">Upravit</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-customers&action=delete&customer_id=' . $customer->id ), 'saw_delete_customer_' . $customer->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('Opravdu smazat zákazníka? Tato akce smaže i všechny související data!');">Smazat</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Display add/edit customer page
	 */
	public static function edit_page() {
		global $wpdb;
		
		$customer_id = isset( $_GET['customer_id'] ) ? intval( $_GET['customer_id'] ) : 0;
		$customer = null;
		$is_new = true;
		
		if ( $customer_id ) {
			$customer = $wpdb->get_row( $wpdb->prepare( 
				"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d", 
				$customer_id 
			) );
			
			if ( ! $customer ) {
				wp_die( 'Zákazník nenalezen.' );
			}
			
			$is_new = false;
		}
		
		// Handle form submission
		if ( isset( $_POST['saw_save_customer'] ) ) {
			check_admin_referer( 'saw_save_customer' );
			self::save_customer( $customer_id );
			
			if ( $is_new ) {
				$customer_id = $wpdb->insert_id;
			}
			
			wp_redirect( admin_url( 'admin.php?page=saw-customers-edit&customer_id=' . $customer_id . '&saved=1' ) );
			exit;
		}
		
		?>
		<div class="wrap">
			<h1><?php echo $is_new ? 'Přidat zákazníka' : 'Upravit zákazníka'; ?></h1>
			
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Zákazník byl uložen.</p>
				</div>
			<?php endif; ?>
			
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'saw_save_customer' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="name">Název *</label></th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $customer->name ?? '' ); ?>" required>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="ico">IČO</label></th>
						<td>
							<input type="text" name="ico" id="ico" class="regular-text" value="<?php echo esc_attr( $customer->ico ?? '' ); ?>">
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="email">Email</label></th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $customer->email ?? '' ); ?>">
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="phone">Telefon</label></th>
						<td>
							<input type="tel" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr( $customer->phone ?? '' ); ?>">
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="address">Adresa</label></th>
						<td>
							<textarea name="address" id="address" rows="3" class="large-text"><?php echo esc_textarea( $customer->address ?? '' ); ?></textarea>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="primary_color">Primární barva</label></th>
						<td>
							<input type="color" name="primary_color" id="primary_color" value="<?php echo esc_attr( $customer->primary_color ?? '#2271b1' ); ?>">
							<p class="description">Barva bude použita pro branding ve frontendu.</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="logo">Logo</label></th>
						<td>
							<?php if ( ! empty( $customer->logo_url ) ) : ?>
								<p>
									<img src="<?php echo esc_url( $customer->logo_url ); ?>" alt="Logo" style="max-width: 200px; display: block; margin-bottom: 10px;">
								</p>
							<?php endif; ?>
							<input type="file" name="logo" id="logo" accept="image/*">
							<p class="description">Podporované formáty: JPG, PNG, GIF (max 2 MB)</p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="saw_save_customer" class="button button-primary" value="<?php echo $is_new ? 'Přidat zákazníka' : 'Uložit změny'; ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers' ) ); ?>" class="button">Zrušit</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save customer
	 */
	private static function save_customer( $customer_id ) {
		global $wpdb;
		
		$data = array(
			'name'          => sanitize_text_field( $_POST['name'] ),
			'ico'           => sanitize_text_field( $_POST['ico'] ),
			'email'         => sanitize_email( $_POST['email'] ),
			'phone'         => sanitize_text_field( $_POST['phone'] ),
			'address'       => sanitize_textarea_field( $_POST['address'] ),
			'primary_color' => sanitize_hex_color( $_POST['primary_color'] ),
		);
		
		// Handle logo upload
		if ( ! empty( $_FILES['logo']['name'] ) ) {
			$logo_url = self::handle_logo_upload();
			if ( $logo_url ) {
				$data['logo_url'] = $logo_url;
			}
		}
		
		if ( $customer_id ) {
			// Update
			$wpdb->update(
				$wpdb->prefix . 'saw_customers',
				$data,
				array( 'id' => $customer_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert
			$wpdb->insert(
				$wpdb->prefix . 'saw_customers',
				$data,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			
			// Create default training config
			$customer_id = $wpdb->insert_id;
			$wpdb->insert(
				$wpdb->prefix . 'saw_training_config',
				array(
					'customer_id'      => $customer_id,
					'training_version' => 1,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Handle logo upload
	 */
	private static function handle_logo_upload() {
		if ( empty( $_FILES['logo']['name'] ) ) {
			return false;
		}
		
		$file = $_FILES['logo'];
		
		// Validate file
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		$max_size = 2 * 1024 * 1024; // 2 MB
		
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_die( 'Neplatný formát souboru.' );
		}
		
		if ( $file['size'] > $max_size ) {
			wp_die( 'Soubor je příliš velký (max 2 MB).' );
		}
		
		// Upload file
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
		
		if ( isset( $upload['error'] ) ) {
			wp_die( $upload['error'] );
		}
		
		return $upload['url'];
	}

	/**
	 * Delete customer
	 */
	private static function delete_customer( $customer_id ) {
		global $wpdb;
		
		// Delete customer (CASCADE will delete related data)
		$wpdb->delete(
			$wpdb->prefix . 'saw_customers',
			array( 'id' => $customer_id ),
			array( '%d' )
		);
		
		// Log audit
		SAW_Audit::log( array(
			'action'      => 'customer_deleted',
			'customer_id' => $customer_id,
			'details'     => 'Customer deleted from Super Admin',
		) );
	}
}
