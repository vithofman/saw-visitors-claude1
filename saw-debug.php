<?php
/**
 * SAW VISITORS - DIAGNOSTICKÝ SCRIPT
 * 
 * Nahraj tento soubor na: /var/www/visitors.sawuh.cz/saw-debug.php
 * Pak otevři v prohlížeči: https://visitors.sawuh.cz/saw-debug.php
 * 
 * Ukáže přesně kde je problém!
 */

// Zapni zobrazování chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><meta charset='UTF-8'><title>SAW Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
echo ".ok{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}";
echo ".box{background:white;padding:15px;margin:10px 0;border-radius:5px;border:1px solid #ddd;}";
echo "h2{color:#1e40af;border-bottom:2px solid #1e40af;padding-bottom:5px;}";
echo "pre{background:#f9f9f9;padding:10px;overflow-x:auto;}</style></head><body>";

echo "<h1>🔍 SAW VISITORS - DIAGNOSTIKA</h1>";

// ==============================================
// KROK 1: WordPress načtení
// ==============================================
echo "<div class='box'><h2>KROK 1: WordPress</h2>";

$wp_load = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

if (!file_exists($wp_load)) {
    echo "<span class='error'>❌ CHYBA: wp-load.php nenalezen!</span><br>";
    echo "Hledal jsem v: <code>$wp_load</code><br>";
    echo "Tento script musí být v root složce WordPress!<br>";
    exit;
}

echo "<span class='ok'>✅ WordPress nalezen</span><br>";

require_once($wp_load);

echo "<span class='ok'>✅ WordPress načten</span><br>";
echo "WordPress verze: <strong>" . get_bloginfo('version') . "</strong><br>";
echo "Site URL: <strong>" . get_site_url() . "</strong><br>";
echo "</div>";

// ==============================================
// KROK 2: Databáze
// ==============================================
echo "<div class='box'><h2>KROK 2: Databáze</h2>";

global $wpdb;

$table_name = $wpdb->prefix . 'saw_customers';
echo "Název tabulky: <strong>$table_name</strong><br>";

// Ověř že tabulka existuje
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<span class='error'>❌ CHYBA: Tabulka $table_name NEEXISTUJE!</span><br>";
    exit;
}

echo "<span class='ok'>✅ Tabulka existuje</span><br>";

// Zjisti strukturu
$columns = $wpdb->get_results("DESCRIBE $table_name");

echo "<strong>Struktura tabulky:</strong><br>";
echo "<pre>";
foreach ($columns as $col) {
    echo "  • {$col->Field} ({$col->Type})" . ($col->Null === 'YES' ? ' NULL' : ' NOT NULL') . "\n";
}
echo "</pre>";

// Spočítej zákazníky
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<span class='ok'>✅ Počet zákazníků v DB: <strong>$count</strong></span><br>";

if ($count > 0) {
    $customers = $wpdb->get_results("SELECT id, name, ico FROM $table_name LIMIT 3", ARRAY_A);
    echo "<strong>První 3 zákazníci:</strong><br><pre>";
    print_r($customers);
    echo "</pre>";
}

echo "</div>";

// ==============================================
// KROK 3: Plugin SAW Visitors
// ==============================================
echo "<div class='box'><h2>KROK 3: Plugin SAW Visitors</h2>";

$plugin_dir = WP_PLUGIN_DIR . '/saw-visitors/';
echo "Plugin složka: <code>$plugin_dir</code><br>";

if (!file_exists($plugin_dir)) {
    echo "<span class='error'>❌ CHYBA: Plugin složka nenalezena!</span><br>";
    exit;
}

echo "<span class='ok'>✅ Plugin složka existuje</span><br>";

// Ověř hlavní soubory
$files_to_check = array(
    'saw-visitors.php' => 'Hlavní soubor pluginu',
    'includes/class-saw-visitors.php' => 'Hlavní třída',
    'includes/class-saw-router.php' => 'Router',
    'includes/models/class-saw-customer.php' => 'Customer Model',
    'includes/controllers/admin/class-saw-customers-controller.php' => 'Customers Controller',
    'includes/frontend/class-saw-app-layout.php' => 'App Layout',
);

foreach ($files_to_check as $file => $desc) {
    $full_path = $plugin_dir . $file;
    if (file_exists($full_path)) {
        echo "<span class='ok'>✅ $desc</span> <small>($file)</small><br>";
    } else {
        echo "<span class='error'>❌ CHYBÍ: $desc</span> <small>($file)</small><br>";
    }
}

echo "</div>";

// ==============================================
// KROK 4: Test načtení Customer Model
// ==============================================
echo "<div class='box'><h2>KROK 4: Test Customer Model</h2>";

$model_file = $plugin_dir . 'includes/models/class-saw-customer.php';

if (!file_exists($model_file)) {
    echo "<span class='error'>❌ Model soubor nenalezen!</span><br>";
} else {
    echo "<span class='ok'>✅ Model soubor existuje</span><br>";
    
    try {
        require_once($model_file);
        echo "<span class='ok'>✅ Model načten bez chyby</span><br>";
        
        if (class_exists('SAW_Customer')) {
            echo "<span class='ok'>✅ Třída SAW_Customer existuje</span><br>";
            
            // Zkus vytvořit instanci
            try {
                $customer_model = new SAW_Customer();
                echo "<span class='ok'>✅ Instance vytvořena</span><br>";
                
                // Zkus zavolat get_all()
                try {
                    $customers = $customer_model->get_all(array('limit' => 5));
                    echo "<span class='ok'>✅ Metoda get_all() funguje!</span><br>";
                    echo "Počet načtených zákazníků: <strong>" . count($customers) . "</strong><br>";
                    
                    if (!empty($customers)) {
                        echo "<strong>První zákazník:</strong><br><pre>";
                        print_r($customers[0]);
                        echo "</pre>";
                    }
                } catch (Exception $e) {
                    echo "<span class='error'>❌ CHYBA při volání get_all():</span><br>";
                    echo "<pre>" . $e->getMessage() . "</pre>";
                    echo "<pre>" . $e->getTraceAsString() . "</pre>";
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>❌ CHYBA při vytváření instance:</span><br>";
                echo "<pre>" . $e->getMessage() . "</pre>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        } else {
            echo "<span class='error'>❌ Třída SAW_Customer NEEXISTUJE!</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ CHYBA při načítání modelu:</span><br>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "</div>";

// ==============================================
// KROK 5: Test Controller
// ==============================================
echo "<div class='box'><h2>KROK 5: Test Customers Controller</h2>";

$controller_file = $plugin_dir . 'includes/controllers/admin/class-saw-customers-controller.php';

if (!file_exists($controller_file)) {
    echo "<span class='error'>❌ Controller soubor nenalezen!</span><br>";
} else {
    echo "<span class='ok'>✅ Controller soubor existuje</span><br>";
    
    try {
        require_once($controller_file);
        echo "<span class='ok'>✅ Controller načten bez chyby</span><br>";
        
        if (class_exists('SAW_Customers_Controller')) {
            echo "<span class='ok'>✅ Třída SAW_Customers_Controller existuje</span><br>";
            
            try {
                $controller = new SAW_Customers_Controller();
                echo "<span class='ok'>✅ Instance controlleru vytvořena</span><br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ CHYBA při vytváření controlleru:</span><br>";
                echo "<pre>" . $e->getMessage() . "</pre>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        } else {
            echo "<span class='error'>❌ Třída SAW_Customers_Controller NEEXISTUJE!</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ CHYBA při načítání controlleru:</span><br>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "</div>";

// ==============================================
// KROK 6: Konstanty pluginu
// ==============================================
echo "<div class='box'><h2>KROK 6: Plugin konstanty</h2>";

$constants = array(
    'SAW_VISITORS_VERSION',
    'SAW_VISITORS_PLUGIN_DIR',
    'SAW_VISITORS_PLUGIN_URL',
);

foreach ($constants as $const) {
    if (defined($const)) {
        echo "<span class='ok'>✅ $const</span> = <code>" . constant($const) . "</code><br>";
    } else {
        echo "<span class='error'>❌ $const NENÍ DEFINOVÁNA!</span><br>";
    }
}

echo "</div>";

// ==============================================
// ZÁVĚR
// ==============================================
echo "<div class='box' style='background:#e0f2fe;'><h2>📊 ZÁVĚR</h2>";
echo "<p><strong>Tento diagnostic script identifikoval přesně kde je problém.</strong></p>";
echo "<p>Pošli mi SCREENSHOT nebo ZKOPÍRUJ celý výstup této stránky.</p>";
echo "<p>Zejména hledej <span class='error'>ČERVENÉ ❌</span> části - tam je problém!</p>";
echo "</div>";

echo "</body></html>";
?>