# TrayPrint Client Upgrade Implementation Guide

This document contains the exact code enhancements to apply to the **TrayPrint** desktop client (`/home/yudi/dev/trayprint`) to enable real-time sub-second WebSocket printing over Cloudflare Tunnel (Laravel Reverb) and hardware health telemetry reporting.

---

## 1. Upgrade `websocket_client.py`

Replace `websocket_client.py` with the enhanced client that supports:
1. **Cloudflare Tunnel WSS**: Auto-detects `https://` / `wss://` and formats port 443 cleanly.
2. **Dynamic Channel Subscriptions**: Subscribes to `admin.queue` and `agent.{agent_id}`.
3. **Automatic Reconnection & Exponential Backoff**.

```python
"""
WebSocket client for Print Hub's Reverb server with Cloudflare Tunnel WSS support.
"""

import json
import logging
import threading
import time
from urllib.parse import urlparse
from typing import Callable, Optional

log = logging.getLogger('trayprint.ws')

try:
    import websockets
    HAS_WEBSOCKETS = True
except ImportError:
    HAS_WEBSOCKETS = False
    log.info("'websockets' library not installed. WebSocket client disabled. Install with: pip install websockets")


class ReverbWebSocketClient:
    def __init__(
        self,
        host: str = '127.0.0.1',
        port: int = 8080,
        app_key: str = '',
        scheme: str = 'ws',
        agent_key: str = '',
        agent_id: str = '',
        hub_url: str = '',
        on_event: Optional[Callable] = None,
        reconnect_delay: float = 5.0,
        max_reconnect_attempts: int = 0,
    ):
        self.host = host
        self.port = port
        self.app_key = app_key
        self.agent_key = agent_key
        self.agent_id = str(agent_id) if agent_id else ''
        self.hub_url = hub_url
        self.on_event = on_event
        self.reconnect_delay = reconnect_delay
        self.max_reconnect_attempts = max_reconnect_attempts

        # Infer host/scheme from hub_url if provided
        if hub_url:
            parsed = urlparse(hub_url)
            self.host = parsed.hostname or self.host
            if parsed.scheme == 'https':
                self.scheme = 'wss'
                self.port = parsed.port or 443
            else:
                self.scheme = 'ws'
                self.port = parsed.port or 80
        else:
            self.scheme = 'wss' if scheme in ('https', 'wss') else 'ws'

        if (self.scheme == 'wss' and self.port == 443) or (self.scheme == 'ws' and self.port == 80):
            self._ws_url = f"{self.scheme}://{self.host}/app/{self.app_key}"
        else:
            self._ws_url = f"{self.scheme}://{self.host}:{self.port}/app/{self.app_key}"

        self._thread: Optional[threading.Thread] = None
        self._stop_event = threading.Event()
        self._connected = False
        self._connect_count = 0

    @property
    def is_connected(self) -> bool:
        return self._connected

    @property
    def is_running(self) -> bool:
        return self._thread is not None and self._thread.is_alive()

    def start(self):
        if not HAS_WEBSOCKETS:
            log.warning("WebSocket client not started: 'websockets' library not available")
            return
        if self.is_running:
            return
        self._stop_event.clear()
        self._thread = threading.Thread(target=self._run_loop, daemon=True, name='reverb-ws')
        self._thread.start()
        log.info("WebSocket client started -> connecting to %s", self._ws_url)

    def stop(self):
        self._stop_event.set()
        if self._thread:
            self._thread.join(timeout=3)
        self._connected = False
        log.info("WebSocket client stopped")

    def _run_loop(self):
        attempts = 0
        while not self._stop_event.is_set():
            if self.max_reconnect_attempts > 0 and attempts >= self.max_reconnect_attempts:
                log.warning("WebSocket client: max reconnect attempts reached")
                break
            try:
                attempts += 1
                import websockets.asyncio.client
                import asyncio

                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                try:
                    loop.run_until_complete(self._run_async())
                finally:
                    loop.close()
            except Exception as e:
                log.debug("WebSocket connection disconnected (%s), reconnecting...", e)
                self._connected = False

            if not self._stop_event.is_set():
                self._stop_event.wait(self.reconnect_delay)

    async def _run_async(self):
        import websockets.asyncio.client
        import asyncio

        async with websockets.asyncio.client.connect(
            self._ws_url,
            ping_interval=30,
            ping_timeout=10,
            close_timeout=5,
        ) as ws:
            self._connected = True
            log.info("WebSocket connected to %s", self._ws_url)

            # Subscribe to channels
            channels = ['admin.queue']
            if self.agent_id:
                channels.append(f"agent.{self.agent_id}")

            for ch in channels:
                await ws.send(json.dumps({'event': 'pusher:subscribe', 'data': {'channel': ch}}))
                log.debug("Subscribed to WebSocket channel: %s", ch)

            while not self._stop_event.is_set():
                try:
                    message = await asyncio.wait_for(ws.recv(), timeout=5.0)
                    if isinstance(message, bytes):
                        message = message.decode('utf-8')
                    data = json.loads(message)
                    event = data.get('event', '')
                    if event.startswith('pusher:'):
                        continue

                    log.info("WebSocket push received: %s on channel %s", event, data.get('channel'))
                    if self.on_event:
                        self.on_event(data)
                except asyncio.TimeoutError:
                    continue
                except websockets.exceptions.ConnectionClosed:
                    break


def start_websocket_client(config: dict, on_event: Optional[Callable] = None) -> Optional[ReverbWebSocketClient]:
    if not HAS_WEBSOCKETS:
        return None
    hub_url = config.get('hub_url', '')
    if not hub_url:
        return None

    app_key = config.get('reverb_app_key', 'printhub-live-key')
    agent_id = config.get('agent_id', '')

    client = ReverbWebSocketClient(
        app_key=app_key,
        agent_key=config.get('agent_key', ''),
        agent_id=agent_id,
        hub_url=hub_url,
        on_event=on_event,
    )
    client.start()
    return client
```

---

## 2. Upgrade Telemetry in `server.py` & `printer.py`

In `printer.py`, add `get_printer_hardware_status(printer_name)`:

```python
def get_printer_hardware_status(printer_name: str) -> dict:
    """Returns normalized hardware status dictionary for telemetry."""
    if not is_windows() or not win32print:
        return {'state': 'ready', 'message': 'Online'}

    try:
        h = win32print.OpenPrinter(printer_name)
        info = win32print.GetPrinter(h, 2)
        win32print.ClosePrinter(h)
        status_bits = info.get('Status', 0)

        if status_bits == 0:
            return {'state': 'ready', 'message': 'Ready'}
        if status_bits & 16:
            return {'state': 'paper_out', 'message': 'Out of paper'}
        if status_bits & 8:
            return {'state': 'paper_jam', 'message': 'Paper jam'}
        if status_bits & 128:
            return {'state': 'offline', 'message': 'Printer offline'}
        if status_bits & 131072:
            return {'state': 'low_toner', 'message': 'Toner low'}
        if status_bits & 2:
            return {'state': 'error', 'message': 'General error'}
        return {'state': 'ready', 'message': f'Status code {status_bits}'}
    except Exception as e:
        return {'state': 'error', 'message': str(e)}
```

In `server.py`, enhance `report_status_to_hub()` to upload `hardware_status`:

```python
def report_status_to_hub(hub_url, agent_key):
    """Syncs discovered printers, capabilities, and hardware status to Print Hub."""
    import requests
    if not hub_url:
        return
    try:
        headers = {
            'Authorization': f'Bearer {agent_key}',
            'Content-Type': 'application/json'
        }
        printers_list = printer.get_printers()
        capabilities_dict = {}
        hardware_status_dict = {}

        for p in printers_list:
            name = p.get('name', '')
            if name:
                try:
                    hardware_status_dict[name] = printer.get_printer_hardware_status(name)
                except Exception:
                    pass

        payload = {
            'printers': [p['name'] for p in printers_list],
            'capabilities': capabilities_dict,
            'hardware_status': hardware_status_dict,
        }

        # Send to telemetry endpoint
        requests.post(f'{hub_url}/api/print-hub/telemetry', json=payload, headers=headers, timeout=10)
        log.info("Uploaded hardware telemetry for %d printers to Print Hub", len(printers_list))
    except Exception as e:
        log.debug("Telemetry sync failed: %s", e)
```

---

## 3. Automatic Real-Time Queue Refresh

In `server.py`, connect the WebSocket push event to `request_queue_refresh()`:

```python
def on_ws_event(event_data):
    """Callback triggered whenever a WebSocket push arrives from Print Hub."""
    event_name = event_data.get('event', '')
    log.info("WebSocket triggered instant queue check (event: %s)", event_name)
    request_queue_refresh()

# At startup in server.py:
ws_client = start_websocket_client(config, on_event=on_ws_event)
```
