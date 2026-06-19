<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterTenantRequest;
use App\Services\TenantRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(
        RegisterTenantRequest     $request,
        TenantRegistrationService $registrar,
    ): RedirectResponse {
        $user = $registrar->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard')->with(
            'success',
            "Welcome to MSME Tracker, {$user->name}! Your 14-day free trial has started."
        );
    }
}
