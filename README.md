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
| Frontend | Livewire v4, Blade, Tailwind CSS |
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
- No multi-tenancy scaffolding yet → Phase 1
- No RBAC yet → Phase 1
- Redis not configured for production queue → Phase 9
- Ollama/Qwen LLM not yet integrated → Phase 4

---

### Phase 1 — Database Architecture *(In Progress)*

---

### Phase 2 — Core Rules Engine *(Planned)*

---

### Phase 3 — Import Pipeline — CSV & Tally XML *(Planned)*

---

### Phase 4 — Vendor Classification & Udyam Verification *(Planned)*

---

### Phase 5 — Dashboard UI *(Planned)*

---

### Phase 6 — Alerts System — Email & WhatsApp *(Planned)*

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
