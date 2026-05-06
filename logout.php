<?php
/**
 * logout.php — Encerra a sessão do usuário
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/auth.php';

// Verifica CSRF antes de destruir a sessão
$token = $_POST['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($token)) {
    sessionLogout();
}

// Redireciona sempre para o login, independente do resultado
header('Location: index.php');
exit;
