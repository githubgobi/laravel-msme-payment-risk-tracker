<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\LlmClassifyController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UdyamVerificationController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect root to dashboard or login
Route::get('/', function () {
    return Auth::check() ? redirect('/dashboard') : redirect('/login');
});

// Razorpay webhook — public (no auth, no CSRF); HMAC-verified inside controller
Route::post('/webhooks/razorpay', [WebhookController::class, 'razorpay'])
    ->name('webhooks.razorpay')
    ->middleware('throttle:webhooks');

// Auth routes (Laravel built-in session auth)
// Public registration
Route::get('/register',  [RegisterController::class, 'show'])->name('register')->middleware('guest');
Route::post('/register', [RegisterController::class, 'store'])->middleware(['guest', 'throttle:register']);

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login')->middleware('guest');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        Auth::user()->update(['last_login_at' => now()]);
        return redirect()->intended('/dashboard');
    }

    return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
})->middleware(['guest', 'throttle:login']);

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout')->middleware('auth');

// Onboarding (auth but NO onboarding check — exempt by design)
Route::middleware('auth')->group(function () {
    Route::get('/onboarding',          [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

// Impersonation (super-admin only, leave route is accessible while impersonating)
Route::middleware('auth')->group(function () {
    Route::post('/admin/impersonate/{tenant}', [ImpersonateController::class, 'start'])->name('admin.impersonate');
    Route::get('/impersonate/leave',           [ImpersonateController::class, 'leave'])->name('admin.impersonate.leave');
});

// Authenticated + active-tenant routes
Route::middleware(['auth', 'tenant.user', 'tenant.active', 'onboarding'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Vendor routes (static paths must come before {vendor} wildcard)
    Route::post('/vendors/bulk-classify',       [VendorController::class, 'bulkClassify'])->name('vendors.bulk-classify');
    Route::get('/vendors/create',               [VendorController::class, 'create'])->name('vendors.create');
    Route::get('/vendors/ai-review',            [LlmClassifyController::class, 'review'])->name('vendors.ai-review');
    Route::post('/vendors/ai-classify-batch',   [LlmClassifyController::class, 'batch'])->name('vendors.ai-classify.batch');
    Route::post('/vendors',                     [VendorController::class, 'store'])->name('vendors.store');
    Route::get('/vendors',                      [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/{vendor}',             [VendorController::class, 'show'])->name('vendors.show');
    Route::put('/vendors/{vendor}',             [VendorController::class, 'update'])->name('vendors.update');
    Route::post('/vendors/{vendor}/ai-classify',       [LlmClassifyController::class, 'suggest'])->name('vendors.ai-classify.suggest');
    Route::post('/vendors/{vendor}/ai-classify/apply', [LlmClassifyController::class, 'apply'])->name('vendors.ai-classify.apply');

    // Udyam API verification
    Route::post('/udyam/verify',                [UdyamVerificationController::class, 'verify'])->name('udyam.verify')->middleware('throttle:udyam');
    // Invoice routes
    Route::get('/invoices',                              [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}',                    [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('/invoices/{invoice}',                    [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}',                 [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    // Payment routes (nested under invoice)
    Route::post('/invoices/{invoice}/payments',                    [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::delete('/invoices/{invoice}/payments/{payment}',        [PaymentController::class, 'destroy'])->name('invoices.payments.destroy');

    // Calculator
    Route::get('/calculator',          [CalculatorController::class, 'index'])->name('calculator.index');
    Route::post('/calculator/compute', [CalculatorController::class, 'compute'])->name('calculator.compute')->middleware('throttle:calculator');

    // Alert routes (settings must come before any future {alert} wildcard)
    Route::get('/alerts',             [AlertController::class, 'index'])->name('alerts.index');
    Route::put('/alerts/settings',    [AlertController::class, 'updateSettings'])->name('alerts.settings');

    // Import routes
    Route::get('/import',                       [ImportController::class, 'index'])->name('import.index');
    Route::post('/import',                      [ImportController::class, 'store'])->name('import.store')->middleware('throttle:import');
    Route::get('/import/{batch}',               [ImportController::class, 'show'])->name('import.show');
    Route::get('/import/sample/{type}',         [ImportController::class, 'downloadSample'])->name('import.sample');

    // Subscription / billing
    Route::get('/subscribe',                [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscribe/{plan}',        [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');

    // Settings routes
    Route::get('/settings',                [ProfileController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile',        [ProfileController::class, 'update'])->name('settings.profile');
    Route::post('/settings/team',          [TeamController::class, 'store'])->name('settings.team.store');
    Route::put('/settings/team/{user}',    [TeamController::class, 'update'])->name('settings.team.update');
    Route::delete('/settings/team/{user}', [TeamController::class, 'destroy'])->name('settings.team.destroy');

    // Annual 43B(h) reports
    Route::get('/reports',              [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{fy}/pdf',     [ReportController::class, 'pdf'])->name('reports.pdf')->where('fy', '[0-9]{4}');
    Route::get('/reports/{fy}/excel',   [ReportController::class, 'excel'])->name('reports.excel')->where('fy', '[0-9]{4}');

    // AI status endpoint
    Route::get('/ai/status', [LlmClassifyController::class, 'status'])->name('ai.status');
});
