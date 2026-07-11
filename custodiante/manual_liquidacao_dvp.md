# Manual de Liquidação DVP (Entrega contra Pagamento)

> **Base legal:** Resolução CVM nº 32/2021 (consolidada com as alterações da Resolução CVM nº 209/2024), em especial o **art. 13, incisos II e III**, que impõem à instituição custodiante o dever de garantir a **integridade e a certeza da origem das instruções** de movimentação (inc. II) e de assegurar a **regular movimentação dos ativos, processando-a com controle eletrônico e documental** (inc. III). O presente manual integra o pacote de comprovação de estrutura operacional e tecnológica submetido no pleito de autorização para o exercício da atividade de custódia de valores mobiliários, com foco na custódia de recursos de fundos de investimento.

---

## 1. Objetivo e escopo

Este documento descreve a política e os procedimentos operacionais adotados pela instituição para a **liquidação de operações no padrão DVP (Delivery versus Payment — entrega contra pagamento)**, abrangendo:

- a recepção e validação de instruções de liquidação originadas de boletas do gestor;
- os ciclos de liquidação por classe de ativo;
- o fluxo operacional passo a passo da liquidação financeira e da movimentação de ativos;
- o tratamento de falhas de liquidação (*fails*);
- os registros, a trilha de auditoria e a interface com a conciliação diária.

O manual descreve o comportamento implementado na **plataforma-piloto** (PHP + MySQL, portal `/custodia/`), cujo módulo central de liquidação reside em `custodia/instrucoes.php`, e projeta os elementos de infraestrutura de mercado (RSFN, B3, SELIC) que constituem **roadmap pós-deferimento** 🟩.

**Documentos correlatos:** `manual_custodia.md`, `manual_conciliacao.md`, `dossie_estrutura_operacional_tecnologica.md` e `matriz_conformidade_cvm32.md`. Ao longo do texto, os campos persistidos na base de dados do piloto são indicados entre [colchetes].

---

## 2. O conceito de DVP (entrega contra pagamento)

A liquidação DVP é o **padrão de mercado** consagrado nas infraestruturas do Sistema Financeiro Nacional (SELIC para títulos públicos, B3 para renda variável e cotas, e STR para a perna financeira em Reservas Bancárias). Não corresponde a um dispositivo específico da Resolução CVM nº 32/2021 — trata-se de um conceito **operacional das infraestruturas de mercado** que instrumentaliza os deveres de regular movimentação e controle previstos no art. 13.

O princípio essencial do DVP é a **simultaneidade e condicionalidade recíproca** entre as duas pernas de uma operação:

- a **perna do ativo** (entrega/recebimento do título ou cota); e
- a **perna financeira** (pagamento/recebimento do caixa correspondente).

Sob o mecanismo DVP, **a transferência do ativo somente se efetiva se, e no mesmo instante em que, ocorre o pagamento financeiro correspondente** — e vice-versa. Uma perna nunca se completa sem a outra.

### Por que o DVP elimina o risco de principal

O **risco de principal** é o risco de uma contraparte entregar o ativo (ou o dinheiro) e **não receber a contrapartida devida**, perdendo o valor integral (o principal) da operação. Ao amarrar as duas pernas em uma liquidação atômica e condicional, o DVP torna **impossível** que uma parte entregue sem receber:

- se o pagamento não é confirmado, o ativo **não se move**;
- se o ativo não é entregue, o caixa **não é debitado**.

Dessa forma, a instituição custodiante assegura ao fundo custodiado que **nenhuma movimentação de ativo ocorre sem a contrapartida financeira certa**, atendendo materialmente ao dever de regular movimentação e processamento com controle eletrônico e documental (art. 13, III).

---

## 3. Fontes de instrução e validação da integridade e origem (art. 13, II)

Toda liquidação tem origem em uma **instrução de liquidação**, que por sua vez deriva de uma **boleta enviada pelo gestor** do fundo. A instituição custodiante não origina operações por conta própria; ela processa instruções cuja **integridade e certeza da origem** devem ser asseguradas nos termos do **art. 13, II**.

### 3.1 A boleta do gestor

A boleta é o documento eletrônico que expressa a intenção do gestor de negociar um ativo por conta do fundo. Ela carrega, no mínimo:

| Elemento da boleta | Descrição |
|---|---|
| Fundo | Fundo de investimento titular da operação |
| Ativo | Identificação do título/ação/cota |
| Lado | Compra ou venda |
| Quantidade e preço | Volume e valor unitário |
| Contraparte | Identificação da contraparte da operação [contraparte] |
| Data da operação | Data de negociação (base para o ciclo D+N) |

### 3.2 Aceite e rejeição com motivo

O módulo `custodia/instrucoes.php` implementa o **controle de aceite/rejeição** da boleta pela mesa de custódia:

- **Aceite:** validada a integridade dos dados e a legitimidade da origem, a boleta é aceita e **gera uma instrução de liquidação** — cria-se um registro em [liquidacoes] vinculado à boleta de origem [boleta_id], com [status] inicial **Pendente**.
- **Rejeição:** identificada inconsistência (dados incompletos, ativo divergente, contraparte não reconhecida, origem não autenticada), a boleta é **rejeitada com registro do motivo**, não gerando instrução de liquidação.

O aceite/rejeição com motivo constitui o **ponto de controle de integridade e origem** exigido pelo art. 13, II: nenhuma instrução avança para agenda de liquidação sem passar pela validação documentada da mesa de custódia. A autenticação da origem da boleta (identificação e credenciamento do gestor emissor) e o registro do ato de aceite compõem a **certeza da origem**.

---

## 4. Ciclos de liquidação por classe de ativo

O prazo entre a data da operação e a data de liquidação (o ciclo **D+N**) varia conforme a classe do ativo e o **modelo de liquidação** da infraestrutura. A boleta registra a data pactuada; a plataforma valida contra o padrão do mercado.

| Classe de ativo | Infraestrutura | Ciclo | Modelo de liquidação |
|---|---|---|---|
| Título público federal | SELIC | **D+0** (tempo real; D+1 por convenção comercial) | **LBTR/DVP modelo 1** — operação por operação, bruta, em tempo real (grade 6h30–18h30) |
| Renda fixa privada (registrada) | B3 Balcão (NoMe/ex-Cetip) | **D+0 a D+1** (data pactuada bilateralmente) | **Duplo comando** (matching bilateral); modalidades: Bruta (DVP via STR), Bilateral ou Sem modalidade |
| Ação (renda variável) | Câmara B3 (CCP) | **D+2** (travado pela câmara) | **Netting multilateral** — saldo líquido em janela única diária (pagamentos 14h10–14h50; DVP final ~15h50; mensagens LDL) |
| Cota de fundo listada | Câmara B3 | **D+2** | Como ações (netting multilateral) |
| Cota de fundo aberto (não listada) | Administrador/custodiante | Cotização do fundo | Fora de CCP — ordem → cotização → liquidação na conta do fundo |

**Pontos que distinguem os modelos (importante para não homogeneizar):**

- No **Selic** não há "lote no fim do dia": cada operação liquida **individualmente, em tempo real**, com as duas pernas simultâneas (título e dinheiro) — é o clássico DVP modelo 1 do BIS.
- Na **bolsa** o custodiante **não liquida operação por operação**: a Câmara B3 (contraparte central) compensa tudo **multilateralmente** e liquida o **saldo líquido** do liquidante na janela — a perna financeira usa as mensagens **LDL** (LDL0001 resultado líquido; LDL0004/LDL0005 pagamentos), não uma transferência STR por negócio.
- No **balcão** a operação só existe quando **as duas pontas comandam** (duplo comando); a perna financeira da modalidade Bruta passa pelo STR via banco liquidante.

A perna financeira transita pela **conta de Reservas Bancárias** do banco liquidante (diretamente, se o custodiante for banco; ou por banco liquidante contratado) — ver seção 6.

---

## 5. Fluxo operacional passo a passo

O ciclo completo, da boleta ao reflexo na posição, segue as etapas abaixo. O módulo `custodia/instrucoes.php` orquestra as transições de [status] em [liquidacoes] e a geração de mensageria em [mensagens_spb].

### 5.1 Diagrama textual do fluxo

```
 ┌────────────────────┐
 │  BOLETA DO GESTOR  │  (fonte da instrução — art. 13, II)
 └─────────┬──────────┘
           │
           ▼
 ┌────────────────────┐      rejeição c/ motivo
 │ ACEITE / REJEIÇÃO  ├───────────────────────────►  [não gera instrução]
 │ (mesa de custódia) │
 └─────────┬──────────┘
           │ aceite
           ▼
 ┌────────────────────┐
 │ INSTRUÇÃO DE       │  cria [liquidacoes]: status = Pendente
 │ LIQUIDAÇÃO         │  vincula [boleta_id], [contraparte]
 └─────────┬──────────┘
           │
           ▼
 ┌────────────────────┐
 │ AGENDA D+N         │  define [data_liquidacao]
 │ (D+1 RF / D+2 RV)  │  (por classe de ativo — seção 4)
 └─────────┬──────────┘
           │  (na data de liquidação)
           ▼
 ┌────────────────────┐        falha
 │ LIQUIDAÇÃO DVP     ├──────────────────►  status = Falha  ──► (ver seção 7)
 │ (entrega x pgto)   │
 └─────────┬──────────┘
           │ sucesso
           ▼
 ┌────────────────────┐
 │ MOVIMENTAÇÃO DE    │  debita/credita o caixa da conta do fundo
 │ CAIXA              │
 └─────────┬──────────┘
           ▼
 ┌────────────────────┐
 │ CONFIRMAÇÃO NA     │  gera mensagem em [mensagens_spb]
 │ MENSAGERIA (SPB)   │  (SEL1099 no Selic · LDL0005 na câmara ·
 └─────────┬──────────┘   STR0004 na bruta de balcão)
           ▼
 ┌────────────────────┐
 │ REFLEXO NA POSIÇÃO │  atualiza a carteira do fundo
 │ (carteira)         │  status = Liquidada
 └────────────────────┘
```

### 5.2 Descrição das etapas

1. **Boleta → aceite.** O gestor envia a boleta; a mesa de custódia valida integridade e origem e realiza aceite ou rejeição com motivo (art. 13, II).
2. **Aceite → instrução.** A boleta aceita gera a instrução de liquidação em [liquidacoes] com [status] = **Pendente**, vinculando [boleta_id] e [contraparte].
3. **Instrução → agenda.** A [data_liquidacao] é a **pactuada na boleta**, validada contra o padrão do segmento (TPF D+0 em tempo real; balcão D+0/D+1 bilateral; bolsa D+2 travado pela câmara).
4. **Agenda → liquidação DVP.** Na [data_liquidacao], executa-se a liquidação no modelo do segmento: LBTR individual no Selic; saldo líquido multilateral na janela da Câmara B3; DVP bruta bilateral no balcão. A perna do ativo e a financeira são condicionadas mutuamente.
5. **Liquidação → movimentação de caixa.** Confirmada a liquidação, o **caixa da conta do fundo é movimentado** (debitado em compras, creditado em vendas), com data contábil = data de liquidação.
6. **Movimentação → confirmação na mensageria.** Gera-se a confirmação em [mensagens_spb] com o código real do Catálogo do SFN: **SEL1099** ("SEL informa movimentação financeira") no Selic; **LDL0005** (câmara paga credores) na bolsa; **STR0004** (transferência IF→IF do banco liquidante) na modalidade bruta de balcão. *(Nota: STR0008 é a transferência entre contas de clientes — a TED — e não uma mensagem de confirmação.)*
7. **Confirmação → reflexo na posição.** A **posição na carteira do fundo é atualizada** e o [status] da liquidação passa a **Liquidada**.
8. **Trilha de falha.** Se a liquidação não se completa, o [status] é marcado como **Falha**, disparando o tratamento da seção 7.

---

## 6. Liquidação financeira e conta Reservas/STR

A **perna financeira** do DVP é sempre liquidada em moeda de banco central, por meio do **STR (Sistema de Transferência de Reservas)**, com débito/crédito em **conta de Reservas Bancárias**. A forma como a instituição custodiante acessa essa conta depende de seu enquadramento:

| Enquadramento | Acesso ao STR / Reservas | Implicação operacional |
|---|---|---|
| **Banco liquidante** | Possui conta Reservas própria e acesso direto ao STR | Liquida a perna financeira diretamente, sem intermediário |
| **Banco não-liquidante** | Não possui acesso direto; contrata banco liquidante | A perna financeira transita pelo banco liquidante contratado, sob acordo formal |

Em ambos os casos, o princípio DVP é preservado: a movimentação do caixa da conta do fundo somente ocorre **contra a entrega/recebimento efetivo do ativo** na central depositária correspondente.

> 🟩 **Roadmap pós-deferimento:** a definição do enquadramento (liquidante próprio x banco liquidante contratado), a homologação junto à **RSFN (Rede do Sistema Financeiro Nacional)** e as **adesões operacionais à B3 e ao SELIC** serão concluídas após o deferimento do pleito. Na plataforma-piloto, a interação com STR/SELIC/B3 é **simulada** por meio da mensageria interna [mensagens_spb].

---

## 7. Tratamento de falhas de liquidação (*fails*)

Uma **falha de liquidação** (*fail*) ocorre quando, na [data_liquidacao], a operação não se completa — tipicamente por indisponibilidade de ativo na perna de entrega, insuficiência de recursos na perna financeira, ou divergência de instruções entre as partes. O piloto trata a falha por meio do [status] **Falha** em [liquidacoes].

### 7.1 Ciclo de tratamento

1. **Identificação.** Não confirmada a liquidação DVP na [data_liquidacao], o registro em [liquidacoes] recebe [status] = **Falha**. A falha é sinalizada à mesa de custódia.
2. **Repactuação.** A mesa de custódia apura a causa e articula com a contraparte [contraparte] a **repactuação** das condições e da nova data de liquidação.
3. **Reliquidação.** Ajustadas as condições, a operação é **reliquidada** — reprocessada no padrão DVP, retornando ao fluxo da seção 5 a partir da etapa de liquidação. Em caso de sucesso, o [status] evolui para **Liquidada**.
4. **Registro.** Toda a ocorrência (falha, causa, repactuação e reliquidação) é **registrada** com carimbo de tempo, atendendo ao **art. 13, V** (registro de acessos, erros, incidentes e interrupções).

### 7.2 Estados de uma liquidação

| [status] | Significado | Transição possível |
|---|---|---|
| **Pendente** | Instrução gerada e agendada; aguarda a [data_liquidacao] | → Liquidada / Falha |
| **Liquidada** | Liquidação DVP concluída; caixa e posição refletidos | (estado final) |
| **Falha** | Liquidação não concluída na data prevista | → Pendente (reliquidação) → Liquidada |

---

## 8. Mensageria e confirmações (padrão de catálogo SPB)

As confirmações de liquidação são materializadas em mensagens do **Catálogo de Serviços do SFN** (BCB/Deinf), persistidas na tabela [mensagens_spb]. Códigos empregados (todos reais, verificados no catálogo v5.12):

| Código | Grupo / infraestrutura | Significado oficial | Uso na plataforma |
|---|---|---|---|
| **SEL1052** | SEL / Selic | Participante requisita operação definitiva | Comando de compra/venda de título público |
| **SEL1054 / SEL1056** | SEL / Selic | Operação compromissada / retorno | **Zeragem over** do caixa (contratação e retorno na abertura) |
| **SEL1081** | SEL / Selic | Consulta posição de custódia | Base do batimento diário (art. 13, §1º, I) |
| **SEL1099** | SEL / Selic | SEL informa movimentação financeira | Confirmação da perna financeira no Selic |
| **LDL0001 / LDL0005** | LDL / Câmara B3 | Resultado líquido informado / câmara paga credores | Liquidação multilateral de bolsa (janela) |
| **STR0004** | STR | IF requisita transferência para IF | Perna financeira bruta do balcão (banco liquidante) |
| **STR0008** | STR | IF requisita transferência entre contas de **clientes** (a TED) | Pagamentos a cotistas (resgates) — **não** é mensagem de confirmação |
| **"052"** | — (NoMe/Cetip21) | Tipo de operação: compra/venda definitiva | Comando da ponta no balcão (duplo comando) |

> 🟩 **Homologação futura:** a **homologação junto à RSFN** e a integração produtiva com SELIC, STR e B3 compõem o roadmap pós-deferimento. No piloto, a geração de mensagens em [mensagens_spb] é **simulada** (tráfego e semântica fiéis ao catálogo; itens rotulados ARQ-POS/AGENDA-EV representam arquivos/feeds fora da RSFN) e serve à comprovação de estrutura e à trilha de auditoria.

---

## 9. Registros, trilha de auditoria e interface com a conciliação (art. 13, V)

Nos termos do **art. 13, V**, a instituição mantém **registro de acessos, erros, incidentes e interrupções**. Aplicado à liquidação DVP, o registro compreende:

- o **ciclo de vida completo** de cada liquidação em [liquidacoes] (Pendente → Liquidada / Falha), com [data_liquidacao], [contraparte] e [boleta_id];
- as **mensagens de confirmação** em [mensagens_spb], com carimbo de tempo;
- os **eventos de aceite/rejeição** de boletas, com motivo;
- as **falhas, repactuações e reliquidações**, com causa e horário.

### 9.1 Interface com a conciliação diária

A trilha de liquidação alimenta a **conciliação diária** entre as posições internas (carteira do fundo) e as posições nas centrais depositárias e o caixa em Reservas. A movimentação de caixa e o reflexo na posição gerados pela liquidação DVP são os insumos conciliados diariamente, encerrando o ciclo de controle das posições (art. 2º, §2º) por meio de sistemas de registro, processamento e controle (art. 5º, II).

> O procedimento detalhado de conciliação, incluindo tratamento de quebras e prazos, é descrito em **`manual_conciliacao.md`**.

---

## 10. Papéis e segregação de funções

A liquidação DVP observa **segregação de funções** entre a execução e o controle, princípio de mitigação de risco operacional:

| Papel | Área | Responsabilidade |
|---|---|---|
| **Execução** | Mesa de custódia | Aceite/rejeição de boletas; geração de instruções; execução da liquidação DVP; movimentação de caixa; tratamento de falhas e reliquidação |
| **Controle** | Controladoria | Conciliação diária das posições e do caixa; verificação da trilha; tratamento de quebras |
| **Supervisão** | Compliance / Risco | Monitoramento de exceções, incidentes e conformidade com a Res. CVM 32/2021 |

A **mesa de custódia executa** a liquidação; a **controladoria concilia** de forma independente. Nenhuma área acumula a execução e a conferência da mesma operação, preservando a integridade do processo (art. 13, III e V).

---

## 11. Referência normativa e revisão

Este manual foi elaborado com fundamento na **Resolução CVM nº 32, de 19 de maio de 2021** (consolidada com a **Resolução CVM nº 209/2024**), notadamente:

- **Art. 2º, §2º** — controle das posições dos ativos custodiados;
- **Art. 5º, II** — sistemas de registro, processamento e controle;
- **Art. 13, II** — garantia da integridade e certeza da origem das instruções;
- **Art. 13, III** — regular movimentação dos ativos, processada com controle eletrônico e documental;
- **Art. 13, V** — registro de acessos, erros, incidentes e interrupções.

O conceito de **DVP (entrega contra pagamento)** é tratado como **padrão operacional das infraestruturas de mercado** (SELIC, B3, STR), e não como dispositivo normativo específico.

**Documentos correlatos:** `manual_custodia.md`, `manual_conciliacao.md`, `dossie_estrutura_operacional_tecnologica.md`, `matriz_conformidade_cvm32.md`.

> ⚠️ **Nota de revisão:** este documento descreve a estrutura implementada na plataforma-piloto e o roadmap de homologação pós-deferimento. **Revisar com as áreas jurídica e de compliance** antes da submissão regulatória e a cada alteração normativa ou de infraestrutura de mercado.

---

*Documento de uso interno — pacote de comprovação de estrutura para pleito de autorização de custódia (Res. CVM 32/2021 c/c Res. CVM 209/2024).*
