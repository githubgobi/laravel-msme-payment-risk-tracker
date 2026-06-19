<?php

namespace App\Enums;

enum TenantPlan: string
{
    case Starter = 'starter';
    case Growth  = 'growth';
    case CaFirm  = 'ca_firm';

    public function label(): string
    {
        return match($this) {
            self::Starter => 'Starter',
            self::Growth  => 'Growth',
            self::CaFirm  => 'CA Firm',
        };
    }

    public function monthlyPriceInr(): int
    {
        return match($this) {
            self::Starter => 1500,
            self::Growth  => 3000,
            self::CaFirm  => 4000,
        };
    }

    public function maxVendors(): int
    {
        return match($this) {
            self::Starter => 50,
            self::Growth  => 200,
            self::CaFirm  => PHP_INT_MAX,
        };
    }

    public function maxUsers(): int
    {
        return match($this) {
            self::Starter => 5,
            self::Growth  => 15,
            self::CaFirm  => PHP_INT_MAX,
        };
    }

    public function maxClientBusinesses(): int
    {
        return match($this) {
            self::Starter => 1,
            self::Growth  => 1,
            self::CaFirm  => 10,
        };
    }
}
