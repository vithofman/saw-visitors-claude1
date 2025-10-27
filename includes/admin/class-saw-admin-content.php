<?php
/**
 * Super Admin - Content Management
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Content {

	/**
	 * Display content management page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		if ( ! $customer_id ) {
			echo '<div class="wrap"><h1>Správa obsahu</h1><p>Nejprve vyberte zákazníka z dropdownu v horní liště.</p></div>';
			return;
		}
		
		// Handle form submission
		if ( isset( $_POST['saw_save_content'] ) ) {
			check_admin_referer( 'saw_save_content' );
			self::save_content( $customer_id );
			wp_redirect( admin_url( 'admin.php?page=saw-content&saved=1' ) );
			exit;
		}
		
		$materials = self::get_materials( $customer_id );
		$documents = self::get_documents( $customer_id );
		
		?>
		<div class="wrap">
			<h1>Správa obsahu</h1>
			
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Obsah byl uložen.</p>
				</div>
			<?php endif; ?>
			
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'saw_save_content' ); ?>
				
				<h2 class="title">Školící materiály</h2>
				
				<?php foreach ( array( 'cs', 'en', 'de', 'uk' ) as $lang ) : ?>
					<?php $lang_names = array( 'cs' => 'Čeština', 'en' => 'Angličtina', 'de' => 'Němčina', 'uk' => 'Ukrajinština' ); ?>
					
					<h3><?php echo esc_html( $lang_names[ $lang ] ); ?></h3>
					
					<table class="form-table">
						<tr>
							<th scope="row">Video (MP4)</th>
							<td>
								<?php $video = self::get_material( $materials, 'video', $lang ); ?>
								<?php if ( $video ) : ?>
									<p>✅ Nahráno: <a href="<?php echo esc_url( $video->file_url ); ?>" target="_blank">Zobrazit</a></p>
								<?php endif; ?>
								<input type="file" name="video_<?php echo esc_attr( $lang ); ?>" accept="video/mp4">
								<p class="description">Max 20 MB</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">PDF mapa</th>
							<td>
								<?php $pdf = self::get_material( $materials, 'pdf', $lang ); ?>
								<?php if ( $pdf ) : ?>
									<p>✅ Nahráno: <a href="<?php echo esc_url( $pdf->file_url ); ?>" target="_blank">Zobrazit</a></p>
								<?php endif; ?>
								<input type="file" name="pdf_<?php echo esc_attr( $lang ); ?>" accept="application/pdf">
								<p class="description">Max 20 MB</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">Rizika (WYSIWYG)</th>
							<td>
								<?php
								$risks = self::get_material( $materials, 'risks_wysiwyg', $lang );
								wp_editor(
									$risks->wysiwyg_content ?? '',
									'risks_wysiwyg_' . $lang,
									array(
										'textarea_name' => 'risks_wysiwyg_' . $lang,
										'media_buttons' => false,
										'textarea_rows' => 10,
									)
								);
								?>
							</td>
						</tr>
						
						<tr>
							<th scope="row">Další informace (WYSIWYG)</th>
							<td>
								<?php
								$additional = self::get_material( $materials, 'additional_wysiwyg', $lang );
								wp_editor(
									$additional->wysiwyg_content ?? '',
									'additional_wysiwyg_' . $lang,
									array(
										'textarea_name' => 'additional_wysiwyg_' . $lang,
										'media_buttons' => false,
										'textarea_rows' => 10,
									)
								);
								?>
							</td>
						</tr>
					</table>
					
					<hr>
				<?php endforeach; ?>
				
				<h2 class="title">Dokumenty</h2>
				
				<p class="description">7 kategorií dokumentů pro zobrazení ve školení</p>
				
				<table class="form-table">
					<?php
					$categories = array(
						'emergency'    => 'První pomoc a evakuace',
						'fire'         => 'Protipožární bezpečnost',
						'work_safety'  => 'Bezpečnost práce',
						'hygiene'      => 'Hygiena a zdraví',
						'environment'  => 'Ochrana životního prostředí',
						'security'     => 'Zabezpečení areálu',
						'other'        => 'Ostatní',
					);
					?>
					
					<?php foreach ( $categories as $category => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<?php $docs = self::get_documents_by_category( $documents, $category ); ?>
								<?php if ( ! empty( $docs ) ) : ?>
									<ul>
										<?php foreach ( $docs as $doc ) : ?>
											<li>
												<a href="<?php echo esc_url( $doc->file_url ); ?>" target="_blank"><?php echo esc_html( $doc->filename ); ?></a>
												(<?php echo esc_html( $doc->language ); ?>)
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								
								<input type="file" name="doc_<?php echo esc_attr( $category ); ?>[]" accept=".pdf" multiple>
								<p class="description">Můžete nahrát více souborů najednou (max 20 MB každý)</p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				
				<p class="submit">
					<input type="submit" name="saw_save_content" class="button button-primary" value="Uložit změny">
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Get materials for customer
	 */
	private static function get_materials( $customer_id ) {
		global $wpdb;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d",
			$customer_id
		) );
	}

	/**
	 * Get specific material
	 */
	private static function get_material( $materials, $type, $language ) {
		foreach ( $materials as $material ) {
			if ( $material->material_type === $type && $material->language === $language ) {
				return $material;
			}
		}
		return null;
	}

	/**
	 * Get documents for customer
	 */
	private static function get_documents( $customer_id ) {
		global $wpdb;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_documents WHERE customer_id = %d ORDER BY category, language",
			$customer_id
		) );
	}

	/**
	 * Get documents by category
	 */
	private static function get_documents_by_category( $documents, $category ) {
		return array_filter( $documents, function( $doc ) use ( $category ) {
			return $doc->category === $category;
		} );
	}

	/**
	 * Save content
	 */
	private static function save_content( $customer_id ) {
		global $wpdb;
		
		// Save materials (video, PDF, WYSIWYG)
		foreach ( array( 'cs', 'en', 'de', 'uk' ) as $lang ) {
			// Video
			if ( ! empty( $_FILES[ 'video_' . $lang ]['name'] ) ) {
				$file_url = self::handle_file_upload( $_FILES[ 'video_' . $lang ], 'video/mp4', 20 * 1024 * 1024 );
				if ( $file_url ) {
					self::save_material( $customer_id, 'video', $lang, $file_url );
				}
			}
			
			// PDF
			if ( ! empty( $_FILES[ 'pdf_' . $lang ]['name'] ) ) {
				$file_url = self::handle_file_upload( $_FILES[ 'pdf_' . $lang ], 'application/pdf', 20 * 1024 * 1024 );
				if ( $file_url ) {
					self::save_material( $customer_id, 'pdf', $lang, $file_url );
				}
			}
			
			// Risks WYSIWYG
			$risks_content = wp_kses_post( $_POST[ 'risks_wysiwyg_' . $lang ] ?? '' );
			self::save_material( $customer_id, 'risks_wysiwyg', $lang, null, $risks_content );
			
			// Additional WYSIWYG
			$additional_content = wp_kses_post( $_POST[ 'additional_wysiwyg_' . $lang ] ?? '' );
			self::save_material( $customer_id, 'additional_wysiwyg', $lang, null, $additional_content );
		}
		
		// Save documents
		$categories = array( 'emergency', 'fire', 'work_safety', 'hygiene', 'environment', 'security', 'other' );
		
		foreach ( $categories as $category ) {
			if ( ! empty( $_FILES[ 'doc_' . $category ]['name'][0] ) ) {
				$files = $_FILES[ 'doc_' . $category ];
				
				for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
					if ( empty( $files['name'][ $i ] ) ) {
						continue;
					}
					
					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);
					
					$file_url = self::handle_file_upload( $file, 'application/pdf', 20 * 1024 * 1024 );
					
					if ( $file_url ) {
						$wpdb->insert(
							$wpdb->prefix . 'saw_documents',
							array(
								'customer_id' => $customer_id,
								'category'    => $category,
								'language'    => 'cs', // Default, můžete rozšířit
								'filename'    => basename( $file_url ),
								'file_url'    => $file_url,
							),
							array( '%d', '%s', '%s', '%s', '%s' )
						);
					}
				}
			}
		}
	}

	/**
	 * Save material (insert or update)
	 */
	private static function save_material( $customer_id, $type, $language, $file_url = null, $wysiwyg_content = null ) {
		global $wpdb;
		
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND material_type = %s AND language = %s",
			$customer_id,
			$type,
			$language
		) );
		
		$data = array(
			'customer_id'     => $customer_id,
			'material_type'   => $type,
			'language'        => $language,
		);
		
		if ( $file_url ) {
			$data['file_url'] = $file_url;
			$data['filename'] = basename( $file_url );
		}
		
		if ( $wysiwyg_content !== null ) {
			$data['wysiwyg_content'] = $wysiwyg_content;
		}
		
		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'saw_materials',
				$data,
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'saw_materials',
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Handle file upload
	 */
	private static function handle_file_upload( $file, $allowed_type, $max_size ) {
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return false;
		}
		
		if ( $file['type'] !== $allowed_type ) {
			wp_die( 'Neplatný formát souboru.' );
		}
		
		if ( $file['size'] > $max_size ) {
			wp_die( 'Soubor je příliš velký.' );
		}
		
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
		
		if ( isset( $upload['error'] ) ) {
			wp_die( $upload['error'] );
		}
		
		return $upload['url'];
	}

	/**
	 * Get selected customer from session
	 */
	private static function get_selected_customer() {
		return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	}
}
