<?php
/**
 * src/shamir.php
 *
 * Implementação de Shamir's Secret Sharing sobre GF(2^8) — campo de Galois
 * com polinômio irredutível 0x11B (AES).
 *
 * Suporta segredos arbitrários (array de bytes).
 * Cada share é um array de bytes com o índice x na posição 0.
 *
 * Referências:
 *  - Shamir, A. (1979). "How to share a secret." CACM 22(11):612–613.
 *  - hashicorp/vault shamirutil (Go) — lógica GF(256) equivalente.
 */

declare(strict_types=1);

namespace Shamir;

// -------------------------------------------------------
// Aritmética em GF(2^8) — polinômio x^8+x^4+x^3+x+1 (0x11B)
// -------------------------------------------------------

/** Multiplicação em GF(2^8) via Russian Peasant */
function gfMul(int $a, int $b): int
{
    $p = 0;
    for ($i = 0; $i < 8; $i++) {
        if ($b & 1) {
            $p ^= $a;
        }
        $carry = $a & 0x80;
        $a     = ($a << 1) & 0xFF;
        if ($carry) {
            $a ^= 0x1B; // x^8 mod (x^8+x^4+x^3+x+1) = x^4+x^3+x+1 = 0x1B
        }
        $b >>= 1;
    }
    return $p & 0xFF;
}

/** Potenciação em GF(2^8) */
function gfPow(int $base, int $exp): int
{
    $result = 1;
    $base  &= 0xFF;
    while ($exp > 0) {
        if ($exp & 1) {
            $result = gfMul($result, $base);
        }
        $base = gfMul($base, $base);
        $exp >>= 1;
    }
    return $result;
}

/** Inverso multiplicativo em GF(2^8) — pelo Pequeno Teorema de Fermat: a^{-1} = a^{254} */
function gfInv(int $a): int
{
    if ($a === 0) {
        throw new \DivisionByZeroError('Inverso de 0 em GF(2^8) é indefinido.');
    }
    return gfPow($a, 254);
}

// -------------------------------------------------------
// Avaliação de polinômio em GF(2^8)
// -------------------------------------------------------

/**
 * Avalia p(x) = coefficients[0] + coefficients[1]*x + ... + coefficients[k-1]*x^{k-1}
 * em GF(2^8).
 *
 * @param int[] $coefficients  Coeficientes do polinômio (índice 0 = coef. constante = segredo)
 * @param int   $x             Ponto de avaliação
 */
function polyEval(array $coefficients, int $x): int
{
    $result = 0;
    // Avaliação de Horner: p(x) = c0 + x*(c1 + x*(c2 + ...))
    for ($i = count($coefficients) - 1; $i >= 0; $i--) {
        $result = $coefficients[$i] ^ gfMul($result, $x);
    }
    return $result;
}

// -------------------------------------------------------
// Interpolação de Lagrange em GF(2^8)
// -------------------------------------------------------

/**
 * Reconstrói o valor secreto (coeficiente constante) a partir de $threshold shares.
 *
 * @param array<int,int> $xValues  Índices x dos shares (1-based)
 * @param array<int,int> $yValues  Valores y correspondentes
 */
function lagrangeInterpolate(array $xValues, array $yValues): int
{
    $k      = count($xValues);
    $secret = 0;

    for ($i = 0; $i < $k; $i++) {
        $num = 1;
        $den = 1;
        for ($j = 0; $j < $k; $j++) {
            if ($i === $j) {
                continue;
            }
            $num = gfMul($num, $xValues[$j]);               // num *= x_j
            $den = gfMul($den, $xValues[$i] ^ $xValues[$j]); // den *= (x_i XOR x_j)
        }
        $lagrange = gfMul($num, gfInv($den));
        $secret  ^= gfMul($yValues[$i], $lagrange);
    }

    return $secret & 0xFF;
}

// -------------------------------------------------------
// API pública
// -------------------------------------------------------

/**
 * Divide um segredo binário (string de bytes) em $n shares com threshold $k.
 *
 * @param string $secret     Segredo em bytes (ex.: hash Argon2id)
 * @param int    $n          Número total de shares
 * @param int    $k          Threshold (mínimo de shares para reconstruir)
 * @return array<int, string> Mapa [1..n => string_de_bytes_do_share]
 */
function split(string $secret, int $n, int $k): array
{
    if ($k < 2 || $k > $n) {
        throw new \InvalidArgumentException("Threshold inválido: k=$k, n=$n");
    }
    if ($n > 255) {
        throw new \InvalidArgumentException('Número máximo de shares é 255.');
    }

    $secretBytes = array_values(unpack('C*', $secret));
    $len         = count($secretBytes);

    // Gera pontos x únicos e aleatórios em [1..255] para cada share
    $xCoords = [];
    while (count($xCoords) < $n) {
        $candidate = ord(random_bytes(1));
        if ($candidate !== 0 && !in_array($candidate, $xCoords, true)) {
            $xCoords[] = $candidate;
        }
    }

    // Para cada byte do segredo, gera um polinômio de grau (k-1)
    // e avalia nos n pontos
    $shares = array_fill(1, $n, '');

    for ($byteIdx = 0; $byteIdx < $len; $byteIdx++) {
        // Coeficiente constante = byte do segredo; demais aleatórios
        $coefficients = [$secretBytes[$byteIdx]];
        for ($d = 1; $d < $k; $d++) {
            // Coeficiente aleatório em GF(2^8), pode ser 0 (não afeta segurança)
            $coefficients[] = ord(random_bytes(1));
        }

        for ($shareIdx = 0; $shareIdx < $n; $shareIdx++) {
            $y = polyEval($coefficients, $xCoords[$shareIdx]);
            $shares[$shareIdx + 1] .= chr($y);
        }
    }

    // Prefixa cada share com seu x (1 byte) para facilitar reconstrução
    $result = [];
    for ($i = 0; $i < $n; $i++) {
        $result[$i + 1] = chr($xCoords[$i]) . $shares[$i + 1];
    }

    return $result;
}

/**
 * Reconstrói o segredo a partir de $k ou mais shares.
 *
 * @param string[] $shares  Array de shares (qualquer subset com |shares| >= threshold)
 * @return string           Segredo reconstruído em bytes
 */
function combine(array $shares): string
{
    if (count($shares) < 2) {
        throw new \InvalidArgumentException('São necessários pelo menos 2 shares.');
    }

    // Extrai x e bytes de conteúdo de cada share
    $xValues    = [];
    $shareBytes = [];

    foreach (array_values($shares) as $share) {
        if (strlen($share) < 2) {
            throw new \InvalidArgumentException('Share inválido ou corrompido.');
        }
        $xValues[]    = ord($share[0]);
        $shareBytes[] = array_values(unpack('C*', substr($share, 1)));
    }

    $len    = count($shareBytes[0]);
    $secret = '';

    for ($byteIdx = 0; $byteIdx < $len; $byteIdx++) {
        $yValues = array_column($shareBytes, $byteIdx);
        $secret .= chr(lagrangeInterpolate($xValues, $yValues));
    }

    return $secret;
}
