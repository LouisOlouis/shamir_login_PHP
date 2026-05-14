<?php
/**
 * config.php
 *
 * Carrega .env, inicia sessão e prepara conexões PDO de forma LAZY:
 * - DB1 (principal) é conectado imediatamente — sem ele o sistema não funciona.
 * - DB2, DB3, DB4 são conectados sob demanda e toleram falha.
 *   Um banco secundário offline NÃO impede a abertura do site.
 */

declare(strict_types=1);

// -------------------------------------------------------
// 1. Sessão segura — sempre primeiro
// -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// -------------------------------------------------------
// 2. Carregador de .env
// -------------------------------------------------------
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        die(
            '<pre style="font-family:monospace;padding:20px;background:#1e1e2e;color:#f38ba8;">' .
            "ERRO: Arquivo .env não encontrado.\n\n" .
            "Copie o template e preencha:\n  cp .env.example .env\n\n" .
            "Caminho esperado: $path</pre>"
        );
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') ||
             ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// -------------------------------------------------------
// 3. Helper: lê variável de ambiente
// -------------------------------------------------------
function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        if ($default !== '') {
            return $default;
        }
        die(
            '<pre style="font-family:monospace;padding:20px;background:#1e1e2e;color:#f38ba8;">' .
            "ERRO: Variável obrigatória ausente no .env: $key</pre>"
        );
    }

    return (string) $value;
}

// -------------------------------------------------------
// 4. Carrega .env
// -------------------------------------------------------
loadEnv(__DIR__ . '/.env');

// -------------------------------------------------------
// 5. Factory de conexão PDO
//    $required = true  → die() se falhar (banco principal)
//    $required = false → retorna null se falhar (bancos secundários)
// -------------------------------------------------------
function makePdo(
    string $host,
    int    $port,
    string $dbname,
    string $user,
    string $pass,
    bool   $required = false
): ?PDO {
    $dsn     = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3, // timeout curto para servidor offline
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if ($required) {
            die(
                '<pre style="font-family:monospace;padding:20px;background:#1e1e2e;color:#f38ba8;">' .
                "ERRO CRÍTICO: Banco principal \"$dbname\" inacessível.\n\n" .
                'Mensagem: ' . htmlspecialchars($e->getMessage()) . "\n\n" .
                "Verifique:\n" .
                "  1. MySQL está rodando (XAMPP → Start MySQL)\n" .
                "  2. Credenciais DB1 no .env estão corretas\n" .
                "  3. O banco foi criado (sql/schema.sql)</pre>"
            );
        }
        // Banco secundário offline: registra no log e retorna null
        error_log("[ShamirAuth] Banco secundário \"$dbname\" offline: " . $e->getMessage());
        return null;
    }
}

// -------------------------------------------------------
// 6. DB1 — obrigatório (contém tabela users)
// -------------------------------------------------------
$dbConnections    = [];
$dbConnections[1] = makePdo(
    host    : env('DB1_HOST'),
    port    : (int) env('DB1_PORT', '3306'),
    dbname  : env('DB1_NAME'),
    user    : env('DB1_USER'),
    pass    : env('DB1_PASS'),
    required: true
);

$dbMain = $dbConnections[1];

// -------------------------------------------------------
// 7. DB2, DB3, DB4 — opcionais (null se offline)
//    auth.php trata null e pula para o próximo banco
// -------------------------------------------------------
for ($i = 2; $i <= 4; $i++) {
    $dbConnections[$i] = makePdo(
        host    : env("DB{$i}_HOST"),
        port    : (int) env("DB{$i}_PORT", '3306'),
        dbname  : env("DB{$i}_NAME"),
        user    : env("DB{$i}_USER"),
        pass    : env("DB{$i}_PASS"),
        required: false
    );
}