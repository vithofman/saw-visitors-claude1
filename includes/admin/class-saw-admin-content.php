<?php
/**
 * Super Admin - Content Management (Phase 5 - UPDATED v4.6.1)
 * 
 * Spravuje školící materiály (video, PDF, WYSIWYG) a dokumenty pro vybraného zákazníka
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
	 * Maximální velikost souboru (20 MB)
	 */
	const MAX_FILE_SIZE = 20 * 1024 * 1024;

	/**
	 * Povolené MIME typy
	 */
	const ALLOWED_VIDEO_TYPES = array( 'video/mp4' );
	const ALLOWED_PDF_TYPES = array( 'application/pdf' );

	/**
	 * Kategorie dokumentů
	 */
	const DOC_CATEGORIES = array(
		'emergency'    => 'Mimořádné situace',
		'fire'         => 'Požární ochrana',
		'work_safety'  => 'Bezpečnost práce',
		'hygiene'      => 'Hygiena',
		'environment'  => 'Životní prostředí',
		'security'     => 'Bezpečnost',
		'other'        => 'Ostatní',
	);

	/**
	 * Jazyky
	 */
	const LANGUAGES = array(
		'cs' => 'Čeština',
		'en' => 'Angličtina',
		'de' => 'Němčina',
		'uk' => 'Ukrajinština',
	);

	/**
	 * Display main content management page
	 */
	public static function main_page() {
		$customer_id = self::get_selected_customer();
		
		if ( ! $customer_id ) {
			self::render_no_customer_selected();
			return;
		}

		// Handle form submission
		if ( isset( $_POST['saw_save_content'] ) ) {
			check_admin_referer( 'saw_save_content' );
			$result = self::save_content( $customer_id );
			
			if ( $result['success'] ) {
				wp_redirect( admin_url( 'admin.php?page=saw-content&saved=1' ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=saw-content&error=' . urlencode( $result['message'] ) ) );
			}
			exit;
		}

		// Handle document deletion
		if ( isset( $_GET['delete_doc'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_doc_' . $_GET['delete_doc'] ) ) {
				self::delete_document( intval( $_GET['delete_doc'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-content&deleted=1' ) );
				exit;
			}
		}

		// Handle material deletion
		if ( isset( $_GET['delete_material'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_material_' . $_GET['delete_material'] ) ) {
				self::delete_material( intval( $_GET['delete_material'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-content&deleted=1' ) );
				exit;
			}
		}

		$materials = self::get_materials( $customer_id );
		$documents = self::get_documents( $customer_id );
		$customer = self::get_customer( $customer_id );

		self::render_page( $customer, $materials, $documents );
	}

	/**
	 * Render main content page
	 */
	private static function render_page( $customer, $materials, $documents ) {
		?>
		<div class="wrap">
			<h1>
				Správa obsahu
				<span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
			</h1>

			<?php self::render_notices(); ?>

			<div class="saw-content-tabs">
				<button class="saw-tab-button active" data-tab="materials">Školící materiály</button>
				<button class="saw-tab-button" data-tab="documents">Dokumenty</button>
			</div>

			<form method="post" enctype="multipart/form-data" id="saw-content-form">
				<?php wp_nonce_field( 'saw_save_content' ); ?>

				<!-- Tab: Školící materiály -->
				<div id="tab-materials" class="saw-tab-content active">
					<?php self::render_materials_section( $materials ); ?>
				</div>

				<!-- Tab: Dokumenty -->
				<div id="tab-documents" class="saw-tab-content" style="display: none;">
					<?php self::render_documents_section( $documents ); ?>
				</div>

				<p class="submit">
					<button type="submit" name="saw_save_content" class="button button-primary button-large">
						💾 Uložit vše
					</button>
				</p>
			</form>
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
			.saw-content-tabs {
				margin: 20px 0;
				border-bottom: 1px solid #c3c4c7;
			}
			.saw-tab-button {
				background: transparent;
				border: none;
				border-bottom: 3px solid transparent;
				padding: 12px 24px;
				font-size: 14px;
				font-weight: 600;
				color: #646970;
				cursor: pointer;
				transition: all 0.2s;
			}
			.saw-tab-button:hover {
				color: #2271b1;
			}
			.saw-tab-button.active {
				color: #2271b1;
				border-bottom-color: #2271b1;
			}
			.saw-tab-content {
				margin-top: 20px;
			}
			.saw-language-section {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				margin-bottom: 20px;
			}
			.saw-language-section h3 {
				margin: 0 0 20px 0;
				padding: 0 0 10px 0;
				border-bottom: 2px solid #2271b1;
				color: #2271b1;
			}
			.saw-material-item {
				background: #f0f0f1;
				padding: 15px;
				border-radius: 4px;
				margin-bottom: 15px;
			}
			.saw-material-item .material-status {
				display: inline-block;
				margin-bottom: 10px;
			}
			.saw-material-item .material-status.uploaded {
				color: #00a32a;
				font-weight: 600;
			}
			.saw-material-item .material-status.empty {
				color: #646970;
			}
			.saw-document-category {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				margin-bottom: 20px;
			}
			.saw-document-list {
				margin-top: 15px;
			}
			.saw-document-item {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 10px;
				background: #f0f0f1;
				border-radius: 4px;
				margin-bottom: 10px;
			}
			.saw-document-item .doc-info {
				flex: 1;
			}
			.saw-document-item .doc-actions {
				display: flex;
				gap: 10px;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Tab switching
			$('.saw-tab-button').on('click', function() {
				const tab = $(this).data('tab');
				
				$('.saw-tab-button').removeClass('active');
				$(this).addClass('active');
				
				$('.saw-tab-content').hide();
				$('#tab-' + tab).show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render materials section (video, PDF, WYSIWYG)
	 */
	private static function render_materials_section( $materials ) {
		foreach ( self::LANGUAGES as $lang_code => $lang_name ) {
			?>
			<div class="saw-language-section">
				<h3><?php echo esc_html( $lang_name ); ?></h3>

				<!-- Video -->
				<div class="saw-material-item">
					<h4>🎥 Video (MP4)</h4>
					<?php
					$video = self::get_material( $materials, 'video', $lang_code );
					if ( $video && $video->file_url ) {
						?>
						<p class="material-status uploaded">
							✅ Nahráno: <a href="<?php echo esc_url( $video->file_url ); ?>" target="_blank"><?php echo esc_html( $video->filename ); ?></a>
							<span class="material-meta">(<?php echo esc_html( size_format( filesize( str_replace( content_url(), WP_CONTENT_DIR, $video->file_url ) ) ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $video->id ), 'delete_material_' . $video->id ) ); ?>" 
							   class="button button-small" 
							   onclick="return confirm('Opravdu smazat toto video?');">Smazat</a>
						</p>
						<?php
					} else {
						?>
						<p class="material-status empty">❌ Nenahrané</p>
						<?php
					}
					?>
					<input type="file" 
						   name="video_<?php echo esc_attr( $lang_code ); ?>" 
						   accept="video/mp4"
						   class="saw-file-input">
					<p class="description">Maximální velikost: 20 MB. Formát: MP4</p>
				</div>

				<!-- PDF Map -->
				<div class="saw-material-item">
					<h4>📄 PDF Mapa</h4>
					<?php
					$pdf = self::get_material( $materials, 'pdf', $lang_code );
					if ( $pdf && $pdf->file_url ) {
						?>
						<p class="material-status uploaded">
							✅ Nahráno: <a href="<?php echo esc_url( $pdf->file_url ); ?>" target="_blank"><?php echo esc_html( $pdf->filename ); ?></a>
							<span class="material-meta">(<?php echo esc_html( size_format( filesize( str_replace( content_url(), WP_CONTENT_DIR, $pdf->file_url ) ) ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $pdf->id ), 'delete_material_' . $pdf->id ) ); ?>" 
							   class="button button-small" 
							   onclick="return confirm('Opravdu smazat toto PDF?');">Smazat</a>
						</p>
						<?php
					} else {
						?>
						<p class="material-status empty">❌ Nenahrané</p>
						<?php
					}
					?>
					<input type="file" 
						   name="pdf_<?php echo esc_attr( $lang_code ); ?>" 
						   accept="application/pdf"
						   class="saw-file-input">
					<p class="description">Maximální velikost: 20 MB. Formát: PDF</p>
				</div>

				<!-- Risks WYSIWYG -->
				<div class="saw-material-item">
					<h4>⚠️ Rizika (WYSIWYG)</h4>
					<?php
					$risks = self::get_material( $materials, 'risks_wysiwyg', $lang_code );
					$risks_content = $risks ? $risks->wysiwyg_content : '';
					
					wp_editor(
						$risks_content,
						'risks_wysiwyg_' . $lang_code,
						array(
							'textarea_name' => 'risks_wysiwyg_' . $lang_code,
							'textarea_rows' => 10,
							'media_buttons' => true,
							'teeny'         => false,
						)
					);
					?>
				</div>

				<!-- Additional Info WYSIWYG -->
				<div class="saw-material-item">
					<h4>ℹ️ Další informace (WYSIWYG)</h4>
					<?php
					$additional = self::get_material( $materials, 'additional_wysiwyg', $lang_code );
					$additional_content = $additional ? $additional->wysiwyg_content : '';
					
					wp_editor(
						$additional_content,
						'additional_wysiwyg_' . $lang_code,
						array(
							'textarea_name' => 'additional_wysiwyg_' . $lang_code,
							'textarea_rows' => 10,
							'media_buttons' => true,
							'teeny'         => false,
						)
					);
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render documents section
	 */
	private static function render_documents_section( $documents ) {
		foreach ( self::DOC_CATEGORIES as $category_key => $category_name ) {
			$category_docs = array_filter( $documents, function( $doc ) use ( $category_key ) {
				return $doc->category === $category_key;
			});
			?>
			<div class="saw-document-category">
				<h3><?php echo esc_html( $category_name ); ?></h3>

				<?php if ( ! empty( $category_docs ) ) : ?>
					<div class="saw-document-list">
						<?php foreach ( $category_docs as $doc ) : ?>
							<div class="saw-document-item">
								<div class="doc-info">
									<strong><?php echo esc_html( $doc->filename ); ?></strong>
									<span class="doc-meta">
										<?php echo esc_html( strtoupper( $doc->language ) ); ?> |
										<?php echo esc_html( size_format( filesize( str_replace( content_url(), WP_CONTENT_DIR, $doc->file_url ) ) ) ); ?>
									</span>
								</div>
								<div class="doc-actions">
									<a href="<?php echo esc_url( $doc->file_url ); ?>" 
									   class="button button-small" 
									   target="_blank">Zobrazit</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_doc=' . $doc->id ), 'delete_doc_' . $doc->id ) ); ?>" 
									   class="button button-small" 
									   onclick="return confirm('Opravdu smazat tento dokument?');">Smazat</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="description">Zatím nejsou nahrány žádné dokumenty v této kategorii.</p>
				<?php endif; ?>

				<h4 style="margin-top: 20px;">Nahrát nové dokumenty</h4>
				<input type="file" 
					   name="doc_<?php echo esc_attr( $category_key ); ?>[]" 
					   accept="application/pdf"
					   multiple
					   class="saw-file-input">
				<p class="description">Můžete vybrat více souborů najednou. Maximální velikost: 20 MB per soubor. Formát: PDF</p>
			</div>
			<?php
		}
	}

	/**
	 * Render notices
	 */
	private static function render_notices() {
		if ( isset( $_GET['saved'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Obsah byl úspěšně uložen.</strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['deleted'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>✅ Položka byla odstraněna.</strong></p>
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
	 * Render no customer selected message
	 */
	private static function render_no_customer_selected() {
		?>
		<div class="wrap">
			<h1>Správa obsahu</h1>
			<div class="notice notice-warning">
				<p><strong>⚠️ Nejprve vyberte zákazníka</strong> z dropdownu v horní liště.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save content (materials + documents)
	 */
	private static function save_content( $customer_id ) {
		try {
			global $wpdb;

			// Uložit materiály pro každý jazyk
			foreach ( self::LANGUAGES as $lang_code => $lang_name ) {
				// Video upload
				if ( ! empty( $_FILES[ 'video_' . $lang_code ]['name'] ) ) {
					$result = self::handle_file_upload( 
						$_FILES[ 'video_' . $lang_code ], 
						self::ALLOWED_VIDEO_TYPES,
						'materials'
					);
					
					if ( $result['success'] ) {
						self::save_material( $customer_id, 'video', $lang_code, $result['file_url'] );
					} else {
						return $result;
					}
				}

				// PDF upload
				if ( ! empty( $_FILES[ 'pdf_' . $lang_code ]['name'] ) ) {
					$result = self::handle_file_upload( 
						$_FILES[ 'pdf_' . $lang_code ], 
						self::ALLOWED_PDF_TYPES,
						'materials'
					);
					
					if ( $result['success'] ) {
						self::save_material( $customer_id, 'pdf', $lang_code, $result['file_url'] );
					} else {
						return $result;
					}
				}

				// Risks WYSIWYG
				if ( isset( $_POST[ 'risks_wysiwyg_' . $lang_code ] ) ) {
					$risks_content = wp_kses_post( $_POST[ 'risks_wysiwyg_' . $lang_code ] );
					self::save_material( $customer_id, 'risks_wysiwyg', $lang_code, null, $risks_content );
				}

				// Additional WYSIWYG
				if ( isset( $_POST[ 'additional_wysiwyg_' . $lang_code ] ) ) {
					$additional_content = wp_kses_post( $_POST[ 'additional_wysiwyg_' . $lang_code ] );
					self::save_material( $customer_id, 'additional_wysiwyg', $lang_code, null, $additional_content );
				}
			}

			// Uložit dokumenty
			foreach ( self::DOC_CATEGORIES as $category_key => $category_name ) {
				if ( ! empty( $_FILES[ 'doc_' . $category_key ]['name'][0] ) ) {
					$files = $_FILES[ 'doc_' . $category_key ];
					
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

						$result = self::handle_file_upload( $file, self::ALLOWED_PDF_TYPES, 'documents' );

						if ( $result['success'] ) {
							$wpdb->insert(
								$wpdb->prefix . 'saw_documents',
								array(
									'customer_id' => $customer_id,
									'category'    => $category_key,
									'language'    => 'cs', // Default
									'filename'    => basename( $result['file_url'] ),
									'file_url'    => $result['file_url'],
									'created_at'  => current_time( 'mysql' ),
								),
								array( '%d', '%s', '%s', '%s', '%s', '%s' )
							);
						} else {
							return $result;
						}
					}
				}
			}

			// Log audit
			SAW_Audit::log( array(
				'action'      => 'content_updated',
				'customer_id' => $customer_id,
				'details'     => 'Super Admin updated content for customer',
			) );

			return array( 'success' => true );

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Chyba při ukládání: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Handle file upload
	 */
	private static function handle_file_upload( $file, $allowed_types, $subdirectory = 'materials' ) {
		// Validace
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return array(
				'success' => false,
				'message' => 'Chyba při nahrávání souboru.',
			);
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return array(
				'success' => false,
				'message' => 'Soubor je příliš velký (max. 20 MB).',
			);
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return array(
				'success' => false,
				'message' => 'Nepovolený typ souboru.',
			);
		}

		// Vytvořit upload directory
		$upload_dir = WP_CONTENT_DIR . '/uploads/saw-visitor-docs/' . $subdirectory;
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		// Generovat unikátní název
		$filename = uniqid( 'saw_' ) . '_' . sanitize_file_name( $file['name'] );
		$file_path = $upload_dir . '/' . $filename;

		// Přesunout soubor
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return array(
				'success' => false,
				'message' => 'Nepodařilo se uložit soubor.',
			);
		}

		// Nastavit oprávnění
		chmod( $file_path, 0644 );

		$file_url = content_url( 'uploads/saw-visitor-docs/' . $subdirectory . '/' . $filename );

		return array(
			'success'  => true,
			'file_url' => $file_url,
			'filename' => $filename,
		);
	}

	/**
	 * Save or update material
	 */
	private static function save_material( $customer_id, $type, $language, $file_url = null, $wysiwyg_content = null ) {
		global $wpdb;

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}saw_materials 
			WHERE customer_id = %d AND material_type = %s AND language = %s",
			$customer_id,
			$type,
			$language
		) );

		$data = array(
			'customer_id'   => $customer_id,
			'material_type' => $type,
			'language'      => $language,
			'updated_at'    => current_time( 'mysql' ),
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
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert(
				$wpdb->prefix . 'saw_materials',
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Get materials for customer
	 */
	private static function get_materials( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d ORDER BY material_type, language",
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
			"SELECT * FROM {$wpdb->prefix}saw_documents WHERE customer_id = %d ORDER BY category, language, filename",
			$customer_id
		) );
	}

	/**
	 * Delete material
	 */
	private static function delete_material( $material_id ) {
		global $wpdb;

		$material = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_materials WHERE id = %d",
			$material_id
		) );

		if ( ! $material ) {
			return;
		}

		// Smazat soubor z disku
		if ( $material->file_url ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $material->file_url );
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		// Smazat z databáze
		$wpdb->delete(
			$wpdb->prefix . 'saw_materials',
			array( 'id' => $material_id ),
			array( '%d' )
		);

		// Log audit
		SAW_Audit::log( array(
			'action'      => 'material_deleted',
			'customer_id' => $material->customer_id,
			'details'     => sprintf( 'Deleted material: %s (%s)', $material->material_type, $material->language ),
		) );
	}

	/**
	 * Delete document
	 */
	private static function delete_document( $document_id ) {
		global $wpdb;

		$document = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_documents WHERE id = %d",
			$document_id
		) );

		if ( ! $document ) {
			return;
		}

		// Smazat soubor z disku
		$file_path = str_replace( content_url(), WP_CONTENT_DIR, $document->file_url );
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		// Smazat z databáze
		$wpdb->delete(
			$wpdb->prefix . 'saw_documents',
			array( 'id' => $document_id ),
			array( '%d' )
		);

		// Log audit
		SAW_Audit::log( array(
			'action'      => 'document_deleted',
			'customer_id' => $document->customer_id,
			'details'     => sprintf( 'Deleted document: %s (%s)', $document->filename, $document->category ),
		) );
	}

	/**
	 * Get selected customer ID from session
	 */
	private static function get_selected_customer() {
		if ( ! session_id() ) {
			session_start();
		}
		return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	}

	/**
	 * Get customer data
	 */
	private static function get_customer( $customer_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );
	}
}
