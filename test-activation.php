<?php
/**
 * Test aktivace SAW Visitors pluginu
 * 
 * Nahraj do: wp-content/plugins/saw-visitors/
 * Spusť: https://visitors.sawuh.cz/wp-content/plugins/saw-visitors/test-activation.php
 */

// Najít WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

header('Content-Type: text/plain; charset=utf-8');

echo "====================================\n";
echo "TEST AKTIVACE SAW VISITORS\n";
echo "====================================\n\n";

// Definovat konstanty
define('SAW_VISITORS_VERSION', '4.6.1');
define('SAW_VISITORS_PLUGIN_DIR', __DIR__ . '/');
define('SAW_VISITORS_PLUGIN_URL', plugins_url('/', __FILE__));
define('SAW_VISITORS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SAW_DB_PREFIX', 'saw_');
define('SAW_DEBUG', true);

echo "1. NAČTENÍ ACTIVATORU:\n";
$activator_path = SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';

if (!file_exists($activator_path)) {
    echo "   ✗ CHYBA: Soubor neexistuje!\n";
    echo "   Cesta: $activator_path\n";
    exit;
}

echo "   ✓ Soubor existuje\n";

try {
    require_once $activator_path;
    echo "   ✓ Soubor načten\n";
} catch (Exception $e) {
    echo "   ✗ CHYBA při načítání: " . $e->getMessage() . "\n";
    exit;
}

if (!class_exists('SAW_Activator')) {
    echo "   ✗ CHYBA: Třída SAW_Activator nebyla nalezena!\n";
    exit;
}

echo "   ✓ Třída SAW_Activator existuje\n\n";

echo "2. KONTROLA METOD:\n";
$methods = get_class_methods('SAW_Activator');
echo "   Nalezené metody: " . implode(', ', $methods) . "\n\n";

if (!method_exists('SAW_Activator', 'activate')) {
    echo "   ✗ CHYBA: Metoda activate() neexistuje!\n";
    exit;
}

echo "   ✓ Metoda activate() existuje\n\n";

echo "3. TEST SPUŠTĚNÍ AKTIVACE:\n";
echo "   Spouštím SAW_Activator::activate()...\n\n";

try {
    // Zachytit výstup
    ob_start();
    SAW_Activator::activate();
    $output = ob_get_clean();
    
    echo "   ✓ ÚSPĚCH! Aktivace proběhla bez chyby.\n\n";
    
    if (!empty($output)) {
        echo "   Výstup aktivace:\n";
        echo "   " . str_replace("\n", "\n   ", trim($output)) . "\n\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ CHYBA: " . $e->getMessage() . "\n";
    echo "   Soubor: " . $e->getFile() . "\n";
    echo "   Řádek: " . $e->getLine() . "\n\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n\n";
    exit;
} catch (Error $e) {
    ob_end_clean();
    echo "   ✗ PHP ERROR: " . $e->getMessage() . "\n";
    echo "   Soubor: " . $e->getFile() . "\n";
    echo "   Řádek: " . $e->getLine() . "\n\n";
    exit;
}

echo "4. KONTROLA DATABÁZE:\n";
global $wpdb;
$prefix = $wpdb->prefix . 'saw_';

// Zkontrolovat kolik tabulek bylo vytvořeno
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$prefix}%'", ARRAY_N);
$table_count = count($tables);

echo "   Nalezeno tabulek: $table_count\n";

if ($table_count > 0) {
    echo "   ✓ Databázové tabulky byly vytvořeny!\n\n";
    echo "   Seznam tabulek:\n";
    foreach ($tables as $table) {
        $table_name = str_replace($wpdb->prefix, '', $table[0]);
        echo "   - $table_name\n";
    }
} else {
    echo "   ⚠ VAROVÁNÍ: Žádné tabulky nebyly vytvořeny\n";
    echo "   Toto může být problém, ale nemusí (záleží na konfiguraci)\n";
}

echo "\n====================================\n";
echo "ZÁVĚR\n";
echo "====================================\n";
echo "✓ Activator lze spustit bez chyb\n";
echo "✓ Plugin by měl být připraven k aktivaci\n\n";

echo "DALŠÍ KROKY:\n";
echo "1. Jít do WordPress Admin → Pluginy\n";
echo "2. Aktivovat SAW Visitors\n";
echo "3. Pokud selže, poslat mi screenshot této stránky\n";
echo "====================================\n";