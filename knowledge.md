# Knowledge do Projeto BV Grupo Novo

## Visão Geral

- Objetivo do plugin: criar uma página de produto paralela para cotação de veículos da BV Locadora dentro de WordPress/WooCommerce, com fluxo modular para plano diário e plano mensal, geração de cotação em HTML/PDF e envio de contexto para WhatsApp.
- Fluxo geral: o usuário escolhe datas no cabeçalho ou na página do produto, navega para o plano diário ou mensal, seleciona variação, taxas, proteção e demais opcionais, preenche o modal de cotação e recebe um link de PDF e/ou mensagem para WhatsApp.
- Arquitetura: plugin WordPress com bootstrap principal, shortcodes, templates em PHP, scripts JS de cálculo e interface, CSS por bloco, endpoint AJAX para geração de arquivo, endpoints REST de leitura e tela administrativa para tarifa dinâmica e exportação.
- Principais componentes encontrados:
  - `bv-grupo-novo.php`
  - `inclui/ShortcodesPT.php`
  - `inclui/RenderPT.php`
  - `inclui/IntegracoesPT.php`
  - `inclui/DynamicTariffs.php`
  - `inclui/ApiRest.php`
  - `inclui/GerarArquivoEndpoint.php`
  - `inclui/ExportacaoCSV.php`
  - `modelos/partes/*.php`
  - `assets/js/*.js`
  - `assets/css/*.css`
  - `docs/API_BUSCA_WORDPRESS.md`
  - `docs/DEVELOPER_GUIDE.html`
  - `docs/documentação bvlocadora.pdf`

## Fluxo do Cliente

- Cliente acessa o site ou a página do produto.
- Cliente escolhe data de retirada e devolução no cabeçalho.
- O sistema salva o agendamento em `localStorage` como `bvgn_agendamento`.
- O sistema redireciona para:
  - `/planos-diarios` se o período for de até 30 dias.
  - `/planos-mensais` se o período for maior que 30 dias.
- Na página do produto, o cliente vê o bloco de imagem, título, descrição, variações, taxas, proteção, agendamento e totais.
- Em plano diário, a variação pode ser selecionada automaticamente com base na quantidade de dias.
- Em plano mensal, a variação é baseada em `franquia-de-km` e o período é tratado como 30 dias.
- O cliente ajusta proteção e opcionais.
- O resumo é recalculado no front-end.
- O cliente abre o modal de cotação, informa nome, WhatsApp e mensagem opcional.
- O front-end tenta gerar PDF via AJAX e, se conseguir, envia o link ao WhatsApp.
- Se o AJAX não estiver disponível, o sistema cai para uma mensagem direta do WhatsApp sem PDF.
- Não foi encontrado fluxo de criação de pedido, checkout, reserva em banco ou pagamento no código.

## Grupos de Veículos

- O plugin não contém uma lista completa de veículos embutida no código.
- O grupo do produto é inferido por:
  - meta `_bvgn_grupo`
  - padrão no título `Grupo X`
  - nos endpoints REST, o grupo também é inferido assim
- Grupos encontrados em regras e mapas internos:
  - `A`
  - `B`
  - `C`
  - `D`
  - `E`
  - `F`
  - `G`
  - `H`
  - `I`
  - `J`
- Exemplos de veículos encontrados na documentação:
  - `Grupo A - Hatch`
  - `Grupo A - Hatch - 03 a 06 Dias`
- Categorias encontradas:
  - `aluguel-de-carros-diaria`
  - `aluguel-de-carros-mensal`
- “Similares” aparece como conceito de título e documentação, mas não foi encontrada uma tabela própria de veículos similares no repositório.
- Diárias:
  - a variação é escolhida por faixa de dias.
  - a seleção pode ser automática.
- Mensal:
  - a variação é escolhida por `franquia-de-km`.
  - a quantidade de dias é exibida como 30 dias.

## Tarifas

- Faixas de diária:
  - as variações diárias usam intervalo mínimo e máximo de dias.
  - o cálculo do preço diário usa a variação selecionada.
  - se o período não encaixar em uma variação, o sistema tenta usar uma opção de fallback.
- Faixas de mensal:
  - as variações mensais dependem do atributo `franquia-de-km`.
  - o template mensal trata o ciclo como 30 dias.
  - os preços mensais são apresentados a partir da variação do produto.
- Regras encontradas:
  - no cabeçalho, o período máximo aceito para busca diária é de 30 dias.
  - no cabeçalho, domingos são bloqueados.
  - no front-end do produto, o plano diário também usa limite absoluto de 30 dias.
  - a tarifa dinâmica só é aplicada no plano diário.
  - a tarifa dinâmica é administrável no painel.
  - o total exibido no front-end é arredondado com `Math.round`.
  - o PDF usa o total enviado pelo front-end.

## Proteções

- Proteção diária existe apenas para o plano diário.
- Os valores variam por cor de grupo.
- Mapeamento de cor por grupo encontrado:
  - verde: `A`, `B`, `C`
  - azul: `D`, `E`, `F`, `G`, `I`
  - laranja: `H`
- Valores encontrados para proteção:
  - verde:
    - básica: `35`
    - premium: `65`
  - azul:
    - básica: `45`
    - premium: `85`
  - laranja:
    - básica: `65`
    - premium: `125`
- Caução associada à proteção:
  - o bloco de proteção envia caução via `data-caucao`.
  - no JS, a caução pode entrar como aviso e não como item do total final.
  - o PDF trata caução separadamente.
- Grupo especial:
  - o grupo `H` aparece com caução obrigatória no diário.
  - nesse caso o bloco de “Sem proteção” não é exibido como opção normal.
- No plano mensal, o texto exibido é “Proteção básica — incluída”.

## Caução

- Caução diária sem proteção:
  - `A`: `2000`
  - `B`: `4000`
  - `C`: `4000`
  - `D`: `4000`
  - `E`: `4000`
  - `F`: `4000`
  - `G`: `4000`
  - `H`: `4000`
  - `I`: `4000`
- Caução diária com proteção:
  - o fallback padrão encontrado é `500`.
  - para o grupo `H`, o valor reduzido com proteção é `2000`.
- Caução mensal:
  - `A`: `1000` e km `0.75`
  - `B`: `1000` e km `0.75`
  - `C`: `1500` e km `0.60`
  - `D`: `1500` e km `0.60`
  - `E`: `2500` e km `1.00`
  - `F`: `2500` e km `1.00`
  - `G`: `2000` e km `1.00`
  - `I`: `2000` e km `1.00`
  - `J`: `2000` e km `1.00`
  - `H`: `5000` e km `1.50`
- Origem da caução mensal:
  - o código tenta usar `BVGN_IntegracoesPT::obter_mensal_por_grupo()`.
  - essa função é referenciada, mas não foi encontrada no repositório.
  - quando não existe integração, o fallback local acima é usado.

## Opcionais

- Opcionais encontrados em `inclui/IntegracoesPT.php`:
  - `Cadeirinha 0–25 kg (diaria)`
  - `Condutor adicional (diaria)`
  - `Taxa de limpeza (obrigatória)`
- Observações:
  - a taxa de limpeza é tratada como obrigatória na UI.
  - os opcionais são usados no front, no PDF e no export CSV.
  - a integração com `YITH_WAPO` aparece como TODO, mas não está implementada.

## FAQ

- Não foi encontrada uma FAQ estruturada com perguntas e respostas no projeto.
- Não foi encontrado um arquivo de perguntas/respostas para:
  - endereço
  - horário
  - PIX
  - documentação
  - reserva
  - cancelamento
  - proteção
  - motorista adicional
  - limite de km
- Existem textos fixos de apoio em alguns templates, mas não em formato de FAQ.

## Ferramentas

- `[grupo_novo]`
  - finalidade: container principal para montar o bloco modular do produto.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: `div.bvgn-container` com `data-produto-id` e, se houver, `data-produto-grupo`.
  - observações: aceita conteúdo interno com outros shortcodes.

- `[gn_imagem]`
  - finalidade: exibir a imagem destacada do produto.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: HTML da imagem do produto.
  - observações: usa a imagem destacada WooCommerce/WordPress.

- `[gn_titulo]`
  - finalidade: exibir o título do produto.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: `h2.bvgn-titulo`.
  - observações: lê o nome do produto.

- `[gn_descricao]`
  - finalidade: exibir a descrição do produto.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: `div.bvgn-descricao`.
  - observações: aplica `wpautop` e `wp_kses_post`.

- `[gn_variacoes]`
  - finalidade: listar variações do produto.
  - parâmetros: `produto_id` opcional, `type` com `auto|diario|mensal`.
  - retorno esperado: lista de radios com preço, intervalo e descrição.
  - observações: no mensal usa o atributo `franquia-de-km`.

- `[gn_agendamento]`
  - finalidade: mostrar os campos de datas do produto.
  - parâmetros: `produto_id` opcional, `type` com `auto|diario|mensal`.
  - retorno esperado: bloco de agendamento diário ou mensal.
  - observações: diário tem retirada e devolução; mensal tem só retirada.

- `[gn_taxas]`
  - finalidade: listar taxas e serviços opcionais.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: cards com checkbox/radio, preços e ícones.
  - observações: inclui a taxa fixa mensal quando aplicável.

- `[gn_protecao]`
  - finalidade: exibir o bloco de proteção do plano diário.
  - parâmetros: `produto_id` opcional.
  - retorno esperado: radios de proteção com caução.
  - observações: só renderiza para plano diário.

- `[gn_totais]`
  - finalidade: exibir o resumo de valores.
  - parâmetros: `produto_id` opcional, `type` com `auto|diario|mensal`.
  - retorno esperado: resumo lateral e campos ocultos.
  - observações: abastece o modal com os valores crus.

- `[gn_informacoes]`
  - finalidade: campo de informações adicionais.
  - parâmetros: aceita `placeholder`.
  - retorno esperado: não foi encontrado retorno funcional.
  - observações: a implementação atual retorna string vazia.

- `[gn_botao_cotacao]`
  - finalidade: botão simples para gerar cotação e abrir WhatsApp.
  - parâmetros: `produto_id`, `format` com `html|pdf`, `phone`.
  - retorno esperado: botão com `data-formato` e `data-telefone`.
  - observações: o fluxo real depende do JavaScript do modal.

- `[gn_botao_cotacao_popup]`
  - finalidade: abrir o modal de cotação.
  - parâmetros: `produto_id`, `rotulo`, `classe`.
  - retorno esperado: botão e modal acessível.
  - observações: inclui o modal apenas uma vez por página.

- `[bvgn_agendamento_header]`
  - finalidade: renderizar o calendário de cabeçalho desktop.
  - parâmetros: nenhum obrigatório.
  - retorno esperado: campos de local, retirada, devolução e botão “Buscar carros”.
  - observações: usa `flatpickr` no front.

- `[bvgn_agendamento_header_mobile]`
  - finalidade: renderizar o calendário de cabeçalho mobile.
  - parâmetros: nenhum obrigatório.
  - retorno esperado: versão mobile dos campos do cabeçalho.
  - observações: mesma lógica do desktop com IDs próprios.

- `GET /wp-json/bv/v1/veiculos`
  - finalidade: listar produtos/veículos publicados.
  - parâmetros: `page`, `per_page`.
  - retorno esperado: paginação com itens básicos de produto.
  - observações: exige Bearer token.

- `GET /wp-json/bv/v1/veiculos/{id}/regras`
  - finalidade: retornar regras comerciais de um produto.
  - parâmetros: `id`.
  - retorno esperado: variações, taxas base, proteção, caução e tarifa dinâmica.
  - observações: exige Bearer token e WooCommerce ativo.

- `wp_ajax_bvgn_gerar_arquivo`
  - finalidade: receber os dados da cotação, criar o registro no CPT e gerar PDF.
  - parâmetros: dados do formulário, taxas, totais, datas, nome, WhatsApp, local e mensagem.
  - retorno esperado: JSON com URL do PDF ou erro.
  - observações: existe também `wp_ajax_nopriv_bvgn_gerar_arquivo`.

- `admin-post.php?action=bvgn_save_tariffs`
  - finalidade: salvar regras de tarifa dinâmica.
  - parâmetros: `rules[...]`.
  - retorno esperado: persistência na option `bvgn_dynamic_tariffs`.
  - observações: acessível apenas para `manage_options`.

- Exportação CSV da listagem `bvgn_cotacao`
  - finalidade: exportar cotações para CSV.
  - parâmetros: ação em massa `bvgn_export_selected` ou query `bvgn_export_all=csv`.
  - retorno esperado: download de CSV com cabeçalhos e linhas por post.
  - observações: usa `;` como separador e inclui BOM UTF-8.

- Evento `bvgn:cotacaoModalSubmit`
  - finalidade: notificar outros listeners quando o modal é enviado.
  - parâmetros: `detail` com `produto_id`, `nome`, `whatsapp`, `whatsapp_destino`, `mensagem` e `nonce`.
  - retorno esperado: evento JavaScript.
  - observações: não é endpoint, é evento de integração.

- `buscar_veiculos`
  - finalidade: não encontrado no repositório.
  - parâmetros: não aplicável.
  - retorno esperado: não aplicável.
  - observações: o equivalente funcional encontrado é o endpoint REST `GET /wp-json/bv/v1/veiculos`.

## Estrutura dos Dados

- WordPress/WooCommerce:
  - produtos WooCommerce representam os veículos/grupos.
  - categorias `product_cat` definem diário ou mensal.
  - variações do produto representam planos/faixas.
- CPT:
  - `bvgn_cotacao`
  - campos usados na listagem administrativa e exportação.
- Post meta encontrados:
  - `produto_id`
  - `cliente_nome`
  - `cliente_whats`
  - `variacao_rotulo`
  - `datas_inicio`
  - `datas_fim`
  - `totais_base`
  - `totais_taxas`
  - `totais_dynamic_extra`
  - `totais_qtd`
  - `totais_subtotal`
  - `totais_total`
  - `totais_tipo`
  - `totais_caucao`
  - `local_retirada`
  - `mensagem`
  - `codigo`
  - `pdf_url`
- Post meta de produto:
  - `_bvgn_grupo`
- Options:
  - `bvgn_dynamic_tariffs`
  - `bvgn_api_token`
- Constantes referenciadas:
  - `BVGN_WHATS_DESTINO`
  - `BVGN_DIR`
  - `BVGN_URL`
  - `BVGN_CAMINHO`
  - `BVGN_DIR_ARQUIVOS`
  - `BVGN_URL_ARQUIVOS`
  - `BVGN_USAR_TEMPLATE`
  - `BVGN_API_TOKEN`
- Endpoints e arquivos de dados:
  - REST `bv/v1`
  - AJAX `bvgn_gerar_arquivo`
  - CSV com download direto
  - PDF gerado em `wp-content/uploads/grupo-novo/`
- Arquivos JSON:
  - não foram encontrados arquivos JSON do domínio do plugin.
- Planilhas:
  - não foram encontradas planilhas no repositório.
- Banco de dados:
  - não foi encontrada criação de tabela customizada.
  - a persistência usa `posts`, `post_meta` e `options` do WordPress.

## Estados do Atendimento

- Estado explícito de máquina de atendimento:
  - não encontrado.
- Estados observados no fluxo:
  - aguardando datas
  - aguardando variação
  - aguardando proteção/opcionais
  - modal aberto
  - gerando PDF
  - fallback para WhatsApp sem PDF
  - erro ao gerar arquivo

## Regras de Negócio

- Diária:
  - a categoria detectada é `aluguel-de-carros-diaria`.
  - a variação é selecionada por faixa de dias.
  - o período máximo trabalhado no front é 30 dias.
  - domingos são bloqueados no calendário.
  - proteções existem apenas no plano diário.
  - a tarifa dinâmica só entra no diário.
  - opcionais como cadeirinha e condutor adicional multiplicam por dias quando marcado como diário.
- Mensal:
  - a categoria detectada é `aluguel-de-carros-mensal`.
  - a variação depende de `franquia-de-km`.
  - a devolução é apresentada como 30 dias.
  - proteção básica é mostrada como incluída.
  - caução e km excedente têm mapa por grupo.
- Grupos:
  - o grupo é inferido por meta ou pelo título do produto.
  - o grupo `H` tem tratamento especial com caução obrigatória no diário.
- Similares:
  - o termo aparece apenas como conceito textual de produto/título.
  - não foi encontrada lógica própria de “similares”.
- Pagamento:
  - não foi encontrada integração de pagamento.
- Reserva:
  - o texto do PDF usa “pré-reserva”.
  - não foi encontrada criação real de reserva no banco ou em WooCommerce.
- WhatsApp:
  - o destino padrão é fixo no plugin.
  - o modal monta uma mensagem e tenta abrir WhatsApp com o link do PDF.
- PDF:
  - o PDF é gerado com Dompdf quando `vendor/autoload.php` existe.
  - se o PDF não puder ser gerado, o fluxo cai para mensagem direta.
- CSV:
  - exporta campos de cotações já persistidas no CPT.
- Tarifa dinâmica:
  - existe uma interface de administração para cadastrar regras por tipo, percentual, prioridade, datas e grupos.
  - no front, a regra escolhida é a de maior prioridade entre as que batem.
  - a implementação usa a data de início como referência.

## Melhorias Futuras

- Há duplicação de lógica entre os scripts novos e os arquivos legados `*-old.js`.
- Há duplicação de regras de caução e proteção em múltiplos lugares:
  - `inclui/ApiRest.php`
  - `modelos/partes/taxa-variavel-diaria.php`
  - `modelos/partes/taxa-fixa-mensal.php`
  - `assets/js/grupo-novo.js`
  - `assets/js/bvgn-modal.js`
  - `modelos/partes/cotacao.php`
- Há inconsistência entre documentação e implementação em alguns pontos:
  - documentação antiga menciona categorias `diarias` e `mensal`, enquanto o código usa `aluguel-de-carros-diaria` e `aluguel-de-carros-mensal`.
  - `obter_mensal_por_grupo()` é referenciada, mas não existe no repositório.
  - a lógica e os comentários da tarifa dinâmica não estão totalmente alinhados.
  - o cabeçalho permite até 60 dias no calendário, mas redireciona para mensal acima de 30.
- Há regras espalhadas no código que poderiam ser centralizadas:
  - mapa de grupo para cor
  - mapa de caução diária
  - mapa de caução mensal
  - preços de proteção
  - textos fixos de PDF
  - slugs de categorias
- Há informações que hoje vivem em prompt/documentação e deveriam estar documentadas em fonte única:
  - telefone fixo de destino do WhatsApp
  - endereço exibido no PDF
  - logo do PDF
  - regras de grupo e valores padrão
  - texto de pré-reserva
- O shortcode `[gn_informacoes]` está vazio e merece definição funcional ou remoção.
- O fluxo de reserva/pagamento não existe e pode ser documentado externamente para evitar expectativa errada.
