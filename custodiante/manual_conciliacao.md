# Manual de Conciliação de Posições de Custódia

> **Base legal:** Resolução CVM nº 32, de 19 de maio de 2021 (consolidada com a Resolução CVM nº 209, de 2024) — **Art. 13, §1º, I**: o custodiante que atende diretamente investidores deve realizar **conciliação diária** entre as posições mantidas nas contas de custódia sob sua responsabilidade e as posições fornecidas pelo **depositário central**.
>
> **Documento integrante do pacote de comprovação** apresentado à Comissão de Valores Mobiliários para fins de autorização para o exercício da atividade de custódia de valores mobiliários.

---

## 1. Objetivo

Este manual estabelece a metodologia, a periodicidade e os controles internos adotados pela instituição para assegurar que **as posições registradas nas contas de custódia correspondam às posições mantidas pelo depositário central**, conforme exigido pelo **Art. 3º, parágrafo único**, e pelo **Art. 13, §1º, I**, da Resolução CVM nº 32/2021.

A conciliação de posições é o mecanismo central de controle pelo qual o custodiante:

- comprova a **fidedignidade** dos registros de titularidade sob sua guarda (Art. 2º, §2º, I, "a" — conservação, controle e conciliação das posições);
- identifica, classifica, trata e registra tempestivamente qualquer **divergência** entre as fontes;
- mantém **trilha de auditoria** dos erros e incidentes identificados (Art. 13, V); e
- sustenta controles internos escritos e verificáveis (Art. 16).

> **Referências cruzadas:** `manual_custodia.md` (estrutura geral da atividade), `manual_liquidacao_dvp.md` (liquidação e trânsito de ativos), `dossie_estrutura_operacional_tecnologica.md` (arquitetura de sistemas), `matriz_conformidade_cvm32.md` (mapeamento artigo a artigo) e `politica_confidencialidade_seguranca.md` (tratamento de dados sensíveis).

---

## 2. Escopo e Periodicidade

### 2.1 Periodicidade obrigatória

A conciliação entre as posições das contas de custódia e as posições do **depositário central** é realizada em **base diária**, em todo dia útil, cumprindo o dever expresso do **Art. 13, §1º, I**, da Resolução CVM nº 32/2021. Nenhuma posição custodiada permanece um dia útil sem confronto com a posição de referência do depositário central.

### 2.2 Frentes de conciliação

Embora a norma exija especificamente a conciliação **custodiante × depositário central**, a implementação operacional adotada organiza o processo em **três frentes complementares**, que juntas materializam o dever regulamentar e a diligência de controle sobre o ciclo completo do ativo e do caixa:

| Frente | Fontes confrontadas | Fundamento | Natureza |
|---|---|---|---|
| **Posição × Custodiante** | Posições internas das contas de custódia × posições do depositário central | Art. 13, §1º, I; Art. 3º, par. único | **Obrigação regulamentar direta** |
| **Caixa × Extrato** | Saldos de caixa das contas × extrato da conta de liquidação | Art. 2º, §2º, I, "a"; Art. 5º, II | Controle de caixa e liquidação |
| **Operações × Gestor** | Operações registradas × instruções/carteira do gestor | Art. 2º, §2º, I, "a" | Diligência operacional da administradora |

> **Nota de transparência regulatória:** a exigência normativa nuclear é a conciliação diária **custodiante × depositário central** (Art. 13, §1º, I). As frentes "Caixa × Extrato" e "Operações × Gestor" não substituem essa obrigação — são camadas adicionais de controle que ampliam a cobertura sobre o ciclo de liquidação e sobre a consistência entre a carteira instruída pelo gestor e a posição efetivamente custodiada.

No sistema-piloto, essas três frentes são registradas no campo `[origem]` da tabela `conciliacao`, com os valores:

- `Posição × Custodiante`
- `Operações × Gestor`
- `Caixa × Extrato`

---

## 3. Fontes de Dados

A conciliação parte do confronto entre a **base interna de custódia** e as **fontes externas de referência**. As fontes são:

| Fonte | Origem | Geração no piloto |
|---|---|---|
| **Posição custodiada (interna)** | Registro das contas de custódia sob responsabilidade do custodiante | `custodia/arquivos.php` — gera a posição custodiada em formato CSV |
| **Extrato de conta** | Movimentação e saldo da conta de liquidação | `custodia/arquivos.php` — gera o extrato de conta |
| **Posições do depositário central** | Arquivo de posições fornecido pelo depositário central | Fonte de referência normativa (Art. 13, §1º, I) |
| **Instruções / carteira do gestor** | Ordens e composição-alvo da carteira | Base de operações do gestor |

### 3.1 🟩 Roadmap — captura automática das posições do depositário central

> 🟩 **Item de roadmap.** No estágio atual da plataforma-piloto, a captura dos arquivos de posição do depositário central é **simulada**, para fins de demonstração do fluxo de conciliação ponta a ponta. A **captura automática real** — via conexão direta com o depositário central (recepção e importação automatizada dos arquivos oficiais de posição) — é item planejado do roadmap de produção. A lógica de comparação, classificação, tratamento e trilha de auditoria descrita neste manual já está implementada e independe da origem do arquivo; apenas o **transporte** do arquivo do depositário será promovido de simulado para automático.

---

## 4. Processo Diário de Conciliação (Batch)

O ciclo diário é executado em rotina de processamento em lote (batch), em todo dia útil, seguindo os passos abaixo:

```
┌──────────────────────────────────────────────────────────────────┐
│  1. IMPORTAR   → carrega posições internas, extrato e arquivo do   │
│                  depositário central                               │
│  2. COMPARAR   → confronta posição × posição, saldo × saldo,       │
│                  operação × instrução                              │
│  3. IDENTIFICAR→ registra divergências na tabela `conciliacao`     │
│                  (situacao = Divergente)                           │
│  4. CLASSIFICAR→ atribui `classificacao` = Timing / Erro / Suspeita│
│  5. TRATAR     → resolução com justificativa ou escalonamento      │
│  6. REGISTRAR  → grava trilha (resolucao, resolvido_por,           │
│                  resolvido_em)                                      │
└──────────────────────────────────────────────────────────────────┘
```

| Passo | Ação | Registro no piloto |
|---|---|---|
| 1 | **Importar** posições internas, extrato e posições do depositário central | Arquivos gerados por `custodia/arquivos.php` |
| 2 | **Comparar** as fontes item a item (ativo, quantidade, saldo) | Rotina batch de conciliação |
| 3 | **Identificar** divergências | `conciliacao.[situacao]` = `Divergente` |
| 4 | **Classificar** cada divergência | `conciliacao.[classificacao]` = `Timing` \| `Erro` \| `Suspeita` |
| 5 | **Tratar** — resolver com justificativa ou escalar ao compliance | Tela `admin/conciliacao.php` |
| 6 | **Registrar** trilha de resolução | `[resolucao]`, `[resolvido_por]`, `[resolvido_em]` |

Itens sem qualquer diferença são registrados diretamente com `[situacao]` = `Conciliado`. As divergências ao custodiante, na frente Posição × Custodiante, são exibidas em `custodia/index.php`.

---

## 5. Classificação e Critérios de Divergência

Toda divergência identificada recebe uma classificação no campo `[classificacao]`, que orienta o tratamento subsequente. Os critérios são:

| Classificação | Definição | Exemplo típico | Encaminhamento |
|---|---|---|---|
| **Timing** | Diferença temporária decorrente de defasagem entre eventos que serão naturalmente conciliados no ciclo de liquidação | Operação liquidada em D+1 ainda não refletida na posição interna no fechamento de D | Resolução com justificativa após confirmação da liquidação |
| **Erro** | Divergência real por falha de lançamento, cadastro ou processamento, corrigível internamente | Quantidade lançada a maior/menor; ativo classificado em conta incorreta | Correção do lançamento e resolução com justificativa |
| **Suspeita** | Divergência sem explicação legítima aparente, com potencial indício de fraude, apropriação indevida ou registro sem lastro | Ativo constante na posição do custodiante **sem contrapartida** na controladoria ("ativo fantasma") | **Escalonamento obrigatório ao compliance** |

### 5.1 Critérios de materialidade e prazo

- Divergências classificadas como **Timing** devem ser reconfirmadas e resolvidas até o ciclo de liquidação previsto; se persistirem além do prazo esperado, são **reclassificadas** como `Erro` ou `Suspeita`.
- Divergências classificadas como **Suspeita** são de tratamento **prioritário** e disparam escalonamento imediato ao compliance, independentemente de valor.
- Nenhuma divergência é encerrada sem preenchimento do campo `[resolucao]` (justificativa) e da trilha de responsável e data.

---

## 6. Fluxo de Tratamento e Escalonamento

O tratamento das divergências ocorre na tela `admin/conciliacao.php`, que oferece dois caminhos:

### 6.1 Resolução com justificativa

Para divergências de **Timing** e **Erro**, o responsável pela controladoria registra a **resolução com justificativa**. O sistema grava:

- `[resolucao]` — descrição da causa e do tratamento aplicado;
- `[resolvido_por]` — identificação do responsável;
- `[resolvido_em]` — data e hora da resolução.

A `[situacao]` da divergência passa de `Divergente` para `Resolvido`.

### 6.2 Escalonamento ao compliance

Para divergências classificadas como **Suspeita**, o responsável **escala ao compliance** diretamente da tela `admin/conciliacao.php`. O escalonamento:

- cria um registro em `alertas_fraude`, vinculando a divergência ao fluxo de apuração de compliance;
- mantém a divergência na trilha de conciliação com a justificativa e o responsável pelo escalonamento registrados;
- não permite encerramento silencioso — a suspeita permanece rastreável até parecer do compliance.

```
Divergência (situacao=Divergente)
        │
        ├── Timing / Erro ──► Resolução com justificativa ──► situacao=Resolvido
        │                       (resolucao, resolvido_por, resolvido_em)
        │
        └── Suspeita ─────────► Escalonar ao compliance ──► registro em `alertas_fraude`
                                 (trilha preservada)
```

---

## 7. Exemplo Concreto de Divergência Tratada — "Ativo Fantasma"

> **Cenário:** Na conciliação diária **Posição × Custodiante** do dia útil, a rotina batch identifica um ativo constante na **posição interna do custodiante** que **não possui contrapartida na controladoria** nem correspondência no arquivo de posições do depositário central. Trata-se de um potencial "ativo fantasma".

| Etapa | Registro |
|---|---|
| **Identificação** | Rotina batch marca o item com `[situacao]` = `Divergente`, `[origem]` = `Posição × Custodiante` |
| **Análise** | Não há operação, liquidação ou defasagem de timing que justifique a posição; ausência total de lastro na controladoria |
| **Classificação** | `[classificacao]` = `Suspeita` |
| **Escalonamento** | Operador de controladoria aciona "escalar ao compliance" em `admin/conciliacao.php` → gera registro em `alertas_fraude` |
| **Trilha** | `[resolvido_por]` = responsável que escalou; `[resolvido_em]` = data/hora; `[resolucao]` = "Ativo sem lastro na controladoria e sem correspondência no depositário central; escalado ao compliance para apuração" |
| **Encerramento** | Concluído somente após parecer do compliance; divergência permanece rastreável em todo o percurso |

Este exemplo demonstra o funcionamento da barreira de controle prevista no **Art. 3º, parágrafo único** (correspondência obrigatória entre posições) e no **Art. 13, V** (registro de erros e incidentes): uma posição interna sem lastro no depositário central **jamais é conciliada automaticamente**, sendo tratada como incidente e submetida a apuração formal.

---

## 8. Trilha de Auditoria e Evidências

Cada divergência produz um registro auditável e imutável em seu percurso, atendendo ao **Art. 13, V** (registro de erros e incidentes) e ao **Art. 16** (controles internos escritos e verificáveis). A trilha é composta pelos campos da tabela `conciliacao`:

| Campo | Conteúdo | Finalidade de auditoria |
|---|---|---|
| `[origem]` | Frente de conciliação | Identifica a fonte confrontada |
| `[situacao]` | `Conciliado` / `Divergente` / `Resolvido` | Estado do item no ciclo |
| `[classificacao]` | `Timing` / `Erro` / `Suspeita` | Natureza da divergência |
| `[resolucao]` | Justificativa da resolução ou motivo do escalonamento | Comprova a análise realizada |
| `[resolvido_por]` | Responsável | Atribui responsabilidade nominal |
| `[resolvido_em]` | Data e hora | Comprova tempestividade |

Divergências escaladas geram, adicionalmente, registro em `alertas_fraude`, preservando o vínculo entre a conciliação e o fluxo de compliance. O conjunto desses registros constitui **evidência verificável** para fins de fiscalização e de auditoria interna (arts. 18 e 20 da Res. CVM 32).

---

## 9. Controles Internos e Indicadores

### 9.1 Indicadores de acompanhamento

O processo de conciliação é monitorado por indicadores gerados a partir da tabela `conciliacao`:

| Indicador | Descrição | Fonte |
|---|---|---|
| **Nº de divergências** | Total de itens `Divergente` por frente e por dia | `conciliacao.[situacao]` |
| **Tempo de resolução** | Intervalo entre identificação e `[resolvido_em]` | `conciliacao.[resolvido_em]` |
| **Reincidência** | Recorrência de divergências de mesma natureza/ativo | Análise histórica de `[classificacao]` |
| **Taxa de escalonamento** | Proporção de itens `Suspeita` escalados | `alertas_fraude` vinculados |

### 9.2 Relatório periódico

É produzido **relatório periódico de conciliação**, consolidando os indicadores acima, as divergências abertas e o histórico de resoluções e escalonamentos. Esse relatório subsidia a revisão dos controles internos e serve de insumo para a governança da custódia.

### 9.3 Conexão com o relatório anual de controles internos

Os resultados da conciliação e os indicadores dela derivados integram os elementos considerados no **relatório anual de controles internos** previsto no **Art. 18** da Resolução CVM nº 32/2021. Este manual não detalha o conteúdo desse relatório, cujo escopo e forma são tratados nos documentos e políticas próprios de controles internos da instituição.

---

## 10. Papéis e Segregação de Funções

O processo observa **segregação de funções**, de modo que quem **gera** a posição não é quem a **concilia**, garantindo dupla conferência:

| Função | Responsabilidade | Área |
|---|---|---|
| **Geração de posição** | Produz a posição custodiada e o extrato de conta (`custodia/arquivos.php`); disponibiliza divergências ao custodiante (`custodia/index.php`) | **Custódia** |
| **Conciliação** | Importa, compara, classifica, trata e registra divergências (`admin/conciliacao.php`) | **Controladoria** |
| **Apuração de suspeitas** | Recebe escalonamentos (`alertas_fraude`) e conduz a apuração | **Compliance** |

A separação entre **custódia** (geração) e **controladoria** (conciliação) assegura a **dupla conferência** exigida por um ambiente de controle robusto (Art. 5º, II — sistemas de controle; Art. 16 — controles internos). O compliance atua como terceira instância independente para as divergências suspeitas.

---

## 11. Referência Normativa e Governança do Documento

**Fundamentação regulamentar:**

- **Art. 2º, §2º, I, "a"** — conservação, controle e conciliação das posições;
- **Art. 3º, parágrafo único** — as posições nas contas de custódia devem corresponder às mantidas pelo depositário central;
- **Art. 5º, II** — sistemas de controle;
- **Art. 13, §1º, I** — **conciliação diária** entre posições das contas de custódia e posições do depositário central;
- **Art. 13, V** — registro de erros e incidentes;
- **Art. 16** — controles internos escritos e verificáveis;
- **Art. 18** — relatório anual de controles internos.

> **Aviso de governança:** Este documento integra o pacote de comprovação para autorização de custódia e **deve ser revisado com as áreas Jurídica e de Compliance** antes de sua submissão formal à CVM e a cada atualização normativa. As referências normativas devem ser reconferidas contra a redação vigente da Resolução CVM nº 32/2021 e suas alterações (Resolução CVM nº 209/2024).

---

*Documento de referência interna. Ver também: `manual_custodia.md`, `manual_liquidacao_dvp.md`, `dossie_estrutura_operacional_tecnologica.md`, `matriz_conformidade_cvm32.md`, `politica_confidencialidade_seguranca.md`.*
