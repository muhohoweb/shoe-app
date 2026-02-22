<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DentalService extends Controller
{
    public function index()
    {
        $services = [
            [
                'category' => 'Crowns',
                'services' => [
                    ['name' => 'Zirconia Crown', 'price' => 150.00, 'estimated_days' => 3],
                    ['name' => 'PFM Crown', 'price' => 120.00, 'estimated_days' => 5],
                    ['name' => 'Full Cast Metal Crown', 'price' => 80.00, 'estimated_days' => 3],
                ]
            ],
            [
                'category' => 'Bridges',
                'services' => [
                    ['name' => 'Zirconia Bridge (3 unit)', 'price' => 400.00, 'estimated_days' => 5],
                    ['name' => 'PFM Bridge (3 unit)', 'price' => 300.00, 'estimated_days' => 7],
                ]
            ],
            [
                'category' => 'Dentures',
                'services' => [
                    ['name' => 'Full Denture', 'price' => 250.00, 'estimated_days' => 10],
                    ['name' => 'Partial Denture', 'price' => 180.00, 'estimated_days' => 7],
                ]
            ],
            [
                'category' => 'Veneers',
                'services' => [
                    ['name' => 'Porcelain Veneer', 'price' => 100.00, 'estimated_days' => 5],
                    ['name' => 'Composite Veneer', 'price' => 60.00, 'estimated_days' => 3],
                ]
            ],
        ];

        return response()->json($services);
    }
}
