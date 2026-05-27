<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::latest()->paginate(15);
        return response()->json($promotions);
    }

    public function store(StorePromotionRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('banner_image')) {
            $path = $request->file('banner_image')
                ->store('promotions/banners', 'public');
            $data['banner_image'] = $path;
        }
        $data['created_by'] = $request->user()->user_id;
        $promotion = Promotion::create($data);
        return response()->json($promotion, 201);
    }

    public function show(Promotion $promotion)
    {
        return response()->json($promotion);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion)
    {
        $data = $request->validated();
        if ($request->hasFile('banner_image')) {
            if ($promotion->banner_image) {
                Storage::disk('public')->delete($promotion->banner_image);
            }
            $data['banner_image'] = $request->file('banner_image')
                ->store('promotions/banners', 'public');
        }
        $promotion->update($data);
        return response()->json($promotion);
    }

    public function destroy(Promotion $promotion)
    {
        if ($promotion->banner_image) {
            Storage::disk('public')->delete($promotion->banner_image);
        }
        $promotion->delete();
        return response()->json(null, 204);
    }
}
