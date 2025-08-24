<?php
$cssPath = BVGN_DIR . 'assets/css/cotacao.css';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$data = date('d/m/Y');
$codigo = strtoupper(substr(wp_hash(microtime()), 0, 5));

$validade = date('d/m/Y', strtotime('+5 days'));

// Quebra as taxas em grupos para exibir no "Detalhes"
$taxasAll   = $dados['taxas'] ?? [];
$protecao   = array_values(array_filter($taxasAll, fn($t) => preg_match('/prote[cç][aã]o/i', $t['rotulo'] ?? '')));
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
    <div class="card-grid-2">
      <!-- Card: Cliente -->
      <div class="card">
        <h3>Cliente</h3>
        <dl class="kv">
          <div><dt>Nome</dt><dd><?= esc_html($dados['nome'] ?? '—') ?></dd></div>
          <div><dt>WhatsApp</dt><dd><?= esc_html($dados['whats'] ?? '—') ?></dd></div>
        </dl>
      </div>

      <!-- Card: Cotação -->
      <div class="card">
        <h3>Cotação</h3>
        <dl class="kv">
          <div><dt>Data</dt><dd><?= $data ?></dd></div>
          <div><dt>Nº</dt><dd><?= $codigo ?></dd></div>
          <div><dt>Validade</dt><dd><?= $validade ?></dd></div>
        </dl>
      </div>
    </div>
  </section>

  <section class="cv-detalhes">
  <h2>Detalhes</h2>

  <dl class="kv">
    <div><dt>Plano</dt><dd><?= esc_html($dados['variacaoRotulo'] ?? '—') ?></dd></div>
    <div><dt>Local de retirada</dt><dd><?= esc_html($dados['retirada'] ?? '—') ?></dd></div>
    <div>
      <dt>Período</dt>
      <dd>
        <?php
          $tipo = $dados['totais']['tipo'] ?? 'diario';
          echo ($tipo === 'mensal')
            ? '30 dias'
            : esc_html(($dados['datas']['inicio'] ?? '—') . ' a ' . ($dados['datas']['fim'] ?? '—'));
        ?>
      </dd>
    </div>
    <div><dt>Retirada</dt><dd><?= esc_html($dados['datas']['inicio'] ?? '—') ?></dd></div>
    <div><dt>Devolução</dt><dd><?= esc_html($dados['datas']['fim'] ?? '—') ?></dd></div>
  </dl>

  <div class="alerta">
    <strong>Atenção:</strong> o período informado é uma pré-reserva. A confirmação depende da verificação de disponibilidade do veículo pela equipe BV Locadora.
  </div>

  <div class="grid-3">
    <div class="sub">
      <h4>Proteção</h4>
      <?php if ($protecao): ?>
        <ul class="lista">
          <?php foreach ($protecao as $t): ?>
            <li><?= esc_html($t['rotulo']) ?> — R$ <?= number_format($t['preco'] ?? 0, 2, ',', '.') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p>—</p><?php endif; ?>
    </div>

    <div class="sub">
      <h4>Serviços opcionais</h4>
      <?php if ($opcionais): ?>
        <ul class="lista">
          <?php foreach ($opcionais as $t): ?>
            <li><?= esc_html($t['rotulo']) ?> — R$ <?= number_format($t['preco'] ?? 0, 2, ',', '.') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p>—</p><?php endif; ?>
    </div>

    <div class="sub">
      <h4>Taxas</h4>
      <?php if ($taxasFixas): ?>
        <ul class="lista">
          <?php foreach ($taxasFixas as $t): ?>
            <li><?= esc_html($t['rotulo']) ?> — R$ <?= number_format($t['preco'] ?? 0, 2, ',', '.') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p>—</p><?php endif; ?>
    </div>
  </div>
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
