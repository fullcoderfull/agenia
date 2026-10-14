<?php
// =============================================================================
// lib/llm-cost.php — AGENIA-COST v1
// Coût d'un échange LLM, toujours accompagné de sa provenance :
//   measured  : coût renvoyé par le fournisseur (OpenRouter)
//   estimated : tokens mesurés x grille config/llm-pricing.json (prix par million de tokens)
//   local     : modèle local, aucun coût d'API
//   unknown   : tokens non mesurés, ou modèle absent de la grille
// Aucun chiffre n'est inventé : sans mesure ni grille, le coût reste null.
// =============================================================================

function agenia_llm_pricing(): array
{
    static $grid = null;
    if ($grid !== null) { return $grid; }
    $f = dirname(__DIR__) . '/config/llm-pricing.json';
    $grid = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    return $grid;
}

function agenia_llm_cost(array $audit, $providerCost = null): array
{
    $in    = isset($audit['tokens_in'])  && is_numeric($audit['tokens_in'])  ? (int)$audit['tokens_in']  : null;
    $out   = isset($audit['tokens_out']) && is_numeric($audit['tokens_out']) ? (int)$audit['tokens_out'] : null;
    $grid  = agenia_llm_pricing();
    $res   = [
        'tokens_source' => ($in !== null && $out !== null) ? 'measured' : 'unknown',
        'cost'          => null,
        'currency'      => (string)($grid['currency'] ?? 'USD'),
        'cost_source'   => 'unknown',
        'price_date'    => null,
    ];

    if (!empty($audit['local'])) {
        $res['cost'] = 0.0;
        $res['cost_source'] = 'local';
        return $res;
    }
    if (is_numeric($providerCost)) {
        $res['cost'] = round((float)$providerCost, 6);
        $res['currency'] = 'USD';
        $res['cost_source'] = 'measured';
        return $res;
    }
    if ($in === null || $out === null) { return $res; }

    $models   = is_array($grid['models'] ?? null) ? $grid['models'] : [];
    $provider = strtolower((string)($audit['provider'] ?? ''));
    $model    = (string)($audit['model'] ?? '');
    $price    = $models[$provider . '/' . $model] ?? ($models[$model] ?? null);
    if (!is_array($price) || !isset($price['in'], $price['out'])) { return $res; }

    $res['cost'] = round($in * (float)$price['in'] / 1e6 + $out * (float)$price['out'] / 1e6, 6);
    $res['cost_source'] = 'estimated';
    $res['price_date'] = (string)($price['date'] ?? ($grid['updated'] ?? '')) ?: null;
    return $res;
}
