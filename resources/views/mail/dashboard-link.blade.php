<!DOCTYPE html>
<html lang="is">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stjórnaðu Timr á vefnum</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f5; margin: 0; padding: 24px; }
        .card { background: #fff; border-radius: 8px; max-width: 520px; margin: 0 auto; padding: 40px; }
        .logo { font-size: 22px; font-weight: 700; color: #0a7c68; margin-bottom: 32px; }
        h1 { font-size: 20px; color: #111; margin: 0 0 16px; }
        p { color: #444; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; background: #0a7c68; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin-top: 8px; }
        .url { color: #0a7c68; font-weight: 600; word-break: break-all; }
        .footer { margin-top: 32px; font-size: 13px; color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Timr</div>

        <h1>Hæ, {{ $user->name }}!</h1>

        <p>Timr-smáforritið er fyrir starfsfólk til að stimpla inn og út. Eigendur og stjórnendur stýra vinnustaðnum frá vefstjórnborðinu — setja upp staði, bjóða fólki, búa til vaktatöflur og samþykkja tíma.</p>

        <p>Smelltu á hnappinn hér að neðan til að opna vefstjórnborðið. Tengillinn rennur út eftir 30 mínútur.</p>

        <a href="{{ $loginUrl }}" class="btn">Opna stjórnborð</a>

        <p style="margin-top: 24px;">Eða farðu beint á: <a class="url" href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>

        <div class="footer">
            <p>Ef þú baðst ekki um þennan tölvupóst geturðu hunsað hann.</p>
        </div>
    </div>
</body>
</html>
