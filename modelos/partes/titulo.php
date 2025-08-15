<?php
$p = function_exists('wc_get_product') ? wc_get_product($a['produto_id']) : null;
if ($p){
  echo '<h2 class="bvgn-titulo">'.esc_html($p->get_name()).'</h2>';
}
