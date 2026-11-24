<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Protected trip</title></head>
<body style="margin:0;font-family:system-ui;display:grid;place-items:center;min-height:100vh;background:#0f172a;color:#fff">
    <form method="POST" action="{{ route('track.pin', $trip->token) }}" style="background:#1e293b;padding:24px;border-radius:12px;width:min(320px,calc(100%-48px))">
        @csrf
        <h1 style="font-size:18px">Enter trip PIN</h1>
        <input type="password" name="pin" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required style="width:100%;padding:10px;border-radius:6px;margin-bottom:10px">
        @error('pin')<p style="color:#fca5a5">{{ $message }}</p>@enderror
        <button style="width:100%;padding:10px;border-radius:6px;background:#4f46e5;color:white">View trip</button>
    </form>
</body>
</html>
