# Windows Installer & Scheduled Task Plan for TrayPrint

## Current State

The project already has:
- [`build.py`](/home/yudi/dev/trayprint/build.py) — PyInstaller build script that produces `dist/trayprint.exe`
- [`service.py`](/home/yudi/dev/trayprint/service.py) — Windows Service wrapper using pywin32 (`win32serviceutil.ServiceFramework`)
- [`installer/trayprint.wxs`](/home/yudi/dev/trayprint/installer/trayprint.wxs) — WiX v3.x MSI definition (per-machine, 64-bit)
- [`installer/product.wxs`](/home/yudi/dev/trayprint/installer/product.wxs) — Alternative WiX definition
- [`installer/build_msi.bat`](/home/yudi/dev/trayprint/installer/build_msi.bat) — Batch script to compile WiX → MSI
- [`installer/build_msi.py`](/home/yudi/dev/trayprint/installer/build_msi.py) — Python script supporting cx_Freeze or PyInstaller+WiX
- [`app.py`](/home/yudi/dev/trayprint/app.py) — Existing `install_windows_service()` using nssm (third-party dependency)

## The Problem

The current approach has several issues:
1. **nssm dependency** — `install_windows_service()` requires downloading nssm.exe separately
2. **HKCU Run registry** — Current WiX uses `HKCU\...\Run` for auto-start, which runs as the **logged-in user**, not as Administrator
3. **No elevated privileges** — The tray app runs with user-level permissions, can't install printers or access system resources
4. **No Windows Scheduled Task** — The user wants the app to run as Administrator at system startup via Task Scheduler

## Requirements

1. Build a proper Windows **MSI installer** (already partially done with WiX)
2. The installer should **create a Scheduled Task** that runs `trayprint.exe --silent` at system startup
3. The Scheduled Task should run with **Administrator privileges** (highest available)
4. The installer should also offer the option to install as a **Windows Service** (via pywin32, already exists in `service.py`)
5. The installer should handle **upgrades** (already has MajorUpgrade in WiX)

---

## Architecture Decision: Two Deployment Modes

The user wants the app to run as Administrator at startup. There are two approaches:

### Option A: Windows Service (Recommended for headless/server)
- Uses existing [`service.py`](/home/yudi/dev/trayprint/service.py) with pywin32
- Runs as `NT AUTHORITY\SYSTEM` (highest privilege)
- No tray icon (headless)
- Managed via `services.msc`
- **Already implemented** — just needs to be wired into the installer

### Option B: Scheduled Task (Recommended for interactive + admin)
- Uses `schtasks.exe` to create a task that runs at system startup
- Runs as `SYSTEM` or specified admin account
- Can be configured to run whether user is logged in or not
- Can show tray icon if "Run only when user is logged on" is selected
- **This is what the user specifically asked for**

### Recommended: Hybrid Approach
The installer should offer **both** options via a custom dialog:

```
┌─────────────────────────────────────┐
│  TrayPrint Setup                    │
│                                     │
│  Choose startup mode:               │
│                                     │
│  ○ Windows Service (headless)       │
│  ● Scheduled Task (with tray icon)  │
│                                     │
│  [< Back]  [Install]  [Cancel]      │
└─────────────────────────────────────┘
```

---

## Implementation Plan

### Step 1: Create a PowerShell Script for Scheduled Task Management

**File:** [`/home/yudi/dev/trayprint/installer/Register-TrayPrintTask.ps1`](/home/yudi/dev/trayprint/installer/Register-TrayPrintTask.ps1)

This script will:
- Create a Scheduled Task named `TrayPrintAgent`
- Set it to run at system startup (`ONSTART`)
- Run with `SYSTEM` account (highest privileges)
- Execute `trayprint.exe --silent`
- Set working directory to the install folder
- Configure restart on failure (3 attempts, 1 minute apart)

```powershell
param(
    [Parameter(Mandatory=$true)]
    [string]$InstallPath,
    [switch]$Unregister
)

$taskName = "TrayPrintAgent"
$action = New-ScheduledTaskAction `
    -Execute "$InstallPath\trayprint.exe" `
    -Argument "--silent" `
    -WorkingDirectory $InstallPath

$trigger = New-ScheduledTaskTrigger -AtStartup

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1)

$principal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

if ($Unregister) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    return
}

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Force
```

### Step 2: Create a PowerShell Script for Uninstall

**File:** [`/home/yudi/dev/trayprint/installer/Unregister-TrayPrintTask.ps1`](/home/yudi/dev/trayprint/installer/Unregister-TrayPrintTask.ps1)

```powershell
param(
    [string]$TaskName = "TrayPrintAgent"
)
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
```

### Step 3: Update WiX Installer Definition

**File:** [`/home/yudi/dev/trayprint/installer/product.wxs`](/home/yudi/dev/trayprint/installer/product.wxs)

Changes needed:
1. **Replace** `HKCU\...\Run` auto-start with Scheduled Task registration
2. **Add** `Register-TrayPrintTask.ps1` and `Unregister-TrayPrintTask.ps1` as binaries
3. **Add** Custom Actions to run PowerShell scripts during install/uninstall
4. **Keep** the existing file components (exe, config, shortcuts)
5. **Add** WiX `Property` to require PowerShell execution policy bypass

Key WiX additions:

```xml
<!-- PowerShell scripts bundled with installer -->
<Component Id="C_RegisterTaskScript" Guid="..." Win64="yes">
  <File Id="F_RegisterTaskScript"
        Name="Register-TrayPrintTask.ps1"
        Source="Register-TrayPrintTask.ps1"
        KeyPath="yes" />
</Component>

<Component Id="C_UnregisterTaskScript" Guid="..." Win64="yes">
  <File Id="F_UnregisterTaskScript"
        Name="Unregister-TrayPrintTask.ps1"
        Source="Unregister-TrayPrintTask.ps1"
        KeyPath="yes" />
</Component>

<!-- Custom action: Register Scheduled Task on install -->
<CustomAction Id="RegisterScheduledTask"
              BinaryKey="WixCA"
              DllEntry="WixQuietExec"
              Execute="deferred"
              Impersonate="no"
              Return="check"
              CustomAction=""[SystemFolder]WindowsPowerShell\v1.0\powershell.exe" -ExecutionPolicy Bypass -File "[APPLICATIONFOLDER]Register-TrayPrintTask.ps1" -InstallPath "[APPLICATIONFOLDER]"" />

<!-- Custom action: Unregister Scheduled Task on uninstall -->
<CustomAction Id="UnregisterScheduledTask"
              BinaryKey="WixCA"
              DllEntry="WixQuietExec"
              Execute="deferred"
              Impersonate="no"
              Return="check"
              CustomAction=""[SystemFolder]WindowsPowerShell\v1.0\powershell.exe" -ExecutionPolicy Bypass -File "[APPLICATIONFOLDER]Unregister-TrayPrintTask.ps1"" />

<InstallExecuteSequence>
  <Custom Action="UnregisterScheduledTask" Before="RemoveFiles">Installed AND NOT UPGRADINGPRODUCTCODE</Custom>
  <Custom Action="RegisterScheduledTask" After="InstallFiles">NOT Installed OR UPGRADINGPRODUCTCODE</Custom>
</InstallExecuteSequence>
```

### Step 4: Update the WiX to Remove HKCU Run Registry

The current [`product.wxs`](/home/yudi/dev/trayprint/installer/product.wxs:113-125) has a `C_AutoStartRegistry` component that writes to `HKCU\...\Run`. This should be **removed** since the Scheduled Task replaces it.

### Step 5: Update `build_msi.bat` to Include PowerShell Scripts

**File:** [`/home/yudi/dev/trayprint/installer/build_msi.bat`](/home/yudi/dev/trayprint/installer/build_msi.bat)

Add a check that the PowerShell scripts exist before building:

```batch
if not exist "%SCRIPT_DIR%Register-TrayPrintTask.ps1" (
    echo [ERROR] Register-TrayPrintTask.ps1 not found.
    exit /b 1
)
if not exist "%SCRIPT_DIR%Unregister-TrayPrintTask.ps1" (
    echo [ERROR] Unregister-TrayPrintTask.ps1 not found.
    exit /b 1
)
```

### Step 6: Update `app.py` — Add `--install-task` CLI Command

**File:** [`/home/yudi/dev/trayprint/app.py`](/home/yudi/dev/trayprint/app.py)

Add a new CLI command that allows the user to manually register/unregister the Scheduled Task (useful for debugging or manual setup):

```python
def install_scheduled_task():
    """Register TrayPrint as a Windows Scheduled Task (runs at startup as SYSTEM)."""
    if sys.platform != "win32":
        print("[ERROR] Scheduled task installation is only supported on Windows.")
        return False
    
    import subprocess
    import shutil
    
    app_path = os.path.abspath(sys.argv[0])
    task_name = "TrayPrintAgent"
    
    ps_script = f'''
$action = New-ScheduledTaskAction -Execute "{app_path}" -Argument "--silent" -WorkingDirectory "{get_root_dir()}"
$trigger = New-ScheduledTaskTrigger -AtStartup
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "{task_name}" -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force
'''
    
    try:
        subprocess.run(
            ["powershell.exe", "-ExecutionPolicy", "Bypass", "-Command", ps_script],
            check=True, capture_output=True, text=True, timeout=30,
        )
        print(f"[SUCCESS] Scheduled Task '{task_name}' created.")
        print(f"         Runs {app_path} --silent at system startup as SYSTEM.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"[ERROR] Failed to create scheduled task: {e}")
        print(f"        stderr: {e.stderr}")
        return False


def uninstall_scheduled_task():
    """Remove the TrayPrint Scheduled Task."""
    if sys.platform != "win32":
        return False
    
    import subprocess
    task_name = "TrayPrintAgent"
    
    try:
        subprocess.run(
            ["powershell.exe", "-ExecutionPolicy", "Bypass",
             "-Command", f"Unregister-ScheduledTask -TaskName '{task_name}' -Confirm:$false"],
            check=True, capture_output=True, text=True, timeout=15,
        )
        print(f"[SUCCESS] Scheduled Task '{task_name}' removed.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"[ERROR] Failed to remove scheduled task: {e}")
        return False
```

Add CLI arguments:
```python
parser.add_argument('--install-task', action='store_true',
                    help='Register as Windows Scheduled Task (runs at startup as SYSTEM)')
parser.add_argument('--uninstall-task', action='store_true',
                    help='Remove the Windows Scheduled Task')
```

### Step 7: Update `build.py` — Bundle PowerShell Scripts

**File:** [`/home/yudi/dev/trayprint/build.py`](/home/yudi/dev/trayprint/build.py)

Add the PowerShell scripts to the PyInstaller `--add-data` list so they're available in the single-file executable's extracted temp directory (or better, keep them as separate files alongside the installer).

Actually, for the MSI installer, the PowerShell scripts are bundled **in the MSI itself** (via WiX), not inside the PyInstaller executable. So no change needed in `build.py`.

---

## File Change Summary

| File | Action | Description |
|------|--------|-------------|
| [`installer/Register-TrayPrintTask.ps1`](/home/yudi/dev/trayprint/installer/Register-TrayPrintTask.ps1) | **CREATE** | PowerShell script to register Scheduled Task |
| [`installer/Unregister-TrayPrintTask.ps1`](/home/yudi/dev/trayprint/installer/Unregister-TrayPrintTask.ps1) | **CREATE** | PowerShell script to unregister Scheduled Task |
| [`installer/product.wxs`](/home/yudi/dev/trayprint/installer/product.wxs) | **MODIFY** | Add PowerShell script components, Custom Actions, remove HKCU Run |
| [`installer/build_msi.bat`](/home/yudi/dev/trayprint/installer/build_msi.bat) | **MODIFY** | Add prerequisite checks for PowerShell scripts |
| [`app.py`](/home/yudi/dev/trayprint/app.py) | **MODIFY** | Add `--install-task` / `--uninstall-task` CLI commands |

---

## Build & Install Flow

```
1. python build.py                    # Builds dist/trayprint.exe via PyInstaller
2. cd installer
   candle.exe product.wxs             # Compile WiX source
   light.exe -out TrayPrint.msi product.wixobj  # Link MSI
3. User double-clicks TrayPrint.msi   # Installs to Program Files
4. Installer runs Register-TrayPrintTask.ps1   # Creates Scheduled Task
5. On next reboot:                    # Task Scheduler launches trayprint.exe --silent as SYSTEM
```

## Verification

After installation, verify with:
```powershell
# Check the task exists
Get-ScheduledTask -TaskName "TrayPrintAgent"

# Check it's configured correctly
Get-ScheduledTask -TaskName "TrayPrintAgent" | Get-ScheduledTaskInfo

# Manually trigger
Start-ScheduledTask -TaskName "TrayPrintAgent"

# Check the process is running as SYSTEM
Get-Process -Name trayprint -IncludeUserName | Select-Object UserName, ProcessName, Id
```

## Rollback

Uninstalling the MSI will:
1. Run `Unregister-TrayPrintTask.ps1` to remove the Scheduled Task
2. Remove all installed files
3. Remove Start Menu / Desktop shortcuts
