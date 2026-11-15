<?php

namespace App\Http\Controllers;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function index(Request $request)
    {
        return view('contacts.index', ['contacts' => $request->user()->emergencyContacts]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'relation' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'telegram_chat_id' => ['nullable', 'regex:/^-?\d{5,20}$/'],
        ], [
            'phone.regex' => 'Enter a valid 11-digit Bangladesh mobile number.',
            'telegram_chat_id.regex' => 'Telegram chat ID must be a number.',
        ]);

        if ($request->user()->emergencyContacts()->count() >= 5) {
            return back()->withInput()->withErrors(['phone' => 'Maximum five emergency contacts allowed.']);
        }
        $request->user()->emergencyContacts()->create($data);

        return back()->with('status', 'Contact saved.');
    }

    public function destroy(Request $request, EmergencyContact $contact)
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }
}
