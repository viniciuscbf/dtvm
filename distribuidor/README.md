# Pacote Distribuidor — Licenciamento e estruturação da distribuição de cotas de fundos

Este pacote orienta a **instituição parceira** a habilitar-se para **distribuir cotas dos fundos**
administrados/custodiados na estrutura de DTVM. Distribuição é função **distinta** de custódia e de
administração fiduciária — é a camada que efetivamente **coloca o investidor dentro do fundo**.

> **Escopo.** Trata da distribuição de **cotas de fundos de investimento** (Res. CVM 175). Não cobre
> distribuição de outros valores mobiliários (ações, ofertas públicas) nem a atividade de gestão.

## Princípio que orienta os dois manuais

O peso do licenciamento **depende do tipo da instituição**:

| Situação | Precisa de licença nova de distribuição? |
|---|---|
| **Banco** (comercial/múltiplo/investimento) | **Não** (já integra o sistema de distribuição — Lei 6.385, art. 15). Precisa de **compliance + contratos + operação**. → **Manual A** |
| **Instituição financeira licenciada, mas NÃO-banco** (cooperativa, SCFI, SCD…) | **Depende** — a autorização do BCB não garante distribuir valores mobiliários; pode exigir credenciamento na CVM ou atuação por **convênio/conta e ordem**. → **Manual B** |

## Documentos

1. **[Manual A — Banco que quer distribuir](manual_banco_distribuidor.md)**
   Passo a passo para o banco parceiro: confirmação de escopo, diretores responsáveis, políticas
   (suitability/PLD/LGPD), adesão ANBIMA, contratos de distribuição, termo de adesão, sistemas, custos e prazos.

2. **[Manual B — Instituição financeira licenciada (não-banco) que quer distribuir](manual_instituicao_financeira_distribuidor.md)**
   Caminhos para cooperativas de crédito, SCFI, SCD e afins: quando é preciso credenciamento próprio na
   CVM, quando dá para atuar como **assessor de investimento** de um distribuidor autorizado, requisitos e custos.

3. **[Manual da Plataforma de Distribuição — os 2 casos](manual_plataforma_distribuicao.md)**
   Blueprint **técnico-regulatório** da plataforma que permite o investidor aplicar/resgatar: o que a tecnologia
   precisa ter embutido (onboarding/KYC, suitability, termo de adesão, ordem/cotização/liquidação, conta e ordem,
   IR/informe, PLD), os **diretores responsáveis** e o **mapa de certificações** por função — cobrindo banco e não-banco.
   Ponto-chave: **construir a plataforma é tecnologia (sem licença); a distribuição de direito é do banco.**

## Onde este pacote se encaixa

- `custodiante/` — licença de custódia (Res. CVM 32).
- `administrador_fiduciario/` — licença de administração fiduciária (Res. CVM 21/175).
- `distribuidor/` — **este pacote**: habilitação para distribuir as cotas ao investidor final.
- Guias de negócio/custos na raiz (`guia_credenciamento_banco.md`, `guia_burocratico_regulatorio.md`, `planilha_custos.md`).

## Aviso

Material de orientação para estruturação e diálogo com o parceiro — **não constitui parecer jurídico**.
O escopo exato da autorização de cada instituição e os requisitos aplicáveis devem ser confirmados no
registro da instituição junto à CVM/BCB e validados por assessoria jurídica/compliance antes de protocolar.
