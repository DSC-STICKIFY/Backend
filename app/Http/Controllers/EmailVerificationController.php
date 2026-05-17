<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    protected EmailVerificationService $service;

    public function __construct(EmailVerificationService $service)
    {
        $this->service = $service;
    }

    public function verify(Request $request, $id, $hash)
    {
        $result = $this->service->verify($request, $id, $hash);

        return response()->json($result, $result['status']);
    }

    public function resend(Request $request)
    {
        $result = $this->service->resend($request);

        return response()->json($result, $result['status']);
    }

    public function status(Request $request)
    {
        $result = $this->service->status($request);

        return response()->json($result, $result['status']);
    }
}