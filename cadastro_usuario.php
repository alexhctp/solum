<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePerfil('admin');

$pdo = db();
$errors = [];
$form = [
    'nome' => '',
    'email' => '',
    'perfil' => 'proprietario',
    'tecnicos' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['nome'] = postString('nome');
    $form['email'] = mb_strtolower(postString('email'));
    $form['perfil'] = postString('perfil');
    $senha = $_POST['senha'] ?? '';
    $tecnicos = $_POST['tecnicos'] ?? [];
    $form['tecnicos'] = is_array($tecnicos) ? $tecnicos : [];

    if ($form['nome'] === '' || mb_strlen($form['nome']) > 150) {
        $errors['nome'] = 'Informe o nome (ate 150 caracteres).';
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
        $errors['email'] = 'Informe um e-mail valido.';
    }
    if (!in_array($form['perfil'], ['admin', 'tecnico', 'proprietario'], true)) {
        $errors['perfil'] = 'Selecione um perfil valido.';
    }
    if (!is_string($senha) || strlen($senha) < 8) {
        $errors['senha'] = 'Informe uma senha com no minimo 8 caracteres.';
    }

    $tecnicoIds = [];
    if ($form['perfil'] === 'proprietario') {
        foreach ($form['tecnicos'] as $tecnicoId) {
            if (is_string($tecnicoId) && ctype_digit($tecnicoId) && (int) $tecnicoId > 0) {
                $tecnicoIds[] = (int) $tecnicoId;
            }
        }
        $tecnicoIds = array_values(array_unique($tecnicoIds));
    }

    if ($errors === []) {
        $emailCheck = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
        $emailCheck->execute(['email' => $form['email']]);
        if ($emailCheck->fetchColumn()) {
            $errors['email'] = 'Este e-mail ja esta cadastrado.';
        }
    }

    if ($errors === [] && $tecnicoIds !== []) {
        $placeholders = implode(',', array_fill(0, count($tecnicoIds), '?'));
        $tecnicoCheck = $pdo->prepare(
            "SELECT id FROM usuarios WHERE perfil = 'tecnico' AND id IN ({$placeholders})"
        );
        $tecnicoCheck->execute($tecnicoIds);
        $found = array_map('intval', $tecnicoCheck->fetchAll(PDO::FETCH_COLUMN));
        if (count($found) !== count($tecnicoIds)) {
            $errors['tecnicos'] = 'Selecione apenas tecnicos cadastrados.';
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, senha, perfil)
                 VALUES (:nome, :email, :senha, :perfil)'
            );
            $insert->execute([
                'nome' => $form['nome'],
                'email' => $form['email'],
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'perfil' => $form['perfil'],
            ]);
            $newId = (int) $pdo->lastInsertId();

            if ($form['perfil'] === 'proprietario' && $tecnicoIds !== []) {
                $link = $pdo->prepare(
                    'INSERT INTO tecnico_cliente (tecnico_id, cliente_id) VALUES (:tecnico_id, :cliente_id)'
                );
                foreach ($tecnicoIds as $tecnicoId) {
                    $link->execute([
                        'tecnico_id' => $tecnicoId,
                        'cliente_id' => $newId,
                    ]);
                }
            }

            $pdo->commit();
            flash('success', 'Usuario cadastrado.');
            redirectTo('cadastro_usuario.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Falha ao cadastrar usuario: ' . $exception->getMessage());
            $errors['email'] = 'Nao foi possivel salvar o usuario.';
        }
    }
}

$tecnicos = $pdo->query(
    "SELECT id, nome, email FROM usuarios WHERE perfil = 'tecnico' ORDER BY nome"
)->fetchAll();
$usuarios = $pdo->query(
    'SELECT id, nome, email, perfil FROM usuarios ORDER BY perfil, nome'
)->fetchAll();

renderHeader('Cadastrar usuario');
?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <div class="grid">
        <label>Nome
            <input name="nome" maxlength="150" required value="<?= e($form['nome']) ?>">
            <?php if (isset($errors['nome'])): ?><span class="error"><?= e($errors['nome']) ?></span><?php endif; ?>
        </label>
        <label>E-mail
            <input type="email" name="email" maxlength="190" required value="<?= e($form['email']) ?>">
            <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
        </label>
        <label>Senha
            <input type="password" name="senha" required minlength="8" autocomplete="new-password">
            <?php if (isset($errors['senha'])): ?><span class="error"><?= e($errors['senha']) ?></span><?php endif; ?>
        </label>
        <label>Perfil
            <select name="perfil" required>
                <?php foreach (['admin' => 'Admin', 'tecnico' => 'Tecnico', 'proprietario' => 'Proprietario'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $form['perfil'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['perfil'])): ?><span class="error"><?= e($errors['perfil']) ?></span><?php endif; ?>
        </label>
    </div>
    <label>Tecnicos vinculados <small>(opcional, apenas para proprietario)</small>
        <select name="tecnicos[]" multiple size="4">
            <?php foreach ($tecnicos as $tecnico): ?>
                <option value="<?= e($tecnico['id']) ?>" <?= in_array((string) $tecnico['id'], array_map('strval', $form['tecnicos']), true) ? 'selected' : '' ?>>
                    <?= e($tecnico['nome']) ?> (<?= e($tecnico['email']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['tecnicos'])): ?><span class="error"><?= e($errors['tecnicos']) ?></span><?php endif; ?>
    </label>
    <div class="actions"><button type="submit">Cadastrar</button></div>
</form>

<h2>Usuarios cadastrados</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $usuario): ?>
        <tr>
            <td><?= e($usuario['nome']) ?></td>
            <td><?= e($usuario['email']) ?></td>
            <td><?= e($usuario['perfil']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php renderFooter(); ?>
