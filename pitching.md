# MSME Payment Risk Tracker — Pitch Knowledge Base

---

## 1. The Problem Statement

**Indian businesses are getting hit with surprise tax penalties they don't even know they're building up — every single day.**

When a company buys goods or services from a Micro or Small business (registered under MSME/Udyam), they are legally required to pay within:
- **15 days** if there's no written agreement
- **45 days** if there's a written agreement

Miss that deadline, and under **Section 43B(h) of the Income Tax Act** (effective April 2023):
- The unpaid amount is **disallowed as a business expense** — added back to taxable income
- The company must also **pay interest to the vendor at 3× the RBI bank rate**, compounded monthly (~18–19% per year)

**The tragedy:** Most finance teams track payables in Tally or Excel. They have zero visibility into which vendors are MSME-registered, which invoices are approaching deadline, and how much tax exposure is silently building up. They find out at year-end — when the auditor tells them. By then it's too late.

---

## 2. Sector-Level Knowledge — Why This Matters in India

### The MSME Sector
| Fact | Number |
|---|---|
| Total MSMEs in India | 63+ million |
| Contribution to India's GDP | ~30% |
| Employment | 110+ million people |
| Share of India's exports | ~45% |
| Registered on Udyam portal | 5+ crore (growing daily) |

MSMEs are the backbone of India's supply chain. Almost every mid-size or large Indian business — a textile company, a pharma distributor, a construction firm, an IT services company — buys from multiple Micro/Small vendors.

### The Law: Section 43B(h) — Finance Act 2023
- Introduced via **Finance Bill 2023**, effective **Assessment Year 2024-25** (i.e., FY 2023-24 onwards)
- It amended Section 43B of the Income Tax Act to include MSME payments as a "deductible only when actually paid within deadline" expense
- **No grandfather clause** — applicable from the first year
- **No self-correction** — if you miss the deadline in FY 2023-24, you cannot fix it retroactively

### Why the Market is Unaware
- Tally, the dominant accounting software in India, has no built-in 43B(h) deadline tracker
- Zoho Books has no 43B(h) alert system
- CA firms are advising clients verbally but have no tooling to monitor it at scale
- Most companies discovered the problem only during their FY 2023-24 audit — and paid heavily

### RBI Rate Context
- Current RBI bank rate: ~6.75%
- 3× RBI rate = ~20.25% per year, **compounded monthly**
- This is more expensive than most working capital loans

---

## 3. What We've Built

A **multi-tenant SaaS platform** that plugs into any existing Tally workflow and eliminates 43B(h) exposure.

### Core Features (all complete)
| Feature | What it does |
|---|---|
| **Tally Import** | Accepts Tally XML or CSV ledger exports — no API needed, works with any Tally version |
| **Vendor Classification** | Identifies Micro/Small/Medium/Large vendors via Udyam number or AI classification |
| **Udyam Verification** | Live API call to verify MSME registration status |
| **43B(h) Rules Engine** | Calculates exact payment deadline per invoice (15-day or 45-day rule) |
| **Risk Dashboard** | Shows at-risk balance, projected disallowance, projected interest — live |
| **Email & WhatsApp Alerts** | Notifies finance team before deadlines breach |
| **Disallowance Reports** | Generates CA-ready PDF/Excel for ITR filing |
| **Local AI Classification** | Uses Ollama (local LLM) to auto-classify vendors by name — no data leaves the machine |
| **Multi-Tenant** | CA firms can manage all their client businesses from one login |
| **Role-Based Access** | Owner / Admin / Finance / Viewer roles per tenant |
| **Billing** | Razorpay subscription integration, plan limits enforced |

### Technical Achievement
- **393 automated tests** — all passing
- **Laravel 13 + Filament v5** admin panel (super-admin + impersonation)
- **Single-database multi-tenancy** with row-level isolation
- **3 tenant plans**: Starter (₹1,500/mo), Growth (₹3,000/mo), CA Firm (₹4,000/mo)
- Production-ready: Nginx config, supervisor, CI/CD pipeline included

---

## 4. Business Impact — The Numbers

### Example: A Mid-Size Manufacturing Company
- Annual purchases from Micro/Small vendors: **₹2 crore**
- Finance team misses deadlines on 25% of invoices: **₹50 lakh at risk**
- Tax rate: 30%
- **Disallowance cost:** ₹50L × 30% = **₹15 lakh additional tax**
- **Interest cost to vendor:** ₹50L × 20% = **₹10 lakh**
- **Total annual hit: ₹25 lakh**
- **Our product cost: ₹18,000/year (Starter plan)**
- **ROI: 138×**

### What One CA Firm Saves Their Clients
- A CA firm managing 10 businesses: each business saves ₹5–25L in avoidable penalties
- CA firm earns credibility, retains clients, justifies higher fees
- Our CA Firm plan: ₹48,000/year — covers unlimited clients

### Live Demo Data (in the app right now)
- 4 active tenants, 19 vendors, 27 invoices
- ₹80.18 lakh outstanding across Micro/Small vendors
- 20 at-risk invoices
- Projected disallowance + interest visible on dashboard in real time

---

## 5. Market Opportunity

### Who Needs This?
| Segment | Est. Size | Why They Need It |
|---|---|---|
| Manufacturing SMEs (₹5–100cr turnover) | ~2 lakh businesses | High MSME vendor dependency |
| Trading companies | ~5 lakh businesses | Multiple small suppliers |
| IT/ITES companies | ~50,000 businesses | Subcontractors often MSME-registered |
| Pharma distributors | ~30,000 businesses | Supply chain is MSME-heavy |
| CA firms / Accounting firms | ~1.5 lakh firms | Need to manage this for all clients |

### TAM / SAM / SOM
| Level | Estimate | Basis |
|---|---|---|
| **TAM** (Total addressable market) | ₹500–1,000 crore/year | All Indian businesses with MSME vendor exposure |
| **SAM** (Serviceable market) | ₹100–200 crore/year | Tech-forward businesses ₹5–500cr turnover |
| **SOM** (Target Year 1) | ₹50–75 lakh/year | 150–200 paying customers |

### Revenue Target
- 150 customers × average ₹2,500/mo = **₹3.75 lakh/month = ₹45 lakh/year**
- 500 customers (Year 2) = **₹1.25 crore/year**

---

## 6. Competitive Landscape

| Competitor | What They Do | Gap We Fill |
|---|---|---|
| **Tally ERP** | Full accounting | No 43B(h) tracking, no MSME deadline alerts |
| **Zoho Books** | Cloud accounting | No 43B(h) module, no Udyam verification |
| **ClearTax / Cleartds** | GST/TDS compliance | Not focused on payment deadline tracking |
| **Excel + CA manually** | Current reality for most | No automation, misses deadlines, error-prone |
| **No direct SaaS competitor** | — | **This is an open market** |

**Key moat:** We're not replacing Tally. We sit on top of it. Finance teams keep using Tally — they just export to us weekly. Zero workflow disruption.

---

## 7. Pros & Cons — Honest Assessment

### Pros
- **Clear, measurable ROI** — easy to sell; the math does the work
- **Regulatory tailwind** — law is only getting stricter, not relaxed
- **No direct competitor** — no established SaaS player owns this niche yet
- **CA firm channel** — one CA firm sale = access to 10–50 businesses
- **Sticky product** — once finance teams integrate it into their monthly close, they don't leave
- **Low infrastructure cost** — SaaS margins 70%+
- **AI-assisted** — local LLM for vendor classification reduces onboarding friction
- **India-first** — deep understanding of Tally, Indian tax law, CA workflow

### Cons / Risks
- **Niche dependence** — only relevant for businesses buying from Micro/Small MSME vendors
- **Manual import step** — no real-time Tally API; weekly ledger exports are the workaround
- **Price sensitivity** — Indian SME market negotiates hard; ₹1,500/mo may face resistance
- **Regulatory risk** — if the government relaxes 43B(h) (unlikely), urgency drops
- **ERP vendor risk** — if Tally or Zoho builds this natively, it becomes a feature not a product
- **Awareness gap** — many CFOs still don't fully understand 43B(h); education is part of the sale

---

## 8. Why Now?

1. **FY 2023-24 was year one** — businesses are only now seeing the penalty in their tax returns. Pain is fresh and real.
2. **FY 2024-25 filings are due** — every CA in India is right now looking at clients' books and finding 43B(h) exposure.
3. **Udyam registrations growing** — 5 crore+ businesses now on the Udyam portal; buyer companies face more MSME vendors every month.
4. **WhatsApp-native India** — our alert system fits perfectly into how Indian businesses communicate.
5. **No competitor has moved** — the window is open right now.

---

## 9. Pitch Scripts

### 30-Second Elevator Pitch
> "If your business buys from small vendors — any Udyam-registered supplier — you now have a 15 to 45-day legal deadline to pay them. Miss it, and the Income Tax department adds that unpaid amount back to your taxable income. For a ₹50 lakh liability, that's ₹15 lakh in unexpected tax. Our software imports your Tally data, identifies every at-risk invoice, and sends you alerts before the deadline. It costs ₹1,500 a month. Most clients recover that in the first week."

### 2-Minute Investor Pitch
> "Section 43B(h) is a 2023 amendment to the Income Tax Act that mandates payment to MSME vendors within 15 to 45 days. Miss the deadline, and the expense is disallowed — it gets added back to taxable income. At a 30% tax rate, a company with ₹1 crore of delayed MSME payments is looking at ₹30 lakh in additional tax — every year.
>
> The problem is that nobody is tracking this. Finance teams use Tally. Tally has no 43B(h) module. CA firms are advising clients verbally, but they have no tool to monitor it across their entire client portfolio.
>
> We built a SaaS platform that sits on top of Tally. Finance teams export their ledger, we import it, classify vendors using Udyam verification and AI, calculate exact deadlines, and send alerts via WhatsApp and email before any deadline is missed.
>
> 63 million MSMEs. Every Indian business with MSME suppliers is exposed. No direct competitor. We're priced at ₹1,500 to ₹4,000 per month. 150 customers gets us to ₹45 lakh annual revenue. CA firms are our multiplier — one CA firm sale covers 10 to 50 client businesses.
>
> The product is built. 393 tests pass. Four tenants live in the system. We're ready to go to market."

### CA Firm Sales Pitch
> "As a CA, you're the one who discovers your client's 43B(h) exposure at year-end — after it's too late. We give you a tool so you catch it 30 days before it becomes a problem. You manage all your clients from one dashboard. You get ahead of the issue, not behind it. For ₹4,000 a month, you protect every client you have — and you become the CA who saved them lakhs."

---

## 10. One-Page Summary Card

| | |
|---|---|
| **Product** | MSME 43B(h) Payment Risk Tracker |
| **Problem** | Businesses miss MSME payment deadlines → disallowance + ~20% interest |
| **Solution** | Tally import → deadline tracking → real-time alerts → CA-ready reports |
| **Market** | 2L+ target businesses + 1.5L CA firms in India |
| **Revenue model** | SaaS: ₹1,500 / ₹3,000 / ₹4,000 per month |
| **Year 1 target** | 150 customers = ₹45L ARR |
| **Moat** | The law is the moat. We track compliance no one else is tracking. |
| **Status** | Product complete, tested, seeded, deployed-ready |
| **Ask** | Go-to-market: CA firm partnerships + digital outreach |
