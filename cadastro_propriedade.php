<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pdo = db();
$errors = [];
$perfil = currentPerfil();
$owners = fetchAccessibleOwners($pdo);
$form = [
    'nome' => '',
    'cidade' => '',
    'uf' => '',
    'area_total' => '',
    'cultura_principal' => '',
    'proprietario_id' => $perfil === 'proprietario' ? (string) currentUserId() : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['nome'] = postString('nome');
    $form['cidade'] = postString('cidade');
    $form['uf'] = strtoupper(postString('estado'));
    $form['cultura_principal'] = postString('cultura_principal');
    $areaTotal = decimalInput('area_hectares', $errors, 'a area em hectares', 10, 2);

    if ($perfil === 'proprietario') {
        $ownerId = currentUserId();
    } else {
        $ownerId = postId('proprietario_id', $errors, 'o proprietario');
    }

    if ($form['nome'] === '' || mb_strlen($form['nome']) > 150) {
        $errors['nome'] = 'Informe o nome da propriedade (ate 150 caracteres).';
    }
    if ($form['cidade'] === '' || mb_strlen($form['cidade']) > 100) {
        $errors['cidade'] = 'Informe a cidade (ate 100 caracteres).';
    }
    if (!preg_match('/^[A-Z]{2}$/', $form['uf'])) {
        $errors['estado'] = 'Informe o estado (UF) com duas letras.';
    }
    if ($form['cultura_principal'] === '' || mb_strlen($form['cultura_principal']) > 100) {
        $errors['cultura_principal'] = 'Informe a cultura principal (ate 100 caracteres).';
    }

    $ownerName = '';
    if ($ownerId !== null && $errors === []) {
        $allowedOwnerIds = array_map(static fn (array $owner): int => (int) $owner['id'], $owners);
        if (!in_array($ownerId, $allowedOwnerIds, true)) {
            $errors['proprietario_id'] = 'Selecione um proprietario valido para o seu perfil.';
        } else {
            foreach ($owners as $owner) {
                if ((int) $owner['id'] === $ownerId) {
                    $ownerName = (string) $owner['nome'];
                    break;
                }
            }
        }
    }

    if ($errors === []) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO propriedades
                    (proprietario_id, nome, produtor, cidade, uf, area_total, cultura_principal)
                 VALUES
                    (:proprietario_id, :nome, :produtor, :cidade, :uf, :area_total, :cultura_principal)'
            );
            $statement->execute([
                'proprietario_id' => $ownerId,
                'nome' => $form['nome'],
                'produtor' => $ownerName,
                'cidade' => $form['cidade'],
                'uf' => $form['uf'],
                'area_total' => $areaTotal,
                'cultura_principal' => $form['cultura_principal'],
            ]);
            flash('success', 'Propriedade cadastrada.');
            redirectTo('cadastro_propriedade.php');
        } catch (Throwable $exception) {
            error_log('Falha ao cadastrar propriedade: ' . $exception->getMessage());
            $errors['nome'] = 'Nao foi possivel salvar a propriedade.';
        }
    }

    $form['area_total'] = postString('area_hectares');
    $form['proprietario_id'] = (string) ($ownerId ?? $form['proprietario_id']);
}

$scope = propriedadeScope('p');
$list = $pdo->prepare(
    'SELECT p.id, p.nome, p.cidade, p.uf, p.area_total, p.cultura_principal,
            u.nome AS proprietario_nome, u.email AS proprietario_email
     FROM propriedades p
     LEFT JOIN usuarios u ON u.id = p.proprietario_id
     WHERE ' . $scope['sql'] . '
     ORDER BY p.nome'
);
$list->execute($scope['params']);
$properties = $list->fetchAll();

renderHeader('Cadastrar propriedade');
?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <div class="grid">
        <?php if ($perfil !== 'proprietario'): ?>
            <label>Proprietario
                <select name="proprietario_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($owners as $owner): ?>
                        <option value="<?= e($owner['id']) ?>" <?= $form['proprietario_id'] === (string) $owner['id'] ? 'selected' : '' ?>>
                            <?= e($owner['nome']) ?> (<?= e($owner['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['proprietario_id'])): ?><span class="error"><?= e($errors['proprietario_id']) ?></span><?php endif; ?>
            </label>
        <?php endif; ?>
        <label>Nome da propriedade
            <input name="nome" maxlength="150" required value="<?= e($form['nome']) ?>">
            <?php if (isset($errors['nome'])): ?><span class="error"><?= e($errors['nome']) ?></span><?php endif; ?>
        </label>
        <label>Cidade
            <input name="cidade" maxlength="100" required value="<?= e($form['cidade']) ?>">
            <?php if (isset($errors['cidade'])): ?><span class="error"><?= e($errors['cidade']) ?></span><?php endif; ?>
        </label>
        <label>Estado (UF)
            <input name="estado" maxlength="2" pattern="[A-Za-z]{2}" required value="<?= e($form['uf']) ?>">
            <?php if (isset($errors['estado'])): ?><span class="error"><?= e($errors['estado']) ?></span><?php endif; ?>
        </label>
        <label>Area (ha)
            <input type="number" name="area_hectares" min="0.01" step="0.01" required value="<?= e($form['area_total']) ?>">
            <?php if (isset($errors['area_hectares'])): ?><span class="error"><?= e($errors['area_hectares']) ?></span><?php endif; ?>
        </label>
        <label>Cultura principal
            <input name="cultura_principal" maxlength="100" placeholder="cafe, milho, soja" required value="<?= e($form['cultura_principal']) ?>">
            <?php if (isset($errors['cultura_principal'])): ?><span class="error"><?= e($errors['cultura_principal']) ?></span><?php endif; ?>
        </label>
    </div>
    <div class="actions"><button type="submit">Salvar</button></div>
</form>

<h2>Propriedades visiveis para o seu perfil</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Propriedade</th><th>Proprietario</th><th>Local</th><th>Area (ha)</th><th>Cultura</th></tr></thead>
    <tbody>
    <?php foreach ($properties as $property): ?>
        <tr>
            <td><?= e($property['nome']) ?></td>
            <td><?= e($property['proprietario_nome'] ?? 'nao vinculado') ?></td>
            <td><?= e($property['cidade']) ?>/<?= e($property['uf']) ?></td>
            <td><?= e($property['area_total']) ?></td>
            <td><?= e($property['cultura_principal'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php renderFooter(); ?>
