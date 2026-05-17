<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\InquiryStatusUpdated;

class InquiryController extends Controller
{
    /**
     * Submit a new inquiry.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_type' => 'required|string|in:car_wrap,car_decal,motor_service',
            'customer_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'message' => 'nullable|string',
            
            // Dynamic validation
            'car_type' => 'required_if:service_type,car_wrap|string|nullable',
            'wrap_type' => 'required_if:service_type,car_wrap|string|nullable',
            'decal_type' => 'required_if:service_type,car_decal|string|nullable',
            'placement' => 'required_if:service_type,car_decal|string|nullable',
            'size' => 'required_if:service_type,car_decal|string|nullable',
            
            // Motor fields
            'motor_model' => 'required_if:service_type,motor_service|string|nullable',
            'finish_type' => 'required_if:service_type,motor_service|string|nullable',
            'color_style' => 'string|nullable',
            'schedule_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        
        // Attach user_id if logged in
        if (Auth::check()) {
            $data['user_id'] = Auth::id();
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('inquiries', 'public');
            $data['image'] = $path;
        }

        $inquiry = Inquiry::create($data);

        return response()->json([
            'message' => 'Inquiry submitted successfully',
            'data' => $inquiry
        ], 201);
    }

    /**
     * List all inquiries for admin.
     */
    public function index()
    {
        $inquiries = Inquiry::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $inquiries]);
    }

    /**
     * Update the status of an inquiry.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,reviewed,quoted,approved,scheduled,in_progress,completed,rejected',
            'admin_message' => 'nullable|string',
            'quotation_amount' => 'nullable|numeric|min:0',
            'downpayment_amount' => 'nullable|numeric|min:0',
            'schedule_date' => 'nullable',
            'payment_status' => 'nullable|string',
            'rejection_reason' => 'nullable|string|max:1000'
        ]);

        $inquiry = Inquiry::findOrFail($id);
        $inquiry->status = $request->status;
        
        if ($request->has('admin_message')) $inquiry->admin_message = $request->admin_message;
        if ($request->has('quotation_amount')) $inquiry->quotation_amount = $request->quotation_amount;
        if ($request->has('downpayment_amount')) $inquiry->downpayment_amount = $request->downpayment_amount;
        if ($request->has('schedule_date')) $inquiry->schedule_date = $request->schedule_date;
        if ($request->has('payment_status')) $inquiry->payment_status = $request->payment_status;
        if ($request->has('rejection_reason')) $inquiry->rejection_reason = $request->rejection_reason;
        
        $inquiry->save();

        // Notify the customer if linked to a user
        if ($inquiry->user) {
            $inquiry->user->notify(new InquiryStatusUpdated($inquiry));
        }

        return response()->json([
            'message' => 'Inquiry status updated successfully',
            'data' => $inquiry
        ]);
    }
}
