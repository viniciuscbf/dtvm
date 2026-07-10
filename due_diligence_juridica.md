# Due Diligence Jurídica — a estrutura da parceria é lícita?

> **Documento interno (2026-07-09).** Diligência estilo advogado sobre o **núcleo jurídico** do modelo: uma startup de tecnologia (não registrada) opera a controladoria/backoffice da administração fiduciária e recebe ~75% da taxa, enquanto um **banco registrado** é o administrador fiduciário formal. Baseado no **texto primário** das normas da CVM (Res. 21/2021, 175/2022, 178/2023) e da Lei 6.385/76, com precedentes sancionadores.
>
> **Não é parecer jurídico formal.** É uma diligência de qualidade para você (a) saber se há falha e (b) chegar preparado. Antes de assinar contrato, um advogado de mercado de capitais precisa **materializar** estas conclusões no instrumento. Pontos de incerteza (número exato de alguns artigos) estão sinalizados.

---

## Veredito executivo

**A estrutura NÃO tem falha fatal de conceito — ela é viável.** É uma **terceirização (BPO) de controladoria**, que as normas **expressamente contemplam**, inclusive o repasse da maior parte da taxa ao prestador. **Mas** ela está descrita **perto da linha** do "aluguel de licença" (exercício irregular por interposta pessoa — art. 23 da Lei 6.385), e **três coisas precisam ser corrigidas** no desenho e na forma de comunicar. Nenhuma é impeditiva; todas são de engenharia jurídica + de narrativa.

> **A frase que resume tudo: a CVM julga a SUBSTÂNCIA, não o rótulo do contrato.** Se o banco é administrador de verdade (controla, decide, fiscaliza, tem estrutura própria) e a startup é prestadora de serviço de verdade, é **lícito**. Se o banco é fachada e a startup exerce e monetiza a atividade regulada, é **exercício irregular** — e a CVM pune **os dois lados**.

O ethos do projeto ("não é emprestar o CNPJ e operar por baixo — a norma não permite") **está correto**. Esta diligência confirma exatamente **onde** está essa linha, com os artigos e os precedentes.

---

## 1. O que a lei PERMITE (as boas notícias, com artigo)

| # | O que é permitido | Base |
|---|---|---|
| 1 | **Terceirizar a controladoria/backoffice** (o administrador contrata terceiros para controle/processamento de ativos, escrituração, auditoria) | Res. 21, **art. 29** ("serviços auxiliares"); Res. 175, **art. 83** |
| 2 | O banco (IF autorizada pelo BCB) **pode fazer a controladoria internamente** | Res. 175, **art. 83, §1º** |
| 3 | O banco é **elegível** a administrador fiduciário; **sem capital mínimo** próprio da CVM (só o prudencial do BCB) | Res. 21, **art. 1º, §2º, I**; art. 4º, VII (exige "recursos humanos e computacionais adequados", não capital) |
| 4 | **Repassar ~75% da taxa ao BPO é EXPRESSAMENTE permitido — sem teto percentual** (só não pode exceder 100% da taxa; o excesso corre por conta do banco) | Res. 175, **art. 118, §1º** e **art. 100**; a taxa, por definição (art. 3º, XXXIII), "remunera o administrador **e os prestadores por ele contratados**" |
| 5 | **Responsabilidade proporcional, sem solidariedade automática** (solidariedade só em dolo/má-fé) | Res. 175, **art. 81** + Código Civil, **art. 1.368-D, II** |
| 6 | Processo de habilitação claro e rápido (SIN, **60 dias**, via convênio ANBIMA) | Res. 21, **arts. 6º, 7º, 8º** |

> **Ponto que desarma o maior medo:** o repasse de 75% da taxa **não é, por si, ilegal** — o art. 118, §1º o autoriza. O que era vendido como "indício de fachada" só vira problema **em combinação** com o banco não exercer a atividade de fato. Corrigida a substância, a economia 75/25 é lícita (com transparência — ver §3).

---

## 2. As TRÊS falhas de desenho (o que precisa mudar)

### Falha 1 — o banco não pode ser "fachada" *(a mais séria)*
A atividade de administração de carteira **depende de autorização** (Lei 6.385, **art. 23**) e a responsabilidade é **pessoal e indelegável** do diretor estatutário responsável (Res. 21, **art. 4º, III**). Se o diretor apenas **homologa** cálculos feitos pela startup, sem controle real, a atribuição legal está descumprida. A CVM olha a **realidade econômica**: se quem calcula, enquadra, decide e capta é a startup, e o banco empresta o registro, configura-se **exercício irregular por interposta pessoa**.
- **Precedente confirmado:** PAS CVM **RJ2014/8297 (Danivest)** — a CVM condenou por administração de carteira sem autorização, afirmando que a PJ foi usada **"como fachada"** (multa R$ 200 mil + proibição de atuar por 10 anos). Também há linha consolidada de condenações por "exercício irregular" (art. 23 c/c ICVM 306/Res. 21).
- **Correção:**
  - O **diretor responsável do banco** exerce controle **real** — revisa e aprova de fato a cota/enquadramento/reporte, com **competência técnica própria** e **trilha auditável** (não carimbo).
  - O banco mantém **estrutura própria mínima** (RH + TI) — Res. 21, **art. 4º, VII** — não pode ser uma casca.
  - O banco **fiscaliza** a startup, com as verificações mínimas do Res. 21, **art. 32** (limites cumpridos; prestador tem estrutura adequada).
  - O contrato coloca o banco como **interveniente anuente**, com seleção criteriosa, SLA, direito de auditoria e de veto — Res. 175, **art. 80, p.ú.**

### Falha 2 — a startup não pode CAPTAR/DISTRIBUIR os fundos "por fora"
Captar/distribuir cotas **depende de autorização**; a startup fazer isso "por fora" é o flanco mais exposto ao **art. 23**. Além disso, **é preciso mapear exatamente o que a startup faz**: se ela prestar **escrituração de cotas** ou **controladoria de ativos** (atividades **reguladas**), **ela mesma precisa de habilitação própria** (Res. 175, art. 83 fala em "terceiros **habilitados e autorizados**"); se for **BPO/tecnologia fora do perímetro da CVM**, aplica-se o **dever de fiscalização** do banco (art. 83, §3º, II).
- **Correção:**
  - **Posicionar os serviços da startup como suporte operacional/tecnológico** (software, processamento, monitoramento) — com o **núcleo regulado (a titularidade da administração, a decisão, a coordenação) no banco**. Se algum serviço for regulado (escrituração/controladoria de ativos), decidir: ou o banco o executa, ou a startup se habilita para ele.
  - **A distribuição/captação** é do **banco ou de um distribuidor autorizado** — a startup "origina" no sentido de trazer a oportunidade e a tecnologia, não de ser quem oferta as cotas ao mercado.

### Falha 3 — o canal de assessores (AAI) só como DISTRIBUIDOR, nunca gestor
Res. CVM **178/2023**: o assessor de investimento **capta e recomenda** como **preposto de um intermediário** (art. 3º). É **vedado** ao AAI "realizar, ainda que a título gratuito, serviços de administração de carteira" (**art. 25, III**), e quem for gerir carteira **deve cancelar o registro de AAI** (**art. 7º, §2º**) — não há acúmulo. A linha é **funcional**: quem **decide os ativos** é gestor; quem **capta** é assessor.
- **Precedentes:** PAS **SP2012/0374 ("Hera")** e PAS 2025 — AAIs condenados por **gestão irregular** por, na prática, decidirem os investimentos dos clientes.
- **Correção:** no pitch e no plano, o assessor é **distribuidor/originador** de um fundo cujo **gestor é habilitado** (próprio registrado ou parceiro) e cujo **administrador é o banco**. **Conflito de interesse declarado** (Termo de Ciência — art. 37 + Anexo A). O AAI **nunca** movimenta recursos do cliente nem é "dono/gestor" do fundo.

---

## 3. O 75/25 — esclarecimento (o que mudou entre as frentes de pesquisa)

- **Legalmente, o repasse de ~75% é permitido** (Res. 175, art. 118, §1º — sem teto percentual). Não precisa ser reescrito para ser lícito.
- O risco **não é o número**; é a **substância**: os 75% só viram "indício de fachada" **se combinados** com o banco não exercer a atividade. Corrigida a Falha 1, a economia 75/25 é defensável.
- **Exigências que acompanham o repasse:** (a) **transparência** — discriminar as taxas e manter no site o **"Resumo das Remunerações dos Prestadores"**; (b) documentar a **razoabilidade econômica** (por que o banco fica com 25% — porque retém titularidade, responsabilidade, supervisão e a licença).
- **Prudência opcional (cinto e suspensório):** descrever o pagamento à startup como **taxa de serviço de tecnologia/BPO** (em vez de "fatia da taxa de administração") reduz a leitura de "quem monetiza é o não registrado". É recomendável na **comunicação**, embora não seja exigência legal.

---

## 4. Precedentes sancionadores (o que a CVM já puniu)

| Caso | O que foi | Lição para nós |
|---|---|---|
| **PAS RJ2014/8297 — Danivest** | PJ usada "como fachada" para administração de carteira sem autorização (R$ 200 mil + 10 anos) | A CVM olha a substância; usar uma PJ como fachada é punido |
| **PAS SP2012/0374 — "Hera"** | Agentes autônomos condenados por gestão irregular (decidiam os investimentos) | "Originar" + decidir de fato = gestão irregular |
| **PAS 2025 (assessores)** | AAIs condenados por exercício irregular de administração de carteira sob a Res. 178 | A CVM continua enquadrando AAI que gere como gestão irregular |
| **CVM 2024 (art. 23)** | Multa de R$ 340 mil por exercício irregular de administração de carteira | Linha jurisprudencial viva sobre exercício sem autorização |

> **Ressalva honesta:** **não** há precedente confirmado idêntico ao nosso caso (um **registrado** condenado por **alugar a licença** a um operador). O risco desse cenário é sustentado por **analogia** ao Danivest e à leitura de substância econômica da CVM — não por jurisprudência assentada. Ou seja: o risco é **real e conceitualmente sólido**, mas não há um "carimbo" da CVM dizendo isso literalmente.

---

## 5. Checklist de correção

**Na estrutura / no contrato (com advogado):**
- [ ] Diretor responsável do banco com **controle real** e trilha de aprovação auditável.
- [ ] Banco com **estrutura própria** (RH/TI) e **fiscalização documentada** da startup (Res. 21, art. 32).
- [ ] Contrato com o banco como **interveniente anuente** + seleção criteriosa + auditoria + veto (Res. 175, art. 80, p.ú.).
- [ ] **Segregação** custódia/controladoria × gestão (Res. 21, art. 30); diretor de adm. fiduciária **não acumula** com a tesouraria própria do banco (art. 30, p.ú.).
- [ ] **Mapear** cada serviço da startup: se algum for regulado (escrituração/controladoria de ativos), decidir quem o executa/habilita.
- [ ] **Distribuição** por participante autorizado; AAI apenas como preposto do intermediário.
- [ ] **Transparência** do repasse (Resumo de Remunerações no site) + razoabilidade econômica documentada.

**Na comunicação (deck, carta de intenções, plano):**
- [ ] Descrever o pagamento à startup como **serviço de tecnologia/operação**, não "fica com 75% da taxa".
- [ ] Reforçar em todo material que **o banco é o administrador de verdade** (já está no deck — manter e destacar).
- [ ] Reescrever o **Segmento 2 (assessores)**: o AAI é **distribuidor/originador**, com gestor habilitado — nunca "dono/gestor".
- [ ] Ter a resposta pronta para *"um advogado olhou isso?"* — idealmente, ter feito a consulta antes da reunião.

**Antes de operar:**
- [ ] **Consulta jurídica formal** de mercado de capitais para redigir o contrato e (se houver dúvida sobre a Falha 2) **consulta à área técnica da CVM/SIN**.

---

## 6. Incertezas sinalizadas (não inventamos artigo)

- **Numeração** de alguns dispositivos da Res. 175 (ex.: o artigo do "Resumo das Remunerações" e a faixa arts. 80–83) foi confirmada em **conteúdo**, mas parte da numeração exata deve ser **reconferida** no PDF consolidado antes de citar em peça formal.
- A **não-solidariedade** ("só em dolo/má-fé") está no **Código Civil, art. 1.368-D, II** + Res. 175, art. 81 — não numa cláusula numerada da 175 que a transcreva.
- **Não** existe precedente confirmado do cenário-espelho ("registrado que aluga licença") — risco por analogia, não jurisprudência.
- O **art. 16-A** da Lei 6.385 não foi confirmado verbatim; a âncora é o **art. 23** (autorização) + **art. 11** (penalidades).

---

## Veredito final

**A ideia é juridicamente viável e não tem falha fatal.** O modelo de terceirização é previsto nas normas, o banco é elegível sem capital mínimo da CVM, o repasse de 75% é permitido, e a responsabilidade é proporcional. **As três falhas são de desenho e de narrativa**, todas corrigíveis:
1. garantir que o **banco exerça de fato** a atividade (não fachada);
2. **não** ter a startup captando/distribuindo por fora, e mapear se algum serviço dela é regulado;
3. o canal de **assessores** só como distribuidor.

Feitas essas correções e **contratado um advogado para papelizar o contrato** (o único pré-requisito que eu trataria como inegociável antes de sentar com o compliance de um banco), a estrutura fica do **lado certo da linha** — e você chega à reunião podendo responder "sim, um advogado olhou, e o desenho respeita a Res. 21, a 175 e a 178".

---

*Fontes primárias: Res. CVM 21/2021, 175/2022, 178/2023 (conteudo.cvm.gov.br, consolidados); Lei 6.385/76 e CC art. 1.368-D (planalto.gov.br); PAS CVM RJ2014/8297, SP2012/0374 e correlatos (gov.br/cvm). Consultadas em 2026-07-09. Não substitui parecer de advogado habilitado.*
