<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect root to dashboard or login
Route::get('/', function () {
    return Auth::check() ? redirect('/dashboard') : redirect('/login');
});

// Auth routes (Laravel built-in session auth)
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
})->middleware('guest');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout')->middleware('auth');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Placeholder routes for nav links — controllers added in later phases
    Route::get('/vendors',    fn () => Inertia::render('Vendors/Index'))->name('vendors.index');
    Route::get('/invoices',   fn () => Inertia::render('Invoices/Index'))->name('invoices.index');
    Route::get('/payments',   fn () => Inertia::render('Payments/Index'))->name('payments.index');
    Route::get('/alerts',     fn () => Inertia::render('Alerts/Index'))->name('alerts.index');
    Route::get('/calculator', fn () => Inertia::render('Calculator/Index'))->name('calculator.index');

    // Import routes
    Route::get('/import',                       [ImportController::class, 'index'])->name('import.index');
    Route::post('/import',                      [ImportController::class, 'store'])->name('import.store');
    Route::get('/import/{batch}',               [ImportController::class, 'show'])->name('import.show');
    Route::get('/import/sample/{type}',         [ImportController::class, 'downloadSample'])->name('import.sample');
});
