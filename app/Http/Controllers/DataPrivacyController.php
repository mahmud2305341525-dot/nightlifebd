<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DataPrivacyController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user();

        $data = [
            'export_metadata' => [
                'application' => 'NightLifeBD',
                'exported_at' => now()->toIso8601String(),
                'format_version' => '1.0',
            ],
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
                'gender' => $user->gender,
                'locale' => $user->locale,
                'trust_score' => $user->trust_score,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'emergency_contacts' => $user->emergencyContacts()->get(['name', 'phone', 'relation', 'created_at'])->toArray(),
            'reports' => $user->reports()->with('venue:id,name')->get()->map(fn ($r) => [
                'id' => $r->id,
                'venue' => $r->venue?->name,
                'kind' => $r->kind,
                'body' => $r->body,
                'category' => $r->category,
                'severity' => $r->severity,
                'rating' => $r->rating,
                'status' => $r->status,
                'is_anonymous' => (bool) $r->is_anonymous,
                'incident_time' => $r->incident_time?->toIso8601String(),
                'created_at' => $r->created_at?->toIso8601String(),
            ])->toArray(),
            'trips' => $user->trips()->withCount('pings')->get()->map(fn ($t) => [
                'id' => $t->id,
                'kind' => $t->kind,
                'destination' => $t->destination,
                'vehicle_plate' => $t->vehicle_plate,
                'expires_at' => $t->expires_at?->toIso8601String(),
                'ended_at' => $t->ended_at?->toIso8601String(),
                'total_pings' => $t->pings_count,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->toArray(),
        ];

        AuditLog::record($user->id, 'user.data_exported');

        $filename = sprintf('nightlifebd-data-export-%s.json', now()->format('Y-m-d-His'));

        return Response::json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Type' => 'application/json',
        ]);
    }
}
