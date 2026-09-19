<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pdo = db();
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action === 'delete') {
        $id = postId('id', $errors, 'um identificador valido');
        if ($id !== null) {
            if (!userCanAccessTalhao($pdo, $id)) {
                denyAccess();
            }
            $statement = $pdo->prepare('DELETE FROM talhoes WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('success', 'Talhao excluido.');
            redirectTo('talhoes.php');
        }
    } elseif ($action === 'save') {
        $id = postString('id');
        $propriedadeId = postId('propriedade_id', $errors, 'a propriedade');
        $identificacao = postString('identificacao');
        $areaHa = decimalInput('area_ha', $errors, 'a area do talhao', 10, 2);
        $textura = postString('textura_solo');
        $historico = postString('historico_manejo');

        if ($identificacao === '' || mb_strlen($identificacao) > 100) {
            $errors['identificacao'] = 'Informe a identificacao (ate 100 caracteres).';
        }
        if (!in_array($textura, ['argiloso', 'medio', 'arenoso'], true)) {
            $errors['textura_solo'] = 'Selecione uma textura de solo valida.';
        }
        if (mb_strlen($historico) > 65535) {
            $errors['historico_manejo'] = 'O historico de manejo e muito longo.';
        }

        if ($errors === []) {
            if ($propriedadeId === null || !userCanAccessProperty($pdo, $propriedadeId)) {
                $errors['propriedade_id'] = 'A propriedade selecionada nao existe.';
            }
        }

        if ($id !== '' && ctype_digit($id) && (int) $id > 0 && $errors === []) {
            if (!userCanAccessTalhao($pdo, (int) $id)) {
                denyAccess();
            }
        }

        if ($errors === []) {
            $params = [
                'propriedade_id' => $propriedadeId,
                'identificacao' => $identificacao,
                'area_ha' => $areaHa,
                'textura_solo' => $textura,
                'historico_manejo' => $historico !== '' ? $historico : null,
            ];

            if ($id === '') {
                $statement = $pdo->prepare(
                    'INSERT INTO talhoes (propriedade_id, identificacao, area_ha, textura_solo, historico_manejo)
                     VALUES (:propriedade_id, :identificacao, :area_ha, :textura_solo, :historico_manejo)'
                );
                $statement->execute($params);
                flash('success', 'Talhao cadastrado.');
            } elseif (ctype_digit($id) && (int) $id > 0) {
                $params['id'] = (int) $id;
                $statement = $pdo->prepare(
                    'UPDATE talhoes
                     SET propriedade_id = :propriedade_id, identificacao = :identificacao,
                         area_ha = :area_ha, textura_solo = :textura_solo,
                         historico_manejo = :historico_manejo
                     WHERE id = :id'
                );
                $statement->execute($params);
                flash('success', 'Talhao atualizado.');
            } else {
                $errors['id'] = 'Identificador invalido.';
            }

            if ($errors === []) {
                redirectTo('talhoes.php');
            }
        }

        $editing = $_POST;
    }
}

if ($editing === null && isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT t.*
         FROM talhoes t
         INNER JOIN propriedades p ON p.id = t.propriedade_id
         WHERE t.id = :id AND ' . propriedadeScope('p')['sql']
    );
    $scope = propriedadeScope('p');
    $statement->execute(['id' => (int) $_GET['edit']] + $scope['params']);
    $editing = $statement->fetch() ?: null;
}

$scope = propriedadeScope('p');
$propertiesStatement = $pdo->prepare(
    'SELECT p.id, p.nome, p.produtor
     FROM propriedades p
     WHERE ' . $scope['sql'] . '
     ORDER BY p.nome, p.produtor'
);
$propertiesStatement->execute($scope['params']);
$properties = $propertiesStatement->fetchAll();
$plotsStatement = $pdo->prepare(
    'SELECT t.*, p.nome AS propriedade_nome
     FROM talhoes t
     INNER JOIN propriedades p ON p.id = t.propriedade_id
     WHERE ' . $scope['sql'] . '
     ORDER BY p.nome, t.identificacao'
);
$plotsStatement->execute($scope['params']);
$plots = $plotsStatement->fetchAll();
renderHeader($editing !== null ? 'Editar talhao' : 'Cadastrar talhao');
?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
    <div class="grid">
        <label>Propriedade
            <select name="propriedade_id" required>
                <option value="">Selecione</option>
                <?php foreach ($properties as $property): ?>
                    <option value="<?= e($property['id']) ?>" <?= (string) ($editing['propriedade_id'] ?? '') === (string) $property['id'] ? 'selected' : '' ?>>
                        <?= e($property['nome']) ?> - <?= e($property['produtor']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['propriedade_id'])): ?><span class="error"><?= e($errors['propriedade_id']) ?></span><?php endif; ?>
        </label>
        <label>Identificacao
            <input name="identificacao" maxlength="100" required value="<?= e($editing['identificacao'] ?? '') ?>">
            <?php if (isset($errors['identificacao'])): ?><span class="error"><?= e($errors['identificacao']) ?></span><?php endif; ?>
        </label>
        <label>Area (ha)
            <input type="number" name="area_ha" min="0.01" step="0.01" required value="<?= e($editing['area_ha'] ?? '') ?>">
            <?php if (isset($errors['area_ha'])): ?><span class="error"><?= e($errors['area_ha']) ?></span><?php endif; ?>
        </label>
        <label>Textura do solo
            <select name="textura_solo" required>
                <?php foreach (['argiloso' => 'Argiloso', 'medio' => 'Medio', 'arenoso' => 'Arenoso'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($editing['textura_solo'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['textura_solo'])): ?><span class="error"><?= e($errors['textura_solo']) ?></span><?php endif; ?>
        </label>
    </div>
    <label>Historico de manejo
        <textarea name="historico_manejo" maxlength="65535"><?= e($editing['historico_manejo'] ?? '') ?></textarea>
        <?php if (isset($errors['historico_manejo'])): ?><span class="error"><?= e($errors['historico_manejo']) ?></span><?php endif; ?>
    </label>
    <div class="actions"><button type="submit">Salvar</button><?php if ($editing !== null): ?><a class="button secondary" href="talhoes.php">Cancelar</a><?php endif; ?></div>
</form>

<h2>Talhoes cadastrados</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Propriedade</th><th>Identificacao</th><th>Area (ha)</th><th>Textura</th><th>Acoes</th></tr></thead>
    <tbody>
    <?php foreach ($plots as $plot): ?>
        <tr>
            <td><?= e($plot['propriedade_nome']) ?></td><td><?= e($plot['identificacao']) ?></td>
            <td><?= e($plot['area_ha']) ?></td><td><?= e($plot['textura_solo']) ?></td>
            <td class="actions">
                <a class="button secondary" href="talhoes.php?edit=<?= e($plot['id']) ?>">Editar</a>
                <form method="post" onsubmit="return confirm('Excluir este talhao?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($plot['id']) ?>">
                    <button class="danger" type="submit">Excluir</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php renderFooter(); ?>
