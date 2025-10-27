<?php
/**
 * Super Admin - Content Management (UPDATED v4.7.0)
 * 
 * Správa školících materiálů pro vybraného zákazníka
 * - Hlavní instruktážní video (YouTube/Vimeo URL)
 * - Schematický plán areálu (PDF)
 * - Informace o rizicích (WYSIWYG + Dokumenty)
 * - Další důležité informace (WYSIWYG + Dokumenty)
 * - Specifické informace oddělení (WYSIWYG per oddělení)
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Content {

	const LANGUAGES = array(
		'cs' => 'Čeština',
		'en' => 'English',
		'de' => 'Deutsch',
		'uk' => 'Українська',
	);

	const DOC_CATEGORIES = array(
		'emergency'     => 'Havarijní plány',
		'fire'          => 'Požární ochrana',
		'work_safety'   => 'Bezpečnost práce',
		'hygiene'       => 'Hygiena',
		'environment'   => 'Životní prostředí',
		'security'      => 'Bezpečnost',
		'other'         => 'Ostatní',
	);

	/**
	 * Main page
	 */
	public static function main_page() {
		// DEBUG
		error_log('=== SAW Content Management - START ===');
		error_log('POST data: ' . print_r($_POST, true));
		error_log('SESSION data: ' . print_r($_SESSION, true));
		
		wp_enqueue_style( 
			'saw-admin-content', 
			SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-admin-content.css', 
			array(), 
			SAW_VISITORS_VERSION 
		);

		wp_enqueue_script( 
			'saw-admin-content-js', 
			SAW_VISITORS_PLUGIN_URL . 'assets/js/admin-content.js', 
			array( 'jquery' ), 
			SAW_VISITORS_VERSION, 
			true 
		);

		$customer_id = self::get_selected_customer();
		error_log('Customer ID: ' . $customer_id);
		
		if ( ! $customer_id ) {
			error_log('NO CUSTOMER SELECTED!');
			self::render_no_customer_selected();
			return;
		}

		if ( isset( $_POST['saw_save_content'] ) ) {
			error_log('=== SAVING CONTENT ===');
			check_admin_referer( 'saw_save_content' );
			$result = self::handle_save( $customer_id );
			error_log('Save result: ' . print_r($result, true));

			if ( $result['success'] ) {
				wp_redirect( admin_url( 'admin.php?page=saw-content&saved=1' ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=saw-content&error=' . urlencode( $result['message'] ) ) );
			}
			exit;
		}

		if ( isset( $_GET['delete_doc'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_doc_' . $_GET['delete_doc'] ) ) {
				self::delete_document( intval( $_GET['delete_doc'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-content&deleted=1' ) );
				exit;
			}
		}

		if ( isset( $_GET['delete_material'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_material_' . $_GET['delete_material'] ) ) {
				self::delete_material( intval( $_GET['delete_material'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-content&deleted=1' ) );
				exit;
			}
		}

		if ( isset( $_GET['delete_dept_material'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_dept_material_' . $_GET['delete_dept_material'] ) ) {
				self::delete_dept_material( intval( $_GET['delete_dept_material'] ) );
				wp_redirect( admin_url( 'admin.php?page=saw-content&deleted=1' ) );
				exit;
			}
		}

		$materials = self::get_materials( $customer_id );
		$documents = self::get_documents( $customer_id );
		$departments = self::get_departments( $customer_id );
		$dept_materials = self::get_dept_materials( $customer_id );
		$customer = self::get_customer( $customer_id );

		self::render_page( $customer, $materials, $documents, $departments, $dept_materials );
	}

	/**
	 * Render main page
	 */
	private static function render_page( $customer, $materials, $documents, $departments, $dept_materials ) {
		?>
		<div class="wrap saw-content-wrap">
			<h1>
				Správa obsahu
				<span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
			</h1>

			<?php self::render_notices(); ?>

			<div class="saw-language-tabs">
				<?php foreach ( self::LANGUAGES as $lang_code => $lang_name ) : ?>
					<button type="button" 
							class="saw-language-tab <?php echo $lang_code === 'cs' ? 'active' : ''; ?>" 
							data-lang="<?php echo esc_attr( $lang_code ); ?>">
						<?php echo esc_html( $lang_name ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<form method="post" enctype="multipart/form-data" id="saw-content-form">
				<?php wp_nonce_field( 'saw_save_content' ); ?>

				<?php foreach ( self::LANGUAGES as $lang_code => $lang_name ) : ?>
					<div class="saw-language-content" 
						 data-lang="<?php echo esc_attr( $lang_code ); ?>" 
						 style="<?php echo $lang_code !== 'cs' ? 'display: none;' : ''; ?>">
						
						<div class="saw-accordion">
							
							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">🎬 Hlavní instruktážní video</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_video_section( $materials, $lang_code ); ?>
								</div>
							</div>

							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">📋 Schematický plán areálu / objektů</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_pdf_section( $materials, $lang_code ); ?>
								</div>
							</div>

							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">⚠️ Informace o rizicích a o přijatých opatřeních</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<p class="saw-section-subtitle">
										Zde zadejte písemně informace o rizicích a o přijatých opatřeních, 
										dle odst. 3, § 101, zákona č. 262/2006 Sb., Zákoníku práce v účinném znění.
									</p>
									<?php self::render_risks_section( $materials, $documents, $lang_code ); ?>
								</div>
							</div>

							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">ℹ️ Další důležité informace</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<p class="saw-section-subtitle">
										Zde zadejte jakékoliv další, textové, důležité informace, 
										které mohou být pro návštěvy vaší společnosti podstatné.
									</p>
									<?php self::render_additional_section( $materials, $documents, $lang_code ); ?>
								</div>
							</div>

							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">🏭 Specifické informace oddělení</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_department_section( $departments, $dept_materials, $lang_code ); ?>
								</div>
							</div>

						</div>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<button type="submit" name="saw_save_content" class="button button-primary button-large">
						💾 Uložit vše
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render video section (URL input)
	 */
	private static function render_video_section( $materials, $lang_code ) {
		$video = self::get_material( $materials, 'video', $lang_code );
		$video_url = $video && $video->file_url ? $video->file_url : '';
		?>
		<div class="saw-material-box">
			<label for="video_url_<?php echo esc_attr( $lang_code ); ?>" class="saw-label">
				URL adresa videa (YouTube nebo Vimeo)
			</label>
			<input type="text" 
				   id="video_url_<?php echo esc_attr( $lang_code ); ?>"
				   name="video_url_<?php echo esc_attr( $lang_code ); ?>" 
				   class="saw-text-input saw-video-url-input"
				   placeholder="https://www.youtube.com/watch?v=... nebo https://vimeo.com/..."
				   value="<?php echo esc_attr( $video_url ); ?>">
			
			<?php if ( $video_url ) : ?>
				<div class="saw-video-preview">
					<p class="material-status uploaded">
						✅ <strong>Aktuální video:</strong> 
						<a href="<?php echo esc_url( $video_url ); ?>" target="_blank">
							<?php echo esc_html( $video_url ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $video->id ), 'delete_material_' . $video->id ) ); ?>" 
						   class="button button-small button-link-delete" 
						   onclick="return confirm('Opravdu smazat toto video?');">
							🗑️ Odstranit
						</a>
					</p>
					<?php 
					$embed_html = self::get_video_embed( $video_url );
					if ( $embed_html ) : ?>
						<div class="saw-video-embed-preview">
							<?php echo $embed_html; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render PDF section
	 */
	private static function render_pdf_section( $materials, $lang_code ) {
		$pdf = self::get_material( $materials, 'pdf', $lang_code );
		?>
		<div class="saw-material-box">
			<?php if ( $pdf && $pdf->file_url ) : ?>
				<div class="material-status uploaded">
					<p>
						✅ <strong>Nahráno:</strong> 
						<a href="<?php echo esc_url( $pdf->file_url ); ?>" target="_blank">
							<?php echo esc_html( $pdf->filename ); ?>
						</a>
						<span class="material-meta">(<?php echo esc_html( self::get_file_size( $pdf->file_url ) ); ?>)</span>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $pdf->id ), 'delete_material_' . $pdf->id ) ); ?>" 
						   class="button button-small button-link-delete" 
						   onclick="return confirm('Opravdu smazat tento PDF?');">
							🗑️ Odstranit
						</a>
					</p>
				</div>
			<?php endif; ?>

			<div class="saw-upload-area">
				<div class="upload-icon">📄</div>
				<p class="upload-text">Přetáhněte PDF sem nebo klikněte pro výběr</p>
				<input type="file" 
					   name="pdf_<?php echo esc_attr( $lang_code ); ?>" 
					   accept=".pdf,application/pdf"
					   class="saw-file-input">
				<p class="upload-hint">Maximální velikost: 20 MB | Formát: PDF</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render risks section (WYSIWYG + Documents)
	 */
	private static function render_risks_section( $materials, $documents, $lang_code ) {
		$risks = self::get_material( $materials, 'risks_wysiwyg', $lang_code );
		$risks_content = $risks ? $risks->wysiwyg_content : '';
		
		$risk_docs = array_filter( $documents, function( $doc ) use ( $lang_code ) {
			return $doc->language === $lang_code && in_array( $doc->category, array( 'emergency', 'fire', 'work_safety' ) );
		});
		?>
		<div class="saw-material-box">
			<h4 class="saw-subsection-title">Textové informace o rizicích</h4>
			<?php
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

			<h4 class="saw-subsection-title saw-mt-30">Dokumenty k informacím o rizicích</h4>
			
			<?php if ( ! empty( $risk_docs ) ) : ?>
				<div class="existing-documents">
					<?php foreach ( $risk_docs as $doc ) : ?>
						<div class="doc-row">
							<span class="doc-icon">📄</span>
							<a href="<?php echo esc_url( $doc->file_url ); ?>" target="_blank" class="doc-name">
								<?php echo esc_html( $doc->filename ); ?>
							</a>
							<select name="doc_category_<?php echo esc_attr( $doc->id ); ?>" class="saw-doc-category-select">
								<?php foreach ( self::DOC_CATEGORIES as $cat_key => $cat_name ) : ?>
									<option value="<?php echo esc_attr( $cat_key ); ?>" 
											<?php selected( $doc->category, $cat_key ); ?>>
										<?php echo esc_html( $cat_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="doc-size">(<?php echo esc_html( self::get_file_size( $doc->file_url ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_doc=' . $doc->id ), 'delete_doc_' . $doc->id ) ); ?>" 
							   class="button button-small button-link-delete" 
							   onclick="return confirm('Opravdu smazat tento dokument?');">
								🗑️
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="saw-upload-area saw-doc-upload">
				<div class="upload-icon">📎</div>
				<p class="upload-text">Přidat nový dokument (PDF)</p>
				<input type="file" 
					   name="risk_doc_<?php echo esc_attr( $lang_code ); ?>[]" 
					   accept=".pdf,application/pdf"
					   class="saw-file-input"
					   multiple>
				<select name="risk_doc_category_<?php echo esc_attr( $lang_code ); ?>" class="saw-category-select">
					<option value="">-- Vyberte kategorii --</option>
					<option value="emergency">Havarijní plány</option>
					<option value="fire">Požární ochrana</option>
					<option value="work_safety">Bezpečnost práce</option>
				</select>
			</div>
		</div>
		<?php
	}

	/**
	 * Render additional info section (WYSIWYG + Documents)
	 */
	private static function render_additional_section( $materials, $documents, $lang_code ) {
		$additional = self::get_material( $materials, 'additional_wysiwyg', $lang_code );
		$additional_content = $additional ? $additional->wysiwyg_content : '';
		
		$additional_docs = array_filter( $documents, function( $doc ) use ( $lang_code ) {
			return $doc->language === $lang_code && in_array( $doc->category, array( 'hygiene', 'environment', 'security', 'other' ) );
		});
		?>
		<div class="saw-material-box">
			<h4 class="saw-subsection-title">Textové další informace</h4>
			<?php
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

			<h4 class="saw-subsection-title saw-mt-30">Dokumenty k dalším informacím</h4>
			
			<?php if ( ! empty( $additional_docs ) ) : ?>
				<div class="existing-documents">
					<?php foreach ( $additional_docs as $doc ) : ?>
						<div class="doc-row">
							<span class="doc-icon">📄</span>
							<a href="<?php echo esc_url( $doc->file_url ); ?>" target="_blank" class="doc-name">
								<?php echo esc_html( $doc->filename ); ?>
							</a>
							<select name="doc_category_<?php echo esc_attr( $doc->id ); ?>" class="saw-doc-category-select">
								<?php foreach ( self::DOC_CATEGORIES as $cat_key => $cat_name ) : ?>
									<option value="<?php echo esc_attr( $cat_key ); ?>" 
											<?php selected( $doc->category, $cat_key ); ?>>
										<?php echo esc_html( $cat_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="doc-size">(<?php echo esc_html( self::get_file_size( $doc->file_url ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_doc=' . $doc->id ), 'delete_doc_' . $doc->id ) ); ?>" 
							   class="button button-small button-link-delete" 
							   onclick="return confirm('Opravdu smazat tento dokument?');">
								🗑️
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="saw-upload-area saw-doc-upload">
				<div class="upload-icon">📎</div>
				<p class="upload-text">Přidat nový dokument (PDF)</p>
				<input type="file" 
					   name="additional_doc_<?php echo esc_attr( $lang_code ); ?>[]" 
					   accept=".pdf,application/pdf"
					   class="saw-file-input"
					   multiple>
				<select name="additional_doc_category_<?php echo esc_attr( $lang_code ); ?>" class="saw-category-select">
					<option value="">-- Vyberte kategorii --</option>
					<option value="hygiene">Hygiena</option>
					<option value="environment">Životní prostředí</option>
					<option value="security">Bezpečnost</option>
					<option value="other">Ostatní</option>
				</select>
			</div>
		</div>
		<?php
	}

	/**
	 * Render department section
	 */
	private static function render_department_section( $departments, $dept_materials, $lang_code ) {
		?>
		<div class="saw-material-box">
			<?php if ( empty( $departments ) ) : ?>
				<div class="saw-notice saw-notice-info">
					<p>
						❌ Nejsou vytvořena žádná oddělení. 
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-departments' ) ); ?>">
							Vytvořte oddělení zde
						</a>
					</p>
				</div>
			<?php else : ?>
				
				<div class="saw-department-selector">
					<label for="dept_select_<?php echo esc_attr( $lang_code ); ?>" class="saw-label">
						Vyberte oddělení pro přidání specifických informací:
					</label>
					<div class="dept-select-row">
						<select id="dept_select_<?php echo esc_attr( $lang_code ); ?>" 
								class="saw-dept-select"
								data-lang="<?php echo esc_attr( $lang_code ); ?>">
							<option value="">-- Vyberte oddělení --</option>
							<?php foreach ( $departments as $dept ) : ?>
								<option value="<?php echo esc_attr( $dept->id ); ?>">
									<?php echo esc_html( $dept->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button button-secondary saw-add-dept-btn" 
								data-lang="<?php echo esc_attr( $lang_code ); ?>">
							➕ Přidat oddělení
						</button>
					</div>
				</div>

				<div class="saw-dept-editors-container" id="dept_editors_<?php echo esc_attr( $lang_code ); ?>">
					<?php
					$lang_dept_materials = array_filter( $dept_materials, function( $dm ) use ( $lang_code ) {
						return $dm->language === $lang_code;
					});

					if ( ! empty( $lang_dept_materials ) ) :
						foreach ( $lang_dept_materials as $dm ) :
							$dept = self::get_department_by_id( $departments, $dm->department_id );
							if ( ! $dept ) continue;
							
							$wysiwyg_field = 'wysiwyg_' . $lang_code;
							$content = $dm->$wysiwyg_field ?? '';
							?>
							<div class="saw-dept-editor-block" data-dept-id="<?php echo esc_attr( $dept->id ); ?>">
								<div class="dept-header">
									<h4 class="dept-title">🏭 <?php echo esc_html( $dept->name ); ?></h4>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_dept_material=' . $dm->id ), 'delete_dept_material_' . $dm->id ) ); ?>" 
									   class="button button-small button-link-delete saw-remove-dept-btn" 
									   onclick="return confirm('Opravdu odstranit informace pro toto oddělení?');">
										🗑️ Odstranit
									</a>
								</div>
								<input type="hidden" name="dept_ids_<?php echo esc_attr( $lang_code ); ?>[]" value="<?php echo esc_attr( $dept->id ); ?>">
								<?php
								wp_editor(
									$content,
									'dept_wysiwyg_' . $lang_code . '_' . $dept->id,
									array(
										'textarea_name' => 'dept_wysiwyg_' . $lang_code . '_' . $dept->id,
										'textarea_rows' => 8,
										'media_buttons' => false,
										'teeny'         => true,
									)
								);
								?>
							</div>
						<?php endforeach;
					endif;
					?>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle save
	 */
	private static function handle_save( $customer_id ) {
		global $wpdb;

		foreach ( self::LANGUAGES as $lang_code => $lang_name ) {
			
			if ( isset( $_POST['video_url_' . $lang_code] ) ) {
				$video_url = sanitize_text_field( $_POST['video_url_' . $lang_code] );
				if ( ! empty( $video_url ) ) {
					if ( ! self::is_valid_video_url( $video_url ) ) {
						return array( 
							'success' => false, 
							'message' => 'Neplatná URL adresa videa. Podporovány jsou pouze YouTube a Vimeo.' 
						);
					}
					self::save_material( $customer_id, 'video', $lang_code, $video_url, null );
				}
			}

			if ( isset( $_FILES['pdf_' . $lang_code] ) && $_FILES['pdf_' . $lang_code]['error'] === UPLOAD_ERR_OK ) {
				$file = $_FILES['pdf_' . $lang_code];
				$upload_result = self::handle_file_upload( $file, 'materials', array( 'pdf', 'application/pdf' ) );

				if ( ! $upload_result['success'] ) {
					return $upload_result;
				}

				self::save_material( $customer_id, 'pdf', $lang_code, $upload_result['file_url'], null );
			}

			if ( isset( $_POST['risks_wysiwyg_' . $lang_code] ) ) {
				$risks_content = wp_kses_post( $_POST['risks_wysiwyg_' . $lang_code] );
				self::save_material( $customer_id, 'risks_wysiwyg', $lang_code, null, $risks_content );
			}

			if ( isset( $_POST['additional_wysiwyg_' . $lang_code] ) ) {
				$additional_content = wp_kses_post( $_POST['additional_wysiwyg_' . $lang_code] );
				self::save_material( $customer_id, 'additional_wysiwyg', $lang_code, null, $additional_content );
			}

			if ( isset( $_FILES['risk_doc_' . $lang_code] ) ) {
				$category = sanitize_text_field( $_POST['risk_doc_category_' . $lang_code] ?? 'emergency' );
				foreach ( $_FILES['risk_doc_' . $lang_code]['tmp_name'] as $key => $tmp_name ) {
					if ( $_FILES['risk_doc_' . $lang_code]['error'][$key] === UPLOAD_ERR_OK ) {
						$file = array(
							'name'     => $_FILES['risk_doc_' . $lang_code]['name'][$key],
							'type'     => $_FILES['risk_doc_' . $lang_code]['type'][$key],
							'tmp_name' => $tmp_name,
							'error'    => $_FILES['risk_doc_' . $lang_code]['error'][$key],
							'size'     => $_FILES['risk_doc_' . $lang_code]['size'][$key],
						);
						$upload_result = self::handle_file_upload( $file, 'risk-docs', array( 'pdf' ) );
						if ( $upload_result['success'] ) {
							self::save_document( $customer_id, $category, $lang_code, $upload_result['file_url'], $upload_result['filename'] );
						}
					}
				}
			}

			if ( isset( $_FILES['additional_doc_' . $lang_code] ) ) {
				$category = sanitize_text_field( $_POST['additional_doc_category_' . $lang_code] ?? 'other' );
				foreach ( $_FILES['additional_doc_' . $lang_code]['tmp_name'] as $key => $tmp_name ) {
					if ( $_FILES['additional_doc_' . $lang_code]['error'][$key] === UPLOAD_ERR_OK ) {
						$file = array(
							'name'     => $_FILES['additional_doc_' . $lang_code]['name'][$key],
							'type'     => $_FILES['additional_doc_' . $lang_code]['type'][$key],
							'tmp_name' => $tmp_name,
							'error'    => $_FILES['additional_doc_' . $lang_code]['error'][$key],
							'size'     => $_FILES['additional_doc_' . $lang_code]['size'][$key],
						);
						$upload_result = self::handle_file_upload( $file, 'risk-docs', array( 'pdf' ) );
						if ( $upload_result['success'] ) {
							self::save_document( $customer_id, $category, $lang_code, $upload_result['file_url'], $upload_result['filename'] );
						}
					}
				}
			}

			if ( isset( $_POST['dept_ids_' . $lang_code] ) ) {
				$dept_ids = array_map( 'intval', $_POST['dept_ids_' . $lang_code] );
				foreach ( $dept_ids as $dept_id ) {
					$field_name = 'dept_wysiwyg_' . $lang_code . '_' . $dept_id;
					if ( isset( $_POST[$field_name] ) ) {
						$content = wp_kses_post( $_POST[$field_name] );
						self::save_dept_material( $dept_id, $lang_code, $content );
					}
				}
			}
		}

		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'doc_category_' ) === 0 ) {
				$doc_id = intval( str_replace( 'doc_category_', '', $key ) );
				$category = sanitize_text_field( $value );
				$wpdb->update(
					$wpdb->prefix . 'saw_documents',
					array( 'category' => $category ),
					array( 'id' => $doc_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		return array( 'success' => true );
	}

	/**
	 * Validate video URL (YouTube or Vimeo)
	 */
	private static function is_valid_video_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		return ( strpos( $url, 'youtube.com' ) !== false || 
				 strpos( $url, 'youtu.be' ) !== false || 
				 strpos( $url, 'vimeo.com' ) !== false );
	}

	/**
	 * Get video embed HTML
	 */
	private static function get_video_embed( $url ) {
		if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
			preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches );
			if ( isset( $matches[1] ) ) {
				return '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr( $matches[1] ) . '" frameborder="0" allowfullscreen></iframe>';
			}
		} elseif ( strpos( $url, 'vimeo.com' ) !== false ) {
			preg_match( '/vimeo\.com\/(\d+)/', $url, $matches );
			if ( isset( $matches[1] ) ) {
				return '<iframe src="https://player.vimeo.com/video/' . esc_attr( $matches[1] ) . '" width="560" height="315" frameborder="0" allowfullscreen></iframe>';
			}
		}
		return null;
	}

	/**
	 * Handle file upload
	 */
	private static function handle_file_upload( $file, $subdirectory = 'materials', $allowed_types = array( 'pdf', 'mp4' ) ) {
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return array( 'success' => false, 'message' => 'Chyba při nahrávání souboru.' );
		}

		if ( $file['size'] > 20 * 1024 * 1024 ) {
			return array( 'success' => false, 'message' => 'Soubor je příliš velký (max 20 MB).' );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_types ) ) {
			return array( 'success' => false, 'message' => 'Nepodporovaný typ souboru.' );
		}

		$upload_dir = WP_CONTENT_DIR . '/uploads/saw-visitor-docs/' . $subdirectory;
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$filename = uniqid( 'saw_' ) . '_' . sanitize_file_name( $file['name'] );
		$file_path = $upload_dir . '/' . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return array( 'success' => false, 'message' => 'Nepodařilo se uložit soubor.' );
		}

		chmod( $file_path, 0644 );
		$file_url = content_url( 'uploads/saw-visitor-docs/' . $subdirectory . '/' . $filename );

		return array( 'success' => true, 'file_url' => $file_url, 'filename' => $filename );
	}

	/**
	 * Save material
	 */
	private static function save_material( $customer_id, $type, $language, $file_url = null, $wysiwyg_content = null, $title = '' ) {
		global $wpdb;

		error_log('=== SAVE_MATERIAL ===');
		error_log('Customer: ' . $customer_id . ', Type: ' . $type . ', Lang: ' . $language);
		error_log('File URL: ' . ($file_url ?? 'NULL'));
		error_log('WYSIWYG length: ' . (is_null($wysiwyg_content) ? 'NULL' : strlen($wysiwyg_content)));

		// Pro WYSIWYG použijeme title k rozlišení (risks_wysiwyg nebo additional_wysiwyg)
		$db_type = ( $type === 'risks_wysiwyg' || $type === 'additional_wysiwyg' ) ? 'wysiwyg' : $type;
		
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}saw_materials 
			WHERE customer_id = %d AND type = %s AND language = %s AND title = %s",
			$customer_id, $db_type, $language, $type
		) );

		error_log('Existing record: ' . ($existing ? 'ID ' . $existing->id : 'NONE'));

		$data = array(
			'customer_id' => $customer_id,
			'type'        => $db_type,
			'language'    => $language,
			'title'       => $type, // Použijeme title k uložení původního typu
			'updated_at'  => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s' );

		if ( $file_url ) {
			$data['file_url'] = $file_url;
			$data['filename'] = basename( $file_url );
			$format[] = '%s';
			$format[] = '%s';
		}

		if ( $wysiwyg_content !== null ) {
			$data['wysiwyg_content'] = $wysiwyg_content;
			$format[] = '%s';
		}

		if ( $existing ) {
			error_log('UPDATE existing record');
			$result = $wpdb->update(
				$wpdb->prefix . 'saw_materials',
				$data,
				array( 'id' => $existing->id ),
				$format,
				array( '%d' )
			);
			
			error_log('UPDATE result: ' . ($result === false ? 'FALSE - ERROR: ' . $wpdb->last_error : $result));
		} else {
			error_log('INSERT new record');
			$data['created_at'] = current_time( 'mysql' );
			$format[] = '%s';
			
			error_log('INSERT data: ' . print_r($data, true));
			
			$result = $wpdb->insert( 
				$wpdb->prefix . 'saw_materials', 
				$data,
				$format
			);
			
			error_log('INSERT result: ' . ($result === false ? 'FALSE - ERROR: ' . $wpdb->last_error : $result));
			error_log('Insert ID: ' . $wpdb->insert_id);
		}
	}

	/**
	 * Save document
	 */
	private static function save_document( $customer_id, $category, $language, $file_url, $filename ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'saw_documents',
			array(
				'customer_id' => $customer_id,
				'category'    => $category,
				'language'    => $language,
				'file_url'    => $file_url,
				'filename'    => $filename,
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Save department material
	 */
	private static function save_dept_material( $dept_id, $language, $content ) {
		global $wpdb;

		$wysiwyg_field = 'wysiwyg_' . $language;

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}saw_department_materials 
			WHERE department_id = %d",
			$dept_id
		) );

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'saw_department_materials',
				array( 
					$wysiwyg_field => $content,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'saw_department_materials',
				array(
					'department_id' => $dept_id,
					$wysiwyg_field  => $content,
					'created_at'    => current_time( 'mysql' ),
				)
			);
		}
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

		if ( ! $material ) return;

		if ( $material->file_url ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $material->file_url );
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( $wpdb->prefix . 'saw_materials', array( 'id' => $material_id ) );
	}

	/**
	 * Delete document
	 */
	private static function delete_document( $doc_id ) {
		global $wpdb;
		$doc = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_documents WHERE id = %d",
			$doc_id
		) );

		if ( ! $doc ) return;

		if ( $doc->file_url ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $doc->file_url );
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( $wpdb->prefix . 'saw_documents', array( 'id' => $doc_id ) );
	}

	/**
	 * Delete department material
	 */
	private static function delete_dept_material( $dm_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'saw_department_materials', array( 'id' => $dm_id ) );
	}

	/**
	 * Get materials
	 */
	private static function get_materials( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d ORDER BY type, language",
			$customer_id
		) );
	}

	/**
	 * Get specific material
	 */
	private static function get_material( $materials, $type, $language ) {
		foreach ( $materials as $material ) {
			// title obsahuje původní typ (video, pdf, risks_wysiwyg, additional_wysiwyg)
			if ( $material->title === $type && $material->language === $language ) {
				return $material;
			}
		}
		return null;
	}

	/**
	 * Get documents
	 */
	private static function get_documents( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_documents WHERE customer_id = %d ORDER BY category, language, filename",
			$customer_id
		) );
	}

	/**
	 * Get departments
	 */
	private static function get_departments( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
			$customer_id
		) );
	}

	/**
	 * Get department materials
	 */
	private static function get_dept_materials( $customer_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT dm.*, d.name as department_name 
			FROM {$wpdb->prefix}saw_department_materials dm
			LEFT JOIN {$wpdb->prefix}saw_departments d ON dm.department_id = d.id
			WHERE d.customer_id = %d",
			$customer_id
		) );
	}

	/**
	 * Get department by ID
	 */
	private static function get_department_by_id( $departments, $dept_id ) {
		foreach ( $departments as $dept ) {
			if ( $dept->id == $dept_id ) {
				return $dept;
			}
		}
		return null;
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
	 * Get selected customer from session
	 */
	private static function get_selected_customer() {
		// DEBUG: Try multiple sources
		error_log('SESSION saw_selected_customer_id: ' . ($_SESSION['saw_selected_customer_id'] ?? 'NOT SET'));
		error_log('GET customer_id: ' . ($_GET['customer_id'] ?? 'NOT SET'));
		
		// 1. Try SESSION
		if ( isset( $_SESSION['saw_selected_customer_id'] ) && $_SESSION['saw_selected_customer_id'] > 0 ) {
			return intval( $_SESSION['saw_selected_customer_id'] );
		}
		
		// 2. Try GET parameter
		if ( isset( $_GET['customer_id'] ) && $_GET['customer_id'] > 0 ) {
			return intval( $_GET['customer_id'] );
		}
		
		// 3. Try POST parameter
		if ( isset( $_POST['customer_id'] ) && $_POST['customer_id'] > 0 ) {
			return intval( $_POST['customer_id'] );
		}
		
		// 4. Fallback: Get first customer from database
		global $wpdb;
		$first_customer = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1" );
		
		if ( $first_customer ) {
			error_log('Using first customer from DB: ' . $first_customer);
			return intval( $first_customer );
		}
		
		error_log('NO CUSTOMER FOUND!');
		return 0;
	}

	/**
	 * Render no customer selected notice
	 */
	private static function render_no_customer_selected() {
		?>
		<div class="wrap">
			<h1>Správa obsahu</h1>
			<div class="notice notice-warning">
				<p>⚠️ <strong>Není vybrán žádný zákazník.</strong> Prosím vyberte zákazníka z horního menu.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render notices
	 */
	private static function render_notices() {
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ Obsah byl úspěšně uložen.</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ Položka byla úspěšně smazána.</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>❌ ' . esc_html( $_GET['error'] ) . '</p></div>';
		}
	}

	/**
	 * Get file size
	 */
	private static function get_file_size( $file_url ) {
		$file_path = str_replace( content_url(), WP_CONTENT_DIR, $file_url );
		if ( file_exists( $file_path ) ) {
			$size = filesize( $file_path );
			if ( $size < 1024 ) {
				return $size . ' B';
			} elseif ( $size < 1024 * 1024 ) {
				return round( $size / 1024, 2 ) . ' KB';
			} else {
				return round( $size / ( 1024 * 1024 ), 2 ) . ' MB';
			}
		}
		return 'N/A';
	}
}