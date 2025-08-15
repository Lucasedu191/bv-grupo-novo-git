<?php
$p = function_exists('wc_get_product') ? wc_get_product($a['produto_id']) : null;
if ($p){
  $thumb_id = $p->get_image_id();
  if ($thumb_id){
    echo wp_get_attachment_image($thumb_id, 'large', false, ['class'=>'bvgn-imagem']);
  }
}
