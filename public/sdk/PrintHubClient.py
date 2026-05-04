"""
Print Hub — Python SDK Client

A lightweight, synchronous client for the Print Hub REST API.
Requires Python 3.8+ and the `requests` library.

Usage:
    from printhub_client import PrintHubClient

    client = PrintHubClient(
        base_url="https://print-hub.example.com",
        api_key="your-api-key-here",
    )
    client.set_branch("SDP-SBY")

    result = client.print_with_template(
        template="invoice_sewa",
        data={"no_invoice": "INV-001", "customer": "PT ABC", "total": 150000},
        reference_id="INV-001",
    )
    print(f"Job queued: {result['job_id']}")
"""

from __future__ import annotations

import json
import logging
import os
import time
import urllib.parse
from typing import Any, Dict, List, Optional

import requests

logger = logging.getLogger(__name__)


class PrintHubError(Exception):
    """Base exception for Print Hub SDK errors."""

    def __init__(self, message: str, status_code: Optional[int] = None,
                 response_body: Optional[Dict[str, Any]] = None) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.response_body = response_body


class PrintHubConnectionError(PrintHubError):
    """Raised when the server cannot be reached (network error, timeout, DNS)."""


class PrintHubValidationError(PrintHubError):
    """Raised when data validation fails against a template schema."""

    def __init__(self, message: str, errors: List[str],
                 status_code: Optional[int] = None,
                 response_body: Optional[Dict[str, Any]] = None) -> None:
        super().__init__(message, status_code, response_body)
        self.errors = errors


class PrintHubClient:
    """
    Synchronous Python client for the Print Hub REST API.

    Provides convenient methods for template-based printing, raw PDF printing,
    job management, and system discovery.
    """

    def __init__(
        self,
        base_url: str,
        api_key: str,
        timeout: int = 15,
        max_retries: int = 2,
        retry_delay_ms: int = 200,
    ) -> None:
        """
        Args:
            base_url: Print Hub server URL (e.g. https://print-hub.example.com).
            api_key:  Client app API key from Print Hub > Client Apps.
            timeout:  Request timeout in seconds (default 15).
            max_retries: Max retries on transient failures (default 2).
            retry_delay_ms: Initial retry delay in ms, doubled each attempt (default 200).
        """
        self._base_url = base_url.rstrip("/")
        self._api_key = api_key
        self._timeout = timeout
        self._max_retries = max_retries
        self._retry_delay_ms = retry_delay_ms
        self._default_branch_code: Optional[str] = None
        self._session = requests.Session()
        self._session.headers.update({
            "X-API-Key": api_key,
            "Content-Type": "application/json",
            "Accept": "application/json",
        })

    # ------------------------------------------------------------------
    # Branch configuration
    # ------------------------------------------------------------------

    def set_branch(self, branch_code: str) -> PrintHubClient:
        """Set the default branch for all subsequent calls."""
        self._default_branch_code = branch_code
        return self

    def get_branch_code(self) -> Optional[str]:
        """Return the currently configured default branch code."""
        return self._default_branch_code

    # ------------------------------------------------------------------
    # Connection & Health
    # ------------------------------------------------------------------

    def test_connection(self) -> Dict[str, Any]:
        """Test connectivity to Print Hub. Returns server info and online agent count."""
        return self._get("/api/v1/test")

    def health(self) -> Dict[str, Any]:
        """Get system health information."""
        return self._get("/api/v1/health")

    # ------------------------------------------------------------------
    # Discovery
    # ------------------------------------------------------------------

    def get_branches(self) -> List[Dict[str, Any]]:
        """List all active branches."""
        return self._get("/api/v1/branches")

    def get_online_agents(self, branch_code: Optional[str] = None) -> List[Dict[str, Any]]:
        """List online agents, optionally filtered by branch."""
        params = {}
        if branch_code or self._default_branch_code:
            params["branch_code"] = branch_code or self._default_branch_code
        return self._get("/api/v1/agents/online", params=params)

    def get_queues(self, branch_code: Optional[str] = None,
                   detailed: bool = False) -> List[Dict[str, Any]]:
        """List print queues."""
        params: Dict[str, Any] = {}
        if branch_code or self._default_branch_code:
            params["branch_code"] = branch_code or self._default_branch_code
        if detailed:
            params["detailed"] = "true"
        return self._get("/api/v1/queues", params=params)

    # ------------------------------------------------------------------
    # Templates
    # ------------------------------------------------------------------

    def get_templates(self) -> List[Dict[str, Any]]:
        """List all available print templates."""
        return self._get("/api/v1/templates")

    def get_template(self, name: str) -> Dict[str, Any]:
        """Get detailed info for a specific template."""
        return self._get(f"/api/v1/templates/{urllib.parse.quote(name, safe='')}")

    def get_template_schema(self, name: str) -> Dict[str, Any]:
        """Get the required data schema for a template."""
        return self._get(f"/api/v1/templates/{urllib.parse.quote(name, safe='')}/schema")

    # ------------------------------------------------------------------
    # Data Schemas
    # ------------------------------------------------------------------

    def register_schema(self, schema_name: str, schema_data: Dict[str, Any]) -> Dict[str, Any]:
        """Register or update a data schema for template data binding."""
        return self._post("/api/v1/schema", {
            "schema_name": schema_name,
            **schema_data,
        })

    def list_schemas(self) -> List[Dict[str, Any]]:
        """List all registered data schemas."""
        return self._get("/api/v1/schemas")

    def schema_versions(self, schema_name: str) -> List[Dict[str, Any]]:
        """Get version history for a schema."""
        return self._get(f"/api/v1/schema/{urllib.parse.quote(schema_name, safe='')}/versions")

    # ------------------------------------------------------------------
    # Printing
    # ------------------------------------------------------------------

    def print_with_template(
        self,
        template: str,
        data: Dict[str, Any],
        reference_id: str = "",
        branch_code: Optional[str] = None,
        options: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """Submit a template-based print job.

        Args:
            template: Template name (e.g. 'invoice_sewa').
            data: Field values matching the template schema.
            reference_id: Your internal reference ID for the job.
            branch_code: Target branch (defaults to the configured branch).
            options: Print options dict (copies, duplex, color_mode, etc.).

        Returns:
            dict with keys: status, job_id, agent, printer, etc.
        """
        body: Dict[str, Any] = {
            "template": template,
            "data": data,
        }
        if reference_id:
            body["reference_id"] = reference_id
        if branch_code or self._default_branch_code:
            body["branch_code"] = branch_code or self._default_branch_code
        if options:
            body["options"] = options
        return self._post("/api/v1/print", body)

    def print_raw_pdf(
        self,
        base64_pdf: str,
        reference_id: str = "",
        branch_code: Optional[str] = None,
        options: Optional[Dict[str, Any]] = None,
        printer_name: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Print a raw base64-encoded PDF without using a template.

        Args:
            base64_pdf: Base64-encoded PDF content.
            reference_id: Your internal reference ID.
            branch_code: Target branch.
            options: Print options dict.
            printer_name: Target printer name (required if no template).

        Returns:
            dict with job status info.
        """
        body: Dict[str, Any] = {
            "base64_pdf": base64_pdf,
        }
        if reference_id:
            body["reference_id"] = reference_id
        if branch_code or self._default_branch_code:
            body["branch_code"] = branch_code or self._default_branch_code
        if options:
            body["options"] = options
        if printer_name:
            body["printer_name"] = printer_name
        return self._post("/api/v1/print", body)

    def print_batch(
        self,
        jobs: List[Dict[str, Any]],
    ) -> Dict[str, Any]:
        """Submit multiple print jobs in a single request (max 50).

        Each job dict should contain: template, data, reference_id, branch_code, options.
        """
        # Fill in default branch for jobs that don't specify one
        filled = []
        for job in jobs:
            if "branch_code" not in job and self._default_branch_code:
                job = {**job, "branch_code": self._default_branch_code}
            filled.append(job)
        return self._post("/api/v1/print/batch", {"jobs": filled})

    def preview(self, template: str, data: Dict[str, Any],
                options: Optional[Dict[str, Any]] = None) -> bytes:
        """Generate a PDF preview without sending to a printer.

        Returns:
            Raw PDF binary content.
        """
        body: Dict[str, Any] = {
            "template": template,
            "data": data,
        }
        if options:
            body["options"] = options
        resp = self._session.post(
            f"{self._base_url}/api/v1/preview",
            json=body,
            timeout=self._timeout,
        )
        if resp.status_code != 200:
            raise PrintHubError(
                f"Preview failed (HTTP {resp.status_code}): {resp.text}",
                status_code=resp.status_code,
            )
        return resp.content

    # ------------------------------------------------------------------
    # Job Management
    # ------------------------------------------------------------------

    def job_status(self, job_id: str) -> Dict[str, Any]:
        """Check the current status of a print job."""
        return self._get(f"/api/v1/jobs/{urllib.parse.quote(job_id, safe='')}")

    def cancel_job(self, job_id: str) -> Dict[str, Any]:
        """Cancel a pending print job."""
        return self._delete(f"/api/v1/jobs/{urllib.parse.quote(job_id, safe='')}")

    def wait_for_job(
        self,
        job_id: str,
        timeout_seconds: int = 30,
        poll_interval_ms: int = 500,
    ) -> Dict[str, Any]:
        """Poll until a job reaches a terminal status (success or failed).

        Raises:
            PrintHubError: If the job does not complete within the timeout.
        """
        deadline = time.time() + timeout_seconds
        while time.time() < deadline:
            result = self.job_status(job_id)
            status = result.get("status", "")
            if status in ("success", "failed"):
                return result
            time.sleep(poll_interval_ms / 1000.0)
        raise PrintHubError(
            f"Job {job_id} did not complete within {timeout_seconds}s timeout",
        )

    # ------------------------------------------------------------------
    # Internal request methods
    # ------------------------------------------------------------------

    def _get(self, path: str,
             params: Optional[Dict[str, Any]] = None) -> Any:
        return self._request("GET", path, params=params)

    def _post(self, path: str, body: Optional[Dict[str, Any]] = None) -> Any:
        return self._request("POST", path, json=body)

    def _delete(self, path: str) -> Any:
        return self._request("DELETE", path)

    def _request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        json: Optional[Dict[str, Any]] = None,
    ) -> Any:
        url = f"{self._base_url}{path}"
        last_exc: Optional[Exception] = None

        for attempt in range(self._max_retries + 1):
            try:
                resp = self._session.request(
                    method=method,
                    url=url,
                    params=params,
                    json=json,
                    timeout=self._timeout,
                )

                if resp.status_code == 429:
                    # Rate limited — retry with backoff
                    delay = (self._retry_delay_ms / 1000.0) * (2 ** attempt)
                    logger.warning(
                        "Rate limited (attempt %d/%d), retrying in %.1fs",
                        attempt + 1, self._max_retries + 1, delay,
                    )
                    time.sleep(delay)
                    continue

                if resp.status_code >= 400:
                    self._handle_error_response(resp)

                return resp.json()

            except requests.ConnectionError as e:
                last_exc = PrintHubConnectionError(
                    f"Cannot connect to {url}: {e}",
                )
                if attempt < self._max_retries:
                    delay = (self._retry_delay_ms / 1000.0) * (2 ** attempt)
                    logger.warning(
                        "Connection error (attempt %d/%d), retrying in %.1fs: %s",
                        attempt + 1, self._max_retries + 1, delay, e,
                    )
                    time.sleep(delay)
                    continue
                raise last_exc from e

            except requests.Timeout as e:
                last_exc = PrintHubConnectionError(
                    f"Request to {url} timed out after {self._timeout}s",
                )
                if attempt < self._max_retries:
                    delay = (self._retry_delay_ms / 1000.0) * (2 ** attempt)
                    logger.warning(
                        "Timeout (attempt %d/%d), retrying in %.1fs",
                        attempt + 1, self._max_retries + 1, delay,
                    )
                    time.sleep(delay)
                    continue
                raise last_exc from e

        # If we exhausted retries, raise the last error
        if last_exc:
            raise last_exc
        raise PrintHubError("Max retries exceeded")

    @staticmethod
    def _handle_error_response(resp: requests.Response) -> None:
        """Parse an error response and raise the appropriate exception."""
        try:
            body = resp.json()
        except (json.JSONDecodeError, ValueError):
            body = {}

        message = body.get("error", {}).get("message", body.get("message", resp.reason or "Unknown error"))
        errors = body.get("error", {}).get("errors", body.get("errors", []))

        if resp.status_code == 422:
            raise PrintHubValidationError(
                message, errors,
                status_code=resp.status_code,
                response_body=body,
            )
        raise PrintHubError(
            message,
            status_code=resp.status_code,
            response_body=body,
        )
