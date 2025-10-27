<?php
/**
 * Super Admin - Content Management (Phase 5 - UPDATED v4.6.1)
 * 
 * Spravuje školící materiály (video, PDF, WYSIWYG + dokumenty) pro vybraného zákazníka
 * - Jazykové záložky nahoře
 * - Sbalitelné sekce (accordion)
 * - Dokumenty přímo pod každou sekcí s kategorií
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/admin
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Admin_Content {

	const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB
	const ALLOWED_VIDEO_TYPES = array( 'video/mp4' );
	const ALLOWED_PDF_TYPES = array( 'application/pdf' );

	const DOC_CATEGORIES = array(
		'emergency'    => 'Mimořádné situace',
		'fire'         => 'Požární ochrana',
		'work_safety'  => 'Bezpečnost práce',
		'hygiene'      => 'Hygiena',
		'environment'  => 'Životní prostředí',
		'security'     => 'Bezpečnost',
		'other'        => 'Ostatní',
	);

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

			<!-- Jazykové záložky -->
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
						
						<!-- Accordion sekce -->
						<div class="saw-accordion">
							
							<!-- Video sekce -->
							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">🎥 Video (MP4)</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_video_section( $materials, $lang_code ); ?>
								</div>
							</div>

							<!-- PDF Mapa sekce -->
							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">📄 PDF Mapa</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_pdf_section( $materials, $lang_code ); ?>
								</div>
							</div>

							<!-- Rizika sekce -->
							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">⚠️ Rizika (WYSIWYG + Dokumenty)</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_risks_section( $materials, $documents, $lang_code ); ?>
								</div>
							</div>

							<!-- Další informace sekce -->
							<div class="saw-accordion-item">
								<button type="button" class="saw-accordion-header">
									<span class="accordion-title">ℹ️ Další informace (WYSIWYG + Dokumenty)</span>
									<span class="accordion-icon">▼</span>
								</button>
								<div class="saw-accordion-content">
									<?php self::render_additional_section( $materials, $documents, $lang_code ); ?>
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

		<?php self::render_styles(); ?>
		<?php self::render_scripts(); ?>
		<?php
	}

	/**
	 * Render video section
	 */
	private static function render_video_section( $materials, $lang_code ) {
		$video = self::get_material( $materials, 'video', $lang_code );
		?>
		<div class="saw-material-box">
			<?php if ( $video && $video->file_url ) : ?>
				<div class="material-status uploaded">
					✅ <strong>Nahráno:</strong> <a href="<?php echo esc_url( $video->file_url ); ?>" target="_blank"><?php echo esc_html( $video->filename ); ?></a>
					<span class="material-meta">(<?php echo esc_html( self::get_file_size( $video->file_url ) ); ?>)</span>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $video->id ), 'delete_material_' . $video->id ) ); ?>" 
					   class="button button-small button-link-delete" 
					   onclick="return confirm('Opravdu smazat toto video?');">Smazat</a>
				</div>
			<?php else : ?>
				<div class="material-status empty">❌ Nenahrané</div>
			<?php endif; ?>
			
			<div class="upload-field">
				<input type="file" 
					   name="video_<?php echo esc_attr( $lang_code ); ?>" 
					   accept="video/mp4"
					   class="saw-file-input">
				<p class="description">Maximální velikost: 20 MB. Formát: MP4</p>
			</div>
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
					✅ <strong>Nahráno:</strong> <a href="<?php echo esc_url( $pdf->file_url ); ?>" target="_blank"><?php echo esc_html( $pdf->filename ); ?></a>
					<span class="material-meta">(<?php echo esc_html( self::get_file_size( $pdf->file_url ) ); ?>)</span>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_material=' . $pdf->id ), 'delete_material_' . $pdf->id ) ); ?>" 
					   class="button button-small button-link-delete" 
					   onclick="return confirm('Opravdu smazat toto PDF?');">Smazat</a>
				</div>
			<?php else : ?>
				<div class="material-status empty">❌ Nenahrané</div>
			<?php endif; ?>
			
			<div class="upload-field">
				<input type="file" 
					   name="pdf_<?php echo esc_attr( $lang_code ); ?>" 
					   accept="application/pdf"
					   class="saw-file-input">
				<p class="description">Maximální velikost: 20 MB. Formát: PDF</p>
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
		
		// Filtrovat dokumenty pro rizika a tento jazyk
		$risks_docs = array_filter( $documents, function( $doc ) use ( $lang_code ) {
			return $doc->language === $lang_code && in_array( $doc->category, array( 'emergency', 'fire', 'work_safety' ) );
		});
		?>
		<div class="saw-material-box">
			<h4>WYSIWYG Editor</h4>
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

			<h4 style="margin-top: 30px;">Dokumenty rizik</h4>
			
			<!-- Existující dokumenty -->
			<?php if ( ! empty( $risks_docs ) ) : ?>
				<div class="existing-documents">
					<?php foreach ( $risks_docs as $doc ) : ?>
						<div class="doc-row">
							<span class="doc-icon">📄</span>
							<a href="<?php echo esc_url( $doc->file_url ); ?>" target="_blank" class="doc-name">
								<?php echo esc_html( $doc->filename ); ?>
							</a>
							<span class="doc-category-badge"><?php echo esc_html( self::DOC_CATEGORIES[ $doc->category ] ); ?></span>
							<span class="doc-size">(<?php echo esc_html( self::get_file_size( $doc->file_url ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_doc=' . $doc->id ), 'delete_doc_' . $doc->id ) ); ?>" 
							   class="button button-small button-link-delete" 
							   onclick="return confirm('Opravdu smazat tento dokument?');">Smazat</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Nahrát nové dokumenty -->
			<div class="upload-documents-section">
				<p><strong>Nahrát nové dokumenty:</strong></p>
				<div id="risks-doc-uploads-<?php echo esc_attr( $lang_code ); ?>">
					<div class="doc-upload-row">
						<input type="file" 
							   name="risks_docs_<?php echo esc_attr( $lang_code ); ?>[]" 
							   accept="application/pdf"
							   class="saw-file-input">
						<select name="risks_docs_category_<?php echo esc_attr( $lang_code ); ?>[]" class="doc-category-select">
							<option value="emergency">Mimořádné situace</option>
							<option value="fire">Požární ochrana</option>
							<option value="work_safety">Bezpečnost práce</option>
						</select>
						<button type="button" class="button button-small add-doc-row" data-section="risks" data-lang="<?php echo esc_attr( $lang_code ); ?>">+ Přidat další</button>
					</div>
				</div>
				<p class="description">Maximální velikost: 20 MB per soubor. Formát: PDF</p>
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
		
		// Filtrovat dokumenty pro další informace a tento jazyk
		$additional_docs = array_filter( $documents, function( $doc ) use ( $lang_code ) {
			return $doc->language === $lang_code && in_array( $doc->category, array( 'hygiene', 'environment', 'security', 'other' ) );
		});
		?>
		<div class="saw-material-box">
			<h4>WYSIWYG Editor</h4>
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

			<h4 style="margin-top: 30px;">Dokumenty k dalším informacím</h4>
			
			<!-- Existující dokumenty -->
			<?php if ( ! empty( $additional_docs ) ) : ?>
				<div class="existing-documents">
					<?php foreach ( $additional_docs as $doc ) : ?>
						<div class="doc-row">
							<span class="doc-icon">📄</span>
							<a href="<?php echo esc_url( $doc->file_url ); ?>" target="_blank" class="doc-name">
								<?php echo esc_html( $doc->filename ); ?>
							</a>
							<span class="doc-category-badge"><?php echo esc_html( self::DOC_CATEGORIES[ $doc->category ] ); ?></span>
							<span class="doc-size">(<?php echo esc_html( self::get_file_size( $doc->file_url ) ); ?>)</span>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=saw-content&delete_doc=' . $doc->id ), 'delete_doc_' . $doc->id ) ); ?>" 
							   class="button button-small button-link-delete" 
							   onclick="return confirm('Opravdu smazat tento dokument?');">Smazat</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Nahrát nové dokumenty -->
			<div class="upload-documents-section">
				<p><strong>Nahrát nové dokumenty:</strong></p>
				<div id="additional-doc-uploads-<?php echo esc_attr( $lang_code ); ?>">
					<div class="doc-upload-row">
						<input type="file" 
							   name="additional_docs_<?php echo esc_attr( $lang_code ); ?>[]" 
							   accept="application/pdf"
							   class="saw-file-input">
						<select name="additional_docs_category_<?php echo esc_attr( $lang_code ); ?>[]" class="doc-category-select">
							<option value="hygiene">Hygiena</option>
							<option value="environment">Životní prostředí</option>
							<option value="security">Bezpečnost</option>
							<option value="other">Ostatní</option>
						</select>
						<button type="button" class="button button-small add-doc-row" data-section="additional" data-lang="<?php echo esc_attr( $lang_code ); ?>">+ Přidat další</button>
					</div>
				</div>
				<p class="description">Maximální velikost: 20 MB per soubor. Formát: PDF</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render styles
	 */
	private static function render_styles() {
		?>
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
			
			/* Jazykové záložky */
			.saw-language-tabs {
				display: flex;
				gap: 0;
				margin: 20px 0 0 0;
				border-bottom: 2px solid #c3c4c7;
			}
			.saw-language-tab {
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				border-bottom: none;
				padding: 12px 30px;
				font-size: 15px;
				font-weight: 600;
				color: #646970;
				cursor: pointer;
				transition: all 0.2s;
				border-radius: 4px 4px 0 0;
				margin-right: -1px;
			}
			.saw-language-tab:hover {
				background: #fff;
				color: #2271b1;
			}
			.saw-language-tab.active {
				background: #fff;
				color: #2271b1;
				border-bottom: 2px solid #fff;
				position: relative;
				bottom: -2px;
			}
			
			/* Language content */
			.saw-language-content {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-top: none;
				padding: 20px;
			}
			
			/* Accordion */
			.saw-accordion {
				margin-top: 20px;
			}
			.saw-accordion-item {
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				margin-bottom: 10px;
			}
			.saw-accordion-header {
				width: 100%;
				background: #f0f0f1;
				border: none;
				padding: 15px 20px;
				text-align: left;
				cursor: pointer;
				display: flex;
				justify-content: space-between;
				align-items: center;
				font-size: 15px;
				font-weight: 600;
				transition: background 0.2s;
			}
			.saw-accordion-header:hover {
				background: #e8e8e8;
			}
			.saw-accordion-header .accordion-icon {
				transition: transform 0.3s;
			}
			.saw-accordion-item.active .saw-accordion-header .accordion-icon {
				transform: rotate(-180deg);
			}
			.saw-accordion-content {
				display: none;
				padding: 20px;
				border-top: 1px solid #c3c4c7;
			}
			.saw-accordion-item.active .saw-accordion-content {
				display: block;
			}
			
			/* Material box */
			.saw-material-box {
				background: #fafafa;
				padding: 20px;
				border-radius: 4px;
			}
			.material-status {
				padding: 10px;
				border-radius: 4px;
				margin-bottom: 15px;
			}
			.material-status.uploaded {
				background: #d4edda;
				color: #155724;
			}
			.material-status.empty {
				background: #f8d7da;
				color: #721c24;
			}
			.material-meta {
				color: #646970;
				font-size: 13px;
			}
			.upload-field {
				background: white;
				padding: 15px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
			}
			
			/* Documents */
			.existing-documents {
				background: white;
				padding: 15px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				margin-bottom: 20px;
			}
			.doc-row {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 10px;
				background: #f0f0f1;
				border-radius: 4px;
				margin-bottom: 10px;
			}
			.doc-icon {
				font-size: 20px;
			}
			.doc-name {
				flex: 1;
				font-weight: 600;
				text-decoration: none;
			}
			.doc-category-badge {
				background: #2271b1;
				color: white;
				padding: 3px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
			}
			.doc-size {
				color: #646970;
				font-size: 13px;
			}
			
			/* Upload documents section */
			.upload-documents-section {
				background: white;
				padding: 15px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
			}
			.doc-upload-row {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 10px;
			}
			.doc-upload-row input[type="file"] {
				flex: 2;
			}
			.doc-category-select {
				flex: 1;
				min-width: 200px;
			}
		</style>
		<?php
	}

	/**
	 * Render scripts
	 */
	private static function render_scripts() {
		?>
		<script>
		jQuery(document).ready(function($) {
			// Přepínání jazykových záložek
			$('.saw-language-tab').on('click', function() {
				const lang = $(this).data('lang');
				
				$('.saw-language-tab').removeClass('active');
				$(this).addClass('active');
				
				$('.saw-language-content').hide();
				$('.saw-language-content[data-lang="' + lang + '"]').show();
			});
			
			// Accordion - otevřít první položku při načtení
			$('.saw-accordion-item').first().addClass('active');
			
			// Accordion toggle
			$('.saw-accordion-header').on('click', function() {
				const item = $(this).closest('.saw-accordion-item');
				const wasActive = item.hasClass('active');
				
				// Zavřít všechny
				$('.saw-accordion-item').removeClass('active');
				
				// Otevřít aktuální (pokud nebyla aktivní)
				if (!wasActive) {
					item.addClass('active');
				}
			});
			
			// Přidat další řádek pro nahrání dokumentu
			$('.add-doc-row').on('click', function() {
				const section = $(this).data('section');
				const lang = $(this).data('lang');
				const container = $('#' + section + '-doc-uploads-' + lang);
				
				// Klonovat první řádek
				const firstRow = container.find('.doc-upload-row').first();
				const newRow = firstRow.clone();
				
				// Vyčistit hodnoty
				newRow.find('input[type="file"]').val('');
				newRow.find('select').prop('selectedIndex', 0);
				
				// Změnit tlačítko na "Odebrat"
				const button = newRow.find('.add-doc-row');
				button.removeClass('add-doc-row').addClass('remove-doc-row');
				button.text('− Odebrat');
				
				container.append(newRow);
			});
			
			// Odebrat řádek
			$(document).on('click', '.remove-doc-row', function() {
				$(this).closest('.doc-upload-row').remove();
			});
		});
		</script>
		<?php
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

				// Risks documents
				if ( ! empty( $_FILES[ 'risks_docs_' . $lang_code ]['name'][0] ) ) {
					$files = $_FILES[ 'risks_docs_' . $lang_code ];
					$categories = $_POST[ 'risks_docs_category_' . $lang_code ] ?? array();
					
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

						$category = $categories[ $i ] ?? 'emergency';
						$result = self::handle_file_upload( $file, self::ALLOWED_PDF_TYPES, 'documents' );

						if ( $result['success'] ) {
							$wpdb->insert(
								$wpdb->prefix . 'saw_documents',
								array(
									'customer_id' => $customer_id,
									'category'    => $category,
									'language'    => $lang_code,
									'filename'    => basename( $result['file_url'] ),
									'file_url'    => $result['file_url'],
									'created_at'  => current_time( 'mysql' ),
								),
								array( '%d', '%s', '%s', '%s', '%s', '%s' )
							);
						}
					}
				}

				// Additional documents
				if ( ! empty( $_FILES[ 'additional_docs_' . $lang_code ]['name'][0] ) ) {
					$files = $_FILES[ 'additional_docs_' . $lang_code ];
					$categories = $_POST[ 'additional_docs_category_' . $lang_code ] ?? array();
					
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

						$category = $categories[ $i ] ?? 'other';
						$result = self::handle_file_upload( $file, self::ALLOWED_PDF_TYPES, 'documents' );

						if ( $result['success'] ) {
							$wpdb->insert(
								$wpdb->prefix . 'saw_documents',
								array(
									'customer_id' => $customer_id,
									'category'    => $category,
									'language'    => $lang_code,
									'filename'    => basename( $result['file_url'] ),
									'file_url'    => $result['file_url'],
									'created_at'  => current_time( 'mysql' ),
								),
								array( '%d', '%s', '%s', '%s', '%s', '%s' )
							);
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
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return array( 'success' => false, 'message' => 'Chyba při nahrávání souboru.' );
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return array( 'success' => false, 'message' => 'Soubor je příliš velký (max. 20 MB).' );
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return array( 'success' => false, 'message' => 'Nepovolený typ souboru.' );
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
	 * Save or update material
	 */
	private static function save_material( $customer_id, $type, $language, $file_url = null, $wysiwyg_content = null ) {
		global $wpdb;

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}saw_materials 
			WHERE customer_id = %d AND material_type = %s AND language = %s",
			$customer_id, $type, $language
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
			$wpdb->insert( $wpdb->prefix . 'saw_materials', $data );
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

		if ( $material->file_url ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $material->file_url );
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( $wpdb->prefix . 'saw_materials', array( 'id' => $material_id ), array( '%d' ) );

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

		$file_path = str_replace( content_url(), WP_CONTENT_DIR, $document->file_url );
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		$wpdb->delete( $wpdb->prefix . 'saw_documents', array( 'id' => $document_id ), array( '%d' ) );

		SAW_Audit::log( array(
			'action'      => 'document_deleted',
			'customer_id' => $document->customer_id,
			'details'     => sprintf( 'Deleted document: %s (%s)', $document->filename, $document->category ),
		) );
	}

	/**
	 * Get file size
	 */
	private static function get_file_size( $file_url ) {
		$file_path = str_replace( content_url(), WP_CONTENT_DIR, $file_url );
		if ( file_exists( $file_path ) ) {
			return size_format( filesize( $file_path ) );
		}
		return 'N/A';
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