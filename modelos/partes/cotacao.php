<?php
$cssPath = BVGN_DIR . 'assets/css/cotacao.css';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$data = date('d/m/Y');
$codigo = strtoupper(substr(wp_hash(microtime()), 0, 5));

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

<!-- Cabeçalho timbrado: logo + infos (lado a lado) | título à direita -->
<!-- Cabeçalho timbrado: [logo | infos] + [título] -->
<header class="cotacao-cabecalho">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
    <tr valign="middle">
      <!-- COL 1: LOGO -->
      <td width="78" style="padding:0 8px 0 0;">
        <img src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png"
             alt="BV Locadora" style="display:block;height:56px;width:auto;">
      </td>

      <!-- COL 2: INFOS (à ESQUERDA, na MESMA LINHA da logo) -->
      <td style="padding:0; font-size:10.5px; line-height:1.28; color:#222; text-align:left;">
        <strong>A. F. COMERCIO E SERVICOS LTDA</strong><br>
        CNPJ: 31.315.055/0001-14<br>
        R. Cel. Mota, Centro, 629 — Boa Vista/RR — CEP: 69301-120<br>
        TEL: (95) 98102-2395 — E-mail: bvlocadora@outlook.com
      </td>

      <!-- COL 3: TÍTULO (à DIREITA) -->
      <td width="220" align="right" style="padding:0 0 0 12px;">
        <p style="margin:0; font: 800 20px/1.1 Arial, sans-serif; letter-spacing:.02em; text-transform:uppercase; text-align:right;">
          COTAÇÃO<br><span style="font-weight:900;">DE SERVIÇO</span>
        </p>
      </td>
    </tr>
  </table>
</header>


<!-- Conteúdo -->
<main class="cotacao-container">
  <section class="cotacao-dados">
    <div class="bloco-esquerda">
      <h2>Cliente</h2>
      <p><strong>Nome:</strong> <?= esc_html($dados['nome'] ?? 'Fulano de Tal') ?></p>
      <p><strong>WhatsApp:</strong> <?= esc_html($dados['whats'] ?? '(99) 99999-9999') ?></p>
      <p><strong>Plano:</strong> <?= esc_html($dados['variacaoRotulo'] ?? 'Diário') ?></p>
      <p><strong>Período:</strong>
        <?= esc_html(($dados['totais']['tipo'] ?? '') === 'mensal' ? '30 dias' : (($dados['datas']['inicio'] ?? '') . ' a ' . ($dados['datas']['fim'] ?? ''))) ?>
      </p>
    </div>
    <div class="bloco-direita">
      <h2>Detalhes</h2>
      <p><strong>Data:</strong> <?= $data ?></p>
      <p><strong>Nº Cotação:</strong> <?= $codigo ?></p>
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
