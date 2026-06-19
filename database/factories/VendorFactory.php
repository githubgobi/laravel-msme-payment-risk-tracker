<?php

namespace Database\Factories;

use App\Enums\VendorCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'tenant_id'  => Tenant::factory(),
            'name'       => fake()->company(),
            'gstin'      => null,
            'pan'        => null,
            'udyam_number' => null,
            'category'   => VendorCategory::Unclassified->value,
            'is_active'  => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function micro(): static
    {
        return $this->state(['category' => VendorCategory::Micro->value]);
    }

    public function small(): static
    {
        return $this->state(['category' => VendorCategory::Small->value]);
    }

    public function medium(): static
    {
        return $this->state(['category' => VendorCategory::Medium->value]);
    }

    public function large(): static
    {
        return $this->state(['category' => VendorCategory::Large->value]);
    }

    public function unclassified(): static
    {
        return $this->state(['category' => VendorCategory::Unclassified->value]);
    }

    /** Attach to an existing tenant rather than creating a new one. */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function withGstin(): static
    {
        $state = rand(11, 99);
        $pan   = strtoupper(fake()->lexify('?????') . fake()->numerify('####') . fake()->lexify('?'));
        $gstin = "{$state}{$pan}1Z5";

        return $this->state(['gstin' => $gstin]);
    }

    public function withUdyam(): static
    {
        $state = strtoupper(fake()->lexify('??'));
        $dist  = str_pad(rand(1, 35), 2, '0', STR_PAD_LEFT);
        $num   = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);

        return $this->state([
            'udyam_number' => "UDYAM-{$state}-{$dist}-{$num}",
        ]);
    }
}
