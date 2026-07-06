# Manual de Controladoria de Ativos e Precificação

> **Base legal:** Resolução CVM nº 21/2021 (autorização e requisitos do administrador fiduciário de fundos de investimento) e Resolução CVM nº 175/2022 (Marco Regulatório dos Fundos de Investimento), em especial o **art. 66** (escrituração contábil própria e segregada da dos prestadores de serviço) e o **art. 104, I** (dever de manter registros contábeis, registro de cotistas, atas de assembleias e pareceres do auditor independente). Os deveres operacionais de cálculo de cota e de patrimônio líquido, bem como os leiautes de balancete e de Composição e Diversificação da Carteira (CDA), constam dos **Anexos Normativos por categoria** da Res. CVM 175 (por exemplo, o Anexo Normativo I).
>
> **Documento interno — revisar com Jurídico e Compliance antes de submeter à CVM ou de operar em produção.**

---

## 1. Objetivo e abrangência

Este Manual estabelece a política, os procedimentos e os controles da área de **Controladoria de Ativos e Precificação** da Instituição, no âmbito da constituição da atividade de **administração fiduciária** de fundos de investimento, com vistas a instruir o pedido de autorização sob a **Res. CVM 21** e a operar sob a **Res. CVM 175**.

A área de Controladoria é responsável por:

| Frente | Escopo |
|---|---|
| Marcação a mercado (MaM) | Definição do valor justo dos ativos da carteira segundo cascata de fontes por classe de ativo. |
| Cálculo da cota e do PL | Apuração diária do valor da cota e do patrimônio líquido do fundo, em regime de prévia e aprovação. |
| Escrituração e registros | Manutenção da escrituração contábil própria do fundo, segregada da dos prestadores (art. 66). |
| Lançamentos e ajustes | Registro versionado de eventos, ajustes e reprocessamentos, com trilha de auditoria. |
| Divulgação de informações | Suporte à elaboração e divulgação das informações periódicas e eventuais (art. 104, IV). |

**Execução interna.** Por ser instituição **já autorizada a funcionar pelo Banco Central do Brasil**, a Instituição executa a controladoria dos ativos **internamente**, na forma admitida pela **Res. CVM 175, art. 83, §1º**. A área mantém segregação funcional e de sistemas em relação à gestão de recursos e à custódia.

A plataforma-piloto de suporte a estes processos encontra-se em `..\piloto\`, com o portal operacional acessível em `/admin/`. As tabelas e campos do banco de dados são referidos entre [colchetes] ao longo deste documento.

---

## 2. Marcação a mercado (MaM)

A MaM consiste em avaliar cada ativo da carteira pelo seu valor justo na data de referência, com o objetivo de assegurar tratamento equitativo entre cotistas e refletir com fidedignidade o patrimônio do fundo. A área adota o princípio da **melhor fonte disponível**, aplicando uma cascata de fontes por classe de ativo.

### 2.1 Cascata de fontes por classe de ativo

No piloto, cada ativo da carteira é registrado em [ativos_carteira] com os campos [preco_mam] (preço marcado a mercado), [preco_referencia] (preço de referência de apoio) e [fonte_preco], cujos valores admitidos são **B3**, **ANBIMA** e **Comitê**.

| Classe de ativo | Fonte primária | Fonte secundária | Fonte terciária (fallback) |
|---|---|---|---|
| Títulos públicos federais | ANBIMA (taxas indicativas) | B3 | Comitê |
| Debêntures e crédito privado | ANBIMA | B3 | Comitê |
| Ações e ativos listados | B3 (preço de fechamento/referência) | — | Comitê |
| Derivativos listados | B3 (ajuste diário) | — | Comitê |
| Ativos ilíquidos ou sem fonte pública | Comitê (metodologia interna) | — | — |

A fonte efetivamente aplicada em cada data é persistida em [ativos_carteira.fonte_preco], garantindo rastreabilidade da origem do preço utilizado no fechamento.

### 2.2 Tratamento de feriados e ativo sem negócio

| Situação | Procedimento |
|---|---|
| Feriado na praça de negociação | Mantém-se o último preço válido; a cota reflete apenas a variação de ativos com fonte disponível e o caixa. |
| Ativo sem negócio no dia | Utiliza-se a fonte secundária/terciária da cascata; na ausência, mantém-se [preco_referencia] e sinaliza-se para o Comitê. |
| Divergência relevante entre fontes | Encaminhamento ao **Comitê de Precificação**, que define o preço e registra a decisão (fonte = Comitê). |

### 2.3 Roadmap de produção

🟩 A formalização de um **manual de precificação homologado** com metodologias detalhadas por instrumento, a **captura automática de preços** junto a ANBIMA/B3 e a governança formal do Comitê de Precificação são itens de **roadmap de produção**. No piloto, os preços são inseridos e mantidos manualmente em [ativos_carteira], com identificação de fonte.

---

## 3. Cálculo da cota e do patrimônio líquido (PL)

### 3.1 Fórmula e ciclo

O piloto apura o valor da cota por meio da função `calcular_cota()`, segundo a fórmula simplificada:

```
cota = ( Σ (quantidade × preço MaM) + caixa ) ÷ cotas
```

🟩 **Honestidade metodológica.** O piloto calcula a cota de forma **simplificada** (soma dos ativos marcados a mercado mais o caixa, dividida pelo número de cotas). O **provisionamento pro-rata de despesas**, a **contabilidade por competência (acrual)** e a apuração no leiaute do balancete são itens de **roadmap de produção** (ver Seção 5).

### 3.2 Ciclo de fechamento D-1 (prévia → aprovação → publicação)

O processamento é conduzido em `admin/processamento.php` e registrado na tabela [fechamentos]. Cada fechamento possui uma [versao] e um [status], que percorre o seguinte fluxo:

| [status] | Significado | Ação subsequente |
|---|---|---|
| Em processamento | Controladoria executando cálculo e conferências. | Aguarda conclusão do cálculo. |
| Aguardando aprovação | Prévia da cota D-1 calculada, pendente de aprovação do gestor. | Gestor aprova ou rejeita. |
| Aprovada | Cota validada pelo gestor. | Libera divulgação e download. |
| Rejeitada | Gestor identificou inconsistência. | Retorna à Controladoria para ajuste. |
| Republicada | Fechamento reprocessado com nova [versao]. | Nova divulgação versionada. |

O helper `selo_dia()` marca a informação como **PRÉVIA** ou **OFICIAL**, conforme o estágio do ciclo. A liberação do arquivo ao cotista é controlada por [fechamentos.liberado_download].

### 3.3 Prévia e aprovação do gestor

A cota é calculada em regime de **prévia de D-1** e submetida à **aprovação do gestor** antes de qualquer divulgação. O **informe diário somente é gerado e divulgado após a cota estar no status Aprovada**. Enquanto pendente, a informação permanece com selo PRÉVIA e não é liberada.

### 3.4 Reprocessamento retroativo versionado

Quando um evento exige recálculo de datas já fechadas (ver Seção 6), o fechamento é reprocessado gerando uma **nova [versao]** em [fechamentos], com [status] = Republicada. Preserva-se a versão anterior, assegurando trilha completa da correção retroativa.

---

## 4. Escrituração contábil e registros (arts. 66 e 104, I)

### 4.1 Escrituração própria e segregada (art. 66)

A Instituição mantém **escrituração contábil própria do fundo, segregada da sua própria contabilidade e da dos demais prestadores de serviço**, conforme o **art. 66** da Res. CVM 175. Cada fundo administrado possui seu conjunto de registros contábeis individualizado.

### 4.2 Registros obrigatórios (art. 104, I)

Nos termos do **art. 104, I**, a Controladoria assegura a manutenção dos seguintes registros:

| Registro | Descrição | Suporte no piloto |
|---|---|---|
| Registros contábeis | Movimentação patrimonial e resultado do fundo. | [fechamentos], [lancamentos] |
| Registro de cotistas | Posições e movimentações de cotas. | Base de cotistas (integração) |
| Atas de assembleias | Deliberações dos cotistas. | Arquivo documental |
| Pareceres do auditor independente | Manifestações da auditoria sobre as demonstrações. | Arquivo documental |

### 4.3 Roadmap de produção

🟩 Constituem **roadmap de produção**, ainda não implementados no piloto:

- **Contabilidade por competência (acrual) segundo o plano de contas COSIF** aplicável a fundos;
- **Provisionamento pro-rata** de taxas de administração, gestão e demais despesas;
- **Balancete no leiaute do Anexo Normativo por categoria** (por exemplo, Anexo Normativo I) e emissão da **CDA** no leiaute regulatório.

No piloto, os registros são mantidos de forma simplificada em [fechamentos] e [lancamentos], suficientes para a prévia de cota e a trilha de ajustes, mas ainda não no formato contábil-regulatório final.

---

## 5. Lançamentos, ajustes e trilha de auditoria

Os eventos que afetam a carteira, o preço ou a quantidade dos ativos são registrados em `admin/lancamentos.php`, na tabela [lancamentos], que sustenta os ajustes e o reprocessamento.

### 5.1 Tipos de lançamento

| Tipo em [lancamentos] | Uso |
|---|---|
| Ajuste de preço | Correção de [preco_mam] de um ativo. |
| Correção de quantidade | Retificação de posição em [ativos_carteira]. |
| Provento | Registro de dividendos, juros ou amortizações. |
| Taxa | Lançamento de despesas e taxas do fundo. |

Cada lançamento registra o **autor** e integra a **trilha de auditoria**, permitindo reconstituir quem lançou o quê e quando.

### 5.2 Cascata de reprocessamento

1. Lançamento registrado em [lancamentos] com autor e trilha.
2. Ajuste refletido em [ativos_carteira] ([preco_mam] / quantidade) quando aplicável.
3. Recálculo da cota por `calcular_cota()`.
4. Geração de **nova [versao]** em [fechamentos] com [status] = Republicada.
5. Nova divulgação, com selo OFICIAL, após aprovação do gestor.

O versionamento garante que correções retroativas não sobrescrevam o histórico, preservando a base para eventual auditoria e para o dever de guarda documental.

---

## 6. Divulgação de informações (art. 104, IV)

Nos termos do **art. 104, IV**, cabe ao administrador **elaborar e divulgar as informações periódicas e eventuais** do fundo. A Controladoria fornece os dados apurados (cota, PL, composição de carteira) como insumo dos envios regulatórios e das divulgações aos cotistas.

O detalhamento dos envios (informe diário, mensal, CDA, demonstrações, comunicados e fatos relevantes), respectivos prazos e canais está descrito no documento **`manual_regulatorio_envios.md`**. A divulgação somente ocorre a partir de dados de fechamento no status **Aprovada** e com selo **OFICIAL**.

---

## 7. Controles e conferências

A Controladoria opera com **dupla checagem** entre a apuração de cota/PL e a conciliação de posições e caixa.

| Controle | Objetivo | Referência |
|---|---|---|
| Controladoria × Conciliação | Confrontar as posições e o caixa usados no cálculo da cota contra a conciliação diária de custódia e liquidação. | `manual_conciliacao.md` |
| Conferência de fontes MaM | Validar [fonte_preco] e [preco_mam] antes do fechamento. | Seção 2 |
| Aprovação do gestor | Validação independente da prévia antes da publicação. | Seção 3 |
| Trilha de lançamentos | Rastreabilidade de todo ajuste com autor e versão. | Seção 5 |

Divergências identificadas na dupla checagem bloqueiam o avanço do [status] para Aprovada até resolução e registro em [lancamentos].

---

## 8. Papéis e segregação de funções

A Instituição mantém segregação entre controladoria, gestão e custódia, com fronteiras claras de responsabilidade.

| Função | Responsabilidade | Fronteira |
|---|---|---|
| **Controladoria (administrador)** | MaM, cálculo de cota/PL, escrituração, registros e apuração para divulgação. | **Não** realiza decisões de investimento nem enquadramento. |
| **Gestão** | Decisões de investimento, aprovação da prévia de cota e **enquadramento da carteira**. | O **enquadramento é dever do gestor**, nos termos da **Res. CVM 175, art. 89** — não é atribuição da Controladoria. |
| **Custódia** | Guarda dos ativos, liquidação e confirmação de posições. | Insumo para a conciliação (Seção 7). |

> **Nota de conformidade.** O **enquadramento** dos ativos aos limites e às políticas de investimento é responsabilidade do **gestor** (art. 89). A Controladoria fornece dados e apura o PL, mas **não** exerce o enquadramento.

---

## 9. Referências cruzadas

| Documento | Conteúdo relacionado |
|---|---|
| `matriz_conformidade_cvm21_175.md` | Mapeamento de deveres regulatórios às evidências de controle. |
| `dossie_estrutura_operacional_tecnologica.md` | Arquitetura operacional e tecnológica de suporte. |
| `manual_conciliacao.md` | Rotinas de conciliação de posições e caixa (Seção 7). |
| `manual_regulatorio_envios.md` | Envios regulatórios e divulgações periódicas (Seção 6). |

---

## 10. Referência normativa e revisão

**Base normativa:** Resolução CVM nº 21/2021; Resolução CVM nº 175/2022, em especial **art. 66** (escrituração própria e segregada), **art. 83, §1º** (execução interna da controladoria por instituição autorizada pelo BCB), **art. 89** (enquadramento como dever do gestor), **art. 104, I** (registros contábeis, de cotistas, atas e pareceres) e **art. 104, IV** (divulgação de informações periódicas e eventuais); e os **Anexos Normativos por categoria** quanto aos deveres de cálculo de cota/PL e aos leiautes de balancete e CDA.

🟩 Os itens assinalados constituem **roadmap de produção** e ainda não estão implementados no piloto.

> **Este documento deve ser revisado com as áreas Jurídica e de Compliance antes de sua submissão à CVM ou de sua adoção em ambiente de produção.**
