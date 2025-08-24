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
  $txt = (string)$txt;
  $txt = preg_replace('/\s*\([^)]*\)/u', '', $txt);           // remove "(...)"
  $txt = preg_replace('/\bSelecionad[oa]r?\b/iu', '', $txt);   // "Selecionado/Selecionar"
  $txt = preg_replace('/,\s*cauç[aã]o[^—]*/iu', '', $txt);     // ", caução de ..."
  $txt = preg_replace('/\s{2,}/', ' ', trim($txt));            // espaços
  return $txt;
};

// Calcula o preço exibido (multiplica quando for diária)
$precoExibicao = function($t, $dados){
  $p    = floatval($t['preco'] ?? 0);
  $rot  = (string)($t['rotulo'] ?? '');
  $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
  $qtd  = max(1, intval($dados['totais']['qtd'] ?? 1));

  // Se for item "diária" e plano diário → multiplica pelos dias
  if (preg_match('/di[áa]ria/i', $rot)) {
    if ($tipo === 'diario')      $p *= $qtd;
    elseif ($tipo === 'mensal')  $p *= 30;
  }
  return $p;
};

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

                    if ($tipo === 'mensal') {
                      $labelPlano = 'Mensal (30 dias)';
                    } else {
                      $labelPlano = $qtd > 1 ? "Diárias ($qtd dias)" : "Diária";
                    }
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

        <!-- Linha 2: SERVIÇOS OPCIONAIS | TAXAS -->
        <?php
      // detectar tipo e preparar info da proteção
      $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');

      // localizar item de proteção nas taxas enviadas (se vier do front)
      $protItem = null;
      foreach (($taxasAll ?? []) as $t) {
        if (preg_match('/prote[cç][aã]o/i', $t['rotulo'] ?? '')) {
          $protItem = $t;
          break;
        }
      }

      // rótulo e valor para exibir na “Proteção”
      if ($tipo === 'mensal') {
        $protLabel = 'Proteção básica';
        $protValor = null; // incluída
      } else {
        if ($protItem) {
          $protLabel = $limpaRotulo($protItem['rotulo'] ?? 'Proteção');
          $protValor = $precoExibicao($protItem, $dados);
        } else {
          // se não veio item específico, ainda assim mostra “Proteção” sem valor
          $protLabel = 'Proteção';
          $protValor = null;
        }
      }
        ?>
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
                <?= esc_html($protLabel) ?>
                <?php if ($protValor !== null): ?>
                  — R$ <?= number_format($protValor, 2, ',', '.') ?>
                <?php else: ?>
                  — incluída
                <?php endif; ?>
              </dd>
            </div>
          </div>

          <!-- Lista de opcionais (já limpa e com preço calculado) -->
          <?php if (!empty($opcionais)): ?>
            <ul class="lista">
              <?php foreach ($opcionais as $t): ?>
                <li>
                  <?= esc_html($limpaRotulo($t['rotulo'] ?? '')) ?> —
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
                  <?= esc_html($limpaRotulo($t['rotulo'] ?? '')) ?> —
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
            <td><?= esc_html($rotuloLimpo) ?></td>
            <td>R$ <?= number_format($precoExibicao($t, $dados), 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>

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
