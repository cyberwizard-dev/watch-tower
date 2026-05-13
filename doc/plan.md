# LaravelWatch: Complete System Specification & Implementation Plan

**Version:** 1.0  
**Status:** Ready for Development  
**Last Updated:** May 2026  
**Scope:** Open-Source, Self-Hosted Laravel Observability Platform

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Technology Stack](#technology-stack)
4. [Product Features Matrix](#product-features-matrix)
5. [Data Models & Database Schema](#data-models--database-schema)
6. [API Specifications](#api-specifications)
7. [Dashboard & UI Architecture](#dashboard--ui-architecture)
8. [SDK (Laravel Package) Architecture](#sdk-laravel-package-architecture)
9. [Ingestion Pipeline](#ingestion-pipeline)
10. [Security & Privacy Model](#security--privacy-model)
11. [Extensibility Framework](#extensibility-framework)
12. [Deployment Architecture](#deployment-architecture)
13. [Implementation Roadmap](#implementation-roadmap)
14. [Scaling Strategy](#scaling-strategy)
15. [Risk Assessment & Mitigation](#risk-assessment--mitigation)
16. [Success Metrics & KPIs](#success-metrics--kpis)

---

## Executive Summary

### Problem Statement
Laravel developers lack a **lightweight, self-hosted alternative** to proprietary SaaS observability tools (Sentry, DataDog, New Relic). Existing solutions are either:
- **Too expensive** (per-event pricing models)
- **Too opaque** (black-box data processing)
- **Too complex** (heavy DevOps requirements)
- **Too bloated** (unnecessary features for SMBs)

### Solution: LaravelWatch
A **production-grade, open-source observability platform** designed for Laravel developers who want:
- **Instant visibility** into production issues
- **Zero vendor lock-in** (self-hosted by default)
- **Minimal overhead** (< 5% performance impact)
- **Developer-first UX** (no DevOps required)
- **Extensible architecture** (custom collectors, processors, storage)

### Core Value Proposition
```
Install → Collect → Monitor → Debug → Scale
  <5min    Automatic  Real-time  Faster  Seamless
```

### Success Criteria
- **Installation:** < 10 minutes, zero-config bootstrap
- **Performance:** < 5% overhead on Laravel app
- **Scalability:** From hobby projects to 1M+ requests/day
- **Developer Adoption:** < 100 lines of boilerplate code
- **Community:** Active plugin/driver ecosystem

---

## System Architecture

### 1. High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                       LARAVEL APPLICATIONS                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  App A       │  │  App B       │  │  App C       │          │
│  │ (Production) │  │ (Staging)    │  │ (Dev)        │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└──────────────────────┬──────────────────────────────────────────┘
                       │ Install laravel/watch SDK
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│                    LARAVELWATCH SDK                               │
│           (Laravel Package: laravel/watch)                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Middleware   │  │ Event        │  │ Service      │          │
│  │ (HTTP)       │  │ Listeners    │  │ Providers    │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Queue        │  │ DB Query     │  │ Exception    │          │
│  │ Listeners    │  │ Listeners    │  │ Handlers     │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           Local Buffer (Redis/Memory)                     │  │
│  │         Batching & Async Processing                       │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────┬────────────────────────────────────────────────┬─┘
               │ HTTP POST (batch events)                        │
               │ API Key: app-token-xyz                          │
               │ Compression: gzip                               │
               ▼                                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│          LARAVELWATCH INGESTION API                              │
│     (Laravel + Queue-based processing)                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ POST /api/ingest                                          │  │
│  │   ├─ Authentication (API tokens)                          │  │
│  │   ├─ Rate Limiting (per app)                              │  │
│  │   ├─ Validation & Schema Check                            │  │
│  │   └─ Queue for Processing                                 │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │        Processing Queue (Redis/Beanstalkd)                │  │
│  │   Workers: Normalize → Fingerprint → Aggregate            │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────┬────────────────────────────────────────────────┬─┘
               │                                                 │
               ▼                                                 ▼
┌──────────────────────────┐                    ┌──────────────────────────┐
│    STORAGE LAYER         │                    │   CACHE LAYER            │
│  ┌────────────────────┐  │                    │  ┌────────────────────┐  │
│  │ MySQL/PostgreSQL   │  │                    │  │ Redis (Hot Data)   │  │
│  │ (Events, Traces)   │  │                    │  │ (Real-time metrics)│  │
│  └────────────────────┘  │                    │  └────────────────────┘  │
│  ┌────────────────────┐  │                    │  ┌────────────────────┐  │
│  │ ClickHouse*        │  │                    │  │ In-Memory Cache    │  │
│  │ (Analytics)        │  │                    │  │ (Aggregations)     │  │
│  └────────────────────┘  │                    │  └────────────────────┘  │
└──────────────────────────┘                    └──────────────────────────┘
               ▲                                                   ▲
               └─────────────────────┬──────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────┐
│                PROCESSING ENGINE                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Aggregations │  │ Fingerprinting│  │ Anomaly      │          │
│  │ (metrics)    │  │ (grouping)    │  │ Detection    │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Normalization│  │ Enrichment   │  │ Sampling     │          │
│  │              │  │              │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
               ▲                                                   │
               │                                                   ▼
┌──────────────────────────────────────────────────────────────────┐
│             DASHBOARD & UI (Next.js / Laravel Inertia)          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Overview     │  │ Traces       │  │ Alerts &     │          │
│  │ Dashboard    │  │ Detail View  │  │ Annotations  │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Error        │  │ Performance  │  │ Teams &      │          │
│  │ Grouping     │  │ Trends       │  │ Settings     │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└──────────────────────────────────────────────────────────────────┘

Legend:
* = Optional in MVP, core in V2
```

### 2. Data Flow (Request Lifecycle)

```
USER REQUEST
    │
    ▼
LARAVELWATCH MIDDLEWARE (capture request start)
    │ store: request_id, timestamp, method, uri, user_id
    │
    ▼
APP EXECUTION
    ├─ Database Queries → DB Query Listener
    │    └─ store: query, bindings, duration, n+1 flag
    │
    ├─ Events Fired → Event Listener
    │    └─ store: event_name, listeners_count, duration
    │
    ├─ Jobs Dispatched → Queue Listener
    │    └─ store: job_class, payload, queue, delay
    │
    └─ Exceptions → Exception Handler
         └─ store: exception_class, message, stacktrace
    │
    ▼
LARAVELWATCH MIDDLEWARE (capture response)
    │ store: status_code, response_time, memory_usage
    │
    ▼
LOCAL BUFFER (Redis / Memory)
    │ Batch & compress events
    │ Apply sampling rules
    │
    ▼
ASYNC DISPATCHER (queue)
    │ Send to ingestion API
    │ Retry on failure
    │
    ▼
INGESTION API
    │ ├─ Authenticate
    │ ├─ Rate limit
    │ └─ Queue for processing
    │
    ▼
PROCESSING QUEUE
    │ ├─ Normalize
    │ ├─ Fingerprint (error grouping)
    │ ├─ Enrich (user context, tags)
    │ └─ Aggregate metrics
    │
    ▼
STORAGE
    │ ├─ Write to MySQL/PostgreSQL (events)
    │ ├─ Write to Redis (hot metrics)
    │ └─ Write to ClickHouse (analytics)
    │
    ▼
DASHBOARD
    │ Real-time WebSocket updates
    │ Query aggregated metrics
    │ Drill-down to trace details
```

---

## Technology Stack

### Backend (Ingestion Server)
| Layer | Technology | Reason |
|-------|-----------|--------|
| **Framework** | Laravel 11+ | Native PHP ecosystem, proven at scale |
| **Queue** | Redis/Beanstalkd/Database | Flexible, performant, self-hosted |
| **API** | Laravel Sanctum + JWT | Lightweight token auth, no bloat |
| **Caching** | Redis | Hot data, real-time aggregations |
| **Primary Storage** | PostgreSQL 14+ | JSONB support, window functions, reliability |
| **Analytics Storage** | ClickHouse (optional) | Time-series optimized, cost-efficient at scale |
| **Async Jobs** | Laravel Queue | Native, no external deps for MVP |

### Frontend (Dashboard)
| Layer | Technology | Reason |
|-------|-----------|--------|
| **Framework** | Next.js 15+ **OR** Laravel + Inertia.js | React/Vue ecosystem, real-time capable |
| **Styling** | Tailwind CSS | Utility-first, consistent UI |
| **Real-time** | WebSocket (Laravel Broadcasting) | Built-in, self-hostable |
| **Charts** | Recharts / Chart.js | Lightweight, reactive |
| **State** | TanStack Query / Zustand | Efficient data fetching & caching |

### Laravel SDK (Client Package)
| Component | Technology | Reason |
|-----------|-----------|--------|
| **Package Manager** | Composer | Laravel native |
| **Middleware** | Laravel middleware | Request interception |
| **Event Listeners** | Laravel events | Native lifecycle hooks |
| **Buffer** | Redis / Memory | Low-latency batching |
| **HTTP Client** | Laravel HTTP / GuzzleHTTP | Built-in async support |

### Development & DevOps
| Tool | Purpose |
|------|---------|
| Docker | Containerization for deployment |
| Docker Compose | Local development environment |
| GitHub Actions | CI/CD pipeline |
| PHPUnit | Backend testing |
| Pest | Modern PHP testing framework |
| Vitest | Frontend unit tests |
| Cypress/Playwright | E2E testing |

---

## Product Features Matrix

### MVP (Phase 1: Months 1-2)

| Feature | Priority | Effort | Status |
|---------|----------|--------|--------|
| **SDK: Request Logging** | CRITICAL | 1 week | Core |
| **SDK: Exception Capture** | CRITICAL | 3 days | Core |
| **SDK: Queue Job Tracking** | HIGH | 5 days | Core |
| **SDK: Slow Query Detection** | HIGH | 4 days | Core |
| **Ingestion API: Events endpoint** | CRITICAL | 1 week | Core |
| **Ingestion API: Authentication** | CRITICAL | 3 days | Core |
| **Database: Event Schema** | CRITICAL | 3 days | Core |
| **Database: Fingerprinting** | HIGH | 4 days | Core |
| **Dashboard: Request Overview** | CRITICAL | 1 week | Core |
| **Dashboard: Error Grouping** | CRITICAL | 1 week | Core |
| **Dashboard: Trace Detail View** | HIGH | 5 days | Core |
| **Documentation: Getting Started** | HIGH | 3 days | Core |
| **Docker Compose Setup** | HIGH | 2 days | Core |

**MVP Scope:** Single-tenant, single-server deployment. Database-backed storage only.

### Phase 2 (Months 3-4)

| Feature | Priority |
|---------|----------|
| Multi-project isolation | HIGH |
| User authentication & RBAC | HIGH |
| Real-time WebSocket updates | HIGH |
| Performance trending (7/30/90 day) | MEDIUM |
| SDK: Event Tracking | MEDIUM |
| SDK: Custom Spans | MEDIUM |
| Annotations & notes | MEDIUM |
| Email Alerts (basic) | MEDIUM |
| Search & filtering (advanced) | MEDIUM |

### Phase 3 (Months 5-6)

| Feature | Priority |
|---------|----------|
| Distributed Tracing (OpenTelemetry) | MEDIUM |
| ClickHouse analytics backend | MEDIUM |
| Slack/Discord integration | MEDIUM |
| Anomaly detection (statistical) | LOW |
| Deployment tracking | MEDIUM |
| SDK plugins ecosystem | LOW |
| Rate limiting dashboard | MEDIUM |

### Phase 4+ (Months 7+)

| Feature |
|---------|
| AI-powered root cause analysis |
| Regex-based sampling rules |
| Custom dashboards & reports |
| Mobile app |
| Multi-tenant SaaS mode |
| On-premise licensing |

---

## Data Models & Database Schema

### Core Entities

#### 1. Projects
```sql
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    organization_id UUID NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    api_secret VARCHAR(255) NOT NULL, -- hashed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE INDEX idx_projects_org ON projects(organization_id);
CREATE INDEX idx_projects_api_key ON projects(api_key);
```

#### 2. Requests (Main Event Log)
```sql
CREATE TABLE requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    request_id VARCHAR(255) UNIQUE NOT NULL, -- correlation ID
    
    -- Request metadata
    method VARCHAR(10) NOT NULL,
    uri TEXT NOT NULL,
    status_code INT,
    user_id VARCHAR(255),
    user_email VARCHAR(255),
    
    -- Performance metrics
    duration_ms INT, -- total request time
    db_queries_count INT DEFAULT 0,
    db_time_ms INT DEFAULT 0,
    memory_used_mb INT,
    memory_peak_mb INT,
    
    -- Environment
    environment VARCHAR(50), -- production, staging, dev
    hostname VARCHAR(255),
    ip_address INET,
    user_agent TEXT,
    
    -- Request/Response data
    headers JSONB, -- request headers (sanitized)
    request_data JSONB, -- GET/POST data (sanitized)
    response_data JSONB, -- response preview (sanitized)
    
    -- Flags
    has_errors BOOLEAN DEFAULT FALSE,
    has_slow_queries BOOLEAN DEFAULT FALSE,
    is_sampled BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    indexed_at TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Critical indexes for query performance
CREATE INDEX idx_requests_project_created ON requests(project_id, created_at DESC);
CREATE INDEX idx_requests_status ON requests(project_id, status_code);
CREATE INDEX idx_requests_environment ON requests(project_id, environment);
CREATE INDEX idx_requests_duration ON requests(project_id, duration_ms DESC);
CREATE INDEX idx_requests_request_id ON requests(request_id);
CREATE INDEX idx_requests_user ON requests(project_id, user_id) WHERE user_id IS NOT NULL;
```

#### 3. Database Queries
```sql
CREATE TABLE queries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    request_id UUID NOT NULL,
    
    -- Query details
    query_type VARCHAR(50), -- SELECT, INSERT, UPDATE, DELETE
    sql TEXT NOT NULL,
    bindings JSONB,
    
    -- Performance
    duration_ms DECIMAL(10,3),
    row_count INT,
    
    -- Analysis
    is_n_plus_one BOOLEAN DEFAULT FALSE,
    n_plus_one_group_id VARCHAR(255),
    is_slow BOOLEAN DEFAULT FALSE, -- duration > threshold
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
);

CREATE INDEX idx_queries_request ON queries(request_id);
CREATE INDEX idx_queries_slow ON queries(project_id, is_slow, created_at DESC);
```

#### 4. Exceptions
```sql
CREATE TABLE exceptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    request_id UUID NOT NULL,
    
    -- Exception details
    exception_class VARCHAR(255) NOT NULL,
    message TEXT,
    stacktrace JSONB NOT NULL,
    
    -- Fingerprinting (error grouping)
    fingerprint VARCHAR(255) NOT NULL, -- hash of exception + file + line
    error_group_id UUID, -- groups similar errors
    
    -- Context
    user_id VARCHAR(255),
    user_email VARCHAR(255),
    file VARCHAR(255),
    line INT,
    
    -- Additional
    context JSONB, -- extra context data
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (error_group_id) REFERENCES error_groups(id) ON DELETE SET NULL
);

CREATE INDEX idx_exceptions_project ON exceptions(project_id, created_at DESC);
CREATE INDEX idx_exceptions_fingerprint ON exceptions(project_id, fingerprint);
CREATE INDEX idx_exceptions_group ON exceptions(error_group_id);
```

#### 5. Error Groups (Aggregation)
```sql
CREATE TABLE error_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    
    -- Identification
    fingerprint VARCHAR(255) UNIQUE NOT NULL,
    first_exception_class VARCHAR(255),
    first_message TEXT,
    
    -- Stats
    total_count INT DEFAULT 1,
    last_occurrence TIMESTAMP,
    first_occurrence TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Status
    status VARCHAR(50) DEFAULT 'unresolved', -- unresolved, resolved, ignored
    resolved_at TIMESTAMP,
    resolved_by_user_id UUID,
    
    -- Metadata
    tags JSONB, -- e.g., ["auth", "stripe"]
    assigned_to_user_id UUID,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_error_groups_project ON error_groups(project_id);
CREATE INDEX idx_error_groups_status ON error_groups(project_id, status);
```

#### 6. Queue Jobs
```sql
CREATE TABLE queue_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    
    -- Job details
    job_class VARCHAR(255) NOT NULL,
    queue VARCHAR(100),
    
    -- Lifecycle
    dispatched_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    failed_at TIMESTAMP,
    
    -- Performance
    duration_ms INT,
    
    -- Status
    status VARCHAR(50), -- pending, processing, completed, failed
    
    -- Error info (if failed)
    exception JSONB,
    
    -- Data
    payload JSONB, -- job payload (sanitized)
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_queue_jobs_project_status ON queue_jobs(project_id, status);
CREATE INDEX idx_queue_jobs_class ON queue_jobs(project_id, job_class);
```

#### 7. Events
```sql
CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    request_id UUID,
    
    -- Event details
    event_class VARCHAR(255) NOT NULL,
    fired_by VARCHAR(255),
    
    -- Performance
    duration_ms INT,
    listeners_count INT,
    
    -- Data
    payload JSONB,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
);

CREATE INDEX idx_events_request ON events(request_id);
CREATE INDEX idx_events_class ON events(project_id, event_class);
```

#### 8. Performance Metrics (Aggregated)
```sql
CREATE TABLE metrics (
    id BIGSERIAL PRIMARY KEY,
    project_id UUID NOT NULL,
    
    -- Time bucket (for aggregation)
    period_start TIMESTAMP NOT NULL, -- hourly/daily bucket
    period_end TIMESTAMP NOT NULL,
    
    -- Metrics
    requests_count INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    slow_requests_count INT DEFAULT 0, -- > 1000ms
    avg_response_time_ms DECIMAL(10,2),
    p50_response_time_ms INT,
    p95_response_time_ms INT,
    p99_response_time_ms INT,
    
    -- Database
    total_queries INT DEFAULT 0,
    avg_query_time_ms DECIMAL(10,3),
    
    -- Aggregation level
    aggregation_level VARCHAR(50), -- hour, day, minute
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE (project_id, period_start, aggregation_level)
);

CREATE INDEX idx_metrics_project_period ON metrics(project_id, period_start DESC);
```

#### 9. Users (Dashboard Access)
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL,
    
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255), -- hashed
    
    role VARCHAR(50) DEFAULT 'member', -- owner, admin, member
    
    email_verified_at TIMESTAMP,
    last_login_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE INDEX idx_users_org ON users(organization_id);
CREATE INDEX idx_users_email ON users(email);
```

#### 10. Organizations
```sql
CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    
    plan VARCHAR(50) DEFAULT 'free', -- free, starter, pro, enterprise
    retention_days INT DEFAULT 30,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Design Notes

**Rationale for Schema:**
- **UUID primaries:** Better for distributed systems, privacy
- **JSONB columns:** Flexible for schema-less event data
- **Composite indexes:** Fast queries on project_id + created_at (time-series)
- **Fingerprinting:** Enables error grouping without ML
- **Normalized errors:** error_groups table for efficient aggregation
- **Separation of concerns:** Each event type (requests, exceptions, queries) in dedicated table

**Retention & Archival Strategy:**
```
├─ Hot data (7 days): PostgreSQL (full fidelity)
├─ Warm data (8-30 days): PostgreSQL (aggregated)
├─ Cold data (31+ days): ClickHouse (analytics only)
└─ Archive (annual): S3/Backup
```

---

## API Specifications

### 1. Ingestion Endpoints

#### POST /api/ingest
Receive batched telemetry events from SDKs.

**Authentication:** Bearer token (API key)

**Rate Limits:**
- 1,000 requests/minute per project
- 100MB/minute payload per project
- Burst: 2x rate limit for 10 seconds

**Request Schema:**
```json
{
  "batch_id": "uuid", // for idempotency
  "sdk_version": "1.0.0",
  "app_version": "1.2.3",
  "timestamp": "2026-05-13T10:30:00Z",
  "events": [
    {
      "type": "request", // request, exception, query, job, event
      "id": "uuid",
      "data": { /* event-specific data */ }
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "batch_id": "uuid",
  "events_received": 45,
  "events_queued": 45
}
```

**Status Codes:**
- 202 Accepted: Valid batch, queued for processing
- 400 Bad Request: Invalid schema
- 401 Unauthorized: Invalid API key
- 429 Too Many Requests: Rate limited
- 413 Payload Too Large: Batch exceeds size limit

#### POST /api/ingest/sync
Synchronous ingestion for low-latency critical events.

**Purpose:** For exception tracking requiring immediate visibility.

**Response:**
```json
{
  "success": true,
  "event_id": "uuid",
  "group_id": "uuid" // error group ID if exception
}
```

---

### 2. Dashboard API Endpoints

#### Projects Endpoints

**GET /api/projects**
List all projects for authenticated user's organization.

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "My API",
      "slug": "my-api",
      "environment": ["production", "staging"],
      "status": "healthy",
      "requests_24h": 50000,
      "errors_24h": 12,
      "last_event_at": "2026-05-13T10:30:00Z"
    }
  ]
}
```

**POST /api/projects**
Create new project.

```json
{
  "name": "New Project",
  "description": "My new app"
}
```

**Response:**
```json
{
  "id": "uuid",
  "api_key": "pk_...",
  "api_secret": "sk_..." // shown only once
}
```

**GET /api/projects/{project_id}/settings**
Get project configuration.

---

#### Requests Endpoints

**GET /api/projects/{project_id}/requests**
List requests with filtering.

**Query Parameters:**
```
- status: 200,404,500 (comma-separated)
- environment: production,staging
- method: GET,POST,DELETE
- duration_min: 1000 (ms)
- duration_max: 5000
- has_errors: true|false
- user_id: xyz
- limit: 50 (max 100)
- offset: 0
- sort: -created_at | duration | status
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "request_id": "correlation-id",
      "method": "POST",
      "uri": "/api/users",
      "status_code": 500,
      "duration_ms": 2500,
      "user_id": "user-123",
      "environment": "production",
      "errors_count": 1,
      "queries_count": 15,
      "created_at": "2026-05-13T10:30:00Z"
    }
  ],
  "meta": {
    "total": 5000,
    "limit": 50,
    "offset": 0
  }
}
```

**GET /api/projects/{project_id}/requests/{request_id}**
Get detailed trace for single request.

**Response:**
```json
{
  "request": {
    "id": "uuid",
    "request_id": "correlation-id",
    "method": "POST",
    "uri": "/api/users",
    "status_code": 500,
    "duration_ms": 2500,
    // ... full request details
  },
  "queries": [
    {
      "id": "uuid",
      "sql": "SELECT * FROM users WHERE id = ?",
      "bindings": [123],
      "duration_ms": 45,
      "row_count": 1,
      "is_n_plus_one": true
    }
  ],
  "exceptions": [
    {
      "id": "uuid",
      "class": "ModelNotFoundException",
      "message": "User not found",
      "file": "app/Models/User.php",
      "line": 123,
      "stacktrace": [ /* frames */ ]
    }
  ],
  "events": [
    {
      "class": "UserCreated",
      "listeners_count": 3,
      "duration_ms": 150
    }
  ],
  "timeline": [ /* chronological event sequence */ ]
}
```

---

#### Exceptions/Errors Endpoints

**GET /api/projects/{project_id}/errors**
List error groups.

**Query Parameters:**
```
- status: unresolved|resolved|ignored
- tag: auth,stripe
- sort: -occurrence | -count
- limit: 50
- offset: 0
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "fingerprint": "abc123def456",
      "exception_class": "ModelNotFoundException",
      "first_message": "User not found",
      "total_count": 42,
      "unique_users": 12,
      "first_occurrence": "2026-05-10T...",
      "last_occurrence": "2026-05-13T...",
      "status": "unresolved",
      "tags": ["auth"]
    }
  ]
}
```

**GET /api/projects/{project_id}/errors/{error_group_id}**
Get error group details with occurrences.

**Response:**
```json
{
  "group": { /* error group details */ },
  "occurrences": [
    {
      "id": "uuid",
      "request_id": "uuid",
      "user_id": "user-123",
      "created_at": "2026-05-13T...",
      "stacktrace": [ /* frames */ ]
    }
  ],
  "meta": {
    "total_occurrences": 42
  }
}
```

**PATCH /api/projects/{project_id}/errors/{error_group_id}**
Update error group status.

```json
{
  "status": "resolved", // or "ignored"
  "resolution_note": "Fixed in v1.2.3"
}
```

---

#### Performance Metrics Endpoints

**GET /api/projects/{project_id}/metrics**
Get aggregated performance metrics.

**Query Parameters:**
```
- period: 24h | 7d | 30d | 90d
- granularity: minute | hour | day
- environment: production,staging
```

**Response:**
```json
{
  "metrics": [
    {
      "period_start": "2026-05-13T09:00:00Z",
      "period_end": "2026-05-13T10:00:00Z",
      "requests_count": 5000,
      "errors_count": 12,
      "error_rate": 0.24,
      "avg_response_time_ms": 125,
      "p50_response_time_ms": 80,
      "p95_response_time_ms": 450,
      "p99_response_time_ms": 1200,
      "slow_requests_count": 45
    }
  ]
}
```

**GET /api/projects/{project_id}/endpoints**
Get per-endpoint performance breakdown.

**Response:**
```json
{
  "data": [
    {
      "method": "GET",
      "uri": "/api/users",
      "requests_count": 5000,
      "errors_count": 5,
      "error_rate": 0.1,
      "avg_response_time_ms": 150,
      "p95_response_time_ms": 500,
      "p99_response_time_ms": 1200
    }
  ]
}
```

---

### 3. Authentication & Authorization

**Token Formats:**

```
API Key (SDK): pk_live_abcd1234efgh5678
API Secret (Server): sk_live_xyz987abc123
Bearer Token (Dashboard): eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Token Generation (POST /api/auth/login):**
```json
{
  "email": "dev@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "token": "bearer_token",
  "expires_in": 604800, // 7 days
  "user": { "id": "uuid", "name": "...", "email": "..." }
}
```

**API Key Rotation (POST /api/projects/{id}/rotate-keys):**
```json
{
  "new_api_key": "pk_live_...",
  "old_api_key_expires_in": 2592000 // 30 days grace period
}
```

---

### 4. Webhooks (Phase 2)

**POST /webhooks/events** (from LaravelWatch to external services)

**Events:**
- `error.new` - New unique error
- `error.spike` - Error rate spike
- `performance.degradation` - Slow endpoint detected
- `deployment.tracked` - New release tracked

**Payload:**
```json
{
  "event": "error.new",
  "project_id": "uuid",
  "timestamp": "2026-05-13T10:30:00Z",
  "data": { /* event-specific */ }
}
```

---

## Dashboard & UI Architecture

### Page Structure

```
/
├── /login → Authentication
├── /projects → Project list
└── /projects/{id}
    ├── /overview → Health dashboard
    ├── /requests → Request list + detail
    ├── /errors → Error grouping
    ├── /performance → Metrics & trends
    ├── /database → Query analysis
    ├── /jobs → Queue monitoring
    ├── /events → Event tracking
    ├── /releases → Deployment tracking
    └── /settings
        ├── /general → Project config
        ├── /api-keys → Token management
        ├── /team → Users & roles
        ├── /alerts → Alert configuration
        ├── /retention → Data retention
        └── /integrations → Slack, Discord, etc.
```

### Key UI Components

#### 1. Overview Dashboard
- **KPIs:** Requests/day, Error rate, Avg response time, P95/P99
- **Sparklines:** Last 24h trends for each metric
- **Recent errors:** Top 5 unresolved errors
- **Slowest endpoints:** Top 10 by avg response time
- **Traffic chart:** Requests over 24h with error overlay
- **Health status:** Project status summary

#### 2. Requests List
- **Sortable columns:** Method, URI, Status, Duration, User, Timestamp
- **Filters sidebar:** Status, environment, method, duration range, user
- **Row expand:** Quick stats (queries, errors, events count)
- **Click to detail:** Full trace view

#### 3. Request Detail (Trace View)
- **Timeline visualization:** Chronological event sequence
  ```
  0ms   ├─ Request start
  10ms  ├─ Query: SELECT users (12ms)
  25ms  ├─ Event: UserCreated fired
  30ms  ├─ Query: INSERT into logs (8ms)
  40ms  ├─ Event: LogCreated fired
  42ms  └─ Request end (500 error)
  ```
- **Query panel:** Full SQL + bindings + explain plan
- **Exception panel:** Stacktrace with file links
- **Context panel:** User, headers, request/response data
- **Performance breakdown:** Pie chart (Laravel vs DB vs other)

#### 4. Error Grouping View
- **Grouped exceptions:** Same fingerprint grouped together
- **Error details:**
  - Exception class, message, file:line
  - Occurrence count, affected users
  - First & last occurrence
  - Status toggle (unresolved/resolved/ignored)
  - Tags & assignment
- **Occurrence list:** Sample occurrences with request context

#### 5. Performance Trends
- **Multi-axis chart:**
  - Left Y: Response time (ms) with P50/P95/P99
  - Right Y: Request count
  - X: Time (24h, 7d, 30d, 90d)
- **Metric selection:** Toggle metrics on/off
- **Endpoint breakdown:** Tabbed comparison
- **Anomaly highlights:** Spike detection markers

#### 6. Database Query Analysis
- **Slow queries table:**
  - SQL (syntax highlighted)
  - Duration
  - Frequency
  - Avg/min/max duration
  - EXPLAIN ANALYZE link
- **N+1 detection:**
  - Grouped similar queries
  - Execution count
  - Suggested optimization
- **Query timeline:**
  - Bar chart of top queries by total time

#### 7. Settings & Configuration
- **Project settings:**
  - Name, slug, description
  - Allowed environments
  - Data retention (7/30/90 days)
  - Sampling rate (% of requests)
- **API key management:**
  - List active keys with last used
  - Regenerate / revoke
  - Key scope (read/write)
- **Team & permissions:**
  - Invite users
  - Role assignment (owner/admin/member)
  - Activity log

### Real-Time Features

**WebSocket Connection (Phase 2):**
```javascript
// Client subscribes to project events
ws = new WebSocket('wss://laravelwatch.app/ws');
ws.send(JSON.stringify({
  action: 'subscribe',
  project_id: 'uuid',
  filters: { environment: 'production' }
}));

// Server broadcasts real-time events
{
  type: 'error.new',
  group_id: 'uuid',
  exception_class: 'ModelNotFoundException',
  count: 1
}
```

**Features:**
- New error notifications
- Request count updates (live counter)
- Performance metric updates (every 30s)
- Team member activity (user joined, error resolved)

---

## SDK (Laravel Package) Architecture

### Package Structure

```
laravel/watch/
├── src/
│   ├── ServiceProvider.php          # Main service provider
│   ├── Facades/
│   │   └── Watch.php                # Facade for easy access
│   ├── Collectors/
│   │   ├── RequestCollector.php     # HTTP request lifecycle
│   │   ├── ExceptionCollector.php   # Exception handling
│   │   ├── QueryCollector.php       # Database query logging
│   │   ├── QueueCollector.php       # Job tracking
│   │   └── EventCollector.php       # Laravel events
│   ├── Middleware/
│   │   └── CaptureRequest.php       # Request start/end
│   ├── Listeners/
│   │   ├── CaptureException.php
│   │   ├── CaptureQuery.php
│   │   ├── CaptureJob.php
│   │   └── CaptureEvent.php
│   ├── Buffer/
│   │   ├── Buffer.php               # Abstract buffer interface
│   │   ├── RedisBuffer.php          # Redis implementation
│   │   └── MemoryBuffer.php         # In-memory fallback
│   ├── Transports/
│   │   ├── Transport.php            # Abstract transport
│   │   ├── HttpTransport.php        # HTTP POST transport
│   │   └── LocalTransport.php       # Local file (dev)
│   ├── Config/
│   │   └── watch.php                # Configuration schema
│   ├── Commands/
│   │   ├── PublishConfig.php        # Publish config
│   │   ├── TestConnection.php       # Test API connection
│   │   └── FlushBuffer.php          # Manual buffer flush
│   ├── Processors/
│   │   ├── Processor.php            # Abstract processor
│   │   ├── RedactProcessor.php      # Sanitize data
│   │   └── SamplingProcessor.php    # Apply sampling rules
│   ├── Support/
│   │   ├── Fingerprinter.php        # Create error fingerprints
│   │   ├── Sanitizer.php            # Redact sensitive data
│   │   └── Timer.php                # High-precision timing
│   └── Plugins/
│       └── Plugin.php               # Plugin interface
├── config/
│   └── watch.php                    # Default configuration
├── migrations/
│   └── .gitkeep                     # No migrations (server-side only)
├── tests/
│   └── ...
├── composer.json
└── README.md
```

### Core Components

#### 1. ServiceProvider

```php
namespace LaravelWatch;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/watch.php', 'watch');
        
        $this->app->singleton(Watch::class, function ($app) {
            return new Watch(
                config('watch'),
                $app->make(Buffer::class),
                $app->make(Transport::class)
            );
        });
    }

    public function boot(): void
    {
        // Register middleware
        $this->registerMiddleware();
        
        // Register listeners
        $this->registerListeners();
        
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/watch.php' => config_path('watch.php'),
        ], 'watch-config');
        
        // Register commands
        $this->commands([
            \LaravelWatch\Commands\TestConnection::class,
        ]);
    }

    private function registerMiddleware(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', 
            \LaravelWatch\Middleware\CaptureRequest::class
        );
        $this->app['router']->pushMiddlewareToGroup('api',
            \LaravelWatch\Middleware\CaptureRequest::class
        );
    }

    private function registerListeners(): void
    {
        $this->app['events']->listen(
            \Illuminate\Database\Events\QueryExecuted::class,
            \LaravelWatch\Listeners\CaptureQuery::class
        );
        
        $this->app['events']->listen(
            \Exception::class,
            \LaravelWatch\Listeners\CaptureException::class
        );
        
        // Job events (Laravel 8+)
        $this->app['events']->listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            \LaravelWatch\Listeners\CaptureJob::class
        );
    }
}
```

#### 2. Collector Interface

```php
namespace LaravelWatch\Collectors;

interface Collector
{
    /**
     * Process collected data
     */
    public function handle(array $event): array;
    
    /**
     * Determine if should be collected
     */
    public function shouldCollect(): bool;
}
```

#### 3. Request Middleware

```php
namespace LaravelWatch\Middleware;

class CaptureRequest
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        $requestId = Str::uuid();
        
        // Store in context for access in collectors
        context('watch_request_id', $requestId);
        context('watch_start_time', $startTime);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $startTime) * 1000;
        
        Watch::capture('request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'uri' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => optional(Auth::user())->id,
            'memory_used_mb' => memory_get_usage() / 1024 / 1024,
            'memory_peak_mb' => memory_get_peak_usage() / 1024 / 1024,
        ]);
        
        return $response;
    }
}
```

#### 4. Event Listener (Exception)

```php
namespace LaravelWatch\Listeners;

use Throwable;

class CaptureException
{
    public function handle(Throwable $exception): void
    {
        if (!Watch::shouldCapture()) return;
        
        Watch::capture('exception', [
            'request_id' => context('watch_request_id'),
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => $this->formatStacktrace($exception),
        ]);
    }
    
    private function formatStacktrace(Throwable $e): array
    {
        return array_map(fn($frame) => [
            'function' => $frame['function'] ?? null,
            'file' => $frame['file'] ?? null,
            'line' => $frame['line'] ?? null,
            'class' => $frame['class'] ?? null,
        ], $e->getTrace());
    }
}
```

#### 5. Buffer Implementation (Redis)

```php
namespace LaravelWatch\Buffer;

class RedisBuffer implements Buffer
{
    private $redis;
    private $key;
    
    public function __construct(string $project_id)
    {
        $this->redis = Redis::connection('watch');
        $this->key = "watch:buffer:{$project_id}";
    }
    
    public function add(array $event): void
    {
        $this->redis->rpush($this->key, json_encode($event));
        $this->redis->expire($this->key, 3600); // 1 hour TTL
    }
    
    public function flush(): array
    {
        $events = $this->redis->lrange($this->key, 0, -1);
        $this->redis->del($this->key);
        
        return array_map(fn($e) => json_decode($e, true), $events);
    }
    
    public function count(): int
    {
        return $this->redis->llen($this->key);
    }
}
```

#### 6. Transport (HTTP)

```php
namespace LaravelWatch\Transports;

class HttpTransport implements Transport
{
    private $apiUrl;
    private $apiKey;
    private $apiSecret;
    
    public function send(array $batch): bool
    {
        $payload = json_encode([
            'batch_id' => Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'events' => $batch,
        ]);
        
        $signature = hash_hmac('sha256', $payload, $this->apiSecret);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'X-Signature' => $signature,
                'Content-Encoding' => 'gzip',
            ])->post("{$this->apiUrl}/api/ingest", 
                gzencode($payload)
            );
            
            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Watch transport failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

### Configuration

```php
// config/watch.php

return [
    /*
     * Master switch for watch
     */
    'enabled' => env('WATCH_ENABLED', true),
    
    /*
     * API Configuration
     */
    'api' => [
        'url' => env('WATCH_API_URL', 'https://watch.local'),
        'key' => env('WATCH_API_KEY'),
        'secret' => env('WATCH_API_SECRET'),
        'timeout' => 5,
    ],
    
    /*
     * Buffer Configuration
     */
    'buffer' => [
        'driver' => env('WATCH_BUFFER', 'redis'), // redis, memory
        'redis' => [
            'connection' => 'watch',
        ],
        'memory' => [
            'max_events' => 1000,
            'flush_interval_ms' => 5000,
        ],
    ],
    
    /*
     * Transport Configuration
     */
    'transport' => [
        'driver' => env('WATCH_TRANSPORT', 'http'), // http, local
        'batch_size' => 100,
        'flush_interval_ms' => 10000,
        'max_retries' => 3,
    ],
    
    /*
     * Sampling Configuration
     */
    'sampling' => [
        'enabled' => true,
        'rate' => env('WATCH_SAMPLE_RATE', 1.0), // 100% by default
        'rules' => [
            // Sample exceptions at 100%, others at 50%
            ['condition' => 'has_error', 'rate' => 1.0],
            ['condition' => 'duration > 1000', 'rate' => 1.0],
        ],
    ],
    
    /*
     * Data Redaction (PII removal)
     */
    'redact' => [
        'enabled' => true,
        'patterns' => [
            'password' => '***',
            'token' => '***',
            'credit_card' => '****-****-****-****',
            'ssn' => '***-**-****',
        ],
        'headers_to_skip' => [
            'Authorization',
            'X-API-Key',
            'Cookie',
        ],
    ],
    
    /*
     * Collectors Configuration
     */
    'collectors' => [
        'request' => [
            'enabled' => true,
            'capture_headers' => true,
            'capture_body' => false, // size limit
            'body_size_limit' => 5000,
        ],
        'database' => [
            'enabled' => true,
            'slow_query_threshold_ms' => 100,
            'capture_bindings' => true,
        ],
        'exceptions' => [
            'enabled' => true,
        ],
        'queue' => [
            'enabled' => true,
            'capture_payload' => false,
        ],
        'events' => [
            'enabled' => true,
            'tracked_events' => [], // empty = all, or specific events
        ],
    ],
    
    /*
     * Environment & Release
     */
    'environment' => env('APP_ENV', 'production'),
    'release' => env('APP_VERSION'),
    'hostname' => gethostname(),
];
```

### Usage

**Installation:**
```bash
composer require laravel/watch

php artisan vendor:publish --provider="LaravelWatch\ServiceProvider" --tag="watch-config"

# Set env vars
WATCH_ENABLED=true
WATCH_API_URL=https://watch.example.com
WATCH_API_KEY=pk_...
WATCH_API_SECRET=sk_...
```

**Basic Usage (Automatic):**
```php
// After installation, everything works automatically via middleware & listeners
// No code changes needed!
```

**Custom Event Capture:**
```php
use LaravelWatch\Facades\Watch;

// Capture custom event
Watch::capture('custom_event', [
    'user_id' => auth()->id(),
    'action' => 'payment_processed',
    'amount' => 9999,
]);

// With timing
Watch::time('data_export', function () {
    // ... long operation
});

// Breadcrumbs
Watch::breadcrumb('user_action', ['action' => 'login']);
```

---

## Ingestion Pipeline

### Processing Flow

```
                    INGESTION API
                         │
                         ▼
                  [Rate Limit Check]
                         │
                    (Pass or 429)
                         │
                         ▼
                 [API Key Validation]
                         │
                    (Pass or 401)
                         │
                         ▼
              [Schema Validation]
                    JSON Errors?
                    /        \
                 Yes(400)    No(Pass)
                        \      /
                         ▼    ▼
        (Return Error) [Push to Queue]
                         │
    ┌────────────────────┼────────────────────┐
    │                    │                    │
    ▼                    ▼                    ▼
[Normalize Events] [Fingerprint] [Enrich Data]
    │                    │                    │
    └────────────────────┼────────────────────┘
                         │
                         ▼
                [Deduplication Check]
                  Duplicate?
                  /       \
                Yes       No
                │         │
          [Skip]        [Continue]
                         │
                         ▼
            [Check Error Group]
         Existing fingerprint?
            /             \
          Yes              No
           │               │
      [Increment]   [Create New Group]
         count         │
           │           ▼
           └──────[Write Events]
                  │
    ┌─────────────┼─────────────┐
    │             │             │
    ▼             ▼             ▼
[PostgreSQL] [Redis Cache] [ClickHouse*]
 Events        Hot Data      Analytics
    │             │             │
    └─────────────┼─────────────┘
                  │
                  ▼
          [Broadcast Updates]
        (WebSocket to dashboard)
```

### Queue Worker

**Job Class:**
```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessWatchEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $event,
        public string $projectId
    ) {}

    public function handle(): void
    {
        // Normalize
        $event = $this->normalize($this->event);
        
        // Fingerprint (for exceptions)
        if ($event['type'] === 'exception') {
            $event['fingerprint'] = $this->fingerprint($event);
            $event['error_group_id'] = $this->findOrCreateErrorGroup($event);
        }
        
        // Enrich
        $event['enriched_at'] = now();
        
        // Store
        $this->store($event);
        
        // Broadcast
        broadcast(new EventProcessed($this->projectId, $event));
    }

    private function normalize(array $event): array
    {
        // Apply redaction rules
        // Validate data types
        // Clean sensitive info
        return $event;
    }

    private function fingerprint(array $event): string
    {
        // Create deterministic hash for grouping
        return hash('sha256', implode('|', [
            $event['exception']['class'],
            $event['exception']['file'],
            $event['exception']['line'],
        ]));
    }

    private function store(array $event): void
    {
        match($event['type']) {
            'request' => Request::create($event),
            'exception' => Exception::create($event),
            'query' => Query::create($event),
            'job' => QueueJob::create($event),
            'event' => Event::create($event),
        };
    }
}
```

### Processing Workers (Supervisor Config)

```ini
[program:watch-events]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --queue=watch-events --tries=3 --timeout=60
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/watch-events.log

[program:watch-aggregation]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan watch:aggregate --interval=60
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/watch-aggregation.log
```

---

## Security & Privacy Model

### Data Classification

```
Level 1: Public
├─ Request method, URI path (without query params)
├─ Status codes, response times
└─ Environment name

Level 2: Sensitive
├─ User IDs (anonymized)
├─ Query counts, durations
├─ Exception classes (sanitized)
└─ Performance metrics

Level 3: Confidential (Redacted by default)
├─ Request/response bodies
├─ Query parameters & bindings
├─ Request headers (except standard)
├─ User emails/IPs
└─ Stack traces (partial)
```

### Redaction Rules

**Automatic Redaction:**
```php
'redact' => [
    'patterns' => [
        'password' => '***',
        'api_key' => '***',
        'secret' => '***',
        'token' => '***',
        'credit_card' => '****-****-****-****',
        'ssn' => '***-**-****',
        'email' => '***@***.***',
        'phone' => '***-***-****',
    ],
    'json_paths' => [
        '$.password',
        '$.api_key',
        '$.credit_card',
    ],
],
```

**Custom Redaction:**
```php
// In app's boot
Watch::redact('user_ssn', fn($value) => 
    substr($value, 0, 3) . '-**-****'
);
```

### Authentication

**API Key Structure:**
```
Format: {environment}_{key_type}_{random}
Example: pk_live_abcd1234efgh5678ijkl9101112

Environments:
- pk_live = production
- pk_test = testing
- pk_dev = development
```

**Token Validation:**
```php
class ValidateApiKey
{
    public function handle(Request $request): bool
    {
        $key = $request->bearerToken() ?? 
               $request->header('X-API-Key');
        
        if (!$key) return false;
        
        $project = Project::where('api_key', hash('sha256', $key))
                          ->first();
        
        if (!$project) return false;
        
        // Log access
        Log::info('API key used', ['project_id' => $project->id]);
        
        return true;
    }
}
```

### Encryption

**At Rest:**
```php
// Using Laravel's built-in encryption
$encrypted = Crypt::encryptString($sensitiveData);

// For sensitive event data
$event['encrypted'] = true;
$event['data'] = Crypt::encryptString($event['data']);
```

**In Transit:**
```
- HTTPS/TLS 1.3 enforced
- Certificate pinning (optional)
- Request signing with HMAC-SHA256
```

### Access Control

**RBAC Roles:**
```
Owner
├─ Full access
├─ User management
├─ Billing
└─ API key rotation

Admin
├─ Full data access
├─ Team management
├─ Alert configuration
└─ No billing changes

Member
├─ Read all data
├─ Can't change settings
└─ Can create annotations

Viewer (read-only)
└─ Read-only access to dashboard

Disable
└─ No access

```

**Row-Level Security (per Project):**
```sql
-- Users only see their organization's projects
CREATE POLICY org_isolation ON projects
  USING (organization_id = current_user_org_id());
```

### Audit Logging

```php
// Every sensitive action logged
Event::listen(UserUpdated::class, function ($event) {
    AuditLog::create([
        'user_id' => Auth::id(),
        'action' => 'user_updated',
        'model' => 'User',
        'model_id' => $event->user->id,
        'old_values' => $event->old_values,
        'new_values' => $event->new_values,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'created_at' => now(),
    ]);
});
```

**Audit Log Retention:**
- 90 days for standard actions
- 1 year for security events
- 3 years for compliance requirements

---

## Extensibility Framework

### Plugin Architecture

```
LaravelWatch
├── Drivers (Storage)
│   ├── PostgreSQL Driver (built-in)
│   ├── MySQL Driver (built-in)
│   ├── ClickHouse Driver (optional)
│   └── Custom Driver (via plugin)
│
├── Collectors (Data sources)
│   ├── RequestCollector (built-in)
│   ├── ExceptionCollector (built-in)
│   ├── DatabaseCollector (built-in)
│   └── CustomCollector (via plugin)
│
├── Processors (Data transformation)
│   ├── NormalizationProcessor (built-in)
│   ├── FingerprintingProcessor (built-in)
│   ├── RedactionProcessor (built-in)
│   └── CustomProcessor (via plugin)
│
├── Exporters (Data output)
│   ├── HttpExporter (built-in)
│   ├── LocalExporter (built-in)
│   └── S3Exporter (via plugin)
│
└── Alert Channels
    ├── EmailChannel (built-in)
    ├── SlackChannel (via plugin)
    ├── DiscordChannel (via plugin)
    └── CustomChannel (via plugin)
```

### Plugin Development

**1. Creating a Custom Collector:**

```php
namespace App\Watch\Collectors;

use LaravelWatch\Collectors\Collector;

class RedisMetricsCollector implements Collector
{
    public function shouldCollect(): bool
    {
        return config('watch.collectors.redis.enabled');
    }

    public function handle(array $event): array
    {
        if ($event['type'] !== 'request') return $event;
        
        $redis = Redis::connection();
        
        $event['redis_stats'] = [
            'memory_usage' => $redis->info('memory')['used_memory'],
            'keys_count' => $redis->dbsize(),
            'connections' => $redis->info('clients')['connected_clients'],
        ];
        
        return $event;
    }
}
```

**2. Creating a Custom Driver:**

```php
namespace App\Watch\Drivers;

use LaravelWatch\Drivers\Driver;

class S3Driver implements Driver
{
    public function store(string $table, array $data): void
    {
        $key = sprintf(
            'watch/%s/%s/%s.json',
            date('Y-m-d'),
            $table,
            $data['id']
        );
        
        Storage::disk('s3')->put($key, json_encode($data));
    }

    public function query(string $table, array $filters): array
    {
        // Implement query logic for S3 (likely async)
        // Return cached results from local DB
    }
}
```

**3. Creating a Custom Alert Channel:**

```php
namespace App\Watch\Alerts;

use LaravelWatch\Alerts\Channel;

class TelegramChannel implements Channel
{
    public function send(Alert $alert): bool
    {
        $message = sprintf(
            "🚨 %s in %s\nOccurrences: %d\n%s",
            $alert->title,
            $alert->project,
            $alert->count,
            $alert->url
        );
        
        return Http::post(
            "https://api.telegram.org/bot{$this->token}/sendMessage",
            [
                'chat_id' => config('watch.alerts.telegram.chat_id'),
                'text' => $message,
            ]
        )->successful();
    }
}
```

**4. Registering Plugin (Service Provider):**

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class WatchPluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register custom collector
        Watch::registerCollector(new RedisMetricsCollector());
        
        // Register custom driver
        Watch::registerDriver('s3', new S3Driver());
        
        // Register custom alert channel
        Watch::registerAlertChannel('telegram', new TelegramChannel());
        
        // Register custom processor
        Watch::registerProcessor(new CustomEnrichmentProcessor());
    }
}
```

### Event Hooks

**Available Hooks:**
```php
// Before event is queued
Watch::beforeCapture(function (array $event) {
    // Modify or skip event
    return $event; // or null to skip
});

// After event is processed
Watch::afterProcess(function (array $event) {
    // Log, external notification, etc.
});

// Before storage
Watch::beforeStore(function (array $event) {
    // Final modifications
    return $event;
});

// On error
Watch::onError(function (Throwable $e) {
    // Handle gracefully
    Log::error('Watch error', ['exception' => $e]);
});
```

---

## Deployment Architecture

### Recommended Stack

**Production Deployment:**
```
┌─────────────────────────────────────────────────┐
│           Load Balancer (Nginx/HAProxy)         │
└──────────────────────┬──────────────────────────┘
                       │
    ┌──────────────────┼──────────────────┐
    ▼                  ▼                  ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│ App Server  │  │ App Server  │  │ App Server  │
│  (Laravel)  │  │  (Laravel)  │  │  (Laravel)  │
│ Container 1 │  │ Container 2 │  │ Container 3 │
└──────┬──────┘  └──────┬──────┘  └──────┬──────┘
       │                │                │
       └────────────────┼────────────────┘
                        │
         ┌──────────────┼──────────────┐
         ▼              ▼              ▼
      [Redis]   [PostgreSQL]   [Queue Worker]
      (Buffer)  (Primary DB)    (Processing)
         │          │              │
         └──────────┴──────────────┘
                    │
         ┌──────────┴──────────┐
         ▼                     ▼
    [Dashboard]          [ClickHouse]
    (Next.js CDN)        (Optional)
```

### Docker Compose (Development)

```yaml
version: '3.8'

services:
  # Main Laravel application
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    environment:
      APP_ENV: local
      WATCH_ENABLED: true
      WATCH_API_URL: http://ingestion:8001
      WATCH_API_KEY: ${WATCH_API_KEY}
      WATCH_API_SECRET: ${WATCH_API_SECRET}
      DB_HOST: postgres
      REDIS_HOST: redis
      QUEUE_CONNECTION: redis
    depends_on:
      - postgres
      - redis
    volumes:
      - .:/app
    networks:
      - watch

  # Ingestion API (separate Laravel app)
  ingestion:
    build:
      context: ./ingestion-api
      dockerfile: Dockerfile
    ports:
      - "8001:8001"
    environment:
      APP_ENV: production
      DB_HOST: postgres
      REDIS_HOST: redis
      QUEUE_CONNECTION: redis
    depends_on:
      - postgres
      - redis
    networks:
      - watch

  # PostgreSQL
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: watch
      POSTGRES_USER: watch
      POSTGRES_PASSWORD: secret
    volumes:
      - pgdata:/var/lib/postgresql/data
      - ./scripts/init-db.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - watch
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U watch"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Redis (buffer + cache + queue)
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    networks:
      - watch
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5

  # Dashboard (Next.js)
  dashboard:
    build:
      context: ./dashboard
      dockerfile: Dockerfile
    ports:
      - "3000:3000"
    environment:
      NEXT_PUBLIC_API_URL: http://ingestion:8001
    depends_on:
      - ingestion
    networks:
      - watch

  # Queue worker
  worker:
    build:
      context: ./ingestion-api
      dockerfile: Dockerfile.worker
    environment:
      APP_ENV: production
      DB_HOST: postgres
      REDIS_HOST: redis
    depends_on:
      - postgres
      - redis
    networks:
      - watch
    deploy:
      replicas: 2

volumes:
  pgdata:

networks:
  watch:
    driver: bridge
```

### Kubernetes Deployment (Production)

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: watch-config
  namespace: laravelwatch
data:
  watch.env: |
    APP_ENV=production
    LOG_CHANNEL=stack
    DB_HOST=postgres-service
    REDIS_HOST=redis-service
    QUEUE_CONNECTION=redis

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: watch-ingestion
  namespace: laravelwatch
spec:
  replicas: 3
  selector:
    matchLabels:
      app: watch-ingestion
  template:
    metadata:
      labels:
        app: watch-ingestion
    spec:
      containers:
      - name: ingestion
        image: myregistry.azurecr.io/watch-ingestion:latest
        ports:
        - containerPort: 8001
        envFrom:
        - configMapRef:
            name: watch-config
        resources:
          requests:
            memory: "512Mi"
            cpu: "250m"
          limits:
            memory: "1Gi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 8001
          initialDelaySeconds: 10
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8001
          initialDelaySeconds: 5
          periodSeconds: 5

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: watch-worker
  namespace: laravelwatch
spec:
  replicas: 4
  selector:
    matchLabels:
      app: watch-worker
  template:
    metadata:
      labels:
        app: watch-worker
    spec:
      containers:
      - name: worker
        image: myregistry.azurecr.io/watch-worker:latest
        command: ["php", "artisan", "queue:work", "--queue=watch-events"]
        envFrom:
        - configMapRef:
            name: watch-config
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
          limits:
            memory: "512Mi"
            cpu: "250m"
```

---

## Implementation Roadmap

### Phase 1: MVP (Weeks 1-8)

**Sprint 1 (Weeks 1-2): Foundation**
- [ ] Project setup & infrastructure (Docker Compose)
- [ ] Database schema creation
- [ ] Basic API scaffold (Laravel)
- [ ] Authentication middleware
- **Deliverable:** Deployed skeleton

**Sprint 2 (Weeks 3-4): SDK Development**
- [ ] Laravel package (laravel/watch)
- [ ] Request middleware
- [ ] Exception listener
- [ ] Local buffer (Redis)
- [ ] Transport (HTTP)
- **Deliverable:** SDK can capture events

**Sprint 3 (Weeks 5-6): Ingestion & Storage**
- [ ] Ingestion API endpoint
- [ ] Event queue & workers
- [ ] Normalize processor
- [ ] Store events to PostgreSQL
- [ ] Error fingerprinting
- **Deliverable:** Full pipeline (SDK → Ingestion → DB)

**Sprint 4 (Weeks 7-8): Dashboard MVP**
- [ ] Basic dashboard UI (Next.js)
- [ ] Request list view
- [ ] Error grouping view
- [ ] Trace detail view
- **Deliverable:** Full end-to-end monitoring

**MVP Completion Criteria:**
- ✅ SDK installable via Composer in < 5 minutes
- ✅ Works zero-config on fresh Laravel app
- ✅ Captures requests, exceptions, queries
- ✅ Dashboard shows real data
- ✅ < 5% performance overhead
- ✅ Documentation complete

---

### Phase 2: Multi-Project & RBAC (Weeks 9-12)

**Sprint 5: Multi-Tenancy**
- [ ] Organizations table
- [ ] Multi-project isolation
- [ ] Project settings API
- [ ] API key management
- [ ] Organization switching in dashboard

**Sprint 6: Authentication & Teams**
- [ ] User registration/login
- [ ] Email verification
- [ ] RBAC (owner/admin/member)
- [ ] Team invitation
- [ ] Activity audit log

**Sprint 7: Real-Time & Advanced Filtering**
- [ ] WebSocket server (Laravel Broadcasting)
- [ ] Real-time notifications
- [ ] Advanced search/filtering
- [ ] Saved filters
- [ ] Export to CSV

**Phase 2 Deliverables:**
- Multi-user, multi-project platform
- Team collaboration features
- Real-time dashboard updates

---

### Phase 3: Advanced Monitoring (Weeks 13-18)

**Sprint 8: Performance Analytics**
- [ ] 7/30/90-day trends
- [ ] Endpoint-level breakdown
- [ ] Performance regression detection
- [ ] Percentile (P50/P95/P99) calculations
- [ ] Heatmaps

**Sprint 9: Database & Queue Insights**
- [ ] Query analysis page
- [ ] N+1 detection improvements
- [ ] EXPLAIN ANALYZE integration
- [ ] Queue job detail page
- [ ] Job retry tracking

**Sprint 10: Event Tracking & Custom Spans**
- [ ] Event lifecycle tracking
- [ ] Custom event API
- [ ] Custom spans/breadcrumbs
- [ ] Correlation IDs for distributed requests

**Phase 3 Deliverables:**
- Full-featured monitoring platform
- Advanced analytics
- Extensible event tracking

---

### Phase 4: Integrations & Notifications (Weeks 19-24)

**Sprint 11: Alert System**
- [ ] Alert rules engine
- [ ] Condition builders (error rate > 5%, etc.)
- [ ] Alert history
- [ ] Alert suppression/grouping

**Sprint 12: External Integrations**
- [ ] Slack alerts
- [ ] Discord webhooks
- [ ] Email notifications
- [ ] PagerDuty integration
- [ ] Opsgenie integration

**Sprint 13: Deployment Tracking**
- [ ] Release tracking API
- [ ] Deployment markers on charts
- [ ] Error rate per release
- [ ] Rollback detection
- [ ] GitHub/GitLab integration

**Phase 4 Deliverables:**
- Production-ready alerting
- Integrated with team tools
- Release tracking

---

### Phase 5: Enterprise & Scale (Weeks 25+)

**Sprint 14-16: Advanced Analytics & AI**
- [ ] ClickHouse backend integration
- [ ] Advanced retention policies
- [ ] Custom dashboards
- [ ] Anomaly detection (statistical)
- [ ] Root cause analysis (basic ML)

**Sprint 17-18: Multi-Tenant SaaS**
- [ ] Billing integration (Stripe)
- [ ] Usage tracking & quotas
- [ ] Team seat management
- [ ] Data export & compliance
- [ ] White-label option

**Phase 5 Deliverables:**
- Enterprise-scale platform
- Advanced analytics
- SaaS-ready multi-tenant

---

### Timeline Summary

| Phase | Duration | Key Features | Status |
|-------|----------|--------------|--------|
| **1: MVP** | 8 weeks | Core monitoring | Ready |
| **2: Multi-Project** | 4 weeks | Teams, RBAC, Real-time | Ready |
| **3: Advanced** | 6 weeks | Analytics, Custom events | Ready |
| **4: Integrations** | 6 weeks | Alerts, Webhooks, Deployment | Ready |
| **5: Enterprise** | 8+ weeks | ClickHouse, AI, SaaS | Ready |
| **TOTAL** | 32+ weeks | Full Platform | Ready |

---

## Scaling Strategy

### Database Optimization

**Sharding (1M+ requests/day):**
```sql
-- Shard by project_id
-- Shard key: project_id % 16

-- Routing logic
SELECT shard_number FROM project_shards
WHERE project_id = $1;

-- Table naming
requests_shard_0, requests_shard_1, ..., requests_shard_15

-- Automatic sharding in app
class Request {
    public function getTable() {
        $shard = $this->project_id % 16;
        return "requests_shard_{$shard}";
    }
}
```

**Partitioning (Time-series):**
```sql
CREATE TABLE requests (
    ...
) PARTITION BY RANGE (created_at) (
    PARTITION p_2026_01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p_2026_02 VALUES LESS THAN ('2026-03-01'),
    ...
);

-- Auto-drop old partitions
CREATE EVENT drop_old_partitions
ON SCHEDULE EVERY 1 DAY
DO
  ALTER TABLE requests DROP PARTITION p_2025_01;
```

**Indexes for Scale:**
```sql
-- Composite indexes for common queries
CREATE INDEX idx_requests_project_env_created 
  ON requests(project_id, environment, created_at DESC);

-- Partial indexes for specific conditions
CREATE INDEX idx_slow_requests 
  ON requests(project_id, created_at DESC) 
  WHERE duration_ms > 1000;

-- BRIN indexes for time-series data (lower overhead)
CREATE INDEX idx_requests_created_brin 
  ON requests USING BRIN (created_at);
```

### Caching Strategy

**Redis Cache Levels:**
```
Level 1: Request-level (within request)
├─ Compiled config
├─ User permissions
└─ Feature flags

Level 2: Session-level (1 hour TTL)
├─ Project settings
├─ API key metadata
└─ User preferences

Level 3: Application-level (24 hour TTL)
├─ Aggregated metrics
├─ Error group stats
└─ Leaderboards

Level 4: CDN-level (browser cache, 5min)
├─ Dashboard static assets
├─ Public API responses
└─ Historical data
```

**Cache Invalidation:**
```php
// When new event arrives, invalidate relevant cache
Event::listen(EventProcessed::class, function ($event) {
    Cache::tags(['project:' . $event->project_id])
         ->flush();
         
    // More granular
    Cache::forget("metrics:{$event->project_id}:24h");
    Cache::forget("error_groups:{$event->project_id}");
});
```

### Processing Optimization

**Batching & Buffering:**
```
┌─────────────────────────────────────┐
│ Ingestion API (stateless)           │
│ - Fast validation                   │
│ - Queue events                      │
└──────────────┬──────────────────────┘
               │ 100 events per batch
               ▼
    ┌──────────────────────┐
    │ Processing Queue     │
    │ - Normalize          │
    │ - Fingerprint        │
    │ - Enrich             │
    └──────────┬───────────┘
               │ Batch every 5s or 1000 events
               ▼
        ┌──────────────┐
        │ Storage      │
        │ (PostgreSQL) │
        └──────────────┘
```

**Worker Scaling:**
```
Target: < 2 second latency from ingestion to dashboard

Load: 1M requests/day = 11.6 req/sec
Processing time per event: ~50ms
Required workers: 11.6 * 0.050 = 0.58 → 2 workers minimum

At scale (100M/day):
1,160 req/sec * 0.050s = 58 workers needed

Recommendation: Auto-scale workers 2-100 based on queue depth
```

**Queuing Strategy:**
```
High Priority:
├─ Exceptions
└─ High-value events (errors, slow queries)
  
Medium Priority:
├─ Normal requests
├─ Events
└─ Jobs

Low Priority:
└─ Analytics aggregation (off-peak)

Queue config:
watch-events (high): 4 workers, process every 100ms
watch-aggregation (low): 1 worker, process hourly
```

### Storage Scaling

**PostgreSQL Limits:**
- Single instance: ~100k req/sec with proper indexing
- Beyond: Use read replicas + sharding

**ClickHouse (Optional, 100M+ events/day):**
```sql
-- Create distributed table
CREATE TABLE requests_distributed AS
SELECT * FROM requests
WHERE 1=0;

-- Engine setup for clustering
CREATE TABLE requests ON CLUSTER default AS
SELECT *
FROM requests_local
ENGINE = ReplicatedMergeTree(...)
ORDER BY (project_id, created_at DESC);
```

**Archive Strategy:**
```
Hot (0-7 days):      PostgreSQL [full queries]
Warm (8-30 days):    PostgreSQL [aggregated]
Cold (31-365 days):  ClickHouse [analytics]
Archive (1+ years):  S3 [compliance]

Automatic archival process:
- Daily: Move 30-day-old data to ClickHouse
- Monthly: Archive ClickHouse data to S3
```

### Horizontal Scaling

**Load Balancer Config (Nginx):**
```nginx
upstream watch_ingestion {
    least_conn; # Balance by active connections
    
    server ingestion-1:8001 weight=1;
    server ingestion-2:8001 weight=1;
    server ingestion-3:8001 weight=1;
    
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name api.laravelwatch.com;
    
    location /api/ingest {
        proxy_pass http://watch_ingestion;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_read_timeout 10s;
        proxy_connect_timeout 5s;
        
        # Rate limiting
        limit_req zone=ingest burst=1000 nodelay;
    }
}
```

---

## Risk Assessment & Mitigation

### Identified Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| **Data Loss** | Low | Critical | - Automated backups (hourly) <br> - Replication to standby <br> - Point-in-time recovery <br> - S3 archive |
| **Performance Degradation** | Medium | High | - Caching strategy <br> - Read replicas <br> - Query optimization <br> - Load testing |
| **SDK Breaking Changes** | Medium | Medium | - Semantic versioning <br> - Deprecation warnings (1 version) <br> - Migration guides <br> - Backward compatibility |
| **Data Privacy Breach** | Low | Critical | - Encryption at rest/transit <br> - Regular security audits <br> - PII redaction <br> - Compliance (GDPR, SOC2) |
| **Vendor Lock-in (ClickHouse)** | Medium | Medium | - PostgreSQL as primary <br> - Optional ClickHouse <br> - Custom driver interface <br> - Data export tools |
| **High Latency Ingestion** | Medium | Medium | - Async processing <br> - Local buffering <br> - Batching optimization <br> - Edge servers (future) |
| **Storage Explosion** | Medium | High | - Aggressive sampling <br> - Short retention default <br> - Archival strategy <br> - Quotas per project |

### Mitigation Strategies

**Data Loss Prevention:**
```sql
-- Master-Master replication setup
-- PostgreSQL Streaming Replication

-- Primary server
wal_level = replica
max_wal_senders = 10
wal_keep_segments = 64

-- Automated backups
0 2 * * * pg_dump -h localhost watch | gzip > /backups/watch-$(date +\%Y\%m\%d).sql.gz

-- Point-in-time recovery test (monthly)
0 3 1 * * /scripts/test-pitr.sh
```

**Performance Monitoring:**
```php
// Set up performance budgets
class PerformanceBudget {
    const MAX_INGESTION_LATENCY_MS = 100; // p95
    const MAX_QUERY_LATENCY_MS = 500; // p95
    const MAX_WORKER_QUEUE_DEPTH = 10000;
    const MAX_MEMORY_USAGE_MB = 1000;
}

// Alert if budgets exceeded
Event::listen(QueueDepthExceeded::class, function ($event) {
    if ($event->depth > PerformanceBudget::MAX_WORKER_QUEUE_DEPTH) {
        Notification::send(
            User::admins(),
            new AlertNotification("Queue depth critical: {$event->depth}")
        );
    }
});
```

**SDK Backward Compatibility:**
```php
// Deprecate old method gradually
class Watch {
    /**
     * @deprecated v2.0.0 Use capture() instead
     */
    public static function event(string $name, array $data): void
    {
        trigger_deprecation('laravel/watch', '1.5.0', 
            'Watch::event() is deprecated, use Watch::capture() instead');
        
        self::capture('event', $data);
    }
}

// Supported: v1.0.0, v1.5.0 (deprecated in 1.5.0)
// Removed: v2.0.0
```

---

## Success Metrics & KPIs

### Product Metrics

| KPI | Target | Owner | Measurement |
|-----|--------|-------|-------------|
| **Install Time** | < 10 minutes | Product | Time from `composer require` to first event |
| **Performance Overhead** | < 5% | Eng | % increase in response time with SDK |
| **SDK Adoption** | 1000+ downloads/month | Product | Composer statistics |
| **Dashboard Latency** | < 1 second (p95) | Eng | Dashboard API response time |
| **Error Grouping Accuracy** | > 95% | Eng | Fingerprint collision rate |
| **Data Freshness** | < 10 seconds (p95) | Eng | Event arrival to dashboard visibility |
| **Uptime** | 99.9% | Ops | Infrastructure monitoring |
| **Data Retention** | 30 days (free), 90+ (paid) | Eng | Database size/cleanup jobs |

### User Adoption Metrics

| Metric | Target |
|--------|--------|
| **Active Projects** | 500+ in year 1 |
| **Users** | 5000+ in year 1 |
| **Events Processed/Day** | 10M+ in year 1 |
| **Dashboard Sessions** | 10K/day in year 1 |
| **Customer Satisfaction (NPS)** | > 50 |
| **Support Response Time** | < 24 hours |

### Technical Metrics

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| **Ingestion Latency (p95)** | 100ms | 200ms | 500ms |
| **Database Query Latency (p95)** | 50ms | 100ms | 500ms |
| **Queue Depth** | < 1K | 5K | 10K+ |
| **Error Rate** | < 0.1% | 0.5% | 1%+ |
| **Cache Hit Ratio** | > 80% | 70% | 50% |
| **Worker CPU Usage** | < 60% | 70% | 85%+ |
| **Memory Usage** | < 500MB | 700MB | 1000MB+ |

### Business Metrics

| Metric | Target | Rationale |
|--------|--------|-----------|
| **Customer Acquisition Cost** | < $100 | Sustainable growth |
| **Customer Lifetime Value** | > $2000 | Long-term viability |
| **Churn Rate** | < 5%/month | Retention focus |
| **Feature Utilization** | > 70% | Value delivery |
| **Community Contributions** | 50+ in year 1 | Ecosystem health |

### Monitoring & Alerting

**Prometheus Metrics (exported from Laravel):**
```php
// Key metrics to export
'metrics' => [
    'watch_ingestion_requests_total' => 'Counter',
    'watch_ingestion_request_duration_ms' => 'Histogram',
    'watch_processing_queue_depth' => 'Gauge',
    'watch_database_query_duration_ms' => 'Histogram',
    'watch_storage_requests_total' => 'Counter',
    'watch_cache_hit_ratio' => 'Gauge',
],
```

**Alert Rules:**
```yaml
# Alerting rules (Prometheus)
groups:
  - name: watch.rules
    rules:
      - alert: HighIngestionLatency
        expr: histogram_quantile(0.95, watch_ingestion_request_duration_ms) > 500
        for: 5m
        annotations:
          summary: "Ingestion latency high"
          
      - alert: QueueBacklog
        expr: watch_processing_queue_depth > 10000
        for: 10m
        annotations:
          summary: "Processing queue backing up"
```

---

## Conclusion

LaravelWatch is designed to be the **default choice for Laravel observability** by combining:

1. **Ease of Use:** Install, configure, monitor (< 10 minutes)
2. **Developer Experience:** Zero-config by default, extensible when needed
3. **Performance:** Minimal overhead, optimized for scale
4. **Transparency:** Self-hosted, full control, no vendor lock-in
5. **Community:** Open-source, plugin ecosystem, active development

### Success Definition

✅ LaravelWatch achieves success when:
- Developers choose it over Sentry/DataDog for self-hosted deployments
- SMBs save 50%+ on monitoring costs vs. SaaS alternatives
- Enterprise teams run it on-premise without DevOps headache
- Plugin ecosystem thrives with 50+ community extensions
- 10K+ active projects monitoring billions of events monthly

### Next Steps

1. **Week 1-2:** Initialize repo structure, Docker setup
2. **Week 3-4:** Build SDK (core collectors + buffer + transport)
3. **Week 5-6:** Build ingestion API (validation, storage, queue)
4. **Week 7-8:** Build dashboard (Next.js, basic UI)
5. **Week 9+:** Iterate based on user feedback

---

**Document prepared for:** Architecture Review & Implementation Planning  
**Status:** Ready for development  
**Last Reviewed:** May 2026