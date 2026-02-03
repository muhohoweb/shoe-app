<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = MpesaTransaction::query()
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'status' => $request->status,
            ],
        ]);
    }
}