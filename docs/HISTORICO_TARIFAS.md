# Histórico de Tarifas — 9.9.120

## Origem e interpretação

As tarifas gerais são cadastradas em Produtos → editar produto → Variações, no WooCommerce.
O plugin não possui outro cadastro de tarifa geral: `modelos/partes/variacoes.php` lê o preço
ativo das variações (`get_price()`, armazenado em `_price`) e deriva as faixas dos atributos.
`assets/js/grupo-novo.js` seleciona a primeira faixa correspondente (ou a de maior limite
como fallback) e calcula preço por diária × dias. O relatório observa esses totais-base
para 1, 3, 7 e 15 dias, em reais e com duas casas, sem alterar o cálculo público.

`grupo_id` é o ID do produto pai, identidade persistente do cadastro. O nome vem do título
atual; a identificação comercial usa `_bvgn_grupo` ou `Grupo X` no título. Produtos com
a mesma letra permanecem separados pelo ID. Renomear não reescreve o histórico; títulos
antigos não são preservados. Produtos excluídos aparecem como “Grupo removido (#ID)”.

Somente produtos da categoria `aluguel-de-carros-diaria`, sem a categoria mensal, são
observados. São lidas variações publicadas, ordenadas por `menu_order, ID`, com `_price`
numérico. Estoque e filtros de preço em tempo de execução não compõem a tarifa cadastrada;
o relatório pode diferir da disponibilidade pública ou de preços personalizados por plugins.
Sem variação com preço, o valor é NULL (“Indisponível”), diferente de uma tarifa zero.

Não foi encontrado histórico compatível: o CPT `bvgn_cotacao` guarda cotações, e a option
`bvgn_dynamic_tariffs` guarda regras de adicionais dinâmicos, sem histórico de tarifas gerais.

## Captura e persistência

- `TariffHistory.php`: captura o estado anterior nos hooks
  `woocommerce_before_product_object_save` e `woocommerce_before_product_variation_object_save`.
  Também observa os filtros `add_post_metadata`, `update_post_metadata` e `delete_post_metadata`
  para preços e atributos de variações, abrangendo integrações que usam a API de metadados.
  Os filtros sempre devolvem o valor recebido e não modificam o salvamento.
- Em `shutdown`, prioridade 100, reúne as alterações da requisição por produto e relê os
  valores persistidos. Não há cron de coleta, registro diário, migração de preços antigos
  ou carga inicial do catálogo. Primeiro salvar sem alteração também não gera registro.
- `TariffHistoryRepository.php`: compara os quatro valores normalizados em duas casas;
  exige diferença antes/depois e em relação ao último registro. Uma volta a preço antigo
  gera registro se for diferente do mais recente. Usa bloqueio MySQL por produto para
  serializar leitura final/comparação/inserção. Falhas de instalação, bloqueio ou gravação
  são registradas no log PHP; não são feitas tentativas de alterar os preços.
- Usuário é capturado antes da alteração; zero indica sistema/não identificado. Data é
  gravada em UTC e exibida/filtrada no fuso do WordPress.

Tabela `{prefix}bv_historico_tarifas`, criada por `dbDelta` na ativação ou atualização em
`admin_init` (também verificada antes de gravar). Versão de schema na option
`bvgn_tariff_history_db_version`:

| Campo | Tipo |
| --- | --- |
| id | bigint unsigned, chave primária, auto incremento |
| grupo_id | bigint unsigned, ID do produto pai |
| valor_1_dia, valor_3_dias, valor_7_dias, valor_15_dias | decimal(18,2), nullable |
| data_alteracao | datetime UTC |
| usuario_id | bigint unsigned, padrão 0 |

Índices: `(grupo_id,id)` e `(data_alteracao,id)`. Não há duplicação de nomes de produtos
ou usuários. Não há remoção automática do histórico ao desativar o plugin.

## Relatório e acesso

**Cotações → Histórico de Tarifas**, restrito à capability `manage_options`, como a tela
de tarifa dinâmica existente. `TariffHistoryAdmin.php` oferece grupo, datas inclusivas,
limpeza de filtros, 50 registros por página e ordenação `data_alteracao DESC, id DESC`.
Compara com o registro anterior do mesmo produto mesmo que esteja fora da página ou
do intervalo filtrado. GET somente leitura, inputs sanitizados, datas validadas, SQL
parametrizado e HTML escapado. Nenhuma rota pública ou campo editável de preço foi criado.

## Validação

Teste isolado: `php tests/tariff-history-test.php`. Usa substitutos de WordPress/WooCommerce
e banco para verificar o comportamento; não substitui integração com a instalação real.

Antes de publicar, validar em homologação:

1. Atualização de plugin ativo e ativação limpa: tabela com prefixo correto, vazia.
2. Produto diário real: salvar sem mudança não registra; alterar uma faixa registra os
   quatro totais corretos; alterar várias faixas no mesmo AJAX gera apenas um registro.
3. Salvar novamente, mudar apenas descrição e alterar preço regular com promoção ativa:
   não registrar enquanto os quatro totais ativos permanecerem iguais.
4. Promoção ativa, início/fim agendados, edição em massa e importação/API: registrar
   quando `_price` efetivamente mudar. Confirmar atribuição de usuário/sistema.
5. Preços fracionários, faixas reais, ordem das variações, fallback, variações sem preço
   e produtos mensais. Conferir os totais contra os preços-base atuais do site.
6. Filtros, limite de dia no fuso local, paginação acima de 50 linhas, valores anteriores
   fora dos filtros, produto/usuário removido e bloqueio de acesso sem `manage_options`.
7. Salvamentos concorrentes e permissões do banco para `CREATE/ALTER`, `GET_LOCK` e INSERT.

Limites: SQL direto que contorna os hooks não é observado. Requisições interrompidas antes
de `shutdown` ou falhas de banco podem perder eventos (logados quando detectáveis).
Vários salvamentos do mesmo produto dentro de uma requisição geram o estado final,
sem estados intermediários. Exclusão direta de posts, edição global de termos de atributos
e alterações de categoria fora de um salvamento WooCommerce não são gatilhos dedicados.
Sem acesso ao WordPress/banco de produção, esses testes de integração ficam pendentes.

Referência do ciclo de salvamento consultada:
[WooCommerce WC_Data::save](https://woocommerce.github.io/code-reference/files/woocommerce-includes-abstracts-abstract-wc-data.html).
