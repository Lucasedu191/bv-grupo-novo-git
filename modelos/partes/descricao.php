<?php
$p = function_exists('wc_get_product') ? wc_get_product($a['produto_id']) : null;
if ($p){
  $desc = $p->get_description();
  if ($desc){
    echo '<div class="bvgn-descricao">'.wp_kses_post(wpautop($desc)).'</div>';
  }
}
