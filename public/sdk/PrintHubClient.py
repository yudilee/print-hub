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
        parameters: Optional[Dict[str, Any]] = None,
        pool_id: Optional[int] = None,
        document_id: Optional[int] = None,
        webhook_url: Optional[str] = None,
        agent_id: Optional[int] = None,
        printer: Optional[str] = None,
        branch_id: Optional[int] = None,
        priority: Optional[int] = None,
        scheduled_at: Optional[str] = None,
        recurrence: Optional[str] = None,
        recurrence_end_at: Optional[str] = None,
        recurrence_count: Optional[int] = None,
    ) -> Dict[str, Any]:
        """Submit a template-based print job.

        Args:
            template: Template name (e.g. 'invoice_sewa').
            data: Field values matching the template schema.
            reference_id: Your internal reference ID for the job.
            branch_code: Target branch (defaults to the configured branch).
            options: Print options dict (copies, duplex, color_mode, etc.).
            parameters: Runtime parameter values keyed by parameter name.
            pool_id: Printer pool ID for automatic printer selection.
            document_id: Attach an existing uploaded document.
            webhook_url: URL to receive job status callbacks.
            agent_id: Specific agent ID to route the job to.
            printer: Specific printer name override.
            branch_id: Branch ID override (alternative to branch_code).
            priority: Job priority (higher = more urgent).
            scheduled_at: ISO 8601 datetime for scheduled printing.
            recurrence: Recurrence pattern: daily, weekly, monthly, none.
            recurrence_end_at: ISO 8601 datetime to stop recurring.
            recurrence_count: Max number of recurring executions.

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
        if parameters:
            body["parameters"] = parameters
        if pool_id is not None:
            body["pool_id"] = pool_id
        if document_id is not None:
            body["document_id"] = document_id
        if webhook_url is not None:
            body["webhook_url"] = webhook_url
        if agent_id is not None:
            body["agent_id"] = agent_id
        if printer is not None:
            body["printer"] = printer
        if branch_id is not None:
            body["branch_id"] = branch_id
        if priority is not None:
            body["priority"] = priority
        if scheduled_at is not None:
            body["scheduled_at"] = scheduled_at
        if recurrence is not None:
            body["recurrence"] = recurrence
        if recurrence_end_at is not None:
            body["recurrence_end_at"] = recurrence_end_at
        if recurrence_count is not None:
            body["recurrence_count"] = recurrence_count
        return self._post("/api/v1/print", body)

    def print_raw_pdf(
        self,
        document_base64: str,
        reference_id: str = "",
        branch_code: Optional[str] = None,
        options: Optional[Dict[str, Any]] = None,
        printer: Optional[str] = None,
        pool_id: Optional[int] = None,
        document_id: Optional[int] = None,
        webhook_url: Optional[str] = None,
        agent_id: Optional[int] = None,
        branch_id: Optional[int] = None,
        priority: Optional[int] = None,
        scheduled_at: Optional[str] = None,
        recurrence: Optional[str] = None,
        recurrence_end_at: Optional[str] = None,
        recurrence_count: Optional[int] = None,
    ) -> Dict[str, Any]:
        """Print a raw base64-encoded PDF without using a template.

        Args:
            document_base64: Base64-encoded PDF content.
            reference_id: Your internal reference ID.
            branch_code: Target branch.
            options: Print options dict.
            printer: Target printer name override.
            pool_id: Printer pool ID for automatic printer selection.
            document_id: Attach an existing uploaded document.
            webhook_url: URL to receive job status callbacks.
            agent_id: Specific agent ID to route the job to.
            branch_id: Branch ID override (alternative to branch_code).
            priority: Job priority (higher = more urgent).
            scheduled_at: ISO 8601 datetime for scheduled printing.
            recurrence: Recurrence pattern: daily, weekly, monthly, none.
            recurrence_end_at: ISO 8601 datetime to stop recurring.
            recurrence_count: Max number of recurring executions.

        Returns:
            dict with job status info.
        """
        body: Dict[str, Any] = {
            "document_base64": document_base64,
        }
        if reference_id:
            body["reference_id"] = reference_id
        if branch_code or self._default_branch_code:
            body["branch_code"] = branch_code or self._default_branch_code
        if options:
            body["options"] = options
        if printer is not None:
            body["printer"] = printer
        if pool_id is not None:
            body["pool_id"] = pool_id
        if document_id is not None:
            body["document_id"] = document_id
        if webhook_url is not None:
            body["webhook_url"] = webhook_url
        if agent_id is not None:
            body["agent_id"] = agent_id
        if branch_id is not None:
            body["branch_id"] = branch_id
        if priority is not None:
            body["priority"] = priority
        if scheduled_at is not None:
            body["scheduled_at"] = scheduled_at
        if recurrence is not None:
            body["recurrence"] = recurrence
        if recurrence_end_at is not None:
            body["recurrence_end_at"] = recurrence_end_at
        if recurrence_count is not None:
            body["recurrence_count"] = recurrence_count
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

    # ------------------------------------------------------------------
    # Schema Management — Extended
    # ------------------------------------------------------------------

    def schema_version_diff(self, schema_name: str, from_version: int, to_version: int) -> Dict[str, Any]:
        """Get the diff between two versions of a schema."""
        return self._get(
            f"/api/v1/schemas/{urllib.parse.quote(schema_name, safe='')}/diff",
            params={"from": from_version, "to": to_version},
        )

    def validate_template_data(self, template_name: str, data: Dict[str, Any]) -> Dict[str, Any]:
        """Validate data against a template's schema (server-side).

        Returns:
            dict with keys: valid (bool), errors (list).
        """
        return self._post(
            f"/api/v1/templates/{urllib.parse.quote(template_name, safe='')}/validate",
            {"data": data},
        )

    # ------------------------------------------------------------------
    # Document Management
    # ------------------------------------------------------------------

    def upload_document(self, filename: str, file_data: str, mime_type: Optional[str] = None) -> Dict[str, Any]:
        """Upload a document to Print Hub for later use in print jobs.

        Args:
            filename: Original filename.
            file_data: Base64-encoded file content.
            mime_type: MIME type (e.g. application/pdf).

        Returns:
            dict with keys: id, filename, mime_type, size, url, created_at.
        """
        body: Dict[str, Any] = {
            "filename": filename,
            "file_data": file_data,
        }
        if mime_type:
            body["mime_type"] = mime_type
        return self._post("/api/v1/documents/upload", body)

    def list_documents(self) -> Dict[str, Any]:
        """List all uploaded documents.

        Returns:
            dict with keys: data (list), meta (pagination).
        """
        return self._get("/api/v1/documents")

    def get_document(self, doc_id: int) -> Dict[str, Any]:
        """Get details of a specific document."""
        return self._get(f"/api/v1/documents/{doc_id}")

    def preview_document(self, doc_id: int) -> bytes:
        """Preview a document (rendered as PDF).

        Returns:
            Raw PDF binary content.
        """
        resp = self._session.get(
            f"{self._base_url}/api/v1/documents/{doc_id}/preview",
            timeout=self._timeout,
        )
        if resp.status_code != 200:
            raise PrintHubError(
                f"Document preview failed (HTTP {resp.status_code}): {resp.text}",
                status_code=resp.status_code,
            )
        return resp.content

    def download_document(self, doc_id: int) -> bytes:
        """Download a document's raw file content.

        Returns:
            Raw file binary content.
        """
        resp = self._session.get(
            f"{self._base_url}/api/v1/documents/{doc_id}/download",
            timeout=self._timeout,
        )
        if resp.status_code != 200:
            raise PrintHubError(
                f"Document download failed (HTTP {resp.status_code}): {resp.text}",
                status_code=resp.status_code,
            )
        return resp.content

    def delete_document(self, doc_id: int) -> Dict[str, Any]:
        """Delete a document."""
        return self._delete(f"/api/v1/documents/{doc_id}")

    # ------------------------------------------------------------------
    # Connector Registry
    # ------------------------------------------------------------------

    def register_connector(self, name: str, connector_type: str, config: Dict[str, Any],
                           icon: Optional[str] = None) -> Dict[str, Any]:
        """Register a new data-source connector."""
        body: Dict[str, Any] = {
            "name": name,
            "type": connector_type,
            "config": config,
        }
        if icon:
            body["icon"] = icon
        return self._post("/api/v1/connectors", body)

    def list_connectors(self) -> Dict[str, Any]:
        """List all connectors registered for this client app."""
        return self._get("/api/v1/connectors")

    def update_connector(self, connector_id: str, data: Dict[str, Any]) -> Dict[str, Any]:
        """Update an existing connector."""
        return self._request("PUT", f"/api/v1/connectors/{connector_id}", json=data)

    def test_connector(self, connector_id: str) -> Dict[str, Any]:
        """Test a connector connection."""
        return self._post(f"/api/v1/connectors/{connector_id}/test", {})

    def delete_connector(self, connector_id: str) -> Dict[str, Any]:
        """Delete a connector."""
        return self._delete(f"/api/v1/connectors/{connector_id}")

    # ------------------------------------------------------------------
    # Approvals
    # ------------------------------------------------------------------

    def get_pending_approvals(self) -> Dict[str, Any]:
        """List all print jobs pending approval.

        Returns:
            dict with keys: data (list), meta (pagination).
        """
        return self._get("/api/v1/approvals/pending")

    def approve_job(self, job_id: str) -> Dict[str, Any]:
        """Approve a pending print job."""
        return self._post(f"/api/v1/approvals/{urllib.parse.quote(job_id, safe='')}/approve", {})

    def reject_job(self, job_id: str) -> Dict[str, Any]:
        """Reject a pending print job."""
        return self._post(f"/api/v1/approvals/{urllib.parse.quote(job_id, safe='')}/reject", {})

    # ------------------------------------------------------------------
    # Agent Version
    # ------------------------------------------------------------------

    def get_agent_version(self) -> Dict[str, Any]:
        """Get the latest available TrayPrint agent version.

        Returns:
            dict with keys: version, download_url, release_notes, published_at.
        """
        return self._get("/api/v1/agents/version")

    # ------------------------------------------------------------------
    # Fonts
    # ------------------------------------------------------------------

    def get_fonts(self) -> Dict[str, Any]:
        """List all available fonts.

        Returns:
            dict with keys: data (list), meta (pagination).
        """
        return self._get("/api/v1/fonts")

    # ------------------------------------------------------------------
    # Formula Editor
    # ------------------------------------------------------------------

    def get_formula_functions(self) -> Dict[str, Any]:
        """List all available formula functions.

        Returns:
            dict with keys: functions (list).
        """
        return self._get("/api/v1/formula/functions")

    def validate_formula(self, expression: str) -> Dict[str, Any]:
        """Validate a formula expression.

        Returns:
            dict with keys: valid (bool), errors (list), tokens (list).
        """
        return self._post("/api/v1/formula/validate", {"expression": expression})

    def evaluate_formula(self, expression: str, context: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Evaluate a formula expression with given context.

        Returns:
            dict with keys: result, success (bool), error (optional).
        """
        body: Dict[str, Any] = {"expression": expression}
        if context:
            body["context"] = context
        return self._post("/api/v1/formula/evaluate", body)

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
