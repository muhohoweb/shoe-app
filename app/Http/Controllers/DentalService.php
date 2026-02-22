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


    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name'    => 'required|string',
            'client_email'   => 'required|email',
            'service_name'   => 'required|string',
            'tooth_number'   => 'nullable|string',
            'shade'          => 'nullable|string',
            'estimated_days' => 'required|integer',
            'price'          => 'required|numeric',
            'notes'          => 'nullable|string',
        ]);

        // For now return the payload back as confirmation
        return response()->json([
            'message' => 'Order received successfully',
            'order'   => $validated,
        ], 201);
    }
}
