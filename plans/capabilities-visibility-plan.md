# Capabilities Visibility Plan

## Problem

TrayPrint's "Refresh Capability" feature sends detailed printer capability data (paper sizes, trays, color modes, duplex support, resolutions) via [`POST /api/print-hub/status`](app/Http/Controllers/Api/PrintHubController.php:256), which stores it in the [`print_agents.capabilities`](database/migrations/2026_05_04_000006_add_capabilities_to_print_agents_table.php:18) JSON column. However, the data is effectively invisible — only [`$agent->capabilities['version']`](resources/views/admin/monitoring/index.blade.php:139) is displayed (version string in monitoring dashboard).

---

## Expected Capabilities Data Structure

Based on the [`PrintHubController@updateStatus`](app/Http/Controllers/Api/PrintHubController.php:261) validation and the migration comment, TrayPrint likely sends:

```json
{
  "version": "1.2.3",
  "printers": {
    "Brother-HL-L2360D": {
      "paper_sizes": ["A4", "A5", "Letter", "Legal"],
      "trays": ["tray1", "tray2", "manual"],
      "color_modes": ["color", "monochrome"],
      "resolutions": ["300", "600", "1200"],
      "duplex": true,
      "media_types": ["plain", "envelope", "label"]
    }
  }
}
```

The plan accounts for this structure but is resilient to missing/null capabilities.

---

## Implementation Plan

### Phase 1: Show Capabilities on Agents Page

**Files to modify:**
- [`app/Http/Controllers/Admin/AgentController.php`](app/Http/Controllers/Admin/AgentController.php) — No changes needed; `$agent->capabilities` is already cast to array by [`PrintAgent`](app/Models/PrintAgent.php:30)
- [`resources/views/admin/agents.blade.php`](resources/views/admin/agents.blade.php)

**Changes in [`agents.blade.php`](resources/views/admin/agents.blade.php):**

1. **Add "Capabilities" column header** after "Printers" (line 65)
   ```html
   <th>Capabilities</th>
   ```

2. **Add capabilities cell** after the printers cell (after line ~107)
   ```php
   <td style="font-size: 0.75rem;">
       @if($agent->capabilities && count($agent->capabilities) > 0)
           <div style="display: flex; flex-direction: column; gap: 3px;">
               @if(isset($agent->capabilities['version']))
                   <code style="font-size: 0.65rem;">v{{ $agent->capabilities['version'] }}</code>
               @endif
               @if(isset($agent->capabilities['printers']) && is_array($agent->capabilities['printers']))
                   @foreach($agent->capabilities['printers'] as $printerName => $printerCaps)
                       <div style="font-size: 0.65rem; color: var(--text-muted);">
                           <strong>{{ $printerName }}</strong>:
                           @if(!empty($printerCaps['paper_sizes']))
                               {{ implode(', ', $printerCaps['paper_sizes']) }}
                           @endif
                           @if(!empty($printerCaps['color_modes']))
                               · {{ implode('/', $printerCaps['color_modes']) }}
                           @endif
                           @if(!empty($printerCaps['duplex']))
                               · Duplex
                           @endif
                       </div>
                   @endforeach
               @endif
           </div>
       @else
           <span style="font-style: italic; color: var(--text-muted);">Not reported</span>
       @endif
   </td>
   ```

3. **Update colspan** on empty-state row (line 139) from `colspan="9"` to `colspan="10"`

---

### Phase 2: Enhance Monitoring Dashboard Capabilities Display

**File to modify:**
- [`resources/views/admin/monitoring/index.blade.php`](resources/views/admin/monitoring/index.blade.php)

**Changes:**

1. **Replace the version-only cell** (line 138-139) with a richer capabilities summary:
   ```php
   <td style="font-size: 0.75rem;">
       @if($agent->capabilities)
           <div>
               <code>v{{ $agent->capabilities['version'] ?? '?' }}</code>
               @if(!empty($agent->capabilities['printers']))
                   <br><span style="color: var(--text-muted);">
                   {{ count($agent->capabilities['printers']) }} printer(s) with capabilities
                   </span>
               @endif
           </div>
       @else
           <span style="color: var(--text-muted); font-style: italic;">No data</span>
       @endif
   </td>
   ```

2. **Add a "Capabilities" summary section** below the Version Distribution card (after line 241):
   - A new card showing aggregate capability stats across all agents
   - E.g., which paper sizes are most common, how many agents support duplex, etc.

---

### Phase 3: Use Capabilities in Profile Creation/Editing

**Files to modify:**
- [`resources/views/admin/profiles.blade.php`](resources/views/admin/profiles.blade.php) (create)
- [`resources/views/admin/edit_profile.blade.php`](resources/views/admin/edit_profile.blade.php) (edit)
- Both have identical `updatePrinterDropdown()` functions

**Changes in both views:**

1. **Pass capabilities data to the agent select** — currently `data-printers` is available, add `data-capabilities`:
   ```php
   <option value="{{ $agent->id }}"
       data-printers='{{ json_encode($agent->printers ?? []) }}'
       data-capabilities='{{ json_encode($agent->capabilities ?? []) }}'>
       {{ $agent->name }} {{ $agent->isOnline() ? '●' : '○' }}
   </option>
   ```

2. **Build `agentCapabilities` JS object** alongside existing `agentPrinters`:
   ```javascript
   const agentCapabilities = {
       @foreach($agents as $agent)
       "{{ $agent->id }}": {!! json_encode($agent->capabilities ?? []) !!},
       @endforeach
   };
   ```

3. **Create `updateAdvancedOptions(agentId)` function** that filters the advanced options (tray, color mode, quality, media type) based on the selected agent's capabilities:
   ```javascript
   function updateAdvancedOptions(agentId) {
       const caps = agentCapabilities[agentId];
       const printers = caps?.printers || {};
       // Collect all unique supported values across all printers
       const allPaperSizes = new Set();
       const allTrays = new Set();
       const allColorModes = new Set();
       const allResolutions = new Set();
       const allMediaTypes = new Set();
       let anyDuplex = false;
       
       Object.values(printers).forEach(p => {
           (p.paper_sizes || []).forEach(s => allPaperSizes.add(s));
           (p.trays || []).forEach(t => allTrays.add(t));
           (p.color_modes || []).forEach(c => allColorModes.add(c));
           (p.resolutions || []).forEach(r => allResolutions.add(r));
           (p.media_types || []).forEach(m => allMediaTypes.add(m));
           if (p.duplex) anyDuplex = true;
       });
       
       // Disable/enable or show/hide options based on capabilities
       // ...filter logic for each dropdown
   }
   ```

4. **Wire up the call** in the existing `updatePrinterDropdown` function (or as a separate call on agent change), so when an agent is selected, the advanced options are filtered.

5. **Show capability summary badge** next to the agent select so the admin can see at a glance what the agent supports.

---

### Phase 4: Backend Capability Validation in AgentSelectionService (Optional/Nice-to-have)

**File to modify:**
- [`app/Services/AgentSelectionService.php`](app/Services/AgentSelectionService.php)

Add an optional `$requirements` parameter to `select()`:
```php
public static function select(
    ?int $agentId,
    ?PrintProfile $profile,
    ?int $branchId,
    ?string $profileName = null,
    ?array $requirements = []  // e.g. ['paper_size' => 'A4', 'color_mode' => 'monochrome']
): PrintAgent
```

Before returning an agent, check if its `capabilities` satisfy the `$requirements`. This enables future smart routing where jobs are only sent to agents whose printers can handle them.

---

## Data Flow Diagram

```mermaid
flowchart TB
    subgraph TrayPrint["TrayPrint Agent"]
        A[Scan local printers] --> B[Build capabilities:\npaper_sizes, trays,\ncolor_modes, duplex,\nresolutions]
        B --> C[POST /api/print-hub/status\n{printers, capabilities}]
    end

    subgraph Backend["Print Hub Backend"]
        C --> D[PrintHubController@updateStatus]
        D --> E[(print_agents.capabilities\nJSON column)]
        E --> F[PrintAgent model\ncasts capabilities to array]
    end

    subgraph UI["Admin UI"]
        E --> G[Phase 1: Agents page\nshow capabilities per printer]
        E --> H[Phase 2: Monitoring dashboard\nenhanced capability display]
        E --> I[Phase 3: Profile create/edit\nfilter options by capabilities]
        I --> J[AgentSelectionService\nPhase 4: capability-aware routing]
    end
```

---

## Implementation Order

| Step | File(s) | Description | Effort |
|------|---------|-------------|--------|
| 1 | [`agents.blade.php`](resources/views/admin/agents.blade.php) | Add Capabilities column with per-printer details | Small |
| 2 | [`monitoring/index.blade.php`](resources/views/admin/monitoring/index.blade.php) | Enhance version cell, add capability summary section | Small |
| 3 | [`profiles.blade.php`](resources/views/admin/profiles.blade.php) | Pass capabilities to JS, filter advanced printer options | Medium |
| 4 | [`edit_profile.blade.php`](resources/views/admin/edit_profile.blade.php) | Same filtering logic as step 3 (duplicate changes) | Medium |
| 5 | [`AgentSelectionService.php`](app/Services/AgentSelectionService.php) | Add capability-aware routing (optional enhancement) | Small |

---

## Why This Plan Is Safe

1. **The capabilities column already exists** in the database — we're only changing how it's displayed
2. **The PrintAgent model already casts capabilities to array** — no model changes needed
3. **AgentSelectionService changes are optional** and backward-compatible
4. **All backend validation rules** in [`ProfileController`](app/Http/Controllers/Admin/ProfileController.php) remain unchanged — we're only filtering UI options, not enforcing them server-side
5. **Null-safe access patterns** — all capability access uses `isset()` checks with fallbacks
