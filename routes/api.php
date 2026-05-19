<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\ProductsModelController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReturnMessageController;
use App\Http\Controllers\ReturnRefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePaymentController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserOrderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\CustomerInquiryController;
use App\Http\Controllers\InquiryPaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\ArtistOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcasting
Route::post('/broadcasting/auth', function (Request $request) {
    // Try to get user from any guard
    $user = auth('admin_api')->user() ?? auth('subadmin_api')->user() ?? auth('sanctum')->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $channelName = $request->channel_name;
    $channelUserId = str_replace('private-chat.', '', $channelName);

    // ✅ Support all model types
    $userId = $user->user_id ?? $user->admin_id ?? $user->sub_admin_id ?? null;
    
    $isAdmin = false;
    if ($user instanceof \App\Models\AdminModel || $user instanceof \App\Models\SubAdminModel) {
        $isAdmin = true;
    } elseif (!empty($user->is_admin) || !empty($user->admin_id) || !empty($user->sub_admin_id) || in_array($user->role ?? '', ['admin', 'subadmin'])) {
        $isAdmin = true;
    }

    if ((int) $channelUserId !== (int) $userId && !$isAdmin) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $socketId = $request->socket_id;
    $secret = config('broadcasting.connections.reverb.secret');
    $signature = hash_hmac('sha256', "{$socketId}:{$channelName}", $secret);

    return response()->json([
        'auth' => config('broadcasting.connections.reverb.key') . ':' . $signature,
    ]);
})->middleware('auth:sanctum');

// Registration and Login Routes
Route::post('/account_login', [AuthController::class, 'authLogin']);
Route::post('/account_registration', [AuthController::class, 'authRegister']);
Route::post('/auth/google', [GoogleController::class, 'handleGoogleLogin']);

// Shared Account Routes
Route::middleware('auth:artist_api,admin_api,subadmin_api,sanctum')->group(function () {
    Route::get('/get_user_info', [AuthController::class, 'getUser']);
    Route::post('/account_logout', [AuthController::class, 'AuthLogout']);
    Route::put('/update_profile', [AuthController::class, 'updateProfile']);
    Route::post('/change_password', [AuthController::class, 'updatePassword']);
    Route::delete('/delete_account', [AuthController::class, 'deleteAccount']);
});

// User-specific Orders Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user_orders', [UserOrderController::class, 'getUserOrders']);
    Route::get('/order/{orderId}', [UserOrderController::class, 'getOrderDetails']);
    Route::post('/orders/{id}/approve-design', [UserOrderController::class, 'approveDesign']);
    Route::post('/orders/{id}/request-change', [UserOrderController::class, 'requestChange']);


    // Verified only actions
    Route::middleware('verified')->group(function () {
        Route::post('/place_order', [UserOrderController::class, 'ProductOrder']);
        Route::get('/orders/{id}/track', [TrackingController::class, 'track']);
    });

    // Email Verification
    Route::get('/email/verification-status', [EmailVerificationController::class, 'status']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend']);
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');

// Admin Orders Management
Route::middleware('auth:artist_api,admin_api,subadmin_api,sanctum')->group(function () {
    Route::post('/confirm_payment/{orderId}', [AdminOrderController::class, 'confirmPayment']);
    Route::post('/accept_order/{id}', [AdminOrderController::class, 'acceptOrder']);
    Route::post('/ship_order/{id}', [AdminOrderController::class, 'shipOrder']);
    Route::get('/orders', [AdminOrderController::class, 'getOrderList']);
    Route::get('/recent_orders', [AdminOrderController::class, 'getRecentOrders']);
    Route::post('/cancel_order/{id}', [AdminOrderController::class, 'cancelOrder']);
    Route::post('/complete_order/{id}', [AdminOrderController::class, 'completeOrder']);
    Route::post('/orders/{id}/return-refund', [AdminOrderController::class, 'requestReturnRefund']);
    Route::post('/orders/{id}/approve-return', [AdminOrderController::class, 'approveReturn']);
    Route::post('/orders/{id}/reject-return', [AdminOrderController::class, 'rejectReturn']);
    Route::post('/out_for_delivery/{id}', [AdminOrderController::class, 'outForDelivery']);
    
    // Artist Workflow
    Route::post('/orders/{id}/assign-artist', [AdminOrderController::class, 'assignArtist']);
    Route::post('/orders/{id}/approve-shipment-request', [AdminOrderController::class, 'approveShipmentRequest']);
    Route::post('/orders/{id}/reject-shipment-request', [AdminOrderController::class, 'rejectShipmentRequest']);
    
    // Artist Actions
    Route::middleware('auth:artist_api,sanctum')->group(function () {
        Route::post('/artist/orders/{id}/mark-in-progress', [ArtistOrderController::class, 'markInProgress']);
        Route::post('/artist/orders/{id}/upload-design', [ArtistOrderController::class, 'uploadFinalDesign']);
        Route::post('/artist/orders/{id}/request-shipment', [ArtistOrderController::class, 'requestShipment']);
    });
});


// Admin Dashboard
Route::middleware('auth:sanctum,admin_api,subadmin_api')->group(function () {
    Route::get('/all_users', [AdminDashboardController::class, 'getUsers']);
    Route::get('/all_employees', [AdminDashboardController::class, 'getEmployees']);
    Route::get('/all_artists', [AdminDashboardController::class, 'getArtists']);
    Route::get('/all_sub_admins', [AdminDashboardController::class, 'getSubAdmins']);
    Route::post('/add_employee', [AdminDashboardController::class, 'createEmployee']);
    Route::delete('/delete_employee/{id}', [AdminDashboardController::class, 'deleteEmployee']);
    Route::delete('/delete_sub_admin/{id}', [AdminDashboardController::class, 'deleteSubAdmin']);
    Route::post('/toggle-bot/{userId}', [\App\Http\Controllers\FaqController::class, 'toggleBotActive']);
});

Route::get('/faqs', [App\Http\Controllers\FaqController::class, 'index']);

// Standardized public products route
Route::get('/all_products', [ProductsModelController::class, 'getAllProducts']);


// Chat / Messages - CUSTOMER routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/messages', [MessageController::class, 'getMessages']);
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::get('/messages/unread-count', [MessageController::class, 'getUnreadCount']);
});

// Chat / Messages - ADMIN routes  
Route::middleware('auth:admin_api,subadmin_api,sanctum')->group(function () {
    Route::get('/admin/conversations', [MessageController::class, 'getConversations']);
    Route::get('/admin/messages/{userId}', [MessageController::class, 'getAdminUserMessages']);
    Route::post('/admin/messages', [MessageController::class, 'sendMessage']);
});

// Products CRUD (Admin Only)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/add_product', [ProductsModelController::class, 'addProduct']);
    Route::patch('/update_product/{id}', [ProductsModelController::class, 'updateProduct']);
    Route::delete('/delete_product/{id}', [ProductsModelController::class, 'deleteProduct']);

    // Variations
    Route::post('/products/{product}/designs', [ProductsModelController::class, 'addDesign']);
    Route::delete('/products/designs/{id}', [ProductsModelController::class, 'removeDesign']);
    Route::post('/products/{product}/qualities', [ProductsModelController::class, 'addQuality']);
    Route::delete('/products/qualities/{id}', [ProductsModelController::class, 'removeQuality']);
    Route::post('/products/{product}/sizes', [ProductsModelController::class, 'addSize']);
    Route::delete('/products/sizes/{id}', [ProductsModelController::class, 'removeSize']);
});

// ServicePayment Routes
Route::get('/index_service_payment', [ServicePaymentController::class, 'index']);
Route::post('/store_service_payment', [ServicePaymentController::class, 'store']);
Route::get('/show_service_payment/{id}', [ServicePaymentController::class, 'show']);
Route::put('/update_service_payment/{id}', [ServicePaymentController::class, 'update']);
Route::delete('/delete_service_payment/{id}', [ServicePaymentController::class, 'destroy']);

// Service Routes
Route::get('/services_index', [ServiceController::class, 'index']);
Route::get('/services_show/{id}', [ServiceController::class, 'show']);
Route::post('/services_add', [ServiceController::class, 'store']);
Route::patch('/services_update/{id}', [ServiceController::class, 'update']);
Route::delete('/services_delete/{id}', [ServiceController::class, 'destroy']);

// Public routes (no auth required)
Route::get('promotions/active', [PromotionController::class, 'active']);
Route::get('promotions/product/{productId}', [PromotionController::class, 'productPromotions']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('promotions/{id}/notify', [PromotionController::class, 'notify']);
    Route::apiResource('promotions', PromotionController::class);
});

// Public route - anyone can see reviews
Route::get('/reviews', [ReviewController::class, 'index']);

// Protected routes (only for logged-in users)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::patch('/reviews/{review}/reply', [ReviewController::class, 'reply']);
    Route::patch('/reviews/{review}/toggle-status', [ReviewController::class, 'toggleStatus']);
});

Route::put('/update_employee/{id}', [UserManagementController::class, 'updateEmployee']);
Route::put('/update_sub_admin/{id}', [UserManagementController::class, 'updateSubAdmin']); // same method
Route::put('/update_customer/{id}', [UserManagementController::class, 'updateCustomer']);


// Return & Refund Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('verified')->group(function () {
        Route::post('/returns', [ReturnRefundController::class, 'store']);
        Route::post('/returns/{return_id}/messages', [ReturnMessageController::class, 'store']);
    });
    
    Route::get('/returns', [ReturnRefundController::class, 'index']);
    Route::get('/returns/{id}', [ReturnRefundController::class, 'show']);
    Route::patch('/returns/{id}/status', [ReturnRefundController::class, 'updateStatus']);
    Route::get('/returns/{return_id}/messages', [ReturnMessageController::class, 'index']);

    // Return Policies (Admin)
    Route::get('/return-policies', [\App\Http\Controllers\ReturnPolicyController::class, 'index']);
    Route::post('/return-policies', [\App\Http\Controllers\ReturnPolicyController::class, 'store']);
    Route::put('/return-policies/{id}', [\App\Http\Controllers\ReturnPolicyController::class, 'update']);
    Route::delete('/return-policies/{id}', [\App\Http\Controllers\ReturnPolicyController::class, 'destroy']);
    
    // Return Policy Eligibility (Customer)
    Route::get('/return-eligibility/{orderId}/{productId}', [\App\Http\Controllers\ReturnPolicyController::class, 'checkEligibility']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markOneAsRead']);
});


Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/pay-via-gcash', [PaymentController::class, 'payViaGcash']);
});

Route::post('/paymongo/webhook', [PayMongoWebhookController::class, 'handleWebhook']);

// Laravel routes
Route::get('/payment/success', function () {
    return redirect('http://localhost:5173/payment-success'); // hyphen, not slash
});

Route::get('/payment/failed', function () {
    return redirect('http://localhost:5173/payment-failed'); // hyphen, not slash
});

Route::get('/settings/refund-policy', [SettingController::class, 'refundPolicy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/settings',  [SettingController::class, 'index']);
    Route::post('/admin/settings', [SettingController::class, 'update']);

    // Inquiry Routes
    Route::middleware('verified')->group(function () {
        Route::post('/inquiries', [InquiryController::class, 'store']);
        Route::post('/customer/inquiries/{id}/accept', [CustomerInquiryController::class, 'acceptQuotation']);
        Route::post('/customer/inquiries/{id}/pay-gcash', [InquiryPaymentController::class, 'payViaGcash']);
        Route::post('/customer/inquiries/{id}/pay-onsite', [InquiryPaymentController::class, 'payOnsite']);
    });

    Route::get('/admin/inquiries', [InquiryController::class, 'index']);
    Route::patch('/admin/inquiries/{id}/status', [InquiryController::class, 'updateStatus']);
    
    // Customer side
    Route::get('/customer/inquiries', [CustomerInquiryController::class, 'index']);
    Route::get('/customer/inquiries/{id}', [CustomerInquiryController::class, 'show']);
    Route::post('/customer/inquiries/{id}/decline', [CustomerInquiryController::class, 'declineQuotation']);
    Route::post('/customer/inquiries/{id}/review', [CustomerInquiryController::class, 'submitReview']);
    Route::patch('/admin/inquiries/{id}/mark-paid', [InquiryPaymentController::class, 'markAsPaid']);
});