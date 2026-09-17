# Histórico de Tarifas — 9.9.122

## Histórico diário

A partir da 9.9.122, o relatório é uma fotografia diária: `data + grupo + valores de 1, 3, 7 e 15 dias`.

O relatório também registra Proteção Básica e Premium **por diária**, sem caução,
e Limpeza como **taxa única**, separadas dos totais de aluguel. As proteções seguem
a tabela atual de `modelos/partes/taxa-variavel-diaria.php`: A/B/C = 36,90/66,90;
D/E/F/G/I = 47,90/86,90; H = 68,90/128,90 (básica/premium). Grupo desconhecido
usa a faixa A. Ao mudar esses preços, atualizar também `protection_rates()` em
`inclui/TariffHistory.php`. A limpeza reutiliza a função local
`BVGN_IntegracoesPT::obter_taxas_para_produto()` (atualmente 48,90).
O percentual dinâmico não incide nessas três colunas.

O esquema 7 acrescenta três colunas opcionais à tabela diária, preservando os
registros existentes. Valores ausentes/nulos aparecem como “Indisponível”; zero
aparece como “R$ 0,00”. Não há preenchimento retroativo com preços atuais.
O botão de geração existente atualiza apenas a fotografia de hoje. A leitura
continua paginada, sem consultas de taxas por linha ou chamadas HTTP adicionais.

Referência da reimplementação: taxas do commit `e70c71b`, adaptadas sobre
`ce52456`, sem restaurar o commit inteiro. O merge `6408a55` continha marcadores
de conflito nos três arquivos PHP do histórico; `php -l` reproduz erro de sintaxe
em `TariffHistory.php`. Como esses arquivos são carregados pelo plugin, isso é
uma causa provável da indisponibilidade anterior, sem confirmação por logs do servidor.

Validação local das taxas: `php tests/tariff-history-fees-test.php`,
`php tests/tariff-history-schema-test.php` e `php tests/tariff-history-test.php`.
São testes isolados, sem WordPress/MySQL real. Em homologação:

1. Abrir o histórico antes de gerar: conferir registros antigos, filtros,
   paginação e busca sem resultados, sem erros PHP ou SQL.
2. Gerar o histórico de hoje e conferir A, D/I e H contra os valores acima;
   limpeza 48,90, com indicação de diária/única. Gerar novamente sem duplicar linhas.
3. Conferir 1/3/7/15 dias com e sem tarifa dinâmica: taxas permanecem separadas
   e não recebem o percentual. Conferir também registros com taxas NULL e zero
   em uma base de teste.
4. Abrir páginas diária/mensal e gerar uma cotação/PDF; comparar valores e
   comportamento com a versão anterior. Verificar logs e tempo de carregamento.

Os eventos técnicos das versões 9.9.120/9.9.121 continuam preservados na tabela antiga
`{prefix}bv_historico_tarifas`; nada é apagado. A nova tabela é
`{prefix}bv_historico_diario_tarifas`, com índice único em `(grupo_id, data_referencia)`.

O WP-Cron agenda `bvgn_registrar_historico_diario_tarifas` para 00:10 no fuso do WordPress.
Ele processa todos os grupos diários ativos e usa `INSERT ... ON DUPLICATE KEY UPDATE`, portanto
uma segunda execução ou uma alteração no mesmo dia atualiza a única linha existente. Mudanças de
preço de variações e da opção de tarifa dinâmica atualizam a fotografia do dia corrente.

Para instalações sem visitas frequentes, configure o cron real do servidor para chamar
`wp-cron.php`: o WP-Cron só dispara após uma requisição. Ao voltar a executar, o plugin recupera
as datas pendentes; a regra dinâmica é avaliada para a própria data de retirada, inclusive se a
regra tiver expirado depois dela. Não é possível reconstruir retrospectivamente uma tarifa-base
que tenha sido alterada enquanto o site estava sem executar o processo, por isso o cron real é
recomendado para garantia operacional.

## Tarifa dinâmica: regra vigente no plugin

Cadastro: **Cotações → Tarifa Dinâmica**, classe `BVGN_DynamicTariffs`. A action
`admin_post_bvgn_save_tariffs` chama `replace_rules()`, que sanitiza, ordena e salva a option
`bvgn_dynamic_tariffs`. A API REST atual apenas expõe regras para leitura. IDs são estáveis, gerados pela
option `bvgn_dynamic_tariffs_next_id`.

O cálculo efetivo está em **`assets/js/bvgn-dynamic.js`**, função `calcularTarifaDinamica`.
`grupo-novo.js` e `grupos-homologacao.js` usam esse motor. Não existe calculador PHP
equivalente no projeto. Apesar de um comentário antigo mencionar “dia a dia”, o motor:

1. Considera apenas a **data de retirada**.
2. Filtra regras ativas pelo grupo (letra) e por dia da semana, data específica ou intervalo
   inclusivo. Grupos vazios no cadastro são expandidos para A–J pelo serializador existente.
3. Escolhe somente a maior prioridade; em empate mantém a primeira. A ordenação PHP coloca
   menor ID primeiro no empate. O cadastro alerta sobre sobreposições, mas permite salvá-las.
4. Calcula `Math.round(baseDia * (1 + percentual / 100))` e aplica essa diária ajustada
   a **todos os dias da locação**, mesmo que a devolução ultrapasse a vigência.
5. Retorna o adicional `(diária ajustada - baseDia) * quantidade`. A cotação soma esse
   adicional ao subtotal-base e às taxas. As flags de resumo/PDF só controlam apresentação.

A dinâmica ajusta a diária da faixa geral selecionada. Não há quatro preços próprios na
regra dinâmica, apenas percentual. A base muda conforme a faixa de 1, 3, 7 ou 15 dias.
Sem regra válida ou com base não positiva, não há adicional. `get_rules()` também desativa
e persiste regras cujo `end_date` já passou, na próxima leitura; esse comportamento foi mantido.

## Evolução na 9.9.121

O histórico dinâmico preserva **os dados de entrada completos**, antes e depois: faixas e
preços unitários com precisão original, regras ordenadas (inclusive concorrentes), grupo,
vigência, percentual, prioridade e estado. O JSON `contexto` é imutável. Os quatro valores
das linhas dinâmicas são **calculados na exibição pelo mesmo arquivo JavaScript público**,
sem consultar preços ou regras atuais. As colunas monetárias SQL continuam sendo utilizadas
pelos registros gerais; nas linhas dinâmicas permanecem NULL. Não há fórmula dinâmica PHP
duplicada nem necessidade de Node.js no servidor WordPress.

Cada linha apresenta retirada de referência editável: início da vigência ou próxima
ocorrência semanal a partir da data do evento. Os quatro totais referem-se a essa mesma
retirada. O relatório mostra a regra realmente vencedora antes/depois, inclusive quando
é outra regra ou a Geral. **Não se presume que todo o intervalo tenha o mesmo valor.**
Consultar outra retirada não grava evento nem muda dados. A comparação monetária usa a
mesma retirada nos dois estados; mudar vigência pode manter o valor ou alterar a aplicação.

São observadas gravações bem-sucedidas em `update_option_bvgn_dynamic_tariffs` e
`add_option_bvgn_dynamic_tariffs`; a exclusão integral usa `delete_option` para obter o
estado anterior e `deleted_option` para registrar somente após sucesso. Remover uma regra
da lista gera evento de exclusão. Desativação/reativação, inclusive desativação automática
por vencimento, ficam registradas. O histórico não chama `get_rules()`, evitando novas
mutações ao observar a option. `rules_for_js()` foi extraído de `for_js()` sem mudar sua saída.

A comparação canônica por ID ignora ordem de grupos/regras e diferenças de representação
numérica. Salvar sem alteração não gera evento. Alterações de nome, exibição, grupo ou
prioridade também são auditadas, mesmo que o preço final não mude. Para mudança de grupo,
é considerada a união dos grupos anteriores/novos. Uma alteração geral também gera
contextos dinâmicos para as regras associadas, preservando a base anterior e a nova.
Não há carga retroativa de regras existentes; a captura começa com a atualização.

Eventos são gravados por regra e produto diário associado. Regras sem produto diário
associado não produzem linha de preços por produto. A exclusão mantém a regra anterior
e calcula o resultado com as demais regras após a remoção. Datas inválidas não são
inventadas: a linha pede uma retirada válida para permitir a comparação.

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
| tipo | varchar(16), padrão 'geral' |
| regra_id | bigint unsigned, padrão 0 |
| evento | varchar(24): criada, alterada, desativada, ativada, excluida, base_alterada |
| contexto | longtext JSON nullable, entradas congeladas para as linhas dinâmicas |

Schema 2: o upgrade via `dbDelta` acrescenta as colunas à mesma tabela, sem apagar/recriar
registros. O default `tipo='geral'` classifica automaticamente os dados da 9.9.120.
IDs, datas, usuários e valores anteriores permanecem intactos. A versão só é atualizada
após verificar as colunas; falhas deixam a migração pendente para nova tentativa.
As consultas de “último geral” filtram explicitamente `tipo='geral'`, evitando comparação
com linhas dinâmicas intercaladas.

Índices: `(grupo_id,id)`, `(grupo_id,tipo,regra_id,id)` e `(data_alteracao,id)`. Não há duplicação de nomes de produtos
ou usuários. Não há remoção automática do histórico ao desativar o plugin.

## Relatório e acesso

**Cotações → Histórico de Tarifas**, restrito à capability `manage_options`, como a tela
de tarifa dinâmica existente. `TariffHistoryAdmin.php` oferece grupo, tipo (Todas/Geral/Dinâmica), datas de alteração inclusivas,
limpeza de filtros, 50 registros por página e ordenação `data_alteracao DESC, id DESC`.
Compara com o registro anterior do mesmo produto mesmo que esteja fora da página ou
do intervalo filtrado. GET somente leitura, inputs sanitizados, datas validadas, SQL
parametrizado e HTML escapado. Nenhuma rota pública ou campo editável de preço foi criado.
As linhas dinâmicas mostram ID da regra, evento, situação cadastrada, vigência anterior/nova,
retirada consultada e regra vencedora. O JavaScript administrativo é carregado somente nessa
tela e usa `textContent` para os textos dos registros. Sem JavaScript, os valores dinâmicos
não podem ser calculados e a tela solicita sua ativação.

## Validação

Testes executáveis:

- `php tests/tariff-history-test.php`: geral com/sem mudança, criação dinâmica, percentual,
  período, repetição, desativação, reativação, exclusão individual/integral, contexto de
  concorrentes, base alterada, legado, vencimento automático e mudança de grupo.
- `node tests/tariff-history-engine-test.js`: lê os contextos produzidos pelo teste PHP,
  carrega o motor público real e compara antes/depois para 1, 3, 7 e 15 dias; verifica
  limites de vigência, sobreposição, empate, recorrência semanal, data única e grupo.
- `php tests/tariff-history-schema-test.php`: contrato do SQL, prefixo, defaults compatíveis
  com legado, preservação das colunas antigas, idempotência e repetição após falha.

PHP utiliza substitutos de WordPress/WooCommerce e banco. O teste de schema verifica o
contrato da migração, não executa `ALTER TABLE` real. Node executa o JavaScript público real.

Comparação prática (Grupo A, retirada 15/09/2026, acréscimo de 20%):

| Dias | Base unitária | Motor público | Histórico |
| --- | --- | --- | --- |
| 1 | R$ 149,00 | R$ 179,00 | R$ 179,00 |
| 3 | R$ 150,00 | R$ 540,00 | R$ 540,00 |
| 7 | R$ 130,00 | R$ 1.092,00 | R$ 1.092,00 |
| 15 | 116,666666 | R$ 2.100,00 | R$ 2.100,00 |

Motor público e arquivos públicos de cotação permanecem inalterados. Em `DynamicTariffs.php`,
somente a serialização existente foi extraída para um método reutilizável.

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
8. Atualizar uma cópia do banco 9.9.120 e conferir contagem/valores antigos; filtro Geral
   não deve incluir linhas dinâmicas. Validar instalação nova também.
9. Cadastro dinâmico real: criar, mudar percentual, prioridade, grupos e vigência;
   salvar igual; desativar, reativar, remover e deixar vencer. Testar regras concorrentes
   e consultar retiradas diferentes dentro/fora da vigência no relatório.
10. Comparar totais de locação sem opcionais/proteções contra uma cotação real do catálogo
    para 1/3/7/15 dias. Confirmar produtos sem estoque, plugins de preços e faixas reais.

Limites: SQL direto que contorna os hooks não é observado. Requisições interrompidas antes
de `shutdown` ou falhas de banco podem perder eventos (logados quando detectáveis).
Vários salvamentos do mesmo produto dentro de uma requisição geram o estado final,
sem estados intermediários. Exclusão direta de posts, edição global de termos de atributos
e alterações de categoria fora de um salvamento WooCommerce não são gatilhos dedicados.
Sem acesso ao WordPress/banco de produção, esses testes de integração ficam pendentes.
Eventos dinâmicos gravam na alteração da option; gerais são consolidados em `shutdown`.
Falhas de INSERT são logadas, sem fila automática de recuperação. O contexto tem versão 1;
se o motor público mudar no futuro, será necessário manter a interpretação dessa versão
para não reinterpretar os registros antigos sob novas regras. Este trabalho não mudou o motor.

Referência do ciclo de salvamento consultada:
[WooCommerce WC_Data::save](https://woocommerce.github.io/code-reference/files/woocommerce-includes-abstracts-abstract-wc-data.html).
