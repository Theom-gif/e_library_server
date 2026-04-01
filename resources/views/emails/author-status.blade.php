<!DOCTYPE html>
<html>
<head>
    <title>Author Status</title>
</head>
<body>
    <h2>Hello {{ $user->firstname }} {{ $user->lastname }}</h2>

    <p>
        Your author account has been
        <strong>{{ $status }}</strong>
    </p>

    <p>
        {{ $messageText }}
    </p>

    <hr>

    <p>Thanks,<br>E Library Team</p>
</body>
</html>