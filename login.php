<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (!empty($_SESSION['usuario_id'])) {
    redirectTo('dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = mb_strtolower(postString('email'));
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Informe um e-mail valido.';
    }
    if (!is_string($senha) || $senha === '') {
        $errors['senha'] = 'Informe a senha.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'SELECT id, nome, senha, perfil FROM usuarios WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (
                !is_array($user)
                || !password_verify($senha, (string) $user['senha'])
            ) {
                $errors['email'] = 'E-mail ou senha invalidos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int) $user['id'];
                $_SESSION['usuario_nome'] = (string) $user['nome'];
                $_SESSION['perfil'] = (string) $user['perfil'];
                redirectTo('dashboard.php');
            }
        } catch (Throwable $exception) {
            error_log('Falha no login: ' . $exception->getMessage());
            $errors['email'] = 'Nao foi possivel autenticar. Tente novamente.';
        }
    }
}

renderHeader('Entrar', true);
?>
<p>Acesse com o e-mail e a senha da sua conta.</p>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <label>E-mail
        <input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>">
        <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
    </label>
    <label>Senha
        <input type="password" name="senha" required autocomplete="current-password">
        <?php if (isset($errors['senha'])): ?><span class="error"><?= e($errors['senha']) ?></span><?php endif; ?>
    </label>
    <div class="actions">
        <button type="submit">Entrar</button>
    </div>
</form>
<?php renderFooter(); ?>
