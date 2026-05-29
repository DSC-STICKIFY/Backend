<?php

namespace App\Http\Controllers;

use App\Models\CustomizationRequest;
use App\Models\Quotation;
use App\Models\OrdersModel;
use App\Models\OrderDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CustomizationRequestController extends Controller
{
    // ─── CUSTOMER: Submit a new customization request ─────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'     => 'nullable|integer',
            'product_name'   => 'nullable|string|max:255',
            'quantity'       => 'required|integer|min:1',
            'material_type'  => 'nullable|string|max:255',
            'size_requested' => 'nullable|string|max:255',
            'instructions'   => 'nullable|string',
            'reference_image'=> 'nullable|image|max:10240',
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('reference_image')) {
                $imagePath = $request->file('reference_image')->store('customization_refs', 'public');
            }

            $cr = CustomizationRequest::create([
                'customer_id'     => auth()->user()->user_id,
                'product_id'      => $request->input('product_id'),
                'product_name'    => $request->input('product_name'),
                'quantity'        => $request->input('quantity'),
                'material_type'   => $request->input('material_type'),
                'size_requested'  => $request->input('size_requested'),
                'instructions'    => $request->input('instructions'),
                'reference_image' => $imagePath,
                'status'          => 'pending_request',
                'validation_status'=> 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customization request submitted successfully.',
                'data'    => $cr->load(['customer', 'product']),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('CustomizationRequest store error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── CUSTOMER: List my customization requests ─────────────────────────────
    public function customerIndex(): JsonResponse
    {
        try {
            $requests = CustomizationRequest::with(['product', 'quotation', 'artist'])
                ->where('customer_id', auth()->user()->user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $requests]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── CUSTOMER: Show single request ────────────────────────────────────────
    public function customerShow(int $id): JsonResponse
    {
        try {
            $cr = CustomizationRequest::with(['product', 'quotation', 'artist', 'customer'])
                ->where('customer_id', auth()->user()->user_id)
                ->findOrFail($id);

            return response()->json(['data' => $cr]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── ADMIN: List all customization requests ──────────────────────────────
    public function adminIndex(): JsonResponse
    {
        try {
            $requests = CustomizationRequest::with(['customer', 'product', 'quotation', 'artist'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $requests]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 1: CS/Staff sends request to Staff Feasibility Check
    // Status: pending_request → pending_feasibility
    // ═══════════════════════════════════════════════════════════════════════════
    public function sendToFeasibility(int $id): JsonResponse
    {
        try {
            $cr = CustomizationRequest::findOrFail($id);
            $cr->update(['status' => 'pending_feasibility']);

            return response()->json([
                'success' => true,
                'message' => 'Sent to Staff for feasibility check.',
                'data' => $cr
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 2: Staff checks feasibility (materials + capacity)
    // validation_status: can_accommodate | partially_accommodate | cannot_accommodate
    // Status: pending_feasibility → rejected_by_staff OR partial_pending_cx OR ready_for_artist
    // ═══════════════════════════════════════════════════════════════════════════
    public function submitFeasibility(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'validation_status' => 'required|in:can_accommodate,partially_accommodate,cannot_accommodate',
            'validation_notes'  => 'nullable|string',
            'approved_quantity' => 'required_if:validation_status,partially_accommodate|nullable|integer|min:1',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            $status = 'ready_for_artist';
            if ($request->input('validation_status') === 'cannot_accommodate') {
                $status = 'rejected_by_staff';
            } elseif ($request->input('validation_status') === 'partially_accommodate') {
                $status = 'partial_pending_cx';
            }

            $cr->update([
                'validation_status' => $request->input('validation_status'),
                'validation_notes'  => $request->input('validation_notes'),
                'approved_quantity' => $request->input('approved_quantity'),
                'status'            => $status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feasibility review submitted.',
                'data'    => $cr->fresh()->load(['customer', 'product']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 3: Customer responds to partial accommodation
    // Status: partial_pending_cx → ready_for_artist (Accept) OR → rejected_by_staff (Decline)
    // ═══════════════════════════════════════════════════════════════════════════
    public function customerRespondPartial(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        try {
            $cr = CustomizationRequest::where('customer_id', auth()->user()->user_id)->findOrFail($id);

            if ($cr->status !== 'partial_pending_cx') {
                return response()->json(['success' => false, 'message' => 'Not awaiting partial accommodation response.'], 422);
            }

            $action = $request->input('action');
            $cr->update([
                'status' => $action === 'accept' ? 'ready_for_artist' : 'rejected_by_staff',
            ]);

            return response()->json([
                'success' => true,
                'message' => $action === 'accept' ? 'Partial accommodation accepted.' : 'Request cancelled.',
                'data' => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 4: CS assigns artist
    // Status: ready_for_artist → assigned_to_artist
    // ═══════════════════════════════════════════════════════════════════════════
    public function assignArtist(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'artist_id' => 'required|integer|exists:employees,employee_id',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            if ($cr->status !== 'ready_for_artist') {
                return response()->json(['success' => false, 'message' => 'Feasibility must be validated/accepted before assigning artist.'], 422);
            }

            $cr->update([
                'artist_id' => $request->input('artist_id'),
                'status'    => 'assigned_to_artist',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Artist assigned. Design and quotation discussion unlocked.',
                'data'    => $cr->fresh()->load(['customer', 'product', 'artist']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 5: Artist creates and submits quotation
    // Status: assigned_to_artist → quotation_sent
    // ═══════════════════════════════════════════════════════════════════════════
    public function artistSubmitQuotation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'material_cost'      => 'required|numeric|min:0',
            'printing_cost'      => 'required|numeric|min:0',
            'design_fee'         => 'required|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'additional_notes'   => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $cr = CustomizationRequest::findOrFail($id);

            if ($cr->status !== 'assigned_to_artist') {
                return response()->json(['success' => false, 'message' => 'Not in design discussion stage.'], 422);
            }

            $total = $request->input('material_cost')
                   + $request->input('printing_cost')
                   + $request->input('design_fee')
                   + ($request->input('additional_charges') ?? 0);

            Quotation::updateOrCreate(
                ['customization_request_id' => $id],
                [
                    'material_cost'      => $request->input('material_cost'),
                    'printing_cost'      => $request->input('printing_cost'),
                    'design_fee'         => $request->input('design_fee'),
                    'additional_charges' => $request->input('additional_charges') ?? 0,
                    'additional_notes'   => $request->input('additional_notes'),
                    'total'              => $total,
                ]
            );

            $cr->update([
                'quotation_total' => $total,
                'status'          => 'quotation_sent',
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Quotation submitted to customer.',
                'data'    => $cr->fresh()->load(['customer', 'product', 'artist', 'quotation']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 6: Customer approves quotation + decides on Revision period
    // Status: quotation_sent → revision_period OR → design_finalized
    // ═══════════════════════════════════════════════════════════════════════════
    public function approveQuotation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'needs_revision_period' => 'required|boolean',
            'revision_days'         => 'nullable|integer|min:1|max:5',
        ]);

        try {
            $cr = CustomizationRequest::where('customer_id', auth()->user()->user_id)->findOrFail($id);

            if ($cr->status !== 'quotation_sent') {
                return response()->json(['success' => false, 'message' => 'No active quotation to approve.'], 422);
            }

            $needsRev = $request->input('needs_revision_period');
            $days = $request->input('revision_days', 2);

            $cr->update([
                'needs_revision_period' => $needsRev,
                'revision_deadline'     => $needsRev ? now()->addDays($days) : null,
                'revision_count'        => 0,
                'status'                => $needsRev ? 'revision_period' : 'design_finalized',
            ]);

            return response()->json([
                'success' => true,
                'message' => $needsRev ? 'Quotation approved. Revision period started.' : 'Quotation approved. Design finalized.',
                'data'    => $cr->fresh()->load(['customer', 'product', 'quotation']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 6b: Customer declines quotation
    // Status: quotation_sent → cancelled
    // ═══════════════════════════════════════════════════════════════════════════
    public function declineQuotation(int $id): JsonResponse
    {
        try {
            $cr = CustomizationRequest::where('customer_id', auth()->user()->user_id)->findOrFail($id);

            if ($cr->status !== 'quotation_sent') {
                return response()->json(['success' => false, 'message' => 'No active quotation to decline.'], 422);
            }

            $cr->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Quotation declined. Request cancelled.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 7: Revision cycle (Customer requests revisions)
    // Status: revision_period → revision_requested
    // ═══════════════════════════════════════════════════════════════════════════
    public function customerRequestRevision(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'revision_notes' => 'required|string',
        ]);

        try {
            $cr = CustomizationRequest::where('customer_id', auth()->user()->user_id)->findOrFail($id);

            if ($cr->status !== 'revision_period') {
                return response()->json(['success' => false, 'message' => 'Not in active revision period.'], 422);
            }

            if ($cr->revision_deadline && now()->greaterThan($cr->revision_deadline)) {
                return response()->json(['success' => false, 'message' => 'Revision deadline has passed.'], 422);
            }

            $cr->update([
                'status'         => 'revision_requested',
                'design_status'  => 'revision_requested',
                'revision_count' => $cr->revision_count + 1,
                'instructions'   => $cr->instructions . "\n\n--- Revision #{$cr->revision_count} ---\n" . $request->input('revision_notes'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Revision feedback sent to artist.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 7b: Artist uploads updated mockup
    // Status: revision_requested → revision_period
    // ═══════════════════════════════════════════════════════════════════════════
    public function uploadMockup(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'mockup' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            if ($request->hasFile('mockup')) {
                if ($cr->mockup_image) {
                    Storage::disk('public')->delete($cr->mockup_image);
                }
                $path = $request->file('mockup')->store('customization_mockups', 'public');
                $cr->update([
                    'mockup_image'  => $path,
                    'design_status' => 'waiting_approval',
                    'status'        => 'revision_period',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mockup uploaded successfully.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 7c: Artist marks customization request in progress & sets expected schedule
    // ═══════════════════════════════════════════════════════════════════════════
    public function markInProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'expected_shipped_at'  => 'required|date|after_or_equal:now',
            'expected_delivery_at' => 'required|date|after:expected_shipped_at',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            $cr->update([
                'status'               => 'in_progress',
                'in_progress_at'       => now(),
                'expected_shipped_at'  => $request->input('expected_shipped_at'),
                'expected_delivery_at' => $request->input('expected_delivery_at'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customization request marked as In Progress with timeline.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 8: Artist finalizes design & schedules production date
    // Status: revision_period OR customer_approved → pending_design_approval
    // ═══════════════════════════════════════════════════════════════════════════
    public function finalizeDesign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'production_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            $cr->update([
                'status'          => 'pending_design_approval',
                'design_status'   => 'waiting_approval',
                'production_date' => $request->input('production_date'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Design finalized. Scheduled for production. Sent to Admin/Subadmin for review.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 9: Admin Design Review (Subadmin / Superadmin)
    // Action: approve OR reject
    // Status: pending_design_approval → design_approved OR → revision_period
    // ═══════════════════════════════════════════════════════════════════════════
    public function adminReviewDesign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'admin_design_notes' => 'nullable|string',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            $action = $request->input('action');
            if ($action === 'approve') {
                $cr->update([
                    'status'        => 'design_approved',
                    'design_status' => 'approved',
                    'admin_design_notes' => $request->input('admin_design_notes'),
                ]);
            } else {
                $cr->update([
                    'status'        => 'revision_period',
                    'design_status' => 'revision_requested',
                    'admin_design_notes' => $request->input('admin_design_notes'),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $action === 'approve' ? 'Design approved for production.' : 'Design rejected. Sent back to Artist.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 10: Customer Payment / Checkout -> converts to order
    // Status: design_approved → converted_to_order
    // ═══════════════════════════════════════════════════════════════════════════
    public function convertToOrder(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:COD,GCash,Pickup',
        ]);

        DB::beginTransaction();
        try {
            $cr = CustomizationRequest::with('quotation')->findOrFail($id);

            if ($cr->status !== 'design_approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Design must be approved by admin before checkout.',
                ], 422);
            }

            $total = $cr->quotation_total ?? $cr->quotation?->total ?? 0;

            $order = OrdersModel::create([
                'user_id'              => $cr->customer_id,
                'artist_id'            => $cr->artist_id,
                'total_price'          => $total,
                'order_date'           => now(),
                'payment_method'       => $request->input('payment_method'),
                'status'               => 'In Production',
                'payment_status'       => $request->input('payment_method') === 'GCash' ? 'Paid' : 'Pending',
                'custom_order'         => true,
                'order_type'           => 'Customized',
                'in_progress_at'       => $cr->in_progress_at,
                'expected_shipped_at'  => $cr->expected_shipped_at,
                'expected_delivery_at' => $cr->expected_delivery_at,
                'final_design_url'     => $cr->mockup_image,
                'layout_submitted_at'  => $cr->updated_at,
                'layout_approved_at'   => now(),
            ]);

            OrderDetails::create([
                'order_id'      => $order->order_id,
                'product_id'    => $cr->product_id,
                'quantity'      => $cr->quantity,
                'item_price'    => $total,
                'subtotal'      => $total,
                'status'        => 'In Production',
            ]);

            $cr->update([
                'status'   => 'converted_to_order',
                'order_id' => $order->order_id,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded. Production task active.',
                'data'    => [
                    'customization' => $cr->fresh(),
                    'order'         => $order,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 11: Staff Print Quality & QC Check
    // qc_status: passed | failed
    // ═══════════════════════════════════════════════════════════════════════════
    public function submitQC(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'qc_status' => 'required|in:passed,failed',
            'qc_notes'  => 'nullable|string',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);

            $cr->update([
                'qc_status' => $request->input('qc_status'),
                'qc_notes'  => $request->input('qc_notes'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quality Control result saved.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── ADMIN: Generic Status Update Fallback ────────────────────────────────
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        try {
            $cr = CustomizationRequest::findOrFail($id);
            $cr->update(['status' => $request->input('status')]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated.',
                'data'    => $cr->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
