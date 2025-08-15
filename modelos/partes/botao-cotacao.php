<?php
$formato = in_array($a['format'] ?? '',['html','pdf']) ? $a['format'] : 'html';
$tel  = preg_replace('/\D+/','', $a['phone'] ?? '');
?>
<button class="bvgn-botao-cotacao" data-formato="<?php echo esc_attr($formato); ?>" data-telefone="<?php echo esc_attr($tel); ?>">
  Gerar cotacao e abrir WhatsApp
</button>
