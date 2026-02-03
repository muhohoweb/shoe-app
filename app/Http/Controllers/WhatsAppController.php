<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    //webhook
    public function webhook(Request $request){
        Log::info($request);
    }
}
