<?php
/**
 * Kompletní databáze jazyků pro training languages
 * Formát: 'code' => ['name' => 'Nativní název', 'name_cs' => 'Český název', 'flag' => 'emoji']
 */

return [
    // === PRIORITNÍ JAZYKY (EU + ČR okolí) ===
    'cs' => ['name' => 'Čeština', 'name_cs' => 'Čeština', 'flag' => '🇨🇿', 'priority' => 1],
    'sk' => ['name' => 'Slovenčina', 'name_cs' => 'Slovenština', 'flag' => '🇸🇰', 'priority' => 1],
    'en' => ['name' => 'English', 'name_cs' => 'Angličtina', 'flag' => '🇬🇧', 'priority' => 1],
    'de' => ['name' => 'Deutsch', 'name_cs' => 'Němčina', 'flag' => '🇩🇪', 'priority' => 1],
    'pl' => ['name' => 'Polski', 'name_cs' => 'Polština', 'flag' => '🇵🇱', 'priority' => 1],
    'uk' => ['name' => 'Українська', 'name_cs' => 'Ukrajinština', 'flag' => '🇺🇦', 'priority' => 1],
    'ru' => ['name' => 'Русский', 'name_cs' => 'Ruština', 'flag' => '🇷🇺', 'priority' => 1],
    
    // === ZÁPADNÍ EVROPA ===
    'fr' => ['name' => 'Français', 'name_cs' => 'Francouzština', 'flag' => '🇫🇷', 'priority' => 2],
    'es' => ['name' => 'Español', 'name_cs' => 'Španělština', 'flag' => '🇪🇸', 'priority' => 2],
    'it' => ['name' => 'Italiano', 'name_cs' => 'Italština', 'flag' => '🇮🇹', 'priority' => 2],
    'pt' => ['name' => 'Português', 'name_cs' => 'Portugalština', 'flag' => '🇵🇹', 'priority' => 2],
    'nl' => ['name' => 'Nederlands', 'name_cs' => 'Nizozemština', 'flag' => '🇳🇱', 'priority' => 2],
    
    // === STŘEDNÍ A VÝCHODNÍ EVROPA ===
    'hu' => ['name' => 'Magyar', 'name_cs' => 'Maďarština', 'flag' => '🇭🇺', 'priority' => 2],
    'ro' => ['name' => 'Română', 'name_cs' => 'Rumunština', 'flag' => '🇷🇴', 'priority' => 2],
    'bg' => ['name' => 'Български', 'name_cs' => 'Bulharština', 'flag' => '🇧🇬', 'priority' => 2],
    'hr' => ['name' => 'Hrvatski', 'name_cs' => 'Chorvatština', 'flag' => '🇭🇷', 'priority' => 2],
    'sr' => ['name' => 'Српски', 'name_cs' => 'Srbština', 'flag' => '🇷🇸', 'priority' => 2],
    'sl' => ['name' => 'Slovenščina', 'name_cs' => 'Slovinština', 'flag' => '🇸🇮', 'priority' => 2],
    
    // === SEVERNÍ EVROPA ===
    'sv' => ['name' => 'Svenska', 'name_cs' => 'Švédština', 'flag' => '🇸🇪', 'priority' => 3],
    'da' => ['name' => 'Dansk', 'name_cs' => 'Dánština', 'flag' => '🇩🇰', 'priority' => 3],
    'no' => ['name' => 'Norsk', 'name_cs' => 'Norština', 'flag' => '🇳🇴', 'priority' => 3],
    'fi' => ['name' => 'Suomi', 'name_cs' => 'Finština', 'flag' => '🇫🇮', 'priority' => 3],
    
    // === JIŽNÍ EVROPA ===
    'el' => ['name' => 'Ελληνικά', 'name_cs' => 'Řečtina', 'flag' => '🇬🇷', 'priority' => 3],
    'tr' => ['name' => 'Türkçe', 'name_cs' => 'Turečtina', 'flag' => '🇹🇷', 'priority' => 2],
    
    // === BALTSKÉ STÁTY ===
    'et' => ['name' => 'Eesti', 'name_cs' => 'Estonština', 'flag' => '🇪🇪', 'priority' => 3],
    'lv' => ['name' => 'Latviešu', 'name_cs' => 'Lotyština', 'flag' => '🇱🇻', 'priority' => 3],
    'lt' => ['name' => 'Lietuvių', 'name_cs' => 'Litevština', 'flag' => '🇱🇹', 'priority' => 3],
    
    // === ASIE (pracovníci v ČR) ===
    'vi' => ['name' => 'Tiếng Việt', 'name_cs' => 'Vietnamština', 'flag' => '🇻🇳', 'priority' => 2],
    'mn' => ['name' => 'Монгол', 'name_cs' => 'Mongolština', 'flag' => '🇲🇳', 'priority' => 2],
    'zh' => ['name' => '中文', 'name_cs' => 'Čínština', 'flag' => '🇨🇳', 'priority' => 2],
    'tl' => ['name' => 'Filipino', 'name_cs' => 'Filipínština', 'flag' => '🇵🇭', 'priority' => 2],
    'th' => ['name' => 'ไทย', 'name_cs' => 'Thajština', 'flag' => '🇹🇭', 'priority' => 3],
    'ko' => ['name' => '한국어', 'name_cs' => 'Korejština', 'flag' => '🇰🇷', 'priority' => 3],
    'ja' => ['name' => '日本語', 'name_cs' => 'Japonština', 'flag' => '🇯🇵', 'priority' => 3],
    
    // === DALŠÍ SVĚTOVÉ JAZYKY ===
    'ar' => ['name' => 'العربية', 'name_cs' => 'Arabština', 'flag' => '🇸🇦', 'priority' => 3],
    'he' => ['name' => 'עברית', 'name_cs' => 'Hebrejština', 'flag' => '🇮🇱', 'priority' => 4],
    'hi' => ['name' => 'हिन्दी', 'name_cs' => 'Hindština', 'flag' => '🇮🇳', 'priority' => 4],
    'id' => ['name' => 'Bahasa Indonesia', 'name_cs' => 'Indonéština', 'flag' => '🇮🇩', 'priority' => 4],
    'ms' => ['name' => 'Bahasa Melayu', 'name_cs' => 'Malajština', 'flag' => '🇲🇾', 'priority' => 4],
    'fa' => ['name' => 'فارسی', 'name_cs' => 'Perština', 'flag' => '🇮🇷', 'priority' => 4],
    'bn' => ['name' => 'বাংলা', 'name_cs' => 'Bengálština', 'flag' => '🇧🇩', 'priority' => 4],
    'ur' => ['name' => 'اردو', 'name_cs' => 'Urdština', 'flag' => '🇵🇰', 'priority' => 4],
    
    // === MALTA, IRSKO ===
    'mt' => ['name' => 'Malti', 'name_cs' => 'Maltština', 'flag' => '🇲🇹', 'priority' => 4],
    'ga' => ['name' => 'Gaeilge', 'name_cs' => 'Irština', 'flag' => '🇮🇪', 'priority' => 4],
    
    // === AFRIKA ===
    'sw' => ['name' => 'Kiswahili', 'name_cs' => 'Svahilština', 'flag' => '🇰🇪', 'priority' => 4],
    'am' => ['name' => 'አማርኛ', 'name_cs' => 'Amharština', 'flag' => '🇪🇹', 'priority' => 4],
    
    // === JIŽNÍ AMERIKA ===
    'pt-br' => ['name' => 'Português (Brasil)', 'name_cs' => 'Portugalština (Brazílie)', 'flag' => '🇧🇷', 'priority' => 4],
    
    // === CUSTOM - pro vlastní jazyky ===
    'other' => ['name' => 'Jiný jazyk', 'name_cs' => 'Jiný jazyk', 'flag' => '🌐', 'priority' => 99],
];