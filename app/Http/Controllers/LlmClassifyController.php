<?php

namespace App\Http\Controllers;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Vendor;
use App\Prompts\VendorClassificationPrompt;
use App\Services\Llm\VendorCategoryClassifier;
use App\Services\VendorClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LlmClassifyController extends Controller
{
    public function __construct(
        private readonly VendorCategoryClassifier   $classifier,
        private readonly VendorClassificationService $classificationService,
    ) {}

    /**
     * GET /vendors/ai-review
     * Renders the batch AI review page listing all unclassified vendors.
     */
    public function review(Request $request): Response
    {
        abort_unless(config('llm.enabled'), 404);

        $vendors = Vendor::where('category', VendorCategory::Unclassified)
            ->orderBy('name')
            ->get()
            ->map(fn ($v) => [
                'id'    => $v->id,
                'name'  => $v->name,
                'gstin' => $v->gstin,
                'state' => $v->state,
            ]);

        return Inertia::render('Vendors/AiReview', [
            'vendors'    => $vendors,
            'llmModel'   => config('llm.model'),
            'threshold'  => config('llm.confidence_threshold'),
        ]);
    }

    /**
     * POST /vendors/{vendor}/ai-classify
     * Runs the LLM classifier for a single vendor and returns the suggestion.
     * Does NOT auto-apply — the frontend confirms before calling classify-apply.
     */
    public function suggest(Request $request, Vendor $vendor): JsonResponse
    {
        abort_unless(config('llm.enabled'), 404);

        $result = $this->classifier->classify(
            vendorName: $vendor->name,
            gstin:      $vendor->gstin,
            state:      $vendor->state,
            tenantId:   auth()->user()->tenant_id,
        );

        if ($result === null) {
            return response()->json([
                'error' => 'AI service unavailable. Ensure Ollama is running with model: ' . config('llm.model'),
            ], 503)->header('X-Prompt-Version', VendorClassificationPrompt::VERSION);
        }

        return response()->json([
            'vendor_id'      => $vendor->id,
            'vendor_name'    => $vendor->name,
            'category'       => $result->category->value,
            'category_label' => $result->category->label(),
            'confidence'     => $result->confidence,
            'reasoning'      => $result->reasoning,
            'auto_applied'   => false,
        ])->header('X-Prompt-Version', VendorClassificationPrompt::VERSION);
    }

    /**
     * POST /vendors/{vendor}/ai-classify/apply
     * Applies an LLM suggestion (after user confirms in the UI).
     * Stores confidence + reasoning on the vendor record for audit trail.
     */
    public function apply(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(config('llm.enabled'), 404);

        $data = $request->validate([
            'category'   => ['required', 'string', 'in:micro,small,medium,large'],
            'confidence' => ['required', 'numeric', 'min:0', 'max:1'],
            'reasoning'  => ['nullable', 'string', 'max:500'],
        ]);

        $category = VendorCategory::from($data['category']);

        $this->classificationService->classify(
            vendor:  $vendor,
            category: $category,
            source:  VendorVerificationSource::Llm,
        );

        $vendor->withoutGlobalScopes()->where('id', $vendor->id)->update([
            'llm_confidence' => $data['confidence'],
            'llm_reasoning'  => $data['reasoning'] ?? null,
        ]);

        return back()->with('success',
            "'{$vendor->name}' classified as {$category->label()} by AI (confidence: " .
            round($data['confidence'] * 100) . '%).'
        );
    }

    /**
     * POST /vendors/ai-classify-batch
     * Classifies all unclassified vendors in the tenant and auto-applies
     * those above the confidence threshold.  Returns a JSON summary.
     */
    public function batch(Request $request): JsonResponse
    {
        abort_unless(config('llm.enabled'), 404);

        $vendors = Vendor::where('category', VendorCategory::Unclassified)->get();

        $applied   = 0;
        $suggested = 0;
        $failed    = 0;
        $results   = [];

        $tenantId = auth()->user()->tenant_id;

        foreach ($vendors as $vendor) {
            $result = $this->classifier->classify(
                vendorName: $vendor->name,
                gstin:      $vendor->gstin,
                state:      $vendor->state,
                tenantId:   $tenantId,
            );

            if ($result === null) {
                $failed++;
                continue;
            }

            if ($result->autoApplied) {
                $this->classificationService->classify(
                    vendor:   $vendor,
                    category: $result->category,
                    source:   VendorVerificationSource::Llm,
                );

                $vendor->withoutGlobalScopes()->where('id', $vendor->id)->update([
                    'llm_confidence' => $result->confidence,
                    'llm_reasoning'  => $result->reasoning,
                ]);

                $applied++;
            } else {
                $suggested++;
            }

            $results[] = [
                'vendor_id'    => $vendor->id,
                'vendor_name'  => $vendor->name,
                'category'     => $result->category->value,
                'confidence'   => $result->confidence,
                'auto_applied' => $result->autoApplied,
                'reasoning'    => $result->reasoning,
            ];
        }

        return response()->json([
            'summary' => [
                'total'     => $vendors->count(),
                'applied'   => $applied,
                'suggested' => $suggested,
                'failed'    => $failed,
            ],
            'results' => $results,
        ])->header('X-Prompt-Version', VendorClassificationPrompt::VERSION);
    }

    /**
     * GET /ai/status
     * Health check endpoint for the admin UI — returns Ollama availability.
     */
    public function status(): JsonResponse
    {
        // Intentionally accessible even when LLM_ENABLED=false so admins can
        // verify connectivity before enabling.
        $client = app(\App\Services\OllamaClient::class);

        return response()->json([
            'enabled'   => (bool) config('llm.enabled'),
            'available' => $client->isAvailable(),
            'endpoint'  => config('llm.endpoint'),
            'model'     => config('llm.model'),
            'threshold' => config('llm.confidence_threshold'),
        ]);
    }
}
