<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            Log::warning('SMTP Mail sending failed: '.$e->getMessage());

            $user = User::where('email', $request->input('email'))->first();
            if ($user && (app()->environment('local') || config('app.debug'))) {
                $token = Password::broker()->createToken($user);
                $resetUrl = route('password.reset', [
                    'token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ]);

                return back()
                    ->with('status', __('SMTP connection failed (:err). Since this is a local development environment, use the direct reset button below.', ['err' => 'Check .env MAIL_PASSWORD']))
                    ->with('demo_reset_link', $resetUrl);
            }

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Could not deliver reset email. Please verify SMTP credentials or check storage/logs/laravel.log.')]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
