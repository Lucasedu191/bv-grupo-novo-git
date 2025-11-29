<?php
$cssPath = BVGN_DIR . 'assets/css/cotacao.css';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$data = date('d/m/Y');
// Usa código recebido do endpoint; se não vier, gera um novo
if (!isset($codigo) || $codigo === '') {
  $codigo = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
}


$fmt = function($str){
  if (empty($str)) return '—';
  $ts = strtotime(str_replace('/', '-', $str));
  return $ts ? date('d/m/Y', $ts) : $str;
};



// Limpa o texto do rótulo para exibição
$limpaRotulo = function($txt){
  $orig = (string)$txt;

  // remove "(...)", "Selecionado/Selecionar", ", caução de ..."
  $txt = preg_replace('/\s*\([^)]*\)/u', '', $orig);
  // remove "Selecionar", "Selecionado", "Selecionada" (com variações de acento/caixa)
  $txt = preg_replace('/\bSelecion(?:ar|ado|ada)\b/iu', '', $txt);
  $txt = preg_replace('/,\s*cau[cç][aã]o[^—]*/iu', '', $txt);

  // normaliza espaços
  $txt = preg_replace('/\s{2,}/', ' ', trim($txt));

  // se esvaziou mas era um rótulo de caução / sem proteção, cria nome padrão
  if ($txt === '' && preg_match('/cau[cç][aã]o/i', $orig)) $txt = 'Caução';
  if ($txt === '' && preg_match('/sem\s+prote[cç][aã]o/i', $orig)) $txt = 'Sem proteção';

  return $txt;
};

// Calcula o preço exibido (multiplica quando for diária)
// "2.000,00" -> 2000.00
$toFloatBR = function($moeda){
  $s = preg_replace('/[^\d,\.]/', '', (string)$moeda);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  return (float)$s;
};

// Calcula preço exibido: diária × qtd; e injeta caução quando necessário
$precoExibicao = function($t, $dados) use ($toFloatBR){
  $p    = (float)($t['preco'] ?? 0);
  $rot  = (string)($t['rotulo'] ?? '');
  $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
  $qtd  = max(1, (int)($dados['totais']['qtd'] ?? 1));
  $tipoItem = strtolower($t['tipo'] ?? '');

  if ($tipoItem === 'caucao_obrigatorio') {
    return $p;
  }

  // A) Se for "Sem proteção" e preço veio 0, tentar achar o valor do caução
  if ($p == 0 && preg_match('/sem\s+prote[cç][aã]o/i', $rot)) {
    // 1) no próprio rótulo
    if (preg_match('/cau[cç][aã]o[^0-9]*([\d\.,]+)/iu', $rot, $m)) {
      $p = $toFloatBR($m[1]);
    }
    // 2) em algum outro item com "caução"
    if ($p == 0) {
      foreach (($dados['taxas'] ?? []) as $tt) {
        $r = (string)($tt['rotulo'] ?? '');
        if (preg_match('/cau[cç][aã]o/i', $r)) {
          if ((float)($tt['preco'] ?? 0) > 0) { $p = (float)$tt['preco']; break; }
          if (preg_match('/([\d\.,]+)/', $r, $mm)) { $p = $toFloatBR($mm[1]); break; }
        }
      }
    }
  }

  // B) Se for um item "Caução ..." e preço 0, extrai do texto
  if ($p == 0 && preg_match('/cau[cç][aã]o/i', $rot)) {
    if (preg_match('/([\d\.,]+)/', $rot, $m)) $p = $toFloatBR($m[1]);
  }

  // C) Itens diários multiplicam; proteção também multiplica mesmo sem "diária" no rótulo
  if (preg_match('/di[áa]ria/i', $rot) || preg_match('/prote[cç][aã]o/i', $rot)) {
    if ($tipo === 'diario')      $p *= $qtd;
    elseif ($tipo === 'mensal')  $p *= 30;
  }
  
  // Extra: opcionais específicos (Condutor adicional, Cadeirinha) multiplicam pelos dias apenas no plano diário
  

  return $p;
};

$retirada  = $fmt($dados['datas']['inicio'] ?? '');
$devolucao = $fmt($dados['datas']['fim'] ?? '');
$localRetirada = $dados['local'] ?? '—';
$validade = date('d/m/Y', strtotime('+5 days'));
$mensagem = trim($dados['mensagem'] ?? '');

// Tipo principal da cotação
$tipoMain = strtolower($dados['totais']['tipo'] ?? 'diario');

// Para plano mensal, a devolução é exibida como "30 dias"
if ($tipoMain === 'mensal') {
  $devolucao = '30 dias';
}


// Quebra as taxas em grupos para exibir no "Detalhes"
$taxasAll   = $dados['taxas'] ?? [];
$caucaoObrigatorioItem = null;
$taxasFiltradas = [];
foreach ($taxasAll as $t) {
  $tipoLinha   = strtolower($t['tipo'] ?? '');
  $rotuloLinha = strtolower($t['rotulo'] ?? '');

  if ($tipoLinha === 'caucao_obrigatorio') {
    if ($caucaoObrigatorioItem === null) {
      $caucaoObrigatorioItem = $t;
    }
    continue;
  }

  $taxasFiltradas[] = $t;
}
$taxasAll = $taxasFiltradas;
$dados['taxas'] = $taxasAll;
$taxasFixas = array_values(array_filter($taxasAll, fn($t) => preg_match('/taxa|limpeza/i', $t['rotulo'] ?? '')));
$opcionais  = array_values(array_filter($taxasAll, fn($t) =>
  !preg_match('/prote[cç][aã]o|taxa|limpeza/i', $t['rotulo'] ?? '')
));

if ($caucaoObrigatorioItem) {
  array_unshift($opcionais, $caucaoObrigatorioItem);
}

$logoUrl = 'https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png'; // topo
$wmUrl   = $logoUrl; // marca d’água central
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <style><?= $css ?></style>
</head>
<body class="pdf-cotacao">

<!-- Marca d’água full-page centralizada -->
<div class="marca-dagua">
  <img src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png" alt="">
</div>


<!-- Cabeçalho timbrado sem título (título vai abaixo) -->
<header class="cotacao-cabecalho" style="margin-top:6mm;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
    <tr valign="middle">
      <!-- LOGO -->
      <td width="88" style="padding:0 8px 0 0;">
        <img src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png"
             alt="BV Locadora" style="display:block;height:58px;width:auto;">
      </td>

      <!-- ESPAÇADOR: empurra o texto para a direita (ajuste aqui se quiser mais) -->
      <td width="28" style="padding:0;"></td>

      <!-- INFOS (alinhadas à ESQUERDA) -->
      <td style="padding:0; font-size:10.5px; line-height:1.28; color:#222; text-align:left;">
        <strong>A. F. COMERCIO E SERVICOS LTDA</strong><br>
        CNPJ: 31.315.055/0001-14<br>
        R. Cel. Mota, Centro, 629 — Boa Vista/RR — CEP: 69301-120<br>
        TEL: (95) 98102-2395 — E-mail: bvlocadora@outlook.com
      </td>
    </tr>
  </table>
</header>

<h1 class="titulo-cotacao">
  COTAÇÃO <span>DE SERVIÇO</span>
</h1>

<!-- Conteúdo -->


<main class="cotacao-container">

  <section class="cv-cards">
  <table class="cards-2" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
    <tr>
      <!-- Coluna 1: Cliente -->
      <td class="col" style="width:50%; padding-right:3mm; vertical-align:top;">
        <div class="card">
          <h3>Cliente</h3>
          <dl class="kv">
            <div><dt>Nome</dt><dd><?= esc_html($dados['nome'] ?? '—') ?></dd></div>
            <div><dt>WhatsApp</dt><dd><?= esc_html($dados['whats'] ?? '—') ?></dd></div>
            <?php if ($mensagem !== ''): ?>
              <div><dt>Mensagem</dt><dd><?= nl2br(esc_html($mensagem)); ?></dd></div>
            <?php endif; ?>
          </dl>
        </div>
      </td>

      <!-- Coluna 2: Cotação -->
      <td class="col" style="width:50%; padding-left:3mm; vertical-align:top;">
        <div class="card">
          <h3>Cotação Nº <?= $codigo ?> </h3>
          <dl class="kv">
            <div><dt>Data</dt><dd><?= $data ?></dd></div>
            <div><dt>Validade</dt><dd><?= $validade ?></dd></div>
          </dl>
        </div>
      </td>
    </tr>
  </table>
</section>

  
  

<section class="cv-blocos">
  <table class="cards-2" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">

    <!-- Linha 1: DETALHES (card largura total) -->
    <tr>
      <td class="col" colspan="2" style="vertical-align:top; padding:0;">
        <div class="card card--ghost">
          <h3>Detalhes</h3>

          <!-- grade 4 colunas dentro do card -->
          <table class="kv3 kv4" role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
              <!-- Coluna 1: Plano -->
              <td>
                <dl class="kv kv--stack">
                  <?php
                    $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
                    $qtd  = max(1, intval($dados['totais']['qtd'] ?? 1));
                    $labelPlano = ($tipo === 'mensal') ? 'Mensal (30 dias)' : ($qtd > 1 ? "Diárias ($qtd dias)" : "Diária");
                  ?>
                  <div><dt>Plano</dt><dd><?= esc_html($labelPlano) ?></dd></div>
                </dl>
              </td>

              <!-- Coluna 2: Grupo (nome do produto) -->
              <td>
                <?php
                  $produtoNome = '';
                  if (isset($produto) && is_object($produto) && method_exists($produto, 'get_name')) {
                    $produtoNome = (string) $produto->get_name();
                  } elseif (!empty($dados['variacaoRotulo'])) {
                    // fallback: usa rótulo da variação se existir
                    $produtoNome = (string) $dados['variacaoRotulo'];
                  }
                ?>
                <dl class="kv kv--stack">
                  <div><dt>Grupo</dt><dd><?= esc_html($produtoNome ?: '—') ?></dd></div>
                </dl>
              </td>

              <!-- Coluna 3: Local de retirada -->
              <td>
                <dl class="kv kv--stack">
                  <div><dt>Local de retirada</dt><dd><?= esc_html($localRetirada) ?></dd></div>
                </dl>
              </td>

              <!-- Coluna 4: Retirada e Devolução -->
              <td>
                <dl class="kv kv--stack">
                  <div><dt>Retirada</dt><dd><?= $retirada ?></dd></div>
                  <div><dt>Devolução</dt><dd><?= $devolucao ?></dd></div>
                </dl>
              </td>
            </tr>
          </table>

          <div class="alerta">
            <div><strong>Atenção:</strong> o período informado é uma pré-reserva. A confirmação será feita após a verificação de disponibilidade do veículo pela equipe BV Locadora.</div>
            <?php if ($tipoMain === 'mensal'): ?>
              <div style="margin-top: 3px;"><em>
                Sua pré-reserva tem 30 dias ou mais. Conte com toda a economia, autonomia e flexibilidade do Aluguel Mensal.
              </em></div>
            <?php endif; ?>
          </div>
        </div>
      </td>
    </tr>

    <?php
      // detectar tipo
      $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
      $qtdDias = max(1, intval($dados['totais']['qtd'] ?? 1));

      // localizar itens especiais nas taxas enviadas
      $protItem   = null;
      $caucaoItem = null;
      foreach (($taxasAll ?? []) as $t) {
        $r = (string)($t['rotulo'] ?? '');
        if (!$protItem   && preg_match('/prote[cç][aã]o/i', $r)) $protItem   = $t;
        if (!$caucaoItem && preg_match('/cau[cç][aã]o/i', $r))   $caucaoItem = $t;
        if ($protItem && $caucaoItem) break;
      }

      // rótulo/valor da proteção
      if ($tipo === 'mensal') {
        $protLabel = 'Proteção básica';
        $protValor = null; // incluída
      } else {
        if ($protItem) {
          $protLabel = $limpaRotulo($protItem['rotulo'] ?? 'Proteção');
          $protValor = $precoExibicao($protItem, $dados);
        } else {
          $protLabel = 'Proteção';
          $protValor = null;
        }
      }
    ?>

    <!-- Linha 2: SERVIÇOS OPCIONAIS | TAXAS -->
    <tr>
      <!-- Coluna 1: Serviços opcionais + Proteção -->
      <td class="col" style="width:50%; padding-right:3mm; vertical-align:top;">
        <div class="card card--ghost">
          <h3><?= $tipo === 'mensal' ? 'Taxas e serviços opcionais' : 'Serviços opcionais' ?></h3>

          <?php if ($tipo === 'mensal'): ?>
            <!-- Proteção (apenas no mensal) -->
            <div class="kv" style="margin-bottom: 2mm;">
              <div>
                <dt>Proteção</dt>
                <dd>
                  <?= esc_html($protLabel) ?>
                  <?php if ($protValor !== null): ?>
                    — R$ <?= number_format($protValor, 2, ',', '.') ?>
                  <?php else: ?>
                    — incluída
                  <?php endif; ?>
                </dd>
              </div>
            </div>
          <?php endif; ?>

          <!-- (Mensal) Mostrar Caução destacado -->
          <?php if ($tipo === 'mensal' && $caucaoItem): ?>
            <ul class="lista" style="margin-top:2mm">
              <li>
                <?= esc_html($limpaRotulo($caucaoItem['rotulo'] ?? 'Caução')) ?> —
                R$ <?= number_format($precoExibicao($caucaoItem, $dados), 2, ',', '.') ?>
              </li>
            </ul>
          <?php endif; ?>

          <!-- Lista de opcionais (limpa rótulo e calcula preço; evita duplicar proteção/caução) -->
          <?php if (!empty($opcionais)): ?>
            <ul class="lista">
              <?php foreach ($opcionais as $t): ?>
                <?php
                  $rOrig = (string)($t['rotulo'] ?? '');
                  $tipoItem = strtolower($t['tipo'] ?? '');
                  // pular proteção sempre (já tratamos acima)
                  if (preg_match('/prote[cç][aã]o/i', $rOrig)) continue;
                  // no mensal, pular caução da lista (já mostramos destacado acima)
                  if ($tipo === 'mensal' && preg_match('/cau[cç][aã]o/i', $rOrig)) continue;
                  $rClean = ($tipoItem === 'caucao_obrigatorio')
                    ? trim($rOrig)
                    : $limpaRotulo($rOrig);
                  if ($rClean === '') continue;
                ?>
                <li>
                  <?= esc_html($rClean) ?> —
                  R$ <?= number_format($precoExibicao($t, $dados), 2, ',', '.') ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>—</p>
          <?php endif; ?>
        </div>
      </td>

      <!-- Coluna 2: Taxas -->
      <td class="col" style="width:50%; padding-left:3mm; vertical-align:top;">
        <div class="card card--ghost">
          <h3><?= $tipo === 'mensal' ? 'Taxas obrigatórias' : 'Taxas' ?></h3>

          <?php if (!empty($taxasFixas)): ?>
            <ul class="lista">
              <?php foreach ($taxasFixas as $t): ?>
                <li>
                  <?= esc_html($limpaRotulo(($t['rotulo'] ?? ''))) ?> —
                  R$ <?= number_format($precoExibicao($t, $dados), 2, ',', '.') ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>—</p>
          <?php endif; ?>
        </div>
      </td>
    </tr>

  </table>
</section>



  <section class="cotacao-valores">
    <h2>Valores</h2>
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Valor</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
          $qtd  = max(1, intval($dados['totais']['qtd'] ?? 1));
          $base = (float)($dados['totais']['base'] ?? 0);
          $dynamicExtra    = (float)($dados['totais']['dynamic_extra'] ?? 0);
          $dynamicDetalhes = is_array($dados['totais']['dynamic_detalhes'] ?? null) ? $dados['totais']['dynamic_detalhes'] : [];
          $dynamicDetalhesPdf = array_values(array_filter($dynamicDetalhes, fn($d) => !empty($d['show_pdf'])));
          $dynamicExtraVis = 0.0;
          foreach ($dynamicDetalhesPdf as $d) {
            $dynamicExtraVis += (float)($d['valor'] ?? 0);
          }
          if ($tipo === 'diario') {
            $unitComDyn = $base;
            if ($dynamicExtraVis > 0 && $qtd > 0) {
              $unitComDyn += ($dynamicExtraVis / $qtd);
            }
            $labelPlano = sprintf(
              'Diárias (%d × R$ %s%s)',
              $qtd,
              number_format($base, 2, ',', '.'),
              $dynamicExtraVis > 0 ? ' → R$ ' . number_format($unitComDyn, 2, ',', '.') : ''
            );
            $valorPlano = $base * $qtd;
          } else {
            // mantemos apresentação atual para mensal
            $labelPlano = 'Mensal (30 dias)';
            $valorPlano = $base; // exibição do valor base mensal
          }
        ?>
        <tr>
          <td><?= esc_html($labelPlano) ?></td>
          <td>R$ <?= number_format($valorPlano, 2, ',', '.') ?></td>
        </tr>

        <?php if ($dynamicExtraVis > 0.0): ?>
          <?php
            $detResumo = '';
            if (!empty($dynamicDetalhesPdf)) {
              $uniq = [];
              $rotulos = [];
              foreach ($dynamicDetalhesPdf as $d) {
                $perc = isset($d['percent']) ? floatval($d['percent']) : 0;
                $desc = isset($d['desc']) ? trim((string)$d['desc']) : '';
                $rot  = isset($d['rotulo']) ? trim((string)$d['rotulo']) : '';
                $key = strtolower(preg_replace('/\s+/', ' ', $rot)) . '|' .
                       strtolower(preg_replace('/\s+/', ' ', $desc)) . '|' .
                       number_format($perc, 4, '.', '');
                if (isset($uniq[$key])) continue;
                $uniq[$key] = true;
                $partes = [];
                if ($rot !== '') $partes[] = $rot;
                if ($desc !== '') $partes[] = $desc;
                $temPercentTxt = (stripos($rot, '%') !== false) || (stripos($desc, '%') !== false);
                if ($perc !== 0.0 && !$temPercentTxt) {
                  $partes[] = '+' . $perc . '%';
                }
                $txt = implode(' — ', $partes);
                $txt = trim($txt);
                if ($txt !== '') $rotulos[] = $txt;
              }
              $detResumo = implode(' | ', array_slice($rotulos, 0, 3));
              if (count($rotulos) > 3) $detResumo .= ' ...';
            }
          ?>
          <tr>
            <td>Tarifa dinâmica<?= $detResumo ? ' (' . esc_html($detResumo) . ')' : '' ?></td>
            <td>R$ <?= number_format($dynamicExtraVis, 2, ',', '.') ?></td>
          </tr>
        <?php endif; ?>

        <?php foreach (($dados['taxas'] ?? []) as $t): ?>
          <?php
            // No plano mensal, não listar linha de proteção na tabela (já mostramos "incluída" no card)
            $rot = (string)($t['rotulo'] ?? '');
            if ($tipo === 'mensal' && preg_match('/prote[cç][aã]o/i', $rot)) {
              continue;
            }
            $rotuloLimpo = $limpaRotulo($rot);
            if ($rotuloLimpo === '') {
              continue;
            }
          ?>
          <tr>
            <?php
              $itemLabel = $rotuloLimpo;
              $valorItem = $precoExibicao($t, $dados);
              if (
                $tipo === 'diario' && (
                  preg_match('/condutor|cadeirinh/i', $rot) ||
                  preg_match('/prote[c��][aǜ]o/i', $rot) ||
                  preg_match('/di[ǭa]ria/i', $rot)
                )
              ) {
                $q = max(1, (int)$qtd);
                $unit = ($q > 0) ? ($valorItem / $q) : $valorItem;
                $itemLabel .= ' R$ ' . number_format($unit, 2, ',', '.') . ' (x ' . $q . ' dias)';
              }
            ?>
            <td><?= esc_html($itemLabel) ?></td>
            <td>R$ <?= number_format($valorItem, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>

        <tr class="total">
          <td>Total Estimado</td>
          <td>R$ <?= number_format($dados['totais']['total'], 2, ',', '.') ?></td>
        </tr>
      </tbody>

    </table>
  </section>

</main>

</body>
</html>
