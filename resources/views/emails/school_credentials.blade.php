<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Your school account</title>
</head>
<body>
  <h2>Welcome to {{ $school->name }}</h2>

  <p>Hi,</p>

  <p>Your school account has been created on <strong>{{ config('app.name') }}</strong>.</p>

  <p><strong>Login details</strong></p>
  <ul>
    <li><strong>Username:</strong> {{ $username }}</li>
    <li><strong>Temporary password:</strong> {{ $password }}</li>
  </ul>

  <p>For security, you are required to change your password on first login. Click the link below to set a new password:</p>

  <p><a href="{{ $changePasswordUrl }}">{{ $changePasswordUrl }}</a></p>

  <p>Note: Your account is currently restricted to subscription management only. Full access will be granted after payment and approval by our admin team.</p>

  <p>Regards,<br/>{{ config('app.name') }} Team</p>
</body>
</html>
