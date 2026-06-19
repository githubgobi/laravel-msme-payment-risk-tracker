<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalculatorControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Calc Test Corp',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'subscription_ends_at'    => now()->addYear(),
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function index_renders_calculator_page_with_required_props(): void
    {
        $this->actingAs($this->owner)
            ->get('/calculator')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Calculator/Index')
                ->has('vendorCategories')
                ->has('defaultBankRate')
            );
    }

    #[Test]
    public function compute_returns_disallowance_for_overdue_micro_vendor_invoice(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-04-01',
                'amount'          => 200000,
                'agreement_exists' => false,
                'vendor_category' => 'micro',
                'bank_rate'       => 6.75,
                'paid_amount'     => 0,
                'as_of'           => '2026-06-20',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'overdue')
            ->assertJsonPath('is_subject_to_43bh', true)
            ->assertJsonPath('disallowance_amount', 200000)
            ->assertJsonStructure([
                'effective_deadline',
                'deadline_days',
                'financial_year',
                'status',
                'status_label',
                'days_to_deadline',
                'days_overdue',
                'is_subject_to_43bh',
                'disallowance_amount',
                'interest_amount',
                'total_exposure',
                'effective_tax_rate',
                'annual_interest_rate',
                'balance',
            ]);
    }

    #[Test]
    public function compute_returns_zero_disallowance_for_medium_vendor(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-04-01',
                'amount'          => 500000,
                'agreement_exists' => false,
                'vendor_category' => 'medium',
                'bank_rate'       => 6.75,
                'paid_amount'     => 0,
                'as_of'           => '2026-06-20',
            ])
            ->assertStatus(200)
            ->assertJsonPath('is_subject_to_43bh', false)
            ->assertJsonPath('disallowance_amount', 0)
            ->assertJsonPath('interest_amount', 0);
    }

    #[Test]
    public function compute_returns_paid_status_when_fully_paid(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-04-01',
                'amount'          => 100000,
                'agreement_exists' => false,
                'vendor_category' => 'micro',
                'bank_rate'       => 6.75,
                'paid_amount'     => 100000,
                'as_of'           => '2026-06-20',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('disallowance_amount', 0)
            ->assertJsonPath('interest_amount', 0);
    }

    #[Test]
    public function compute_returns_pending_status_before_deadline(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-06-18',  // 15-day deadline = 2026-07-03
                'amount'          => 100000,
                'agreement_exists' => false,
                'vendor_category' => 'micro',
                'bank_rate'       => 6.75,
                'paid_amount'     => 0,
                'as_of'           => '2026-06-19',  // 14 days before deadline
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('disallowance_amount', 0)
            ->assertJsonPath('interest_amount', 0);
    }

    #[Test]
    public function compute_uses_45_day_deadline_when_agreement_exists(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-06-01',
                'amount'          => 100000,
                'agreement_exists' => true,
                'vendor_category' => 'micro',
                'bank_rate'       => 6.75,
                'paid_amount'     => 0,
                'as_of'           => '2026-06-20',
            ])
            ->assertStatus(200)
            ->assertJsonPath('deadline_days', 45)
            ->assertJsonPath('effective_deadline', '2026-07-16')  // June 1 + 45 days
            ->assertJsonPath('status', 'pending'); // not yet overdue on June 20
    }

    #[Test]
    public function compute_returns_validation_errors_for_missing_fields(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'invoice_date',
                'amount',
                'agreement_exists',
                'vendor_category',
                'bank_rate',
            ]);
    }

    #[Test]
    public function compute_rejects_bank_rate_above_25(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/calculator/compute', [
                'invoice_date'    => '2026-06-01',
                'amount'          => 100000,
                'agreement_exists' => false,
                'vendor_category' => 'micro',
                'bank_rate'       => 30, // Above max
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_rate']);
    }
}
