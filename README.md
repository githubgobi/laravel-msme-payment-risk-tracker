# MSME Payment Risk Tracker

**Section 43B(h) Compliance — Don't lose your tax deduction.**

A production-grade SaaS application that helps Indian businesses track payment deadlines to Udyam-registered Micro/Small suppliers, prevent tax disallowance under Section 43B(h) of the Income Tax Act, and automate alerts before deadlines are missed.

---

## Business Problem

Under Section 43B(h), if a buyer does not pay a Udyam-registered Micro/Small supplier within:
- **15 days** — when no written agreement exists
- **45 days** — when a written agreement exists (maximum under MSME Act)

...the unpaid expense is **disallowed** and added back to taxable income, plus **non-deductible compound interest at 3× the RBI bank rate** (~18–19% p.a. at the current 6.75% bank rate), compounded monthly.

Most SMEs track this in Excel and discover exposure only at March year-end. A single missed deadline on a ₹50 lakh invoice can add ₹15–20 lakh to the tax bill.

---

## Target Customers

- Finance/accounts managers and proprietors at Indian businesses with ₹5cr–₹100cr turnover
- Manufacturing, trading, and textile companies buying from many small vendors
- CA firms managing payables for multiple clients (white-label multi-client dashboard)

---

## Revenue Model

| Plan | Price | Vendor Limit |
|---|---|---|
| Starter | ₹1,500/mo | Up to 50 vendors |
| Growth | ₹3,000/mo | Up to 200 vendors |
| CA Firm | ₹4,000/mo | Up to 10 client businesses |

Target: 150 customers → ₹3–6 lakh/month. 400 customers → ₹8–12 lakh/month.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| Admin Panel | Filament v5.6.7 |
| Customer UI | Inertia.js + Vue 3 + Pinia + Tailwind CSS v4 |
| Database | MySQL 8.4 |
| Queue | Laravel Queue (database driver local / Redis in production) |
| Import | maatwebsite/excel v3 (CSV/Excel), custom XML parser (Tally) |
| AI / LLM | Ollama + Qwen2.5 (local, vendor name fuzzy matching) |
| Alerts | Laravel Mail + WhatsApp via AiSensy/Interakt |
| Udyam Verify | Surepass API / Figment API (manual tagging for MVP) |
| Dev Server | Laragon (Windows) |
| Production | Ubuntu 24.04 + Nginx + PHP-FPM + Supervisor |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                  Browser / Mobile                    │
└──────────────────────┬──────────────────────────────┘
                       │ HTTPS
┌──────────────────────▼──────────────────────────────┐
│             Filament Admin Panel (/admin)            │
│          Livewire Components (reactive UI)           │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                  Service Layer                       │
│  MsmeDeadlineEngine  │  ImportPipeline              │
│  DisallowanceCalc    │  VendorMatcher (Ollama)       │
│  AlertDispatcher     │  UdyamVerifier               │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│              Repository Layer                        │
│  InvoiceRepository  │  VendorRepository             │
│  PaymentRepository  │  TenantRepository             │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   MySQL 8.4                          │
│  tenants │ vendors │ purchase_invoices │ payments   │
│  import_batches │ alert_log │ audit_log             │
└─────────────────────────────────────────────────────┘
```

---

## Engineering Standards

Every feature is built after a full multi-disciplinary architecture review covering:

- Business, functional, and non-functional requirements
- User journeys, roles, permissions, edge cases, failure scenarios
- SOLID principles, Clean Architecture, Repository + Service Layer Pattern
- OWASP Top 10 security compliance (SQLi, XSS, CSRF, SSRF, IDOR)
- Minimum **90% code coverage**, **100% on critical business logic** (rules engine)
- Production-ready — no prototypes, no incomplete implementations

Self-review gate before every delivery: Database Architect → Technical Architect → Security Architect → DevOps Architect → QA Architect → Performance Engineer.

---

## Phases

---

### Phase 0 — Environment Setup & Scaffold ✅

**Completed:** 2026-06-18

#### Objectives
Bootstrap production-grade Laravel project with correct stack, configure MySQL and Filament admin panel, initialize git with remote.

#### Requirements Addressed
- Laravel 13.16.1 project scaffolded with PHP 8.3
- MySQL 8.4 database `msme_risk_tracker` created with `utf8mb4_unicode_ci` collation
- Filament v5.6.7 admin panel installed at `/admin`
- Livewire v4.3.1 installed (required by Filament v5)
- maatwebsite/excel v3 installed for CSV/Excel import
- Guzzle v7 installed for HTTP client (Udyam API, WhatsApp API)
- Vite frontend assets compiled
- Git initialized and pushed to GitHub

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Admin panel | Filament v5.6.7 | v3 has active security advisory; v5 is patched and stable |
| Livewire version | v4.3.1 | Required by Filament v5 (not v3) |
| Multi-tenancy strategy | Single-database (`tenant_id` scoped globally) | Simpler at current scale (<1,000 tenants), lower ops overhead |
| Session driver (local) | `file` | `database` driver caused 4–6 extra MySQL queries per request |
| Cache driver (local) | `file` | Filament caches heavily; database driver was bottleneck |
| Redis client | `predis` | `phpredis` PHP extension not installed in this Laragon setup |

#### Configuration Changes

| Key | Value | Reason |
|---|---|---|
| `SESSION_DRIVER` | `file` | Eliminates per-request MySQL session queries in local dev |
| `CACHE_STORE` | `file` | Eliminates per-request MySQL cache queries in local dev |
| `REDIS_CLIENT` | `predis` | `phpredis` extension absent — `predis` is pure PHP fallback |
| `APP_URL` | `http://msme-pament-risk-tracker.test` | Laragon local domain |
| `APP_FAKER_LOCALE` | `en_IN` | Indian locale for realistic test data |

#### PHP Extensions Enabled
- `extension=zip` in `php.ini` — was commented out; required by Composer for package extraction

#### Dependencies Added

| Package | Version | Purpose |
|---|---|---|
| `filament/filament` | v5.6.7 | Admin panel, tables, forms, widgets |
| `livewire/livewire` | v4.3.1 | Reactive UI (installed as Filament dependency) |
| `maatwebsite/excel` | ^3.1 | CSV/Excel ledger import |
| `guzzlehttp/guzzle` | ^7.8 | HTTP client for Udyam/WhatsApp APIs |

#### Database Migrations Run
| Migration | Purpose |
|---|---|
| `create_users_table` | Base auth |
| `create_cache_table` | Framework cache |
| `create_jobs_table` | Queue jobs |

#### Admin Credentials (Local Only)
- URL: `http://msme-pament-risk-tracker.test/admin`
- Email: `admin@msme.local`
- Password: `admin123` *(change before any shared or staging environment)*

#### Known Limitations / Deferred
- No multi-tenancy scaffolding yet → Phase 1 ✅
- No RBAC yet → Phase 1 ✅
- Redis not configured for production queue → Phase 9
- Ollama/Qwen LLM not yet integrated → Phase 4

---

### Phase 1 — Database Architecture ✅

**Completed:** 2026-06-19

#### Objectives
Design and implement the complete production-grade database schema covering all entities required for 43B(h) compliance tracking, multi-tenancy, audit trail, and alerting.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Multi-tenancy | Single DB, `tenant_id` global scope | Simpler ops at target scale; `TenantScope` Eloquent global scope enforces isolation automatically |
| Monetary storage | `DECIMAL(15,2)` | Avoids float precision errors on large INR amounts |
| `balance` column | MySQL stored computed column (`amount - paid_amount`) | Always consistent with payments; eliminates SUM() join on every dashboard row |
| `vendor_category_snapshot` | Copied to invoice at creation | Vendor category can change later; historical invoices must use category at invoice time |
| `financial_year` | Stored as `VARCHAR(7)` e.g. `"2025-26"` | Enables GROUP BY without date math; India FY = Apr 1 – Mar 31 |
| `audit_log` | No soft deletes, no `updated_at`, append-only | Immutable by design — required for Form 3CD compliance |
| Soft deletes | All main tables | Financial records must never be hard-deleted |

#### Schema Overview

| Table | Purpose | Key Indexes |
|---|---|---|
| `tenants` | Business/CA firm accounts, plan, subscription | `subscription_status`, `is_active` |
| `users` | Auth users scoped to tenant, with role | `(tenant_id, is_active)`, `(tenant_id, role)` |
| `vendors` | Supplier master with Udyam classification | `(tenant_id, category)`, `udyam_number`, `UNIQUE(tenant_id, gstin)` |
| `import_batches` | CSV/Tally XML import job tracking | `(tenant_id, status)` |
| `purchase_invoices` | Core entity — bill with deadline and risk computation | `(tenant_id, effective_deadline, status)`, `UNIQUE(tenant_id, invoice_number, vendor_id)` |
| `payments` | Payments against invoices | `(tenant_id, invoice_id)`, `(tenant_id, payment_date)` |
| `alert_log` | Email/WhatsApp dispatch history | `(tenant_id, alert_type, status)` |
| `audit_log` | Immutable change trail for compliance | `(tenant_id, model_type, model_id)` |

#### PHP Enums Created (13 total)

| Enum | Values |
|---|---|
| `UserRole` | owner, admin, finance, viewer |
| `TenantPlan` | starter, growth, ca_firm |
| `TenantStatus` | trial, active, inactive, suspended |
| `VendorCategory` | micro, small, medium, large, unclassified |
| `VendorVerificationSource` | manual, api, llm |
| `InvoiceStatus` | pending, partial, paid, overdue, disallowed |
| `PaymentMode` | neft, rtgs, imps, upi, cheque, cash, other |
| `ImportSource` | csv, tally_xml, manual |
| `ImportStatus` | pending, processing, completed, failed |
| `AlertChannel` | email, whatsapp, sms |
| `AlertType` | t10_warning, t3_urgent, overdue, year_end_summary |
| `AlertStatus` | pending, sent, delivered, failed |
| `AuditEvent` | created, updated, deleted, restored |

#### Models Created

| Model | Traits | Key Relationships |
|---|---|---|
| `Tenant` | SoftDeletes | hasMany: users, vendors, purchaseInvoices, payments, importBatches, alertLog |
| `User` | SoftDeletes | belongsTo: tenant |
| `Vendor` | HasTenant, HasAuditColumns, SoftDeletes | hasMany: purchaseInvoices |
| `ImportBatch` | HasTenant | hasMany: purchaseInvoices |
| `PurchaseInvoice` | HasTenant, HasAuditColumns, SoftDeletes | belongsTo: vendor, importBatch; hasMany: payments, alertLog |
| `Payment` | HasTenant, HasAuditColumns, SoftDeletes | belongsTo: invoice |
| `AlertLog` | HasTenant | belongsTo: invoice |
| `AuditLog` | (none — append-only) | static `record()` factory method |

#### Shared Infrastructure

- **`TenantScope`** — Eloquent global scope; auto-filters every query by `auth()->user()->tenant_id`. Bypassed for console commands.
- **`HasTenant` trait** — applies `TenantScope` + auto-sets `tenant_id` on `creating`
- **`HasAuditColumns` trait** — auto-sets `created_by` / `updated_by` on `creating` and `updating`

#### Migrations Run

```
2026_06_19_000001_create_tenants_table              99ms  ✓
2026_06_19_000002_add_tenant_fields_to_users_table  325ms ✓
2026_06_19_000003_create_vendors_table              528ms ✓
2026_06_19_000004_create_import_batches_table       247ms ✓
2026_06_19_000005_create_purchase_invoices_table    729ms ✓
2026_06_19_000006_create_payments_table             434ms ✓
2026_06_19_000007_create_alert_log_table            268ms ✓
2026_06_19_000008_create_audit_log_table             83ms ✓
```

#### Edge Cases Handled
- Vendor category changes after invoice → `vendor_category_snapshot` preserves state at invoice time
- Partial payment before deadline → only `balance` (stored computed) is at risk
- Credit notes → `amount` can be negative; rules engine will skip in Phase 2
- Duplicate CSV imports → `UNIQUE(tenant_id, invoice_number, vendor_id)` constraint rejects duplicates
- Leap year / month-end dates → handled by Carbon in Phase 2 rules engine (tests will cover Feb 28/29)
- Financial year spanning two calendar years → `financial_year` stored as `"2025-26"` at invoice creation

#### Known Limitations / Deferred
- No `tenant_id` on `audit_log` FK (nullable — super-admin actions have no tenant) → by design
- GSTIN/PAN encryption at rest → Phase 9 (deployment)
- Seeder with realistic test data → Phase 8 (testing)

#### Hotfix
- `2026_06_19_000009_add_soft_deletes_to_users_table` — `SoftDeletes` was added to `User` model but `deleted_at` was missing from the `users` table. Added via separate migration.

---

### Phase 1.5 — Inertia.js + Vue 3 Frontend Stack ✅

**Completed:** 2026-06-19

#### Objectives
Replace the default Blade-only frontend with an Inertia.js + Vue 3 SPA-hybrid stack for the customer-facing UI, while keeping Filament at `/admin` for super-admin operations.

#### Architecture Decision: Why Inertia.js + Vue 3

| Approach | Rejected Because |
|---|---|
| Full SPA (separate Vue app) | Needs a separate API layer; auth/session handling is complex; doubles deployment surface |
| Full Blade + Livewire | Limited JS ecosystem; no Vue component libraries; harder to build rich financial UIs |
| **Inertia.js + Vue 3** ✅ | Single codebase; server-side auth stays in Laravel; Vue for rich UI; no REST API needed |

#### Dependencies Added

| Package | Version | Purpose |
|---|---|---|
| `inertiajs/inertia-laravel` | ^2.0 | Server-side Inertia adapter |
| `@inertiajs/vue3` | latest | Vue 3 client adapter |
| `vue` | ^3.x | Frontend framework |
| `@vitejs/plugin-vue` | latest | Vite plugin for SFC compilation |
| `pinia` | latest | Vue state management |
| `vue3-apexcharts` + `apexcharts` | latest | Dashboard charting |
| `@heroicons/vue` | ^2.x | UI icons |
| `@tailwindcss/vite` | latest | Tailwind CSS v4 via Vite |

#### Files Created

| File | Purpose |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `auth.user` + flash messages to all Vue pages |
| `resources/views/app.blade.php` | Inertia root template (replaces `welcome.blade.php`) |
| `resources/js/app.js` | Bootstraps Inertia + Vue + Pinia + ApexCharts |
| `resources/js/bootstrap.js` | Axios with CSRF + credentials |
| `resources/js/Layouts/AppLayout.vue` | Collapsible sidebar + topbar with tenant name |
| `resources/js/Components/NavItem.vue` | Sidebar nav link with active state + tooltip when collapsed |
| `resources/js/Components/FlashMessage.vue` | Auto-dismiss toasts (success/error/warning, 5s) |
| `resources/js/Components/StatCard.vue` | KPI card with icon + optional trend indicator |
| `resources/js/Components/AppCard.vue` | Reusable content card with header/actions slot |
| `resources/js/Components/AppBadge.vue` | Colored status badge with dot option |
| `resources/js/Components/AppButton.vue` | Button/Link with variants (primary/secondary/danger/ghost) + loading spinner |
| `resources/js/Pages/Auth/Login.vue` | Custom customer login page |
| `resources/js/Pages/Dashboard.vue` | Dashboard with 4 KPI cards + at-risk invoice table |
| `app/Http/Controllers/DashboardController.php` | Returns `Inertia::render('Dashboard')` |

#### Route Structure

| Method | Path | Handler |
|---|---|---|
| GET | `/` | Redirect to `/dashboard` or `/login` |
| GET/POST | `/login` | Customer auth (not Filament) |
| POST | `/logout` | Session invalidate + redirect |
| GET | `/dashboard` | `DashboardController@index` |
| GET | `/vendors`, `/invoices`, `/payments`, `/import`, `/alerts`, `/calculator` | Placeholder Inertia renders |
| GET | `/admin/*` | Filament super-admin panel (unchanged) |

#### Notes
- Filament and Inertia coexist without conflict — Filament owns `/admin/*`, Inertia owns all other routes
- ApexCharts (820KB) is loaded eagerly in Phase 1.5; will be lazy-loaded in Phase 5 when charts are fully implemented
- `formatCurrency()` in `Dashboard.vue` formats INR to Cr/L/K shorthand

---

### Phase 2 — Core Rules Engine ✅

**Completed:** 2026-06-19

#### Objectives
Implement the Section 43B(h) calculation engine as a pure, framework-independent PHP service with 100% unit test coverage. No database access inside the engine — all inputs are primitives, all outputs are DTOs.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Engine has no DB access | Primitive inputs only | Fully unit-testable without migrations or mocks |
| Separate `InvoiceRiskRecomputer` | DB-aware batch wrapper | Separation of concerns — engine stays pure |
| `RiskAssessment` DTO | PHP 8.3 readonly constructor promotion | Immutable output; prevents accidental mutation |
| Interest formula | Compound, monthly rests | Section 43B(h) requirement: "3× bank rate compounded monthly" |
| Status = `Disallowed` when | balance > 0 AND past March 31 of FY | Tax disallowance is confirmed at FY end |
| Batch recompute uses `chunk(200)` | Memory-safe processing | Never loads all invoices of a tenant into memory |

#### Formula Reference

```
Deadline:
  invoice_date + 15 days   (no written agreement)
  invoice_date + 45 days   (written agreement exists — MSME Act maximum)

Financial Year:
  April 1 → March 31 ("2025-26", "2024-25", etc.)

Disallowance:
  amount = unpaid balance as of effective_deadline

Interest (compound, monthly rests):
  annual_rate  = rbi_bank_rate × 3
  monthly_rate = annual_rate / 12 / 100
  interest     = principal × ((1 + monthly_rate)^n − 1)
  where n = complete months elapsed since effective_deadline
```

#### Files Created

| File | Purpose |
|---|---|
| `app/DTOs/RiskAssessment.php` | Immutable output DTO with `toArray()` + `safe()` factory |
| `app/Services/MsmeDeadlineEngine.php` | Pure calculation engine — deadline, FY, interest, full `assess()` |
| `app/Services/InvoiceRiskRecomputer.php` | DB-aware batch updater; calls engine, persists to `purchase_invoices` |
| `app/Console/Commands/RecomputeMsmeRisk.php` | `php artisan msme:recompute-risk [--tenant=] [--as-of=]` |
| `tests/Unit/Services/MsmeDeadlineEngineTest.php` | 48 unit tests, 99 assertions — **100% method coverage** |
| `tests/Feature/ExampleTest.php` | Updated scaffold test to match real redirect behaviour |

#### Edge Cases Tested

| Scenario | Behaviour |
|---|---|
| Payment on deadline date | `daysOverdue = 0` → Pending (not overdue) |
| Payment 1 day after deadline | Overdue, full balance disallowed, 0 months interest |
| Partial payment before deadline | Only balance (not amount) is at risk |
| Negative amount (credit note) | Treated as Paid — skipped |
| Vendor category = Medium/Large/Unclassified | `isSubjectTo43Bh = false`, zero disallowance |
| Leap year (Feb 14, 2024 + 15 days) | Feb 29, 2024 ✓ |
| Non-leap year (Feb 14, 2025 + 15 days) | Mar 1, 2025 ✓ |
| Invoice in last week of March | FY boundary handled correctly |
| Past March 31 of FY | Status → `Disallowed` |
| `as-of` override for retrospective | Supported via `asOf` param |

#### Test Results

```
Tests:   50 total (48 engine + 2 existing)
Passed:  50 / 50
Assertions: 102
Duration: ~1.2s
Coverage: 100% on MsmeDeadlineEngine + RiskAssessment DTO
```

#### Artisan Command

```bash
# Recompute all tenants
php artisan msme:recompute-risk

# Single tenant
php artisan msme:recompute-risk --tenant=1

# Retrospective (e.g., for March 31 year-end snapshot)
php artisan msme:recompute-risk --as-of=2025-03-31
```

---

---

### Phase 3 — Import Pipeline — CSV & Tally XML ✅

**Completed:** 2026-06-19

#### Objectives
Build a production-grade file import pipeline supporting CSV/Excel (from any ERP) and Tally XML exports. Each import creates an `ImportBatch` record, processes rows with full validation, auto-matches or auto-creates vendors, computes risk on each new invoice, and logs every error with row number and reason.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Job dispatch | `ProcessImportBatch` ShouldQueue job | Queue-ready; runs synchronously in local dev (QUEUE_CONNECTION=sync) |
| Row processing | One try/catch per row | Partial failure is logged and skipped — does not abort the whole file |
| Vendor matching priority | GSTIN → Udyam → Name → Create | GSTIN is most reliable identifier in Indian taxation context |
| New vendor category | `unclassified` | Phase 4 will classify via Udyam API / LLM; import should not block on it |
| In-memory vendor cache | `VendorMatcher::$cache` | Avoids N+1 DB lookups when one file has many rows from the same vendor |
| Error log | JSON in `import_batches.error_log`, max 500 rows | Bounded size; full download via "Download Error CSV" client-side |
| Progress save | Every 100 rows | Allows UI to show partial progress on large files |
| Computed column (balance) | Driver-aware migration | SQLite (used in tests) doesn't support `storedAs()`; falls back to regular column |
| File storage | `storage/app/imports/{tenant_id}/` | Private storage — never publicly accessible |

#### Import Flow

```
POST /import (StoreImportRequest)
  → Store file to storage/app/imports/{tenant_id}/
  → Create ImportBatch (status=pending)
  → Dispatch ProcessImportBatch job
     → ImportPipeline::process()
        → Parse file: CsvImporter (maatwebsite/excel) or TallyXmlImporter (SimpleXML)
        → ColumnMapper: normalize headers to canonical names
        → For each row:
            → RowValidator::validate() → errors[] or continue
            → VendorMatcher::findOrCreate() (GSTIN → Udyam → Name → Create)
            → MsmeDeadlineEngine: compute effective_deadline, financial_year
            → PurchaseInvoice::create()
            → InvoiceRiskRecomputer::recomputeOne()
        → Save error_log, update batch status = completed
```

#### Column Header Support (CSV/Excel)

| Canonical Name | Accepted Aliases |
|---|---|
| `invoice_number` | bill_number, voucher_number, inv_no, ref_number |
| `invoice_date` | bill_date, date, voucher_date, doc_date |
| `vendor_name` | party_name, supplier_name, ledger_name |
| `amount` | invoice_amount, gross_amount, net_amount, total_amount |
| `gstin` | gst_number, vendor_gstin, party_gstin |
| `udyam_number` | udyam_no, msme_number |
| `paid_amount` | amount_paid, payment_amount |
| `agreement_exists` | has_agreement, written_agreement |
| `narration` | description, remarks, notes, particulars |

#### Date Formats Accepted

`DD-MM-YYYY`, `YYYY-MM-DD`, `DD/MM/YYYY`, `YYYYMMDD` (Tally), `DD Jan YYYY`, `DD-Jan-YYYY`

#### Files Created

| File | Purpose |
|---|---|
| `app/DTOs/ImportRow.php` | Normalized row DTO (source-agnostic) |
| `app/DTOs/RowImportResult.php` | Per-row outcome: imported/skipped/failed + message |
| `app/Services/Import/ColumnMapper.php` | Header alias resolution with 40+ recognized aliases |
| `app/Services/Import/RowValidator.php` | Pure validation: required fields, date parsing, GSTIN/Udyam format |
| `app/Services/Import/VendorMatcher.php` | GSTIN→Udyam→Name match with in-memory cache |
| `app/Services/Import/CsvImporter.php` | maatwebsite/excel ToCollection parser |
| `app/Services/Import/TallyXmlImporter.php` | SimpleXML parser for Tally ERP 9 and Tally Prime formats |
| `app/Services/Import/ImportPipeline.php` | Orchestrator: parse → validate → match vendor → create invoice → risk |
| `app/Jobs/ProcessImportBatch.php` | ShouldQueue job (tries=1, timeout=300s) |
| `app/Http/Requests/StoreImportRequest.php` | File validation (max 10MB, allowed MIME types) |
| `app/Http/Controllers/ImportController.php` | index, store, show, downloadSample |
| `resources/js/Pages/Import/Index.vue` | Upload form with drag/drop + import history table |
| `resources/js/Pages/Import/Show.vue` | Batch results with stat cards, error table, client-side CSV download |
| `storage/app/samples/sample-import.csv` | Downloadable sample with 5 example rows |
| `storage/app/samples/sample-tally.xml` | Downloadable Tally XML sample with 3 purchase + 1 payment voucher |
| `database/factories/TenantFactory.php` | Tenant model factory for tests |

#### Routes Added

| Method | Path | Handler |
|---|---|---|
| GET | `/import` | `ImportController@index` |
| POST | `/import` | `ImportController@store` |
| GET | `/import/{batch}` | `ImportController@show` |
| GET | `/import/sample/{type}` | `ImportController@downloadSample` |

#### Validation Rules

| Field | Rule |
|---|---|
| `invoice_number` | Required, max 100 chars |
| `invoice_date` | Required, must parse to a valid date |
| `vendor_name` | Required, min 2 chars, max 200 chars |
| `amount` | Required, numeric (Indian comma format supported) |
| `paid_amount` | Optional, must be numeric if provided |
| `gstin` | Optional, must match 15-char GSTIN pattern if provided |
| `udyam_number` | Optional, must match UDYAM-XX-00-0000000 format if provided |

#### Test Results

```
Tests:   129 total
Passed:  129 / 129
Assertions: 246
Duration: ~3.1s (SQLite in-memory)

Unit tests added:
  RowValidatorTest    — 37 cases covering all validation paths
  ColumnMapperTest    — 12 cases covering alias resolution

Feature tests added:
  ImportControllerTest — 17 cases covering auth, validation, success path
```

#### Known Limitations / Deferred to Later Phases

| Item | Deferred to |
|---|---|
| Balance column in SQLite uses regular column (not computed) | Acceptable — MySQL prod uses computed column |
| Vendor name fuzzy matching (LLM) | Phase 4 |
| Udyam API verification after import | Phase 4 |
| Real-time progress polling (WebSocket / polling) | Phase 5 |
| Import from email attachment | Phase 6+ |
| WhatsApp/email notification on import completion | Phase 6 |

---

---

### Phase 4 — Vendor Classification & Udyam Verification ✅

**Completed:** 2026-06-19

#### Objectives
Enable finance teams to classify vendors imported as `unclassified` into the correct MSME category (Micro/Small/Medium/Large), verify Udyam registration numbers against the government database via the Surepass API, and automatically propagate category changes to all existing non-paid invoices so risk scores stay accurate.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Propagation is async | `PropagateVendorClassification` queue job | Category change can affect hundreds of invoices; HTTP response must not block |
| Two-step propagation | Update `vendor_category_snapshot`, then run `InvoiceRiskRecomputer` | Snapshot is the source of truth; risk is re-derived from it |
| Verification degrades gracefully | `UdyamVerifierService` returns `notConfigured` / `failed` / `notFound` — never throws | Missing API key or network error must not crash the UI |
| Bulk classify limit | Max 100 vendors per request | Prevents runaway job fan-out; each vendor dispatches its own propagation job |
| Category changes fire on diff | `VendorClassificationService::classify()` checks old vs new category | Prevents redundant propagation jobs when category is unchanged |
| `TenantScope` fixed in tests | Removed `runningInConsole()` guard | `auth()->check()` already handles unauthenticated console/queue contexts; guard was causing tenant isolation to be bypassed in PHPUnit |
| Bulk-classify route before `{vendor}` wildcard | `Route::post('/vendors/bulk-classify')` registered first | Prevents Laravel treating "bulk-classify" as a vendor ID |

#### Udyam Verification Flow

```
POST /udyam/verify { udyam_number, vendor_id? }
  → UdyamVerifierService::verify()
     → If no API key:  UdyamVerificationResult::notConfigured()
     → POST Surepass API (10s timeout)
        → 404 / success=false:  notFound()
        → 5xx:                  failed()
        → 200 + success=true:   verified(enterpriseName, category, registeredAt)
  → If verified AND vendor_id provided:
     → VendorClassificationService::classify(vendor, category, source=api, verifiedAt=now)
        → PropagateVendorClassification::dispatch(vendor)
  → Return JSON result to Vue
```

#### Classification Propagation Flow

```
VendorClassificationService::classify(vendor, newCategory)
  → vendor.category changed?
      YES → Update vendor.category + verification_source in DB
          → PropagateVendorClassification::dispatch(vendor)
              → Chunk 200: UPDATE purchase_invoices SET vendor_category_snapshot = newCategory
                           WHERE vendor_id = X AND status != 'paid'
              → InvoiceRiskRecomputer::recomputeForVendor(vendor)
                 → Chunk 200: recalculate disallowance_amount, interest_amount, status
      NO  → Update other fields only (name, contact, etc.); no job dispatched
```

#### Files Created

| File | Purpose |
|---|---|
| `app/DTOs/UdyamVerificationResult.php` | Immutable result DTO with 4 factory methods: `verified`, `notFound`, `notConfigured`, `failed` |
| `app/Services/UdyamVerifierService.php` | Surepass API client with graceful degradation (10s timeout, all failures handled) |
| `app/Services/VendorClassificationService.php` | Classifies one or many vendors; dispatches propagation job only on category change |
| `app/Jobs/PropagateVendorClassification.php` | Async job: updates `vendor_category_snapshot` on invoices → recomputes risk (tries=3, timeout=120s) |
| `app/Http/Requests/UpdateVendorRequest.php` | Validates name, category, GSTIN (regex + tenant-unique), Udyam, PAN, contact fields |
| `app/Http/Requests/BulkClassifyRequest.php` | Validates vendor_ids (1–100) + category |
| `app/Http/Controllers/VendorController.php` | index (search+filter+paginate), show, update, bulkClassify |
| `app/Http/Controllers/UdyamVerificationController.php` | POST /udyam/verify → verify + optional auto-apply |
| `resources/js/Pages/Vendors/Index.vue` | Vendor list: category filter chips, name/GSTIN search, bulk classify bar, tax exposure column |
| `resources/js/Pages/Vendors/Show.vue` | Vendor detail: edit form, stat cards, recent invoices table, Udyam verify sidebar |
| `database/factories/VendorFactory.php` | Vendor factory with states: micro, small, medium, large, unclassified, withGstin, withUdyam |

#### Routes Added

| Method | Path | Handler |
|---|---|---|
| POST | `/vendors/bulk-classify` | `VendorController@bulkClassify` |
| GET | `/vendors` | `VendorController@index` |
| GET | `/vendors/{vendor}` | `VendorController@show` |
| PUT | `/vendors/{vendor}` | `VendorController@update` |
| POST | `/udyam/verify` | `UdyamVerificationController@verify` |

#### Configuration Added

```env
# .env — add your Surepass API key for Udyam verification
SUREPASS_API_KEY=your_bearer_token_here
```

```php
// config/services.php
'surepass' => ['token' => env('SUREPASS_API_KEY')],
```

#### `InvoiceRiskRecomputer` Extended

Added `recomputeForVendor(Vendor, ?Carbon): int` method — recomputes all non-paid invoices for a single vendor in chunks of 200. Used by `PropagateVendorClassification` job.

#### `TenantScope` Bug Fix

Removed the `! app()->runningInConsole()` guard from `TenantScope::apply()`. This guard incorrectly bypassed tenant isolation during PHPUnit tests (because PHPUnit is itself a console process). Since `auth()->check()` already returns `false` in unauthenticated console/queue contexts, the guard was redundant and harmful.

#### Test Results

```
Tests:   159 total (all passing)
         + 12 unit tests  (UdyamVerifierServiceTest — 100% path coverage)
         + 18 feature tests (VendorControllerTest — CRUD, validation, propagation, bulk, tenant isolation)
Passed:  159 / 159
Assertions: 362
Duration: ~4.2s (SQLite in-memory)
```

#### Test Coverage Highlights

| Test | What it verifies |
|---|---|
| API key absent → notConfigured | No key in .env → graceful degradation |
| 404 response → notFound | Udyam not in govt DB |
| success=false → notFound | API returns success:false |
| 500 response → failed | Server error → temporary failure |
| Network error → failed | Connection refused/timeout |
| Verified → Micro/Small/Medium category | Enterprise type mapping |
| `show_returns_404_for_other_tenants_vendor` | TenantScope blocks cross-tenant access |
| Category unchanged → no job dispatched | Idempotent classify |
| Category changed → job dispatched | Propagation triggered |
| Bulk > 100 vendors → validation error | Rate limiting on bulk ops |

#### Known Limitations / Deferred

| Item | Deferred to |
|---|---|
| LLM-based vendor name matching (Ollama/Qwen) | Phase 5+ |
| Udyam verification rate limits / caching | Phase 9 (production hardening) |
| Vendor merge / de-duplicate UI | Phase 8 |
| Create vendor manually (without import) | Phase 5 |

---

### Phase 5 — Dashboard UI with Real Data ✅

**Completed:** 2026-06-19

#### Objectives
Replace the hardcoded-zero dashboard with a fully live data view. Finance managers can see their 43B(h) risk exposure the moment they log in, switch between financial years, and spot unclassified vendor blind spots through a prominent warning banner.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| `DashboardService` class | All queries in one service | Keeps controller thin; service is independently testable |
| Monthly trend in PHP | Load invoice rows, group in PHP | Avoids MySQL `MONTH()` vs SQLite `strftime()` dialect mismatch in tests |
| FY filter via `?fy=` query param | Inertia GET, no JS store | Sharable URLs; browser back button works; no global state |
| Invalid `?fy=` silently falls back | No 422/redirect | Dashboard should never hard-fail on a bad URL param |
| All queries use `TenantScope` | Automatic (from auth) | No manual `where tenant_id` needed — safe by default |
| At-risk list: top 15, sorted by urgency | Overdue first → deadline ASC | Finance manager cares most about what needs action NOW |

#### Widgets Delivered

| Widget | Description |
|---|---|
| **At-Risk Balance** KPI | Unpaid balance on Micro/Small invoices at risk (current FY, selected FY) |
| **Due This Week** KPI | Count of invoices with deadline within 7 days |
| **Projected Disallowance** KPI | `SUM(disallowance_amount)` across all non-paid invoices |
| **Projected Interest** KPI | `SUM(interest_amount)` across all non-paid invoices |
| **FY Tabs** | Switch between financial years (current FY shown by default) |
| **Unclassified Vendor Banner** | Warning with count + direct link to classify — shown when count > 0 |
| **At-Risk Invoice Table** | Top 15 invoices sorted overdue-first then by deadline ASC; shows vendor name, balance, deadline, days remaining, tax exposure, status badge |
| **Overdue Alert Card** | Red panel showing count if any invoices are overdue in selected FY |
| **Vendor Coverage Sidebar** | Count by category (Micro/Small/Medium/Large/Unclassified) + total |
| **Monthly Trend Chart** | ApexCharts grouped bar — disallowance (orange) + interest (purple) per month in India FY order (Apr → Mar) |

#### Files Modified / Created

| File | Change |
|---|---|
| `app/Services/DashboardService.php` | New — `summaryStats()`, `atRiskInvoices()`, `vendorBreakdown()`, `monthlyTrend()`, `unclassifiedVendorCount()`, `availableYears()`, `currentFy()`, `resolveFy()` |
| `app/Http/Controllers/DashboardController.php` | Updated — now uses `DashboardService`, reads `?fy` param |
| `resources/js/Pages/Dashboard.vue` | Updated — FY tabs, unclassified banner, real data binding, trend chart (ApexCharts), overdue alert card |
| `tests/Feature/DashboardControllerTest.php` | New — 19 feature tests |

#### `DashboardService` Query Overview

```
summaryStats(fy):
  → 1 query: SELECT COUNT(*), SUM(amount-paid_amount), SUM(disallowance), SUM(interest)
             WHERE status IN (pending, partial, overdue) AND financial_year = ?
  → 1 query: COUNT WHERE status = overdue
  → 1 query: COUNT WHERE deadline BETWEEN today AND today+7

atRiskInvoices(fy, limit=15):
  → 1 query: SELECT invoices + JOIN vendor (eager load)
             ORDER BY overdue first, then deadline ASC LIMIT 15

vendorBreakdown():
  → 1 query: SELECT category, COUNT(*) GROUP BY category

monthlyTrend(fy):
  → 1 query: SELECT invoice_date, disallowance_amount, interest_amount, status
             WHERE financial_year = ?
  → Grouped in PHP by invoice_date.month
  → Returns 12 entries (Apr → Mar)
```

#### Test Results

```
Tests:   178 total (all passing)
         + 19 feature tests (DashboardControllerTest)
Passed:  178 / 178
Assertions: 546
Duration: ~12s (SQLite in-memory)

Test coverage:
  - Auth redirect
  - Empty state (zero invoices → zero stats)
  - FY default (current FY)
  - Valid FY query param respected
  - Invalid FY param falls back to current FY (no crash)
  - Disallowance/interest aggregation
  - Overdue count isolated
  - Due-this-week count
  - FY isolation (invoices from other FY not counted)
  - At-risk list sorted (overdue before pending)
  - At-risk list includes vendor name via eager load
  - Paid invoices excluded from at-risk list
  - Vendor breakdown counts
  - Unclassified vendor count
  - Monthly trend returns 12 months
  - Monthly trend aggregates disallowance correctly
  - Tenant isolation (other tenant's data invisible)
  - Available years always includes current FY
```

---

### Phase 6 — Alerts System — Email & WhatsApp ✅

**Completed:** 2026-06-19

#### Objectives
Send proactive 43B(h) deadline alerts to finance managers via Email and WhatsApp before payment deadlines are missed. Deduplication prevents repeated alerts within the same day.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| `AlertDispatcherService` is console-safe | All queries use `withoutGlobalScopes()` + explicit `tenant_id` | Console context has no auth → TenantScope doesn't apply; must filter manually |
| Alert log created BEFORE job dispatch | AlertLog (status=pending) → `SendAlertJob` reads from it | Gives a DB record of every intended dispatch, even if the job fails |
| Deduplication window | Same `(invoice_id, alert_type, channel)` on the same calendar day | T10/T3 don't repeat — invoice moves out of the [+8,+10]/[+1,+3] window by next day; overdue dedupes daily |
| WhatsApp via AiSensy | AiSensy Business API (POST to backend.aisensy.com/campaign) | India-first, pre-approved template messaging, reliable delivery |
| WhatsApp degrades gracefully | Missing `AISENSY_API_KEY` → job catches and marks AlertLog as failed | Doesn't block email; missing key is common in dev environments |
| Tenant alert settings | Stored in `tenants.settings['alerts']` JSON | No new migration; settings are per-tenant and change rarely |
| Email fallback recipients | If `email_recipients` is empty → use all active users' emails | Finance team set up in Users already; no redundant config needed |
| `YearEndSummary` alert type | Manual-only — artisan command doesn't auto-dispatch it | Year-end summary requires business judgement on timing; not automated |

#### Alert Types and Windows

| Alert Type | Trigger Condition | Cadence |
|---|---|---|
| `T10Warning` | Invoice deadline in [+8, +10] days, status = pending/partial | Once per invoice (moves out of window) |
| `T3Urgent` | Invoice deadline in [+1, +3] days, status = pending/partial | Once per invoice (moves out of window) |
| `Overdue` | Status = overdue, deadline < today | Daily (dedup prevents same-day repeats) |
| `YearEndSummary` | Manual trigger only | On demand |

#### Alert Dispatch Flow

```
php artisan msme:send-alerts [--tenant=N] [--as-of=YYYY-MM-DD] [--dry-run]
  → AlertDispatcherService::dispatchForTenant(tenant)
     → resolveChannels(settings)  → [Email → recipients] + [WhatsApp → phone]
     → resolveTypes(settings)     → [T10Warning, T3Urgent, Overdue] (per toggles)
     → For each type:
         qualifyingInvoices(tenant, type, today)
         → For each invoice × channel:
              alreadySentToday? → skip (skipped++)
              : AlertLog::create(status=pending)
              : SendAlertJob::dispatch(alertLog)
                 → EmailAlertChannel::send()   → Mail::to(recipient)
                 → WhatsAppAlertChannel::send() → POST AiSensy API
                 → Update AlertLog: status=sent, sent_at, provider_message_id
                 → On failure: status=failed, failed_reason (re-throws for 3 retries)
```

#### Files Created

| File | Purpose |
|---|---|
| `app/Services/Alerts/AlertChannelInterface.php` | Interface for swappable channel implementations |
| `app/Services/Alerts/EmailAlertChannel.php` | Sends via `Mail::to()` with InvoiceAlertMail |
| `app/Services/Alerts/WhatsAppAlertChannel.php` | POST to AiSensy API with 5 template params |
| `app/Services/AlertDispatcherService.php` | Core service: resolves channels/types, dedupes, creates AlertLog, dispatches jobs |
| `app/Mail/InvoiceAlertMail.php` | Laravel Mailable — computes balance, daysText, totalExposure for template |
| `resources/views/emails/invoice-alert.blade.php` | Markdown email — invoice details, tax risk table, call-to-action button |
| `app/Jobs/SendAlertJob.php` | Queued job (tries=3, timeout=30s) — sends via channel, updates AlertLog |
| `app/Console/Commands/SendMsmeAlerts.php` | `msme:send-alerts` command with `--tenant`, `--as-of`, `--dry-run` options |
| `app/Http/Requests/UpdateAlertSettingsRequest.php` | Validates email_recipients (max:10, each valid email), whatsapp_number (E.164) |
| `app/Http/Controllers/AlertController.php` | `index()` (paginated history + filters) + `updateSettings()` |
| `resources/js/Pages/Alerts/Index.vue` | Two-tab UI: History (table with filters) + Settings (toggle form) |
| `tests/Feature/AlertDispatcherServiceTest.php` | 12 feature tests — window qualification, dedup, channel resolution, tenant isolation |
| `tests/Feature/AlertControllerTest.php` | 11 feature tests — auth, Inertia rendering, tenant isolation, settings CRUD |

#### AlertLog Model Fix

Added `protected $table = 'alert_log'` — Laravel auto-pluralizes to `alert_logs` but the actual table is `alert_log` (matches migration).

#### Routes Added

| Method | Path | Handler |
|---|---|---|
| GET | `/alerts` | `AlertController@index` |
| PUT | `/alerts/settings` | `AlertController@updateSettings` |

#### Configuration Added

```env
# .env — add your AiSensy API key for WhatsApp alerts
AISENSY_API_KEY=your_api_key_here
AISENSY_CAMPAIGN_NAME=msme_43bh_alert     # pre-approved WhatsApp template
AISENSY_USER_NAME=MSME Tracker            # sender display name
```

```php
// config/services.php
'aisensy' => [
    'key'           => env('AISENSY_API_KEY'),
    'campaign_name' => env('AISENSY_CAMPAIGN_NAME', 'msme_43bh_alert'),
    'user_name'     => env('AISENSY_USER_NAME', 'MSME Tracker'),
],
```

#### WhatsApp Template (AiSensy — must be pre-approved)

Template name: `msme_43bh_alert`
Parameters:
1. `{{1}}` — vendor name
2. `{{2}}` — balance amount (₹)
3. `{{3}}` — deadline date
4. `{{4}}` — days remaining / overdue text
5. `{{5}}` — disallowance amount (₹)

#### Artisan Command Usage

```bash
# Run for all active tenants (use in cron, runs daily)
php artisan msme:send-alerts

# Single tenant
php artisan msme:send-alerts --tenant=1

# Backdate testing
php artisan msme:send-alerts --as-of=2025-03-28

# Preview what would be dispatched without actually sending
php artisan msme:send-alerts --dry-run
```

#### Alert Settings (stored in tenants.settings JSON)

```json
{
  "alerts": {
    "email_enabled": true,
    "email_recipients": ["finance@company.com"],
    "whatsapp_enabled": false,
    "whatsapp_number": "+919876543210",
    "t10_enabled": true,
    "t3_enabled": true,
    "overdue_enabled": true
  }
}
```

#### Test Results

```
Phase 6 tests: 27 (AlertDispatcherServiceTest: 12, AlertControllerTest: 11, + 4 others)
Full suite:    205 tests / 205 passing / 678 assertions
Duration:      ~5.7s (SQLite in-memory)

Key scenarios covered:
  - T10 window [+8,+10]: invoice at +9 qualifies, +11 does not
  - T3 window [+1,+3]: invoice at +2 qualifies, +4 does not
  - Overdue: status=overdue + deadline in past qualifies
  - Paid invoice: never qualifies for any alert type
  - YearEndSummary: always returns empty (manual-only)
  - Deduplication: same invoice+type+channel today → skipped
  - Email disabled: no channels → 0 dispatched
  - WhatsApp added: 1 invoice × 2 channels → 2 dispatched
  - t10_enabled=false: T10 invoices skipped
  - Empty email_recipients: fallback to all active users' emails
  - Cross-tenant: other tenant's invoices invisible
  - Auth required on all routes
  - TenantScope: other tenant's alert logs hidden
  - Settings update: persists to tenant.settings['alerts']
  - Settings update: preserves other tenant.settings keys
  - Validation: invalid email → error, invalid phone → error
```

#### Known Limitations / Deferred

| Item | Deferred to |
|---|---|
| SMS channel (AlertChannel::Sms) | Phase 9 |
| WhatsApp delivery webhooks (mark Delivered) | Phase 9 |
| YearEndSummary manual send UI | Phase 7 |
| Scheduler registration in `routes/console.php` | Phase 9 (deployment) |
| Per-user alert preferences (not just tenant-level) | Phase 7 |

---

---

### Phase 7 — Multi-Tenancy & Billing *(Planned)*

---

### Phase 8 — Testing *(Planned)*

---

### Phase 9 — Deployment *(Planned)*

---

### Phase 10 — Client Delivery & Onboarding *(Planned)*

---

## Local Development Setup

```bash
# 1. Clone the repository
git clone https://github.com/githubgobi/laravel-msme-payment-risk-tracker.git
cd laravel-msme-payment-risk-tracker

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Copy environment file and configure
cp .env.example .env
php artisan key:generate

# 5. Create MySQL database
mysql -u root -e "CREATE DATABASE msme_risk_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Run migrations
php artisan migrate

# 7. Build frontend assets
npm run build

# 8. Access admin panel
# http://msme-pament-risk-tracker.test/admin
```

### .env Quick Reference (local dev)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=msme_risk_tracker
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
REDIS_CLIENT=predis
```

---

## Repository

GitHub: [githubgobi/laravel-msme-payment-risk-tracker](https://github.com/githubgobi/laravel-msme-payment-risk-tracker)

---

## License

Proprietary. All rights reserved.
