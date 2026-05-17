<?php

namespace App\Http\Controllers;

use App\Services\ReturnMessageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReturnMessageController extends Controller
{
    protected $returnMessageService;

    public function __construct(ReturnMessageService $returnMessageService)
    {
        $this->returnMessageService = $returnMessageService;
    }

    /**
     * Send a message on a return request.
     *
     * @param Request $request
     * @param int $return_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $return_id)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $message = $this->returnMessageService->addMessage($return_id, $request->message);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data'    => $message
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all messages for a return request.
     *
     * @param int $return_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($return_id)
    {
        try {
            $messages = $this->returnMessageService->getMessages($return_id);

            return response()->json([
                'success' => true,
                'data'    => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}