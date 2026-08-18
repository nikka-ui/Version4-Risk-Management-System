<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Password reset code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5; padding: 24px;">
  <p>Hello {{ $name }},</p>
  <p>Use this one-time code to reset your ACCC Risk Management System password:</p>
  <p style="font-size: 28px; font-weight: 700; letter-spacing: 0.24em; margin: 24px 0;">{{ $otp }}</p>
  <p>This code expires in {{ $minutes }} minutes. If you did not request a reset, you can ignore this email.</p>
  <p style="color: #64748b; font-size: 13px;">ACCC Risk Management System</p>
</body>
</html>
