/**
 * Print Hub — Node.js SDK Client (ESM)
 *
 * A lightweight, promise-based client for the Print Hub REST API.
 * Requires Node.js 18+ (native fetch) or 16+ with `node-fetch` polyfill.
 *
 * Usage:
 *   import { PrintHubClient } from './PrintHubClient.mjs';
 *
 *   const client = new PrintHubClient({
 *     baseUrl: 'https://print-hub.example.com',
 *     apiKey:  'your-api-key-here',
 *   });
 *   client.setBranch('SDP-SBY');
 *
 *   const result = await client.printWithTemplate({
 *     template: 'invoice_sewa',
 *     data: { no_invoice: 'INV-001', customer: 'PT ABC', total: 150000 },
 *     referenceId: 'INV-001',
 *   });
 *   console.log(`Job queued: ${result.job_id}`);
 */

// ---------------------------------------------------------------------------
// Custom Error Classes
// ---------------------------------------------------------------------------

export class PrintHubError extends Error {
  /** @param {string} message */
  constructor(message) {
    super(message);
    this.name = 'PrintHubError';
  }
}

export class PrintHubConnectionError extends PrintHubError {
  /** @param {string} message */
  constructor(message) {
    super(message);
    this.name = 'PrintHubConnectionError';
  }
}

export class PrintHubValidationError extends PrintHubError {
  /** @param {string} message @param {string[]} errors */
  constructor(message, errors = []) {
    super(message);
    this.name = 'PrintHubValidationError';
    this.errors = errors;
  }
}

// ---------------------------------------------------------------------------
// Client
// ---------------------------------------------------------------------------

export class PrintHubClient {
  /**
   * @param {Object} options
   * @param {string} options.baseUrl    - Print Hub server URL
   * @param {string} options.apiKey     - Client app API key
   * @param {number} [options.timeout=15] - Request timeout in seconds
   * @param {number} [options.maxRetries=2]  - Max retries on transient failures
   * @param {number} [options.retryDelayMs=200] - Initial retry delay (exponential backoff)
   */
  constructor({ baseUrl, apiKey, timeout = 15, maxRetries = 2, retryDelayMs = 200 }) {
    if (!baseUrl) throw new PrintHubError('baseUrl is required');
    if (!apiKey) throw new PrintHubError('apiKey is required');

    this._baseUrl = baseUrl.replace(/\/+$/, '');
    this._apiKey = apiKey;
    this._timeout = timeout * 1000; // convert to ms
    this._maxRetries = maxRetries;
    this._retryDelayMs = retryDelayMs;
    this._defaultBranchCode = null;
    this._abortController = null;
  }

  // -----------------------------------------------------------------------
  // Branch configuration
  // -----------------------------------------------------------------------

  /** @param {string} branchCode */
  setBranch(branchCode) {
    this._defaultBranchCode = branchCode;
    return this;
  }

  /** @returns {string|null} */
  getBranchCode() {
    return this._defaultBranchCode;
  }

  // -----------------------------------------------------------------------
  // Connector Registry
  // -----------------------------------------------------------------------

  /**
   * Register a new data-source connector.
   * @param {string} name  - Human-readable name (e.g. "SDP Finance ERP")
   * @param {string} type  - One of: api, webhook, odoo, custom
   * @param {Object} config - Configuration: endpoint URL, auth type, headers, etc.
   * @param {string|null} [icon] - Optional emoji or icon URL
   */
  async registerConnector(name, type, config, icon = null) {
    return this._post('/api/v1/connectors', { name, type, config, icon });
  }

  /** List all connectors registered for this client app. */
  async listConnectors() {
    return this._get('/api/v1/connectors');
  }

  /**
   * Update an existing connector.
   * @param {string} id    - Connector UUID
   * @param {Object} data  - Fields to update (name, type, config, icon, is_active)
   */
  async updateConnector(id, data) {
    return this._put(`/api/v1/connectors/${encodeURIComponent(id)}`, data);
  }

  /**
   * Test a connector by sending a HEAD request to its configured URL.
   * @param {string} id  - Connector UUID
   */
  async testConnector(id) {
    return this._post(`/api/v1/connectors/${encodeURIComponent(id)}/test`, {});
  }

  /**
   * Delete a connector.
   * @param {string} id  - Connector UUID
   */
  async deleteConnector(id) {
    return this._delete(`/api/v1/connectors/${encodeURIComponent(id)}`);
  }

  // -----------------------------------------------------------------------
  // Connection & Health
  // -----------------------------------------------------------------------

  /** Test connectivity to Print Hub. */
  async testConnection() {
    return this._get('/api/v1/test');
  }

  /** Get system health information. */
  async health() {
    return this._get('/api/v1/health');
  }

  // -----------------------------------------------------------------------
  // Discovery
  // -----------------------------------------------------------------------

  /** List all active branches. */
  async getBranches() {
    return this._get('/api/v1/branches');
  }

  /** List online agents, optionally filtered by branch. */
  async getOnlineAgents(branchCode) {
    const params = {};
    if (branchCode || this._defaultBranchCode) {
      params.branch_code = branchCode || this._defaultBranchCode;
    }
    return this._get('/api/v1/agents/online', params);
  }

  /** List print queues. */
  async getQueues({ branchCode, detailed = false } = {}) {
    const params = {};
    if (branchCode || this._defaultBranchCode) {
      params.branch_code = branchCode || this._defaultBranchCode;
    }
    if (detailed) params.detailed = 'true';
    return this._get('/api/v1/queues', params);
  }

  // -----------------------------------------------------------------------
  // Templates
  // -----------------------------------------------------------------------

  /** List all available print templates. */
  async getTemplates() {
    return this._get('/api/v1/templates');
  }

  /** Get detailed info for a specific template. */
  async getTemplate(name) {
    return this._get(`/api/v1/templates/${encodeURIComponent(name)}`);
  }

  /** Get the required data schema for a template. */
  async getTemplateSchema(name) {
    return this._get(`/api/v1/templates/${encodeURIComponent(name)}/schema`);
  }

  // -----------------------------------------------------------------------
  // Data Schemas
  // -----------------------------------------------------------------------

  /** Register or update a data schema. */
  async registerSchema(schemaName, schemaData) {
    return this._post('/api/v1/schema', { schema_name: schemaName, ...schemaData });
  }

  /** List all registered data schemas. */
  async listSchemas() {
    return this._get('/api/v1/schemas');
  }

  /** Get version history for a schema. */
  async schemaVersions(schemaName) {
    return this._get(`/api/v1/schema/${encodeURIComponent(schemaName)}/versions`);
  }

  // -----------------------------------------------------------------------
  // Printing
  // -----------------------------------------------------------------------

  /**
   * Submit a template-based print job.
   * @param {Object} opts
   * @param {string} opts.template         - Template name
   * @param {Object} opts.data             - Field values
   * @param {string} [opts.referenceId]    - Your reference ID
   * @param {string} [opts.branchCode]     - Target branch
   * @param {Object} [opts.options]        - Print options
   * @param {Object} [opts.parameters]     - Runtime parameter values keyed by name
   * @param {number} [opts.poolId]         - Printer pool ID
   * @param {number} [opts.documentId]     - Attach an existing uploaded document
   * @param {string} [opts.webhookUrl]     - Job status callback URL
   * @param {number} [opts.agentId]        - Specific agent ID
   * @param {string} [opts.printer]        - Specific printer name
   * @param {number} [opts.branchId]       - Branch ID override
   * @param {number} [opts.priority]       - Job priority
   * @param {string} [opts.scheduledAt]    - ISO 8601 scheduled datetime
   * @param {string} [opts.recurrence]     - Recurrence pattern
   * @param {string} [opts.recurrenceEndAt]- Recurrence end datetime
   * @param {number} [opts.recurrenceCount]- Max recurring executions
   */
  async printWithTemplate({ template, data, referenceId = '', branchCode, options, parameters,
    poolId, documentId, webhookUrl, agentId, printer, branchId, priority,
    scheduledAt, recurrence, recurrenceEndAt, recurrenceCount } = {}) {
    const body = { template, data };
    if (referenceId) body.reference_id = referenceId;
    if (branchCode || this._defaultBranchCode) body.branch_code = branchCode || this._defaultBranchCode;
    if (options) body.options = options;
    if (parameters) body.parameters = parameters;
    if (poolId != null) body.pool_id = poolId;
    if (documentId != null) body.document_id = documentId;
    if (webhookUrl != null) body.webhook_url = webhookUrl;
    if (agentId != null) body.agent_id = agentId;
    if (printer != null) body.printer = printer;
    if (branchId != null) body.branch_id = branchId;
    if (priority != null) body.priority = priority;
    if (scheduledAt != null) body.scheduled_at = scheduledAt;
    if (recurrence != null) body.recurrence = recurrence;
    if (recurrenceEndAt != null) body.recurrence_end_at = recurrenceEndAt;
    if (recurrenceCount != null) body.recurrence_count = recurrenceCount;
    return this._post('/api/v1/print', body);
  }

  /**
   * Print a raw base64-encoded PDF without using a template.
   * @param {Object} opts
   * @param {string} opts.documentBase64  - Base64-encoded PDF content
   * @param {string} [opts.referenceId]
   * @param {string} [opts.branchCode]
   * @param {Object} [opts.options]
   * @param {string} [opts.printer]
   * @param {number} [opts.poolId]
   * @param {number} [opts.documentId]
   * @param {string} [opts.webhookUrl]
   * @param {number} [opts.agentId]
   * @param {number} [opts.branchId]
   * @param {number} [opts.priority]
   * @param {string} [opts.scheduledAt]
   * @param {string} [opts.recurrence]
   * @param {string} [opts.recurrenceEndAt]
   * @param {number} [opts.recurrenceCount]
   */
  async printRawPdf({ documentBase64, referenceId = '', branchCode, options, printer,
    poolId, documentId, webhookUrl, agentId, branchId, priority,
    scheduledAt, recurrence, recurrenceEndAt, recurrenceCount } = {}) {
    const body = { document_base64: documentBase64 };
    if (referenceId) body.reference_id = referenceId;
    if (branchCode || this._defaultBranchCode) body.branch_code = branchCode || this._defaultBranchCode;
    if (options) body.options = options;
    if (printer != null) body.printer = printer;
    if (poolId != null) body.pool_id = poolId;
    if (documentId != null) body.document_id = documentId;
    if (webhookUrl != null) body.webhook_url = webhookUrl;
    if (agentId != null) body.agent_id = agentId;
    if (branchId != null) body.branch_id = branchId;
    if (priority != null) body.priority = priority;
    if (scheduledAt != null) body.scheduled_at = scheduledAt;
    if (recurrence != null) body.recurrence = recurrence;
    if (recurrenceEndAt != null) body.recurrence_end_at = recurrenceEndAt;
    if (recurrenceCount != null) body.recurrence_count = recurrenceCount;
    return this._post('/api/v1/print', body);
  }

  /**
   * Submit multiple print jobs in a single request.
   * @param {Object[]} jobs - Array of job objects
   */
  async printBatch(jobs) {
    const filled = jobs.map(job => {
      if (!job.branch_code && this._defaultBranchCode) {
        return { ...job, branch_code: this._defaultBranchCode };
      }
      return job;
    });
    return this._post('/api/v1/print/batch', { jobs: filled });
  }

  /**
   * Generate a PDF preview without sending to a printer.
   * @param {string} template
   * @param {Object} data
   * @param {Object} [options]
   * @returns {Promise<Buffer>} Raw PDF binary
   */
  async preview(template, data, options = {}, parameters = {}) {
    const url = `${this._baseUrl}/api/v1/preview`;
    const body = { template, data, options };
    if (parameters && Object.keys(parameters).length) body.parameters = parameters;
    const resp = await fetch(url, {
      method: 'POST',
      headers: this._headers(),
      body: JSON.stringify(body),
      signal: this._signal(),
    });
    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}));
      this._handleError(resp.status, body);
    }
    const arrayBuffer = await resp.arrayBuffer();
    return Buffer.from(arrayBuffer);
  }

  // -----------------------------------------------------------------------
  // Job Management
  // -----------------------------------------------------------------------

  /** Check the current status of a print job. */
  async jobStatus(jobId) {
    return this._get(`/api/v1/jobs/${encodeURIComponent(jobId)}`);
  }

  /** Cancel a pending print job. */
  async cancelJob(jobId) {
    return this._delete(`/api/v1/jobs/${encodeURIComponent(jobId)}`);
  }

  /**
   * Poll until a job reaches a terminal status.
   * @param {string} jobId
   * @param {number} [timeoutSeconds=30]
   * @param {number} [pollIntervalMs=500]
   */
  async waitForJob(jobId, timeoutSeconds = 30, pollIntervalMs = 500) {
    const deadline = Date.now() + timeoutSeconds * 1000;
    while (Date.now() < deadline) {
      const result = await this.jobStatus(jobId);
      const status = result.status || '';
      if (status === 'success' || status === 'failed') {
        return result;
      }
      await this._sleep(pollIntervalMs);
    }
    throw new PrintHubError(`Job ${jobId} did not complete within ${timeoutSeconds}s timeout`);
  }

  // -----------------------------------------------------------------------
  // Internal helpers
  // -----------------------------------------------------------------------

  _headers() {
    return {
      'X-API-Key': this._apiKey,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
  }

  _signal() {
    this._abortController?.abort();
    this._abortController = new AbortController();
    // Set a timeout using AbortSignal.timeout if available (Node 20+)
    if (typeof AbortSignal.timeout === 'function') {
      return AbortSignal.timeout(this._timeout);
    }
    return this._abortController.signal;
  }

  _sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  async _request(method, path, params = {}, body = undefined) {
    let url = `${this._baseUrl}${path}`;

    if (Object.keys(params).length > 0) {
      const qs = new URLSearchParams();
      for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null) qs.append(k, String(v));
      }
      const qstr = qs.toString();
      if (qstr) url += `?${qstr}`;
    }

    let lastError = null;

    for (let attempt = 0; attempt <= this._maxRetries; attempt++) {
      try {
        const resp = await fetch(url, {
          method,
          headers: this._headers(),
          body: body ? JSON.stringify(body) : undefined,
          signal: this._signal(),
        });

        if (resp.status === 429) {
          // Rate limited — retry with backoff
          const delay = this._retryDelayMs * Math.pow(2, attempt);
          console.warn(`Rate limited (attempt ${attempt + 1}/${this._maxRetries + 1}), retrying in ${delay}ms`);
          await this._sleep(delay);
          continue;
        }

        const data = await resp.json();

        if (!resp.ok) {
          this._handleError(resp.status, data);
        }

        return data;

      } catch (err) {
        if (err instanceof PrintHubError) throw err; // Already handled

        lastError = new PrintHubConnectionError(
          `Request to ${method} ${url} failed: ${err.message}`
        );

        if (attempt < this._maxRetries) {
          const delay = this._retryDelayMs * Math.pow(2, attempt);
          console.warn(`Connection error (attempt ${attempt + 1}/${this._maxRetries + 1}), retrying in ${delay}ms: ${err.message}`);
          await this._sleep(delay);
          continue;
        }
        throw lastError;
      }
    }

    throw lastError || new PrintHubError('Max retries exceeded');
  }

  async _get(path, params = {}) {
    return this._request('GET', path, params);
  }

  async _post(path, body) {
    return this._request('POST', path, {}, body);
  }

  async _put(path, body) {
    return this._request('PUT', path, {}, body);
  }

  async _delete(path) {
    return this._request('DELETE', path);
  }

  _handleError(status, body) {
    const message = body?.error?.message || body?.message || `HTTP ${status}`;
    const errors = body?.error?.errors || body?.errors || [];

    if (status === 422) {
      throw new PrintHubValidationError(message, errors);
    }
    throw new PrintHubError(message);
  }

  // -----------------------------------------------------------------------
  // Schema Management — Extended
  // -----------------------------------------------------------------------

  /** Get the diff between two versions of a schema. */
  async schemaVersionDiff(schemaName, fromVersion, toVersion) {
    return this._get(`/api/v1/schemas/${encodeURIComponent(schemaName)}/diff`, { from: fromVersion, to: toVersion });
  }

  /** Validate data against a template's schema (server-side). */
  async validateTemplateData(templateName, data) {
    return this._post(`/api/v1/templates/${encodeURIComponent(templateName)}/validate`, { data });
  }

  // -----------------------------------------------------------------------
  // Document Management
  // -----------------------------------------------------------------------

  /** Upload a document to Print Hub for later use in print jobs. */
  async uploadDocument(filename, fileData, mimeType = null) {
    const body = { filename, file_data: fileData };
    if (mimeType) body.mime_type = mimeType;
    return this._post('/api/v1/documents/upload', body);
  }

  /** List all uploaded documents. */
  async listDocuments() {
    return this._get('/api/v1/documents');
  }

  /** Get details of a specific document. */
  async getDocument(docId) {
    return this._get(`/api/v1/documents/${docId}`);
  }

  /** Preview a document (rendered as PDF). */
  async previewDocument(docId) {
    const url = `${this._baseUrl}/api/v1/documents/${docId}/preview`;
    const resp = await fetch(url, {
      method: 'GET',
      headers: this._headers(),
      signal: this._signal(),
    });
    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}));
      this._handleError(resp.status, body);
    }
    const arrayBuffer = await resp.arrayBuffer();
    return Buffer.from(arrayBuffer);
  }

  /** Download a document's raw file content. */
  async downloadDocument(docId) {
    const url = `${this._baseUrl}/api/v1/documents/${docId}/download`;
    const resp = await fetch(url, {
      method: 'GET',
      headers: this._headers(),
      signal: this._signal(),
    });
    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}));
      this._handleError(resp.status, body);
    }
    const arrayBuffer = await resp.arrayBuffer();
    return Buffer.from(arrayBuffer);
  }

  /** Delete a document. */
  async deleteDocument(docId) {
    return this._delete(`/api/v1/documents/${docId}`);
  }

  // -----------------------------------------------------------------------
  // Approvals
  // -----------------------------------------------------------------------

  /** List all print jobs pending approval. */
  async getPendingApprovals() {
    return this._get('/api/v1/approvals/pending');
  }

  /** Approve a pending print job. */
  async approveJob(jobId) {
    return this._post(`/api/v1/approvals/${encodeURIComponent(jobId)}/approve`, {});
  }

  /** Reject a pending print job. */
  async rejectJob(jobId) {
    return this._post(`/api/v1/approvals/${encodeURIComponent(jobId)}/reject`, {});
  }

  // -----------------------------------------------------------------------
  // Agent Version
  // -----------------------------------------------------------------------

  /** Get the latest available TrayPrint agent version. */
  async getAgentVersion() {
    return this._get('/api/v1/agents/version');
  }

  // -----------------------------------------------------------------------
  // Fonts
  // -----------------------------------------------------------------------

  /** List all available fonts. */
  async getFonts() {
    return this._get('/api/v1/fonts');
  }

  // -----------------------------------------------------------------------
  // Formula Editor
  // -----------------------------------------------------------------------

  /** List all available formula functions. */
  async getFormulaFunctions() {
    return this._get('/api/v1/formula/functions');
  }

  /** Validate a formula expression. */
  async validateFormula(expression) {
    return this._post('/api/v1/formula/validate', { expression });
  }

  /** Evaluate a formula expression with given context. */
  async evaluateFormula(expression, context = {}) {
    return this._post('/api/v1/formula/evaluate', { expression, context });
  }
}

  // ---------------------------------------------------------------------------
  // Preview Request Handlers (static)
  // ---------------------------------------------------------------------------

  /** @type {Map<string, Function>} */
  static previewHandlers = new Map();

  /**
   * Register a handler for a named live-preview schema.
   *
   * When Print Hub sends a preview request for this schema (via the
   * /print-hub-preview webhook endpoint), the registered async function
   * will be called with the incoming payload and must return an object
   * with a `data` property containing the live preview data.
   *
   * @param {string}   schemaName  Logical name (e.g. connector name)
   * @param {Function} handler     Async function that receives (payload) and returns { data }
   */
  static handlePreviewRequest(schemaName, handler) {
    if (typeof handler !== 'function') {
      throw new PrintHubError('handlePreviewRequest: handler must be a function');
    }
    PrintHubClient.previewHandlers.set(schemaName, handler);
  }

  /**
   * Handle an incoming preview request from Print Hub.
   *
   * Call this from your Express.js route handler for POST /print-hub-preview.
   *
   * Usage:
   *   app.post('/print-hub-preview', async (req, res) => {
   *     await PrintHubClient.handleIncomingPreviewRequest(req, res);
   *   });
   *
   * @param {import('express').Request}  req
   * @param {import('express').Response} res
   */
  static async handleIncomingPreviewRequest(req, res) {
    const payload = req.body || {};
    const schemaName = payload?.connector?.name || 'default';
    const handler = PrintHubClient.previewHandlers.get(schemaName);

    if (!handler) {
      return res.status(500).json({
        error: `No preview handler registered for schema "${schemaName}". Call PrintHubClient.handlePreviewRequest() first.`,
      });
    }

    try {
      const result = await handler(payload);
      return res.json({
        data:        result.data || [],
        received_at: new Date().toISOString(),
      });
    } catch (err) {
      return res.status(500).json({
        error: `Preview handler error: ${err.message}`,
      });
    }
  }
}
