# Manual de Gestão do Risco de Liquidez — Administração Fiduciária

> **Documento de trabalho — v1.0** · Pacote de Autorização e Estruturação da Atividade de **Administração Fiduciária** de fundos de investimento.
> **Base legal:** **Resolução CVM nº 21/2021, art. 26, §4º** (dever do administrador fiduciário de **supervisionar diligentemente** a gestão de riscos do gestor e de **gerir, em conjunto com o gestor, o risco de liquidez** dos fundos, com mecanismos de troca de informações); e **Resolução CVM nº 175/2022, art. 92** (gestão de liquidez das **classes abertas**, dever conjunto dos prestadores de serviços essenciais).
> **Finalidade:** descrever como o administrador fiduciário exerce seu papel de **supervisão + gestão conjunta** do risco de liquidez, sem assumir a gestão da carteira — que permanece com o **gestor** (Res. 175, art. 89).
> **Requerente:** `[RAZÃO SOCIAL DA INSTITUIÇÃO]` — CNPJ `[__]`. Campos entre `[colchetes]` a preencher pela instituição.

---

## Convenções

| Símbolo | Significado |
|---|---|
| ✅ | Atendido / processo definido |
| 🟡 | Parcial — demonstrável no piloto, falta formalização (dados do banco, aprovação de diretoria) |
| 🟩 | Roadmap — métrica ou ferramenta de **produção** que o piloto **não** implementa |

> **Âncora tecnológica:** o portal `/admin/` do piloto (`../piloto/`) é a maquete da controladoria/administração fiduciária. A base de previsão de caixa é a tabela `previsao_caixa`; a visão do fluxo, o módulo `admin/caixa`.

---

## 1. Objetivo e princípio reitor

Este manual estabelece o papel do **administrador fiduciário** na gestão do **risco de liquidez** dos fundos administrados — o risco de o fundo não conseguir honrar, tempestivamente e sem perdas relevantes, seus compromissos (notadamente resgates de cotistas e obrigações operacionais).

O princípio reitor decorre do **art. 26, §4º, da Res. CVM 21**, que impõe ao administrador fiduciário dois deveres distintos e cumulativos:

1. **(I) Supervisionar diligentemente** a gestão de riscos conduzida pelo **gestor** contratado — dever de vigilância sobre o processo do terceiro; e
2. **(II) Gerir, EM CONJUNTO com o gestor, o risco de liquidez** dos fundos — dever de **coautoria**, exercido lado a lado com o gestor, prevendo **mecanismos de troca de informações**.

> **Fronteira que este manual NÃO cruza.** A **decisão de investimento** e o **enquadramento** da carteira são atribuições do **gestor** (Res. 175, art. 89). O administrador fiduciário **não** seleciona ativos, não define alocação e não substitui o gestor na condução da carteira. Ele **supervisiona** o processo de risco do gestor e **compartilha** a gestão do risco de liquidez — o que é diferente de gerir a carteira. A confusão entre esses papéis é o principal erro conceitual que este manual previne.

Para as **classes abertas** — aquelas cujos cotistas podem solicitar resgate a qualquer tempo, sujeitas ao maior risco de descasamento —, o **art. 92 da Res. CVM 175** reforça que a gestão de liquidez é **dever conjunto dos prestadores de serviços essenciais** (administrador e gestor). As **classes fechadas**, cujo resgate ocorre apenas no encerramento ou em eventos previstos, têm exposição estrutural menor a corridas de resgate, mas não estão isentas de gestão de fluxo.

---

## 2. Divisão de papéis: administrador × gestor na liquidez

A gestão de liquidez é **conjunta**, mas os papéis são **assimétricos**. A tabela abaixo delimita responsabilidades.

| Dimensão | **Gestor** (decisão / carteira) | **Administrador fiduciário** (supervisão + gestão conjunta) |
|---|---|---|
| Decisão de investimento e enquadramento | **Titular** (Res. 175, art. 89) | Não interfere; **verifica e reporta** desenquadramentos |
| Definição da política/limites de liquidez | Propõe e aplica na carteira | **Participa da definição em conjunto** e **supervisiona** a aplicação (art. 26, §4º, I e II) |
| Perfil de liquidez dos ativos | Classifica os ativos que negocia | **Consolida** e **critica** a classificação; questiona premissas |
| Perfil do passivo (cotistas, prazos de resgate) | Insumo recebido | **Titular do dado** — administra o passivo do cotista (controladoria de passivo) |
| Previsão de caixa | Insumo para decisão | **Produz** a previsão consolidada (`previsao_caixa`) |
| Testes de estresse de resgate | Executa na carteira / metodologia própria | **Participa, valida e supervisiona** a metodologia e os resultados 🟩 |
| Acionamento de ferramentas (gates, side pockets, fechamento p/ resgate) | Propõe / delibera conforme regulamento | **Delibera em conjunto** e **operacionaliza** o passivo afetado 🟩 |
| Evidência da supervisão diligente | — | **Titular** — mantém trilha e atas (art. 26, §4º, I) |

### Mecanismos de troca de informações (art. 26, §4º)

O §4º exige que a gestão conjunta preveja **mecanismos de troca de informações** entre administrador e gestor. No arranjo do requerente:

| Mecanismo | Sentido | Conteúdo | Periodicidade |
|---|---|---|---|
| Boletas / carteira | Gestor → Administrador | Composição, negócios, liquidez estimada dos ativos | D+0 / contínuo |
| Perfil do passivo | Administrador → Gestor | Concentração de cotistas, prazos de cotização/liquidação de resgate | Periódica e sob evento |
| Previsão de caixa | Administrador → Gestor | Fluxo projetado (`previsao_caixa`) — proventos, resgates, taxas, vencimentos | Diária/atualizada |
| Alertas de liquidez | Bidirecional | Sinais de descasamento, resgates concentrados, estresse | Sob evento |
| Comitê de liquidez | Conjunto | Deliberação, cenários, acionamento de ferramentas | Periódica + extraordinária |

> As formas, prazos e formatos dessa troca devem constar de **contrato/protocolo** entre administrador e gestor (`[referência ao instrumento contratual]`), atendendo à exigência de o §4º **prever** tais mecanismos.

---

## 3. Fontes de dados da gestão de liquidez

A gestão do risco de liquidez cruza dois lados do balanço do fundo — o **ativo** (o que pode ser convertido em caixa) e o **passivo** (o que pode ser exigido em caixa) — e a **projeção de fluxo** entre eles.

| Fonte | Lado | Origem | Referência no piloto |
|---|---|---|---|
| Perfil do passivo — cotistas | Passivo | Controladoria de passivo do administrador | `cotistas`, `mov_cotistas` |
| Prazos de resgate (cotização/liquidação) | Passivo | Regulamento das classes / cadastro | `manual_passivo_tributacao.md`; `[campo prazos]` 🟡 |
| Liquidez dos ativos | Ativo | Classificação do gestor + valorização da controladoria | `ativos_carteira` (`preco_mam`, `fonte_preco`) |
| Previsão de caixa | Fluxo | Consolidação pela controladoria | `previsao_caixa`; `admin/caixa` |
| Posição / extrato de custódia | Ativo/caixa | Custodiante (conciliação) | `conciliacao`; `manual_controladoria_precificacao.md` |

### 3.1 A tabela `previsao_caixa` (base de previsão)

A previsão de caixa do piloto registra eventos projetados por fundo e data, classificados em quatro tipos:

| `tipo` | Natureza | Sinal no fluxo |
|---|---|---|
| **Provento a receber** | Dividendos, juros, cupons | Entrada (+) |
| **Resgate agendado** | Resgate de cotista já solicitado/agendado | Saída (−) |
| **Taxa a pagar** | Taxa de administração/gestão e demais despesas | Saída (−) |
| **Vencimento de título** | Amortização/vencimento de ativo | Entrada (+) |

Campos: `fundo_id`, `data_prevista`, `tipo`, `descricao`, `valor`. O módulo `admin/caixa` oferece a **visão do fluxo** agregada. Essa base é o ponto de partida — a projeção determinística de curto prazo — sobre a qual se constroem os controles de horizonte e de estresse descritos a seguir.

---

## 4. Casamento ativo × passivo e horizontes de liquidez

O núcleo técnico da gestão de liquidez é o **casamento (matching)** entre o **horizonte de conversão dos ativos em caixa** e o **horizonte de exigibilidade do passivo** (prazos de resgate somados à cotização e liquidação previstas em regulamento).

**Conceito.** Para cada horizonte temporal, compara-se:

- **Oferta de liquidez** = caixa + parcela dos ativos conversível em caixa naquele horizonte, com haircut por condição de mercado; contra
- **Demanda de liquidez** = resgates projetados/potenciais + obrigações (taxas, despesas, chamadas) exigíveis no mesmo horizonte.

O resultado é um **perfil de descasamento por prazo** (buckets de liquidez), que sinaliza janelas em que a demanda potencial excede a oferta disponível.

| Horizonte (bucket) | Oferta de liquidez | Demanda de liquidez | Descasamento |
|---|---|---|---|
| D+0 / D+1 | `[ ]` | `[ ]` | `[ ]` |
| D+2 a D+5 | `[ ]` | `[ ]` | `[ ]` |
| D+6 a D+21 | `[ ]` | `[ ]` | `[ ]` |
| > D+21 | `[ ]` | `[ ]` | `[ ]` |

> 🟩 **Métricas de produção.** O cálculo automatizado do **casamento ativo × passivo por horizonte**, com haircuts, buckets parametrizados e classificação de liquidez dos ativos, **não** está implementado no piloto. O piloto entrega a **base determinística** de curto prazo (`previsao_caixa` / `admin/caixa`); a métrica de descasamento por bucket é construção de produção. Os prazos de resgate por classe (`[campo prazos de cotização/liquidação]`) precisam ser cadastrados de forma estruturada para automatizar o lado passivo.

---

## 5. Testes de estresse de resgate e ferramentas de liquidez

### 5.1 Testes de estresse (cenários)

Além do fluxo esperado, a gestão conjunta deve considerar **cenários adversos** de resgate e de deterioração da liquidez dos ativos. Os cenários típicos:

| Cenário | Choque no passivo | Choque no ativo |
|---|---|---|
| Resgate elevado | Resgates ≥ `[X]%` do PL em janela curta | — |
| Estresse de mercado | — | Alargamento de spread / haircut sobre ativos |
| Combinado | Resgate elevado + iliquidez de ativos | Ambos simultâneos |
| Resgate concentrado | Saída de cotista(s) relevante(s) | — |

> 🟩 **Métricas de produção.** A metodologia de **testes de estresse de resgate** — definição de choques, séries de referência, haircuts por classe de ativo e o teste periódico — **não** está implementada no piloto. É executada/proposta pelo **gestor** e **validada e supervisionada** pelo administrador fiduciário, em conjunto (art. 26, §4º; Res. 175, art. 92). Os parâmetros específicos seguem **regulamentação específica / Anexos da Res. 175** e a **autorregulação ANBIMA** aplicável — este manual **não** fixa números de artigo nem parâmetros que não constem da norma.

### 5.2 Ferramentas de gestão de liquidez (classes abertas)

Para as **classes abertas**, o ordenamento prevê ferramentas para preservar a equidade entre cotistas diante de estresse de liquidez. Descrição **conceitual**:

| Ferramenta | O que faz | Papel do administrador |
|---|---|---|
| **Gate** | Limita/rateia o valor resgatável em uma janela | Operacionaliza o rateio no passivo; delibera em conjunto |
| **Side pocket** | Segrega ativos ilíquidos/problemáticos em classe/estrutura apartada | Operacionaliza a segregação e o passivo afetado |
| Fechamento para resgates | Suspende temporariamente resgates | Comunica cotistas/CVM; delibera em conjunto |
| Ajuste anti-diluição (`[se aplicável]`) | Repassa custo de liquidez a quem entra/sai | Aplica no cálculo do passivo |

> **Honestidade regulatória.** As condições de acionamento, os limites e o rito de cada ferramenta constam de **regulamentação específica / Anexos da Res. CVM 175**, do **regulamento de cada classe** e da **autorregulação ANBIMA** — não são reproduzidos aqui como número de artigo para não induzir a erro. A decisão de acionamento é **conjunta** (prestadores essenciais) e observa o regulamento; nenhuma dessas ferramentas está implementada como fluxo de produção no piloto 🟩.

---

## 6. Concentração de cotistas e risco de resgate concentrado

O **risco de resgate concentrado** é o risco de que a saída de **poucos cotistas relevantes** force a liquidação desordenada de ativos, prejudicando os cotistas remanescentes. É um risco do **passivo**, cujo dado é do **administrador fiduciário** (controladoria de passivo).

Indicadores de monitoramento (conceito):

| Indicador | Definição | Alerta sugerido |
|---|---|---|
| Maior cotista / PL | Participação do maior cotista | > `[__]%` |
| Top 5 cotistas / PL | Participação somada dos 5 maiores | > `[__]%` |
| Índice de concentração | HHI ou equivalente sobre o passivo | > `[__]` |
| Cotistas ligados / partes relacionadas | Concentração efetiva por grupo | monitorar `partes_relacionadas` |

> 🟩 O cálculo automatizado desses índices de concentração de passivo, com alerta parametrizado, é **métrica de produção** não implementada no piloto. O dado-fonte (`cotistas`, `mov_cotistas`) existe na controladoria de passivo; a agregação e o alerta são roadmap. Ver `manual_passivo_tributacao.md`.

---

## 7. Governança: comitê, rotina, escalonamento e evidências

A **supervisão diligente** exigida pelo **art. 26, §4º, I** só se demonstra por **evidência**. A governança de liquidez formaliza rotina, deliberação e trilha.

### 7.1 Rotina e comitê

| Instância | Composição | Objeto | Periodicidade |
|---|---|---|---|
| Monitoramento diário | Controladoria (admin) | Previsão de caixa, alertas | Diária |
| **Comitê de liquidez** | Administrador + gestor (essenciais) | Casamento, estresse, concentração, ferramentas | `[mensal]` + extraordinário |
| Reporte à diretoria | Diretor de adm. fiduciária | Exposições e deliberações | `[periodicidade]` |

### 7.2 Escalonamento

| Gatilho | Ação | Responsável |
|---|---|---|
| Descasamento de curto prazo | Alerta ao gestor; reforço de caixa (via decisão do gestor) | Controladoria → Gestor |
| Resgate concentrado detectado | Convocação de comitê extraordinário | Administrador |
| Estresse severo / iliquidez | Avaliação de ferramenta (gate/side pocket/fechamento) | Comitê conjunto |
| Descumprimento pelo gestor | Registro, exigência de correção, reporte CVM se aplicável | Administrador (supervisão) |

### 7.3 Evidências da supervisão diligente (art. 26, §4º, I)

| Evidência | Suporte |
|---|---|
| Atas do comitê de liquidez | `[repositório]` 🟩 |
| Trilha da troca de informações admin×gestor | `[protocolo/contrato]` 🟡 |
| Previsão de caixa versionada | `previsao_caixa`; `admin/caixa` 🟡 |
| Relatórios de estresse validados | `[metodologia]` 🟩 |
| Registro de acionamento de ferramentas | `[quando aplicável]` 🟩 |

> A ausência de evidência equivale, para fins de fiscalização, à ausência de supervisão. A construção do **repositório de atas e trilhas** é prioridade de formalização (🟩/🟡).

---

## 8. Interfaces com outros processos

| Interface | Documento / módulo | Papel na liquidez |
|---|---|---|
| **Controladoria e precificação** | `manual_controladoria_precificacao.md` | Valorização dos ativos (MaM) alimenta a oferta de liquidez; conciliação de caixa com custodiante |
| **Passivo do cotista e tributação** | `manual_passivo_tributacao.md` | Perfil do passivo, prazos de resgate, concentração, come-cotas afetam a demanda de liquidez |
| **Estrutura operacional/tecnológica** | `dossie_estrutura_operacional_tecnologica.md` | Segregação de perfis (`admin`/`gestor`/`custodia`), arquitetura da controladoria |
| **Matriz de conformidade** | `matriz_conformidade_cvm21_175.md` | Rastreio dispositivo-a-dispositivo (Res. 21 art. 26, §4º; Res. 175 art. 92) |

**Fluxo integrado (visão):**

```
   Gestor (carteira, liquidez dos ativos, estresse)
        │  troca de informações (art. 26, §4º)
        ▼
   ADMINISTRADOR FIDUCIÁRIO — gestão CONJUNTA + supervisão
        │  perfil do passivo (cotistas, prazos)  ← controladoria de passivo
        │  previsão de caixa (previsao_caixa)     → admin/caixa
        │  casamento ativo×passivo 🟩 · estresse 🟩 · concentração 🟩
        ▼
   Comitê de liquidez → ferramentas (classes abertas) 🟩 → evidências
```

---

## 9. Síntese de status (piloto)

| Item | Status |
|---|---|
| Base de previsão de caixa (`previsao_caixa`) | 🟡 (piloto) |
| Visão de fluxo (`admin/caixa`) | 🟡 (piloto) |
| Casamento ativo × passivo por horizonte | 🟩 |
| Testes de estresse de resgate | 🟩 |
| Índices de concentração de cotistas | 🟩 |
| Ferramentas de liquidez (gates, side pockets) | 🟩 |
| Comitê de liquidez / atas / trilha de supervisão | 🟩 / 🟡 |
| Protocolo de troca de informações admin×gestor | 🟡 |

---

> **Rodapé.** Documento de trabalho **v1.0**, integrante do pacote de autorização e estruturação da atividade de **administração fiduciária**. Base legal: **Res. CVM 21/2021, art. 26, §4º** (supervisão diligente + gestão conjunta do risco de liquidez, com troca de informações) e **Res. CVM 175/2022, art. 92** (gestão de liquidez das classes abertas — dever conjunto dos prestadores essenciais), observado que a **decisão de investimento e o enquadramento** são do **gestor** (art. 89). Ferramentas, parâmetros e testes específicos seguem **regulamentação específica / Anexos** e **autorregulação ANBIMA** — não reproduzidos como artigo para não induzir a erro. Itens 🟩 são construção de produção não implementada no piloto. Campos `[colchetes]` a preencher pela instituição.
> **Última atualização:** `[data]` · **Responsável:** `[Diretor de Administração Fiduciária]`.
