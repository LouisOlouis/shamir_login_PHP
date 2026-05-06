<?php
/**
 * config.php
 * Carrega variáveis do .env e abre as 4 conexões PDO.
 */

declare(strict_types=1);

// -------------------------------------------------------
// 1. Carregador mínimo de .env (sem dependências externas)
// -------------------------------------------------------
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env não encontrado em: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Ignora comentários e linhas vazias
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Separa chave=valor
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remove aspas envolventes opcionais
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Só define se ainda não estiver no ambiente
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// -------------------------------------------------------
// 2. Helper: lê variável de ambiente (obrigatória)
// -------------------------------------------------------
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Variável de ambiente ausente: $key");
    }

    return $value;
}

// -------------------------------------------------------
// 3. Carrega o arquivo .env da raiz do projeto
// -------------------------------------------------------
loadEnv(__DIR__ . '/.env');

// -------------------------------------------------------
// 4. Factory de conexão PDO
// -------------------------------------------------------
function makePdo(
    string $host,
    int    $port,
    string $dbname,
    string $user,
    string $pass
): PDO {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// -------------------------------------------------------
// 5. Abre as 4 conexões
//    $dbConnections[1..4] → PDO de cada banco
// -------------------------------------------------------
$dbConnections = [];

for ($i = 1; $i <= 4; $i++) {
    $dbConnections[$i] = makePdo(
        host  : env("DB{$i}_HOST"),
        port  : (int) env("DB{$i}_PORT", '3306'),
        dbname: env("DB{$i}_NAME"),
        user  : env("DB{$i}_USER"),
        pass  : env("DB{$i}_PASS")
    );
}

// Alias semântico: DB principal é sempre o índice 1
$dbMain = $dbConnections[1];

// -------------------------------------------------------
// 6. Sessão segura
// -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => (int) env('SESSION_LIFETIME', '3600'),
        'path'     => '/',
        'secure'   => false,   // altere para true em produção (HTTPS)
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}