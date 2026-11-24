<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvidenceCandidate;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvidenceAssistantController extends Controller
{
    public function index(): View
    {
        return view('admin.evidence-assistant', [
            'candidates' => EvidenceCandidate::latest()->paginate(20),
        ]);
    }

    public function store(Request $request, GeminiService $gemini): RedirectResponse
    {
        $data = $request->validate([
            'source_url' => ['nullable', 'url', 'max:255'],
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $extracted = $gemini->enabled()
            ? $gemini->json('Extract one incident or closure candidate from public notice text. Return ONLY JSON: {"category":"other","approximate_place":"text|null","approximate_area":"text|null","facts":"short neutral extracted facts"}'."\nSource URL: ".($data['source_url'] ?? 'none')."\nText: ".$data['text'])
            : null;

        $category = in_array($extracted['category'] ?? null, ['harassment', 'theft', 'violence', 'dark_road', 'overcharging', 'positive', 'other'], true)
            ? $extracted['category']
            : 'other';

        EvidenceCandidate::create([
            'source_url' => $data['source_url'] ?? null,
            'category' => $category,
            'approximate_place' => isset($extracted['approximate_place']) ? mb_substr((string) $extracted['approximate_place'], 0, 255) : null,
            'approximate_area' => isset($extracted['approximate_area']) ? mb_substr((string) $extracted['approximate_area'], 0, 100) : null,
            'facts' => mb_substr((string) ($extracted['facts'] ?? 'AI extraction unavailable; human review required.'), 0, 5000),
            'status' => 'unverified',
        ]);

        return back()->with('status', __('Evidence candidate added to the unverified moderation queue.'));
    }

    public function approve(Request $request, EvidenceCandidate $candidate): RedirectResponse
    {
        $candidate->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', __('Candidate approved for review workflow.'));
    }

    public function reject(Request $request, EvidenceCandidate $candidate): RedirectResponse
    {
        $candidate->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', __('Candidate rejected.'));
    }
}
