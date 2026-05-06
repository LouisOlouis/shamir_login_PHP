<?php
/**
 * dashboard.php — Área autenticada
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/layout.php';

requireLogin();

$email  = htmlspecialchars($_SESSION['email']   ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

// Demonstração: carrega metadados dos shares sem expor os valores
$sharesMeta = [];
for ($i = 1; $i <= 4; $i++) {
    try {
        $stmt = $dbConnections[$i]->prepare(
            'SELECT share_index, LENGTH(share_value) AS b64_len
             FROM shares WHERE user_id = :uid AND share_index = :idx LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':idx' => $i]);
        $row = $stmt->fetch();
        $sharesMeta[$i] = $row
            ? ['status' => 'ok',     'len' => $row['b64_len']]
            : ['status' => 'missing','len' => 0];
    } catch (\PDOException) {
        $sharesMeta[$i] = ['status' => 'error', 'len' => 0];
    }
}
?>
<?= htmlHead('Dashboard') ?>

<div class="card dash-card">
  <!-- Logo -->
  <div class="logo">
    <div class="logo-icon">🔐</div>
    <div>
      <div class="logo-text">ShamirAuth</div>
      <div class="logo-sub">Autenticado</div>
    </div>
  </div>

  <h1>Bem-vindo 👋</h1>
  <p class="subtitle">Sua sessão está ativa e seus shares estão distribuídos com segurança.</p>

  <?= flashMessage() ?>

  <!-- Info da conta -->
  <div style="margin-bottom:1.5rem;">
    <div class="info-row">
      <div class="dot"></div>
      <div>
        <div class="info-label">E-mail</div>
        <div class="info-value"><?= $email ?></div>
      </div>
    </div>
    <div class="info-row">
      <div class="dot"></div>
      <div>
        <div class="info-label">User ID</div>
        <div class="info-value" style="font-family:var(--font-mono)">#<?= $userId ?></div>
      </div>
    </div>
    <div class="info-row">
      <div class="dot"></div>
      <div>
        <div class="info-label">Sessão</div>
        <div class="info-value" style="font-family:var(--font-mono)"><?= session_id() ?></div>
      </div>
    </div>
  </div>

  <!-- Status dos shares -->
  <div class="flow-title">Status dos shares (4 bancos)</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.5rem;">
    <?php foreach ($sharesMeta as $idx => $meta): ?>
      <?php
        $ok    = $meta['status'] === 'ok';
        $color = $ok ? 'var(--success)' : 'var(--danger)';
        $icon  = $ok ? '✓' : '✗';
      ?>
      <div class="info-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;">
        <div style="display:flex;align-items:center;gap:.4rem;width:100%">
          <span style="color:<?= $color ?>;font-size:.9rem;"><?= $icon ?></span>
          <span class="info-label">DB<?= $idx ?> · Share <?= $idx ?></span>
        </div>
        <span style="font-family:var(--font-mono);font-size:.72rem;color:var(--muted);">
          <?= $ok ? "{$meta['len']} bytes (base64)" : strtoupper($meta['status']) ?>
        </span>
      </div>
    <?php endforeach ?>
  </div>

  <!-- Fluxo de reconstrução -->
  <div class="flow-title">Como o login foi processado</div>
  <div class="flow-steps" style="margin-bottom:1.5rem;">
    <div class="flow-step"><span class="num">1</span> E-mail encontrado em DB1 (banco principal)</div>
    <div class="flow-step"><span class="num">2</span> Shares 1, 2 e 3 coletados de DB1, DB2, DB3</div>
    <div class="flow-step"><span class="num">3</span> Hash Argon2id reconstruído via interpolação de Lagrange em GF(2⁸)</div>
    <div class="flow-step"><span class="num">4</span> password_verify() confirmou a senha — acesso concedido</div>
  </div>

  <!-- Logout -->
  <form method="POST" action="logout.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <button type="submit" class="btn btn-danger">Sair da conta</button>
  </form>

  <div class="security-badge">
    <span class="badge">Argon2id</span>
    <span class="badge">Shamir SSS</span>
    <span class="badge">GF(2⁸)</span>
    <span class="badge">session_regenerate</span>
  </div>
</div>

<?= htmlFoot() ?>
