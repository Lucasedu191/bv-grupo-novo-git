<?php
/**
 * Template parcial: Variacoes (ajustado p/ plano mensal via atributo 'franquia-de-km')
 * Atributo detectado a partir do dump: [attribute_franquia-de-km] => "1.000 km" / "3.000 km" / "5.000 km"
 * Portanto, NÃO é taxonomia global (sem 'pa_') e o valor já vem legível.
 */

if (!defined('ABSPATH')) { exit; }

// ==== Contexto mínimo/fallback ====
$a    = isset($a) && is_array($a) ? $a : [];
$tipo = isset($a['type']) ? $a['type'] : 'diario';

/** @var WC_Product|false $p */
if (!isset($p) || !$p instanceof WC_Product) {
  $pid = isset($a['product_id']) ? intval($a['product_id']) : get_the_ID();
  $p   = $pid ? wc_get_product($pid) : false;
}

// Fallback simples caso sua função já exista noutro arquivo
if (!function_exists('bvgn_min_max_by_label')){
  function bvgn_min_max_by_label($rotulo, $tipo){
    if ($tipo === 'mensal') return [30, 30];

    // Extrai dois números, ex: "03–06 Dias", "07 a 14 Dias"
    if (preg_match('~(\d{1,2})[^\d]+(\d{1,2})~', $rotulo, $m)) {
      return [intval($m[1]), intval($m[2])];
    }

    // Um único número, ex: "1 Dia"
    if (preg_match('~(\d{1,2})\s*dias?~i', $rotulo, $m)){
      $n = max(1, intval($m[1]));
      return [$n, $n];
    }

    // Fallback
    return [1, 30];
  }
}

// ==== Ajustes específicos ====
// Com base no dump: a chave veio como 'attribute_franquia-de-km' (sem 'pa_')
$ATTR_TAX  = 'franquia-de-km';
$ATTR_KEY  = 'attribute_' . $ATTR_TAX;
$lista     = [];

if ($p && $p->is_type('variable')){
  $available = $p->get_available_variations();

  foreach ($available as $raw){
    $variation_id = isset($raw['variation_id']) ? $raw['variation_id'] : ( $raw['id'] ?? 0 );
    $attrs        = isset($raw['attributes']) ? $raw['attributes'] : [];
    $wcvar        = ($variation_id ? wc_get_product($variation_id) : null);
    $preco        = ($wcvar && method_exists($wcvar,'get_price')) ? (float) $wcvar->get_price() : 0.0;

    // === NOVO: pegar descrição da variação (texto puro, preservando quebras) ===
    $desc = '';
    if ($wcvar && method_exists($wcvar, 'get_description')) {
      $desc = sanitize_textarea_field( $wcvar->get_description() );
    }

    if ($tipo === 'mensal'){
      // no mensal, exigimos o atributo "franquia-de-km" (valor já legível, ex.: "1.000 km")
      $franquia_valor = isset($attrs[$ATTR_KEY]) ? trim((string)$attrs[$ATTR_KEY]) : '';
      if ($franquia_valor === '') continue;

      $rotulo  = sprintf(__('Franquia: %s', 'bvgn'), $franquia_valor);
      $min_max = [30, 30]; // mensal fixo (ajuste se necessário)

    } else {
      // diário mantém sua heurística por rótulo
      $slug = $attrs['attribute_pa_por-dia'] ?? '';
      $rotulo = ucwords(str_replace('-', ' ', $slug));
      if (stripos($rotulo, 'dia') === false) continue;
      $min_max = bvgn_min_max_by_label($rotulo, 'diario');
    }

    $lista[] = [
      'id'       => $variation_id,
      'preco'    => $preco,
      'rotulo'   => wp_strip_all_tags($rotulo),
      'min_days' => $min_max[0],
      'max_days' => $min_max[1],
      'desc'     => $desc, // <-- NOVO: descrição da variação
    ];
  }
}

// ==== Saída ====
// Observação: Ajuste as classes/estrutura abaixo para casar com seu CSS/JS atual.
// Mantemos data-bvgn-tipo e data-* nas opções para o JS calcular corretamente.
?>
<div class="bvgn-variacoes" data-bvgn-tipo="<?php echo esc_attr($tipo); ?>">
  <?php if (empty($lista)): ?>
    <div class="bvgn-variacao-vazia">
      <?php if ($tipo === 'mensal'): ?>
        <em><?php esc_html_e('Nenhuma variação mensal encontrada (verifique o atributo de franquia de km).', 'bvgn'); ?></em>
      <?php else: ?>
        <em><?php esc_html_e('Nenhuma variação disponível.', 'bvgn'); ?></em>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="bvgn-variacoes-opcoes">
      <?php foreach ($lista as $i => $v): 
        $input_id = 'bvgn_var_' . esc_attr($tipo) . '_' . esc_attr($v['id']);
      ?>
        <label class="bvgn-variacao" for="<?php echo esc_attr($input_id); ?>">
          <input
            type="radio"
            name="bvgn_variacao"
            id="<?php echo esc_attr($input_id); ?>"
            value="<?php echo esc_attr($v['id']); ?>"
            data-preco="<?php echo esc_attr($v['preco']); ?>"
            data-min-days="<?php echo esc_attr($v['min_days']); ?>"
            data-max-days="<?php echo esc_attr($v['max_days']); ?>"
            data-desc="<?php echo esc_attr($v['desc']); ?>"
            data-rotulo="<?php echo esc_attr($v['rotulo']); ?>"
            <?php checked($i === 0); ?>
          />
          <span class="lbl"><?php echo esc_html($v['rotulo']); ?></span>
          <?php if ($v['preco'] > 0): ?>
            <span class="preco"><?php echo wc_price($v['preco']); ?></span>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="bvgn-variacao-desc" aria-live="polite" style="margin-top:8px;display:none;">
      <em class="bvgn-variacao-desc-text"></em>
    </div>
  <?php endif; ?>
</div>
