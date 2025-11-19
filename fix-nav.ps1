$content = Get-Content 'd:\Programming\WP-Plugins\SAW Visistors\saw-visitors-antigravity2\saw-visitors-claude1\assets\js\saw-app-navigation.js.backup' -Raw

# Fix indentation issue on lines 29-32
$content = $content -replace '(?m)^if \(\$link\.hasClass', '            if ($link.hasClass'
$content = $content -replace '(?m)^    console\.log', '                console.log'
$content = $content -replace '(?m)^    return;', '                return;'
$content = $content -replace '(?m)^}(?=\r?\n\s+const \$parentRow)', '            }'

# Add new checks at beginning of handler  
$oldPattern = '            // Skip related item links \(handled by sidebar\.js\)'
$newCode = @'
            // Skip AJAX-handled sidebar edit buttons
            if ($link.hasClass('saw-edit-ajax')) {
                console.log(' SPA: Skipping - AJAX edit button');
                return;
            }
            
            // Skip sidebar close buttons (handled by admin-table.js)
            if ($link.hasClass('saw-sidebar-close')) {
                console.log(' SPA: Skipping - Sidebar close button');
                return;
            }
            
            // Skip related item links (handled by sidebar.js)
'@

$content = $content -replace $oldPattern, $newCode

# Remove duplicate .saw-sidebar-close check
$content = $content -replace '(?m)^\s+if \(\$link\.hasClass\(''saw-sidebar-close''\)\) return;\r?\n', ''

$content | Set-Content 'd:\Programming\WP-Plugins\SAW Visistors\saw-visitors-antigravity2\saw-visitors-claude1\assets\js\saw-app-navigation.js' -NoNewline
Write-Host "File updated successfully"
