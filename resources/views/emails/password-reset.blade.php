<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            background: #f4f4f4;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>School Management System</h2>
        </div>
        <div class="content">
            <h3>Hello {{ $user->name }},</h3>
            <p>You recently requested to reset your password for your School Management System account.</p>
            <p>Click the button below to reset it:</p>
            <p style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </p>
            <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
            <p>This password reset link will expire in 24 hours.</p>
            <hr>
            <p style="font-size: 12px; color: #666;">
                If the button doesn't work, copy and paste this link into your browser:<br>
                {{ $resetUrl }}
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} School Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>