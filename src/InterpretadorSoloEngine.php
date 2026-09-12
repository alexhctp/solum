<?php

declare(strict_types=1);

/**
 * Calcula indicadores de fertilidade e gera um parecer técnico inicial.
 *
 * As recomendações são uma triagem técnica baseada nos dados informados e
 * devem ser revisadas por um profissional habilitado antes da aplicação.
 */
final class InterpretadorSoloEngine
{
    private const K_MGDM3_POR_CMOLC = 390.0;

    private float $prnt;
    private float $vAlvo;
    private float $caMgMin;
    private float $caMgMax;
    private float $kElevadoMgdm3;
    private float $ctcBaixaCmolc;

    public function __construct(
        float $prnt = 80.0,
        float $vAlvo = 60.0,
        float $caMgMin = 3.0,
        float $caMgMax = 4.0,
        float $kElevadoMgdm3 = 120.0,
        float $ctcBaixaCmolc = 7.0
    ) {
        if ($prnt <= 0 || $prnt > 100) {
            throw new InvalidArgumentException('O PRNT deve estar entre 0 e 100.');
        }
        if ($vAlvo < 0 || $vAlvo > 100) {
            throw new InvalidArgumentException('O V% alvo deve estar entre 0 e 100.');
        }
        if ($caMgMin <= 0 || $caMgMax < $caMgMin) {
            throw new InvalidArgumentException('A faixa Ca:Mg informada e invalida.');
        }
        if ($kElevadoMgdm3 < 0 || $ctcBaixaCmolc < 0) {
            throw new InvalidArgumentException('Os limiares agronomicos nao podem ser negativos.');
        }

        $this->prnt = $prnt;
        $this->vAlvo = $vAlvo;
        $this->caMgMin = $caMgMin;
        $this->caMgMax = $caMgMax;
        $this->kElevadoMgdm3 = $kElevadoMgdm3;
        $this->ctcBaixaCmolc = $ctcBaixaCmolc;
    }

    /**
     * @param array<string, mixed> $analise Registro de analises_solo ou DTO equivalente.
     * @return array<string, mixed>
     */
    public function interpretar(array $analise): array
    {
        $ca = $this->positiveNumber($analise, 'ca_cmolc');
        $mg = $this->positiveNumber($analise, 'mg_cmolc');
        $hAl = $this->positiveNumber($analise, 'h_al_cmolc');
        $kMgdm3 = $this->number($analise, 'k_mgdm3', false);
        $kCmolc = array_key_exists('k_cmolc', $analise)
            ? $this->positiveNumber($analise, 'k_cmolc')
            : $kMgdm3 / self::K_MGDM3_POR_CMOLC;
        if (!array_key_exists('k_mgdm3', $analise) && array_key_exists('k_cmolc', $analise)) {
            $kMgdm3 = $kCmolc * self::K_MGDM3_POR_CMOLC;
        }

        $sb = $ca + $mg + $kCmolc;
        $ctc = $sb + $hAl;
        $vAtual = $ctc > 0 ? ($sb / $ctc) * 100 : 0.0;
        $nc = max(0.0, ($ctc * ($this->vAlvo - $vAtual) / 100) * (100 / $this->prnt));
        $caMg = $mg > 0 ? $ca / $mg : null;

        $alertas = [];
        if ($caMg === null) {
            $alertas[] = 'Nao foi possivel calcular a relacao Ca:Mg porque Mg e igual a zero.';
        } elseif ($caMg < $this->caMgMin || $caMg > $this->caMgMax) {
            $alertas[] = sprintf(
                'Relacao Ca:Mg de %.2f:1 fora da faixa ideal de %.1f:1 a %.1f:1.',
                $caMg,
                $this->caMgMin,
                $this->caMgMax
            );
        }

        $parcelarK = $ctc < $this->ctcBaixaCmolc;
        if ($kMgdm3 >= $this->kElevadoMgdm3 && $mg > 0) {
            $alertas[] = sprintf(
                'K elevado (%.2f mg/dm3): pode inibir a absorcao de Mg; evite aplicacoes excessivas e monitore o equilibrio.',
                $kMgdm3
            );
        }
        if ($parcelarK) {
            $alertas[] = sprintf(
                'CTC baixa (%.2f cmolc/dm3): avaliar parcelamento das aplicacoes de K para reduzir perdas e antagonismos.',
                $ctc
            );
        }
        if ($vAtual >= $this->vAlvo) {
            $parecerBase = sprintf(
                'V%% atual de %.2f%% ja atende ou supera o V%% alvo de %.2f%%; nao ha necessidade de calagem pelo metodo da saturacao por bases.',
                $vAtual,
                $this->vAlvo
            );
        } else {
            $parecerBase = sprintf(
                'V%% atual de %.2f%% abaixo do V%% alvo de %.2f%%; necessidade de calagem estimada em %.3f t/ha, considerando PRNT de %.1f%%.',
                $vAtual,
                $this->vAlvo,
                $nc,
                $this->prnt
            );
        }

        $parecer = $parecerBase;
        if ($alertas !== []) {
            $parecer .= ' Alertas: ' . implode(' ', $alertas);
        }

        return [
            'k_cmolc' => round($kCmolc, 4),
            'sb_cmolc' => round($sb, 4),
            'ctc_pH7_cmolc' => round($ctc, 4),
            'v_percent_atual' => round($vAtual, 2),
            'v_percent_alvo' => round($this->vAlvo, 2),
            'nc_ton_ha' => round($nc, 3),
            'relacao_ca_mg' => $caMg !== null ? round($caMg, 3) : null,
            'k_elevado' => $kMgdm3 >= $this->kElevadoMgdm3,
            'ctc_baixa' => $parcelarK,
            'parcelar_k' => $parcelarK,
            'alertas' => $alertas,
            'parecer_tecnico' => $parecer,
        ];
    }

    /**
     * @param array<string, mixed> $analise
     */
    private function positiveNumber(array $analise, string $field): float
    {
        $value = $this->number($analise, $field);
        if ($value < 0) {
            throw new InvalidArgumentException("O campo {$field} nao pode ser negativo.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $analise
     */
    private function number(array $analise, string $field, bool $required = true): float
    {
        if (!array_key_exists($field, $analise) || $analise[$field] === '') {
            if (!$required) {
                return 0.0;
            }
            throw new InvalidArgumentException("O campo {$field} e obrigatorio.");
        }

        if (!is_int($analise[$field]) && !is_float($analise[$field]) && !is_string($analise[$field])) {
            throw new InvalidArgumentException("O campo {$field} deve ser numerico.");
        }

        $normalized = str_replace(',', '.', (string) $analise[$field]);
        if (!is_numeric($normalized) || !is_finite((float) $normalized)) {
            throw new InvalidArgumentException("O campo {$field} deve ser numerico.");
        }

        return (float) $normalized;
    }
}