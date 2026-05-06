<?php
/**
 * src/auth.php
 * Funções auxiliares de autenticação:
 *  - geração de hash Argon2id
 *  - persistência e leitura de shares nos 4 bancos
 *  - registro e login de usuários
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/shamir.php';

// -------------------------------------------------------
// Constantes de configuração do Shamir
// -------------------------------------------------------
define('SHAMIR_TOTAL',     4); // n: total de shares
define('SHAMIR_THRESHOLD', 3); // k: mínimo para reconstruir

// -------------------------------------------------------
// Hash com Argon2id
// -------------------------------------------------------

/**
 * Gera hash Argon2id da senha.
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ]);
}

/**
 * Verifica se hash reconstruído bate com a senha informada.
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// -------------------------------------------------------
// Persistência de shares
// -------------------------------------------------------

/**
 * Salva 1 share no banco de índice $dbIndex.
 *
 * @param PDO[]  $dbConnections  Mapa [1..4 => PDO]
 * @param int    $dbIndex        Qual banco usar (1-4)
 * @param int    $userId         ID do usuário no DB principal
 * @param int    $shareIndex     Índice lógico do share (1-4)
 * @param string $shareBytes     Bytes brutos do share
 */
function saveShare(
    array  $dbConnections,
    int    $dbIndex,
    int    $userId,
    int    $shareIndex,
    string $shareBytes
): void {
    $pdo  = $dbConnections[$dbIndex];
    $b64  = base64_encode($shareBytes);

    $stmt = $pdo->prepare(
        'INSERT INTO shares (user_id, share_index, share_value)
         VALUES (:uid, :idx, :val)
         ON DUPLICATE KEY UPDATE share_value = VALUES(share_value)'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':idx' => $shareIndex,
        ':val' => $b64,
    ]);
}

/**
 * Lê 1 share de um banco específico.
 *
 * @return string|null  Bytes brutos do share, ou null se não encontrado
 */
function loadShare(
    array $dbConnections,
    int   $dbIndex,
    int   $userId,
    int   $shareIndex
): ?string {
    $pdo  = $dbConnections[$dbIndex];
    $stmt = $pdo->prepare(
        'SELECT share_value FROM shares
         WHERE user_id = :uid AND share_index = :idx
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId, ':idx' => $shareIndex]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $decoded = base64_decode($row['share_value'], strict: true);
    return ($decoded === false) ? null : $decoded;
}

// -------------------------------------------------------
// Registro de usuário
// -------------------------------------------------------

/**
 * Registra um novo usuário:
 *  1. Cria registro na tabela users (DB1)
 *  2. Gera hash Argon2id
 *  3. Divide em 4 shares (threshold 3)
 *  4. Salva share i no banco i
 *
 * @param PDO    $dbMain         Conexão DB1 (principal)
 * @param PDO[]  $dbConnections  Mapa [1..4 => PDO]
 * @param string $email
 * @param string $password
 * @throws RuntimeException se e-mail já estiver cadastrado
 */
function registerUser(
    PDO    $dbMain,
    array  $dbConnections,
    string $email,
    string $password
): void {
    // Verifica duplicidade
    $chk = $dbMain->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        throw new \RuntimeException('E-mail já cadastrado.');
    }

    // Insere usuário (sem senha)
    $ins = $dbMain->prepare('INSERT INTO users (email) VALUES (:email)');
    $ins->execute([':email' => $email]);
    $userId = (int) $dbMain->lastInsertId();

    // Hash Argon2id
    $hash = hashPassword($password);

    // Divide hash em 4 shares
    $shares = \Shamir\split($hash, SHAMIR_TOTAL, SHAMIR_THRESHOLD);

    // Persiste share i no banco i
    foreach ($shares as $shareIndex => $shareBytes) {
        saveShare($dbConnections, $shareIndex, $userId, $shareIndex, $shareBytes);
    }
}

// -------------------------------------------------------
// Login de usuário
// -------------------------------------------------------

/**
 * Tenta autenticar o usuário:
 *  1. Busca ID pelo e-mail (DB1)
 *  2. Coleta os shares dos bancos 1, 2 e 3 (threshold 3)
 *  3. Reconstrói o hash via Shamir
 *  4. Verifica com password_verify
 *
 * @param PDO    $dbMain
 * @param PDO[]  $dbConnections
 * @param string $email
 * @param string $password
 * @return array{id:int,email:string}  Dados do usuário autenticado
 * @throws RuntimeException em falha de autenticação
 */
function loginUser(
    PDO    $dbMain,
    array  $dbConnections,
    string $email,
    string $password
): array {
    // Busca usuário
    $stmt = $dbMain->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Tempo constante para evitar user-enumeration timing
        password_verify($password, '$argon2id$v=19$m=65536,t=4,p=2$fakesalt$fakehash');
        throw new \RuntimeException('Credenciais inválidas.');
    }

    $userId = (int) $user['id'];

    // Coleta os 3 primeiros shares (bancos 1, 2, 3) — threshold = 3
    $collectedShares = [];
    foreach ([1, 2, 3] as $dbIndex) {
        $share = loadShare($dbConnections, $dbIndex, $userId, $dbIndex);
        if ($share === null) {
            throw new \RuntimeException("Share $dbIndex não encontrado. Banco corrompido?");
        }
        $collectedShares[] = $share;
    }

    // Reconstrói o hash
    $reconstructedHash = \Shamir\combine($collectedShares);

    // Valida senha
    if (!verifyPassword($password, $reconstructedHash)) {
        throw new \RuntimeException('Credenciais inválidas.');
    }

    return ['id' => $userId, 'email' => $user['email']];
}

// -------------------------------------------------------
// Helpers de sessão
// -------------------------------------------------------

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function sessionLogin(int $userId, string $email): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['email']   = $email;
}

function sessionLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// -------------------------------------------------------
// CSRF simples
// -------------------------------------------------------

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    // Token vazio ou sessão sem token gerado → inválido
    if (empty($_SESSION['csrf_token']) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
