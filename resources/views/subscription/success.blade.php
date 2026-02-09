<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <script>
        setTimeout(function() {
            window.close(); // Close the popup window
            if (window.opener) {
                window.opener.location.reload(); // Refresh parent window
            }
        }, 3000);
    </script>
</head>
<body>
    <div style="text-align: center; padding: 50px;">
        <h1 style="color: green;">✅ Payment Successful!</h1>
        <p>Your subscription has been activated. This window will close automatically.</p>
    </div>
</body>
</html>