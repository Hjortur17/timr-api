<!DOCTYPE html>
<html lang="is">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Þú hefur verið boðið á Timr</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 520px; margin: 0 auto; padding: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #0a7c68; margin-bottom: 32px; }
        h1 { font-size: 20px; color: #111; margin: 0 0 16px; }
        p { color: #444; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; background: #0a7c68; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin-top: 8px; }
        .footer { margin-top: 32px; font-size: 13px; color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Timr</div>

        <h1>Hæ, {{ $employee->name }}!</h1>

        <p>Þú hefur verið bætt við sem starfsmaður á Timr. Smelltu á hnappinn hér að neðan til að búa til aðgang þinn.</p>

        <a href="{{ config('app.frontend_url') }}/register?token={{ $employee->invite_token }}&email={{ urlencode($employee->email) }}" class="btn">
            Búa til aðgang
        </a>

        <div class="footer">
            <p>Ef þú áttir ekki von á þessum tölvupósti geturðu hunsað hann.</p>
        </div>
    </div>
</body>
</html>
