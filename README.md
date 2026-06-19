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

### Phase 7 — Multi-Tenancy & Billing ✅

**Completed:** 2026-06-19

#### Objectives
Enable self-serve tenant registration (14-day free trial), enforce plan-based vendor/user limits, protect all authenticated routes from expired or suspended accounts, and give tenants a full settings UI to manage their business profile and team.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| Self-registration creates trial tenant | `TenantRegistrationService` in DB::transaction | Atomic — never leave a Tenant without an Owner or an orphaned User |
| Plan limits enforced in service layer | `PlanLimitService` — not in middleware | Controllers can return informative errors (not 403/502 blobs) |
| Vendor count tracked locally in import | Count once at start, increment via `$vendor->wasRecentlyCreated` | Avoids N+1 DB queries during large imports |
| `EnsureActiveTenant` renders Inertia page | HTTP 402 + `Auth/Suspended` page | User sees a meaningful upgrade message, not a blank error |
| `hasActiveAccess()` checks both status AND expiry | Separate `trial_ends_at` and `subscription_ends_at` timestamps | Subscription can be Active but expired (common billing edge case) |
| Settings in two controllers | `ProfileController` + `TeamController` under `Settings/` namespace | Single Responsibility — profile and team have different validation, authorization, and side effects |
| Owner cannot deactivate themselves | `destroy()` guard in `TeamController` | Prevents account lockout — at least one active owner is always guaranteed |
| Trial countdown in topbar | Shows badge in `AppLayout.vue` when `is_trial=true` | Persistent visibility; turns red at ≤3 days |

#### Plan Limits

| Plan | Vendors | Users | Price |
|---|---|---|---|
| Starter | 50 | 5 | ₹1,500/mo |
| Growth | 200 | 15 | ₹3,000/mo |
| CA Firm | Unlimited | Unlimited | ₹4,000/mo |

#### Registration Flow

```
GET /register → Auth/Register.vue (guest only)
POST /register { business_name, name, email, password, phone?, gstin? }
  → RegisterTenantRequest::validate()
  → TenantRegistrationService::register()
     DB::transaction {
       Tenant::create(plan=starter, status=trial, trial_ends_at=+14d, rbi_bank_rate=6.75)
       User::create(tenant_id, role=owner)
     }
  → Auth::login(user)
  → Redirect /dashboard with flash 'welcome'
```

#### Suspended Account Flow

```
Any auth route → EnsureActiveTenant middleware
  → Super-admin (no tenant_id) → pass through
  → $tenant->hasActiveAccess()
      Active + subscription_ends_at in future → pass through
      Trial + trial_ends_at in future → pass through
      Any other case → Inertia::render('Auth/Suspended') HTTP 402
```

#### Files Created / Modified

| File | Purpose |
|---|---|
| `app/Enums/TenantPlan.php` | Added `maxUsers(): int` method |
| `app/Models/Tenant.php` | Added `hasActiveAccess()` + `trialDaysRemaining()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Extended tenant shared props with `is_trial`, `trial_days_remaining`, `trial_ends_at`, `max_vendors` |
| `app/Http/Requests/RegisterTenantRequest.php` | Validates business_name, name, email (unique:users), password (confirmed), phone (E.164), gstin (regex + unique:tenants) |
| `app/Services/TenantRegistrationService.php` | DB::transaction creates Tenant → User (Owner) |
| `app/Http/Controllers/RegisterController.php` | `show()` + `store()` — redirects to dashboard on success |
| `app/Services/PlanLimitService.php` | `canAddVendor()`, `canAddUser()`, `currentVendorCount()`, `currentUserCount()`, `vendorLimitMessage()`, `userLimitMessage()` |
| `app/Services/Import/VendorMatcher.php` | Added `$canCreate = true` parameter to `findOrCreate()` — throws RuntimeException when `$canCreate=false` |
| `app/DTOs/RowImportResult.php` | Added `bool $vendorCreated = false` property; updated `imported()` factory |
| `app/Services/Import/ImportPipeline.php` | Injects `PlanLimitService`; tracks vendor count locally; passes `$canCreateVendor` per row |
| `app/Http/Middleware/EnsureActiveTenant.php` | Checks `hasActiveAccess()`; renders `Auth/Suspended` with reason/plan/trial_ends_at |
| `bootstrap/app.php` | Registers `tenant.active` middleware alias |
| `app/Http/Requests/Settings/UpdateProfileRequest.php` | Validates all tenant profile fields; GSTIN unique ignoring own tenant |
| `app/Http/Requests/Settings/StoreTeamUserRequest.php` | Validates name, email (unique:users), role, password |
| `app/Http/Requests/Settings/UpdateTeamUserRequest.php` | Validates role (sometimes) and is_active (sometimes) |
| `app/Http/Controllers/Settings/ProfileController.php` | `index()` returns profile + billing + team + limits; `update()` saves tenant fields |
| `app/Http/Controllers/Settings/TeamController.php` | `store()` checks plan limit; `update()` same-tenant check; `destroy()` deactivates (cannot self-deactivate) |
| `routes/web.php` | Added `/register` (guest), `tenant.active` middleware on auth group, settings routes |
| `resources/js/Pages/Auth/Register.vue` | Registration form: business name, name, email, password, phone, GSTIN; "14-day trial" benefits panel |
| `resources/js/Pages/Auth/Suspended.vue` | Standalone page (no sidebar): trial_expired / subscription_expired / account_suspended with plan pricing + upgrade CTA |
| `resources/js/Pages/Settings/Index.vue` | Three-tab settings: Profile (business info + RBI rate), Team (user list + add member form + deactivate), Billing (plan info + usage bars + plan comparison) |
| `resources/js/Layouts/AppLayout.vue` | Added Settings nav item (Cog6ToothIcon); added trial countdown badge in topbar (yellow/red by urgency) |
| `tests/Feature/RegisterControllerTest.php` | 11 tests: registration, trial start, auto-login, GSTIN optional, validation |
| `tests/Unit/Services/PlanLimitServiceTest.php` | 12 tests: Starter/Growth/CaFirm limits, soft-delete exclusion, inactive user exclusion |
| `tests/Feature/Settings/ProfileControllerTest.php` | 11 tests: rendering, limit props, canManage per role, update, GSTIN unique, cross-tenant isolation |
| `tests/Feature/Settings/TeamControllerTest.php` | 10 tests: add member, plan limit block, role-gate, deactivate, self-deactivate guard, cross-tenant |

#### Routes Added

| Method | Path | Middleware | Handler |
|---|---|---|---|
| GET | `/register` | guest | `RegisterController@show` |
| POST | `/register` | guest | `RegisterController@store` |
| GET | `/settings` | auth, tenant.active | `ProfileController@index` |
| PUT | `/settings/profile` | auth, tenant.active | `ProfileController@update` |
| POST | `/settings/team` | auth, tenant.active | `TeamController@store` |
| PUT | `/settings/team/{user}` | auth, tenant.active | `TeamController@update` |
| DELETE | `/settings/team/{user}` | auth, tenant.active | `TeamController@destroy` |

#### Test Results

```
Phase 7 tests: 44 new tests
Full suite:    247 tests / 247 passing / 791 assertions
Duration:      ~7.8s (SQLite in-memory)
```

#### Known Limitations / Deferred

| Item | Deferred to |
|---|---|
| Razorpay / Stripe payment integration | Phase 9 |
| Subscription webhook handling (renewal, cancellation) | Phase 9 |
| YearEndSummary manual send UI | Phase 8 |
| CA Firm multi-client management UI | Phase 8 |
| Per-user alert preferences | Phase 8 |

---

### Phase 8 — Core Features, Testing & Factory Data ✅

**Completed:** 2026-06-19

#### Objectives

Complete the three core customer-facing features that were blocked on placeholders (Invoice CRUD, Payment recording, 43B(h) Calculator), write comprehensive test factories and a realistic demo seeder, and close all test coverage gaps with 301 passing tests.

#### Architecture Decisions

| Decision | Choice | Reason |
|---|---|---|
| `balance` column in tests | Regular SQLite column (not computed) | MySQL supports `storedAs()` generated columns; SQLite does not. PaymentController computes via SUM — both drivers are consistent |
| Payments nested under invoices | `DELETE /invoices/{invoice}/payments/{payment}` | Payments are always in the context of an invoice; no standalone payments list page needed |
| Calculator endpoint returns JSON | `POST /calculator/compute → JsonResponse` | Called via Axios from Vue; must return JSON for both success and validation errors |
| `shouldRenderJsonWhen` updated | Excludes `AuthenticationException` explicitly | Auth redirects must remain web redirects; validation errors on Axios routes must return 422 JSON |
| `InvoiceController::destroy` blocks if payments | Redirect back with errors (not abort) | 409 Conflict with session error gives the Inertia page context to display the block reason |
| `daysToDeadline` computed outside DTO | Calculated in controller from `Carbon::today()->diffInDays(deadline)` | `RiskAssessment` DTO is stateless/timeless; "today" is a runtime context |
| Factories set `balance` manually | `'balance' => $data['amount'] - ($data['paid_amount'] ?? 0)` | In SQLite test DB, balance is not computed; factory must maintain invariant |

#### Files Created

##### Controllers

| File | Purpose |
|---|---|
| `app/Http/Controllers/InvoiceController.php` | `index()` (paginated, filtered), `show()`, `update()` (deadline recalculate on agreement toggle), `destroy()` (blocked if payments exist) |
| `app/Http/Controllers/PaymentController.php` | `store()` (DB::transaction → SUM paid_amount → recompute risk), `destroy()` (verify tenant ownership → re-SUM → recompute) |
| `app/Http/Controllers/CalculatorController.php` | `index()` (passes vendor categories + tenant RBI rate), `compute()` (POST → JSON risk assessment) |

##### Form Requests

| File | Rules |
|---|---|
| `app/Http/Requests/UpdateInvoiceRequest.php` | `canManageInvoices()` authorize; narration (nullable, max 1000), agreement_exists (sometimes, boolean) |
| `app/Http/Requests/StorePaymentRequest.php` | `canManageInvoices()` authorize; amount (max = current balance), payment_date (before_or_equal:today), payment_mode (Enum), reference/notes optional |

##### Vue Pages

| File | Features |
|---|---|
| `resources/js/Pages/Invoices/Index.vue` | 4 KPI stat cards, debounced search, status/FY/vendor filters, quick pills, sortable table, Inertia pagination |
| `resources/js/Pages/Invoices/Show.vue` | Risk panel (amount/paid/balance/exposure), edit form (narration + agreement toggle), payment history with delete, record payment form, urgency sidebar, vendor info sidebar, danger zone |
| `resources/js/Pages/Calculator/Index.vue` | Category selector with 43B(h) indicator, invoice params form, live results panel (deadline, days status, disallowance, interest, effective tax rate, formula reference) |

##### Factories & Seeder

| File | Purpose |
|---|---|
| `database/factories/PurchaseInvoiceFactory.php` | States: pending/partial/paid/overdue/disallowed/withAgreement/micro/small/medium/forTenant/forVendor |
| `database/factories/PaymentFactory.php` | States: neft/upi/forInvoice |
| `database/factories/ImportBatchFactory.php` | States: pending/failed/tallyXml/forTenant |
| `database/seeders/DatabaseSeeder.php` | Two realistic tenants: "Arjun Textiles" (Starter, Active, 8 vendors, ~20 invoices) + "Rajesh & Associates" (Growth, Trial, 6 vendors, ~10 invoices) |

##### Tests

| File | Tests | What is Covered |
|---|---|---|
| `tests/Unit/Services/InvoiceRiskRecomputerTest.php` | 8 | pending/overdue/paid status transitions, large vendor → zero disallowance, recomputeForTenant skips paid, recomputeForVendor scoped, uses tenant bank rate, marks Disallowed after FY end |
| `tests/Unit/Services/VendorClassificationServiceTest.php` | 8 | classify updates category, dispatches job on change, no job when unchanged, records verification source, updates udyam_number, bulkClassify count, skips already-classified, dispatches per-changed vendor |
| `tests/Feature/EnsureActiveTenantTest.php` | 7 | active passes, trial-active passes, trial-expired → 402, subscription-expired → 402, inactive → 402, suspended → 402, super-admin passes |
| `tests/Feature/InvoiceControllerTest.php` | 14 | auth redirect, index props, tenant isolation, status filter, FY filter, search, show renders detail, show 404 cross-tenant, owner updates narration, agreement toggle recalculates deadline, viewer blocked, soft delete, destroy blocked with payments |
| `tests/Feature/PaymentControllerTest.php` | 10 | owner records payment, paid_amount updated, full payment → Paid status, amount > balance rejected, future date rejected, invalid mode rejected, finance can record, viewer blocked, owner deletes + recompute, cross-tenant payment → 404 |
| `tests/Feature/CalculatorControllerTest.php` | 7 | index renders props, Micro overdue → disallowance, Medium → zero, paid → Paid status, pre-deadline → Pending, agreement → 45-day deadline, validation errors (missing fields + bank_rate > 25) |

#### Routes Added

| Method | Path | Handler |
|---|---|---|
| GET | `/invoices` | `InvoiceController@index` |
| GET | `/invoices/{invoice}` | `InvoiceController@show` |
| PUT | `/invoices/{invoice}` | `InvoiceController@update` |
| DELETE | `/invoices/{invoice}` | `InvoiceController@destroy` |
| POST | `/invoices/{invoice}/payments` | `PaymentController@store` |
| DELETE | `/invoices/{invoice}/payments/{payment}` | `PaymentController@destroy` |
| GET | `/calculator` | `CalculatorController@index` |
| POST | `/calculator/compute` | `CalculatorController@compute` |

#### Demo Seeder Data

```
Tenant 1: Arjun Textiles Pvt Ltd (Starter plan, Active subscription)
  ├── Owner: arjun@arjuntextiles.com / password
  ├── Finance: priya@arjuntextiles.com / password
  ├── 8 vendors (3 Micro, 2 Small, 1 Medium, 1 Large, 1 Unclassified)
  ├── ~20 invoices (mix of all statuses: pending, partial, overdue, disallowed, paid)
  └── 8 payments across invoices

Tenant 2: Rajesh & Associates (Growth plan, Trial — 10 days left)
  ├── Owner: rajesh@rajeshca.com / password
  ├── 6 vendors (2 Micro, 2 Small, 2 Unclassified)
  ├── ~10 invoices
  └── 5 payments
```

#### Key Bug Fixed

`bootstrap/app.php` `shouldRenderJsonWhen` callback was configured as `fn ($r) => $r->is('api/*')` — this caused `ValidationException` on `/calculator/compute` (called via Axios with `Accept: application/json`) to redirect instead of returning 422 JSON. Fixed to:
```php
fn (Request $request, Throwable $e): bool => $e instanceof AuthenticationException
    ? false  // auth always redirects (Inertia SPA behaviour)
    : $request->is('api/*') || $request->expectsJson()
```

#### Test Results

```
Phase 8 new tests: 54
Full suite: 301 tests / 301 passing / 960 assertions
Duration: ~24s (SQLite in-memory)
Frontend: npm run build ✓ (no errors; ApexCharts chunk warning is expected)
```

#### Known Limitations / Deferred

| Item | Deferred to |
|---|---|
| Razorpay / Stripe payment integration | Phase 9 |
| YearEndSummary manual send UI | Phase 9 |
| CA Firm multi-client management UI | Phase 9 |
| Create vendor manually (without import) | Phase 9 |
| Vendor merge / de-duplicate UI | Phase 9 |

---

### Phase 9 — Production Deployment ✅

**Completed:** 2026-06-19

#### Objectives
Make the application fully production-ready: scheduled jobs, security hardening, Razorpay subscription billing, manual vendor creation, infrastructure configs, CI/CD pipeline, and a zero-downtime deploy script.

#### Architecture Decisions

| Decision | Rationale |
|---|---|
| Razorpay subscriptions (not orders) | Recurring billing with automatic retries and dunning management built-in |
| Webhook-first subscription sync + 6-hour polling | Webhooks are primary; `subscriptions:sync` job as a safety net for missed events |
| 7-day grace period on `subscription.halted` | Gives tenants time to fix payment issues without losing access immediately |
| `TenantStatus::Inactive` for cancelled subscriptions | Access blocked via `EnsureActiveTenant`, consistent with existing status enum |
| Rate limiters in `AppServiceProvider::boot()` | `RateLimiter::for()` uses the facade which requires a fully-booted container — not safe in `bootstrap/app.php` middleware closures |
| SecurityHeaders middleware on web group | Belt-and-suspenders with Nginx headers; middleware covers non-Nginx environments (dev, tests) |
| `audit:prune --years=10` (not 8) | Section 43B(h) requires 8-year trail; 10-year cutoff provides 2-year margin against accidental early deletion |
| CSRF exempt on `/webhooks/razorpay` | Razorpay POSTs raw JSON body; CSRF token not present. Security handled by HMAC-SHA256 signature verification inside controller |

#### Files Created

| File | Purpose |
|---|---|
| `app/Http/Middleware/SecurityHeaders.php` | X-Frame-Options, CSP, HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| `app/Providers/AppServiceProvider.php` | Rate limiter definitions: login (5/min), register (3/min), calculator (30/min), import (5/tenant-min), udyam (10/min), webhooks (120/min) |
| `app/Services/RazorpayService.php` | Razorpay API client: createCustomer, createSubscription, getSubscription, cancelSubscription, verifyWebhookSignature (HMAC-SHA256) |
| `app/Services/TenantSubscriptionService.php` | Webhook event handler: activated → Active, charged → extends end date, halted → 7-day grace period, cancelled/completed → Inactive |
| `app/Http/Controllers/WebhookController.php` | POST /webhooks/razorpay — CSRF-exempt, HMAC-verified, always returns 200 to prevent Razorpay retries |
| `app/Http/Controllers/SubscriptionController.php` | GET /subscribe (plan catalog), POST /subscribe/{plan} (create Razorpay subscription) |
| `app/Http/Requests/CreateVendorRequest.php` | Validates name, category, GSTIN (regex + unique-per-tenant), PAN, Udyam number, contact fields |
| `app/Console/Commands/PruneAuditLog.php` | `audit:prune --years=10` — deletes audit_logs records older than N years |
| `app/Console/Commands/SyncSubscriptions.php` | `subscriptions:sync` — reconciles all tenant subscriptions from Razorpay API |
| `resources/js/Pages/Vendors/Create.vue` | Manual vendor creation form: category buttons, GSTIN/PAN/Udyam fields, contact section |
| `resources/js/Pages/Subscription/Upgrade.vue` | Plan comparison page with Razorpay JS checkout integration |
| `deploy/nginx.conf` | Nginx: HTTPS, HTTP/2, gzip, static asset cache, rate limiting, PHP-FPM upstream |
| `deploy/php-fpm.conf` | PHP-FPM: dynamic pm (max 20), Opcache tuning, Redis session handler |
| `deploy/supervisor.conf` | 2 default workers + 1 import worker + scheduler process |
| `deploy/redis.conf` | allkeys-lru, 256MB maxmemory, persistence disabled, password required |
| `deploy/deploy.sh` | Zero-downtime: maintenance on → pull → composer → migrate → npm build → reload → maintenance off |
| `scripts/backup-db.sh` | Daily MySQL dump via mysqldump (single-transaction), 7-day local retention |
| `.github/workflows/ci.yml` | PHP test (SQLite) + npm build in parallel; SSH deploy on main push |

#### Files Modified

| File | Change |
|---|---|
| `routes/console.php` | Registered scheduler: msme:recompute-risk (18:35 UTC), msme:send-alerts (02:30 UTC), subscriptions:sync (every 6h), audit:prune (Sunday 01:00 UTC) |
| `routes/web.php` | Added throttle middleware on login, register, calculator, import, udyam routes; added /subscribe, /webhooks/razorpay, /vendors/create, POST /vendors routes |
| `bootstrap/app.php` | Added SecurityHeaders to web group; CSRF exempt for /webhooks/razorpay |
| `config/services.php` | Added Razorpay (key_id, key_secret, webhook_secret, plan IDs) |
| `app/Models/Tenant.php` | Added razorpay_customer_id, razorpay_subscription_id, razorpay_plan_id, grace_period_ends_at to fillable + casts |
| `app/Http/Controllers/VendorController.php` | Added create() and store() methods for manual vendor creation |
| `.env.example` | Full production template with all required keys documented |
| `database/migrations/2026_06_19_..._add_razorpay_fields_to_tenants_table.php` | New columns with indexes |

#### New Routes

```
GET  /subscribe                  → SubscriptionController@index     (auth + tenant.active)
POST /subscribe/{plan}           → SubscriptionController@subscribe  (auth + tenant.active)
POST /webhooks/razorpay          → WebhookController@razorpay        (public, throttle:webhooks)
GET  /vendors/create             → VendorController@create           (auth + tenant.active)
POST /vendors                    → VendorController@store            (auth + tenant.active)
```

#### Scheduler Entries

```
00:05 IST (18:35 UTC) daily  → msme:recompute-risk   (overlap protection: 30 min)
08:00 IST (02:30 UTC) daily  → msme:send-alerts       (overlap protection: 60 min)
Every 6 hours                → subscriptions:sync     (overlap protection: 20 min)
Sunday 01:00 UTC             → audit:prune --years=10 (overlap protection: 30 min)
```

#### Rate Limiting

```
Login:       5 requests/minute per IP
Register:    3 requests/minute per IP
Calculator:  30 requests/minute per user/IP
Import:      5 requests/minute per tenant/IP
Udyam:       10 requests/minute per user/IP
Webhooks:    120 requests/minute per IP
```

#### Production Deployment

```bash
# Server setup (Ubuntu 24.04)
# 1. Install Nginx, PHP 8.3-FPM, MySQL 8, Redis, Node 20, Supervisor
# 2. Copy config files from deploy/ directory
# 3. Set .env (copy from .env.example, fill all REQUIRED values)
# 4. Generate app key: php artisan key:generate
# 5. Add crontab entry OR use Supervisor for scheduler
# 6. Deploy: bash deploy/deploy.sh main
```

#### GitHub Actions Secrets Required

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | Production server IP/hostname |
| `DEPLOY_USER` | SSH user (e.g. `deploy`) |
| `DEPLOY_SSH_KEY` | Private SSH key (ED25519 recommended) |
| `DEPLOY_PORT` | SSH port (default 22) |

#### Test Results

```
Tests: 336 passed
Assertions: 1040
Duration: ~59s
Coverage: 5 new test files — WebhookControllerTest (8), SubscriptionControllerTest (5),
          VendorControllerTest additions (6), RazorpayServiceTest (7),
          TenantSubscriptionServiceTest (7) = 33 new tests (301 → 336 total)
```

#### Known Issues / Razorpay Integration Notes

- Razorpay subscription creation requires the tenant's customer to be created first — `createCustomer()` is idempotent and stores the customer ID
- The Razorpay JS SDK must be loaded via `<script>` tag in the layout for the checkout modal to work
- After a successful payment, Razorpay fires `subscription.activated` webhook — the tenant status updates asynchronously; the Upgrade.vue frontend reloads the page after payment to show updated status

---

### Phase 10 — Client Delivery & Onboarding ✅

**Completed:** 2026-06-19

#### Objectives
Complete the customer-facing delivery layer: Filament super-admin panel for tenant and user management, a guided onboarding wizard for new tenants, CA-grade annual 43B(h) disallowance reports (PDF + Excel), admin impersonation, and all supporting tests.

#### Architecture Decisions

| Decision | Rationale |
|---|---|
| `onboarding_completed_at` timestamp on Tenant | Single nullable field — `null` means incomplete, any timestamp means done. Simple, queryable, auditable. |
| `EnsureOnboardingComplete` middleware | Applied only to the main `auth + tenant.active + onboarding` group; exempt routes (onboarding itself, impersonate, logout) listed as named-route exceptions to prevent redirect loops |
| DomPDF for PDF generation | `barryvdh/laravel-dompdf` — well-maintained, Laravel-native, handles INR ₹ symbol via UTF-8 DejaVu font |
| Maatwebsite/Excel for Excel export | Already installed (`^3.1`); `VendorExposureExport` uses `FromCollection + WithHeadings + WithMapping + ShouldAutoSize` |
| `ReportService::annualSummary()` is pure | Takes a Tenant + FY year; returns a plain array. Controller and export class both consume the same data — no duplication |
| Interest computed on `amount - paid_amount` | `outstanding_amount` is not a DB column; derived at read time to stay in sync with payments |
| Impersonation uses session key `impersonating_admin_id` | Original admin ID stored in session, `Auth::login($owner)` switches user, `/impersonate/leave` restores. No additional package required |
| Filament `navigationIcon` type must be `string\|BackedEnum\|null` | Filament v5 parent class declares the exact type; PHP 8.3 requires child property types to match exactly — `?string` causes a fatal error |
| `TenantFactory` defaults `onboarding_completed_at = now()` | All existing tests assume onboarding is complete; factory provides an `->onboarding()` state for tests that specifically test the onboarding flow |

#### Files Created

| File | Purpose |
|---|---|
| `database/migrations/2026_06_19_160212_add_onboarding_completed_at_to_tenants_table.php` | Adds nullable `onboarding_completed_at` timestamp to `tenants` |
| `app/Filament/Resources/TenantResource.php` | Super-admin Filament resource: list all tenants, edit, suspend/activate/impersonate actions |
| `app/Filament/Resources/TenantResource/Pages/ListTenants.php` | Filament list page for TenantResource |
| `app/Filament/Resources/TenantResource/Pages/EditTenant.php` | Filament edit page for TenantResource |
| `app/Filament/Resources/UserResource.php` | Super-admin read-only user list across all tenants |
| `app/Filament/Resources/UserResource/Pages/ListUsers.php` | Filament list page for UserResource |
| `app/Filament/Widgets/AdminOverviewWidget.php` | StatsOverviewWidget: MRR, active tenants, trial tenants, churned this month |
| `app/Http/Middleware/EnsureOnboardingComplete.php` | Redirects to `/onboarding` when tenant `onboarding_completed_at IS NULL`; exempt for super-admins and named routes |
| `app/Http/Controllers/OnboardingController.php` | GET /onboarding (checklist), POST /onboarding/complete (sets timestamp) |
| `resources/js/Pages/Onboarding/Index.vue` | 5-step checklist with progress bar; POST to complete when all steps done |
| `app/Http/Controllers/ImpersonateController.php` | POST /admin/impersonate/{tenant} stores admin ID in session + logs in as owner; GET /impersonate/leave restores admin |
| `app/Services/ReportService.php` | `annualSummary(tenant, year)` — aggregates invoices, outstanding amounts, disallowance, interest at 3× RBI bank rate compounded monthly |
| `app/Exports/VendorExposureExport.php` | Maatwebsite Excel export: vendor-wise 43B(h) exposure with auto-sized columns and header styling |
| `app/Http/Controllers/ReportController.php` | GET /reports (index), GET /reports/{fy}/pdf (DomPDF download), GET /reports/{fy}/excel (Excel download) |
| `resources/views/reports/annual-summary.blade.php` | CA-grade PDF template: summary tiles, vendor detail table, legal disclaimer, company header |
| `resources/js/Pages/Reports/Index.vue` | Year selector with PDF + Excel download buttons for last 5 financial years |
| `tests/Feature/OnboardingControllerTest.php` | 7 tests: page accessible, guest redirect, complete sets timestamp, complete requires auth, middleware redirects unfinished tenant, completed tenant passes through, steps structure |
| `tests/Feature/ReportControllerTest.php` | 8 tests: index renders, guest redirect, PDF content type, Excel content type, PDF filename, Excel filename, invalid FY → 404, tenant isolation |
| `tests/Unit/Services/ReportServiceTest.php` | 8 tests: structure, overdue → disallowance, paid → no disallowance, out-of-FY excluded, interest = 0 for non-overdue, interest > 0 for overdue, interest = 0 when fully paid, vendor rows aggregate correctly |

#### Files Modified

| File | Change |
|---|---|
| `app/Models/Tenant.php` | Added `onboarding_completed_at` to fillable + casts as datetime; added `hasCompletedOnboarding()` helper |
| `app/Providers/Filament/AdminPanelProvider.php` | Registered `AdminOverviewWidget` in widgets array |
| `bootstrap/app.php` | Added `'onboarding' => EnsureOnboardingComplete::class` middleware alias |
| `routes/web.php` | Added onboarding, impersonation, and report routes; main auth group now applies `onboarding` middleware |
| `database/factories/TenantFactory.php` | Default `onboarding_completed_at = now()`; added `->onboarding()` state (null) |
| All 15 feature test setUp() files | Added `'onboarding_completed_at' => now()` to `Tenant::create()` calls so existing tests pass with the new middleware |

#### New Routes

```
GET  /onboarding                   → OnboardingController@index    (auth)
POST /onboarding/complete          → OnboardingController@complete  (auth)
POST /admin/impersonate/{tenant}   → ImpersonateController@start    (auth, super-admin only)
GET  /impersonate/leave            → ImpersonateController@leave     (auth)
GET  /reports                      → ReportController@index          (auth + tenant.active + onboarding)
GET  /reports/{fy}/pdf             → ReportController@pdf            (auth + tenant.active + onboarding)
GET  /reports/{fy}/excel           → ReportController@excel          (auth + tenant.active + onboarding)
```

#### Onboarding Checklist Steps

| # | Key | Done When |
|---|---|---|
| 1 | `profile` | `gstin` and `state` both non-empty |
| 2 | `vendors` | At least 1 vendor exists |
| 3 | `invoices` | At least 1 purchase invoice exists |
| 4 | `alerts` | `settings.alert_enabled` is truthy |
| 5 | `team` | Tenant has more than 1 user |

#### Admin Dashboard (Filament /admin)

| Widget / Resource | What it shows |
|---|---|
| AdminOverviewWidget | MRR (₹), Active tenants, Total tenants, Churned this month |
| TenantResource | All tenants; searchable/filterable; suspend/activate/impersonate table actions |
| UserResource | All users across all tenants; read-only; shows tenant name, role, last login |

#### Dependency Added

| Package | Version | Purpose |
|---|---|---|
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation for annual 43B(h) reports |

#### Test Results

```
Phase 10 new tests: 23
Full suite: 359 tests / 359 passing / 1112 assertions
Duration: ~32s (SQLite in-memory)
Frontend: npm run build ✓
```

#### Known Limitations / Deferred

| Item | Notes |
|---|---|
| Nested impersonation blocked | Correct — second impersonate attempt returns 403 |
| PDF ₹ symbol requires UTF-8 DomPDF config | Set `pdf.options.defaultFont` to `dejavusans` in config/dompdf.php if ₹ renders as ? |
| AdminOverviewWidget MRR is plan price × active tenants | Does not deduct prorated cancellations; accurate for trend, not ARR |
| Onboarding steps are hardcoded in PHP | Easily extendable via `OnboardingController::buildChecklist()` |

---

### Phase 11 — Local LLM Integration ✅

#### Objectives

Integrate Ollama (local LLM) for two high-value automation tasks: (1) vendor name fuzzy matching during CSV/Tally XML import to prevent duplicate vendor records; (2) auto-classification of unclassified vendors using MSME Udyam turnover criteria. Both are confidence-gated — below threshold, results are suggestions only; a human confirms before changes persist.

#### Architecture Decisions

| Decision | Rationale |
|---|---|
| `LlmClient` contract interface | `OllamaClient` implements `LlmClient`; services type-hint the interface — testable via mock without touching HTTP, and swappable to another provider later |
| `LLM_ENABLED=false` default | Zero performance impact when disabled; import pipeline and classify endpoints behave exactly as before Phase 11 |
| LLM inserted as step 3.5 in `VendorMatcher` | Preserves existing match priority: GSTIN → Udyam → exact name → LLM fuzzy → create new |
| JSON-format Ollama prompt (`"format":"json"`) | Structured output eliminates free-text parsing fragility; `temperature=0, seed=42` for deterministic responses |
| Confidence threshold (default 80%) | Below threshold: result returned as suggestion, human confirms; above threshold: auto-applied in batch command |
| `llm_confidence + llm_reasoning` columns on vendors | Full audit trail for every AI-generated classification — CA-grade traceability |
| `VendorVerificationSource::Llm` (pre-existing enum case) | No enum migration needed; audit column was already designed for AI in Phase 4 |
| `OllamaClient` stays `final` | Concrete infrastructure adapter — shouldn't be extended; `LlmClient` interface handles testability |
| `VendorFuzzyMatcher`, `VendorCategoryClassifier` non-final | Need to be mockable in feature tests via Mockery; PHPUnit cannot mock final classes |

#### Files Created

| File | Purpose |
|---|---|
| `config/llm.php` | LLM_ENABLED, LLM_ENDPOINT, LLM_MODEL, LLM_TIMEOUT, LLM_CONFIDENCE_THRESHOLD, LLM_MAX_MATCH_CANDIDATES |
| `app/Contracts/LlmClient.php` | Interface: generate(), isAvailable(), getModel(), getEndpoint() |
| `app/Services/OllamaClient.php` | Implements LlmClient — Guzzle wrapper for `/api/generate` and `/api/tags`; silent on failure (returns null) |
| `app/Services/Llm/VendorFuzzyMatcher.php` | Prompts LLM with imported name + up to 20 candidate vendors; returns LlmMatchResult or null |
| `app/Services/Llm/VendorCategoryClassifier.php` | Prompts LLM with vendor name + GSTIN state code + state; returns LlmClassificationResult or null |
| `app/DTOs/LlmMatchResult.php` | readonly DTO: vendorId, vendorName, confidence, reasoning |
| `app/DTOs/LlmClassificationResult.php` | readonly DTO: category (VendorCategory), confidence, reasoning, autoApplied |
| `app/Http/Controllers/LlmClassifyController.php` | review() — AI Review page; suggest() — single vendor suggestion; apply() — confirm suggestion; batch() — classify all unclassified; status() — Ollama health check |
| `app/Console/Commands/AiClassifyVendors.php` | `ai:classify-vendors --tenant= --dry-run --force` — progress bar, table output, auto-applies above threshold |
| `resources/js/Components/AiClassifyButton.vue` | "AI Suggest" button → spinner → result card with confidence badge → Confirm/Dismiss |
| `resources/js/Pages/Vendors/AiReview.vue` | Grid of unclassified vendor cards with AiClassifyButton; "Classify All" triggers batch endpoint |
| `database/migrations/2026_06_19_..._add_llm_fields_to_vendors_table.php` | `llm_confidence DECIMAL(4,3) NULL`, `llm_reasoning TEXT NULL` |
| `tests/Unit/Services/OllamaClientTest.php` | 8 tests: generate success, HTTP error, connection error, isAvailable matching, JSON format sent |
| `tests/Unit/Services/VendorFuzzyMatcherTest.php` | 8 tests: match found, null vendor_id, below threshold, client null, invalid JSON, unknown ID, empty candidates, custom threshold |
| `tests/Unit/Services/VendorCategoryClassifierTest.php` | 8 tests: micro result, autoApplied false, client unavailable, invalid JSON, unknown category, unclassified rejected, large vendor, GSTIN in prompt |
| `tests/Feature/LlmClassifyControllerTest.php` | 10 tests: review renders, LLM disabled 404, unclassified only list, suggest JSON, suggest 503, suggest 404 disabled, apply persists, apply validation, batch summary, status endpoint |

#### Files Modified

| File | Change |
|---|---|
| `app/Models/Vendor.php` | Added llm_confidence, llm_reasoning to fillable; float cast for llm_confidence |
| `app/Services/Import/VendorMatcher.php` | Injected VendorFuzzyMatcher; added step 3.5 llmFuzzyMatch() between name-match and create |
| `app/Providers/AppServiceProvider.php` | Singleton bindings for OllamaClient, LlmClient, VendorFuzzyMatcher, VendorCategoryClassifier, VendorMatcher |
| `routes/web.php` | GET /vendors/ai-review, POST /vendors/ai-classify-batch (before {vendor} wildcard), POST /vendors/{vendor}/ai-classify, POST /vendors/{vendor}/ai-classify/apply, GET /ai/status |
| `.env.example` | Added LLM_ENABLED, LLM_ENDPOINT, LLM_MODEL, LLM_TIMEOUT, LLM_CONFIDENCE_THRESHOLD, LLM_MAX_MATCH_CANDIDATES |

#### New Routes

```
GET  /vendors/ai-review                       → LlmClassifyController@review   (auth + tenant.active + onboarding)
POST /vendors/ai-classify-batch               → LlmClassifyController@batch    (auth + tenant.active + onboarding)
POST /vendors/{vendor}/ai-classify            → LlmClassifyController@suggest  (auth + tenant.active + onboarding)
POST /vendors/{vendor}/ai-classify/apply      → LlmClassifyController@apply    (auth + tenant.active + onboarding)
GET  /ai/status                               → LlmClassifyController@status   (auth + tenant.active + onboarding)
```

#### Enabling Ollama Locally

```bash
# 1. Install Ollama: https://ollama.ai
ollama pull qwen2.5:3b          # ~2 GB download

# 2. Set env vars in .env
LLM_ENABLED=true
LLM_ENDPOINT=http://localhost:11434
LLM_MODEL=qwen2.5:3b

# 3. Classify all unclassified vendors (dry run first)
php artisan ai:classify-vendors --dry-run
php artisan ai:classify-vendors

# 4. Or use the web UI: /vendors/ai-review
```

#### Artisan Command

```
php artisan ai:classify-vendors [--tenant=<id>] [--dry-run] [--force]

Options:
  --tenant=  Restrict to a single tenant (omit for all tenants)
  --dry-run  Show predictions without persisting — safe for review
  --force    Apply even below the confidence threshold (use with caution)
```

#### Prompt Design

**Fuzzy Match prompt** sends:
- Imported vendor name
- Up to 20 existing vendor candidates with ID, name, GSTIN
- Instructions to handle Pvt/Private, Ltd/Limited, abbreviation variants
- Asks for `{"vendor_id": int|null, "confidence": float, "reasoning": string}`

**Category Classification prompt** sends:
- Vendor name, optional GSTIN (with state code), optional state
- Udyam turnover thresholds (Micro ≤ ₹5cr, Small ≤ ₹50cr, Medium ≤ ₹250cr, Large > ₹250cr)
- Conservative bias instruction: prefer Micro/Small when uncertain (safe for tax compliance)
- Asks for `{"category": "micro|small|medium|large", "confidence": float, "reasoning": string}`

#### Test Results

```
Tests:      393 passed (359 → 393, +34 new tests)
Assertions: 1,196
Duration:   ~43s (SQLite in-memory)
Frontend:   npm run build ✓
```

#### Known Limitations

| Item | Notes |
|---|---|
| LLM_ENABLED=false in .env.example | Intentional — Ollama must be installed and model pulled before enabling |
| Fuzzy match candidate limit: 20 | Sending 100+ candidates degrades LLM accuracy and increases latency; 20 is optimal for 3b models |
| First inference after cold start: 10-20 s | Ollama loads model into VRAM on first request; subsequent calls are fast (~1-3 s) |
| Batch classification is synchronous | For tenants with 500+ vendors consider wrapping in a queued job |
| Production server needs GPU or ARM CPU | `qwen2.5:3b` runs on CPU (~30s/inference) or GPU (~1s/inference); plan accordingly |

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


### Onboarding Checklist Steps

| # | Key | Done When |
|---|---|---|
| 1 | `profile` | `gstin` and `state` both non-empty |
| 2 | `vendors` | At least 1 vendor exists |
| 3 | `invoices` | At least 1 purchase invoice exists |
| 4 | `alerts` | `settings.alert_enabled` is truthy |
| 5 | `team` | Tenant has more than 1 user |

### Admin Dashboard (Filament /admin)

| Widget/Resource | What it shows |
|---|---|
| AdminOverviewWidget | MRR (₹), Active tenants, Total tenants, Churned this month |
| TenantResource | All tenants across all DB rows (no TenantScope); searchable/filterable; suspend/activate/impersonate actions |
| UserResource | All users across all tenants; read-only; shows tenant name, role, last login |

### Dependency Added

| Package | Version | Purpose |
|---|---|---|
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation for annual 43B(h) reports |

### Test Results

```
Phase 10 new tests: 23
Full suite: 359 tests / 359 passing / 1112 assertions
Duration: ~32s (SQLite in-memory)
Frontend: npm run build ✓ (chunk size warning on ApexCharts is expected; all chunks built successfully)
```

### Known Limitations / Deferred

| Item | Notes |
|---|---|
| Impersonation does not support nested impersonation | Correct behaviour — second impersonate attempt returns 403 |
| PDF reports use DejaVu Sans for ₹ symbol — requires UTF-8 DomPDF config | Set `pdf.options.defaultFont` to `dejavusans` in config/dompdf.php if ₹ renders as ? |
| AdminOverviewWidget MRR is computed from plan price × active tenants | Does not deduct prorated cancellations; accurate for MRR trend, not ARR |
| Onboarding steps are hardcoded in PHP | Easily extendable — each step is a keyed array entry in `OnboardingController::buildChecklist()` |

---

## Repository

GitHub: [githubgobi/laravel-msme-payment-risk-tracker](https://github.com/githubgobi/laravel-msme-payment-risk-tracker)

---

## License

Proprietary. All rights reserved.
