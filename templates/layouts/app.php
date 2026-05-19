<?php
$title = $title ?? 'CaddyPanel';
$theme = $theme ?? 'dark';
$navigation = $navigation ?? [];
$user = $user ?? null;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root,
        [data-theme="dark"] {
            --bg: #0f1115;
            --panel: #171a21;
            --panel-2: #1f2430;
            --text: #f2f4f8;
            --muted: #9aa4b2;
            --border: #2a2f3a;
            --accent: #4f8cff;
            --danger: #ff5c5c;
            --success: #4cc38a;
        }

        [data-theme="light"] {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --panel-2: #eef2f7;
            --text: #171a21;
            --muted: #667085;
            --border: #d8dee9;
            --accent: #2563eb;
            --danger: #dc2626;
            --success: #16a34a;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        a { color: inherit; }
        .shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px;
            background: var(--panel);
            border-right: 1px solid var(--border);
            padding: 20px;
        }
        .brand { font-weight: 700; font-size: 18px; margin-bottom: 24px; }
        .nav { display: grid; gap: 6px; }
        .nav a {
            color: var(--muted);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 6px;
        }
        .nav a:hover { background: var(--panel-2); color: var(--text); }
        .main { flex: 1; padding: 28px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
        .muted { color: var(--muted); }
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 18px;
        }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .metric { font-size: 30px; font-weight: 700; margin-top: 8px; }
        .button {
            display: inline-block;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .badge {
            display: inline-block;
            border: 1px solid var(--success);
            color: var(--success);
            border-radius: 6px;
            padding: 4px 8px;
        }
        input {
            width: 100%;
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px;
        }
        label { display: block; margin-bottom: 6px; color: var(--muted); }
        .field { margin-bottom: 14px; }
        .alert {
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 14px;
        }
        @media (max-width: 760px) {
            .shell { display: block; }
            .sidebar { width: auto; }
            .grid { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; gap: 12px; flex-direction: column; }
        }
    </style>
</head>
<body>
<?php echo $content; ?>
</body>
</html>
