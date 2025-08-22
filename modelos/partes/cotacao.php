<?php
$css = @file_get_contents(BVGN_CAMINHO . 'assets/css/cotacao.css');
$data = date('d/m/Y');
$codigo = strtoupper(substr(wp_hash(microtime()), 0, 5));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <style><?= $css ?></style>
</head>
<body>

<!-- Cabeçalho -->
<header class="cotacao-cabecalho">
  <img src="https://bvlocadora.com.br/wp-content/uploads/2019/04/logo-boton-bv-locadora.png" alt="BV Locadora" class="logo-cabecalho">
  <h1 class="titulo-cabecalho">COTAÇÃO</h1>
</header>

<!-- Conteúdo principal -->
<main class="cotacao-container">
  <section class="cotacao-dados">
    <div class="bloco-esquerda">
      <p><strong>Cliente:</strong> <?= esc_html($dados['nome'] ?? '') ?></p>
      <p><strong>WhatsApp:</strong> <?= esc_html($dados['whats'] ?? '') ?></p>
      <p><strong>Plano:</strong> <?= esc_html($dados['variacaoRotulo'] ?? '') ?></p>
      <p><strong>Período:</strong>
        <?= esc_html(($dados['totais']['tipo'] ?? '') === 'mensal' ? '30 dias' : (($dados['datas']['inicio'] ?? '') . ' a ' . ($dados['datas']['fim'] ?? ''))) ?>
      </p>
    </div>
    <div class="bloco-direita">
      <p><strong>Data:</strong> <?= $data ?></p>
      <p><strong>Nº Cotação:</strong> <?= $codigo ?></p>
    </div>
  </section>

  <?php if (!empty($dados['taxas'])): ?>
    <section class="cotacao-tabela">
      <table>
        <thead>
          <tr>
            <th>Descrição</th>
            <th style="text-align:right">Valor</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Diária/Mensal</td>
            <td style="text-align:right">R$ <?= number_format($dados['totais']['base'], 2, ',', '.') ?></td>
          </tr>
          <tr>
            <td>Caução</td>
            <td style="text-align:right">R$ 2.500,00</td>
          </tr>
          <?php foreach ($dados['taxas'] as $t): ?>
            <tr>
              <td><?= esc_html($t['rotulo']) ?></td>
              <td style="text-align:right">R$ <?= number_format($t['preco'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td>Subtotal</td>
            <td style="text-align:right">R$ <?= number_format($dados['totais']['subtotal'], 2, ',', '.') ?></td>
          </tr>
          <tr class="total">
            <td><strong>Total Estimado</strong></td>
            <td style="text-align:right"><strong>R$ <?= number_format($dados['totais']['total'], 2, ',', '.') ?></strong></td>
          </tr>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</main>

<!-- Rodapé -->
<footer class="cotacao-rodape">
  <div class="rodape-conteudo">
    <img src="https://bvlocadora.com.br/wp-content/uploads/2019/04/logo-boton-bv-locadora.png" class="logo-rodape" alt="Logo">
    <div class="info-empresa">
      <p>Rua Coronel Mota, 629, Centro - Boa Vista, Roraima, Cep: 69301120</p>
      <p>(95) 98102-2395</p>
      <p>bvlocadora@outlook.com</p>
    </div>
  </div>
</footer>

</body>
</html>
