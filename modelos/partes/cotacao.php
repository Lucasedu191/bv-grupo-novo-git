<?php
$cssPath = BVGN_DIR . 'assets/css/cotacao.css';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$data = date('d/m/Y');
$codigo = strtoupper(substr(wp_hash(microtime()), 0, 5));

$logoUrl      = BVGN_URL . 'assets/img/logo-bvlocadora.png'; // topo
$wmUrl   = $logoUrl; // usa a mesma imagem como marca d'água
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
  <img src="<?= esc_url($wmUrl) ?>" alt="" />
</div>

<!-- Cabeçalho moderno / timbrado -->
<header class="cotacao-cabecalho">
  <div class="cabecalho-col-esq">
    <img class="logo-topo" src="<?= esc_url($logoUrl) ?>" alt="BV Locadora" />
    <div class="empresa-info">
      <strong>BV Locadora</strong><br>
      CNPJ: 31.315.055/0001-14<br>
      Rua Coronel Mota, 629 — Centro — Boa Vista/RR — CEP: 69301-120<br>
      Tel: (95) 98102-2395 — E-mail: bvlocadora@outlook.com — www.bvlocadora.com.br
    </div>
  </div>

  <div class="cabecalho-col-dir">
    <p class="titulo-topo">COTAÇÃO<br><span>DE SERVIÇO</span></p>
    <!-- se você já tem a caixa de Data/Nº, mantenha aqui -->
  </div>
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
