<?php
// Variáveis disponíveis: $dados (array com tudo), $titulo (nome do produto)
$css = @file_get_contents(BVGN_CAMINHO.'assets/css/cotacao.css');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cotação — <?= esc_html($titulo) ?></title>
  <style>
   <?= $css ?>
  </style>
</head>
<body>

<header>
  <div><strong>BV Locadora</strong></div>
  <div>Rua Coronel Mota, 629, Centro — Boa Vista/RR — CEP 69301-120</div>
  <div>Tel/Whats: (95) 98102-2395 • bvlocadora@outlook.com • www.bvlocadora.com.br</div>
</header>

<footer>
  <div>Cotação gerada via site BV Locadora — <span class="muted">Sujeita à validação da equipe</span>.</div>
  <!-- A numeração de páginas será inserida via PHP (canvas->page_text) no endpoint -->
</footer>

<main class="container">
  <h1>COTAÇÃO DE SERVIÇO</h1>

  <p>
    <span class="label">Cliente:</span> <?= esc_html($dados['nome'] ?? '') ?><br>
    <span class="label">WhatsApp:</span> <?= esc_html($dados['whats'] ?? '') ?><br>
  </p>

  <p>
    <span class="label">Plano:</span> <?= esc_html($dados['variacaoRotulo'] ?? '') ?><br>
    <?php if (!empty($dados['datas']['inicio'])): ?>
      <span class="label">Período:</span>
      <?= esc_html(($dados['totais']['tipo'] ?? '') === 'mensal' ? '30 dias' : (($dados['datas']['inicio'] ?? '').' a '.($dados['datas']['fim'] ?? ''))) ?>
      <br>
    <?php endif; ?>
  </p>

  <?php if (!empty($dados['protecao']['nome'])): ?>
  <p>
    <span class="label">Proteção:</span>
    <?= esc_html($dados['protecao']['nome']) ?> — R$
    <?= number_format(floatval($dados['protecao']['preco_dia'] ?? 0),2,',','.') ?>/dia
  </p>
  <?php endif; ?>

  <?php if (!empty($dados['taxas'])): ?>
  <h3>Opcionais / Taxas</h3>
  <table>
    <thead><tr><th>Descrição</th><th style="text-align:right">Valor</th></tr></thead>
    <tbody>
      <?php foreach ($dados['taxas'] as $t): ?>
        <tr>
          <td><?= esc_html($t['rotulo']) ?></td>
          <td style="text-align:right">R$ <?= number_format(floatval($t['preco'] ?? 0),2,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h3>Totais</h3>
  <table>
    <tbody>
      <tr><td>Subtotal</td><td style="text-align:right">R$ <?= number_format(floatval($dados['totais']['subtotal'] ?? 0),2,',','.') ?></td></tr>
      <tr class="total"><td>Total Estimado</td><td style="text-align:right">R$ <?= number_format(floatval($dados['totais']['total'] ?? 0),2,',','.') ?></td></tr>
    </tbody>
  </table>

  <?php if (!empty($dados['informacoes'])): ?>
    <h3>Observações</h3>
    <div><?= wp_kses_post($dados['informacoes']) ?></div>
  <?php endif; ?>
</main>

</body>
</html>
