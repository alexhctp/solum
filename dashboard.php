<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pdo = db();
$scope = propriedadeScope('p');
$perfil = currentPerfil();

$totalsStmt = $pdo->prepare('SELECT COUNT(*) FROM propriedades p WHERE ' . $scope['sql']);
$totalsStmt->execute($scope['params']);
$totals = [
    'propriedades' => (int) $totalsStmt->fetchColumn(),
    'talhoes' => 0,
];

$latestStmt = $pdo->prepare(
    'SELECT a.*, t.identificacao, p.nome AS propriedade_nome, p.cidade, p.uf,
            ((a.ca_cmolc + a.mg_cmolc + (a.k_mgdm3 / 390)) + a.h_al_cmolc) AS ctc_pH7,
            ((a.ca_cmolc + a.mg_cmolc + (a.k_mgdm3 / 390)) /
                NULLIF((a.ca_cmolc + a.mg_cmolc + (a.k_mgdm3 / 390)) + a.h_al_cmolc, 0) * 100) AS v_atual,
            (a.k_mgdm3 >= 120 AND a.mg_cmolc > 0) AS k_mg_risco,
            (a.ca_cmolc / NULLIF(a.mg_cmolc, 0) < 3 OR a.ca_cmolc / NULLIF(a.mg_cmolc, 0) > 4) AS relacao_risco
     FROM analises_solo a
     INNER JOIN talhoes t ON t.id = a.talhao_id
     INNER JOIN propriedades p ON p.id = t.propriedade_id
     WHERE NOT EXISTS (
         SELECT 1 FROM analises_solo newer
         WHERE newer.talhao_id = a.talhao_id
           AND (newer.data_coleta > a.data_coleta
                OR (newer.data_coleta = a.data_coleta AND newer.id > a.id))
     )
     AND ' . $scope['sql'] . '
     ORDER BY p.nome, t.identificacao'
);
$latestStmt->execute($scope['params']);
$latestAnalyses = $latestStmt->fetchAll();

$rbacUsers = [];
$rbacClients = [];
$rbacProperties = [];
$rbacProfile = [];

if ($perfil === 'admin') {
    $rbacUsers = $pdo->query('SELECT id, nome, email, perfil FROM usuarios ORDER BY perfil, nome')->fetchAll();
} elseif ($perfil === 'tecnico') {
    $clientStmt = $pdo->prepare(
        "SELECT u.id, u.nome, u.email, u.perfil
         FROM usuarios u
         INNER JOIN tecnico_cliente tc ON tc.cliente_id = u.id
         WHERE tc.tecnico_id = :uid AND u.perfil = 'proprietario'
         ORDER BY u.nome"
    );
    $clientStmt->execute(['uid' => currentUserId()]);
    $rbacClients = $clientStmt->fetchAll();
} else {
    $profileStmt = $pdo->prepare(
        'SELECT id, nome, email, perfil FROM usuarios WHERE id = :uid'
    );
    $profileStmt->execute(['uid' => currentUserId()]);
    $rbacProfile = $profileStmt->fetch() ?: [];
}

$propertyStmt = $pdo->prepare(
    'SELECT p.id, p.nome, p.cidade, p.uf, p.area_total, p.cultura_principal, u.nome AS proprietario_nome
     FROM propriedades p
     LEFT JOIN usuarios u ON u.id = p.proprietario_id
     WHERE ' . $scope['sql'] . '
     ORDER BY p.nome'
);
$propertyStmt->execute($scope['params']);
$rbacProperties = $propertyStmt->fetchAll();

$monitoredPlots = count($latestAnalyses);
$criticalPlots = 0;
$antagonismRisks = [];
$propertyStats = [];

foreach ($latestAnalyses as $analysis) {
    $isCritical = (float) $analysis['ph_agua'] < 5.5 || (float) ($analysis['v_atual'] ?? 0) < 50;
    $hasAntagonismRisk = (bool) $analysis['k_mg_risco'] || (bool) $analysis['relacao_risco'];
    if ($isCritical) {
        $criticalPlots++;
    }
    if ($hasAntagonismRisk) {
        $antagonismRisks[] = $analysis;
    }

    $property = $analysis['propriedade_nome'];
    if (!isset($propertyStats[$property])) {
        $propertyStats[$property] = ['total' => 0, 'criticos' => 0];
    }
    $propertyStats[$property]['total']++;
    if ($isCritical) {
        $propertyStats[$property]['criticos']++;
    }
}

$criticalPercent = $monitoredPlots > 0 ? ($criticalPlots / $monitoredPlots) * 100 : 0;
$propertyLabels = array_keys($propertyStats);
$propertyCritical = array_map(static fn (array $item): int => $item['criticos'], array_values($propertyStats));
$propertyTotal = array_map(static fn (array $item): int => $item['total'], array_values($propertyStats));

renderHeader('Painel de Gestão de fertilidade');
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { max-width: none; padding: 0; background: #f4f7f2; }
    body > nav { max-width: 1180px; margin: 1.5rem auto; padding: 0 1.5rem; }
    main { max-width: 1180px; margin: 0 auto; background: transparent; border: 0; padding: 0 1.5rem 2rem; }
    .kpi { border: 0; border-left: .3rem solid #198754; box-shadow: 0 .2rem .7rem rgba(25, 60, 35, .08); }
    .kpi .value { font-size: 2rem; font-weight: 700; }
    .chart-card { min-height: 330px; }
    .chart-container { position: relative; height: 250px; }
    .risk-row { border-left: .3rem solid #dc3545; }
    @media (max-width: 576px) { .chart-card { min-height: 270px; } .chart-container { height: 190px; } }
</style>
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><span class="text-success fw-semibold">MONITORAMENTO</span><h1 class="display-6 fw-bold mb-1">Saúde do solo</h1><p class="text-secondary mb-0">Visão consolidada das análises mais recentes por talhão.</p></div>
        <a class="btn btn-success" href="analises_solo.php">Nova análise</a>
    </div>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Escopo do perfil: <?= e($perfil) ?></h2>
            <?php if ($perfil === 'admin'): ?>
                <h3 class="h6 mt-3">Usuarios, tecnicos e clientes</h3>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th></tr></thead>
                        <tbody>
                        <?php foreach ($rbacUsers as $user): ?>
                            <tr><td><?= e($user['nome']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['perfil']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($perfil === 'tecnico'): ?>
                <h3 class="h6 mt-3">Clientes associados</h3>
                <?php if ($rbacClients === []): ?>
                    <p class="text-secondary mb-0">Nenhum cliente vinculado a este tecnico.</p>
                <?php else: ?>
                    <ul class="mb-0">
                        <?php foreach ($rbacClients as $client): ?>
                            <li><?= e($client['nome']) ?> — <?= e($client['email']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <h3 class="h6 mt-3">Meus dados</h3>
                <p class="mb-0"><?= e($rbacProfile['nome'] ?? '') ?> — <?= e($rbacProfile['email'] ?? '') ?></p>
            <?php endif; ?>
            <h3 class="h6 mt-4">Propriedades no seu escopo</h3>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Propriedade</th><th>Proprietario</th><th>Local</th><th>Area (ha)</th><th>Cultura</th></tr></thead>
                    <tbody>
                    <?php foreach ($rbacProperties as $property): ?>
                        <tr>
                            <td><?= e($property['nome']) ?></td>
                            <td><?= e($property['proprietario_nome'] ?? '-') ?></td>
                            <td><?= e($property['cidade']) ?>/<?= e($property['uf']) ?></td>
                            <td><?= e($property['area_total']) ?></td>
                            <td><?= e($property['cultura_principal'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4"><div class="card kpi h-100"><div class="card-body"><div class="text-secondary">Propriedades</div><div class="value text-success"><?= e($totals['propriedades']) ?></div><small>cadastradas no sistema</small></div></div></div>
        <div class="col-12 col-md-4"><div class="card kpi h-100"><div class="card-body"><div class="text-secondary">Talhões monitorados</div><div class="value text-success"><?= e($monitoredPlots) ?></div><small>com pelo menos uma análise</small></div></div></div>
        <div class="col-12 col-md-4"><div class="card kpi h-100"><div class="card-body"><div class="text-secondary">Acidez crítica</div><div class="value <?= $criticalPercent >= 50 ? 'text-danger' : 'text-warning' ?>"><?= e(number_format($criticalPercent, 1, ',', '.')) ?>%</div><small>pH &lt; 5,5 ou V% &lt; 50</small></div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-lg-7"><div class="card chart-card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Talhões críticos por propriedade</h2><div class="chart-container"><canvas id="criticalChart" aria-label="Gráfico de talhões críticos por propriedade"></canvas></div></div></div></div>
        <div class="col-12 col-lg-5"><div class="card chart-card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Distribuição do monitoramento</h2><div class="chart-container"><canvas id="overviewChart" aria-label="Gráfico de distribuição do monitoramento"></canvas></div></div></div></div>
    </div>
    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h2 class="h5 mb-0">Risco de antagonismo K x Mg</h2><span class="badge text-bg-danger"><?= e(count($antagonismRisks)) ?> alerta(s)</span></div>
            <?php if ($antagonismRisks === []): ?>
                <p class="text-secondary mb-0">Nenhum risco identificado nas análises mais recentes.</p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($antagonismRisks as $risk): ?>
                        <div class="col-12 col-xl-6"><div class="risk-row bg-light rounded p-3"><div class="fw-semibold"><?= e($risk['propriedade_nome']) ?> · <?= e($risk['identificacao']) ?></div><small class="text-secondary">K: <?= e($risk['k_mgdm3']) ?> mg/dm³ · Mg: <?= e($risk['mg_cmolc']) ?> cmolc/dm³ · Ca:Mg: <?= $risk['mg_cmolc'] > 0 ? e(number_format((float) $risk['ca_cmolc'] / (float) $risk['mg_cmolc'], 2, ',', '.')) . ':1' : 'indisponível' ?></small><div class="mt-2"><a href="relatorio_laudo.php?analise_id=<?= e($risk['id']) ?>" class="btn btn-sm btn-outline-danger">Ver laudo</a></div></div></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($propertyLabels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
new Chart(document.getElementById('criticalChart'), { type: 'bar', data: { labels, datasets: [
    { label: 'Críticos', data: <?= json_encode($propertyCritical) ?>, backgroundColor: '#dc3545', borderRadius: 5 },
    { label: 'Monitorados', data: <?= json_encode($propertyTotal) ?>, backgroundColor: '#198754', borderRadius: 5 }
] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
new Chart(document.getElementById('overviewChart'), { type: 'doughnut', data: { labels: ['Críticos', 'Sem criticidade'], datasets: [{ data: [<?= e($criticalPlots) ?>, <?= e(max(0, $monitoredPlots - $criticalPlots)) ?>], backgroundColor: ['#dc3545', '#198754'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
</script>
<?php renderFooter(); ?>
