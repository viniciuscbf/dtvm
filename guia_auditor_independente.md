# Auditor independente de fundos — guia interno aprofundado (barateamento)

> Documento interno do projeto Argus DTVM. Objetivo: entender **a fundo** a dinâmica do auditor
> independente de fundos e avaliar, com pragmatismo e honestidade, **quanto dá para baratear** sem
> criar risco jurídico para o auditor nem para a administradora. Baseado em pesquisa com fontes
> reais (CVM, CFC, IBRACON, NBC TA/CTA, casos julgados). As ressalvas de dado fraco estão marcadas.

---

## 0. Resumo executivo (a resposta direta)

- **A auditoria de fundo é obrigatória, anual e cara por convenção — não por natureza.** Para um
  fundo pequeno, simples e homogêneo (RF, poucos ativos, marcação automatizada), o trabalho real é
  uma fração do de um FIDC/FIP. **O barateamento é legítimo e tem mecanismo claro** (menos horas).
- **O mercado é concentrado, mas menos do que parecia:** as Big Four auditam **~85% dos fundos**
  (dado jul/2026; era ~95% em 2021 — a fatia não-Big-Four **cresceu** para ~15%). Há **319 firmas de
  auditoria ativas** na CVM (CNPJs distintos), das quais só **4 são Big Four** e **315 são
  nacionais/boutiques** — e **~80 já auditam fundos**. Esse é o pool real de candidatas, com
  nome/CNPJ/telefone no **Anexo A** e no arquivo `auditores_candidatos_argus.csv`.
- **Custo de mercado de um fundo simples:** referência pública de **~R$ 11 mil/ano** (é o número que
  aparece; não há tabela oficial). FIDC vai a **R$ 50–150 mil**; FIP até **2,5% do PL**.
- **O alvo "100 fundos × R$ 1.200 = R$ 120 mil/ano" é atraente para o perfil certo, mas a premissa
  de "1 pessoa, pouco esforço" é irreal e o preço de R$ 1.200 é agressivo** (ver §11). R$ 120 mil
  bruto interessa a um auditor de renda média (não a sócio de Big Four), mas 100 fundos é carga de
  uma firma pequena inteira, não de uma pessoa — e R$ 1.200/fundo (≈9× abaixo do mercado) flerta com
  **aviltamento de honorários** (infração ética autônoma) e não reduz a responsabilidade pessoal do
  auditor, que é integral por assinatura. **Recomendação pragmática: mirar R$ 3–5 mil/fundo** (ainda
  2–4× abaixo do mercado), com a Argus entregando volume + dados conciliados — isso é defensável.

---

## 1. O que o auditor faz especificamente (escopo)

Base: **Res. CVM 175, art. 69** ("as demonstrações contábeis do fundo e de suas classes devem ser
auditadas anualmente por auditor independente registrado na CVM") + **NBC CTA 32** (Comunicado
Técnico CFC/IBRACON, vigente desde 30/11/2021), que padroniza os procedimentos de auditoria de fundos.

O trabalho anual sobre **um** fundo inclui:
1. **Avaliação de risco e entendimento da entidade** — a partir da estrutura do administrador
   (indagações, entendimento do ambiente e dos controles).
2. **Avaliação de controles internos** — se foram desenhados e operaram no período.
3. **Procedimentos substantivos** — variam por tipo de fundo.
4. **Circularização** — extratos dos **custodiantes** e confronto com a carteira; confronto de cotas
   com custodiante de fundos investidos; **assessores jurídicos** (contingências); no FIP,
   comprovação da propriedade das participações.
5. **Existência e titularidade dos ativos** — testes documentais (extrato B3, matrícula em FII).
6. **Marcação a mercado / precificação** — recálculo com preços B3/ANBIMA; para ativos sem cotação
   (valor justo nível 3), entendimento dos modelos do administrador; FIP/imobiliário recomendam
   **especialista de avaliação**.
7. **Enquadramento e partes relacionadas** — comparação da carteira com os limites do regulamento e
   da norma; verificação de transações com partes relacionadas a preço de mercado.
8. **Produto final:** o **relatório do auditor independente** (parecer) sobre as demonstrações.

**PLD:** a NBC CTA 32 **não** atribui ao auditor das demonstrações um teste formal de PLD — a
prevenção à lavagem é dever primário do administrador/gestor (Res. CVM 50). O auditor só olha
controles internos relevantes para distorção relevante das demonstrações.

> Nota Argus: quase todo esse escopo (circularização de custódia, conciliação, marcação a mercado,
> enquadramento pré/pós-trade, partes relacionadas) **é exatamente o que o piloto já produz de forma
> automatizada e conciliada**. Isso é o que legitima o barateamento (§9).

---

## 2. Frequência e prazos

- **Frequência: ANUAL.** Não há revisão semestral obrigatória. Fundos com **menos de 90 dias** de
  atividade não precisam auditar.
- **Entrega das demonstrações auditadas + parecer, após o encerramento do exercício:**
  - **FIF (financeiro): 90 dias** · **FIDC: 90 dias** · **FIP: 150 dias** (Res. 175, Anexos I/II/IV).
- **Assembleia** delibera sobre as demonstrações em até **60 dias** após o envio à CVM.
- **Retificação de erro** descoberto: demonstração retificada aos cotistas em **15 dias úteis** do
  envio do relatório do auditor à CVM.

Implicação operacional: o pico de trabalho do auditor é concentrado (jan–mar para exercício em 31/12).
Uma administradora com muitos fundos precisa de auditor(es) com capacidade de absorver o lote nesse
período.

---

## 3. Vínculo, contratação, troca e rodízio

- **Quem contrata:** o **administrador fiduciário** (atribuição privativa — não é o gestor nem o
  cotista). Para o modelo Argus, **é a própria Argus/o banco parceiro que contrata e negocia o
  auditor** — posição de força para negociar preço em lote.
- **Formalização:** contrato de prestação de serviços de auditoria entre administrador (pelo fundo) e
  a firma/auditor **registrado na CVM**.
- **Troca:** livre a qualquer tempo, **mas** a substituição é **comunicada à CVM em 20 dias**, com
  justificativa e **anuência do auditor substituído**; se o administrador não comunicar, o auditor
  comunica em 10 dias; o auditor pode registrar discordância em 30 dias.
- **Exclusividade / limite de quantidade:** **não há.** Uma firma audita milhares de fundos ao mesmo
  tempo (é o modelo Big Four). O único limite é **temporal por cliente**.
- **Rodízio obrigatório (Res. CVM 23, art. 31):** **máximo de 5 exercícios sociais consecutivos** com
  o mesmo cliente, e **intervalo mínimo de 3 exercícios** para recontratar. A exceção de 10 anos
  (art. 31-A) exige Comitê de Auditoria Estatutário e **não** se aplica na prática a fundos pequenos.
  → **Consequência para a Argus: é preciso ter pelo menos 2 auditores/firmas em rodízio** para cobrir
  a carteira continuamente.

---

## 4. Registro na CVM e perfil do auditor

**Registro (Res. CVM 23/2021, que substituiu a Instrução 308/99):** duas categorias — **AIPF**
(pessoa física) e **AIPJ** (pessoa jurídica). Requisitos: registro no **CRC como contador**, **5 anos**
de experiência em auditoria, escritório legalizado, e **aprovação no Exame de Qualificação Técnica
(EQT)** aplicado por CFC+IBRACON (habilita o **CNAI**). Deveres contínuos: **educação continuada**,
**controle de qualidade interno** (art. 32) e **revisão externa por pares a cada 4 anos** (art. 33).

**Perfil e renda (para saber quem se interessaria):**
| Perfil | Renda bruta anual (estimativa) |
|---|---|
| Auditor CNAI **empregado** (fora Big Four / início) | ~R$ 36–64 mil |
| **Gerente** de auditoria | ~R$ 125–180 mil |
| **Sócio de firma pequena** de auditoria | ~R$ 120–360 mil (pró-labore + lucros) |
| **Sócio de Big Four** | ~R$ 255–780 mil+ |

- Há **>14.000 CNAI** no CFC; na CVM (registro exigido para auditar fundo) há **319 firmas ativas
  (CNPJs distintos) + ~29 PF** (jul/2026); só **4 são Big Four**, **315 são nacionais/boutiques**, e
  **~80 já auditam fundos** (Anexo A). O gargalo é menos estreito do que o registro sugere — e a lista
  de candidatas é conhecida.
- **Apetite por volume padronizado a preço baixo: existe, mas é segmentado.** Interessa ao auditor/
  firma pequena de renda média (R$ 5–15 mil/mês), cujo negócio escala com nº de clientes; **não**
  interessa a sócio de Big Four (alto custo de oportunidade). O EQT/CNAI cria escassez, então o
  auditor habilitado tem algum poder de barganha — o produto precisa **aumentar a produtividade
  dele** (padronização = mais fundos por hora), não competir só por preço.

---

## 5. Estrutura de mercado (concentração)

Estudo **ASA/CVM (mai/2021)** — participação por firma **nos fundos** registrados na CVM:

| Firma | Fundos | % |
|---|---|---|
| KPMG | 6.733 | 36% |
| PwC | 4.656 | 25% |
| EY | 3.237 | 17% |
| Deloitte | 3.146 | 17% |
| **Big Four** | **17.772** | **~95%** |
| Outras firmas | 905 | **~5%** |

- Em **fundos** a concentração é **maior** que em companhias abertas (onde Big Four ≈ 63%).
- Corroboração (Economatica 2017): 3 maiores concentravam **96,7%** dos fundos por PL.
- **Leitura estratégica:** o "andar de baixo" (fundos pequenos com firmas não-Big-Four) é **pequeno
  em market share** mas existe — e é exatamente onde a Argus consegue negociar. As firmas médias
  (BDO, Grant Thornton, Russell Bedford) e boutiques dividem esses ~5%.

---

## 6. Custo real e como as firmas precificam

**Precificação = horas estimadas × taxa-hora** (NBC PA 1 exige compatibilidade com relevância,
complexidade, horas, qualificação e risco). O **PL** entra como parâmetro de **materialidade**, não
como tabela de preço. **Nenhuma fonte pública traz tabela oficial de honorário de auditoria de fundo**
— o valor é negociado caso a caso; os regulamentos reais listam "honorários do auditor" como encargo
**sem cifra**.

Ordem de grandeza (com ressalva de que são exemplos, não médias):
| Tipo de fundo | Auditoria/ano | vs. fundo simples |
|---|---|---|
| **Fundo simples / RF (BR)** | **~R$ 10–11 mil** (ex. público) | 1× |
| Boutique/multimercado (EUA, proxy) | US$ 12–18 mil | ~2× |
| **FIDC (BR)** | **R$ 50–150 mil** | ~5–15× |
| **FIP (BR)** | até **2,5% do PL** (adm+contab+audit) | depende do PL |

Drivers do preço: **tipo de fundo** (o maior), nº de ativos/transações, ativos "nível 3"
(difícil avaliação), nº de classes/séries, e o **prêmio Big Four** (bem mais caro). **Fundo pequeno,
simples e com poucos ativos custa muito menos** — a diferença para um FIDC é de **uma ordem de
grandeza**.

---

## 7. Onde e como achar o auditor

Caminho concreto:
1. **InfoAudi (CVM)** — lista oficial de auditores registrados: `web.cvm.gov.br/app/infoaudi` (ou
   Central de Sistemas `sistemas.cvm.gov.br/consultas.asp`). Confirma registro **ativo** (não
   suspenso/cancelado). *Não* filtra "quem audita fundo".
2. **FNET/B3** (`fnet.bmfbovespa.com.br`) — baixar demonstrações de fundos parecidos; o relatório traz
   o **nome da firma** que já atua no segmento → lista de candidatos que já fazem fundo.
3. **CNAI/CFC** — `registro.cfc.org.br` (consulta CNAI/CNAI-PJ) para confirmar habilitação.
4. **IBRACON** (o de auditoria, `ibracon.com.br` — cuidado, o outro IBRACON é de concreto) — **GT
   Instituições Financeiras** e **Seções Regionais**: firmas médias/pequenas mais baratas que Big Four.
5. **CRC estadual** — validar regularidade do registro do contador.

Alvo: **firma pequena/média registrada na CVM** que já audita fundos e quer volume estável.

---

## 8. O que **legitimamente** barateia (as alavancas)

Ordenadas pela força da evidência para o caso Argus:
1. **Simplicidade e homogeneidade do fundo** (a mais forte). Fundo RF simples, poucos ativos, sem
   nível 3 → menos procedimentos substantivos → menos horas. Mecanismo direto e reconhecido.
2. **Dados já conciliados pela administradora.** Conciliação automatizada cria trilha auditável,
   reduz retrabalho e aumenta a confiança nos dados → menos esforço substantivo. **É o coração do
   piloto Argus** (motor de conciliação/contabilidade/custódia).
3. **Padronização de procedimentos e papéis de trabalho.** A **NBC CTA 32** padroniza a base dos
   procedimentos de fundos; papéis de anos consecutivos são reutilizáveis (atualizados). Reduz o
   tempo médio → reduz honorário. *(Ressalva: a CTA 32 diz que não elimina o julgamento por fundo.)*
4. **Automação / data analytics.** Testar a população inteira em vez de amostra; focar horas no risco
   alto. *(Ressalva honesta: os números fortes de redução — 30/50/80% — vêm de outros setores, não de
   auditoria de fundos; use como direção, não como prova.)*
5. **Escala/lote de fundos da mesma administradora.** Mesma administradora, mesmos sistemas, mesmos
   controles → custo marginal por fundo cai. *(Ressalva: não há fonte pública que quantifique a
   economia de escala do auditor em lote — é premissa a validar com cotação real.)*

**O que NÃO barateia legalmente:** cortar procedimentos, não fazer papéis de trabalho, "carimbar"
parecer. Isso não é economia — é infração (ver §10).

---

## 9. Como o piloto Argus reduz o custo do auditor (ponte com o produto)

O piloto já entrega, pronto e conciliado, boa parte do que o auditor teria que montar:
- **Carteira conciliada com a posição do custodiante** (motor de conciliação) → circularização mais
  rápida.
- **Marcação a mercado por indexador com fonte registrada** (ANBIMA/B3/Comitê) → recálculo de preço
  facilitado; poucos (ou nenhum) ativos nível 3 nos fundos simples-alvo.
- **Contabilidade em partidas dobradas + balancete (Ativo = Passivo + PL)** → demonstrações já
  estruturadas.
- **Enquadramento pré/pós-trade e trilha de partes relacionadas** → teste de limites e de partes
  relacionadas com evidência pronta.
- **Cadastro de contrapartes com KYC/limite** e **monitoramento antifraude** → ambiente de controle
  demonstrável.

→ Tese defensável: o auditor gasta **menos horas por fundo** porque a Argus entrega dados limpos e
homogêneos. O barateamento vem de **menos trabalho real**, não de trabalho omitido.

---

## 10. Implicações legais (as duas perguntas do plano)

### 10.1. Trabalho mal feito pelo auditor

**Sanções administrativas (Lei 6.385/76, art. 11):** advertência, **multa**, suspensão/cassação do
registro, **inabilitação** temporária (até 20 anos). Teto de multa (pós-MP 784/2017): o **maior**
entre R$ 500 milhões, 2× a operação, 3× a vantagem/prejuízo, ou **20% do faturamento**. A Res. CVM 23
ainda prevê suspensão automática por falhas de controle de qualidade/educação continuada.

**Casos reais (o que a CVM/Judiciário efetivamente fez):**
- **FIDC Union National (PAS RJ2013/9762, 2015):** KPMG multada em **R$ 1 milhão** e o sócio em
  R$ 200 mil por parecer sem ressalvas com **procedimentos insuficientes** (amostragem em vez de
  análise da provisão de perdas). *É o precedente-chave de auditor de fundo.*
- **Silverado FIDC (PAS 2024, ~R$ 497,5 mi):** puniu **gestora, administradores e custodiantes** —
  **o auditor não figurou entre os condenados**. Mostra que, mesmo na maior fraude recente de FIDC,
  o elo punido foi a administração/custódia por falha na verificação de lastro.
- **Audiva (PAS 2024):** **inabilitação de 51 meses** de firma por irregularidades na auditoria.
- **KPMG / Banco BVA (STJ, REsp 1.931.678, 2024):** **responsabilidade civil** — condenação a pagar
  **>R$ 10 milhões a um investidor terceiro** por parecer sem ressalvas negligente. Primeira condenação
  civil da Corte responsabilizando auditoria perante quem não era cliente do contrato.
- **Limite (contraponto favorável):** o STJ (4ª Turma) decidiu que **o auditor não responde por fraude
  praticada por funcionário da auditada** — nem toda fraude não detectada gera responsabilidade; é
  preciso demonstrar **falha técnica** do auditor. A responsabilidade é **subjetiva** (exige culpa).
- *(Cruzeiro do Sul e Panamericano são esfera CRSFN/BCB — banco, não fundo; citar como tal.)*

### 10.2. Auditoria "rápida e barata" — o risco de assinar muito por pouco

Aqui está o ponto sensível do plano. As normas técnicas criam um piso objetivo de esforço:
- **NBC TA 500** — a opinião precisa de evidência **suficiente** (quantidade) e **apropriada**
  (qualidade). Assinar sem executar procedimentos suficientes é, por definição, evidência insuficiente.
- **NBC TA 315/330/240** — exigem planejar e executar **avaliação de risco** e **respostas
  proporcionais**, com **ceticismo profissional** frente a fraude.
- **NBC TA 230 (papéis de trabalho)** — registro dos procedimentos, evidências e conclusões; guarda
  **mínima de 5 anos**; a **CVM pode requisitar** os papéis a qualquer tempo (Res. 23, art. 25). Regra
  de ouro: **"não documentado = não feito".** A fiscalização não precisa provar erro de julgamento —
  basta a ausência de evidência do trabalho.
- **Aviltamento de honorários (Código de Ética do Contador, Res. CFC 803/96):** precificar
  **substancialmente abaixo** do usual para trabalho de mesma dificuldade é **infração ética
  autônoma**. O honorário deve ser compatível com risco, esforço, tecnologia e controle de qualidade.
  Ou seja: **preço baixíssimo é, por si, sancionável** — não só sintoma.
- **Revisão externa pelos pares (a cada 4 anos)** e **PAS da CVM:** as infrações **mais comuns** de
  auditor na CVM são justamente "**ausência de planejamento e de procedimentos**" (~39%) e "falhas na
  revisão de qualidade" (~34%). Um auditor que assina volume sem esforço proporcional é o perfil
  estatisticamente mais punido.

**Conclusão honesta:** a lei não fixa "hora mínima" numérica, mas fixa um **resultado mínimo**
(evidência suficiente + papéis de trabalho) e **veda o preço aviltado**. Um auditor que assine 100
fundos por R$ 1.200 "sem esforço" acumula: evidência insuficiente + papéis pobres + aviltamento +
falha previsível na revisão por pares + exposição a multa/suspensão/inabilitação **e** ação civil
(cada assinatura é uma exposição de responsabilidade integral, como no BVA). **Isso é risco real, não
teórico.**

---

## 11. Análise do modelo-alvo (100 fundos × R$ 1.200 = R$ 120 mil/ano)

Ponto a ponto, sem moralismo mas com honestidade:

**a) R$ 120 mil/ano é atraente para o perfil certo?** Sim — para um auditor CNAI de renda média
(empregado sênior ~R$ 60–120 mil, ou sócio de firma pequena ~R$ 120–360 mil), R$ 120 mil incrementais
são relevantes. **Não** para sócio de Big Four. Confirma sua intuição: quem ganha ~600 mil não entra;
quem ganha ~120 mil, sim.

**b) "100 fundos por 1 pessoa, pouco esforço" é realista?** **Não.** Mesmo com fundo simples e dados
prontos, cada auditoria exige planejamento, avaliação de risco, testes, circularização, papéis de
trabalho, relatório **e** revisão de qualidade. A norma pressupõe **sócio responsável + equipe**. Se
um fundo simples levar, digamos, 15–25 horas (estimativa — **não há hora-por-fundo publicada**), 100
fundos = 1.500–2.500 horas/ano ≈ carga de **uma firma pequena inteira**, não de uma pessoa. R$ 120 mil
de receita para uma firma com equipe é magro.

**c) R$ 1.200/fundo é seguro?** **É agressivo.** ~9× abaixo do mercado (~R$ 11 mil) entra no território
de **aviltamento** e, se vier acompanhado de trabalho raso, de **negligência**. E o preço baixo **não
reduz** a responsabilidade por assinatura.

**d) Então o barateamento é viável?** **Sim, com calibragem.** O mecanismo (fundos simples + dados
conciliados pela Argus + padronização + lote da mesma administradora) **genuinamente reduz horas** e
justifica preço **bem abaixo** do mercado. O erro é fixar em R$ 1.200 e prometer "pouco esforço". A
faixa **defensável**: **R$ 3–5 mil/fundo** (ainda 2–4× mais barato que ~R$ 11 mil), com a economia
**documentada** (papéis de trabalho reutilizáveis + evidência de que os dados vieram conciliados).
Nesse patamar, 100 fundos = **R$ 300–500 mil/ano** — receita que sustenta uma firma pequena de 2–3
pessoas dedicada à conta Argus, sem aviltamento e com trabalho efetivamente feito.

**e) Alternativa que aproxima do R$ 1.200 legalmente:** só se a Argus **absorver ainda mais do
trabalho** (papéis de trabalho pré-montados, evidência pré-organizada, população 100% testada
automaticamente e entregue ao auditor) — reduzindo as horas do auditor ao mínimo de revisão e
opinião. Ainda assim, documente a justificativa do preço para blindar contra aviltamento, e mantenha
o auditor **genuinamente** revisando (não carimbando).

---

## 12. Estratégia recomendada para a Argus

1. **Posicionar a Argus como fornecedora de eficiência ao auditor**, não como compradora de preço
   baixo: "entregamos 100 fundos homogêneos com dados conciliados e papéis pré-organizados; seu custo
   marginal por fundo é mínimo". Isso é o que legitima o desconto.
2. **Negociar em lote com 2 firmas pequenas registradas na CVM** (para o rodízio de 5 anos), achadas
   via FNET + InfoAudi + GT-IF do IBRACON.
3. **Preço-alvo R$ 3–5 mil/fundo** (revisar com cotação real), não R$ 1.200 — múltiplas vezes abaixo
   do mercado, mas defensável.
4. **Documentar a origem da economia** (dados conciliados, padronização) nos papéis, para responder a
   fiscalização/revisão por pares.
5. **Nunca** vender "auditoria carimbo": a responsabilidade é do auditor **e** respinga na
   administradora (Silverado puniu a administração). O barato mal feito é o mais caro.
6. **Validar as lacunas** (§13) com uma cotação/entrevista de 2–3 firmas antes de cravar número no
   pitch.

---

## 13. Lacunas honestas (o que ainda falta confirmar)

- **Honorário médio por porte de fundo** — só há **um** exemplo público (~R$ 11 mil). Cotar mercado.
- **Horas para auditar um fundo simples** e **economia de escala em lote** — **sem fonte pública**;
  premissa central do modelo, precisa de dado primário (cotação/entrevista).
- **% de redução de custo** por padronização/dados conciliados **em fundos** — evidência direcional;
  os %s fortes vêm de outros setores.
- **"100 fundos/ano por 1 pessoa"** — **não sustentado**; assumir firma com equipe.
- **Repartição PF/PJ** do registro CVM — não confirmada em fonte pública (consultar InfoAudi).

---

## 14. Fontes principais

**Norma e escopo:** Res. CVM 175 art. 69 (auditoria anual) · NBC CTA 32 (procedimentos de fundos) ·
Res. CVM 23/2021 (registro, rodízio art. 31, guarda art. 25) · NBC TA 500/315/330/240/230 (evidência,
risco, papéis) · Código de Ética CFC 803/96 (aviltamento).
**Mercado e custo:** Estudo ASA/CVM "Auditores Independentes" (mai/2021) · Economatica (2017) · XP
"Taxas e custos em fundos" (~R$ 11 mil) · SciELO RC&F (determinantes de honorário).
**Casos:** PAS RJ2013/9762 (Union National/KPMG) · PAS 2024 Silverado · PAS 2024 Audiva · STJ REsp
1.931.678 (BVA/KPMG) · STJ 4ª Turma (auditor x fraude de funcionário).
**Onde achar:** InfoAudi (CVM) · CNAI (CFC) · IBRACON GT Instituições Financeiras · FNET/B3.

*(URLs completas nos relatórios de pesquisa arquivados nesta linha de trabalho.)*

---

# Anexo A — Radiografia real do mercado (dados abertos CVM, jul/2026) e lista de candidatas

Esta parte corrige e aprofunda os §§4–5 com **dados oficiais baixados da CVM**, e entrega a **lista
concreta de firmas** para prospectar — do mesmo jeito que fizemos com bancos/DTVMs.

## A.1. A fonte de dados (a "API/cadastro" que existe)

A CVM publica **dados abertos** com tudo o que precisamos, atualizados até o último dia útil:

1. **Cadastro dos auditores** — dataset "Auditores: Informação Cadastral"
   (`dados.cvm.gov.br/dataset/auditor-cad`). Arquivo `dados/AUDITOR/CAD/DADOS/cad_auditor.zip`, que
   contém:
   - `cad_auditor_pj.csv` — **firmas**: `CD_CVM; CNPJ; DENOM_SOCIAL; SIT; DT_INI_SIT; endereço;
     MUN; UF; CEP`.
   - `cad_auditor_pf.csv` — auditores **pessoa física**.
2. **Vínculo auditor ↔ fundo** — cadastro de fundos `dados/FI/CAD/DADOS/cad_fi.csv` traz, por fundo,
   as colunas **`AUDITOR` e `CNPJ_AUDITOR`**. Dá para **contar quantos fundos cada firma audita** →
   ranquear por experiência real.
3. **Contato (telefone/e-mail)** — CNPJ → **BrasilAPI** (`brasilapi.com.br/api/cnpj/v1/{cnpj}`), o
   mesmo enriquecimento usado no pipeline de bancos.
4. **Consulta pontual oficial** — **InfoAudi** (`web.cvm.gov.br/app/infoaudi`) para confirmar
   situação (ativo/suspenso) na hora de contratar.

> É **reprodutível**: um script baixa os 3 CSV, cruza por CNPJ e re-ranqueia — pipeline igual ao de
> prospecção de parceiros. O resultado desta rodada está em **`auditores_candidatos_argus.csv`**.

## A.2. O tamanho real do mercado (corrige o "~350")

Números de **jul/2026** (extraídos dos CSV acima):
- **319 firmas de auditoria (CNPJs distintos) ATIVAS** na CVM (486 registros contando filiais;
  +5 suspensas) e ~29 pessoas físicas.
- Dessas, só **4 são Big Four**; as outras **315 são nacionais** — algumas dezenas são braços de
  redes internacionais (BDO, Grant Thornton, RSM, UHY, Baker Tilly, Mazars, MGI) e o restante são
  **boutiques puras**.
- Auditar fundo é um subconjunto: dos **46.810** fundos do cadastro, **19.121 têm auditor informado**,
  e apenas **85 firmas** de fato auditam fundos.
- **Concentração real:** Big Four = **85,4%** dos fundos auditados (KPMG 4.988 · PwC 4.902 · EY 3.776
  · Deloitte 2.637 = 16.323). O restante — **14,6% (2.798 fundos)** — está em **~80 firmas
  não-Big-Four**.

**Correção honesta ao meu número anterior:** não são "350 caros e escassos". Há **344 boutiques
registradas** e **~80 já com experiência em fundos**. E a fatia não-Big-Four **subiu de ~5% (2021)
para ~15% (2026)** — o "andar de baixo" está crescendo, o que é bom para a tese de barateamento.

## A.3. As candidatas (não-Big-Four que **já auditam fundos**)

Ranking por nº de fundos auditados (proxy de experiência). As **boutiques** são o alvo preferencial
(qualificadas, com histórico de fundo, sem prêmio Big Four, e que ganham escala com volume):

| # | Firma | Tier | Fundos | Cidade/UF | Telefone |
|---|---|---|---:|---|---|
| 1 | **Next Auditores** | boutique | 650 | Blumenau/SC | 47 3288-1979 |
| 2 | BDO RCS | rede intl | 490 | São Paulo/SP | 11 3848-5880 |
| 3 | Grant Thornton | rede intl | 414 | Rio/RJ | 11 3886-5100 |
| 4 | RSM Brasil | rede intl | 257 | Rio/RJ | 11 2154-7795 |
| 5 | UHY Bendoraytes | rede intl | 229 | São Paulo/SP | 21 3030-4662 |
| 6 | **Confiance** | boutique | 114 | São Paulo/SP | 11 5096-5196 |
| 7 | **YPC Auditun** | boutique | 66 | São Paulo/SP | 11 3816-9888 |
| 8 | BWEL | boutique | 63 | — | — |
| 9 | MGI Assurance | rede intl | 49 | Curitiba/PR | 41 3044-6127 |
| 10 | Baker Tilly 4Partners | rede intl | 49 | São Paulo/SP | 11 5102-2510 |
| 11 | **Audipec** | boutique | 26 | Rio/RJ | 21 2252-2160 |
| 12 | **Audifactor** | boutique | 25 | Joinville/PR | 47 3035-3231 |
| 13 | **Conatus** | boutique | 18 | São Paulo/SP | 11 3627-3318 |
| 14 | **Sênior** | boutique | 17 | Maringá/PR | 44 3026-1441 |
| 15 | **Nova Master** | boutique | 17 | São Paulo/SP | 11 5089-5900 |
| 16 | Forvis Mazars | rede intl | 17 | Rio/RJ | 11 3524-4500 |
| 17 | **Ramires & Cia** | boutique | 12 | Porto Alegre/RS | 51 9661-1136 |
| 18 | **MCS Markup** | boutique | 9 | Rio/RJ | 21 2533-1122 |
| 19 | **MBAudit** | boutique | 8 | Porto Alegre/RS | 51 3337-6664 |
| 20 | **Idea Auditores** | boutique | 8 | Goiânia/GO | 62 9550-0064 |

Lista completa das **80 firmas** (com CNPJ, cidade/UF e telefone) em **`auditores_candidatos_argus.csv`**.

**Sweet spot para o Argus** (boutiques com volume de fundo, custo baixo, apetite por escala): **Next**
(líder fora Big Four, forte em FIDC), **Confiance**, **YPC**, **BWEL**, **Audipec**, **Audifactor**,
**Conatus**, **Sênior**, **Nova Master**, **Ramires**. As redes (BDO, Grant Thornton, RSM, UHY, Baker
Tilly, Mazars, MGI) são qualificadas mas de preço médio-alto — segunda opção.

## A.4. Como abordar (o "vá atrás" na prática)

1. **Priorizar boutiques da região dos fundos** — a oferta se concentra em **SP (125), PR (41), RS
   (35), RJ (33), SC (25)**. Firma local + com histórico de fundo = menos atrito.
2. **Pitch de lote:** "administrador com carteira de N fundos homogêneos, dados já conciliados e papéis
   padronizados; busco parceria de auditoria em lote, com o rodízio de 5 anos coberto por 2 firmas".
3. **Cotar 3–4 firmas** da lista, pedindo **preço por fundo** (não por hora). Mirar **R$ 3–5k/fundo**
   (§11) — várias vezes abaixo do mercado, mas defensável.
4. **Confirmar no InfoAudi** que o registro está **ativo** (não suspenso) e checar o **rodízio** (a
   firma não pode ter passado 5 anos consecutivos no mesmo fundo).
5. Preferir quem participa do **GT Instituições Financeiras do IBRACON** (sinal de especialização).

## A.5. Ressalvas honestas sobre estes dados

- O nº de fundos por firma vem do campo **`AUDITOR` autodeclarado** no cad_fi (só **~41% dos fundos**
  têm o campo preenchido) — é **proxy forte de experiência**, não censo perfeito. Firmas com poucos
  fundos podem ter mais (campo em branco).
- **Registrada ≠ topa preço baixo.** A lista entrega os *leads* qualificados; o preço é negociação
  (a análise de viabilidade do §11 continua valendo — R$ 1.200 é agressivo; R$ 3–5k é o alvo).
- **Telefones** vêm da Receita via BrasilAPI e podem estar desatualizados — validar antes de abordar.
- Dados de jul/2026; re-rodar o pipeline periodicamente mantém a lista viva.

## A.6. Fontes de dados (Anexo)
- CVM Dados Abertos — Auditores: `dados.cvm.gov.br/dataset/auditor-cad` ·
  `dados/AUDITOR/CAD/DADOS/cad_auditor.zip`
- CVM Dados Abertos — Fundos (campo AUDITOR): `dados/FI/CAD/DADOS/cad_fi.csv`
- InfoAudi (consulta oficial): `web.cvm.gov.br/app/infoaudi`
- BrasilAPI CNPJ (contatos): `brasilapi.com.br/api/cnpj/v1/{cnpj}`
- Arquivo gerado: `auditores_candidatos_argus.csv` (80 firmas não-Big-Four que auditam fundos)
