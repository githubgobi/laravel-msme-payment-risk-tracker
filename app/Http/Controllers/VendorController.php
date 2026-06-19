<?php

namespace App\Http\Controllers;

use App\Enums\VendorCategory;
use App\Http\Requests\BulkClassifyRequest;
use App\Http\Requests\CreateVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Vendor;
use App\Services\VendorClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorClassificationService $classifier,
    ) {}

    public function index(Request $request): Response
    {
        $search   = $request->get('search', '');
        $category = $request->get('category', '');

        $query = Vendor::withCount('purchaseInvoices')
            ->withSum('purchaseInvoices', 'disallowance_amount')
            ->withSum('purchaseInvoices', 'interest_amount')
            ->orderByRaw("CASE category WHEN 'unclassified' THEN 0 ELSE 1 END") // unclassified first
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        if ($category && VendorCategory::tryFrom($category)) {
            $query->where('category', $category);
        }

        $vendors = $query->paginate(25)->withQueryString();

        $summary = [
            'total'        => Vendor::count(),
            'unclassified' => Vendor::where('category', VendorCategory::Unclassified)->count(),
            'micro'        => Vendor::where('category', VendorCategory::Micro)->count(),
            'small'        => Vendor::where('category', VendorCategory::Small)->count(),
            'medium'       => Vendor::where('category', VendorCategory::Medium)->count(),
            'large'        => Vendor::where('category', VendorCategory::Large)->count(),
        ];

        return Inertia::render('Vendors/Index', [
            'vendors'    => $vendors->through(fn ($v) => $this->formatVendorRow($v)),
            'summary'    => $summary,
            'filters'    => ['search' => $search, 'category' => $category],
            'categories' => collect(VendorCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vendors/Create', [
            'categories' => collect(VendorCategory::cases())
                ->filter(fn ($c) => $c !== VendorCategory::Unclassified)
                ->values()
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
        ]);
    }

    public function store(CreateVendorRequest $request): RedirectResponse
    {
        $data     = $request->validated();
        $category = VendorCategory::from($data['category']);

        $vendor = Vendor::create([
            'tenant_id'     => $request->user()->tenant_id,
            'name'          => $data['name'],
            'category'      => $category,
            'gstin'         => $data['gstin'] ?? null,
            'pan'           => $data['pan'] ?? null,
            'udyam_number'  => $data['udyam_number'] ?? null,
            'contact_person' => $data['contact_name'] ?? null,
            'email'         => $data['contact_email'] ?? null,
            'phone'         => $data['contact_phone'] ?? null,
            'address'       => $data['address'] ?? null,
            'city'          => $data['city'] ?? null,
            'state'         => $data['state'] ?? null,
            'is_active'     => true,
        ]);

        return redirect()->route('vendors.show', $vendor)
            ->with('success', "Vendor '{$vendor->name}' created successfully.");
    }

    public function show(Vendor $vendor): Response
    {
        $invoices = $vendor->purchaseInvoices()
            ->latest('invoice_date')
            ->limit(10)
            ->get()
            ->map(fn ($inv) => [
                'id'                  => $inv->id,
                'invoice_number'      => $inv->invoice_number,
                'invoice_date'        => $inv->invoice_date->format('d M Y'),
                'amount'              => (float) $inv->amount,
                'paid_amount'         => (float) $inv->paid_amount,
                'balance'             => (float) $inv->amount - (float) $inv->paid_amount,
                'effective_deadline'  => $inv->effective_deadline->format('d M Y'),
                'disallowance_amount' => (float) $inv->disallowance_amount,
                'interest_amount'     => (float) $inv->interest_amount,
                'status'              => $inv->status->value,
                'status_label'        => $inv->status->label(),
            ]);

        $stats = [
            'total_invoices'      => $vendor->purchaseInvoices()->count(),
            'at_risk_invoices'    => $vendor->purchaseInvoices()->atRisk()->count(),
            'total_disallowance'  => (float) $vendor->purchaseInvoices()->sum('disallowance_amount'),
            'total_interest'      => (float) $vendor->purchaseInvoices()->sum('interest_amount'),
        ];

        return Inertia::render('Vendors/Show', [
            'vendor'     => [
                'id'                  => $vendor->id,
                'name'                => $vendor->name,
                'gstin'               => $vendor->gstin,
                'pan'                 => $vendor->pan,
                'udyam_number'        => $vendor->udyam_number,
                'udyam_verified_at'   => $vendor->udyam_verified_at?->format('d M Y'),
                'category'            => $vendor->category->value,
                'category_label'      => $vendor->category->label(),
                'verification_source' => $vendor->verification_source?->value,
                'state'               => $vendor->state,
                'city'                => $vendor->city,
                'address'             => $vendor->address,
                'contact_person'      => $vendor->contact_person,
                'phone'               => $vendor->phone,
                'email'               => $vendor->email,
                'notes'               => $vendor->notes,
                'is_active'           => $vendor->is_active,
            ],
            'stats'      => $stats,
            'invoices'   => $invoices,
            'categories' => collect(VendorCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): HttpResponse
    {
        $data     = $request->validated();
        $category = VendorCategory::from($data['category']);

        $this->classifier->classify(
            vendor:      $vendor,
            category:    $category,
            udyamNumber: $data['udyam_number'] ?? null,
        );

        // Update remaining fields (non-classification fields)
        $vendor->withoutGlobalScopes()->where('id', $vendor->id)->update(array_filter([
            'name'           => $data['name'],
            'gstin'          => $data['gstin'] ?? null,
            'pan'            => $data['pan'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'email'          => $data['email'] ?? null,
            'state'          => $data['state'] ?? null,
            'city'           => $data['city'] ?? null,
            'address'        => $data['address'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'is_active'      => $data['is_active'] ?? true,
        ], fn ($v) => $v !== null));

        return back()->with('success', "Vendor '{$vendor->name}' updated. Risk scores will be refreshed in the background.");
    }

    public function bulkClassify(BulkClassifyRequest $request): HttpResponse
    {
        $vendorIds = $request->validated()['vendor_ids'];
        $category  = VendorCategory::from($request->validated()['category']);

        $vendors = Vendor::whereIn('id', $vendorIds)->get();
        $changed = $this->classifier->bulkClassify($vendors, $category);

        $label = $category->label();

        return back()->with('success',
            "{$changed} vendor(s) reclassified as {$label}. Risk scores refreshing in background."
        );
    }

    private function formatVendorRow(Vendor $vendor): array
    {
        $disallowance = (float) ($vendor->purchase_invoices_sum_disallowance_amount ?? 0);
        $interest     = (float) ($vendor->purchase_invoices_sum_interest_amount ?? 0);

        return [
            'id'                  => $vendor->id,
            'name'                => $vendor->name,
            'gstin'               => $vendor->gstin,
            'udyam_number'        => $vendor->udyam_number,
            'udyam_verified_at'   => $vendor->udyam_verified_at?->format('d M Y'),
            'category'            => $vendor->category->value,
            'category_label'      => $vendor->category->label(),
            'verification_source' => $vendor->verification_source?->value,
            'is_active'           => $vendor->is_active,
            'invoice_count'       => $vendor->purchase_invoices_count ?? 0,
            'total_exposure'      => round($disallowance + $interest, 2),
        ];
    }
}
