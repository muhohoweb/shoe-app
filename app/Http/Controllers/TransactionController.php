<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(): Response
    {
        $transactions = MpesaTransaction::latest()->paginate(15);

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
        ]);
    }
}