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
   * @param {string} opts.template    - Template name
   * @param {Object} opts.data        - Field values
   * @param {string} [opts.referenceId] - Your reference ID
   * @param {string} [opts.branchCode]  - Target branch
   * @param {Object} [opts.options]     - Print options
   */
  async printWithTemplate({ template, data, referenceId = '', branchCode, options } = {}) {
    const body = { template, data };
    if (referenceId) body.reference_id = referenceId;
    if (branchCode || this._defaultBranchCode) body.branch_code = branchCode || this._defaultBranchCode;
    if (options) body.options = options;
    return this._post('/api/v1/print', body);
  }

  /**
   * Print a raw base64-encoded PDF without using a template.
   * @param {Object} opts
   * @param {string} opts.base64Pdf   - Base64-encoded PDF content
   * @param {string} [opts.referenceId]
   * @param {string} [opts.branchCode]
   * @param {Object} [opts.options]
   * @param {string} [opts.printerName]
   */
  async printRawPdf({ base64Pdf, referenceId = '', branchCode, options, printerName } = {}) {
    const body = { base64_pdf: base64Pdf };
    if (referenceId) body.reference_id = referenceId;
    if (branchCode || this._defaultBranchCode) body.branch_code = branchCode || this._defaultBranchCode;
    if (options) body.options = options;
    if (printerName) body.printer_name = printerName;
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
  async preview(template, data, options = {}) {
    const url = `${this._baseUrl}/api/v1/preview`;
    const resp = await fetch(url, {
      method: 'POST',
      headers: this._headers(),
      body: JSON.stringify({ template, data, options }),
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
}
