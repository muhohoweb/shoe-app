<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('transactions/Index', [
            'transactions' => MpesaTransaction::latest()->get(),
        ]);
    }
}