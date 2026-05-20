<?php
/**
 * src/layout.php
 * Funções de layout HTML compartilhadas entre as páginas.
 */

declare(strict_types=1);

function htmlHead(string $title): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} — Shamir Login</title>
  <style>
    /* ── Tokens ─────────────────────────────────────────── */
    :root {
      --bg:        #0b0f1a;
      --surface:   #111827;
      --border:    #1e2d45;
      --accent:    #3b82f6;
      --accent-h:  #2563eb;
      --danger:    #ef4444;
      --success:   #22c55e;
      --text:      #e2e8f0;
      --muted:     #64748b;
      --radius:    10px;
      --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
      --font-sans: 'Inter', system-ui, sans-serif;
    }

    /* ── Reset ──────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body {
      font-family: var(--font-sans);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }

    /* ── Card ───────────────────────────────────────────── */
    .card {
      width: 100%;
      max-width: 420px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2.5rem 2rem;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }

    /* ── Logo / título ──────────────────────────────────── */
    .logo {
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-bottom: 2rem;
    }
    .logo-icon {
      width: 36px; height: 36px;
      background: var(--accent);
      border-radius: 8px;
      display: grid;
      place-items: center;
      font-size: 1.1rem;
    }
    .logo-text {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: -.02em;
      color: var(--text);
    }
    .logo-sub {
      font-size: .7rem;
      color: var(--muted);
      font-family: var(--font-mono);
    }

    h1 {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: -.03em;
      margin-bottom: .4rem;
    }
    .subtitle {
      font-size: .85rem;
      color: var(--muted);
      margin-bottom: 1.8rem;
    }

    /* ── Form ───────────────────────────────────────────── */
    label {
      display: block;
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: .4rem;
    }
    input[type="email"],
    input[type="password"],
    input[type="text"] {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-family: var(--font-sans);
      font-size: .95rem;
      padding: .65rem .9rem;
      outline: none;
      transition: border-color .15s;
      margin-bottom: 1.1rem;
    }
    input:focus { border-color: var(--accent); }

    /* ── Botão ──────────────────────────────────────────── */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100%;
      padding: .75rem 1rem;
      border: none;
      border-radius: var(--radius);
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s, transform .1s;
      text-decoration: none;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
    }
    .btn-primary:hover { background: var(--accent-h); transform: translateY(-1px); }
    .btn-ghost {
      background: transparent;
      color: var(--accent);
      border: 1px solid var(--border);
      margin-top: .6rem;
    }
    .btn-ghost:hover { border-color: var(--accent); }
    .btn-danger {
      background: var(--danger);
      color: #fff;
    }
    .btn-danger:hover { filter: brightness(1.1); }

    /* ── Alertas ────────────────────────────────────────── */
    .alert {
      border-radius: var(--radius);
      padding: .75rem 1rem;
      font-size: .875rem;
      margin-bottom: 1.2rem;
      display: flex;
      align-items: flex-start;
      gap: .5rem;
    }
    .alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
    .alert-success { background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.3);  color: #86efac; }

    /* ── Badge de segurança ─────────────────────────────── */
    .security-badge {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      margin-top: 1.5rem;
      padding-top: 1.2rem;
      border-top: 1px solid var(--border);
    }
    .badge {
      font-size: .65rem;
      font-family: var(--font-mono);
      background: rgba(59,130,246,.08);
      border: 1px solid rgba(59,130,246,.2);
      color: var(--accent);
      border-radius: 4px;
      padding: .2rem .5rem;
    }

    /* ── Dashboard ──────────────────────────────────────── */
    .dash-card { max-width: 560px; }
    .info-row {
      display: flex;
      align-items: center;
      gap: .8rem;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: .8rem 1rem;
      margin-bottom: .8rem;
    }
    .info-label {
      font-size: .7rem;
      font-family: var(--font-mono);
      text-transform: uppercase;
      color: var(--muted);
      min-width: 80px;
    }
    .info-value { font-size: .9rem; word-break: break-all; }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); flex-shrink: 0; }

    .flow-title {
      font-size: .7rem;
      font-family: var(--font-mono);
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: .6rem;
      letter-spacing: .05em;
    }
    .flow-steps {
      display: flex;
      flex-direction: column;
      gap: .4rem;
      margin-bottom: 1.5rem;
    }
    .flow-step {
      display: flex;
      align-items: center;
      gap: .6rem;
      font-size: .8rem;
      color: var(--muted);
    }
    .flow-step .num {
      width: 20px; height: 20px;
      border-radius: 50%;
      background: rgba(59,130,246,.15);
      border: 1px solid rgba(59,130,246,.3);
      color: var(--accent);
      font-size: .65rem;
      display: grid;
      place-items: center;
      flex-shrink: 0;
    }

    .footer-link {
      text-align: center;
      margin-top: 1.2rem;
      font-size: .83rem;
      color: var(--muted);
    }
    .footer-link a { color: var(--accent); text-decoration: none; }
    .footer-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
HTML;
}

function htmlFoot(): string
{
    return '</body></html>';
}

function flashMessage(): string
{
    if (empty($_SESSION['flash'])) return '';

    ['type' => $type, 'message' => $msg] = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $icon = $type === 'error' ? '⚠' : '✓';
    return "<div class=\"alert alert-{$type}\"><span>{$icon}</span> " . htmlspecialchars($msg) . '</div>';
}

// setFlash() definida em src/auth.php
