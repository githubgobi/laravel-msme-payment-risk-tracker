# MSME Payment Risk Tracker — Pitch Knowledge Base (தமிழ்)

---

## 1. சிக்கல் என்ன? (Problem Statement)

**இந்திய வணிக நிறுவனங்கள் தினமும் தெரியாமலே வரி அபராதத்தை சேர்த்துக்கொண்டிருக்கின்றன.**

ஒரு நிறுவனம் Micro அல்லது Small வணிகர்களிடம் (Udyam-பதிவு செய்யப்பட்டவர்கள்) பொருட்கள் அல்லது சேவைகள் வாங்கும்போது, சட்டப்படி குறிப்பிட்ட நாட்களுக்குள் பணம் செலுத்த வேண்டும்:
- **15 நாட்களுக்குள்** — எழுத்துப்பூர்வ ஒப்பந்தம் இல்லாவிட்டால்
- **45 நாட்களுக்குள்** — எழுத்துப்பூர்வ ஒப்பந்தம் இருந்தால்

இந்த deadline தவறினால், **Section 43B(h) of the Income Tax Act** (April 2023 முதல் நடைமுறையில்) படி:
- செலுத்தாத தொகை **business expense ஆக அனுமதிக்கப்படாது** — அது taxable income-ல் சேர்க்கப்படும்
- கூடவே vendor-க்கு **3× RBI bank rate வட்டி** (மாதாமாதம் compound ஆகும், ~18–19% per year) செலுத்த வேண்டும்

**கசப்பான உண்மை:** பெரும்பாலான finance team-கள் payables-ஐ Tally அல்லது Excel-ல் track செய்கின்றன. எந்த vendor MSME-பதிவு செய்தவர் என்று தெரியாது. எந்த invoice deadline நெருங்குகிறது என்று தெரியாது. tax exposure எவ்வளவு என்று தெரியாது. ஆண்டு இறுதியில் auditor சொல்லும்போது மட்டும் தெரியும் — அப்போது திருத்திக்கொள்ள வழியில்லை.

---

## 2. துறை அறிவு — இது ஏன் இந்தியாவில் முக்கியம்? (Sector-Level Knowledge)

### MSME துறை புள்ளிவிவரங்கள்
| தகவல் | எண்ணிக்கை |
|---|---|
| இந்தியாவில் மொத்த MSMEs | 6.3 கோடிக்கும் அதிகம் |
| GDP-ல் பங்களிப்பு | ~30% |
| வேலைவாய்ப்பு | 11 கோடிக்கும் அதிகம் |
| ஏற்றுமதியில் பங்கு | ~45% |
| Udyam portal-ல் பதிவு | 5 கோடிக்கும் அதிகம் (தினமும் வளர்கிறது) |

MSME-கள் இந்தியாவின் supply chain-ன் முதுகெலும்பு. ஒவ்வொரு நடுத்தர மற்றும் பெரிய நிறுவனமும் — textile, pharma, construction, IT — பல Micro/Small vendor-களிடம் வாங்குகின்றன.

### சட்டம்: Section 43B(h) — Finance Act 2023
- **Finance Bill 2023** மூலம் அறிமுகப்படுத்தப்பட்டது
- **Assessment Year 2024-25** (FY 2023-24) முதல் நடைமுறை
- MSME-க்கு செய்யும் payment-ஐ deadline-க்குள் செய்தால் மட்டுமே business expense ஆக அனுமதிக்கப்படும்
- **முதல் ஆண்டிலேயே பொருந்தும்** — grandfather clause இல்லை
- **பழைய குற்றங்களை திருத்த முடியாது** — FY 2023-24-ல் தவறினால் retroactive திருத்தம் இல்லை

### ஏன் பலருக்கு இன்னும் தெரியவில்லை?
- Tally-ல் 43B(h) deadline tracking இல்லை
- Zoho Books-ல் 43B(h) alert system இல்லை
- CA-கள் verbally சொல்கிறார்கள் — monitor செய்ய tool இல்லை
- பெரும்பாலான நிறுவனங்கள் FY 2023-24 audit-ல் தான் பிரச்சனை கண்டுபிடித்தன — அப்போது மிகவும் தாமதம்

### RBI Rate விளக்கம்
- தற்போதைய RBI bank rate: ~6.75%
- 3× RBI rate = ~20.25% per year, **மாதாமாதம் compound ஆகும்**
- பெரும்பாலான working capital loan-களை விட இது அதிகம்

---

## 3. நாம் என்ன கட்டியிருக்கிறோம்? (What We've Built)

Tally workflow-ஐ மாற்றாமல், அதன் மேல் உட்கார்ந்து 43B(h) exposure-ஐ முழுவதும் நிர்வகிக்கும் **multi-tenant SaaS platform**.

### Core Features (அனைத்தும் complete)
| Feature | என்ன செய்கிறது |
|---|---|
| **Tally Import** | Tally XML அல்லது CSV ledger export ஏற்றுக்கொள்கிறது — எந்த Tally version-லும் வேலை செய்யும் |
| **Vendor Classification** | Udyam number அல்லது AI மூலம் Micro/Small/Medium/Large vendor-களை identify செய்கிறது |
| **Udyam Verification** | MSME பதிவை live API மூலம் verify செய்கிறது |
| **43B(h) Rules Engine** | ஒவ்வொரு invoice-க்கும் சரியான payment deadline கணக்கிடுகிறது |
| **Risk Dashboard** | At-risk balance, projected disallowance, projected interest — real time-ல் காட்டுகிறது |
| **Email & WhatsApp Alerts** | Deadline வருவதற்கு முன்பே finance team-க்கு அறிவிப்பு அனுப்புகிறது |
| **Disallowance Reports** | CA-க்கு தேவையான PDF/Excel report தயார் செய்கிறது |
| **Local AI Classification** | Ollama (local LLM) மூலம் vendor பெயரை வைத்து auto-classify செய்கிறது — data வெளியே போவதில்லை |
| **Multi-Tenant** | CA firm-கள் அனைத்து client நிறுவனங்களையும் ஒரே login-ல் நிர்வகிக்கலாம் |
| **Role-Based Access** | Owner / Admin / Finance / Viewer roles — ஒவ்வொரு tenant-க்கும் |
| **Billing** | Razorpay subscription integration, plan limits enforce ஆகும் |

### Technical சாதனை
- **393 automated tests** — அனைத்தும் pass
- **Laravel 13 + Filament v5** admin panel (super-admin + impersonation)
- **Single-database multi-tenancy** — row-level isolation
- **3 tenant plans**: Starter (₹1,500/மாதம்), Growth (₹3,000/மாதம்), CA Firm (₹4,000/மாதம்)
- Production-ready: Nginx config, supervisor, CI/CD pipeline

---

## 4. வணிக தாக்கம் — எண்களில் (Business Impact)

### உதாரணம்: ஒரு நடுத்தர உற்பத்தி நிறுவனம்
- Micro/Small vendor-களிடம் ஆண்டுக்கு வாங்குவது: **₹2 கோடி**
- Finance team 25% invoice-களில் deadline தவறுகிறது: **₹50 லட்சம் risk-ல்**
- Tax rate: 30%
- **Disallowance cost:** ₹50L × 30% = **₹15 லட்சம் கூடுதல் வரி**
- **Vendor-க்கு interest:** ₹50L × 20% = **₹10 லட்சம்**
- **மொத்த ஆண்டு இழப்பு: ₹25 லட்சம்**
- **நம் software cost: ₹18,000/ஆண்டு (Starter plan)**
- **ROI: 138 மடங்கு**

### ஒரு CA Firm என்ன சேமிக்கலாம்?
- 10 client நிறுவனங்களை நிர்வகிக்கும் CA firm
- ஒவ்வொரு client-க்கும் ₹5–25 லட்சம் வரை அபராதம் தவிர்க்கலாம்
- CA-ன் நம்பகத்தன்மை உயரும், client retention அதிகரிக்கும்
- CA Firm plan: ₹48,000/ஆண்டு — unlimited clients

### Live Demo Data (இப்போது app-ல் இருக்கிறது)
- 4 active tenants, 19 vendors, 27 invoices
- Micro/Small vendor-களுக்கு ₹80.18 லட்சம் outstanding
- 20 at-risk invoices
- Projected disallowance + interest dashboard-ல் real time காட்டுகிறது

---

## 5. சந்தை வாய்ப்பு (Market Opportunity)

### யாருக்கு இது தேவை?
| பிரிவு | தோராய அளவு | ஏன் தேவை |
|---|---|---|
| Manufacturing SMEs (₹5–100cr turnover) | ~2 லட்சம் நிறுவனங்கள் | MSME vendor dependency அதிகம் |
| Trading நிறுவனங்கள் | ~5 லட்சம் நிறுவனங்கள் | பல சிறு suppliers |
| IT/ITES நிறுவனங்கள் | ~50,000 நிறுவனங்கள் | Subcontractors பலர் MSME-பதிவு |
| Pharma distributors | ~30,000 நிறுவனங்கள் | MSME-heavy supply chain |
| CA firms / Accounting firms | ~1.5 லட்சம் firms | அனைத்து clients-க்கும் manage செய்ய வேண்டும் |

### TAM / SAM / SOM
| நிலை | மதிப்பீடு | அடிப்படை |
|---|---|---|
| **TAM** (மொத்த சந்தை) | ₹500–1,000 கோடி/ஆண்டு | MSME vendor exposure உள்ள அனைத்து இந்திய நிறுவனங்கள் |
| **SAM** (சேவை செய்யக்கூடிய சந்தை) | ₹100–200 கோடி/ஆண்டு | Tech-forward businesses ₹5–500cr turnover |
| **SOM** (Year 1 இலக்கு) | ₹50–75 லட்சம்/ஆண்டு | 150–200 paying customers |

### Revenue இலக்கு
- 150 customers × சராசரி ₹2,500/மாதம் = **₹3.75 லட்சம்/மாதம் = ₹45 லட்சம்/ஆண்டு**
- 500 customers (Year 2) = **₹1.25 கோடி/ஆண்டு**

---

## 6. போட்டி நிலை (Competitive Landscape)

| போட்டியாளர் | என்ன செய்கிறார்கள் | நாம் நிரப்பும் இடைவெளி |
|---|---|---|
| **Tally ERP** | Full accounting software | 43B(h) tracking இல்லை, MSME deadline alert இல்லை |
| **Zoho Books** | Cloud accounting | 43B(h) module இல்லை, Udyam verification இல்லை |
| **ClearTax / Cleartds** | GST/TDS compliance | Payment deadline tracking-ல் focus இல்லை |
| **Excel + CA manually** | இப்போது பெரும்பாலோர் இதுவே செய்கிறார்கள் | Automation இல்லை, deadline miss ஆகும், error-prone |
| **நேரடி SaaS போட்டியாளர் இல்லை** | — | **சந்தை திறந்திருக்கிறது** |

**முக்கிய நன்மை:** நாம் Tally-ஐ replace செய்வதில்லை. அதன் மேல் உட்கார்கிறோம். Finance team Tally-ஐ தொடர்ந்து பயன்படுத்துவார்கள் — வாரம் ஒரு முறை export செய்வார்கள். எந்த workflow மாற்றமும் இல்லை.

---

## 7. நன்மை தீமைகள் — நேர்மையான மதிப்பீடு (Pros & Cons)

### நன்மைகள் (Pros)
- **தெளிவான ROI** — எண்களே விற்பனை செய்யும்; கணக்கு எளிதானது
- **சட்ட ஆதரவு** — சட்டம் இன்னும் கடுமையாகும், தளர்வடையாது
- **நேரடி போட்டியாளர் இல்லை** — இந்த niche-ஐ யாரும் இன்னும் own செய்யவில்லை
- **CA firm channel** — ஒரு CA firm sale = 10–50 நிறுவனங்களுக்கு access
- **Sticky product** — monthly close workflow-ல் ஒருமுறை சேர்ந்தால் விட்டுப் போவதில்லை
- **குறைந்த infrastructure cost** — SaaS margin 70%+
- **AI-assisted** — local LLM vendor classification, onboarding friction குறையும்
- **India-first** — Tally, Indian tax law, CA workflow-ஐ ஆழமாக புரிந்துகொண்டு கட்டியது

### குறைபாடுகள் / ஆபத்துகள் (Cons / Risks)
- **Niche சார்பு** — Micro/Small MSME vendor-களிடம் மட்டுமே வாங்குபவர்களுக்கு தேவை; Large/Medium vendor-களிடம் மட்டுமே வாங்குபவர்களுக்கு பொருந்தாது
- **Manual import** — Real-time Tally API இல்லை; வாரம் ஒரு முறை ledger export workaround
- **விலை உணர்திறன்** — Indian SME சந்தையில் பேரம் பேசுவார்கள்; ₹1,500/மாதம் சிலருக்கு அதிகமாகத் தெரியலாம்
- **சட்ட ஆபத்து** — அரசு 43B(h)-ஐ தளர்த்தினால் (சாத்தியம் குறைவு) urgency குறையும்
- **ERP vendor ஆபத்து** — Tally அல்லது Zoho இந்த feature-ஐ native-ஆக சேர்த்தால் நாம் feature ஆகிவிடுவோம்
- **விழிப்புணர்வு இடைவெளி** — பல CFO-க்கள் இன்னும் 43B(h)-ஐ முழுமையாக புரிந்துகொள்ளவில்லை; விற்பனையே education ஆகும்

---

## 8. இப்போதே ஏன்? (Why Now?)

1. **FY 2023-24 முதல் ஆண்டு** — நிறுவனங்கள் இப்போது தான் tax return-ல் penalty பார்க்கின்றன. வலி புதியது, உண்மையானது.
2. **FY 2024-25 filings நடக்கின்றன** — இப்போது இந்தியாவிலுள்ள ஒவ்வொரு CA-வும் client books-ல் 43B(h) exposure தேடுகிறார்.
3. **Udyam பதிவுகள் வளர்கின்றன** — 5 கோடி+ நிறுவனங்கள் Udyam portal-ல்; buyer நிறுவனங்கள் மாதந்தோறும் அதிக MSME vendor-களை சந்திக்கின்றன.
4. **WhatsApp-native India** — நம் alert system இந்தியர்கள் தொடர்பு கொள்ளும் விதத்திற்கு perfectly பொருந்துகிறது.
5. **போட்டியாளர்கள் இன்னும் நகரவில்லை** — சந்தை இப்போது திறந்திருக்கிறது.

---

## 9. Pitch Scripts (விளக்க வாசகங்கள்)

### 30 வினாடி Elevator Pitch
> "உங்கள் நிறுவனம் சிறு vendors-இடம் பொருட்கள் வாங்குகிறதா? அவர்கள் Udyam-பதிவு செய்தவர்களாக இருந்தால், 15 முதல் 45 நாட்களுக்குள் பணம் செலுத்த வேண்டும் — இது சட்டம். தவறினால், Income Tax department அந்த தொகையை உங்கள் taxable income-ல் சேர்க்கும். ₹50 லட்சம் liability என்றால் ₹15 லட்சம் கூடுதல் வரி. நம் software உங்கள் Tally data import செய்து, risk-ல் உள்ள ஒவ்வொரு invoice-ஐயும் கண்டுபிடித்து, deadline வருவதற்கு முன்பே alert அனுப்பும். மாதம் ₹1,500. பெரும்பாலான clients முதல் வாரத்திலேயே அதை recover செய்கிறார்கள்."

### 2 நிமிட Investor Pitch
> "Section 43B(h) என்பது 2023-ல் வந்த Income Tax Act திருத்தம். MSME vendor-களுக்கு 15 முதல் 45 நாட்களுக்குள் பணம் செலுத்தாவிட்டால் அந்த expense disallow ஆகும் — taxable income-ல் சேரும். 30% tax rate-ல், ₹1 கோடி delayed MSME payment என்பது ₹30 லட்சம் கூடுதல் வரி — ஆண்டுதோறும்.
>
> பிரச்சனை என்னவென்றால் யாரும் இதை track செய்வதில்லை. Finance team-கள் Tally பயன்படுத்துகிறார்கள். Tally-ல் 43B(h) module இல்லை. CA-கள் verbally சொல்கிறார்கள் — ஆனால் entire client portfolio-ஐ monitor செய்ய tool இல்லை.
>
> நாம் Tally-ன் மேல் உட்கார்ந்து வேலை செய்யும் SaaS platform கட்டியிருக்கிறோம். Finance team ledger export செய்கிறார்கள், நாம் import செய்கிறோம், Udyam verification மற்றும் AI மூலம் vendors-ஐ classify செய்கிறோம், exact deadline கணக்கிடுகிறோம், WhatsApp மற்றும் email மூலம் deadline வருவதற்கு முன்பே alert அனுப்புகிறோம்.
>
> 6.3 கோடி MSMEs. MSME suppliers உள்ள ஒவ்வொரு இந்திய நிறுவனமும் exposed. நேரடி competitor இல்லை. விலை ₹1,500 முதல் ₹4,000 வரை. 150 customers — ₹45 லட்சம் annual revenue. CA firms நம் multiplier — ஒரு CA firm sale = 10 முதல் 50 client நிறுவனங்கள்.
>
> Product கட்டியாச்சு. 393 tests pass ஆகின்றன. நான்கு tenants live-ல் இருக்கின்றன. Market-க்கு போக தயார்."

### CA Firm-க்கு Sales Pitch
> "CA ஆன நீங்கள் client-ன் 43B(h) exposure-ஐ year-end audit-ல் கண்டுபிடிக்கிறீர்கள் — அப்போது மிகவும் தாமதம். நாம் தரும் tool மூலம் 30 நாட்கள் முன்பே பிரச்சனையை கண்டுபிடிப்பீர்கள். அனைத்து clients-ஐயும் ஒரே dashboard-ல் நிர்வகியுங்கள். பிரச்சனைக்கு பின்னால் இல்லாமல் முன்னால் இருங்கள். மாதம் ₹4,000-ல் உங்கள் ஒவ்வொரு client-ஐயும் பாதுகாப்பீர்கள் — நீங்கள் லட்சங்களை மிச்சப்படுத்திய CA ஆவீர்கள்."

---

## 10. ஒரே பக்க சுருக்கம் (One-Page Summary Card)

| | |
|---|---|
| **Product** | MSME 43B(h) Payment Risk Tracker |
| **சிக்கல்** | MSME payment deadline தவறினால் → disallowance + ~20% interest |
| **தீர்வு** | Tally import → deadline tracking → real-time alerts → CA-ready reports |
| **சந்தை** | 2 லட்சம்+ target நிறுவனங்கள் + 1.5 லட்சம் CA firms |
| **Revenue model** | SaaS: ₹1,500 / ₹3,000 / ₹4,000 per month |
| **Year 1 இலக்கு** | 150 customers = ₹45 லட்சம் ARR |
| **நம் நன்மை** | சட்டமே நம் moat. யாரும் track செய்யாத compliance-ஐ நாம் track செய்கிறோம். |
| **தற்போதைய நிலை** | Product complete, tested, seeded, deployed-ready |
| **தேவை** | Go-to-market: CA firm partnerships + digital outreach |
