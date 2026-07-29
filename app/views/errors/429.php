<?php
/**
 * 429 Too Many Requests error page.
 * Displayed when rate limiting threshold is exceeded.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>429 — Too Many Requests</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #ffffff;
            color: #2f3437;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            text-align: center;
        }
        h1 {
            font-size: 48px;
            font-weight: 300;
            margin: 0 0 20px;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            color: #787774;
            margin: 0 0 30px;
        }
        .actions {
            margin-top: 40px;
        }
        a {
            display: inline-block;
            padding: 12px 24px;
            background: #111111;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin: 0 10px;
            transition: background 200ms ease-out;
        }
        a:hover {
            background: #333333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>429</h1>
        <p>Too many requests. Please wait a moment before trying again.</p>
        <div class="actions">
            <a href="/">Back to gallery</a>
        </div>
    </div>
</body>
</html>
