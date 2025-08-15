BV Grupo Novo (Produto Paralelo) — v0.3.1
==============================================

Esta versão usa **shortcodes** para montar o layout no Elementor e mantém a URL do produto, ex.:
https://bvlocadora.com.br/product/grupo-a-kwid-mobi-ou-similar/

Instalação
----------
- Plugins → Adicionar novo → Enviar plugin → selecione o ZIP → Instalar → Ativar.
- No Elementor (modelo "grupo_new"), use **Widgets Shortcode** com:
  [grupo_novo]
    [gn_imagem]
    [gn_titulo]
    [gn_descricao]
    [gn_variacoes]
    [gn_taxas]
    [gn_agendamento]
    [gn_totais]
    [gn_informacoes placeholder="Observacoes do cliente..."]
    [gn_botao_cotacao format="html" phone="+5541XXXXXXXX"]
  [/grupo_novo]

Dica: se a caixa do Elementor não aceitar múltiplas linhas, use **um widget Shortcode para cada** (gn_imagem, gn_titulo, etc.) dentro de um widget Section/Container.

Detecção Diário/Mensal
----------------------
- 'aluguel-de-carros-diaria' → Diário
- 'aluguel-de-carros-mensal' → Mensal
Os shortcodes [gn_variacoes], [gn_agendamento] e [gn_totais] detectam automaticamente.

PDF (Opcional)
--------------
- Rode `composer install` na pasta do plugin para habilitar Dompdf.
- Sem Dompdf, o botão gera HTML normalmente em wp-content/uploads/grupo-novo/.

Template do plugin (opcional)
-----------------------------
- Para usar o template do plugin (sem Elementor), defina no wp-config.php:
  define('BVGN_USAR_TEMPLATE', true);
