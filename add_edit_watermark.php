<?php
$path = "/var/www/html/resources/views/admin/edit_profile.blade.php";
$content = file_get_contents($path);

// Add per-copy watermark section after the watermark preview div
$search = '            <div id="watermark-preview" style="margin-top: 0.75rem; padding: 1rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border); min-height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">Preview will appear here</span>
            </div>
        </fieldset>';

$replacement = '            <div id="watermark-preview" style="margin-top: 0.75rem; padding: 1rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border); min-height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">Preview will appear here</span>
            </div>

            {{-- Per-Copy Watermark Texts --}}
            <div id="per-copy-watermark-section" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border); display: {{ old(\'copies\', $profile->copies) > 1 ? \'block\' : \'none\' }};">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">📋 Per-Copy Watermark Texts</div>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 0.75rem;">
                    When copies > 1, you can specify a different watermark text for each copy.
                    Leave empty to use the single watermark text above for all copies.
                </p>
                <div id="copy-watermark-inputs"></div>
            </div>
        </fieldset>';

$content = str_replace($search, $replacement, $content);

// Now add the JavaScript for dynamic per-copy watermark inputs
$jsCode = <<<'JS'

// ── Per-Copy Watermark UI ─────────────────────────────────────
function initPerCopyWatermark() {
    const copiesInput = document.getElementById('copies');
    if (!copiesInput) return;

    copiesInput.addEventListener('input', updateCopyWatermarkInputs);
    copiesInput.addEventListener('change', updateCopyWatermarkInputs);
    updateCopyWatermarkInputs();
}

function updateCopyWatermarkInputs() {
    const copies = parseInt(document.getElementById('copies')?.value || 1);
    const section = document.getElementById('per-copy-watermark-section');
    const container = document.getElementById('copy-watermark-inputs');

    if (!section || !container) return;

    if (copies <= 1) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';

    // Preserve existing values
    const existingInputs = container.querySelectorAll('input');
    const existingValues = {};
    existingInputs.forEach(inp => {
        const match = inp.name.match(/watermark_copy_texts\[(\d+)\]/);
        if (match) existingValues[parseInt(match[1])] = inp.value;
    });

    // Get saved values from PHP (for initial load)
    const savedValues = window._savedWatermarkCopyTexts || {};

    let html = '<div class="form-row" style="flex-wrap: wrap; gap: 8px;">';
    for (let i = 0; i < copies; i++) {
        const val = existingValues[i] || savedValues[i] || '';
        const label = 'Copy ' + (i + 1);
        html += '<div class="form-group" style="flex: 1; min-width: 150px;">';
        html += '<label style="font-size: 0.75rem;">' + label + '</label>';
        html += '<input type="text" name="watermark_copy_texts[' + i + ']" value="' + val.replace(/"/g, '"') + '" placeholder="e.g. Customer Copy" style="font-size: 0.8rem;">';
        html += '</div>';
    }
    html += '</div>';
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', initPerCopyWatermark);
JS;

// Insert before the closing </script> tag
$content = str_replace("</script>", $jsCode . "\n</script>", $content);

file_put_contents($path, $content);
echo "Edit profile watermark section updated";
