<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$pdo = db();
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action === 'delete') {
        $id = postId('id', $errors, 'um identificador valido');
        if ($id !== null) {
            $statement = $pdo->prepare('DELETE FROM propriedades WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('success', 'Propriedade excluida. Os talhoes e analises relacionados tambem foram removidos.');
            redirectTo('propriedades.php');
        }
    } elseif ($action === 'save') {
        $id = postString('id');
        $nome = postString('nome');
        $produtor = postString('produtor');
        $cidade = postString('cidade');
        $uf = strtoupper(postString('uf'));
        $areaTotal = decimalInput('area_total', $errors, 'a area total', 10, 2);

        if ($nome === '' || mb_strlen($nome) > 150) {
            $errors['nome'] = 'Informe o nome da propriedade (ate 150 caracteres).';
        }
        if ($produtor === '' || mb_strlen($produtor) > 150) {
            $errors['produtor'] = 'Informe o produtor (ate 150 caracteres).';
        }
        if ($cidade === '' || mb_strlen($cidade) > 100) {
            $errors['cidade'] = 'Informe a cidade (ate 100 caracteres).';
        }
        if (!preg_match('/^[A-Z]{2}$/', $uf)) {
            $errors['uf'] = 'Informe a UF com duas letras.';
        }

        if ($errors === []) {
            $params = [
                'nome' => $nome,
                'produtor' => $produtor,
                'cidade' => $cidade,
                'uf' => $uf,
                'area_total' => $areaTotal,
            ];

            if ($id === '') {
                $statement = $pdo->prepare(
                    'INSERT INTO propriedades (nome, produtor, cidade, uf, area_total)
                     VALUES (:nome, :produtor, :cidade, :uf, :area_total)'
                );
                $statement->execute($params);
                flash('success', 'Propriedade cadastrada.');
            } elseif (ctype_digit($id) && (int) $id > 0) {
                $params['id'] = (int) $id;
                $statement = $pdo->prepare(
                    'UPDATE propriedades
                     SET nome = :nome, produtor = :produtor, cidade = :cidade,
                         uf = :uf, area_total = :area_total
                     WHERE id = :id'
                );
                $statement->execute($params);
                flash('success', 'Propriedade atualizada.');
            } else {
                $errors['id'] = 'Identificador invalido.';
            }

            if ($errors === []) {
                redirectTo('propriedades.php');
            }
        }

        $editing = $_POST;
    }
}

if ($editing === null && isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM propriedades WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}

$properties = $pdo->query('SELECT * FROM propriedades ORDER BY nome, produtor')->fetchAll();
renderHeader($editing !== null ? 'Editar propriedade' : 'Cadastrar propriedade');
?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
    <div class="grid">
        <label>Nome da propriedade
            <input name="nome" maxlength="150" required value="<?= e($editing['nome'] ?? '') ?>">
            <?php if (isset($errors['nome'])): ?><span class="error"><?= e($errors['nome']) ?></span><?php endif; ?>
        </label>
        <label>Produtor
            <input name="produtor" maxlength="150" required value="<?= e($editing['produtor'] ?? '') ?>">
            <?php if (isset($errors['produtor'])): ?><span class="error"><?= e($errors['produtor']) ?></span><?php endif; ?>
        </label>
        <label>Cidade
            <input name="cidade" maxlength="100" required value="<?= e($editing['cidade'] ?? '') ?>">
            <?php if (isset($errors['cidade'])): ?><span class="error"><?= e($errors['cidade']) ?></span><?php endif; ?>
        </label>
        <label>UF
            <input name="uf" maxlength="2" pattern="[A-Za-z]{2}" required value="<?= e($editing['uf'] ?? '') ?>">
            <?php if (isset($errors['uf'])): ?><span class="error"><?= e($errors['uf']) ?></span><?php endif; ?>
        </label>
        <label>Area total (ha)
            <input type="number" name="area_total" min="0.01" step="0.01" required value="<?= e($editing['area_total'] ?? '') ?>">
            <?php if (isset($errors['area_total'])): ?><span class="error"><?= e($errors['area_total']) ?></span><?php endif; ?>
        </label>
    </div>
    <div class="actions">
        <button type="submit">Salvar</button>
        <?php if ($editing !== null): ?><a class="button secondary" href="propriedades.php">Cancelar</a><?php endif; ?>
    </div>
</form>

<h2>Propriedades cadastradas</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Nome</th><th>Produtor</th><th>Local</th><th>Area (ha)</th><th>Acoes</th></tr></thead>
    <tbody>
    <?php foreach ($properties as $property): ?>
        <tr>
            <td><?= e($property['nome']) ?></td>
            <td><?= e($property['produtor']) ?></td>
            <td><?= e($property['cidade']) ?>/<?= e($property['uf']) ?></td>
            <td><?= e($property['area_total']) ?></td>
            <td class="actions">
                <a class="button secondary" href="propriedades.php?edit=<?= e($property['id']) ?>">Editar</a>
                <form method="post" onsubmit="return confirm('Excluir esta propriedade?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e($property['id']) ?>">
                    <button class="danger" type="submit">Excluir</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php renderFooter(); ?>
