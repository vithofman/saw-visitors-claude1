<?php
if (!defined('ABSPATH')) exit;

$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
$has_departments = !empty($departments);

$t = array(
    'title' => 'Specifika oddělení',
    'confirm' => 'Potvrzuji, že jsem si přečetl/a všechny informace',
    'continue' => 'Pokračovat'
);
?>

<div style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:#f7fafc;z-index:9999">
    
    <div style="background:white;border-bottom:2px solid #e2e8f0;padding:1.5rem 2rem">
        <h2 style="margin:0;font-size:1.75rem;color:#2d3748;font-weight:700">
            🏭 <?php echo esc_html($t['title']); ?>
        </h2>
    </div>
    
    <?php if (!$has_departments): ?>
        <form method="POST" style="padding:2rem">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_department">
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
    <?php else: ?>
        
        <div style="height:calc(100vh - 120px);overflow-y:auto;padding:2rem">
            <div style="max-width:1400px;margin:0 auto">
                
                <?php foreach ($departments as $index => $dept): ?>
                    <?php $dept_id = 'dept-' . $index; ?>
                    
                    <div style="background:white;border:2px solid #e2e8f0;border-radius:12px;margin-bottom:1rem">
                        
                        <button type="button" 
                                onclick="toggleDept('<?php echo $dept_id; ?>')" 
                                style="width:100%;padding:1.5rem 2rem;background:#f7fafc;border:none;display:flex;justify-content:space-between;cursor:pointer">
                            <div style="display:flex;gap:1rem">
                                <span id="icon-<?php echo $dept_id; ?>" style="font-size:1.5rem;transition:transform 0.3s">▶</span>
                                <h3 style="margin:0;font-size:1.25rem;color:#2d3748">
                                    <?php echo esc_html($dept['department_name']); ?>
                                </h3>
                            </div>
                            <?php if (!empty($dept['documents'])): ?>
                                <span style="padding:0.25rem 0.75rem;background:#dbeafe;color:#1e40af;border-radius:999px;font-size:0.875rem">
                                    📄 <?php echo count($dept['documents']); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <div id="<?php echo $dept_id; ?>" style="display:none">
                            <div style="display:flex">
                                
                                <div style="flex:1;padding:2rem;border-right:2px solid #e2e8f0">
                                    <?php if (!empty($dept['text_content'])): ?>
                                        <div style="color:#2d3748;line-height:1.8">
                                            <?php echo wp_kses_post($dept['text_content']); ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="color:#a0aec0;font-style:italic">Žádný obsah</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="width:350px;padding:2rem;background:#f7fafc">
                                    <h4 style="margin:0 0 1rem 0;font-size:1rem;color:#4a5568">📄 Dokumenty</h4>
                                    
                                    <?php if (empty($dept['documents'])): ?>
                                        <p style="color:#a0aec0;font-style:italic">Žádné dokumenty</p>
                                    <?php else: ?>
                                        <?php foreach ($dept['documents'] as $doc): ?>
                                            <a href="<?php echo esc_url(content_url() . '/uploads' . $doc['file_path']); ?>" 
                                               target="_blank"
                                               style="display:block;padding:1rem;background:white;border:2px solid #e2e8f0;border-radius:8px;margin-bottom:0.75rem;text-decoration:none;color:inherit">
                                                <div style="display:flex;align-items:center;gap:0.75rem">
                                                    <span style="font-size:2rem">📄</span>
                                                    <div style="flex:1;min-width:0">
                                                        <div style="font-weight:600;color:#2d3748;font-size:0.875rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                                            <?php echo esc_html($doc['file_name']); ?>
                                                        </div>
                                                        <div style="font-size:0.75rem;color:#718096">
                                                            <?php echo size_format($doc['file_size']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
                
            </div>
        </div>
        
        <div style="position:absolute;bottom:0;left:0;right:0;background:white;border-top:2px solid #e2e8f0;padding:1.5rem 2rem">
            <form method="POST" style="max-width:1400px;margin:0 auto;display:flex;gap:2rem">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="complete_training_department">
                
                <label style="flex:1;display:flex;gap:1rem;padding:1rem;background:#fef3c7;border:2px solid #fbbf24;border-radius:8px;cursor:pointer">
                    <input type="checkbox" 
                           id="dept-confirm" 
                           style="width:24px;height:24px" 
                           required>
                    <span style="color:#92400e;font-weight:600">
                        <?php echo esc_html($t['confirm']); ?>
                    </span>
                </label>
                
                <button type="submit" 
                        id="continue-btn" 
                        class="saw-terminal-btn saw-terminal-btn-success" 
                        style="opacity:0.5;width:auto;padding:1rem 3rem" 
                        disabled>
                    <?php echo esc_html($t['continue']); ?> →
                </button>
            </form>
        </div>
        
    <?php endif; ?>
    
</div>

<script>
function toggleDept(id) {
    var content = document.getElementById(id);
    var icon = document.getElementById('icon-' + id);
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(90deg)';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

jQuery(document).ready(function($) {
    $('#dept-confirm').on('change', function() {
        var checked = $(this).is(':checked');
        $('#continue-btn')
            .prop('disabled', !checked)
            .css('opacity', checked ? '1' : '0.5');
    });
});
</script>