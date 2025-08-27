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

// Função para extrair valor da caução de qualquer rótulo
$extrairCaucao = function($rotulo) use ($toFloatBR) {
  // Procura padrão "caução de R$ X" ou similar
  if (preg_match('/cau[cç][aã]o[^0-9]*R?\$?\s*([\d\.,]+)/iu', $rotulo, $m)) {
    return $toFloatBR($m[1]);
  }
  // Procura qualquer número no rótulo de caução
  if (preg_match('/cau[cç][aã]o/i', $rotulo) && preg_match('/([\d\.,]+)/', $rotulo, $m)) {
    return $toFloatBR($m[1]);
  }
  return 0;
};

// CORREÇÃO: Função de cálculo de preço para exibição no PDF
$precoExibicao = function($t, $dados) use ($toFloatBR, $extrairCaucao){
  $p    = (float)($t['preco'] ?? 0);
  $rot  = (string)($t['rotulo'] ?? '');
  $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');
  $qtd  = max(1, (int)($dados['totais']['qtd'] ?? 1));

  // CASO ESPECIAL: "Sem proteção" no diário
  if (preg_match('/sem\s+prote[cç][aã]o/i', $rot) && $tipo === 'diario') {
    // Se o preço veio como 0, tentar extrair caução do rótulo
    if ($p == 0) {
      $caucaoValue = $extrairCaucao($rot);
      if ($caucaoValue > 0) {
        return $caucaoValue; // valor fixo da caução
      }
      
      // Se não achou no rótulo, procurar em outros itens
      foreach (($dados['taxas'] ?? []) as $tt) {
        $rr = (string)($tt['rotulo'] ?? '');
        if (preg_match('/cau[cç][aã]o/i', $rr)) {
          $caucaoFromOther = $extrairCaucao($rr);
          if ($caucaoFromOther > 0) {
            return $caucaoFromOther;
          }
        }
      }
    }
    return $p; // se não achou caução, retorna o que veio
  }

  // CASO ESPECIAL: Proteção com cobertura no diário
  if (preg_match('/prote[cç][aã]o/i', $rot) && !preg_match('/sem\s+prote[cç][aã]o/i', $rot) && $tipo === 'diario') {
    // O JavaScript já enviou o valor correto (multiplicado), apenas retorna
    return $p;
  }

  // Para itens com "caução" diretamente
  if ($p == 0 && preg_match('/cau[cç][aã]o/i', $rot)) {
    return $extrairCaucao($rot);
  }

  // Para outros itens diários (taxas, etc.)
  if (preg_match('/di[áa]ria/i', $rot)) {
    if ($tipo === 'diario')      return $p * $qtd;
    elseif ($tipo === 'mensal')  return $p * 30;
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
    // SEÇÃO CORRIGIDA - substituir a partir da linha que define $tipo

    $tipo = strtolower($dados['totais']['tipo'] ?? 'diario');

    // Localizar itens especiais nas taxas
    $protItem = null;
    $caucaoItem = null;
    foreach (($taxasAll ?? []) as $t) {
      $r = (string)($t['rotulo'] ?? '');
      if (!$protItem && preg_match('/prote[cç][aã]o/i', $r)) {
        $protItem = $t;
      }
      if (!$caucaoItem && preg_match('/cau[cç][aã]o/i', $r)) {
        $caucaoItem = $t;
      }
      if ($protItem && $caucaoItem) break;
    }

    // Determinar como exibir a proteção
    if ($tipo === 'mensal') {
      $protLabel = 'Proteção básica';
      $protValor = null; // mostra "incluída"
    } else {
      // DIÁRIO: mostrar exatamente o que foi selecionado
      if ($protItem) {
        $rotOrig = (string)($protItem['rotulo'] ?? '');
        $protLabel = $limpaRotulo($rotOrig);
        
        // Calcular valor para exibição
        $valorExibir = $precoExibicao($protItem, $dados);
        
        // Se for "sem proteção", ajustar o rótulo
        if (preg_match('/sem\s+prote[cç][aã]o/i', $rotOrig)) {
          $protLabel = 'Sem proteção';
        }
        
        $protValor = $valorExibir;
      } else {
        $protLabel = 'Sem proteção selecionada';
        $protValor = 0;
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
                <?= esc_html($protLabel) ?>
                <?php if ($protValor !== null): ?>
                  — R$ <?= number_format($protValor, 2, ',', '.') ?>
                <?php else: ?>
                  — incluída
                <?php endif; ?>
              </dd>
            </div>
          </div>

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

        <?php foreach (($dados['taxas'] ?? []) as $t): ?>
        <?php
          $rot = (string)($t['rotulo'] ?? '');
          
          // No plano mensal, não listar proteção na tabela (já mostramos "incluída" no card)
          if ($tipo === 'mensal' && preg_match('/prote[cç][aã]o/i', $rot)) {
            continue;
          }
          
          // No plano diário, se for "sem proteção" com valor 0, pular da tabela 
          // (o valor da caução já aparece como "Sem proteção" no card)
          if ($tipo === 'diario' && preg_match('/sem\s+prote[cç][aã]o/i', $rot) && $precoExibicao($t, $dados) == 0) {
            continue;
          }
          
          $rotuloLimpo = $limpaRotulo($rot);
          if ($rotuloLimpo === '') {
            continue;
          }
          
          $valorExibir = $precoExibicao($t, $dados);
          if ($valorExibir <= 0) {
            continue; // pula itens com valor 0
          }
        ?>
        <tr>
          <td><?= esc_html($rotuloLimpo) ?></td>
          <td>R$ <?= number_format($valorExibir, 2, ',', '.') ?></td>
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
