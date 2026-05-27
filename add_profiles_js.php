<?php
$path = "/var/www/html/resources/views/admin/profiles.blade.php";
$content = file_get_contents($path);

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

    let html = '<div class="form-row" style="flex-wrap: wrap; gap: 8px;">';
    for (let i = 0; i < copies; i++) {
        const val = existingValues[i] || '';
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
echo "JS added to profiles.blade.php";
