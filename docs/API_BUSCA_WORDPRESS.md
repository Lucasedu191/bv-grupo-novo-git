# API de Consulta de Veiculos (WordPress/WooCommerce)

Documentacao para integracao de leitura com os endpoints REST do plugin `BV Grupo Novo`.

## 1. Objetivo

Permitir que sistemas externos (ex.: assistente de WhatsApp) consultem:

- lista de veiculos/produtos publicados
- regras comerciais por veiculo (planos, variacoes, taxas, protecao, caucao e tarifa dinamica)

Base:

`https://bvlocadora.com.br/wp-json/bv/v1`

## 2. Autenticacao

Todos os endpoints exigem Bearer Token:

```http
Authorization: Bearer wordpress_token_abc123
```

Sem token, token invalido ou token nao configurado, a API retorna `401`.

Fonte do token no WordPress (ordem de prioridade):

1. Constante `BVGN_API_TOKEN`
2. Option `bvgn_api_token`

## 3. Endpoints Disponiveis

### 3.1 Listar veiculos

`GET /veiculos`

URL completa:

`https://bvlocadora.com.br/wp-json/bv/v1/veiculos`

Query params:

- `page` (opcional, int, default `1`)
- `per_page` (opcional, int, default `50`, max `100`)

Exemplo:

```bash
curl --request GET "https://bvlocadora.com.br/wp-json/bv/v1/veiculos?page=1&per_page=20" \
  --header "Authorization: Bearer wordpress_token_abc123"
```

Exemplo de resposta:

```json
{
  "pagina": 1,
  "por_pagina": 20,
  "total": 158,
  "total_paginas": 8,
  "itens": [
    {
      "id": 123,
      "nome": "Grupo A - Hatch",
      "slug": "grupo-a-hatch",
      "preco": 189.9,
      "preco_promocional": null,
      "descricao_curta": "Descricao curta do produto.",
      "link_produto": "https://bvlocadora.com.br/produto/grupo-a-hatch/",
      "imagem_principal": "https://bvlocadora.com.br/wp-content/uploads/2026/01/carro.jpg",
      "categorias": [
        { "id": 22, "nome": "Aluguel de carros diaria", "slug": "aluguel-de-carros-diaria" }
      ],
      "status_estoque": "instock",
      "tipo_produto": "variable",
      "variacoes_basicas": [
        {
          "id": 456,
          "nome": "Grupo A - Hatch - 03 a 06 Dias",
          "preco": 189.9,
          "preco_promocional": null,
          "status_estoque": "instock",
          "atributos": {
            "periodo": "03-06-dias"
          }
        }
      ]
    }
  ]
}
```

### 3.2 Regras de um veiculo

`GET /veiculos/{id}/regras`

URL completa (exemplo):

`https://bvlocadora.com.br/wp-json/bv/v1/veiculos/123/regras`

Exemplo:

```bash
curl --request GET "https://bvlocadora.com.br/wp-json/bv/v1/veiculos/123/regras" \
  --header "Authorization: Bearer wordpress_token_abc123"
```

Exemplo de resposta:

```json
{
  "produto_id": 123,
  "produto_nome": "Grupo A - Hatch",
  "grupo": "A",
  "tipo_plano": "diario",
  "variacoes": [
    {
      "id": 456,
      "rotulo": "03-06 Dias",
      "preco": 189.9,
      "preco_regular": 189.9,
      "preco_promocional": null,
      "min_dias": 3,
      "max_dias": 6,
      "descricao": ""
    }
  ],
  "taxas_base": [
    { "rotulo": "Cadeirinha 0-25 kg (diaria)", "preco": 20, "icone": "assets/svg/passos01.svg" },
    { "rotulo": "Condutor adicional (diaria)", "preco": 20, "icone": "assets/svg/passos02.svg" },
    { "rotulo": "Taxa de limpeza (obrigatoria)", "preco": 45, "icone": "assets/svg/passos03.svg" }
  ],
  "taxa_limpeza": { "rotulo": "Taxa de limpeza (obrigatoria)", "preco": 45, "icone": "assets/svg/passos03.svg" },
  "cadeirinha": { "rotulo": "Cadeirinha 0-25 kg (diaria)", "preco": 20, "icone": "assets/svg/passos01.svg" },
  "condutor_adicional": { "rotulo": "Condutor adicional (diaria)", "preco": 20, "icone": "assets/svg/passos02.svg" },
  "protecao": [
    { "tipo": "basica", "preco_dia": 35, "caucao": 500 },
    { "tipo": "premium", "preco_dia": 65, "caucao": 500 }
  ],
  "caucao_diaria": {
    "grupo": "A",
    "caucao_sem_protecao": 2000,
    "caucao_com_protecao": 500
  },
  "caucao_mensal": {
    "grupo": "A",
    "caucao": 1000,
    "km_preco": 0.75,
    "origem": "fallback_local"
  },
  "tarifa_dinamica": []
}
```

## 4. Onde estao os valores de opcionais/taxas

No endpoint `/veiculos/{id}/regras`, os principais campos sao:

- Cadeirinha: `cadeirinha.preco`
- Condutor adicional: `condutor_adicional.preco`
- Taxa de limpeza: `taxa_limpeza.preco`
- Protecao basica/premium: `protecao[].preco_dia`
- Caucao diaria: `caucao_diaria.caucao_sem_protecao` e `caucao_diaria.caucao_com_protecao`
- Caucao mensal e km excedente: `caucao_mensal.caucao` e `caucao_mensal.km_preco`

## 5. Codigos HTTP esperados

- `200` sucesso
- `401` token ausente/invalido/nao configurado
- `404` produto nao encontrado ou nao publicado
- `503` WooCommerce inativo

## 6. Fluxo recomendado para chatbot

1. Consultar `/veiculos` para listar opcoes.
2. Cliente escolhe veiculo (id).
3. Consultar `/veiculos/{id}/regras`.
4. Exibir resposta curta com preco base + opcionais relevantes.

## 7. Exemplo rapido (Postman)

Metodo: `GET`  
URL: `https://bvlocadora.com.br/wp-json/bv/v1/veiculos/123/regras`  
Headers:

- `Authorization: Bearer wordpress_token_abc123`
- `Accept: application/json`

## 8. Observacoes importantes

- Esta API e de leitura nesta etapa.
- Nao cria pedido, pre-reserva ou pagamento.
- Campos podem vir `null` dependendo da configuracao do produto.
- Sempre tratar campos opcionais no consumidor.

## 9. Referencia no plugin

- Endpoints e autenticacao: `inclui/ApiRest.php`
- Bootstrap do plugin: `bv-grupo-novo.php`
