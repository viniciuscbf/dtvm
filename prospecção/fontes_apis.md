# Fontes e APIs — prospecção por CNPJ

APIs públicas testadas (jul/2026) para montar e **cruzar** a lista de instituições.
As três primeiras são as usadas pelo `build_lista.py`; as demais permitem enriquecer mais.

## Usadas no script

### 1. CVM — Cadastro de administradores de carteira (quem tem a licença)
- **ZIP:** https://dados.cvm.gov.br/dados/ADM_CART/CAD/DADOS/cad_adm_cart.zip
- Contém `cad_adm_cart_pj.csv` (3.033 PJs; encoding **latin-1**, separador `;`).
- Campos-chave: `CNPJ`, `DENOM_SOCIAL`, `CATEG_REG` (Administrador Fiduciário / Gestor),
  **`SUBCATEG_REG`** (= "Instituição Financeira" isola os bancos/DTVMs), `SIT`, `VL_PATRIM_LIQ`,
  `MUN`, `UF`, `EMAIL`, `DDD`, `TEL`, `SITE_ADMIN`.
- Também há `ADM_FUNDO`, `GESTOR`, etc. no mesmo portal: https://dados.cvm.gov.br/

### 2. BCB — IFDATA (instituições autorizadas + segmento + UF)
- **API OData (JSON):**
  `https://olinda.bcb.gov.br/olinda/servico/IFDATA/versao/v1/odata/IfDataCadastro(AnoMes=@AnoMes)?@AnoMes=202506&$format=json&$top=5000`
- Campos: `CodInst`, `NomeInstituicao`, `Tcb` (segmento), `Uf`, `Municipio`, `CnpjInstituicaoLider` (raiz 8 díg.), `Situacao`.
- **Ativo total / financeiro** (evolução futura): endpoint `IfDataValores(...)` no mesmo serviço —
  filtra por tipo de instituição + relatório; permite ranquear por **ativo total** (melhor proxy de porte).
- Painel: https://www3.bcb.gov.br/ifdata/

### 3. CNPJ — Receita Federal (enriquecimento: CNAE, porte, capital, situação)
- **BrasilAPI:** `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` — rápido, sem chave. Dá `cnae_fiscal`,
  `cnae_fiscal_descricao`, `porte`, `capital_social`, `descricao_situacao_cadastral`, sócios (`qsa`),
  endereço, `ddd_telefone_1`, e-mail.
- **Minha Receita (fallback):** `https://minhareceita.org/{cnpj}` — mesma base da Receita, robusto para volume.

## Outras APIs de CNPJ para cruzar mais informações

| Fonte | URL | O que agrega | Nota |
|---|---|---|---|
| **ReceitaWS** | `https://receitaws.com.br/v1/cnpj/{cnpj}` | dados cadastrais + QSA | grátis com **rate limit baixo** (3/min); tem plano pago |
| **CNPJá** | `https://api.cnpja.com/office/{cnpj}` | cadastral + Simples + IE + mapas | precisa de token; camada grátis |
| **Casa dos Dados** | https://casadosdados.com.br/ | busca por CNAE/UF/porte em massa | bom para **descobrir** empresas por filtro (ex.: todas CNAE 6612) |
| **Receita — Dados Abertos (bulk)** | https://dados.gov.br/dados/conjuntos-dados/cadastro-nacional-da-pessoa-juridica---cnpj | base **completa** do CNPJ (dezenas de GB) | para cruzamento em massa offline; é o que alimenta Minha Receita |
| **BrasilAPI Bancos** | `https://brasilapi.com.br/api/banks/v1` | lista de bancos com código COMPE/ISPB + nome | útil para casar nome/ISPB |
| **BCB — Relação de Instituições** | https://dadosabertos.bcb.gov.br/dataset/relacao-de-instituicoes-em-funcionamento-no-pais | todas as instituições autorizadas por tipo | complementa o IFDATA (inclui não-bancárias) |

## Como classificar o tipo pelo CNAE (usado no script)

- **Banco:** CNAE começa com `6421` (comercial), `6422` (múltiplo c/ carteira comercial),
  `6423` (caixa), `6424` (coop. crédito), `6431`–`6438` (múltiplo s/ carteira comercial,
  investimento, desenvolvimento, câmbio).
- **Corretora / Distribuidora (CTVM/DTVM):** `6612` (corretoras e distribuidoras de TVM).
- **Gestora / administração de fundos (não-IF):** `6630` / `6619` — **não** elegível como parceiro-licença.

## Para refinar "licença ociosa" (passo manual)

Cruzar os candidatos com o **Ranking ANBIMA de Administração Fiduciária** (PL sob administração):
quem tem a licença mas **quase não aparece** no ranking = licença ociosa = alvo mais quente.
O ANBIMA Data é renderizado em JavaScript (resiste a fetch) — baixe a planilha pelo navegador:
https://data.anbima.com.br/publicacoes/ranking-de-administradores-de-fundos-de-investimento
