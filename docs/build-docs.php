<?php
// Gera o PDF do guia do desenvolvedor usando Dompdf (já presente em vendor/)

declare(strict_types=1);

// Caminhos base
$root = dirname(__DIR__);
$htmlFile = __DIR__ . '/DEVELOPER_GUIDE.html';
$outFile  = __DIR__ . '/DEVELOPER_GUIDE.pdf';

if (!file_exists($htmlFile)) {
  fwrite(STDERR, "Arquivo HTML do guia não encontrado: {$htmlFile}\n");
  exit(1);
}

$html = file_get_contents($htmlFile);
if ($html === false) {
  fwrite(STDERR, "Falha ao ler {$htmlFile}\n");
  exit(1);
}

// Autoload do Dompdf
$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
  fwrite(STDERR, "vendor/autoload.php não encontrado. Instale dependências ou inclua a pasta vendor.\n");
  exit(1);
}
require $autoload;

use Dompdf\Dompdf;

// Instancia e renderiza
$dompdf = new Dompdf([
  'isHtml5ParserEnabled' => true,
  'isRemoteEnabled' => true,
]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Numeração de páginas
$canvas = $dompdf->get_canvas();
$font   = $dompdf->getFontMetrics()->get_font('Helvetica', 'normal');
$canvas->page_text(520, 820, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 9, [0,0,0]);

// Salva
file_put_contents($outFile, $dompdf->output());

echo "PDF gerado com sucesso em: {$outFile}\n";

