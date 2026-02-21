<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Cinghy'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Base Styles -->
    <link rel="stylesheet" href="/css/app.css">
    <?php if (isset($extra_css)): foreach ($extra_css as $css): ?>
        <link rel="stylesheet" href="/css/<?php echo $css; ?>.css">
    <?php endforeach; endif; ?>
    <style>
        :root {
            --accent-color: #32e68f;
            --accent-contrast: #000;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: var(--surface-1);
        }
        .auth-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: var(--surface-2);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }
        .auth-logo {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--accent-color);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-primary);
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            background: var(--accent-color);
            color: var(--accent-contrast);
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        .error {
            background-color: #ff4d4d22;
            color: #ff4d4d;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid #ff4d4d55;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">Cinghy</div>
        <?php echo $content; ?>
    </div>
</body>
</html>
