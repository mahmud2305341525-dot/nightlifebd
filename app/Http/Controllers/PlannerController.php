<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\NightPlannerService;
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function index()
    {
        return view('planner.index', ['areas' => Venue::approved()->distinct()->orderBy('area')->pluck('area'), 'result' => null]);
    }

    public function generate(Request $request, NightPlannerService $planner)
    {
        $d = $request->validate([
            'text' => ['nullable', 'string', 'max:500'],
            'budget' => ['nullable', 'integer', 'min:100', 'max:1000000'],
            'group' => ['nullable', 'integer', 'between:1,20'],
            'area' => ['nullable', 'string', 'max:60'],
            'hour' => ['nullable', 'integer', 'between:0,23'],
        ]);
        $result = $planner->plan([
            'text' => $d['text'] ?? '',
            'budget' => $d['budget'] ?? null,
            'group' => $d['group'] ?? null,
            'area' => $d['area'] ?? null,
            'hour' => isset($d['hour']) ? (int) $d['hour'] : null,
        ], (bool) $request->user()?->is_adult_confirmed);

        return view('planner.index', [
            'areas' => Venue::approved()->distinct()->orderBy('area')->pluck('area'),
            'result' => $result,
        ]);
    }
}
