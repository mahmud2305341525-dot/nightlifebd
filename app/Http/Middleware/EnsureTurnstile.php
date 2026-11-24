<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EnsureTurnstile
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.turnstile.secret');
        if (! filled($secret)) {
            return $next($request);
        }
        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            return back()->withErrors(['captcha' => __('Complete the verification challenge.')])->withInput();
        }
        try {
            $response = Http::timeout(10)->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
            if (! $response->successful() || $response->json('success') !== true) {
                return back()->withErrors(['captcha' => __('Verification failed. Please try again.')])->withInput();
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['captcha' => __('Verification service unavailable.')])->withInput();
        }

        return $next($request);
    }
}
