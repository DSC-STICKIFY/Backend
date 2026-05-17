<?php

use GlennRaya\Xendivel\Xendivel;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// TESTING OF E-WALLET PAYMENT
// Route::post('/pay-via-ewallet', function (Request $request) {
//     $response = Xendivel::payWithEwallet($request)
//         ->getResponse();

//     return $response;
// });
