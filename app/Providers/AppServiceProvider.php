<?php

namespace App\Providers;

use App\Contracts\LlmClient;
use App\Services\Import\VendorMatcher;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as LaravelRoute;
use App\Services\Knowledge\CosineSimilarity;
use App\Services\Knowledge\DocumentChunker;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\HashEmbedder;
use App\Services\Knowledge\KnowledgeRepository;
use App\Services\Knowledge\RagContextBuilder;
use App\Services\Knowledge\VendorIngester;
use App\Services\Llm\VendorCategoryClassifier;
use App\Services\Llm\VendorFuzzyMatcher;
use App\Services\OllamaClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OllamaClient::class, fn () => new OllamaClient(
            endpoint: config('llm.endpoint'),
            model:    config('llm.model'),
            timeout:  config('llm.timeout'),
        ));

        // Bind the interface so controllers/services can resolve LlmClient directly
        $this->app->bind(LlmClient::class, OllamaClient::class);

        $this->app->singleton(VendorFuzzyMatcher::class, fn ($app) => new VendorFuzzyMatcher(
            client:              $app->make(OllamaClient::class),
            confidenceThreshold: config('llm.confidence_threshold'),
            maxCandidates:       config('llm.max_match_candidates'),
        ));

        $this->app->singleton(HashEmbedder::class, fn () => new HashEmbedder());

        $this->app->singleton(EmbeddingService::class, fn ($app) => new EmbeddingService(
            endpoint:  config('llm.endpoint'),
            chatModel: config('llm.model'),
            timeout:   min(10, config('llm.timeout')),
            hasher:    $app->make(HashEmbedder::class),
        ));

        $this->app->singleton(KnowledgeRepository::class, fn ($app) => new KnowledgeRepository(
            chunker:    new DocumentChunker(),
            embedder:   $app->make(EmbeddingService::class),
            similarity: new CosineSimilarity(),
        ));

        $this->app->singleton(RagContextBuilder::class, fn ($app) => new RagContextBuilder(
            repository: $app->make(KnowledgeRepository::class),
        ));

        $this->app->singleton(VendorIngester::class, fn ($app) => new VendorIngester(
            repository: $app->make(KnowledgeRepository::class),
        ));

        $this->app->singleton(VendorCategoryClassifier::class, fn ($app) => new VendorCategoryClassifier(
            client:              $app->make(OllamaClient::class),
            confidenceThreshold: config('llm.confidence_threshold'),
            ragContext:          config('llm.enabled') ? $app->make(RagContextBuilder::class) : null,
        ));

        // Override VendorMatcher binding to inject LLM matcher when enabled
        $this->app->singleton(VendorMatcher::class, fn ($app) => new VendorMatcher(
            fuzzyMatcher: config('llm.enabled') ? $app->make(VendorFuzzyMatcher::class) : null,
            llmEnabled:   (bool) config('llm.enabled'),
        ));
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureOpenApi();
    }

    private function configureOpenApi(): void
    {
        // Document JSON-returning endpoints: vendors, knowledge, invoices, calculator, reports, health
        $documented = [
            'vendors/ai-classify-batch',
            'vendors/{vendor}/ai-classify',
            'knowledge/stats',
            'knowledge/search',
            'knowledge/ingest/vendors',
            'knowledge/{id}',
            'invoices',
            'invoices/{invoice}',
            'invoices/{invoice}/payments',
            'invoices/{invoice}/payments/{payment}',
            'calculator/compute',
            'health',
            'ai/status',
        ];

        Scramble::routes(function (LaravelRoute $route) use ($documented): bool {
            foreach ($documented as $pattern) {
                if (fnmatch($pattern, $route->uri()) || $route->uri() === $pattern) {
                    return true;
                }
            }
            return false;
        });
    }

    private function configureRateLimiting(): void
    {
        // Login: 5 attempts per minute per IP — reduces brute-force credential attacks
        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        // Registration: 3 per minute per IP — prevents automated account creation
        RateLimiter::for('register', fn (Request $request) =>
            Limit::perMinute(3)->by($request->ip())
        );

        // Calculator API: 30 per minute per user — this endpoint is called on every keystroke
        RateLimiter::for('calculator', fn (Request $request) =>
            Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
        );

        // File import: 5 per minute per tenant — each import is expensive (queue + storage)
        RateLimiter::for('import', fn (Request $request) =>
            Limit::perMinute(5)->by($request->user()?->tenant_id ?: $request->ip())
        );

        // Udyam API: 10 per minute per user — Surepass charges per verification call
        RateLimiter::for('udyam', fn (Request $request) =>
            Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
        );

        // Razorpay webhooks: 120 per minute per IP — allow burst from Razorpay's servers
        RateLimiter::for('webhooks', fn (Request $request) =>
            Limit::perMinute(120)->by($request->ip())
        );
    }
}
