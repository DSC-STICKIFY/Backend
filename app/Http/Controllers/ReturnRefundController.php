<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ReturnRefundService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReturnRefundController extends Controller
{
    protected ReturnRefundService $returnRefundService;

    public function __construct(ReturnRefundService $returnRefundService)
    {
        $this->returnRefundService = $returnRefundService;
    }

    /**
     * Store a new return/refund request (Customer Side)
     */
    public function store(Request $request)
    {
        try {
            $return = $this->returnRefundService->createReturn($request);

            return response()->json([
                'success' => true,
                'message' => 'Return request submitted successfully.',
                'data' => $return,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('ReturnRefundController::store Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get all return requests (Admin)
     */
    public function index()
    {
        try {
            $returns = $this->returnRefundService->getAllReturns();

            return response()->json([
                'success' => true,
                'data' => $returns,
            ]);
        } catch (\Exception $e) {
            \Log::error('ReturnRefundController::index Error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch return requests.',
            ], 500);
        }
    }

    /**
     * Get single return request
     */
    public function show($id)
    {
        try {
            $return = $this->returnRefundService->getReturn($id);

            return response()->json([
                'success' => true,
                'data' => $return,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Return request not found.',
            ], 404);
        }
    }

    /**
     * Update return status (Approve / Reject) - Admin
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected,refunded,completed',
                'refund_proof' => 'nullable|image|max:5120',
            ]);

            $return = $this->returnRefundService->updateReturnStatus(
                $id, 
                $request->status, 
                $request->file('refund_proof')
            );

            return response()->json([
                'success' => true,
                'message' => "Return request {$request->status} successfully.",
                'data' => $return,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('ReturnRefundController::updateStatus Error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update return status.',
            ], 500);
        }
    }

    /**
     * Authorize a sub-admin to approve/reject a return request
     */
    public function authorizeSubAdmin(Request $request, $id)
    {
        try {
            $return = $this->returnRefundService->authorizeSubAdmin($id);

            return response()->json([
                'success' => true,
                'message' => 'Sub-admin successfully authorized.',
                'data' => $return,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('ReturnRefundController::authorizeSubAdmin Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to authorize sub-admin.',
            ], 500);
        }
    }
}