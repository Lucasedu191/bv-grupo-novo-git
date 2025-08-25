<?php
$cssPath = BVGN_DIR . 'assets/css/cotacao.css';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$data = date('d/m/Y');
$codigo = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);


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
  $txt = preg_replace('/\bSelecionad[oa]r?\b/iu', '', $txt);
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

  // C) Itens diários multiplicam
  if (preg_match('/di[áa]ria/i', $rot)) {
    if ($tipo === 'diario')      $p *= $qtd;
    elseif ($tipo === 'mensal')  $p *= 30;
  }

  return $p;
};
// Busca item por palavra-chave no rótulo
$buscaItem = function(array $lista, string $regex){
  foreach ($lista as $it) {
    $r = (string)($it['rotulo'] ?? '');
    if (preg_match($regex, $r)) return $it;
  }
  return null;
};

// Extrai "R$ 1.234,56" de um texto e retorna float
$extraiMoeda = function($txt) use ($toFloatBR){
  if (preg_match('/R\$\s*([\d\.\,]+)/u', (string)$txt, $m)) return $toFloatBR($m[1]);
  if (preg_match('/([\d\.\,]+)/u', (string)$txt, $m))       return $toFloatBR($m[1]);
  return 0.0;
};

// Itens especiais a partir do que chegou em $dados['taxas']
$protItem   = $buscaItem($taxasAll, '/prote[cç][aã]o/i'); // pode ser "Sem proteção" ou "Proteção básica"
$caucaoItem = $buscaItem($taxasAll, '/cau[cç][aã]o/i');   // "Caução (aplicado...) — R$ 1000,00"

// Detecta "sem proteção" e tenta achar o valor do caução mesmo que venha 0
$ehSemProtecao = $protItem && preg_match('/sem\s+prote[cç][aã]o/i', (string)$protItem['rotulo']);
$caucaoValor   = 0.0;
if ($ehSemProtecao) {
  // 1) tenta no próprio rótulo da proteção
  $caucaoValor = max($caucaoValor, $extraiMoeda($protItem['rotulo'] ?? ''));
  // 2) tenta num item separado de caução (se existir)
  if ($caucaoItem) {
    $caucaoValor = max($caucaoValor, (float)($caucaoItem['preco'] ?? 0));
    if ($caucaoValor <= 0) $caucaoValor = max($caucaoValor, $extraiMoeda($caucaoItem['rotulo'] ?? ''));
  }
}

$retirada  = $fmt($dados['datas']['inicio'] ?? '');
$devolucao = $fmt($dados['datas']['fim'] ?? '');
$localRetirada = $dados['local'] ?? '—';
$validade = date('d/m/Y', strtotime('+5 days'));
$mensagem = trim($dados['mensagem'] ?? '');


// Quebra as taxas em grupos para exibir no "Detalhes"
$taxasAll   = $dados['taxas'] ?? [];
$taxasFixas = array_values(array_filter($taxasAll, fn($t) => preg_match('/taxa|limpeza/i', $t['rotulo'] ?? '')));
$opcionais  = array_values(array_filter($taxasAll, fn($t) =>
  !preg_match('/prote[cç][aã]o|taxa|limpeza/i', $t['rotulo'] ?? '')
));

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

          <!-- grade 3 colunas dentro do card -->
          <table class="kv3" role="presentation" width="100%" cellspacing="0" cellpadding="0">
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

              <!-- Coluna 2: Local de retirada -->
              <td>
                <dl class="kv kv--stack">
                  <div><dt>Local de retirada</dt><dd><?= esc_html($localRetirada) ?></dd></div>
                </dl>
              </td>

              <!-- Coluna 3: Retirada e Devolução -->
              <td>
                <dl class="kv kv--stack">
                  <div><dt>Retirada</dt><dd><?= $retirada ?></dd></div>
                  <div><dt>Devolução</dt><dd><?= $devolucao ?></dd></div>
                </dl>
              </td>
            </tr>
          </table>

          <div class="alerta">
            <strong>Atenção:</strong> o período informado é uma pré-reserva. A confirmação será feita após a verificação de disponibilidade do veículo pela equipe BV Locadora.
          </div>
        </div>
      </td>
    </tr>

    <?php
      // detectar tipo
      $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');

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

          <!-- Proteção -->
          <div class="kv" style="margin-bottom: 2mm;">
            <div>
              <dt>Proteção</dt>
              <dd>
                <?php
                  // rótulo base: "Proteção básica" (mensal) OU rótulo limpo do item selecionado (diário)
                  if ($tipo === 'mensal') {
                    $protLabel = 'Proteção básica';
                    $protValor = null; // incluída
                  } else {
                    $protLabel = $protItem ? $limpaRotulo($protItem['rotulo'] ?? 'Proteção') : 'Proteção';
                    $protValor = $protItem ? $precoExibicao($protItem, $dados) : null;
                  }
                ?>
                <?= esc_html($protLabel) ?>

                <?php if ($ehSemProtecao && $caucaoValor > 0): ?>
                  — caução de R$ <?= number_format($caucaoValor, 2, ',', '.') ?>
                <?php endif; ?>

                <?php if ($protValor !== null): ?>
                  — R$ <?= number_format($protValor, 2, ',', '.') ?>
                <?php else: ?>
                  — incluída
                <?php endif; ?>
              </dd>
            </div>
          </div>


          <!-- (Mensal) Mostrar Caução destacado -->
          <?php if ($tipo === 'mensal'): ?>
            <ul class="lista" style="margin-top:2mm">
              <?php
                // mostra caução: usa item de caução se existir; se não, tenta extrair do texto da proteção (front costuma mostrar na lateral)
                $caucaoParaCard = 0.0;
                if ($caucaoItem) {
                  $caucaoParaCard = (float)($caucaoItem['preco'] ?? 0);
                  if ($caucaoParaCard <= 0) $caucaoParaCard = $extraiMoeda($caucaoItem['rotulo'] ?? '');
                } else {
                  $caucaoParaCard = max($caucaoParaCard, $extraiMoeda($protItem['rotulo'] ?? ''));
                }
              ?>
              <?php if ($caucaoParaCard > 0): ?>
                <li>
                  Caução — R$ <?= number_format($caucaoParaCard, 2, ',', '.') ?>
                </li>
              <?php endif; ?>
            </ul>
          <?php endif; ?>

          <!-- Lista de opcionais (limpa rótulo e calcula preço; evita duplicar proteção/caução) -->
          <?php if (!empty($opcionais)): ?>
            <ul class="lista">
              <?php foreach ($opcionais as $t): ?>
                <?php
                  $rOrig = (string)($t['rotulo'] ?? '');
                  // pular proteção sempre (já tratamos acima)
                  if (preg_match('/prote[cç][aã]o/i', $rOrig)) continue;
                  // no mensal, pular caução da lista (já mostramos destacado acima)
                  if ($tipo === 'mensal' && preg_match('/cau[cç][aã]o/i', $rOrig)) continue;
                  $rClean = $limpaRotulo($rOrig);
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

          if ($tipo === 'mensal') {
            $labelPlano = 'Mensal (30 dias)';
          } else {
            $labelPlano = $qtd > 1 ? "Diárias ($qtd dias)" : "Diária";
          }
        ?>
        <tr>
          <td><?= $labelPlano ?></td>
          <td>R$ <?= number_format($dados['totais']['base'], 2, ',', '.') ?></td>
        </tr>

        <?php
          $caucaoListado = false;
          $temSemProtecao = false;
        ?>
        <?php foreach (($dados['taxas'] ?? []) as $t): ?>
          <?php
            $rotBruto = (string)($t['rotulo'] ?? '');
            $rotClean = $limpaRotulo($rotBruto);

            // no mensal, não listar proteção; e marque caução se aparecer
            if ($tipo === 'mensal' && preg_match('/prote[cç][aã]o/i', $rotBruto)) continue;
            if (preg_match('/cau[cç][aã]o/i', $rotBruto)) $caucaoListado = true;

            // diário: se for "Sem proteção", não use o preço 0 — use o do caução
            $preco = $precoExibicao($t, $dados);
            if ($ehSemProtecao && preg_match('/sem\s+prote[cç][aã]o/i', $rotBruto)) {
              $temSemProtecao = true;
              if ($caucaoValor > 0) {
                $preco    = $caucaoValor;
                $rotClean = 'Sem proteção — caução de'; // mostra o texto pedido
              }
            }

            if ($rotClean === '') continue;
          ?>
          <tr>
            <td><?= esc_html($rotClean) ?></td>
            <td>R$ <?= number_format($preco, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>

        <?php // Mensal: garantir linha de caução mesmo que não tenha vindo em $dados['taxas'] ?>
        <?php if ($tipo === 'mensal' && !$caucaoListado): ?>
          <?php
            $caucaoParaTabela = 0.0;
            if ($caucaoItem) {
              $caucaoParaTabela = (float)($caucaoItem['preco'] ?? 0);
              if ($caucaoParaTabela <= 0) $caucaoParaTabela = $extraiMoeda($caucaoItem['rotulo'] ?? '');
            } else {
              $caucaoParaTabela = max($caucaoParaTabela, $extraiMoeda($protItem['rotulo'] ?? ''));
            }
          ?>
          <?php if ($caucaoParaTabela > 0): ?>
            <tr>
              <td>Caução</td>
              <td>R$ <?= number_format($caucaoParaTabela, 2, ',', '.') ?></td>
            </tr>
          <?php endif; ?>
        <?php endif; ?>


        <tr>
          <td>Subtotal</td>
          <td>R$ <?= number_format($dados['totais']['subtotal'], 2, ',', '.') ?></td>
        </tr>
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
