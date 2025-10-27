<?php
// /wp-content/plugins/saw-visitors/includes/admin/class-saw-admin-content.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Admin_Content {

    public static function render_page() {
        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění k této stránce.' );
        }

        if ( ! isset( $_SESSION['saw_selected_customer_id'] ) ) {
            echo '<div class="wrap"><h1>Správa obsahu</h1>';
            echo '<div class="notice notice-warning"><p>Nejprve vyberte zákazníka v horní liště.</p></div></div>';
            return;
        }

        $customer_id = intval( $_SESSION['saw_selected_customer_id'] );
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $customer_id
        ) );

        if ( ! $customer ) {
            wp_die( 'Zákazník nenalezen.' );
        }

        self::enqueue_styles_scripts();

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['saw_content_nonce'] ) ) {
            self::handle_save( $customer_id );
        }

        $languages = array(
            'cs' => 'Čeština',
            'en' => 'English',
            'de' => 'Deutsch',
            'uk' => 'Українська'
        );

        $departments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d ORDER BY name ASC",
            $customer_id
        ) );

        ?>
        <div class="wrap saw-content-wrapper">
            <h1>
                Správa obsahu
                <span class="saw-customer-badge"><?php echo esc_html( $customer->name ); ?></span>
            </h1>

            <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?>
                <div class="saw-success-message">
                    ✓ Obsah byl úspěšně uložen!
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'saw_save_content', 'saw_content_nonce' ); ?>

                <div class="saw-language-tabs">
                    <?php foreach ( $languages as $lang_code => $lang_name ) : ?>
                        <button type="button" class="saw-language-tab <?php echo $lang_code === 'cs' ? 'active' : ''; ?>" data-lang="<?php echo esc_attr( $lang_code ); ?>">
                            <?php echo esc_html( $lang_name ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ( $languages as $lang_code => $lang_name ) : ?>
                    <div class="saw-language-content" data-lang-content="<?php echo esc_attr( $lang_code ); ?>" style="<?php echo $lang_code !== 'cs' ? 'display:none;' : ''; ?>">
                        
                        <div class="saw-accordion">

                            <?php self::render_video_section( $customer_id, $lang_code ); ?>

                            <?php self::render_pdf_map_section( $customer_id, $lang_code ); ?>

                            <?php self::render_risks_section( $customer_id, $lang_code ); ?>

                            <?php self::render_additional_info_section( $customer_id, $lang_code ); ?>

                            <?php self::render_department_specific_section( $customer_id, $lang_code, $departments ); ?>

                        </div>

                    </div>
                <?php endforeach; ?>

                <p style="margin-top: 30px;">
                    <button type="submit" class="button-primary saw-save-btn">Uložit vše</button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.saw-language-tab').on('click', function() {
                var lang = $(this).data('lang');
                $('.saw-language-tab').removeClass('active');
                $(this).addClass('active');
                $('.saw-language-content').hide();
                $('[data-lang-content="' + lang + '"]').show();
            });

            $('.saw-accordion-header').on('click', function() {
                var $item = $(this).closest('.saw-accordion-item');
                var $content = $item.find('.saw-accordion-content');
                
                if ($item.hasClass('active')) {
                    $item.removeClass('active');
                    $content.slideUp(300);
                } else {
                    $('.saw-accordion-item').removeClass('active');
                    $('.saw-accordion-content').slideUp(300);
                    $item.addClass('active');
                    $content.slideDown(300);
                }
            });

            $('.saw-file-upload-area').on('click', function() {
                $(this).find('input[type="file"]').click();
            });

            $('input[type="file"]').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                var $area = $(this).closest('.saw-file-upload-area');
                if (fileName) {
                    $area.addClass('has-file');
                    $area.find('.saw-upload-text').text('Soubor vybrán: ' + fileName);
                }
            });

            $('.saw-add-document-btn').on('click', function(e) {
                e.preventDefault();
                var $container = $(this).closest('.saw-documents-upload').find('.saw-documents-list');
                var lang = $(this).closest('[data-lang-content]').data('lang-content');
                var section = $(this).data('section');
                var rowIndex = $container.find('.saw-document-row').length;
                
                var rowHtml = `
                    <div class="saw-document-row">
                        <div class="saw-doc-upload-wrapper">
                            <label class="saw-doc-upload-btn">
                                <span>📎</span>
                                <span>Vybrat soubor</span>
                                <input type="file" class="saw-file-input-hidden" name="doc_file_${section}_${lang}_${rowIndex}" accept=".pdf,.doc,.docx,.xls,.xlsx">
                            </label>
                        </div>
                        <select name="doc_category_${section}_${lang}_${rowIndex}" class="saw-doc-category-select">
                            <option value="">-- Vyberte kategorii --</option>
                            <option value="emergency">Mimořádné situace</option>
                            <option value="fire">Požární ochrana</option>
                            <option value="safety">Bezpečnost práce</option>
                            <option value="hygiene">Hygiena</option>
                            <option value="environment">Životní prostředí</option>
                            <option value="security">Bezpečnost</option>
                            <option value="other">Ostatní</option>
                        </select>
                        <button type="button" class="saw-doc-remove-btn">🗑</button>
                    </div>
                `;
                
                $container.append(rowHtml);
            });

            $(document).on('click', '.saw-doc-remove-btn', function(e) {
                e.preventDefault();
                $(this).closest('.saw-document-row').remove();
            });

            $(document).on('change', 'input[type="file"]', function() {
                var fileName = $(this).val().split('\\').pop();
                var $btn = $(this).closest('.saw-doc-upload-btn');
                if (fileName) {
                    $btn.find('span:last').text(fileName);
                }
            });

            $('.saw-add-department-btn').on('click', function(e) {
                e.preventDefault();
                var $select = $(this).siblings('.saw-department-select');
                var deptId = $select.val();
                var deptName = $select.find('option:selected').text();
                var lang = $(this).closest('[data-lang-content]').data('lang-content');
                var $container = $(this).closest('.saw-department-selector').siblings('.saw-departments-list');
                
                if (!deptId) {
                    alert('Vyberte oddělení');
                    return;
                }
                
                var existingDept = $container.find('[data-dept-id="' + deptId + '"]');
                if (existingDept.length > 0) {
                    alert('Toto oddělení již bylo přidáno');
                    return;
                }
                
                var editorId = 'dept_wysiwyg_' + lang + '_' + deptId;
                
                var deptHtml = `
                    <div class="saw-department-item" data-dept-id="${deptId}">
                        <div class="saw-department-header">
                            <h4 class="saw-department-name">${deptName}</h4>
                            <button type="button" class="saw-remove-department-btn">Odstranit</button>
                        </div>
                        <div class="saw-content-section">
                            <label class="saw-subsection-title">Specifické informace pro toto oddělení</label>
                            <div class="saw-wysiwyg-wrapper">
                                <textarea id="${editorId}" name="dept_wysiwyg_${lang}_${deptId}" class="saw-wysiwyg-editor" rows="8"></textarea>
                            </div>
                        </div>
                        <div class="saw-content-section">
                            <label class="saw-subsection-title">Dokumenty pro oddělení</label>
                            <div class="saw-documents-upload">
                                <div class="saw-documents-list"></div>
                                <button type="button" class="saw-add-document-btn" data-section="dept_${deptId}">
                                    <span>➕</span>
                                    <span>Přidat dokument</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $container.append(deptHtml);
                $container.find('.saw-no-departments-message').remove();
                
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.initialize(editorId, {
                        tinymce: {
                            wpautop: true,
                            plugins: 'lists,link,textcolor',
                            toolbar1: 'formatselect,bold,italic,underline,bullist,numlist,link,forecolor'
                        },
                        quicktags: true
                    });
                }
                
                $select.val('');
            });

            $(document).on('click', '.saw-remove-department-btn', function(e) {
                e.preventDefault();
                if (confirm('Opravdu chcete odstranit specifické informace tohoto oddělení?')) {
                    var $item = $(this).closest('.saw-department-item');
                    var editorId = $item.find('textarea').attr('id');
                    
                    if (typeof wp !== 'undefined' && wp.editor && editorId) {
                        wp.editor.remove(editorId);
                    }
                    
                    $item.remove();
                }
            });
        });
        </script>
        <?php
    }

    private static function render_video_section( $customer_id, $lang_code ) {
        global $wpdb;
        
        $material = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'video' AND file_url LIKE CONCAT('%%', %s, '%%')",
            $customer_id,
            $lang_code
        ) );

        $video_url = '';
        if ( $material && $material->file_url ) {
            $urls = maybe_unserialize( $material->file_url );
            if ( is_array( $urls ) && isset( $urls[ $lang_code ] ) ) {
                $video_url = $urls[ $lang_code ];
            } elseif ( ! is_array( $urls ) ) {
                $video_url = $material->file_url;
            }
        }
        ?>
        <div class="saw-accordion-item">
            <button type="button" class="saw-accordion-header">
                <span>🎥 Hlavní instruktážní video</span>
                <span class="accordion-icon">▼</span>
            </button>
            <div class="saw-accordion-content">
                <div class="saw-content-section">
                    <label class="saw-section-title">URL videa (YouTube nebo Vimeo)</label>
                    <div class="saw-video-input-group">
                        <input 
                            type="url" 
                            name="video_url_<?php echo esc_attr( $lang_code ); ?>" 
                            class="saw-video-input" 
                            value="<?php echo esc_attr( $video_url ); ?>"
                            placeholder="https://www.youtube.com/watch?v=... nebo https://vimeo.com/..."
                        >
                    </div>
                    <?php if ( $video_url ) : ?>
                        <div class="saw-video-preview">
                            <div class="saw-video-preview-info">
                                <span class="saw-video-icon">🎬</span>
                                <div>
                                    <div style="font-weight: 600; margin-bottom: 4px;">Video uloženo</div>
                                    <div class="saw-video-url-text"><?php echo esc_html( $video_url ); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_pdf_map_section( $customer_id, $lang_code ) {
        global $wpdb;
        
        $material = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'pdf' AND file_url LIKE CONCAT('%%', %s, '%%')",
            $customer_id,
            $lang_code
        ) );

        $has_file = false;
        $file_url = '';
        if ( $material && $material->file_url ) {
            $urls = maybe_unserialize( $material->file_url );
            if ( is_array( $urls ) && isset( $urls[ $lang_code ] ) ) {
                $has_file = true;
                $file_url = $urls[ $lang_code ];
            } elseif ( ! is_array( $urls ) ) {
                $has_file = true;
                $file_url = $material->file_url;
            }
        }
        ?>
        <div class="saw-accordion-item">
            <button type="button" class="saw-accordion-header">
                <span>🗺️ Schematický plán areálu / objektů</span>
                <span class="accordion-icon">▼</span>
            </button>
            <div class="saw-accordion-content">
                <div class="saw-content-section">
                    <label class="saw-section-title">PDF soubor s mapou</label>
                    <div class="saw-file-upload-area <?php echo $has_file ? 'has-file' : ''; ?>">
                        <div class="saw-upload-icon">📄</div>
                        <div class="saw-upload-text">
                            <?php echo $has_file ? 'Klikněte pro změnu souboru' : 'Klikněte nebo přetáhněte PDF soubor'; ?>
                        </div>
                        <div class="saw-upload-hint">Pouze PDF, max 10MB</div>
                        <input 
                            type="file" 
                            name="pdf_map_<?php echo esc_attr( $lang_code ); ?>" 
                            class="saw-file-input-hidden" 
                            accept=".pdf"
                        >
                    </div>
                    <?php if ( $has_file ) : ?>
                        <div class="saw-current-file">
                            <div class="saw-file-info">
                                <span class="saw-file-icon">📕</span>
                                <div class="saw-file-details">
                                    <div class="saw-file-name"><?php echo esc_html( basename( $file_url ) ); ?></div>
                                    <div class="saw-file-size">Nahráno</div>
                                </div>
                            </div>
                            <label>
                                <input type="checkbox" name="delete_pdf_map_<?php echo esc_attr( $lang_code ); ?>" value="1">
                                Smazat tento soubor
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_risks_section( $customer_id, $lang_code ) {
        global $wpdb;
        
        $column_name = 'content_' . $lang_code;
        $material = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'wysiwyg' AND title = 'risks'",
            $customer_id
        ) );

        $wysiwyg_content = $material && isset( $material->$column_name ) ? $material->$column_name : '';

        $documents = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_documents WHERE customer_id = %d AND language = %s AND category IN ('bozp', 'po') ORDER BY id DESC",
            $customer_id,
            $lang_code
        ) );

        ?>
        <div class="saw-accordion-item">
            <button type="button" class="saw-accordion-header">
                <span>⚠️ Informace o rizicích a o přijatých opatřeních</span>
                <span class="accordion-icon">▼</span>
            </button>
            <div class="saw-accordion-content">
                <label class="saw-section-subtitle">
                    Zde zadejte písemně informace o rizicích a o přijatých opatřeních, dle odst. 3, § 101, zákona č. 262/2006 Sb., Zákoníku práce v účinném znění.
                </label>

                <div class="saw-content-section">
                    <label class="saw-subsection-title">Textové informace o rizicích</label>
                    <div class="saw-wysiwyg-wrapper">
                        <?php 
                        wp_editor( $wysiwyg_content, 'risks_wysiwyg_' . $lang_code, array(
                            'textarea_name' => 'risks_wysiwyg_' . $lang_code,
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => false,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,forecolor',
                            ),
                        ));
                        ?>
                    </div>
                </div>

                <div class="saw-content-section">
                    <label class="saw-subsection-title">Dokumenty rizik</label>
                    <div class="saw-documents-upload">
                        <div class="saw-documents-list">
                            <?php foreach ( $documents as $doc ) : ?>
                                <div class="saw-document-row">
                                    <div>
                                        <strong><?php echo esc_html( $doc->title ); ?></strong>
                                        <br>
                                        <small>Kategorie: <?php echo esc_html( self::get_category_label( $doc->category ) ); ?></small>
                                        <input type="hidden" name="existing_doc_risks_<?php echo esc_attr( $lang_code ); ?>[]" value="<?php echo esc_attr( $doc->id ); ?>">
                                    </div>
                                    <div></div>
                                    <label>
                                        <input type="checkbox" name="delete_doc_<?php echo esc_attr( $doc->id ); ?>" value="1">
                                        Smazat
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="saw-add-document-btn" data-section="risks">
                            <span>➕</span>
                            <span>Přidat dokument</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_additional_info_section( $customer_id, $lang_code ) {
        global $wpdb;
        
        $column_name = 'content_' . $lang_code;
        $material = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'wysiwyg' AND title = 'additional'",
            $customer_id
        ) );

        $wysiwyg_content = $material && isset( $material->$column_name ) ? $material->$column_name : '';

        $documents = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_documents WHERE customer_id = %d AND language = %s AND category = 'other' ORDER BY id DESC",
            $customer_id,
            $lang_code
        ) );

        ?>
        <div class="saw-accordion-item">
            <button type="button" class="saw-accordion-header">
                <span>ℹ️ Další informace</span>
                <span class="accordion-icon">▼</span>
            </button>
            <div class="saw-accordion-content">
                <label class="saw-section-subtitle">
                    Zde zadejte jakékoliv další důležité informace, které mohou být pro návštěvníky vaší společnosti podstatné.
                </label>

                <div class="saw-content-section">
                    <label class="saw-subsection-title">Textové další informace</label>
                    <div class="saw-wysiwyg-wrapper">
                        <?php 
                        wp_editor( $wysiwyg_content, 'additional_wysiwyg_' . $lang_code, array(
                            'textarea_name' => 'additional_wysiwyg_' . $lang_code,
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => false,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,forecolor',
                            ),
                        ));
                        ?>
                    </div>
                </div>

                <div class="saw-content-section">
                    <label class="saw-subsection-title">Dokumenty k dalším informacím</label>
                    <div class="saw-documents-upload">
                        <div class="saw-documents-list">
                            <?php foreach ( $documents as $doc ) : ?>
                                <div class="saw-document-row">
                                    <div>
                                        <strong><?php echo esc_html( $doc->title ); ?></strong>
                                        <br>
                                        <small>Kategorie: <?php echo esc_html( self::get_category_label( $doc->category ) ); ?></small>
                                        <input type="hidden" name="existing_doc_additional_<?php echo esc_attr( $lang_code ); ?>[]" value="<?php echo esc_attr( $doc->id ); ?>">
                                    </div>
                                    <div></div>
                                    <label>
                                        <input type="checkbox" name="delete_doc_<?php echo esc_attr( $doc->id ); ?>" value="1">
                                        Smazat
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="saw-add-document-btn" data-section="additional">
                            <span>➕</span>
                            <span>Přidat dokument</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_department_specific_section( $customer_id, $lang_code, $departments ) {
        global $wpdb;

        $column_name = 'wysiwyg_' . $lang_code;
        $dept_materials = $wpdb->get_results( $wpdb->prepare(
            "SELECT dm.*, d.name as department_name 
            FROM {$wpdb->prefix}saw_department_materials dm
            LEFT JOIN {$wpdb->prefix}saw_departments d ON dm.department_id = d.id
            WHERE d.customer_id = %d AND dm.{$column_name} IS NOT NULL AND dm.{$column_name} != ''",
            $customer_id
        ), OBJECT_K );

        ?>
        <div class="saw-accordion-item">
            <button type="button" class="saw-accordion-header">
                <span>🏢 Specifické informace oddělení</span>
                <span class="accordion-icon">▼</span>
            </button>
            <div class="saw-accordion-content">
                <div class="saw-department-specific-section">
                    
                    <div class="saw-department-selector">
                        <label class="saw-section-title">Přidat specifické informace pro oddělení</label>
                        <div class="saw-department-select-wrapper">
                            <select class="saw-department-select">
                                <option value="">-- Vyberte oddělení --</option>
                                <?php foreach ( $departments as $dept ) : ?>
                                    <option value="<?php echo esc_attr( $dept->id ); ?>">
                                        <?php echo esc_html( $dept->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="saw-add-department-btn">
                                <span>➕</span>
                                <span>Přidat oddělení</span>
                            </button>
                        </div>
                    </div>

                    <div class="saw-departments-list">
                        <?php if ( empty( $dept_materials ) ) : ?>
                            <div class="saw-no-departments-message">
                                Zatím nejsou přidána žádná oddělení. Použijte tlačítko výše pro přidání.
                            </div>
                        <?php else : ?>
                            <?php foreach ( $dept_materials as $dept_id => $material ) : ?>
                                <div class="saw-department-item" data-dept-id="<?php echo esc_attr( $material->department_id ); ?>">
                                    <div class="saw-department-header">
                                        <h4 class="saw-department-name"><?php echo esc_html( $material->department_name ); ?></h4>
                                        <button type="button" class="saw-remove-department-btn">Odstranit</button>
                                    </div>
                                    <div class="saw-content-section">
                                        <label class="saw-subsection-title">Specifické informace pro toto oddělení</label>
                                        <div class="saw-wysiwyg-wrapper">
                                            <?php 
                                            $content = isset( $material->$column_name ) ? $material->$column_name : '';
                                            wp_editor( $content, 'dept_wysiwyg_' . $lang_code . '_' . $material->department_id, array(
                                                'textarea_name' => 'dept_wysiwyg_' . $lang_code . '_' . $material->department_id,
                                                'textarea_rows' => 8,
                                                'media_buttons' => false,
                                                'teeny' => false,
                                                'tinymce' => array(
                                                    'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,forecolor',
                                                ),
                                            ));
                                            ?>
                                        </div>
                                    </div>
                                    <div class="saw-content-section">
                                        <label class="saw-subsection-title">Dokumenty pro oddělení</label>
                                        <div class="saw-documents-upload">
                                            <div class="saw-documents-list">
                                                <?php
                                                $dept_docs = $wpdb->get_results( $wpdb->prepare(
                                                    "SELECT * FROM {$wpdb->prefix}saw_department_documents 
                                                    WHERE department_id = %d AND language = %s
                                                    ORDER BY id DESC",
                                                    $material->department_id,
                                                    $lang_code
                                                ) );
                                                foreach ( $dept_docs as $doc ) : ?>
                                                    <div class="saw-document-row">
                                                        <div>
                                                            <strong><?php echo esc_html( $doc->title ); ?></strong>
                                                            <br>
                                                            <small>Kategorie: <?php echo esc_html( self::get_category_label( $doc->category ) ); ?></small>
                                                            <input type="hidden" name="existing_doc_dept_<?php echo esc_attr( $lang_code ); ?>_<?php echo esc_attr( $material->department_id ); ?>[]" value="<?php echo esc_attr( $doc->id ); ?>">
                                                        </div>
                                                        <div></div>
                                                        <label>
                                                            <input type="checkbox" name="delete_doc_<?php echo esc_attr( $doc->id ); ?>" value="1">
                                                            Smazat
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="saw-add-document-btn" data-section="dept_<?php echo esc_attr( $material->department_id ); ?>">
                                                <span>➕</span>
                                                <span>Přidat dokument</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

    private static function enqueue_styles_scripts() {
        wp_enqueue_media();
        wp_enqueue_editor();
        
        wp_enqueue_style(
            'saw-admin-content',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-admin-content.css',
            array(),
            SAW_VISITORS_VERSION
        );
    }

    private static function handle_save( $customer_id ) {
        global $wpdb;

        check_admin_referer( 'saw_save_content', 'saw_content_nonce' );

        $languages = array( 'cs', 'en', 'de', 'uk' );
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/saw-visitor-docs/';

        foreach ( $languages as $lang_code ) {

            $video_url = isset( $_POST['video_url_' . $lang_code] ) ? esc_url_raw( $_POST['video_url_' . $lang_code] ) : '';
            if ( $video_url ) {
                $existing = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id, file_url FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'video'",
                    $customer_id
                ) );

                $all_urls = array();
                if ( $existing && $existing->file_url ) {
                    $all_urls = maybe_unserialize( $existing->file_url );
                    if ( ! is_array( $all_urls ) ) {
                        $all_urls = array();
                    }
                }

                $all_urls[ $lang_code ] = $video_url;

                if ( $existing ) {
                    $wpdb->update(
                        $wpdb->prefix . 'saw_materials',
                        array( 'file_url' => maybe_serialize( $all_urls ) ),
                        array( 'id' => $existing->id )
                    );
                } else {
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_materials',
                        array(
                            'customer_id' => $customer_id,
                            'title' => 'Video',
                            'type' => 'video',
                            'file_url' => maybe_serialize( $all_urls ),
                        )
                    );
                }
            }

            if ( isset( $_FILES['pdf_map_' . $lang_code] ) && $_FILES['pdf_map_' . $lang_code]['error'] === UPLOAD_ERR_OK ) {
                $file = $_FILES['pdf_map_' . $lang_code];
                $filename = sanitize_file_name( $file['name'] );
                $target_path = $base_path . 'materials/' . $filename;
                
                if ( move_uploaded_file( $file['tmp_name'], $target_path ) ) {
                    $file_url = $upload_dir['baseurl'] . '/saw-visitor-docs/materials/' . $filename;
                    
                    $existing = $wpdb->get_row( $wpdb->prepare(
                        "SELECT id, file_url FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'pdf'",
                        $customer_id
                    ) );

                    $all_urls = array();
                    if ( $existing && $existing->file_url ) {
                        $all_urls = maybe_unserialize( $existing->file_url );
                        if ( ! is_array( $all_urls ) ) {
                            $all_urls = array();
                        }
                    }

                    $all_urls[ $lang_code ] = $file_url;

                    if ( $existing ) {
                        $wpdb->update(
                            $wpdb->prefix . 'saw_materials',
                            array( 'file_url' => maybe_serialize( $all_urls ) ),
                            array( 'id' => $existing->id )
                        );
                    } else {
                        $wpdb->insert(
                            $wpdb->prefix . 'saw_materials',
                            array(
                                'customer_id' => $customer_id,
                                'title' => 'PDF Mapa',
                                'type' => 'pdf',
                                'file_url' => maybe_serialize( $all_urls ),
                            )
                        );
                    }
                }
            }

            if ( isset( $_POST['delete_pdf_map_' . $lang_code] ) ) {
                $existing = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id, file_url FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'pdf'",
                    $customer_id
                ) );

                if ( $existing && $existing->file_url ) {
                    $all_urls = maybe_unserialize( $existing->file_url );
                    if ( is_array( $all_urls ) && isset( $all_urls[ $lang_code ] ) ) {
                        unset( $all_urls[ $lang_code ] );
                        
                        if ( empty( $all_urls ) ) {
                            $wpdb->delete(
                                $wpdb->prefix . 'saw_materials',
                                array( 'id' => $existing->id )
                            );
                        } else {
                            $wpdb->update(
                                $wpdb->prefix . 'saw_materials',
                                array( 'file_url' => maybe_serialize( $all_urls ) ),
                                array( 'id' => $existing->id )
                            );
                        }
                    }
                }
            }

            $risks_wysiwyg = isset( $_POST['risks_wysiwyg_' . $lang_code] ) ? wp_kses_post( $_POST['risks_wysiwyg_' . $lang_code] ) : '';
            $column_name = 'content_' . $lang_code;
            
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'wysiwyg' AND title = 'risks'",
                $customer_id
            ) );

            if ( $existing ) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_materials',
                    array( $column_name => $risks_wysiwyg ),
                    array( 'id' => $existing->id )
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_materials',
                    array(
                        'customer_id' => $customer_id,
                        'title' => 'risks',
                        'type' => 'wysiwyg',
                        $column_name => $risks_wysiwyg,
                    )
                );
            }

            $additional_wysiwyg = isset( $_POST['additional_wysiwyg_' . $lang_code] ) ? wp_kses_post( $_POST['additional_wysiwyg_' . $lang_code] ) : '';
            
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_materials WHERE customer_id = %d AND type = 'wysiwyg' AND title = 'additional'",
                $customer_id
            ) );

            if ( $existing ) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_materials',
                    array( $column_name => $additional_wysiwyg ),
                    array( 'id' => $existing->id )
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_materials',
                    array(
                        'customer_id' => $customer_id,
                        'title' => 'additional',
                        'type' => 'wysiwyg',
                        $column_name => $additional_wysiwyg,
                    )
                );
            }

            foreach ( $_FILES as $key => $file ) {
                if ( strpos( $key, 'doc_file_risks_' . $lang_code ) === 0 && $file['error'] === UPLOAD_ERR_OK ) {
                    $index = str_replace( 'doc_file_risks_' . $lang_code . '_', '', $key );
                    $category = isset( $_POST['doc_category_risks_' . $lang_code . '_' . $index] ) ? sanitize_text_field( $_POST['doc_category_risks_' . $lang_code . '_' . $index] ) : 'other';
                    
                    $filename = sanitize_file_name( $file['name'] );
                    $target_path = $base_path . 'risk-docs/' . $filename;
                    
                    if ( move_uploaded_file( $file['tmp_name'], $target_path ) ) {
                        $file_url = $upload_dir['baseurl'] . '/saw-visitor-docs/risk-docs/' . $filename;
                        $wpdb->insert(
                            $wpdb->prefix . 'saw_documents',
                            array(
                                'department_id' => 0,
                                'category' => self::map_category_to_enum( $category ),
                                'title' => $filename,
                                'file_url' => $file_url,
                                'language' => $lang_code,
                                'created_at' => current_time( 'mysql' ),
                            )
                        );
                    }
                }

                if ( strpos( $key, 'doc_file_additional_' . $lang_code ) === 0 && $file['error'] === UPLOAD_ERR_OK ) {
                    $index = str_replace( 'doc_file_additional_' . $lang_code . '_', '', $key );
                    $category = isset( $_POST['doc_category_additional_' . $lang_code . '_' . $index] ) ? sanitize_text_field( $_POST['doc_category_additional_' . $lang_code . '_' . $index] ) : 'other';
                    
                    $filename = sanitize_file_name( $file['name'] );
                    $target_path = $base_path . 'additional-docs/' . $filename;
                    
                    if ( move_uploaded_file( $file['tmp_name'], $target_path ) ) {
                        $file_url = $upload_dir['baseurl'] . '/saw-visitor-docs/additional-docs/' . $filename;
                        $wpdb->insert(
                            $wpdb->prefix . 'saw_documents',
                            array(
                                'department_id' => 0,
                                'category' => self::map_category_to_enum( $category ),
                                'title' => $filename,
                                'file_url' => $file_url,
                                'language' => $lang_code,
                                'created_at' => current_time( 'mysql' ),
                            )
                        );
                    }
                }

                if ( preg_match( '/^doc_file_dept_(\d+)_' . $lang_code . '_(\d+)$/', $key, $matches ) && $file['error'] === UPLOAD_ERR_OK ) {
                    $dept_id = intval( $matches[1] );
                    $index = intval( $matches[2] );
                    $category = isset( $_POST['doc_category_dept_' . $dept_id . '_' . $lang_code . '_' . $index] ) ? sanitize_text_field( $_POST['doc_category_dept_' . $dept_id . '_' . $lang_code . '_' . $index] ) : 'other';
                    
                    $filename = sanitize_file_name( $file['name'] );
                    $target_path = $base_path . 'department-docs/' . $filename;
                    
                    if ( move_uploaded_file( $file['tmp_name'], $target_path ) ) {
                        $file_url = $upload_dir['baseurl'] . '/saw-visitor-docs/department-docs/' . $filename;
                        $wpdb->insert(
                            $wpdb->prefix . 'saw_department_documents',
                            array(
                                'department_id' => $dept_id,
                                'category' => self::map_category_to_enum( $category ),
                                'title' => $filename,
                                'file_url' => $file_url,
                                'language' => $lang_code,
                                'created_at' => current_time( 'mysql' ),
                            )
                        );
                    }
                }
            }

            foreach ( $_POST as $key => $value ) {
                if ( strpos( $key, 'dept_wysiwyg_' . $lang_code . '_' ) === 0 ) {
                    $dept_id = intval( str_replace( 'dept_wysiwyg_' . $lang_code . '_', '', $key ) );
                    $content = wp_kses_post( $value );
                    
                    $column_name = 'wysiwyg_' . $lang_code;
                    
                    $existing = $wpdb->get_row( $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}saw_department_materials WHERE department_id = %d",
                        $dept_id
                    ) );

                    if ( $existing ) {
                        $wpdb->update(
                            $wpdb->prefix . 'saw_department_materials',
                            array( $column_name => $content ),
                            array( 'id' => $existing->id )
                        );
                    } else {
                        $wpdb->insert(
                            $wpdb->prefix . 'saw_department_materials',
                            array(
                                'department_id' => $dept_id,
                                'title' => 'Specific Info',
                                $column_name => $content,
                            )
                        );
                    }
                }
            }
        }

        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'delete_doc_' ) === 0 && $value === '1' ) {
                $doc_id = intval( str_replace( 'delete_doc_', '', $key ) );
                $wpdb->delete( $wpdb->prefix . 'saw_documents', array( 'id' => $doc_id ) );
                $wpdb->delete( $wpdb->prefix . 'saw_department_documents', array( 'id' => $doc_id ) );
            }
        }

        SAW_Audit::log( array(
            'action' => 'content_updated',
            'customer_id' => $customer_id,
            'details' => 'Content updated for customer ID: ' . $customer_id,
        ) );

        wp_redirect( add_query_arg( 'saved', '1', wp_get_referer() ) );
        exit;
    }

    private static function get_category_label( $category ) {
        $labels = array(
            'bozp' => 'Bezpečnost práce',
            'po' => 'Požární ochrana',
            'evakuace' => 'Evakuace',
            'prvni_pomoc' => 'První pomoc',
            'organizacni' => 'Organizační',
            'pojisteni' => 'Pojištění',
            'covid' => 'COVID-19',
            'emergency' => 'Mimořádné situace',
            'fire' => 'Požární ochrana',
            'safety' => 'Bezpečnost práce',
            'hygiene' => 'Hygiena',
            'environment' => 'Životní prostředí',
            'security' => 'Bezpečnost',
            'other' => 'Ostatní',
        );
        return isset( $labels[ $category ] ) ? $labels[ $category ] : $category;
    }

    private static function map_category_to_enum( $category ) {
        $map = array(
            'emergency' => 'evakuace',
            'fire' => 'po',
            'safety' => 'bozp',
            'hygiene' => 'prvni_pomoc',
            'environment' => 'organizacni',
            'security' => 'pojisteni',
            'other' => 'covid',
        );
        return isset( $map[ $category ] ) ? $map[ $category ] : 'organizacni';
    }
}