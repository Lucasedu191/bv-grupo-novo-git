<?php
$css = <<<CSS
  @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@600&family=Titillium+Web:wght@400;600&display=swap');

  @page { margin: 120px 40px 80px 40px; }

  body {
    font-family: 'Titillium Web', sans-serif;
    font-size: 12px;
    color: #333;
    background: #f1f1f1;
  }

  header {
    position: fixed;
    top: -100px;
    left: 0;
    right: 0;
    text-align: center;
    border-bottom: 2px solid #163c67;
    padding-bottom: 10px;
    background: #fff;
  }

  header img {
    max-height: 60px;
  }

  header .info {
    font-size: 10px;
    margin-top: 5px;
    line-height: 1.4;
  }

  footer {
    position: fixed;
    bottom: -60px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 10px;
    border-top: 1px solid #ccc;
    padding-top: 6px;
  }

  .card {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 0 5px rgba(0,0,0,0.05);
  }

  .titulo {
    font-family: 'Barlow', sans-serif;
    font-size: 16px;
    text-transform: uppercase;
    color: #163c67;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }

  .blocos {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 15px;
  }

  .bloco {
    width: 48%;
  }

  .linha {
    border-top: 1px solid #ccc;
    margin: 20px 0;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }

  th, td {
    padding: 6px;
    border-bottom: 1px solid #eee;
  }

  .total {
    background: #163c67;
    color: #fff;
    font-weight: bold;
    text-align: right;
    padding: 10px;
    margin-top: 10px;
  }

  .contato {
    font-size: 10px;
    margin-top: 30px;
    text-align: center;
  }

CSS;

$data = date('d/m/Y');
$codigo = strtoupper(substr(wp_hash(microtime()), 0, 5));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style><?= $css ?></style>
</head>
<body>

<header>
  <img src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png" alt="BV Locadora">
  <div class="info">
    Rua Coronel Mota, 629, Centro — Boa Vista/RR — CEP 69301-120<br>
    Tel/Whats: (95) 98102-2395 • bvlocadora@outlook.com • www.bvlocadora.com.br
  </div>
</header>

<footer>
  Cotação gerada via site BV Locadora — Sujeita à validação da equipe.
</footer>

<main class="container">
  <div class="card">
    <div class="titulo">
      <span>BVLOCADORA</span>
      <span>COTAÇÃO DE SERVIÇO</span>
    </div>

    <div class="blocos">
      <div class="bloco">
        <strong>CLIENTE</strong><br>
        <?= esc_html($dados['nome'] ?? '') ?><br>
        <?= esc_html($dados['endereco'] ?? 'Rua Exemplo, 456') ?><br>
        <?= esc_html($dados['cidade'] ?? 'Boa Vista – RR') ?>
      </div>
      <div class="bloco">
        <strong>DATA</strong><br>
        <?= $data ?><br><br>
        <strong>Nº COTAÇÃO</strong><br>
        <?= $codigo ?>
      </div>
    </div>

    <div class="blocos">
      <div class="bloco">
        <strong>BV LOCADORA</strong><br>
        Rua Coronel Mota, 629<br>
        Boa Vista – RR
      </div>
      <div class="bloco">
        <!-- vazio -->
      </div>
    </div>

    <div class="linha"></div>

    <div class="blocos">
      <div class="bloco">
        <strong>MODELO</strong><br>
        <?= esc_html($dados['modelo'] ?? 'Modelo não informado') ?><br><br>
        <strong>GRUPO</strong><br>
        <?= esc_html($dados['grupo'] ?? 'Grupo não informado') ?><br><br>
        <strong>PLANO</strong><br>
        <?= esc_html($dados['variacaoRotulo'] ?? '-') ?>
      </div>
    </div>

    <div class="linha"></div>

    <table>
      <tbody>
        <tr><td>Diária/Mensal</td><td style="text-align:right">R$ <?= number_format($dados['totais']['base'],2,',','.') ?></td></tr>
        <tr><td>Caução</td><td style="text-align:right">R$ 2.500,00</td></tr>
        <?php if (!empty($dados['protecao'])): ?>
        <tr><td>Proteção</td><td style="text-align:right">R$ <?= number_format(floatval($dados['protecao']['preco_dia'] ?? 0),2,',','.') ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($dados['taxas'])):
          $totalOpcionais = 0;
          foreach($dados['taxas'] as $t):
            $totalOpcionais += floatval($t['preco']);
          endforeach; ?>
        <tr><td>Opcionais</td><td style="text-align:right">R$ <?= number_format($totalOpcionais,2,',','.') ?></td></tr>
        <?php endif; ?>
        <tr><td>Taxa de Limpeza</td><td style="text-align:right">R$ 45,00</td></tr>
      </tbody>
    </table>

    <div class="total">
      TOTAL ESTIMADO — R$ <?= number_format($dados['totais']['total'],2,',','.') ?>
    </div>

    <div class="contato">
      Telefone: (95) 98102-2395 – bvlocadora@outlook.com<br>
      www.bvlocadora.com.br<br>
      A cotação possui validade de 5 dias.
    </div>
  </div>
</main>

</body>
</html>
