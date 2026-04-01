<!DOCTYPE html>
<html>
<head>
    <title>Author Status</title>
</head>
<body style="margin:0; padding:24px; background-color:#f5f5f5; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:12px; padding:32px; border:1px solid #e5e7eb;">
        <h2 style="margin-top:0;">Hello {{ trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: 'Author' }}</h2>

        <p>
            Your author account has been <strong>{{ $status }}</strong>.
        </p>

        <p>
            {{ $messageText }}
        </p>

        @if (!empty($portalUrl))
            <p>
                Please <a href="{{ $portalUrl }}" style="color:#2563eb; font-weight:600;">click here</a> to go in as an author.
            </p>

            <p style="margin:28px 0 30px;">
                <a
                    href="{{ $portalUrl }}"
                    style="display:inline-block; background:linear-gradient(135deg, #1d4ed8 0%, #2563eb 45%, #3b82f6 100%); color:#ffffff; text-decoration:none; padding:15px 28px; border-radius:999px; font-weight:700; font-size:16px; letter-spacing:0.2px; box-shadow:0 10px 24px rgba(37, 99, 235, 0.28); border:1px solid #1d4ed8;"
                >
                    {{ $actionText ?: 'Go To Author Portal' }} &rarr;
                </a>
            </p>
        @endif

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="margin-bottom:0;">Thanks,<br>E Library Team</p>
    </div>
</body>
</html>
