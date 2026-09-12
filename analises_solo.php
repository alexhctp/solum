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
            $statement = $pdo->prepare('DELETE FROM analises_solo WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('success', 'Analise excluida.');
            redirectTo('analises_solo.php');
        }
    } elseif ($action === 'save') {
        $id = postString('id');
        $talhaoId = postId('talhao_id', $errors, 'o talhao');
        $dataColeta = postString('data_coleta');
        $camada = postString('camada_cm');
        $extrator = postString('extrator_p');
        $ph = decimalInput('ph_agua', $errors, 'o pH da agua', 2, 2);
        $al = decimalInput('al_cmolc', $errors, 'o aluminio (Al)', 5, 3);
        $ca = decimalInput('ca_cmolc', $errors, 'o calcio (Ca)', 5, 3);
        $mg = decimalInput('mg_cmolc', $errors, 'o magnesio (Mg)', 5, 3);
        $k = decimalInput('k_mgdm3', $errors, 'o potassio (K)', 8, 2);
        $p = decimalInput('p_mgdm3', $errors, 'o fosforo (P)', 8, 2);
        $mo = decimalInput('mo_gdm3', $errors, 'a materia organica', 8, 2);
        $hAl = decimalInput('h_al_cmolc', $errors, 'H+Al', 5, 3);

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dataColeta);
        if ($date === false || $date->format('Y-m-d') !== $dataColeta) {
            $errors['data_coleta'] = 'Informe uma data de coleta valida.';
        }
        if (!preg_match('/^\d{1,3}-\d{1,3}$/', $camada)) {
            $errors['camada_cm'] = 'Informe a camada no formato 0-20.';
        }
        if (!in_array($extrator, ['Mehlich-1', 'Resina'], true)) {
            $errors['extrator_p'] = 'Selecione um extrator valido.';
        }
        if ($ph !== null && (float) $ph > 14) {
            $errors['ph_agua'] = 'O pH deve estar entre 0 e 14.';
        }

        if ($errors === []) {
            $plotCheck = $pdo->prepare('SELECT id FROM talhoes WHERE id = :id');
            $plotCheck->execute(['id' => $talhaoId]);
            if (!$plotCheck->fetchColumn()) {
                $errors['talhao_id'] = 'O talhao selecionado nao existe.';
            }
        }

        if ($errors === []) {
            $params = [
                'talhao_id' => $talhaoId,
                'data_coleta' => $dataColeta,
                'camada_cm' => $camada,
                'extrator_p' => $extrator,
                'ph_agua' => $ph,
                'al_cmolc' => $al,
                'ca_cmolc' => $ca,
                'mg_cmolc' => $mg,
                'k_mgdm3' => $k,
                'p_mgdm3' => $p,
                'mo_gdm3' => $mo,
                'h_al_cmolc' => $hAl,
            ];

            if ($id === '') {
                $statement = $pdo->prepare(
                    'INSERT INTO analises_solo
                        (talhao_id, data_coleta, camada_cm, extrator_p, ph_agua, al_cmolc, ca_cmolc,
                         mg_cmolc, k_mgdm3, p_mgdm3, mo_gdm3, h_al_cmolc)
                     VALUES
                        (:talhao_id, :data_coleta, :camada_cm, :extrator_p, :ph_agua, :al_cmolc, :ca_cmolc,
                         :mg_cmolc, :k_mgdm3, :p_mgdm3, :mo_gdm3, :h_al_cmolc)'
                );
                $statement->execute($params);
                flash('success', 'Analise de solo cadastrada.');
            } elseif (ctype_digit($id) && (int) $id > 0) {
                $params['id'] = (int) $id;
                $statement = $pdo->prepare(
                    'UPDATE analises_solo
                     SET talhao_id = :talhao_id, data_coleta = :data_coleta, camada_cm = :camada_cm,
                         extrator_p = :extrator_p, ph_agua = :ph_agua, al_cmolc = :al_cmolc,
                         ca_cmolc = :ca_cmolc, mg_cmolc = :mg_cmolc, k_mgdm3 = :k_mgdm3,
                         p_mgdm3 = :p_mgdm3, mo_gdm3 = :mo_gdm3, h_al_cmolc = :h_al_cmolc
                     WHERE id = :id'
                );
                $statement->execute($params);
                flash('success', 'Analise de solo atualizada.');
            } else {
                $errors['id'] = 'Identificador invalido.';
            }

            if ($errors === []) {
                redirectTo('analises_solo.php');
            }
        }

        $editing = $_POST;
    }
}

if ($editing === null && isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM analises_solo WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}

$plots = $pdo->query(
    'SELECT t.id, t.identificacao, p.nome AS propriedade_nome
     FROM talhoes t
     INNER JOIN propriedades p ON p.id = t.propriedade_id
     ORDER BY p.nome, t.identificacao'
)->fetchAll();
$analyses = $pdo->query(
    'SELECT a.*, t.identificacao, p.nome AS propriedade_nome
     FROM analises_solo a
     INNER JOIN talhoes t ON t.id = a.talhao_id
     INNER JOIN propriedades p ON p.id = t.propriedade_id
     ORDER BY a.data_coleta DESC, p.nome, t.identificacao'
)->fetchAll();
renderHeader($editing !== null ? 'Editar analise de solo' : 'Cadastrar analise de solo');
?>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
    <div class="grid">
        <label>Talhao
            <select name="talhao_id" required>
                <option value="">Selecione</option>
                <?php foreach ($plots as $plot): ?>
                    <option value="<?= e($plot['id']) ?>" <?= (string) ($editing['talhao_id'] ?? '') === (string) $plot['id'] ? 'selected' : '' ?>>
                        <?= e($plot['propriedade_nome']) ?> - <?= e($plot['identificacao']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['talhao_id'])): ?><span class="error"><?= e($errors['talhao_id']) ?></span><?php endif; ?>
        </label>
        <label>Data da coleta
            <input type="date" name="data_coleta" required value="<?= e($editing['data_coleta'] ?? '') ?>">
            <?php if (isset($errors['data_coleta'])): ?><span class="error"><?= e($errors['data_coleta']) ?></span><?php endif; ?>
        </label>
        <label>Camada (cm)
            <input name="camada_cm" pattern="[0-9]{1,3}-[0-9]{1,3}" placeholder="0-20" required value="<?= e($editing['camada_cm'] ?? '') ?>">
            <?php if (isset($errors['camada_cm'])): ?><span class="error"><?= e($errors['camada_cm']) ?></span><?php endif; ?>
        </label>
        <label>Extrator de P
            <select name="extrator_p" required>
                <?php foreach (['Mehlich-1', 'Resina'] as $extractor): ?>
                    <option value="<?= e($extractor) ?>" <?= ($editing['extrator_p'] ?? '') === $extractor ? 'selected' : '' ?>><?= e($extractor) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['extrator_p'])): ?><span class="error"><?= e($errors['extrator_p']) ?></span><?php endif; ?>
        </label>
        <label>pH em agua
            <input type="number" name="ph_agua" min="0" max="14" step="0.01" required value="<?= e($editing['ph_agua'] ?? '') ?>">
            <?php if (isset($errors['ph_agua'])): ?><span class="error"><?= e($errors['ph_agua']) ?></span><?php endif; ?>
        </label>
    </div>
    <h2>Macronutrientes e indicadores</h2>
    <div class="grid">
        <?php foreach ([
            'al_cmolc' => ['Al (cmolc/dm3)', '0.001'], 'ca_cmolc' => ['Ca (cmolc/dm3)', '0.001'],
            'mg_cmolc' => ['Mg (cmolc/dm3)', '0.001'], 'k_mgdm3' => ['K (mg/dm3)', '0.01'],
            'p_mgdm3' => ['P (mg/dm3)', '0.01'], 'mo_gdm3' => ['MO (g/dm3)', '0.01'],
            'h_al_cmolc' => ['H+Al (cmolc/dm3)', '0.001'],
        ] as $field => [$label, $step]): ?>
            <label><?= e($label) ?>
                <input type="number" name="<?= e($field) ?>" min="0" step="<?= e($step) ?>" required value="<?= e($editing[$field] ?? '0') ?>">
                <?php if (isset($errors[$field])): ?><span class="error"><?= e($errors[$field]) ?></span><?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="actions"><button type="submit">Salvar</button><?php if ($editing !== null): ?><a class="button secondary" href="analises_solo.php">Cancelar</a><?php endif; ?></div>
</form>

<h2>Analises cadastradas</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Propriedade / talhao</th><th>Coleta</th><th>Camada</th><th>Extrator</th><th>pH</th><th>Ca</th><th>Mg</th><th>K</th><th>P</th><th>Acoes</th></tr></thead>
    <tbody>
    <?php foreach ($analyses as $analysis): ?>
        <tr>
            <td><?= e($analysis['propriedade_nome']) ?> / <?= e($analysis['identificacao']) ?></td>
            <td><?= e($analysis['data_coleta']) ?></td><td><?= e($analysis['camada_cm']) ?></td><td><?= e($analysis['extrator_p']) ?></td>
            <td><?= e($analysis['ph_agua']) ?></td><td><?= e($analysis['ca_cmolc']) ?></td><td><?= e($analysis['mg_cmolc']) ?></td>
            <td><?= e($analysis['k_mgdm3']) ?></td><td><?= e($analysis['p_mgdm3']) ?></td>
            <td class="actions">
                <a class="button secondary" href="analises_solo.php?edit=<?= e($analysis['id']) ?>">Editar</a>
                <form method="post" onsubmit="return confirm('Excluir esta analise?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($analysis['id']) ?>">
                    <button class="danger" type="submit">Excluir</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php renderFooter(); ?>
