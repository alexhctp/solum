<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    return Database::connection();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        exit('Token de seguranca invalido.');
    }
}

function redirectTo(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consumeFlash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function postId(string $key, array &$errors, string $label): ?int
{
    $value = postString($key);

    if (!ctype_digit($value) || (int) $value < 1) {
        $errors[$key] = "Informe {$label}.";
        return null;
    }

    return (int) $value;
}

function decimalInput(
    string $key,
    array &$errors,
    string $label,
    int $integerDigits,
    int $scale,
    bool $required = true
): ?string {
    $value = str_replace(',', '.', postString($key));

    if ($value === '' && !$required) {
        return null;
    }

    $pattern = '/^\d{1,' . $integerDigits . '}(?:\.\d{1,' . $scale . '})?$/';
    if (!preg_match($pattern, $value)) {
        $errors[$key] = "Informe {$label} como numero decimal com ate {$scale} casas.";
        return null;
    }

    [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return $integer . '.' . str_pad($fraction, $scale, '0');
}

function renderHeader(string $title): void
{
    $flash = consumeFlash();
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | Solum</title>
        <style>
            :root { color-scheme: light; font-family: system-ui, sans-serif; }
            body { max-width: 1180px; margin: 0 auto; padding: 1.5rem; color: #1f2937; background: #f5f7f4; }
            nav { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
            nav a { color: #166534; font-weight: 600; }
            main { background: #fff; padding: 1.5rem; border: 1px solid #d1d5db; border-radius: .5rem; }
            form { display: grid; gap: 1rem; max-width: 760px; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; }
            label { display: grid; gap: .35rem; font-weight: 600; }
            input, select, textarea, button { box-sizing: border-box; font: inherit; padding: .6rem; border: 1px solid #9ca3af; border-radius: .3rem; }
            textarea { min-height: 6rem; resize: vertical; }
            button, .button { display: inline-block; width: fit-content; border: 0; background: #166534; color: #fff; padding: .65rem 1rem; cursor: pointer; text-decoration: none; border-radius: .3rem; }
            .button.secondary { background: #4b5563; }
            .button.danger, button.danger { background: #b91c1c; }
            .actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
            .error { color: #b91c1c; font-size: .9rem; }
            .notice { padding: .8rem; margin-bottom: 1rem; background: #dcfce7; border: 1px solid #86efac; }
            table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; overflow: auto; }
            th, td { text-align: left; vertical-align: top; padding: .7rem; border-bottom: 1px solid #d1d5db; }
            th { background: #f3f4f6; }
            .table-wrap { overflow-x: auto; }
            small { color: #4b5563; font-weight: 400; }
            footer { margin-top: 1.5rem; text-align: center; }
        </style>
    </head>
    <body>
        <nav aria-label="Navegacao principal">
            <a href="dashboard.php">Dashboard</a>
            <a href="propriedades.php">Propriedades</a>
            <a href="talhoes.php">Talhoes</a>
            <a href="analises_solo.php">Analises de solo</a>
        </nav>
        <main>
            <h1><?= e($title) ?></h1>
            <?php if ($flash !== null): ?>
                <div class="notice" role="status"><?= e($flash['message']) ?></div>
            <?php endif; ?>
    <?php
}

function renderFooter(): void
{
    ?>
        </main>
        <footer>
            Feito com ❤️ ☕ pela equipe <a href="equipe.php">Solus</a>
        </footer>
    </body>
    </html>
    <?php
}
