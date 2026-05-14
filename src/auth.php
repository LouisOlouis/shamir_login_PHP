<?php
/**
 * src/auth.php
 * Funções auxiliares de autenticação.
 *
 * Tolerância a falha:
 *  - loadShare() retorna null se a conexão do banco for null (offline)
 *  - saveShare()  lança exceção apenas se banco obrigatório falhar
 *  - loginUser()  coleta os 3 primeiros shares disponíveis dentre os 4 bancos
 *  - registerUser() aborta se não conseguir salvar shares suficientes
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/shamir.php';

// -------------------------------------------------------
// Constantes de configuração do Shamir
// -------------------------------------------------------
define('SHAMIR_TOTAL',     4); // n: total de shares gerados
define('SHAMIR_THRESHOLD', 3); // k: mínimo para reconstruir o hash

// -------------------------------------------------------
// Hash com Argon2id
// -------------------------------------------------------

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ]);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// -------------------------------------------------------
// Persistência de shares
// -------------------------------------------------------

/**
 * Salva 1 share no banco $dbIndex.
 * Retorna true em sucesso, false se banco estiver offline (null).
 * Lança PDOException somente em erro inesperado de SQL.
 */
function saveShare(
    array  $dbConnections,
    int    $dbIndex,
    int    $userId,
    int    $shareIndex,
    string $shareBytes
): bool {
    $pdo = $dbConnections[$dbIndex] ?? null;

    // Banco offline: não é possível salvar
    if ($pdo === null) {
        return false;
    }

    $b64  = base64_encode($shareBytes);
    $stmt = $pdo->prepare(
        'INSERT INTO shares (user_id, share_index, share_value)
         VALUES (:uid, :idx, :val)
         ON DUPLICATE KEY UPDATE share_value = VALUES(share_value)'
    );
    $stmt->execute([':uid' => $userId, ':idx' => $shareIndex, ':val' => $b64]);
    return true;
}

/**
 * Lê 1 share de um banco.
 * Retorna null se banco offline (null) ou share não encontrado.
 */
function loadShare(
    array $dbConnections,
    int   $dbIndex,
    int   $userId,
    int   $shareIndex
): ?string {
    $pdo = $dbConnections[$dbIndex] ?? null;

    // Banco offline
    if ($pdo === null) {
        return null;
    }

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
 * Registra um novo usuário.
 *
 * Exige que todos os bancos que estiverem online recebam seu share.
 * Se menos de SHAMIR_THRESHOLD bancos estiverem disponíveis, aborta
 * e desfaz o insert do usuário (rollback manual).
 *
 * @throws RuntimeException
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

    // Conta quantos bancos estão disponíveis antes de inserir qualquer coisa
    $availableBanks = 0;
    for ($i = 1; $i <= SHAMIR_TOTAL; $i++) {
        if ($dbConnections[$i] !== null) {
            $availableBanks++;
        }
    }

    if ($availableBanks < SHAMIR_THRESHOLD) {
        throw new \RuntimeException(
            "Apenas $availableBanks banco(s) disponível(is). " .
            'São necessários pelo menos ' . SHAMIR_THRESHOLD . ' para registrar com segurança. ' .
            'Verifique se os servidores secundários estão online.'
        );
    }

    // Insere usuário
    $ins = $dbMain->prepare('INSERT INTO users (email) VALUES (:email)');
    $ins->execute([':email' => $email]);
    $userId = (int) $dbMain->lastInsertId();

    // Hash Argon2id + divisão em shares
    $hash   = hashPassword($password);
    $shares = \Shamir\split($hash, SHAMIR_TOTAL, SHAMIR_THRESHOLD);

    // Salva shares nos bancos disponíveis
    $savedCount  = 0;
    $failedBanks = [];

    foreach ($shares as $shareIndex => $shareBytes) {
        $ok = saveShare($dbConnections, $shareIndex, $userId, $shareIndex, $shareBytes);
        if ($ok) {
            $savedCount++;
        } else {
            $failedBanks[] = $shareIndex;
        }
    }

    // Se não salvou shares suficientes, apaga o usuário e lança erro
    if ($savedCount < SHAMIR_THRESHOLD) {
        $del = $dbMain->prepare('DELETE FROM users WHERE id = :id');
        $del->execute([':id' => $userId]);

        throw new \RuntimeException(
            "Registro abortado: apenas $savedCount share(s) salvo(s). " .
            'Banco(s) offline: DB' . implode(', DB', $failedBanks) . '. ' .
            'Verifique os servidores e tente novamente.'
        );
    }
}

// -------------------------------------------------------
// Login de usuário
// -------------------------------------------------------

/**
 * Autentica o usuário buscando shares nos 4 bancos.
 * Usa os 3 primeiros que responderem (threshold=3).
 * Tolera 1 banco secundário offline.
 *
 * @throws RuntimeException
 */
function loginUser(
    PDO    $dbMain,
    array  $dbConnections,
    string $email,
    string $password
): array {
    // Busca usuário pelo email
    $stmt = $dbMain->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Executa hash fake para manter tempo constante (evita user-enumeration)
        password_verify($password, '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHRmYWtl$ZmFrZWhhc2hmYWtlaGFzaA');
        throw new \RuntimeException('Credenciais inválidas.');
    }

    $userId = (int) $user['id'];

    // Coleta shares dos 4 bancos — para ao atingir threshold=3
    // Bancos offline (null) são pulados automaticamente
    $collectedShares = [];
    $failedBanks     = [];

    for ($dbIndex = 1; $dbIndex <= SHAMIR_TOTAL; $dbIndex++) {
        if (count($collectedShares) >= SHAMIR_THRESHOLD) {
            break;
        }

        // Banco offline? pula
        if ($dbConnections[$dbIndex] === null) {
            $failedBanks[] = $dbIndex;
            continue;
        }

        try {
            $share = loadShare($dbConnections, $dbIndex, $userId, $dbIndex);
            if ($share !== null) {
                $collectedShares[] = $share;
            } else {
                // Banco online mas share ausente (dado corrompido/apagado)
                $failedBanks[] = $dbIndex;
                error_log("[ShamirAuth] Share $dbIndex ausente para user_id=$userId");
            }
        } catch (\PDOException $e) {
            // Erro de SQL inesperado durante a leitura
            $failedBanks[] = $dbIndex;
            error_log("[ShamirAuth] Erro ao ler share $dbIndex: " . $e->getMessage());
        }
    }

    // Sem shares suficientes → login impossível
    if (count($collectedShares) < SHAMIR_THRESHOLD) {
        $available = count($collectedShares);
        throw new \RuntimeException(
            "Servidores insuficientes para autenticar ($available/" . SHAMIR_THRESHOLD . " disponíveis). " .
            'Banco(s) offline: DB' . implode(', DB', $failedBanks) . '. ' .
            'São necessários pelo menos ' . SHAMIR_THRESHOLD . ' servidores online.'
        );
    }

    // Reconstrói o hash Argon2id via interpolação de Lagrange
    $reconstructedHash = \Shamir\combine($collectedShares);

    // Valida a senha contra o hash reconstruído
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
// CSRF
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
    if (empty($_SESSION['csrf_token']) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------------------------------------------
// Flash messages
// -------------------------------------------------------

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}