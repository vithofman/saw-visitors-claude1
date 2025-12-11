-- Překlady pro sekci návštěvníků ve formuláři návštěvy
-- Spusťte tento SQL příkaz v databázi (nahraďte {prefix} správným prefixem, např. wp_ nebo cwp_)

INSERT INTO {prefix}saw_ui_translations (translation_key, language_code, context, section, translation_text) VALUES
-- Sekce
('section_visitors', 'cs', 'admin', 'visits', 'Návštěvníci'),
('btn_add_visitor', 'cs', 'admin', 'visits', 'Přidat'),
('visitors_empty', 'cs', 'admin', 'visits', 'Zatím nebyli přidáni žádní návštěvníci.'),
('visitors_empty_hint', 'cs', 'admin', 'visits', 'Klikněte na "Přidat" pro přidání návštěvníka.'),
('visitors_total', 'cs', 'admin', 'visits', 'Celkem:'),
('visitors_label', 'cs', 'admin', 'visits', 'návštěvníků'),
-- Nested form
('title_add_visitor', 'cs', 'admin', 'visits', 'Přidat návštěvníka'),
('title_edit_visitor', 'cs', 'admin', 'visits', 'Upravit návštěvníka'),
('btn_save_visitor', 'cs', 'admin', 'visits', 'Uložit návštěvníka'),
('btn_back', 'cs', 'admin', 'visits', 'Zpět'),
-- Pole formuláře
('field_first_name', 'cs', 'admin', 'visits', 'Jméno'),
('field_last_name', 'cs', 'admin', 'visits', 'Příjmení'),
('field_email', 'cs', 'admin', 'visits', 'Email'),
('field_phone', 'cs', 'admin', 'visits', 'Telefon'),
('field_position', 'cs', 'admin', 'visits', 'Pozice / Funkce'),
-- Chybové hlášky
('error_required_fields', 'cs', 'admin', 'visits', 'Vyplňte povinná pole (jméno a příjmení).'),
('error_invalid_email', 'cs', 'admin', 'visits', 'Zadejte platný email.'),
('error_duplicate_email', 'cs', 'admin', 'visits', 'Návštěvník s tímto emailem již je v seznamu.'),
('confirm_delete_visitor', 'cs', 'admin', 'visits', 'Opravdu chcete odebrat tohoto návštěvníka?'),
-- Pluralizace
('person_singular', 'cs', 'admin', 'visits', 'návštěvník'),
('person_few', 'cs', 'admin', 'visits', 'návštěvníci'),
('person_many', 'cs', 'admin', 'visits', 'návštěvníků')
ON DUPLICATE KEY UPDATE translation_text = VALUES(translation_text);

