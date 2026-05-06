<?php
/**
 * config.php
 * Carrega variáveis do .env, inicia sessão e abre as 4 conexões PDO.
 *
 * ORDEM DE EXECUÇÃO (importante):
 *  1. Sessão iniciada PRIMEIRO (funções de auth.php dependem de $_SESSION)
 *  2. .env carregado
 *  3. Conexões PDO abertas
 */

declare(strict_types=1);

// -------------------------------------------------------
// 1. Sessão segura — deve ser a primeira coisa
// -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path'     => '/',
        'secure'   => false,   // mude para true em produção (HTTPS)
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// -------------------------------------------------------
// 2. Carregador mínimo de .env (sem dependências externas)
// -------------------------------------------------------
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        die(
            '<pre style="font-family:monospace;padding:20px;background:#1e1e2e;color:#f38ba8;">' .
            "ERRO: Arquivo .env não encontrado.\n\n" .
            "Copie o arquivo de exemplo e preencha seus dados:\n" .
            "  cp .env.example .env\n\n" .
            "Caminho esperado: $path" .
            '</pre>'
        );
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remove aspas envolventes opcionais ("valor" ou 'valor')
        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"'  && $value[-1] === '"')  ||
                ($value[0] === "'"  && $value[-1] === "'")
            )
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
            "ERRO: Variável de ambiente obrigatória não encontrada: $key\n\n" .
            'Verifique seu arquivo .env.' .
            '</pre>'
        );
    }

    return (string) $value;
}

// -------------------------------------------------------
// 4. Carrega o .env
// -------------------------------------------------------
loadEnv(__DIR__ . '/.env');

// -------------------------------------------------------
// 5. Factory de conexão PDO
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

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        die(
            '<pre style="font-family:monospace;padding:20px;background:#1e1e2e;color:#f38ba8;">' .
            "ERRO: Não foi possível conectar ao banco \"$dbname\" em $host:$port\n\n" .
            'Mensagem: ' . $e->getMessage() . "\n\n" .
            "Verifique:\n" .
            "  1. MySQL está rodando (XAMPP → Start MySQL)\n" .
            "  2. As credenciais no .env estão corretas\n" .
            "  3. O banco de dados foi criado (rode sql/schema.sql)" .
            '</pre>'
        );
    }
}

// -------------------------------------------------------
// 6. Abre as 4 conexões  →  $dbConnections[1..4]
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

// Alias para o banco principal (DB1 contém a tabela users)
$dbMain = $dbConnections[1];
