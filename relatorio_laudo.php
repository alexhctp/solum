<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/InterpretadorSoloEngine.php';

$analysisId = filter_input(INPUT_GET, 'analise_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($analysisId === false || $analysisId === null) {
    http_response_code(400);
    exit('Informe uma analise_id valida.');
}

$statement = db()->prepare(
    'SELECT a.*, t.identificacao, t.area_ha, t.textura_solo, t.historico_manejo,
            p.nome AS propriedade_nome, p.produtor, p.cidade, p.uf, p.area_total
     FROM analises_solo a
     INNER JOIN talhoes t ON t.id = a.talhao_id
     INNER JOIN propriedades p ON p.id = t.propriedade_id
     WHERE a.id = :id'
);
$statement->execute(['id' => $analysisId]);
$analysis = $statement->fetch();
if (!$analysis || !userCanAccessAnalise(db(), $analysisId)) {
    http_response_code(404);
    exit('Analise nao encontrada.');
}

$interpretation = (new InterpretadorSoloEngine())->interpretar($analysis);
$ph = (float) $analysis['ph_agua'];
$al = (float) $analysis['al_cmolc'];
$ca = (float) $analysis['ca_cmolc'];
$mg = (float) $analysis['mg_cmolc'];
$k = (float) $analysis['k_mgdm3'];
$p = (float) $analysis['p_mgdm3'];
$mo = (float) $analysis['mo_gdm3'];
$caMg = $interpretation['relacao_ca_mg'];
$phStatus = $ph < 5.5 ? 'Crítico' : ($ph <= 6.5 ? 'Adequado' : 'Alto');
$alStatus = $al > 1.0 ? 'Atenção' : 'Adequado';
$vStatus = $interpretation['v_percent_atual'] < 50 ? 'Crítico' : 'Adequado';
$pStatus = $p < 12 ? 'Baixo' : 'Adequado';
$moStatus = $mo < 20 ? 'Baixa' : 'Adequada';
$gessagem = $al > 1.0 ? 'Avaliar gessagem com base em análise de subsolo, saturação por Al e teor de Ca em profundidade.' : 'Não indicada apenas pelos dados desta análise; confirmar com avaliação de subsolo.';
$adubacao = [];
if ($p < 12) $adubacao[] = 'Planejar adubação fosfatada conforme cultura, produtividade e eficiência do extrator.';
if ($k >= 120) $adubacao[] = 'Evitar concentração de K em uma única aplicação e monitorar Mg.';
if ($adubacao === []) $adubacao[] = 'Manter adubação de manutenção conforme exportação da cultura e análise periódica.';

renderHeader('Laudo de análise de solo');
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { max-width: none; padding: 0; background: #eef2ee; }
    body > nav { max-width: 1000px; margin: 1rem auto; padding: 0 1rem; }
    main { max-width: 1000px; margin: 0 auto 2rem; padding: 0 1rem 2rem; background: transparent; border: 0; }
    .report { background: #fff; padding: 2.2rem; box-shadow: 0 .2rem 1rem rgba(0,0,0,.08); }
    .report-header { border-bottom: 3px solid #198754; }
    .section-title { color: #166534; border-bottom: 1px solid #b7c9bb; padding-bottom: .4rem; }
    .status { font-size: .78rem; font-weight: 700; text-transform: uppercase; }
    .diagnostic { border-left: .3rem solid #198754; }
    .recommendation { background: #f1f8f2; border: 1px solid #b7d6bd; }
    @media print { body, main { margin: 0; padding: 0; background: #fff; } body > nav, .print-actions { display: none !important; } .report { box-shadow: none; padding: 0; } .page-break { break-before: page; } }
</style>
<div class="report">
    <div class="report-header pb-3 mb-4 d-flex justify-content-between gap-3">
        <div><div class="text-success fw-bold">SOLUM · LAUDO TÉCNICO</div><h1 class="h2 mb-1">Interpretação de análise de solo</h1><p class="text-secondary mb-0">Documento gerado em <?= e(date('d/m/Y H:i')) ?></p></div>
        <div class="print-actions"><button type="button" class="btn btn-success" onclick="window.print()">Imprimir / PDF</button></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="border rounded p-3 h-100"><h2 class="h6 text-success">Propriedade</h2><div class="fw-semibold"><?= e($analysis['propriedade_nome']) ?></div><div><?= e($analysis['produtor']) ?></div><div><?= e($analysis['cidade']) ?>/<?= e($analysis['uf']) ?> · Área total: <?= e($analysis['area_total']) ?> ha</div></div></div>
        <div class="col-md-6"><div class="border rounded p-3 h-100"><h2 class="h6 text-success">Talhão e amostra</h2><div class="fw-semibold"><?= e($analysis['identificacao']) ?> · <?= e($analysis['area_ha']) ?> ha</div><div>Coleta: <?= e(date('d/m/Y', strtotime($analysis['data_coleta']))) ?> · Camada: <?= e($analysis['camada_cm']) ?> cm</div><div>Textura: <?= e($analysis['textura_solo']) ?> · Extrator: <?= e($analysis['extrator_p']) ?></div></div></div>
    </div>
    <h2 class="section-title h5">Valores analisados e referências gerais</h2>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead class="table-light"><tr><th>Indicador</th><th>Resultado</th><th>Referência geral</th><th>Status</th></tr></thead><tbody>
        <tr><td>pH em água</td><td><?= e($analysis['ph_agua']) ?></td><td>5,5 a 6,5</td><td><span class="status <?= $phStatus === 'Crítico' ? 'text-danger' : 'text-success' ?>"><?= e($phStatus) ?></span></td></tr>
        <tr><td>Al (cmolc/dm³)</td><td><?= e($analysis['al_cmolc']) ?></td><td>&le; 1,0</td><td><span class="status <?= $alStatus === 'Atenção' ? 'text-warning' : 'text-success' ?>"><?= e($alStatus) ?></span></td></tr>
        <tr><td>Ca (cmolc/dm³)</td><td><?= e($analysis['ca_cmolc']) ?></td><td>Interpretar com textura e CTC</td><td>Informativo</td></tr>
        <tr><td>Mg (cmolc/dm³)</td><td><?= e($analysis['mg_cmolc']) ?></td><td>Interpretar com textura e CTC</td><td>Informativo</td></tr>
        <tr><td>K (mg/dm³)</td><td><?= e($analysis['k_mgdm3']) ?></td><td>Faixa depende da CTC e cultura</td><td><?= $interpretation['k_elevado'] ? '<span class="status text-danger">Elevado</span>' : 'Avaliar' ?></td></tr>
        <tr><td>P (mg/dm³)</td><td><?= e($analysis['p_mgdm3']) ?></td><td>&ge; 12,0 como referência geral</td><td><span class="status <?= $pStatus === 'Baixo' ? 'text-warning' : 'text-success' ?>"><?= e($pStatus) ?></span></td></tr>
        <tr><td>Matéria orgânica (g/dm³)</td><td><?= e($analysis['mo_gdm3']) ?></td><td>&ge; 20,0 como referência geral</td><td><span class="status <?= $moStatus === 'Baixa' ? 'text-warning' : 'text-success' ?>"><?= e($moStatus) ?></span></td></tr>
    </tbody></table></div>
    <h2 class="section-title h5 mt-4">Diagnóstico técnico</h2>
    <div class="row g-3">
        <div class="col-md-6"><div class="diagnostic border rounded p-3 h-100"><h3 class="h6">1. pH / Al</h3><p class="mb-0">pH <?= e($phStatus) ?> (<?= e($analysis['ph_agua']) ?>) e Al <?= e($alStatus) ?> (<?= e($analysis['al_cmolc']) ?> cmolc/dm³).</p></div></div>
        <div class="col-md-6"><div class="diagnostic border rounded p-3 h-100"><h3 class="h6">2. CTC / V%</h3><p class="mb-0">CTC a pH 7: <strong><?= e(number_format($interpretation['ctc_pH7_cmolc'], 2, ',', '.')) ?></strong> cmolc/dm³. V% atual: <strong><?= e(number_format($interpretation['v_percent_atual'], 2, ',', '.')) ?>%</strong> (<?= e($vStatus) ?>).</p></div></div>
        <div class="col-md-6"><div class="diagnostic border rounded p-3 h-100"><h3 class="h6">3. Ca / Mg / K</h3><p class="mb-0">SB: <strong><?= e(number_format($interpretation['sb_cmolc'], 2, ',', '.')) ?></strong>. Relação Ca:Mg: <strong><?= $caMg === null ? 'indisponível' : e(number_format($caMg, 2, ',', '.')) . ':1' ?></strong>. <?= $interpretation['k_elevado'] ? 'K elevado: monitorar antagonismo com Mg.' : 'K sem alerta pelo limiar configurado.' ?></p></div></div>
        <div class="col-md-6"><div class="diagnostic border rounded p-3 h-100"><h3 class="h6">4. P</h3><p class="mb-0">Fósforo em <?= e($analysis['extrator_p']) ?>: <strong><?= e($analysis['p_mgdm3']) ?> mg/dm³</strong>. Classificação preliminar: <?= e($pStatus) ?>.</p></div></div>
        <div class="col-md-6"><div class="diagnostic border rounded p-3 h-100"><h3 class="h6">5. Matéria orgânica</h3><p class="mb-0">MO: <strong><?= e($analysis['mo_gdm3']) ?> g/dm³</strong>. Classificação preliminar: <?= e($moStatus) ?>.</p></div></div>
    </div>
    <div class="recommendation rounded p-4 mt-4 page-break"><h2 class="section-title h5">Recomendações técnicas</h2><div class="row g-3"><div class="col-md-4"><h3 class="h6">Calagem</h3><p class="mb-0"><?= e($interpretation['parecer_tecnico']) ?></p></div><div class="col-md-4"><h3 class="h6">Gessagem</h3><p class="mb-0"><?= e($gessagem) ?></p></div><div class="col-md-4"><h3 class="h6">Adubação</h3><ul class="mb-0"><?php foreach ($adubacao as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div></div><section class="border-top mt-4 pt-3"><h3 class="h6">Prescrição Agronômica</h3><div class="row g-3"><div class="col-md-6"><div class="bg-white border rounded p-3"><h4 class="h6 mb-2">Calagem</h4><code>NC (t/ha) = CTC × (V alvo − V atual) / 100 × (100 / PRNT)</code></div></div><div class="col-md-6"><div class="bg-white border rounded p-3"><h4 class="h6 mb-2">Adubação</h4><code>Dose do nutriente (kg/ha) = (Demanda da cultura − Fornecimento do solo) / Eficiência de aproveitamento</code></div></div></div></section><?php if ($interpretation['alertas'] !== []): ?><div class="alert alert-warning mt-3 mb-0"><strong>Alertas:</strong> <?= e(implode(' ', $interpretation['alertas'])) ?></div><?php endif; ?></div>
    <p class="small text-secondary mt-4 mb-0">Referências gerais para triagem. A recomendação definitiva deve considerar cultura, produtividade esperada, textura, histórico de manejo, profundidade efetiva e tabelas regionais de interpretação.</p>
</div>
<?php renderFooter(); ?>
