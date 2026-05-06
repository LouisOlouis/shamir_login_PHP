# ShamirAuth — Sistema de Login com Shamir's Secret Sharing

## Como funciona

1. **Registro**: senha → hash Argon2id → dividido em 4 shares via GF(2⁸) → cada share salvo em um banco MySQL separado
2. **Login**: coleta 3 shares (threshold=3) → reconstrói o hash → `password_verify()` confirma a senha

---

## Pré-requisitos

- XAMPP (Apache + MySQL) ou equivalente
- PHP 8.1+ com extensões: `pdo_mysql`, `sodium` (para `random_bytes`)
- MySQL 5.7+

---

## Passo a passo — XAMPP

### 1. Copiar o projeto para o htdocs

```
C:\xampp\htdocs\shamir-login\
```

### 2. Iniciar o MySQL no XAMPP

Painel XAMPP → botão **Start** na linha do MySQL.

### 3. Criar os 4 bancos de dados

Abra o **phpMyAdmin** (http://localhost/phpmyadmin) e execute o arquivo:

```
sql/schema.sql
```

Ou, pelo terminal MySQL:

```bash
mysql -u root -p < sql/schema.sql
```

Isso cria os 4 bancos (`shamir_db1` até `shamir_db4`) e todas as tabelas.

### 4. Configurar o .env

Copie o exemplo e edite com suas credenciais:

```bash
cp .env.example .env
```

Edite o arquivo `.env`:

```env
# DB1 — principal (users + share 1)
DB1_HOST=127.0.0.1
DB1_PORT=3306
DB1_NAME=shamir_db1
DB1_USER=root
DB1_PASS=           ← deixe vazio se o XAMPP não tem senha

# DB2 — share 2
DB2_HOST=127.0.0.1
DB2_PORT=3306
DB2_NAME=shamir_db2
DB2_USER=root
DB2_PASS=

# DB3 — share 3
DB3_HOST=127.0.0.1
DB3_PORT=3306
DB3_NAME=shamir_db3
DB3_USER=root
DB3_PASS=

# DB4 — share 4
DB4_HOST=127.0.0.1
DB4_PORT=3306
DB4_NAME=shamir_db4
DB4_USER=root
DB4_PASS=
```

> **XAMPP sem senha**: deixe `DB1_PASS=` (valor vazio, sem aspas).

### 5. Acessar no navegador

```
http://localhost/shamir-login/register.php   ← criar conta
http://localhost/shamir-login/index.php      ← login
http://localhost/shamir-login/dashboard.php  ← área logada
```

---

## Estrutura do projeto

```
shamir-login/
├── .env                  ← suas credenciais (não comitar)
├── .env.example          ← template
├── config.php            ← carrega .env + 4 conexões PDO + sessão
├── index.php             ← login
├── register.php          ← registro
├── dashboard.php         ← área autenticada
├── logout.php            ← encerra sessão
├── src/
│   ├── shamir.php        ← Shamir's Secret Sharing sobre GF(2⁸)
│   ├── auth.php          ← hash, shares, sessão, CSRF
│   └── layout.php        ← HTML/CSS compartilhado
└── sql/
    └── schema.sql        ← criação dos 4 bancos e tabelas
```

---

## Solução de problemas

| Erro | Causa | Solução |
|------|-------|---------|
| `.env não encontrado` | Arquivo não foi criado | `cp .env.example .env` |
| `SQLSTATE[HY000] [1045]` | Senha incorreta | Verifique `DB_PASS` no `.env` |
| `SQLSTATE[HY000] [1049]` | Banco não existe | Rode o `schema.sql` no phpMyAdmin |
| `SQLSTATE[HY000] [2002]` | MySQL não está rodando | XAMPP → Start MySQL |
| `Call to undefined function` | `auth.php` não incluído | Verifique os `require_once` no topo de cada página |
| `PASSWORD_ARGON2ID` undefined | PHP < 7.3 ou sem libargon2 | Atualize o PHP (XAMPP 8.x já inclui) |

---

## Segurança implementada

- ✅ **Argon2id** — hash com 64 MB de memória, 4 rounds, 2 threads
- ✅ **Shamir's Secret Sharing** — GF(2⁸), threshold 3-de-4
- ✅ **Prepared statements** — zero SQL injection
- ✅ **CSRF token** — protege todos os formulários POST
- ✅ **session_regenerate_id** — previne session fixation
- ✅ **HttpOnly + SameSite=Strict** — cookies protegidos
- ✅ **Timing constante** — evita user enumeration no login
- ✅ **random_bytes** — geração segura de coeficientes e tokens
