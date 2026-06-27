# Data Connectivity Architecture — Print Designer

## Current Architecture Analysis

### How Data Flows Today

```
┌─────────────────────────────────────────────────────────────────┐
│ Client App (External System)                                    │
│                                                                 │
│  1. Register Schema ──────────────────► POST /api/v1/schema     │
│     { fields: {...}, tables: {...} }        │                   │
│                                             ▼                   │
│  2. Submit Print Job ────────────────────► POST /api/v1/print   │
│     { template: "invoice",                  │                   │
│       data: { customer: {...},              │                   │
│               items: [...] } }              │                   │
│                                             ▼                   │
│                                    ┌──────────────────┐         │
│                                    │ Print Hub Server │         │
│                                    │                  │         │
│                                    │ Generate PDF     │         │
│                                    │ (resolve {field} │         │
│                                    │  placeholders)   │         │
│                                    └────────┬─────────┘         │
│                                             ▼                   │
│  3. Agent Prints ◄─────── Pulls job with options + PDF          │
└─────────────────────────────────────────────────────────────────┘
```

### What Works

| Feature | Current Support |
|---------|----------------|
| Schema Registration | ✅ Client apps define field types, tables, versions |
| Schema Versioning | ✅ Auto-versioning with changelog/diff |
| Template Designer | ✅ Drag-and-drop canvas with sections |
| Field Binding | ✅ `{field_name}` placeholders resolved at render time |
| Table Data | ✅ Repeating rows with auto page breaks |
| Validation | ✅ Schema-based field validation |

### The Core Problem

**The client app is responsible for fetching AND formatting all data before sending.** This creates tight coupling:

1. **Schema Drift**: If the template designer adds a field, the client app must be updated to provide it
2. **Data Duplication**: The client app fetches from its DB, serializes to JSON, sends to Print Hub — round-trip overhead
3. **No Query Capability**: Templates can't request data — they can only use what's given
4. **Business Logic Split**: Some formatting happens client-side, some in template formulas — hard to maintain

---

## Crystal Reports Approach (Reference)

Crystal Reports connects to data differently:

```
┌─────────────────────────────────────────────────────────────┐
│ Crystal Reports Designer                                    │
│                                                             │
│  Report File (.rpt) contains:                               │
│  ├─ SQL Query / Stored Procedure call                       │
│  ├─ Table links / Joins                                     │
│  ├─ Parameter definitions                                   │
│  └─ Layout + formatting                                     │
│                                                             │
│  At Runtime:                                                │
│  App passes parameters only (e.g. "OrderID=123")            │
│  Crystal executes the embedded query against the DB         │
│  Returns rendered report                                    │
└─────────────────────────────────────────────────────────────┘
```

Key differences:
- **Report owns the query** — not the application
- **Application just passes context** (parameters)
- **Data is fetched at render time** — always fresh
- **Schema is the database schema** — not a separately maintained definition

---

## Proposed: Hybrid Data Resolution Architecture

### Concept

Instead of the client app sending ALL data, introduce a **Data Connector** layer where:

1. **Client apps register "data endpoints"** — URLs or SQL connections that can return data
2. **Templates declare "data sources"** — what endpoints they need and what parameters they expect
3. **Server resolves data at render time** — calls the endpoints, merges results, renders the template
4. **Backward compatible** — client apps can still send data directly (current approach)

### Architecture

```
                         ┌──────────────────────────────────────┐
                         │        Print Hub Server              │
                         │                                      │
┌──────────────┐        │  ┌──────────────┐  ┌──────────────┐  │
│ Client App 1 │───API──┼─▶│ Data Router  │──│ PDF Engine   │  │
│ (ERP System) │        │  │              │  │              │  │
└──────────────┘        │  │ 1. Check job │  │ Render with  │  │
                        │  │    for data  │  │ resolved     │  │
┌──────────────┐        │  │ 2. If context│  │ data         │  │
│ Client App 2 │───API──┼─▶│    provided, │  └──────┬───────┘  │
│ (CRM System) │        │  │    call      │         │          │
└──────────────┘        │  │    connector │         ▼          │
                        │  │ 3. If data   │  ┌──────────────┐  │
                        │  │    provided, │  │ Agent prints │  │
                        │  │    use as-is │  └──────────────┘  │
                        │  └──────┬───────┘                    │
                        │         │                            │
                        │         ▼                            │
                        │  ┌──────────────┐                    │
                        │  │ Data         │                    │
                        │  │ Connectors   │                    │
                        │  │              │                    │
                        │  │ • API Call   │──► Client App API  │
                        │  │ • SQL Query  │──► Client DB       │
                        │  │ • Static     │──► Inline data     │
                        │  └──────────────┘                    │
                        └──────────────────────────────────────┘
```

### Data Flow Options

#### Option 1: Direct Data (Current — Keep for backward compat)
```
POST /api/v1/print
{
  "template": "invoice",
  "data": { "customer": {...}, "items": [...] }  ← Full data payload
}
```

#### Option 2: Context-Based (NEW — Crystal-like)
```
POST /api/v1/print
{
  "template": "invoice",
  "context": {
    "order_id": "ORD-12345"                     ← Only the identifier
  },
  "data_sources": {
    "orders": { "endpoint": "orders_api" }      ← Which connector to use
  }
}
```

The server:
1. Looks up the template's data source requirements
2. Finds the registered connector for `orders_api` on this client app
3. Calls the connector with `{order_id: "ORD-12345"}`
4. Merges returned data into the template data
5. Renders and prints

#### Option 3: Hybrid (Mix of both)
```
POST /api/v1/print
{
  "template": "invoice",
  "data": {
    "user": { "name": "John", "email": "john@..." }  ← Provided directly
  },
  "context": {
    "order_id": "ORD-12345"                          ← Fetched via connector
  }
}
```

---

## Component Design

### 1. Data Connector Registry

New model and API for client apps to register data connectors:

```json
// POST /api/v1/connectors
{
  "name": "orders_api",
  "type": "http",                    // http | sql | static
  "config": {
    "url": "https://erp.example.com/api/orders/{order_id}",
    "method": "GET",
    "auth_type": "api_key",          // api_key | bearer | basic | none
    "headers": {
      "X-API-Key": "{client_app.api_key}"
    },
    "response_map": {                // Map API response to Print Hub data structure
      "fields": "$.customer",
      "tables": {
        "items": "$.line_items"
      }
    }
  },
  "parameters": [                    // What the connector needs to fetch data
    { "name": "order_id", "type": "string", "required": true }
  ]
}
```

### 2. Template Data Source Binding

In the template designer, a new "Data Sources" panel where designers:
- Select which data source(s) the template requires
- Map fields from each source to template elements
- Define parameter bindings (which job context fields map to which data source params)

```json
// In template definition
"data_sources": [
  {
    "connector_name": "orders_api",
    "alias": "orders",
    "parameter_bindings": {
      "order_id": "{context.order_id}"    // Map context.order_id → orders_api.order_id
    }
  },
  {
    "connector_name": "customers_db",
    "alias": "customers",
    "parameter_bindings": {
      "customer_id": "{orders.customer_id}"  // Chain: use order result → customer query
    }
  }
]
```

### 3. Data Resolution Pipeline

In [`ContinuousFormEngine::generate()`](app/Services/ContinuousFormEngine.php:36), before rendering:

```
1. Check if job has 'context' field (new) or 'data' field (legacy)
2. If context:
   a. Load template's data_sources configuration
   b. For each data source:
      - Resolve parameters from context + already-fetched data
      - Call connector with resolved parameters
      - Merge returned data into the working data set
3. If data (legacy):
   - Use as-is (current behavior)
4. If both: merge context-fetched data first, then override with direct data
5. Render template with merged data
```

### 4. SQL Connector (Advanced)

For client apps that grant database access:

```json
{
  "name": "orders_db",
  "type": "sql",
  "config": {
    "connection": "pgsql://user:pass@host:5432/dbname",
    "query": "SELECT * FROM orders WHERE id = :order_id",
    "tables": {
      "items": "SELECT * FROM order_items WHERE order_id = :order_id"
    }
  }
}
```

**Security**: SQL connectors should be restricted to on-premise deployments or VPN-only connections. API-based connectors are preferred for cloud setups.

---

## Implementation Phases

### Phase 1: Foundation (Data Connector Registry)
- New `DataConnector` model + migration
- CRUD API: `POST/GET/PUT/DELETE /api/v1/connectors`
- Admin UI for managing connectors
- Support only `http` type initially

### Phase 2: Template Data Source Binding
- Add `data_sources` JSON field to `PrintTemplate`
- Data Sources panel in the template designer UI
- Drag-and-drop field mapping from sources to template elements

### Phase 3: Server-Side Data Resolution
- Modify `ContinuousFormEngine::generate()` to resolve data sources before rendering
- HTTP connector execution engine (cURL/Guzzle)
- Response transformation / field mapping
- Error handling (connector timeout, invalid response, missing params)

### Phase 4: Advanced Connectors & Caching
- SQL connector type (with query parameterization)
- Response caching (TTL-based, to avoid repeated fetches)
- Connector chaining (use result from one connector as input to another)
- Connector testing UI (test a connector from the admin panel)

---

## Comparison: Before vs After

| Aspect | Current | Proposed |
|--------|---------|----------|
| **Client App Responsibility** | Fetch + format + send all data | Pass context/params only |
| **Data Freshness** | At time of API call | At time of rendering |
| **Template Changes** | Client app must update to add fields | Only template designer needs update |
| **Multiple Data Sources** | Client must merge before sending | Server resolves from multiple endpoints |
| **Schema Maintenance** | Manual, separate | Auto-discovered from connector responses |
| **Security** | Client controls all data access | Client controls via connector auth |
| **Complexity** | Simple but rigid | More flexible but needs setup |
| **Backward Compat** | — | Yes, direct data still works |

---

## Key Design Decisions

### Decision 1: Pull vs Push

**Crystal Reports uses Pull** (report queries the database). This requires the server to have DB credentials for every client app.

**Recommendation: Hybrid** — support both:
- **Push** (client sends data) — for simple cases, backward compat
- **Pull via HTTP** (server calls client's API) — for most integrations
- **Pull via SQL** (server queries client's DB) — for on-premise power users

### Decision 2: When to Fetch Data

Two options:
- **At job creation time**: Fetch data when `POST /api/v1/print` is called
- **At rendering time**: Fetch data when the agent pulls the job

**Recommendation: At job creation time** — simpler, data is captured at submission time, consistent with current behavior. The fetched data becomes part of the job record.

### Decision 3: Connector Security

- HTTP connectors use the client app's existing API key for authentication
- SQL connectors require explicit allow-listing in server config
- All connector calls are logged for audit
- Timeouts and retry limits prevent hung jobs

---

## Migration Path

1. Current client apps continue working unchanged (direct data)
2. New client apps can choose to use connectors
3. Existing templates remain unchanged
4. Template designers can optionally add data source bindings to existing templates
5. The `context` field is added to the print API as optional
