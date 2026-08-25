<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

final class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [

            [
                'name' => 'تجهیزات رایانه‌ای',
                'code' => 'COMPUTER',
                'sort_order' => 10,
            ],

            [
                'name' => 'تجهیزات اداری',
                'code' => 'OFFICE',
                'sort_order' => 20,
            ],

            [
                'name' => 'مبلمان و اثاثیه',
                'code' => 'FURNITURE',
                'sort_order' => 30,
            ],

            [
                'name' => 'تجهیزات شبکه',
                'code' => 'NETWORK',
                'sort_order' => 40,
            ],

            [
                'name' => 'ابزار و تجهیزات فنی',
                'code' => 'TECHNICAL',
                'sort_order' => 50,
            ],

            [
                'name' => 'وسایل نقلیه',
                'code' => 'VEHICLE',
                'sort_order' => 60,
            ],

            [
                'name' => 'سایر',
                'code' => 'OTHER',
                'sort_order' => 100,
            ],

        ];

        foreach ($categories as $category) {

            AssetCategory::updateOrCreate(

                [
                    'code' => $category['code'],
                ],

                [
                    'name' => $category['name'],
                    'description' => null,
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]

            );

        }
    }
}