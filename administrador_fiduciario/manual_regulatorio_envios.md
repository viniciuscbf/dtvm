# Manual Regulatório de Envios, Auditoria, Ofícios e Assembleias

**Área:** Administração Fiduciária
**Base legal:** Resolução CVM nº 175 — arts. 61, 67 a 71 (Parte Geral) — e Resolução CVM nº 21 — arts. 18 e 25
**Referência cruzada:** `matriz_conformidade_cvm21_175.md`, `dossie_estrutura_operacional_tecnologica.md`, `manual_controladoria_precificacao.md`
**Plataforma-piloto:** `..\piloto\` — Portal `/admin/` — Módulo `/admin/regulatorio.php`
**Versão:** [versão] — **Data-base:** [data] — **Responsável:** [administrador fiduciário]

---

## 1. Cabeçalho e base legal

Este manual consolida as obrigações regulatórias de envio periódico e eventual de informações, de auditoria independente das demonstrações contábeis, de tratamento de ofícios do regulador e de condução de assembleias de cotistas, atribuídas ao **administrador fiduciário** no regime da Resolução CVM nº 175 (Parte Geral e Anexos Normativos por categoria) e da Resolução CVM nº 21 (regras de compliance e comunicação de violações).

A fundamentação normativa direta deste documento repousa nos seguintes dispositivos **confirmados**:

| Norma | Dispositivo | Objeto |
|-------|-------------|--------|
| Res. CVM 175 | art. 31 | Comunicação da primeira integralização por sistema — 5 dias úteis |
| Res. CVM 175 | arts. 61–62 | Divulgação de informações periódicas e eventuais; exigência por sistema eletrônico pela Superintendência |
| Res. CVM 175 | art. 67 | Exercício social a cada 12 meses |
| Res. CVM 175 | art. 69 | Demonstrações contábeis auditadas anualmente por auditor independente registrado na CVM |
| Res. CVM 175 | art. 71 | Assembleia sobre as demonstrações em até 60 dias do encaminhamento à CVM |
| Res. CVM 175 | arts. 70–79 | Regime das assembleias de cotistas |
| Res. CVM 21 | art. 18 | Comunicação de violações à CVM em 10 dias úteis |
| Res. CVM 21 | art. 25 | Relatório anual de compliance até o último dia útil de abril |

> **Nota metodológica de honestidade normativa.** Os **prazos e leiautes** do **informe diário, do balancete, da composição e diversificação de carteira (CDA) e do perfil mensal** **não** constam da Parte Geral da Res. CVM 175, mas dos **Anexos Normativos por categoria** (a título ilustrativo, a referência cruzada do art. 139 remete o CDA ao Anexo Normativo I). Por essa razão, este manual **não atribui número de artigo** a esses envios diários/mensais e refere-se genericamente ao **"Anexo Normativo aplicável à categoria"**, tratando os respectivos prazos como **[a confirmar no anexo]**.

---

## 2. Objetivo e panorama das obrigações do administrador

### 2.1 Objetivo

Estabelecer o fluxo operacional, os controles e as evidências mínimas para que o administrador fiduciário cumpra, de forma tempestiva e rastreável, suas obrigações de:

- **envio periódico** de informações (informe diário, balancete, CDA, perfil mensal, demonstrações);
- **envio eventual** de fatos relevantes e informações exigidas por sistema eletrônico;
- **auditoria independente anual** das demonstrações contábeis;
- **tratamento de ofícios** do regulador com resposta protocolada;
- **convocação e condução de assembleias** de cotistas;
- **comunicação de violações** à CVM e **relatório anual de compliance**.

### 2.2 Panorama das obrigações

| Eixo | Natureza | Sistema/Portal | Módulo do piloto |
|------|----------|----------------|------------------|
| Primeira integralização | Eventual (evento) | Sistema CVM | `envios_regulatorios` |
| Informe diário | Periódica (diária) | CVMWeb / Fundos.NET | `envios_regulatorios` |
| Balancete / CDA / Perfil mensal | Periódica (mensal) | CVMWeb / Fundos.NET | `envios_regulatorios` |
| Demonstrações auditadas | Periódica (anual) | CVMWeb / Fundos.NET | `envios_regulatorios` |
| Estatísticas ANBIMA | Periódica | HUB ANBIMA | `envios_regulatorios` |
| Ofícios do regulador | Eventual (demanda) | Sistema CVM | `oficios_cvm` |
| Assembleias | Eventual/periódica | — | `assembleias` |
| Violações / Compliance | Eventual/anual | Sistema CVM | [a mapear] |

> **Honestidade 🟩.** No piloto, as **integrações reais** com **CVMWeb/Fundos.NET** (CVM) e **HUB ANBIMA** são **simuladas**. Os status, protocolos e trilhas registrados no módulo `/admin/regulatorio.php` reproduzem o comportamento esperado, mas **não** efetuam transmissão real aos sistemas do regulador ou da associação.

---

## 3. Calendário de envios regulatórios

O calendário abaixo distingue **envios com prazo confirmado na Parte Geral** (com artigo indicado) dos **envios cujo prazo depende do Anexo Normativo por categoria** (marcados como **[a confirmar no anexo]**, sem atribuição de artigo, em observância à nota metodológica da Seção 1).

| Envio | Periodicidade | Prazo | Fundamento | Destino | Status no piloto |
|-------|---------------|-------|------------|---------|------------------|
| Comunicação da **primeira integralização** | Por evento | **5 dias úteis** da primeira integralização | Res. 175, **art. 31** | CVM | Enviado / Pendente / Erro |
| **Informe diário** | Diária | **[a confirmar no anexo]** | **Anexo Normativo aplicável à categoria** (sem artigo na Parte Geral) | CVM (CVMWeb/Fundos.NET) | Enviado / Pendente / Erro / **Aguardando cota** |
| **Balancete** | Mensal | **[a confirmar no anexo]** | **Anexo Normativo aplicável à categoria** | CVM | Enviado / Pendente / Erro |
| **CDA** (composição e diversificação de carteira) | Mensal | **[a confirmar no anexo]** | **Anexo Normativo aplicável à categoria** (referência cruzada do art. 139) | CVM | Enviado / Pendente / Erro |
| **Perfil mensal** | Mensal | **[a confirmar no anexo]** | **Anexo Normativo aplicável à categoria** | CVM | Enviado / Pendente / Erro |
| **Demonstrações contábeis** anuais + **auditoria** | Anual | Após encerramento do exercício social (12 meses) | Res. 175, **arts. 67 e 69** | CVM | Enviado / Pendente / Erro |
| **Estatísticas ANBIMA** | Periódica | **[a confirmar]** (calendário ANBIMA) | Autorregulação ANBIMA | ANBIMA (HUB) | Enviado / Pendente / Erro |
| Informações **eventuais** (fatos relevantes) | Por evento | Imediato / conforme exigência | Res. 175, **arts. 61–62** | CVM | Enviado / Pendente / Erro |

> **Ressalva reiterada.** Para **informe diário, balancete, CDA e perfil mensal**, os prazos e leiautes efetivos **devem ser lidos no Anexo Normativo da categoria específica** do fundo/classe. Este manual **não** substitui a consulta ao anexo e **não** fixa número de artigo para esses envios.

### 3.1 Divulgação de informações periódicas e eventuais (arts. 61–62)

Nos termos dos **arts. 61 e 62** da Res. 175, o administrador **divulga informações periódicas e eventuais** sobre o fundo e suas classes. A **Superintendência** competente pode **exigir** que determinadas informações sejam prestadas **por sistema eletrônico**. O módulo `envios_regulatorios` reflete essa exigência ao consolidar todos os tipos de envio em fila única, com status e protocolo.

---

## 4. Regra do informe diário bloqueado até a cota aprovada

O piloto implementa controle de **precedência de cota** sobre o informe diário: **o informe diário permanece bloqueado até que a cota do dia seja aprovada** pela controladoria/precificação. Enquanto a cota não é aprovada, o registro correspondente em `envios_regulatorios` assume o status **"Aguardando cota"**, impedindo a transmissão.

### 4.1 Fluxo de liberação

1. A controladoria calcula e **aprova a cota** do dia (ver `manual_controladoria_precificacao.md`).
2. O status do informe diário transita de **"Aguardando cota"** para **"Pendente"** (apto a envio).
3. Após a transmissão simulada, o status transita para **"Enviado"** (com **protocolo**) ou **"Erro"**.

| Estado da cota | Status do informe diário | Ação permitida |
|----------------|--------------------------|----------------|
| Não aprovada | **Aguardando cota** | Envio bloqueado |
| Aprovada | Pendente | Envio liberado |
| — | Enviado | Consulta de protocolo |
| — | Erro | Reprocessamento |

> **Racional de controle.** O bloqueio previne a divulgação de informação diária inconsistente com a cota oficial, alinhando o envio regulatório ao ciclo de precificação. Trata-se de controle **preventivo** com evidência automática no próprio status.

---

## 5. Auditoria independente anual e demonstrações contábeis

### 5.1 Exercício social (art. 67)

O **exercício social** do fundo/classe compreende o período de **12 meses**, conforme o **art. 67** da Res. 175, encerrando-se na data definida em regulamento **[data de encerramento]**.

### 5.2 Auditoria independente (art. 69)

As **demonstrações contábeis** do fundo e de suas classes devem ser **auditadas anualmente** por **auditor independente registrado na CVM**, nos termos do **art. 69**.

- **Dispensa:** o **art. 69** admite **dispensa de auditoria** para fundo ou classe com **menos de 90 dias** [confirmar condições no dispositivo e no anexo aplicável].
- **Auditor:** [nome do auditor independente] — **registro CVM:** [nº de registro].
- **Escopo:** demonstrações do exercício social encerrado, incluindo os elementos exigidos pelo Anexo Normativo da categoria.

### 5.3 Demonstrações e encaminhamento (arts. 66–67 e 71)

Encerrado o exercício social (art. 67) e concluída a auditoria (art. 69), o administrador **elabora e encaminha** as demonstrações contábeis à CVM pelo sistema eletrônico aplicável. Na sequência, observa-se o prazo do **art. 71** para a assembleia.

| Etapa | Prazo/marco | Fundamento |
|-------|-------------|------------|
| Encerramento do exercício social | 12 meses | art. 67 |
| Elaboração das demonstrações | Após encerramento | arts. 66–67 |
| Auditoria independente | Anual, antes do encaminhamento | art. 69 |
| Encaminhamento à CVM | Após auditoria | arts. 61–62 (sistema eletrônico) |
| **Assembleia sobre as demonstrações** | **Até 60 dias** do encaminhamento à CVM | **art. 71** |

---

## 6. Ofícios do regulador

O módulo `oficios_cvm` do piloto registra os ofícios recebidos da CVM, seu **prazo de resposta** e a **resposta protocolada**, assegurando **trilha de auditoria** completa.

### 6.1 Ciclo de tratamento de ofícios

1. **Registro** do ofício recebido: número, data, assunto, **prazo de resposta**.
2. **Distribuição** interna ao responsável e às áreas envolvidas.
3. **Elaboração** da resposta com evidências.
4. **Protocolo** da resposta no sistema do regulador (simulado no piloto) e registro do **número de protocolo**.
5. **Encerramento** e arquivamento com trilha.

| Campo (`oficios_cvm`) | Conteúdo |
|-----------------------|----------|
| Número do ofício | [nº] |
| Data de recebimento | [data] |
| Assunto | [assunto] |
| Prazo de resposta | [prazo] |
| Resposta protocolada | Sim/Não — [nº protocolo] |
| Situação | Em análise / Respondido / Encerrado |

> **Trilha.** Toda transição de situação é registrada com data/hora e responsável, compondo a evidência de tempestividade exigível pelo regulador.

---

## 7. Assembleias de cotistas (arts. 70–79)

O regime de assembleias da Res. 175 é operacionalizado pelo módulo `assembleias` (pauta, convocação, modo **Eletrônica**, resultado). Aplicam-se os dispositivos **confirmados** a seguir.

### 7.1 Competência privativa (art. 70)

Compete **privativamente** à assembleia deliberar, entre outras matérias, sobre:

- as **demonstrações contábeis** apresentadas pelo administrador;
- a **substituição de prestador de serviço essencial**;
- a **alteração do regulamento**;
- **fusão, cisão, incorporação, transformação ou liquidação** do fundo/classe;
- demais matérias atribuídas pelo **art. 70**.

### 7.2 Convocação (art. 72)

A convocação é dirigida a **cada cotista** com **antecedência mínima de 10 dias** da data de realização, conforme o **art. 72**.

### 7.3 Legitimados a convocar (art. 73)

São legitimados a convocar a assembleia, nos termos do **art. 73**:

- os **prestadores de serviços essenciais**;
- o **custodiante**;
- **cotistas** que representem, no mínimo, **5%** das cotas.

### 7.4 Instalação e deliberação (arts. 74 e 76)

- **Instalação (art. 74):** a assembleia **instala-se com qualquer número** de cotistas presentes.
- **Deliberação (art. 76):** as deliberações são tomadas por **maioria dos presentes**, ressalvados os quóruns qualificados aplicáveis.

### 7.5 Assembleia exclusivamente eletrônica (art. 75)

O **art. 75** admite assembleia **exclusivamente eletrônica**. Nessa modalidade, o **administrador garante a autenticidade e a segurança dos votos**. O módulo `assembleias` registra o modo **Eletrônica** e associa a pauta, a convocação e o resultado à sessão.

### 7.6 Resumo das deliberações (art. 79)

O **resumo das deliberações** deve ser disponibilizado aos cotistas em até **30 dias** da realização da assembleia, conforme o **art. 79**.

### 7.7 Quadro-síntese das assembleias

| Aspecto | Regra | Fundamento |
|---------|-------|------------|
| Competência privativa | Demonstrações; substituição de prestador essencial; alteração de regulamento; fusão/cisão/liquidação | art. 70 |
| Assembleia sobre demonstrações | Até 60 dias do encaminhamento à CVM | art. 71 |
| Convocação | A cada cotista, **10 dias** de antecedência mínima | art. 72 |
| Legitimados | Prestadores essenciais, custodiante, cotistas ≥ 5% | art. 73 |
| Instalação | Qualquer número | art. 74 |
| Modalidade eletrônica | Admitida; administrador garante autenticidade/segurança dos votos | art. 75 |
| Deliberação | Maioria dos presentes | art. 76 |
| Resumo | Até **30 dias** | art. 79 |

---

## 8. Comunicação de violações à CVM e relatório anual de compliance (Res. 21)

### 8.1 Comunicação de violações (art. 18)

Nos termos do **art. 18 da Res. CVM 21**, o administrador **informa à CVM** as violações identificadas em **10 dias úteis** do respectivo conhecimento. A comunicação é registrada com evidência de tempestividade e protocolo.

| Elemento | Conteúdo |
|----------|----------|
| Data de conhecimento | [data] |
| Prazo-limite (10 d.u.) | [data + 10 d.u.] |
| Objeto da violação | [descrição] |
| Comunicação à CVM | [nº protocolo] |

### 8.2 Relatório anual de compliance (art. 25)

O **art. 25 da Res. CVM 21** exige a elaboração de **relatório anual de compliance**, a ser concluído até o **último dia útil de abril**, cobrindo o exercício anterior.

| Elemento | Conteúdo |
|----------|----------|
| Exercício de referência | [ano] |
| Prazo-limite | Último dia útil de abril |
| Responsável | [diretor de compliance] |
| Situação | [em elaboração / concluído] |

> Ver `matriz_conformidade_cvm21_175.md` para o cruzamento integral dos deveres da Res. 21 com os controles do piloto.

---

## 9. Integrações e protocolos

O envio efetivo depende de integração com os sistemas do regulador e da autorregulação. No ambiente de produção, os canais são:

| Sistema | Operador | Objeto |
|---------|----------|--------|
| **CVMWeb / Fundos.NET** | CVM | Informe diário, balancete, CDA, perfil mensal, demonstrações |
| **HUB ANBIMA** | ANBIMA | Estatísticas e informações de autorregulação |
| **Sistema CVM** (ofícios) | CVM | Recebimento e resposta protocolada de ofícios |

Cada envio bem-sucedido retorna um **protocolo**, persistido no piloto para fins de trilha e prova de tempestividade.

> **Honestidade 🟩.** As integrações reais com **CVMWeb/Fundos.NET** e **HUB ANBIMA** são **simuladas** no piloto. Os protocolos exibidos são **fictícios** e servem apenas para validar o fluxo operacional e a trilha; **não** representam transmissão efetiva. A ativação das integrações reais é pré-requisito para uso em produção — ver `dossie_estrutura_operacional_tecnologica.md`.

---

## 10. Rodapé

**Documento:** Manual Regulatório de Envios, Auditoria, Ofícios e Assembleias — Administração Fiduciária
**Base legal:** Res. CVM 175 (arts. 31, 61–62, 67, 69, 70–79) e Res. CVM 21 (arts. 18, 25); prazos de informe diário/balancete/CDA/perfil mensal remetidos ao **Anexo Normativo aplicável à categoria** — **[a confirmar no anexo]**
**Plataforma-piloto:** `..\piloto\` — `/admin/regulatorio.php` (`envios_regulatorios`, `oficios_cvm`, `assembleias`)
**Referências cruzadas:** `matriz_conformidade_cvm21_175.md`, `dossie_estrutura_operacional_tecnologica.md`, `manual_controladoria_precificacao.md`
**Ressalva de honestidade:** integrações CVMWeb/Fundos.NET e HUB ANBIMA **simuladas** no piloto (🟩)
**Controle de versão:** [versão] — **Aprovado por:** [responsável] — **Data:** [data] — **Revisão:** [data da próxima revisão]

*Os campos entre colchetes `[ ]` devem ser preenchidos conforme o fundo/classe e a categoria específicos. As referências a prazos de envios diários/mensais marcadas como [a confirmar no anexo] exigem consulta ao Anexo Normativo da categoria antes de qualquer uso operacional.*
