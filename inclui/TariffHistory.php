<?php
if (!defined('ABSPATH')) exit;
class BVGN_TariffHistory {
  const CRON_HOOK='bvgn_registrar_historico_diario_tarifas'; private static $pending=[];
  public static function init() {
    add_action('init',[__CLASS__,'ensure_schedule']); add_action(self::CRON_HOOK,[__CLASS__,'cron']);
    add_action('woocommerce_before_product_object_save',[__CLASS__,'before_save']); add_action('woocommerce_before_product_variation_object_save',[__CLASS__,'before_save']);
    foreach(['add_post_metadata','update_post_metadata','delete_post_metadata'] as $hook) add_filter($hook,[__CLASS__,'before_meta'],10,5);
    add_action('shutdown',[__CLASS__,'flush'],100); add_action('update_option_bvgn_dynamic_tariffs',[__CLASS__,'dynamic_changed'],10,3); add_action('add_option_bvgn_dynamic_tariffs',[__CLASS__,'dynamic_added'],10,2); add_action('deleted_option',[__CLASS__,'dynamic_deleted']);
  }
  public static function activate(){ BVGN_TariffHistoryRepository::install(); self::ensure_schedule(); self::cron(); }
  public static function deactivate(){ $ts=wp_next_scheduled(self::CRON_HOOK); if($ts) wp_unschedule_event($ts,self::CRON_HOOK); }
  public static function ensure_schedule(){ BVGN_TariffHistoryRepository::install(); if(wp_next_scheduled(self::CRON_HOOK)) return; $next=new DateTimeImmutable('tomorrow 00:10:00',wp_timezone()); wp_schedule_event($next->getTimestamp(),'daily',self::CRON_HOOK); }
  public static function cron(){
    $today=current_time('Y-m-d'); $last=get_option('bvgn_daily_tariff_history_last_date','');
    $start=preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$last)?(new DateTimeImmutable($last,wp_timezone()))->modify('+1 day'):new DateTimeImmutable($today,wp_timezone());
    $end=new DateTimeImmutable($today,wp_timezone());
    while($start <= $end) { self::record_date($start->format('Y-m-d')); update_option('bvgn_daily_tariff_history_last_date',$start->format('Y-m-d'),false); $start=$start->modify('+1 day'); }
  }
  public static function before_save($p){ self::watch($p->is_type('variation')?$p->get_parent_id():$p->get_id()); }
  public static function before_meta($check,$id,$key,$value,$extra){ if($check!==null||(!in_array($key,['_price','_regular_price','_sale_price'],true)&&strpos($key,'attribute_')!==0))return $check; if(get_post_type($id)==='product_variation')self::watch(wp_get_post_parent_id($id)); return $check; }
  private static function watch($id){ $id=absint($id); if($id&&self::rates($id)!==null)self::$pending[$id]=true; }
  public static function flush(){ foreach(array_keys(self::$pending) as $id)self::record_group($id,current_time('Y-m-d')); self::$pending=[]; }
  public static function dynamic_changed($old,$new,$option=''){ self::record_date(current_time('Y-m-d')); }
  public static function dynamic_added($option,$new){ self::record_date(current_time('Y-m-d')); }
  public static function dynamic_deleted($option){ if($option==='bvgn_dynamic_tariffs')self::record_date(current_time('Y-m-d')); }
  public static function groups(){ return get_posts(['post_type'=>'product','post_status'=>['publish','private'],'numberposts'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'product_cat','field'=>'slug','terms'=>['aluguel-de-carros-diaria','aluguel-de-carros-mensal']]]]); }
  public static function record_date($date){ foreach(self::groups() as $id)self::record_group($id,$date); }
  public static function record_group($id,$date){
    $rates=self::rates($id);
    if($rates===null)return false;
    $group=strtoupper((string)get_post_meta($id,'_bvgn_grupo',true));
    if($group===''&&preg_match('/Grupo\\s+([A-Z])/i',get_the_title($id),$m))$group=strtoupper($m[1]);
    // O mensal já contém o preço integral do plano, sem tarifa dinâmica.
    if(!has_term('aluguel-de-carros-mensal','product_cat',$id)) {
      $rule=class_exists('BVGN_DynamicTariffs')?BVGN_DynamicTariffs::rule_for_date($date,$group):null;
      foreach($rates as $field=>$rate){
        $days=$field==='valor_diaria'?1:(int)preg_replace('/\\D/','',$field);
        $rates[$field]=($rule?round($rate*(1+((float)$rule['percent']/100))):$rate)*$days;
      }
    }
    return BVGN_TariffHistoryRepository::upsert($id,$date,array_merge($rates,self::protection_rates($group),['taxa_lavagem'=>self::washing_rate($id)]));
  }
  public static function rates($id) {
    return has_term('aluguel-de-carros-mensal','product_cat',$id) ? self::monthly_rates($id) : self::daily_rates($id);
  }
  /** Reutiliza 3/7/15 dias para 1.000/3.000/5.000 km; a diária não se aplica. */
  public static function monthly_rates($id) {
    if(get_post_type($id)!=='product'||!has_term('aluguel-de-carros-mensal','product_cat',$id))return null;
    $fields=[1000=>'valor_3_dias',3000=>'valor_7_dias',5000=>'valor_15_dias'];
    $rates=['valor_diaria'=>null,'valor_3_dias'=>null,'valor_7_dias'=>null,'valor_15_dias'=>null];
    $product=wc_get_product($id);
    if(!$product||!$product->is_type('variable'))return null;
    // Mesma fonte de variações e preço vigente usada no formulário mensal.
    foreach($product->get_available_variations() as $raw) {
      $label=trim((string)($raw['attributes']['attribute_franquia-de-km'] ?? ''));
      if(!preg_match('/^(1[.]?000|3[.]?000|5[.]?000)\s*(?:km)?$/i',$label,$match))continue;
      $field=$fields[(int)str_replace('.','',$match[1])];
      $variation=wc_get_product($raw['variation_id'] ?? 0);
      $price=$variation?$variation->get_price():null;
      if($rates[$field]===null&&is_numeric($price))$rates[$field]=(float)$price;
    }
    return count(array_filter($rates,'is_numeric')) ? $rates : null;
  }
  /** Valores por diária do template taxa-variavel-diaria.php, sem caução. */
  public static function protection_rates($group) {
    $colors = ['A'=>'verde','B'=>'verde','C'=>'verde','D'=>'azul','E'=>'azul','F'=>'azul','G'=>'azul','H'=>'laranja','I'=>'azul'];
    $prices = [
      'verde'=>['protecao_basica'=>36.90,'protecao_premium'=>66.90],
      'azul'=>['protecao_basica'=>47.90,'protecao_premium'=>86.90],
      'laranja'=>['protecao_basica'=>68.90,'protecao_premium'=>128.90],
    ];
    return $prices[$colors[$group] ?? 'verde'];
  }
  /** Limpeza única; ausência da fonte/valor não significa gratuidade. */
  public static function washing_rate($id) {
    if (!is_callable(['BVGN_IntegracoesPT', 'obter_taxas_para_produto'])) return null;
    $fees = BVGN_IntegracoesPT::obter_taxas_para_produto($id);
    if (!is_array($fees)) return null;
    foreach ($fees as $fee) {
      if (!is_array($fee) || !is_string($fee['rotulo'] ?? null)) continue;
      if (stripos($fee['rotulo'], 'limpeza') !== false) {
        return is_numeric($fee['preco'] ?? null) ? (float)$fee['preco'] : null;
      }
    }
    return null;
  }
  public static function daily_rates($id){ if(get_post_type($id)!=='product'||!has_term('aluguel-de-carros-diaria','product_cat',$id)||has_term('aluguel-de-carros-mensal','product_cat',$id))return null; $wanted=['valor_diaria'=>1,'valor_3_dias'=>3,'valor_7_dias'=>7,'valor_15_dias'=>15];$rates=[];foreach(get_posts(['post_type'=>'product_variation','post_parent'=>$id,'post_status'=>'publish','numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','ID'=>'ASC'],'fields'=>'ids']) as $vid){$v=new WC_Product_Variation($vid);$range=self::range(wc_get_formatted_variation($v->get_variation_attributes(),true,false,false));$p=get_post_meta($vid,'_price',true);if(!is_numeric($p))continue;foreach($wanted as $field=>$days)if(!isset($rates[$field])&&$range[0]<=$days&&$range[1]>=$days)$rates[$field]=(float)$p;}return count($rates)===4?$rates:null; }
  public static function range($label){if(preg_match('~(\\d{1,2})[^\\d]+(\\d{1,2})~',$label,$m))return[(int)$m[1],(int)$m[2]];if(preg_match('~(\\d{1,2})\\s*dias?~i',$label,$m))return[(int)$m[1],(int)$m[1]===1?2:(int)$m[1]];return[1,30];}
}
BVGN_TariffHistory::init();
