<?php
/**
 * SAW VISITORS PLUGIN - DEBUG SCRIPT
 * 
 * Tento script zjistí přesně co brání aktivaci pluginu.
 * 
 * POUŽITÍ:
 * 1. Nahrát tento soubor do složky pluginu: wp-content/plugins/saw-visitors/
 * 2. Spustit v prohlížeči: https://vase-domena.cz/wp-content/plugins/saw-visitors/debug-plugin.php
 * 3. Přečíst výstup
 */

// Detekovat cestu k WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SAW Visitors - Debug Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .success { color: #059669; font-weight: 600; }
        .error { color: #dc2626; font-weight: 600; }
        .warning { color: #d97706; font-weight: 600; }
        .info { color: #2563eb; font-weight: 600; }
        .code { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; margin: 10px 0; }
        .file-path { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f8fafc; text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .check-ok { color: #059669; }
        .check-fail { color: #dc2626; }
        h2 { color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        h3 { color: #475569; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>

<div class="header">
    <h1>🔍 SAW Visitors Plugin - Diagnostický Report</h1>
    <p>Verze: 4.6.1 | Datum: <?php echo date('d.m.Y H:i:s'); ?></p>
</div>

<?php
// Konstanty
define('SAW_PLUGIN_DIR', __DIR__ . '/');
define('SAW_VISITORS_PLUGIN_DIR', SAW_PLUGIN_DIR);

$errors = [];
$warnings = [];
$info = [];

// =============================================================================
// SEKCE 1: ZÁKLADNÍ KONTROLY
// =============================================================================
?>

<div class="section">
    <h2>📍 1. Základní kontroly prostředí</h2>
    
    <table>
        <tr>
            <th width="40%">Kontrola</th>
            <th width="40%">Výsledek</th>
            <th width="20%">Status</th>
        </tr>
        
        <!-- PHP Verze -->
        <tr>
            <td>PHP Verze</td>
            <td><span class="file-path"><?php echo PHP_VERSION; ?></span></td>
            <td>
                <?php if (version_compare(PHP_VERSION, '8.1.0', '>=')): ?>
                    <span class="check-ok">✓ OK (požadováno 8.1+)</span>
                <?php else: ?>
                    <span class="check-fail">✗ CHYBA (požadováno 8.1+)</span>
                    <?php $errors[] = "PHP verze je příliš stará: " . PHP_VERSION; ?>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- WordPress -->
        <tr>
            <td>WordPress</td>
            <td>
                <?php if ($wp_loaded): ?>
                    <span class="file-path"><?php echo get_bloginfo('version'); ?></span>
                <?php else: ?>
                    <span class="file-path">Nepodařilo se načíst</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($wp_loaded): ?>
                    <?php $wp_version = get_bloginfo('version'); ?>
                    <?php if (version_compare($wp_version, '6.0', '>=')): ?>
                        <span class="check-ok">✓ OK (požadováno 6.0+)</span>
                    <?php else: ?>
                        <span class="check-fail">✗ CHYBA (požadováno 6.0+)</span>
                        <?php $errors[] = "WordPress verze je příliš stará: " . $wp_version; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="warning">⚠ Varování (nelze ověřit)</span>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Složka pluginu -->
        <tr>
            <td>Složka pluginu</td>
            <td><span class="file-path"><?php echo SAW_PLUGIN_DIR; ?></span></td>
            <td>
                <?php if (is_dir(SAW_PLUGIN_DIR)): ?>
                    <span class="check-ok">✓ Existuje</span>
                <?php else: ?>
                    <span class="check-fail">✗ Neexistuje</span>
                    <?php $errors[] = "Složka pluginu neexistuje"; ?>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Práva zápisu -->
        <tr>
            <td>Práva k zápisu</td>
            <td><span class="file-path"><?php echo substr(sprintf('%o', fileperms(SAW_PLUGIN_DIR)), -4); ?></span></td>
            <td>
                <?php if (is_writable(SAW_PLUGIN_DIR)): ?>
                    <span class="check-ok">✓ Zapisovatelné</span>
                <?php else: ?>
                    <span class="warning">⚠ Pouze pro čtení</span>
                    <?php $warnings[] = "Složka pluginu není zapisovatelná"; ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<?php
// =============================================================================
// SEKCE 2: KONTROLA KRITICKÝCH SOUBORŮ
// =============================================================================
?>

<div class="section">
    <h2>📄 2. Kontrola kritických souborů</h2>
    
    <?php
    $critical_files = [
        'Hlavní soubor' => 'saw-visitors.php',
        'Uninstall script' => 'uninstall.php',
        'Activator' => 'includes/class-saw-activator.php',
        'Deactivator' => 'includes/class-saw-deactivator.php',
        'Hlavní třída' => 'includes/core/class-saw-visitors.php',
        'Loader' => 'includes/core/class-saw-loader.php',
        'Auth' => 'includes/core/class-saw-auth.php',
        'Router' => 'includes/core/class-saw-router.php',
        'Database' => 'includes/database/class-saw-database.php',
    ];
    ?>
    
    <table>
        <tr>
            <th width="30%">Soubor</th>
            <th width="50%">Cesta</th>
            <th width="20%">Status</th>
        </tr>
        
        <?php foreach ($critical_files as $name => $file): ?>
            <?php $full_path = SAW_PLUGIN_DIR . $file; ?>
            <tr>
                <td><?php echo $name; ?></td>
                <td><span class="file-path"><?php echo $file; ?></span></td>
                <td>
                    <?php if (file_exists($full_path)): ?>
                        <span class="check-ok">✓ Existuje</span>
                    <?php else: ?>
                        <span class="check-fail">✗ Chybí</span>
                        <?php $errors[] = "Chybějící soubor: " . $file; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
// =============================================================================
// SEKCE 3: ANALÝZA HLAVNÍHO SOUBORU
// =============================================================================
?>

<div class="section">
    <h2>🔬 3. Analýza saw-visitors.php</h2>
    
    <?php
    $main_file = SAW_PLUGIN_DIR . 'saw-visitors.php';
    $main_file_content = file_exists($main_file) ? file_get_contents($main_file) : '';
    ?>
    
    <?php if ($main_file_content): ?>
        
        <h3>Kontrola cest k activatoru/deactivatoru:</h3>
        
        <table>
            <tr>
                <th width="30%">Kontrola</th>
                <th width="50%">Nalezená cesta</th>
                <th width="20%">Status</th>
            </tr>
            
            <?php
            // Kontrola activator cesty
            preg_match("/require_once.*?['\"](.+?class-saw-activator\.php)['\"]/", $main_file_content, $activator_match);
            $activator_path = isset($activator_match[1]) ? $activator_match[1] : 'Nenalezeno';
            $activator_ok = strpos($activator_path, 'includes/class-saw-activator.php') !== false;
            ?>
            <tr>
                <td>Cesta k activatoru</td>
                <td><span class="file-path"><?php echo htmlspecialchars($activator_path); ?></span></td>
                <td>
                    <?php if ($activator_ok): ?>
                        <span class="check-ok">✓ Správná</span>
                    <?php else: ?>
                        <span class="check-fail">✗ CHYBNÁ!</span>
                        <?php $errors[] = "Chybná cesta k activatoru: " . $activator_path; ?>
                    <?php endif; ?>
                </td>
            </tr>
            
            <?php
            // Kontrola deactivator cesty
            preg_match("/require_once.*?['\"](.+?class-saw-deactivator\.php)['\"]/", $main_file_content, $deactivator_match);
            $deactivator_path = isset($deactivator_match[1]) ? $deactivator_match[1] : 'Nenalezeno';
            $deactivator_ok = strpos($deactivator_path, 'includes/class-saw-deactivator.php') !== false;
            ?>
            <tr>
                <td>Cesta k deactivatoru</td>
                <td><span class="file-path"><?php echo htmlspecialchars($deactivator_path); ?></span></td>
                <td>
                    <?php if ($deactivator_ok): ?>
                        <span class="check-ok">✓ Správná</span>
                    <?php else: ?>
                        <span class="check-fail">✗ CHYBNÁ!</span>
                        <?php $errors[] = "Chybná cesta k deactivatoru: " . $deactivator_path; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php if (!$activator_ok || !$deactivator_ok): ?>
            <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin-top: 20px;">
                <h4 style="color: #dc2626; margin-top: 0;">🚨 KRITICKÁ CHYBA NALEZENA!</h4>
                <p>Soubor <span class="file-path">saw-visitors.php</span> obsahuje <strong>chybné cesty</strong>.</p>
                
                <h4>Co je špatně:</h4>
                <div class="code">
<?php if (!$activator_ok): ?>
❌ Řádek ~53: require_once ... '<?php echo htmlspecialchars($activator_path); ?>';
<?php endif; ?>
<?php if (!$deactivator_ok): ?>
❌ Řádek ~62: require_once ... '<?php echo htmlspecialchars($deactivator_path); ?>';
<?php endif; ?>
                </div>
                
                <h4>Mělo by být:</h4>
                <div class="code">
✅ Řádek ~53: require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';
✅ Řádek ~62: require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-deactivator.php';
                </div>
                
                <h4>Řešení:</h4>
                <p>1. Stáhnout opravený soubor <span class="file-path">saw-visitors-FIXED.php</span></p>
                <p>2. Nahradit aktuální <span class="file-path">saw-visitors.php</span></p>
                <p>3. Zkusit aktivaci znovu</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div style="background: #fef2f2; padding: 15px; border-radius: 5px;">
            <span class="error">✗ Nepodařilo se načíst soubor saw-visitors.php</span>
        </div>
    <?php endif; ?>
</div>

<?php
// =============================================================================
// SEKCE 4: KONTROLA STRUKTURY SLOŽEK
// =============================================================================
?>

<div class="section">
    <h2>📁 4. Kontrola struktury složek</h2>
    
    <?php
    $required_dirs = [
        'includes/',
        'includes/core/',
        'includes/database/',
        'includes/database/schemas/',
        'includes/components/',
        'includes/controllers/',
        'includes/models/',
        'templates/',
        'templates/components/',
        'templates/pages/',
        'assets/',
        'assets/css/',
        'assets/js/',
    ];
    ?>
    
    <table>
        <tr>
            <th width="60%">Složka</th>
            <th width="20%">Souborů</th>
            <th width="20%">Status</th>
        </tr>
        
        <?php foreach ($required_dirs as $dir): ?>
            <?php 
            $full_dir = SAW_PLUGIN_DIR . $dir;
            $file_count = is_dir($full_dir) ? count(glob($full_dir . '*')) : 0;
            ?>
            <tr>
                <td><span class="file-path"><?php echo $dir; ?></span></td>
                <td><?php echo $file_count; ?></td>
                <td>
                    <?php if (is_dir($full_dir)): ?>
                        <span class="check-ok">✓ OK</span>
                    <?php else: ?>
                        <span class="check-fail">✗ Chybí</span>
                        <?php $errors[] = "Chybějící složka: " . $dir; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
// =============================================================================
// SEKCE 5: TEST NAČTENÍ TŘÍD
// =============================================================================
?>

<div class="section">
    <h2>🧪 5. Test načtení PHP tříd</h2>
    
    <?php
    // Definovat konstanty pro test
    if (!defined('SAW_VISITORS_VERSION')) define('SAW_VISITORS_VERSION', '4.6.1');
    if (!defined('ABSPATH')) define('ABSPATH', dirname(SAW_PLUGIN_DIR, 4) . '/');
    
    $test_classes = [
        'SAW_Activator' => 'includes/class-saw-activator.php',
        'SAW_Deactivator' => 'includes/class-saw-deactivator.php',
        'SAW_Loader' => 'includes/core/class-saw-loader.php',
        'SAW_Database' => 'includes/database/class-saw-database.php',
    ];
    ?>
    
    <table>
        <tr>
            <th width="30%">Třída</th>
            <th width="50%">Soubor</th>
            <th width="20%">Status</th>
        </tr>
        
        <?php foreach ($test_classes as $class_name => $file): ?>
            <?php 
            $full_path = SAW_PLUGIN_DIR . $file;
            $can_load = false;
            $error_msg = '';
            
            if (file_exists($full_path)) {
                try {
                    require_once $full_path;
                    $can_load = class_exists($class_name);
                    if (!$can_load) {
                        $error_msg = "Třída $class_name nebyla nalezena v souboru";
                    }
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                } catch (Error $e) {
                    $error_msg = $e->getMessage();
                }
            } else {
                $error_msg = "Soubor neexistuje";
            }
            ?>
            <tr>
                <td><span class="file-path"><?php echo $class_name; ?></span></td>
                <td><span class="file-path"><?php echo $file; ?></span></td>
                <td>
                    <?php if ($can_load): ?>
                        <span class="check-ok">✓ OK</span>
                    <?php else: ?>
                        <span class="check-fail">✗ Chyba</span>
                        <?php if ($error_msg): ?>
                            <br><small><?php echo htmlspecialchars($error_msg); ?></small>
                        <?php endif; ?>
                        <?php $errors[] = "Nelze načíst třídu $class_name: " . $error_msg; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
// =============================================================================
// SEKCE 6: DATABÁZOVÉ SCHÉMA
// =============================================================================
?>

<div class="section">
    <h2>🗄️ 6. Kontrola databázových schémat</h2>
    
    <?php
    $schema_dir = SAW_PLUGIN_DIR . 'includes/database/schemas/';
    $schema_files = is_dir($schema_dir) ? glob($schema_dir . 'schema-*.php') : [];
    ?>
    
    <p>
        <strong>Složka:</strong> <span class="file-path"><?php echo $schema_dir; ?></span><br>
        <strong>Nalezeno souborů:</strong> <?php echo count($schema_files); ?> / 33 očekávaných
    </p>
    
    <?php if (count($schema_files) < 33): ?>
        <div style="background: #fffbeb; border-left: 4px solid #d97706; padding: 15px;">
            <span class="warning">⚠ Varování:</span> Očekáváno 33 schema souborů, nalezeno pouze <?php echo count($schema_files); ?>
        </div>
    <?php else: ?>
        <div style="background: #d1fae5; padding: 10px; border-radius: 5px;">
            <span class="check-ok">✓ Všechna schémata jsou přítomna</span>
        </div>
    <?php endif; ?>
</div>

<?php
// =============================================================================
// SEKCE 7: FINÁLNÍ SHRNUTÍ
// =============================================================================
?>

<div class="section">
    <h2>📊 7. Finální shrnutí</h2>
    
    <h3>Statistiky:</h3>
    <table>
        <tr>
            <td width="30%"><strong>Kritické chyby:</strong></td>
            <td><span class="<?php echo count($errors) > 0 ? 'error' : 'success'; ?>">
                <?php echo count($errors); ?>
            </span></td>
        </tr>
        <tr>
            <td><strong>Varování:</strong></td>
            <td><span class="<?php echo count($warnings) > 0 ? 'warning' : 'success'; ?>">
                <?php echo count($warnings); ?>
            </span></td>
        </tr>
        <tr>
            <td><strong>PHP Verze:</strong></td>
            <td><?php echo PHP_VERSION; ?></td>
        </tr>
        <?php if ($wp_loaded): ?>
        <tr>
            <td><strong>WordPress Verze:</strong></td>
            <td><?php echo get_bloginfo('version'); ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <?php if (count($errors) > 0): ?>
        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 20px; margin-top: 20px;">
            <h3 style="color: #dc2626; margin-top: 0;">🚨 Nalezené kritické chyby:</h3>
            <ol style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li style="margin: 8px 0;"><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>
    
    <?php if (count($warnings) > 0): ?>
        <div style="background: #fffbeb; border-left: 4px solid #d97706; padding: 20px; margin-top: 20px;">
            <h3 style="color: #d97706; margin-top: 0;">⚠️ Varování:</h3>
            <ol style="margin: 0; padding-left: 20px;">
                <?php foreach ($warnings as $warning): ?>
                    <li style="margin: 8px 0;"><?php echo htmlspecialchars($warning); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>
    
    <?php if (count($errors) === 0 && count($warnings) === 0): ?>
        <div style="background: #d1fae5; padding: 20px; border-radius: 10px; text-align: center; margin-top: 20px;">
            <h3 style="color: #059669; margin: 0;">✅ Vše vypadá v pořádku!</h3>
            <p>Plugin by měl být připraven k aktivaci.</p>
        </div>
    <?php endif; ?>
</div>

<div class="section" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <p style="margin: 0;">Vytvořeno: <?php echo date('d.m.Y H:i:s'); ?> | SAW Visitors v4.6.1 Debug Tool</p>
</div>

</body>
</html>