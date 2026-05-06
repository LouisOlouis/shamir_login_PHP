<?php
/**
 * index.php — Página de login
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/layout.php';

// Redireciona se já estiver logado
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// ── Processa POST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['csrf_token'] ?? '';
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!verifyCsrf($token)) {
        $error = 'Token CSRF inválido. Recarregue a página.';
    } elseif ($email === '' || $password === '') {
        $error = 'Preencha todos os campos.';
    } else {
        try {
            /** @var PDO $dbMain */
            /** @var PDO[] $dbConnections */
            $user = loginUser($dbMain, $dbConnections, $email, $password);
            sessionLogin($user['id'], $user['email']);
            header('Location: dashboard.php');
            exit;
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$csrf = csrfToken();
?>
<?= htmlHead('Login') ?>

<div class="card">
  <!-- Logo -->
  <div class="logo">
    <div class="logo-icon">🔐</div>
    <div>
      <div class="logo-text">ShamirAuth</div>
      <div class="logo-sub">Secret Sharing · Argon2id</div>
    </div>
  </div>

  <h1>Entrar</h1>
  <p class="subtitle">Acesse sua conta de forma segura.</p>

  <!-- Flash / erro inline -->
  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><span>⚠</span> <?= htmlspecialchars($error) ?></div>
  <?php endif ?>
  <?= flashMessage() ?>

  <!-- Formulário -->
  <form method="POST" action="index.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <label for="email">E-mail</label>
    <input
      type="email"
      id="email"
      name="email"
      required
      autocomplete="email"
      placeholder="voce@exemplo.com"
      value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
    >

    <label for="password">Senha</label>
    <input
      type="password"
      id="password"
      name="password"
      required
      autocomplete="current-password"
      placeholder="••••••••"
    >

    <button type="submit" class="btn btn-primary">Entrar →</button>
  </form>

  <div class="footer-link">
    Não tem conta? <a href="register.php">Registrar</a>
  </div>

  <!-- Badges de segurança -->
  <div class="security-badge">
    <span class="badge">Argon2id</span>
    <span class="badge">Shamir SSS</span>
    <span class="badge">4 bancos</span>
    <span class="badge">threshold=3</span>
    <span class="badge">CSRF</span>
  </div>
</div>

<?= htmlFoot() ?>
