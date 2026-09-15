<?php
if (!defined('ABSPATH')) exit;
class BVGN_TariffHistoryAdmin {
  const SLUG='bvgn-historico-tarifas';
  public static function init(){ add_action('admin_menu',[__CLASS__,'menu']); }
  public static function menu(){ add_submenu_page('edit.php?post_type=bvgn_cotacao','Histórico de Tarifas','Histórico de Tarifas','manage_options',self::SLUG,[__CLASS__,'render']); }
  private static function input($key){ return isset($_GET[$key])&&is_string($_GET[$key])?sanitize_text_field(wp_unslash($_GET[$key])):''; }
  public static function date($v){ if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$v))return null; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,wp_timezone());return $d&&$d->format('Y-m-d')===$v?$d:null; }
  private static function label($id){$title=get_the_title($id);return ($title!==''?$title:'Grupo removido').' (#'.absint($id).')';}
  public static function render(){
    if(!current_user_can('manage_options'))wp_die('Sem permissão.'); BVGN_TariffHistoryRepository::install();
    $group=absint(self::input('grupo_id')); $startText=self::input('data_inicial'); $endText=self::input('data_final'); $start=self::date($startText);$end=self::date($endText);
    $invalid=($startText!==''&&!$start)||($endText!==''&&!$end)||($start&&$end&&$start>$end);
    $result=$invalid?['rows'=>[],'total'=>0,'page'=>1]:BVGN_TariffHistoryRepository::search($group,$start?$start->format('Y-m-d'):'',$end?$end->format('Y-m-d'):'',max(1,absint(self::input('pagina'))));
    $base=admin_url('edit.php?post_type=bvgn_cotacao&page='.self::SLUG);$groups=BVGN_TariffHistoryRepository::groups(); if($group&&!in_array((string)$group,array_map('strval',$groups),true))$groups[]=$group;
    ?><div class="wrap"><h1>Histórico de Tarifas</h1><p>Valor da diária vigente por grupo e data, no fuso horário configurado no WordPress.</p>
    <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>"><input type="hidden" name="post_type" value="bvgn_cotacao"><input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
    <label>Grupo <select name="grupo_id"><option value="0">Todos os grupos</option><?php foreach($groups as $id): ?><option value="<?php echo esc_attr($id); ?>" <?php selected($group,$id); ?>><?php echo esc_html(self::label($id)); ?></option><?php endforeach; ?></select></label>
    <label>Data inicial <input type="date" name="data_inicial" value="<?php echo esc_attr($startText); ?>"></label><label>Data final <input type="date" name="data_final" value="<?php echo esc_attr($endText); ?>"></label><button class="button button-primary">Filtrar</button> <a class="button" href="<?php echo esc_url($base); ?>">Limpar filtros</a></form>
    <?php if($invalid): ?><div class="notice notice-error inline"><p>Informe datas válidas, com a inicial anterior ou igual à final.</p></div><?php endif; ?>
    <p><?php echo esc_html($result['total']); ?> registro(s).</p><table class="widefat striped"><thead><tr><th>Data</th><th>Grupo</th><th>1 diária</th><th>3 dias</th><th>7 dias</th><th>15 dias</th></tr></thead><tbody><?php if(!$result['rows']): ?><tr><td colspan="6">Nenhum histórico encontrado para os filtros informados.</td></tr><?php endif; foreach($result['rows'] as $row): ?><tr><td><?php echo esc_html(DateTimeImmutable::createFromFormat('!Y-m-d',$row['data_referencia'])->format('d/m/Y')); ?></td><td><?php echo esc_html(self::label($row['grupo_id'])); ?></td><?php foreach(['valor_diaria','valor_3_dias','valor_7_dias','valor_15_dias'] as $field): ?><td><?php echo esc_html($row[$field]===null?'Indisponível':'R$ '.number_format((float)$row[$field],2,',','.')); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
    <?php $pages=(int)ceil($result['total']/50);if($pages>1){$url=add_query_arg(['grupo_id'=>$group,'data_inicial'=>$startText,'data_final'=>$endText],$base);echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(['base'=>add_query_arg('pagina','%#%',$url),'format'=>'','current'=>$result['page'],'total'=>$pages])).'</div></div>';} ?></div><?php
  }
}
BVGN_TariffHistoryAdmin::init();
