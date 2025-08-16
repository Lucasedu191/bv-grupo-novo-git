<?php
$formato = in_array($a['format'] ?? '',['html','pdf']) ? $a['format'] : 'html';
$tel  = preg_replace('/\D+/','', $a['phone'] ?? '');
?>
<button type="button"
        class="<?php echo esc_attr($classe); ?> js-bvgn-open-modal"
        data-bvgn-modal="bvgn-cotacao-modal">
  <?php echo esc_html($rotulo); ?>
</button>
