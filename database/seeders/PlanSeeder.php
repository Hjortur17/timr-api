<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Tier definitions mirror the marketing site pricing
     * (timr-frontend/src/app/[locale]/page.tsx). Prices are in ISK; price_yearly
     * is the per-month price when billed annually.
     */
    public function run(): void
    {
        $plans = [
            [
                'key' => 'free',
                'name' => 'Frír',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 0,
            ],
            [
                'key' => 'nettur',
                'name' => 'Nettur',
                'price_monthly' => 2490,
                'price_yearly' => 2075,
                'sort_order' => 1,
            ],
            [
                'key' => 'thettur',
                'name' => 'Þéttur',
                'price_monthly' => 5490,
                'price_yearly' => 4575,
                'sort_order' => 2,
            ],
            [
                'key' => 'allur-pakkinn',
                'name' => 'Allur pakkinn',
                'price_monthly' => 10990,
                'price_yearly' => 9565,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['key' => $plan['key']], $plan);
        }
    }
}
