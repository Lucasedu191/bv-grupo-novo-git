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
              <p><strong>Mensagem:</strong> <?= nl2br(esc_html($mensagem)); ?></p>
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
                  <div><dt>Plano</dt><dd><?= esc_html($dados['variacaoRotulo'] ?? '—') ?></dd></div>
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
    <tr>
      <td class="col" style="width:50%; padding-right:3mm; vertical-align:top;">
        <div class="card card--ghost">
          <h3>Serviços opcionais</h3>
          <?php if (!empty($opcionais)): ?>
            <ul class="lista">
              <?php foreach ($opcionais as $t): ?>
                <li><?= esc_html($t['rotulo']) ?> — R$ <?= number_format($t['preco'] ?? 0, 2, ',', '.') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?><p>—</p><?php endif; ?>
        </div>
      </td>

      <td class="col" style="width:50%; padding-left:3mm; vertical-align:top;">
        <div class="card card--ghost">
          <h3>Taxas</h3>
          <?php if (!empty($taxasFixas)): ?>
            <ul class="lista">
              <?php foreach ($taxasFixas as $t): ?>
                <li><?= esc_html($t['rotulo']) ?> — R$ <?= number_format($t['preco'] ?? 0, 2, ',', '.') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?><p>—</p><?php endif; ?>
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
        <tr><td>Diária/Mensal</td><td>R$ <?= number_format($dados['totais']['base'], 2, ',', '.') ?></td></tr>
        <tr><td>Caução</td><td>R$ 2.500,00</td></tr>
        <?php foreach ($dados['taxas'] ?? [] as $t): ?>
          <tr><td><?= esc_html($t['rotulo']) ?></td><td>R$ <?= number_format($t['preco'], 2, ',', '.') ?></td></tr>
        <?php endforeach; ?>
        <tr><td>Subtotal</td><td>R$ <?= number_format($dados['totais']['subtotal'], 2, ',', '.') ?></td></tr>
        <tr class="total"><td>Total Estimado</td><td>R$ <?= number_format($dados['totais']['total'], 2, ',', '.') ?></td></tr>
      </tbody>
    </table>
  </section>
</main>

</body>
</html>
