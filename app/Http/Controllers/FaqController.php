<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\FaqModel;

class FaqController extends Controller
{
    public function index()
    {
        return response()->json(FaqModel::where('is_active', true)->get());
    }

    public function toggleBotActive($userId)
    {
        $user = \App\Models\UserModel::findOrFail($userId);
        $user->is_bot_active = !$user->is_bot_active;
        $user->save();

        return response()->json(['success' => true, 'is_bot_active' => $user->is_bot_active]);
    }
}
