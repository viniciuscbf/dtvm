# Manual de Conciliação e Fiscalização do Custodiante — Administração Fiduciária

> **Documento de trabalho — v1.0** · Pacote de Autorização e Estruturação da atividade de **Administração Fiduciária** de fundos de investimento.
>
> **Base legal:**
> - **Resolução CVM nº 21/2021, art. 32** — o administrador fiduciário deve **fiscalizar os prestadores de serviços** contratados em nome do fundo: cumprimento dos **limites** (inc. I), a **estrutura** dos prestadores (inc. II), a **política de gerenciamento de risco** do gestor (incs. III e IV) e os **sistemas do custodiante** (inc. V).
> - **Resolução CVM nº 21/2021, art. 18** — deveres de **boa-fé, diligência e lealdade**, incluindo **contratar e verificar** a custódia dos ativos do fundo.
> - **Resolução CVM nº 175/2022, art. 104, I, "e"** — o administrador fiduciário deve **manter os registros contábeis** relativos às operações e ao patrimônio do fundo (que precisam **corresponder** à posição mantida pelo custodiante).
> - **Resolução CVM nº 175/2022, art. 81** — a responsabilidade dos prestadores é **segregada por esfera de atuação**, sem solidariedade automática, **sem prejuízo do dever de fiscalização** do administrador sobre os demais prestadores.
>
> **Perspectiva do documento:** este é o manual da **ADMINISTRADORA FIDUCIÁRIA** (controladoria) sobre a conciliação. A ótica aqui é a de **quem fiscaliza** o custodiante — não a de quem custodia. A ponta oposta (o dever de conciliação do próprio custodiante perante o depositário central, Res. CVM 32) está documentada em `../custodiante/manual_conciliacao.md`.

---

## 1. Objetivo

Este manual estabelece a metodologia, a periodicidade e os controles internos pelos quais a **administradora fiduciária** assegura que os **registros contábeis do fundo sob sua guarda** (Res. CVM 175, art. 104, I, "e") **correspondem à posição informada pelo custodiante** e ao ciclo de liquidação e de operações do fundo.

A conciliação, na visão da administradora, cumpre duas funções regulatórias distintas e complementares:

1. **Fidedignidade contábil** — comprovar que a contabilidade do fundo (posição, caixa e operações registradas pela controladoria) bate com a posição custodiada. Registro contábil que não corresponde à custódia é, por definição, um registro sob suspeita.

2. **Evidência da diligência** — materializar o **dever de fiscalizar o custodiante** (Res. CVM 21, art. 32, V — *sistemas do custodiante*). A conciliação diária é a prova documental, verificável e datada, de que a administradora **exerceu** essa fiscalização. Essa evidência é o que sustenta, na prática, o **regime de responsabilidade segregada** do **art. 81 da Res. CVM 175**: a segregação de responsabilidade **não é presumida** — ela pressupõe que cada esfera tenha cumprido o seu dever. A administradora só pode invocar a segregação se demonstrar que **fiscalizou**. Sem a trilha de conciliação, a segregação do art. 81 fica sem lastro probatório.

> **Referências cruzadas:** `matriz_conformidade_cvm21_175.md` (mapeamento dispositivo a dispositivo), `dossie_estrutura_operacional_tecnologica.md` (arquitetura da controladoria), `manual_controladoria_precificacao.md` (cálculo e prévia da cota), `politica_confidencialidade_seguranca.md` (tratamento de dados e trilha) e o pacote de custódia `../custodiante/manual_conciliacao.md` (a outra ponta do processo).

---

## 2. Escopo, Frentes e Periodicidade

### 2.1 Periodicidade

A conciliação é executada em **base diária**, em todo dia útil. Nenhuma posição contábil do fundo permanece um dia útil sem confronto com a posição informada pelo custodiante. A tempestividade diária é parte do próprio conceito de diligência: fiscalização que ocorre apenas periodicamente não detecta a divergência no ciclo em que ela se forma.

### 2.2 Frentes de conciliação

A administradora organiza o confronto em **três frentes**, registradas no campo `[origem]` da tabela `conciliacao` do piloto (`/admin/`):

| Frente (`[origem]`) | Fontes confrontadas | Pergunta de controle | Fundamento |
|---|---|---|---|
| **Posição × Custodiante** | Posição contábil da controladoria × arquivo de posição do custodiante | *A contabilidade do fundo reflete o que está efetivamente custodiado?* | Res. 175, art. 104, I, "e"; Res. 21, art. 32, V |
| **Caixa × Extrato** | Saldo de caixa contábil × extrato da conta de liquidação do fundo | *O caixa registrado bate com o extrato bancário/de liquidação?* | Res. 175, art. 104, I, "e"; art. 18 |
| **Operações × Gestor** | Operações contabilizadas × boletas/instruções do gestor | *As operações contabilizadas correspondem ao que o gestor instruiu?* | Res. 21, art. 32, III–IV (diligência sobre o gestor) |

> **Nota de escopo.** As três frentes têm naturezas distintas. **Posição × Custodiante** e **Caixa × Extrato** verificam se a contabilidade da administradora bate com fontes **externas** (custodiante e conta de liquidação). **Operações × Gestor** verifica a consistência entre a **instrução** do gestor e o que foi contabilizado — é a frente que aproxima a fiscalização do gestor da fiscalização do custodiante. As três, somadas, cobrem o ciclo completo: instrução → operação → custódia → contabilidade.

---

## 3. Fontes de Dados

A conciliação parte do confronto entre a **base contábil interna** da administradora e as **fontes de referência** de cada frente:

| Fonte | Natureza | Origem | Frente atendida |
|---|---|---|---|
| **Posição contábil (interna)** | Interna | Registros contábeis mantidos pela controladoria (art. 104, I, "e") | Todas |
| **Arquivo de posição do custodiante** | Externa | Gerado **pelo custodiante**, do lado da custódia (`custodia/arquivos.php` no piloto) | Posição × Custodiante |
| **Extrato da conta de liquidação** | Externa | Gerado do lado da custódia/liquidação (`custodia/arquivos.php`) | Caixa × Extrato |
| **Boletas / instruções do gestor** | Externa (gestor) | Ordens e composição-alvo instruídas pelo gestor | Operações × Gestor |

> **Ponto de fronteira (importante).** É o **custodiante** — não a administradora — quem **gera** os arquivos de posição e de extrato, pois eles refletem a guarda dos ativos, que é atividade da custódia. A administradora **recebe, importa e confronta**. Essa separação (custódia gera / controladoria concilia) é o que dá sentido à conciliação como controle independente: quem produz a posição não é quem a valida.

### 3.1 🟩 Roadmap — captura automática dos arquivos do custodiante

> 🟩 **Item de roadmap — honestidade de estágio.** No estágio atual da plataforma-piloto, a **captura automática dos arquivos de posição e de extrato do custodiante/depositário** é **simulada**, para demonstração do fluxo de conciliação ponta a ponta. A **captura automática real** — recepção e importação automatizada dos arquivos oficiais do custodiante (via conexão direta, mensageria ou API), com autenticação de origem — é **item planejado do roadmap de produção**. A lógica de comparação, classificação, tratamento e trilha descrita neste manual **já está implementada** e independe da origem do arquivo; o que evolui de simulado para automático é apenas o **transporte** do arquivo até a controladoria.

---

## 4. Processo Diário de Conciliação (Passo a Passo)

O ciclo diário é executado em todo dia útil, em rotina de processamento em lote, e segue seis passos:

```
┌───────────────────────────────────────────────────────────────────────┐
│  1. IMPORTAR    → recebe posição do custodiante, extrato da conta e    │
│                   boletas do gestor; carrega a posição contábil interna │
│  2. COMPARAR    → confronta item a item: ativo × ativo, quantidade ×    │
│                   quantidade, saldo × saldo, operação × instrução       │
│  3. CLASSIFICAR → toda diferença vira `Divergente` e recebe             │
│                   `classificacao` = Timing / Erro / Suspeita            │
│  4. TRATAR      → resolver com justificativa OU escalar ao compliance   │
│  5. REGISTRAR   → grava a trilha (resolucao, resolvido_por, resolvido_em)│
│  6. EVIDENCIAR  → o conjunto conciliado + trilha compõe a prova da      │
│                   diligência (art. 32; sustenta o art. 81)             │
└───────────────────────────────────────────────────────────────────────┘
```

| Passo | Ação | Registro no piloto (`/admin/`) |
|---|---|---|
| 1 | **Importar** posição do custodiante, extrato e boletas; carregar posição contábil interna | Arquivos recebidos do custodiante (🟩 hoje simulado) |
| 2 | **Comparar** as fontes item a item por frente | Rotina batch de conciliação |
| 3 | **Classificar** cada divergência | `conciliacao.[situacao]` = `Divergente`; `conciliacao.[classificacao]` = `Timing` \| `Erro` \| `Suspeita` |
| 4 | **Tratar** — resolver com justificativa ou escalar | Tela `admin/conciliacao.php` |
| 5 | **Registrar** trilha de resolução | `[resolucao]`, `[resolvido_por]`, `[resolvido_em]` |
| 6 | **Evidenciar** a diligência | Conjunto `Conciliado`/`Resolvido` + `alertas_fraude` vinculados |

Itens sem qualquer diferença são registrados diretamente com `[situacao]` = `Conciliado`. As divergências que exigem ação da administradora aparecem em destaque no topo de `admin/conciliacao.php`.

---

## 5. Classificação e Critérios de Divergência

Toda divergência recebe uma classificação no campo `[classificacao]`, que determina o encaminhamento. A administradora usa três classes:

| Classificação | Definição | Exemplo típico | Encaminhamento |
|---|---|---|---|
| **Timing** | Diferença **temporária** por defasagem entre eventos que se conciliam naturalmente no ciclo de liquidação | Operação liquidada em D+1 ainda não refletida na posição interna no fechamento de D; provisão de evento com data de referência distinta | Resolução com justificativa após confirmação da liquidação |
| **Erro** | Divergência **real**, por falha de lançamento, cadastro ou processamento, **corrigível internamente** | Quantidade contabilizada a maior/menor; ativo lançado em conta contábil incorreta; preço de fonte defasada | Correção do lançamento + resolução com justificativa |
| **Suspeita** | Divergência **sem explicação legítima aparente**, com potencial indício de fraude, apropriação indevida ou registro sem lastro | **"Ativo fantasma"**: ativo constante na **posição do custodiante sem contrapartida** na controladoria; ou o inverso, ativo na contabilidade **sem correspondência** na custódia | **Escalonamento obrigatório ao compliance** |

### 5.1 Critérios de materialidade e prazo

- Divergências **Timing** devem ser reconfirmadas e resolvidas até o ciclo de liquidação previsto. Se **persistirem** além do prazo esperado, são **reclassificadas** como `Erro` ou `Suspeita` — timing que não se resolve deixa de ser timing.
- Divergências **Suspeita** são de tratamento **prioritário** e disparam escalonamento **imediato** ao compliance, **independentemente de valor**. Fraude não tem piso de materialidade.
- **Nenhuma** divergência é encerrada sem preenchimento do campo `[resolucao]` (justificativa) e da trilha de responsável e data. Não há encerramento silencioso.

> **Assimetria relevante — "ativo fantasma".** O caso mais grave para a administradora é o ativo que aparece na **posição do custodiante** e **não** tem contrapartida na contabilidade do fundo. Como é o custodiante quem guarda o ativo, um item custodiado sem lastro contábil (ou o inverso, um lançamento contábil sem custódia) indica falha de controle na fronteira entre as duas esferas — exatamente o ponto que o art. 32, V (sistemas do custodiante) manda fiscalizar. Esse item **jamais** é conciliado automaticamente; é sempre tratado como incidente.

---

## 6. Tratamento e Escalonamento ao Compliance

O tratamento ocorre na tela `admin/conciliacao.php`, com dois caminhos.

### 6.1 Resolução com justificativa (Timing / Erro)

Para divergências de **Timing** e **Erro**, o responsável pela controladoria registra a **resolução com justificativa**. O sistema grava:

- `[resolucao]` — descrição da causa e do tratamento aplicado;
- `[resolvido_por]` — identificação nominal do responsável;
- `[resolvido_em]` — data e hora da resolução (`NOW()`).

A `[situacao]` passa de `Divergente` para `Resolvido`.

### 6.2 Escalonamento ao compliance (Suspeita)

Para divergências **Suspeita**, o responsável **escala ao compliance** diretamente da tela. O escalonamento, no piloto:

- **cria um registro em `alertas_fraude`** (com `regra`, `tipo`, `severidade`, `explicacao` e `evidencia`), vinculando a divergência ao fluxo de apuração de compliance;
- marca a divergência com `[classificacao]` = `Suspeita`;
- **preserva a trilha** — a divergência permanece rastreável até o parecer do compliance, sem encerramento silencioso.

```
Divergência (situacao = Divergente)
        │
        ├── Timing / Erro ──► Resolução com justificativa ──► situacao = Resolvido
        │                       (resolucao · resolvido_por · resolvido_em)
        │
        └── Suspeita ────────► Escalar ao compliance ──► registro em `alertas_fraude`
                                 (trilha preservada até parecer)
```

### 6.3 Quando é suspeita (e não erro)

A fronteira entre **Erro** e **Suspeita** é decisiva porque só a suspeita escala. O critério prático:

| Sinal | Erro | Suspeita |
|---|---|---|
| Há causa identificável e legítima? | Sim (falha operacional explicável) | Não |
| Corrigível internamente sem apuração? | Sim | Não |
| Há lastro na outra ponta (custódia ⇄ contabilidade)? | Sim, apenas registrado errado | **Não há lastro** de um dos lados |
| Padrão de reincidência inexplicada? | — | Agrava para suspeita |

Na dúvida entre Erro e Suspeita, **escala-se**: o custo de um falso alarme ao compliance é menor que o custo de silenciar uma fraude.

---

## 7. Trilha de Auditoria e Evidência da Diligência

Cada divergência produz um registro auditável do seu percurso, na tabela `conciliacao`. Esse conjunto é a **evidência formal da diligência** exigida pelo **art. 32** e é o **lastro probatório do art. 81**.

| Campo | Conteúdo | Finalidade de auditoria |
|---|---|---|
| `[origem]` | Frente de conciliação | Identifica a fonte confrontada |
| `[situacao]` | `Conciliado` / `Divergente` / `Resolvido` | Estado do item no ciclo |
| `[classificacao]` | `Timing` / `Erro` / `Suspeita` | Natureza da divergência |
| `[detalhe]` | Descrição do item divergente | Contextualiza a análise |
| `[valor_diferenca]` | Diferença apurada | Dimensiona a divergência |
| `[resolucao]` | Justificativa da resolução ou motivo do escalonamento | Comprova a análise realizada |
| `[resolvido_por]` | Responsável nominal | Atribui responsabilidade |
| `[resolvido_em]` | Data e hora | Comprova tempestividade |

Divergências escaladas geram, adicionalmente, registro em `alertas_fraude`, preservando o vínculo entre conciliação e compliance.

> **Por que a trilha sustenta o art. 81.** A responsabilidade segregada (Res. 175, art. 81) **não isenta** o administrador de fiscalizar. Perante a CVM ou em contencioso, a pergunta não é "de quem era a atividade", mas "o administrador **fiscalizou** como devia?". A resposta é a trilha: o histórico datado e nominal de conciliações feitas, divergências detectadas, classificadas e tratadas ou escaladas. **Um fundo com trilha de conciliação completa demonstra diligência; um fundo sem trilha demonstra omissão** — e a omissão corrói a segregação de responsabilidade. A conciliação é, portanto, tanto controle operacional quanto **defesa jurídica documentada**.

---

## 8. Fiscalização dos Demais Prestadores (Art. 32, I–V)

O art. 32 da Res. CVM 21 lista dimensões de fiscalização que vão além da posição custodiada. A conciliação é o mecanismo central, mas o dever é mais amplo:

| Inciso (art. 32) | Objeto da fiscalização | Como a administradora atua | Evidência no piloto |
|---|---|---|---|
| **I — Limites** | Cumprimento dos limites de composição/concentração da carteira | A administradora **acompanha e fiscaliza** o enquadramento | `admin/carteiras.php`; `gestor/enquadramento.php` |
| **II — Estrutura** | Adequação da estrutura dos prestadores contratados | Verificação da estrutura operacional e tecnológica dos prestadores | `dossie_estrutura_operacional_tecnologica.md` |
| **III–IV — Risco do gestor** | Aderência à **política de gerenciamento de risco** do gestor | Acompanhamento de risco e da consistência instrução × operação | Frente **Operações × Gestor**; `gestor/enquadramento.php` |
| **V — Sistemas do custodiante** | Integridade e confiabilidade dos **sistemas do custodiante** | **Conciliação diária** Posição × Custodiante e Caixa × Extrato | `admin/conciliacao.php`; tabela `conciliacao` |

> **Fronteira de deveres — limites (inc. I).** O **enquadramento** da carteira aos limites é **dever do gestor** (Res. CVM 175, **art. 89** — o gestor observa a política de investimento e os limites). O papel da administradora, no art. 32, I, é **acompanhar e fiscalizar** esse enquadramento, **não** executá-lo. A administradora não substitui o gestor: ela verifica, registra desenquadramentos e cobra o reenquadramento. Confundir os dois papéis quebraria a segregação do art. 81.

---

## 9. Papéis e Segregação de Funções

O processo observa **segregação de funções**: quem **gera** a posição não é quem a **concilia**, e quem **opera** o fundo não é quem o **contabiliza**.

| Função | Responsabilidade | Área | Perfil no piloto |
|---|---|---|---|
| **Geração de posição/extrato** | Produz a posição custodiada e o extrato da conta (`custodia/arquivos.php`); atividade da guarda | **Custódia** | `custodia` |
| **Operação do fundo** | Instrui operações, emite boletas, executa a política de investimento e o enquadramento (art. 89) | **Gestão** | `gestor` |
| **Conciliação e contabilidade** | Importa, compara, classifica, trata, registra a trilha e mantém os registros contábeis (art. 104, I, "e") | **Controladoria / Administração fiduciária** | `admin` |
| **Apuração de suspeitas** | Recebe escalonamentos (`alertas_fraude`) e conduz a apuração independente | **Compliance** | (fluxo de compliance) |

A separação entre **custódia** (gera), **gestão** (opera) e **controladoria** (concilia e contabiliza) assegura a **dupla conferência** que dá credibilidade à conciliação. O compliance atua como **terceira instância independente** para as divergências suspeitas. Essa arquitetura de perfis segregados é a implementação prática do art. 27/art. 30 da Res. CVM 21 (segregação de atividades) e o pressuposto do art. 81 da Res. CVM 175.

---

## 10. Exemplo Concreto — Ativo na Posição do Custodiante Não Refletido na Controladoria

> **Cenário.** Na conciliação diária da frente **Posição × Custodiante** de determinado dia útil, a rotina batch identifica, no arquivo de posição enviado pelo **custodiante**, um ativo — por exemplo, um lote de debêntures do fundo — que **não possui contrapartida nos registros contábeis** mantidos pela controladoria. Não há boleta do gestor, liquidação pendente ou defasagem de timing que o explique. Trata-se de um potencial **"ativo fantasma"**.

| Etapa | Registro | Campo |
|---|---|---|
| **Identificação** | Rotina batch marca o item como `Divergente`, frente `Posição × Custodiante` | `[situacao]` = `Divergente`; `[origem]` = `Posição × Custodiante` |
| **Análise** | Não há operação, liquidação ou timing que justifique; ausência total de lastro contábil na controladoria | `[detalhe]` = "Debênture na posição do custodiante sem contrapartida contábil" |
| **Classificação** | Divergência sem explicação legítima → suspeita | `[classificacao]` = `Suspeita` |
| **Escalonamento** | Operador da controladoria aciona **"Escalar ao compliance"** em `admin/conciliacao.php` → gera alerta | Registro em `alertas_fraude` (severidade `Alta`) |
| **Trilha** | Responsável e data/hora gravados; motivo do escalonamento registrado | `[resolvido_por]`, `[resolvido_em]`, `[resolucao]` = "Ativo custodiado sem lastro contábil na controladoria; escalado ao compliance para apuração" |
| **Encerramento** | Concluído **somente** após parecer do compliance; permanece rastreável em todo o percurso | — |

**Leitura regulatória do caso.** Este exemplo mostra a barreira de controle funcionando: um ativo custodiado sem lastro na contabilidade do fundo **jamais é conciliado automaticamente**. Ao detectá-lo, classificá-lo e escalá-lo com trilha datada e nominal, a administradora (i) protege a **fidedignidade contábil** (art. 104, I, "e"), (ii) exerce e **documenta** a **fiscalização dos sistemas do custodiante** (art. 32, V), e (iii) constitui a **evidência de diligência** que dá lastro ao regime de **responsabilidade segregada** (art. 81). O inverso — um lançamento contábil sem correspondência na custódia — recebe o mesmo tratamento.

---

## 11. Referência Normativa e Governança do Documento

**Fundamentação regulamentar:**

- **Res. CVM 21, art. 18** — deveres de boa-fé, diligência e lealdade; contratar e verificar a custódia dos ativos.
- **Res. CVM 21, art. 32** — dever de **fiscalizar os prestadores**: limites (I), estrutura (II), política de risco do gestor (III–IV) e **sistemas do custodiante** (V).
- **Res. CVM 175, art. 89** — o **gestor** observa a política de investimento e os **limites** (enquadramento); a administradora acompanha/fiscaliza.
- **Res. CVM 175, art. 104, I, "e"** — o administrador mantém os **registros contábeis** do fundo (que devem corresponder à custódia).
- **Res. CVM 175, art. 81** — **responsabilidade segregada** por esfera, sem solidariedade automática, **sem prejuízo do dever de fiscalização**.

> **Aviso de governança.** Este documento integra o pacote de estruturação da administração fiduciária e **deve ser revisado com as áreas Jurídica e de Compliance** antes de qualquer submissão formal à CVM e a cada atualização normativa. As referências devem ser reconferidas contra a redação vigente da Res. CVM 21/2021 e da Res. CVM 175/2022 e suas alterações. Itens marcados 🟩 são de **roadmap** e não representam funcionalidade em produção no estágio atual do piloto.

---

*Documento de referência interna. Ver também: `matriz_conformidade_cvm21_175.md`, `dossie_estrutura_operacional_tecnologica.md`, `manual_controladoria_precificacao.md`, `politica_confidencialidade_seguranca.md` e o pacote de custódia `../custodiante/manual_conciliacao.md` (a outra ponta do processo).*
