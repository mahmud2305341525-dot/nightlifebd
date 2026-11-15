<?php

namespace App\Http\Controllers;

use App\Models\DarkSpot;
use Illuminate\Http\Request;

class DarkSpotController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:20,27'],
            'lng' => ['required', 'numeric', 'between:88,93'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        DarkSpot::create(['user_id' => $request->user()->id, 'lat' => $data['lat'], 'lng' => $data['lng'], 'note' => isset($data['note']) ? strip_tags($data['note']) : null]);

        return response()->json(['ok' => true]);
    }
}
