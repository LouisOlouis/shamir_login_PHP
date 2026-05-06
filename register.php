<?php
/**
 * register.php — Página de registro
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

$error   = '';
$success = '';

// ── Processa POST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token     = $_POST['csrf_token']      ?? '';
    $email     = trim($_POST['email']      ?? '');
    $password  = $_POST['password']        ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if (!verifyCsrf($token)) {
        $error = 'Token CSRF inválido. Recarregue a página.';
    } elseif ($email === '' || $password === '' || $password2 === '') {
        $error = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif (!hash_equals($password, $password2)) {
        $error = 'As senhas não coincidem.';
    } else {
        try {
            /** @var PDO $dbMain */
            /** @var PDO[] $dbConnections */
            registerUser($dbMain, $dbConnections, $email, $password);
            setFlash('success', 'Conta criada com sucesso! Faça login.');
            header('Location: index.php');
            exit;
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$csrf = csrfToken();
?>
<?= htmlHead('Registro') ?>

<div class="card">
  <!-- Logo -->
  <div class="logo">
    <div class="logo-icon">🔐</div>
    <div>
      <div class="logo-text">ShamirAuth</div>
      <div class="logo-sub">Secret Sharing · Argon2id</div>
    </div>
  </div>

  <h1>Criar conta</h1>
  <p class="subtitle">Sua senha nunca será armazenada inteira em nenhum banco.</p>

  <!-- Erro -->
  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><span>⚠</span> <?= htmlspecialchars($error) ?></div>
  <?php endif ?>

  <!-- Fluxo visual -->
  <div class="flow-title">O que acontece ao registrar</div>
  <div class="flow-steps">
    <div class="flow-step"><span class="num">1</span> Senha é transformada em hash Argon2id (64 MB, 4 rounds)</div>
    <div class="flow-step"><span class="num">2</span> Hash é dividido em 4 shares via GF(2⁸) (threshold 3)</div>
    <div class="flow-step"><span class="num">3</span> Cada share é salvo em um banco MySQL separado</div>
    <div class="flow-step"><span class="num">4</span> Senha original é descartada da memória</div>
  </div>

  <!-- Formulário -->
  <form method="POST" action="register.php" autocomplete="on">
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

    <label for="password">Senha <span style="color:var(--muted);font-weight:400;text-transform:none;">(mín. 8 caracteres)</span></label>
    <input
      type="password"
      id="password"
      name="password"
      required
      autocomplete="new-password"
      placeholder="••••••••"
      minlength="8"
    >

    <label for="password_confirm">Confirmar senha</label>
    <input
      type="password"
      id="password_confirm"
      name="password_confirm"
      required
      autocomplete="new-password"
      placeholder="••••••••"
      minlength="8"
    >

    <button type="submit" class="btn btn-primary">Criar conta →</button>
  </form>

  <div class="footer-link">
    Já tem conta? <a href="index.php">Entrar</a>
  </div>

  <!-- Badges -->
  <div class="security-badge">
    <span class="badge">Argon2id</span>
    <span class="badge">GF(2⁸)</span>
    <span class="badge">4 bancos</span>
    <span class="badge">threshold=3</span>
    <span class="badge">CSRF</span>
  </div>
</div>

<?= htmlFoot() ?>