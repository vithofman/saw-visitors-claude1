<?php
/**
 * Super Admin - Customers Management (Phase 5 - UPDATED v4.6.1)
 * 
 * CRUD operace pro zákazníky + logo upload + primary_color
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
		// Handle bulk actions
		if ( isset( $_POST['bulk_action'] ) && isset( $_POST['customer_ids'] ) ) {
			check_admin_referer( 'bulk_customers' );
			self::handle_bulk_action( $_POST['bulk_action'], $_POST['customer_ids'] );
			wp_redirect( admin_url( 'admin.php?page=saw-customers&bulk_updated=1' ) );
			exit;
		}

		// Handle delete
		if ( isset( $_GET['delete'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_customer_' . $_GET['delete'] ) ) {
				self::delete_customer( intval( $_GET['delete'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-customers&deleted=1' ) );
				exit;
			}
		}

		$customers = self::get_all_customers();
		self::render_list_page( $customers );
	}

	/**
	 * Display edit/create customer page
	 */
	public static function edit_page() {
		$customer_id = isset( $_GET['customer_id'] ) ? intval( $_GET['customer_id'] ) : 0;
		$is_edit = $customer_id > 0;

		// Handle form submission
		if ( isset( $_POST['saw_save_customer'] ) ) {
			check_admin_referer( 'saw_save_customer' );
			$result = self::save_customer( $customer_id );

			if ( $result['success'] ) {
				wp_redirect( admin_url( 'admin.php?page=saw-customers&saved=1' ) );
				exit;
			} else {
				wp_redirect( admin_url( 'admin.php?page=saw-customers-edit&customer_id=' . $customer_id . '&error=' . urlencode( $result['message'] ) ) );
				exit;
			}
		}

		$customer = $is_edit ? self::get_customer( $customer_id ) : null;
		self::render_edit_page( $customer, $is_edit );
	}

	/**
	 * Render list page
	 */
	private static function render_list_page( $customers ) {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Zákazníci</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers-new' ) ); ?>" class="page-title-action">Přidat nového</a>
			<hr class="wp-header-end">

			<?php self::render_notices(); ?>

			<?php if ( empty( $customers ) ) : ?>
				<div class="notice notice-info">
					<p>Zatím nejsou vytvořeni žádní zákazníci. <a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers-new' ) ); ?>">Přidat prvního zákazníka</a></p>
				</div>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'bulk_customers' ); ?>

					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="bulk_action">
								<option value="">Hromadné akce</option>
								<option value="delete">Smazat</option>
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
								<th class="manage-column">Logo</th>
								<th class="manage-column">Název</th>
								<th class="manage-column">IČO</th>
								<th class="manage-column">Email</th>
								<th class="manage-column">Adresa</th>
								<th class="manage-column">Barva</th>
								<th class="manage-column">Akce</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $customers as $customer ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="customer_ids[]" value="<?php echo esc_attr( $customer->id ); ?>">
									</th>
									<td>
										<?php if ( $customer->logo_url ) : ?>
											<img src="<?php echo esc_url( $customer->logo_url ); ?>" alt="Logo" style="max-width: 60px; height: auto;">
										<?php else : ?>
											<span class="dashicons dashicons-building" style="font-size: 40px; color: #c3c4c7;"></span>
										<?php endif; ?>
									</td>
									<td><strong><?php echo esc_html( $customer->name ); ?></strong></td>
									<td><?php echo esc_html( $customer->ico ?? '-' ); ?></td>
									<td><?php echo esc_html( $customer->email ?? '-' ); ?></td>
									<td><?php echo esc_html( $customer->address ?? '-' ); ?></td>
									<td>
										<div style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr( $customer->primary_color ?? '#2271b1' ); ?>; border: 1px solid #ddd; border-radius: 4px;"></div>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers-edit&customer_id=' . $customer->id ) ); ?>" class="button button-small">Upravit</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-customers&delete=' . $customer->id ), 'delete_customer_' . $customer->id ) ); ?>" 
										   class="button button-small" 
										   onclick="return confirm('Opravdu smazat tohoto zákazníka? Tato akce je nevratná!');">Smazat</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#cb-select-all').on('click', function() {
				$('input[name="customer_ids[]"]').prop('checked', this.checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Render edit/create page
	 */
	private static function render_edit_page( $customer, $is_edit ) {
		$page_title = $is_edit ? 'Upravit zákazníka' : 'Přidat zákazníka';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<?php self::render_notices(); ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'saw_save_customer' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="name">Název <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" 
								   id="name" 
								   name="name" 
								   value="<?php echo esc_attr( $customer->name ?? '' ); ?>" 
								   class="regular-text" 
								   required>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ico">IČO</label>
						</th>
						<td>
							<input type="text" 
								   id="ico" 
								   name="ico" 
								   value="<?php echo esc_attr( $customer->ico ?? '' ); ?>" 
								   class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="email">Email</label>
						</th>
						<td>
							<input type="email" 
								   id="email" 
								   name="email" 
								   value="<?php echo esc_attr( $customer->email ?? '' ); ?>" 
								   class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="phone">Telefon</label>
						</th>
						<td>
							<input type="text" 
								   id="phone" 
								   name="phone" 
								   value="<?php echo esc_attr( $customer->phone ?? '' ); ?>" 
								   class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="address">Adresa</label>
						</th>
						<td>
							<textarea id="address" 
									  name="address" 
									  rows="3" 
									  class="large-text"><?php echo esc_textarea( $customer->address ?? '' ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="primary_color">Primární barva <span class="required">*</span></label>
						</th>
						<td>
							<input type="color" 
								   id="primary_color" 
								   name="primary_color" 
								   value="<?php echo esc_attr( $customer->primary_color ?? '#2271b1' ); ?>" 
								   required>
							<p class="description">Tato barva se použije v návštěvnickém rozhraní.</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="logo">Logo</label>
						</th>
						<td>
							<?php if ( $is_edit && $customer->logo_url ) : ?>
								<div style="margin-bottom: 10px;">
									<img src="<?php echo esc_url( $customer->logo_url ); ?>" alt="Logo" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">
								</div>
							<?php endif; ?>
							<input type="file" 
								   id="logo" 
								   name="logo" 
								   accept="image/png,image/jpeg,image/jpg">
							<p class="description">Doporučená velikost: 300×100 px. Formát: PNG, JPG. Max 2 MB.</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="saw_save_customer" class="button button-primary button-large">
						<?php echo $is_edit ? '💾 Uložit změny' : '➕ Vytvořit zákazníka'; ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers' ) ); ?>" class="button button-large">Zpět</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render notices
	 */
	private static function render_notices() {
		if ( isset( $_GET['saved'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Zákazník byl úspěšně uložen.</strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['deleted'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Zákazník byl smazán.</strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['bulk_updated'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Hromadná akce byla provedena.</strong></p>
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
	 * Save customer (create or update)
	 */
	private static function save_customer( $customer_id ) {
		global $wpdb;

		try {
			// Validace
			if ( empty( $_POST['name'] ) ) {
				return array(
					'success' => false,
					'message' => 'Název zákazníka je povinný.',
				);
			}

			if ( empty( $_POST['primary_color'] ) ) {
				return array(
					'success' => false,
					'message' => 'Primární barva je povinná.',
				);
			}

			$data = array(
				'name'          => sanitize_text_field( $_POST['name'] ),
				'ico'           => sanitize_text_field( $_POST['ico'] ?? '' ),
				'email'         => sanitize_email( $_POST['email'] ?? '' ),
				'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
				'address'       => sanitize_textarea_field( $_POST['address'] ?? '' ),
				'primary_color' => sanitize_hex_color( $_POST['primary_color'] ),
				'updated_at'    => current_time( 'mysql' ),
			);

			// Logo upload
			if ( ! empty( $_FILES['logo']['name'] ) ) {
				$upload_result = self::handle_logo_upload( $_FILES['logo'] );
				if ( $upload_result['success'] ) {
					$data['logo_url'] = $upload_result['file_url'];
				} else {
					return $upload_result;
				}
			}

			// Update or Insert
			if ( $customer_id > 0 ) {
				$wpdb->update(
					$wpdb->prefix . 'saw_customers',
					$data,
					array( 'id' => $customer_id ),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);

				SAW_Audit::log( array(
					'action'      => 'customer_updated',
					'customer_id' => $customer_id,
					'details'     => 'Updated customer: ' . $data['name'],
				) );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert(
					$wpdb->prefix . 'saw_customers',
					$data,
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				$customer_id = $wpdb->insert_id;

				// Vytvořit training_config pro nového zákazníka
				$wpdb->insert(
					$wpdb->prefix . 'saw_training_config',
					array(
						'customer_id'         => $customer_id,
						'video_enabled'       => 1,
						'pdf_enabled'         => 1,
						'risks_enabled'       => 1,
						'additional_enabled'  => 1,
						'current_version'     => 1,
						'created_at'          => current_time( 'mysql' ),
						'updated_at'          => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
				);

				SAW_Audit::log( array(
					'action'      => 'customer_created',
					'customer_id' => $customer_id,
					'details'     => 'Created new customer: ' . $data['name'],
				) );
			}

			return array( 'success' => true );

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Chyba při ukládání: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Handle logo upload
	 */
	private static function handle_logo_upload( $file ) {
		// Validace
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return array(
				'success' => false,
				'message' => 'Chyba při nahrávání loga.',
			);
		}

		if ( $file['size'] > 2 * 1024 * 1024 ) { // 2 MB
			return array(
				'success' => false,
				'message' => 'Logo je příliš velké (max. 2 MB).',
			);
		}

		$allowed_types = array( 'image/png', 'image/jpeg', 'image/jpg' );
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return array(
				'success' => false,
				'message' => 'Nepovolený formát loga (povolené: PNG, JPG).',
			);
		}

		// Vytvořit upload directory
		$upload_dir = WP_CONTENT_DIR . '/uploads/saw-visitor-docs/logos';
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		// Generovat unikátní název
		$extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$filename = uniqid( 'logo_' ) . '.' . $extension;
		$file_path = $upload_dir . '/' . $filename;

		// Přesunout soubor
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return array(
				'success' => false,
				'message' => 'Nepodařilo se uložit logo.',
			);
		}

		chmod( $file_path, 0644 );

		$file_url = content_url( 'uploads/saw-visitor-docs/logos/' . $filename );

		return array(
			'success'  => true,
			'file_url' => $file_url,
		);
	}

	/**
	 * Delete customer
	 */
	private static function delete_customer( $customer_id ) {
		global $wpdb;

		// Zkontrolovat jestli má zákazník data
		$has_data = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saw_invitations WHERE customer_id = %d",
			$customer_id
		) );

		if ( $has_data > 0 ) {
			wp_die( 'Nelze smazat zákazníka s existujícími daty (pozvánky, návštěvy, atd.).', 'Chyba', array( 'back_link' => true ) );
		}

		// Smazat logo
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT logo_url FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );

		if ( $customer && $customer->logo_url ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $customer->logo_url );
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		// Smazat zákazníka
		$wpdb->delete(
			$wpdb->prefix . 'saw_customers',
			array( 'id' => $customer_id ),
			array( '%d' )
		);

		// Smazat training_config
		$wpdb->delete(
			$wpdb->prefix . 'saw_training_config',
			array( 'customer_id' => $customer_id ),
			array( '%d' )
		);

		SAW_Audit::log( array(
			'action'  => 'customer_deleted',
			'details' => 'Deleted customer ID: ' . $customer_id,
		) );
	}

	/**
	 * Handle bulk actions
	 */
	private static function handle_bulk_action( $action, $customer_ids ) {
		if ( $action === 'delete' ) {
			foreach ( $customer_ids as $customer_id ) {
				self::delete_customer( intval( $customer_id ) );
			}
		}
	}

	/**
	 * Get all customers
	 */
	private static function get_all_customers() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY name ASC" );
	}

	/**
	 * Get customer by ID
	 */
	private static function get_customer( $customer_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );
	}
}
