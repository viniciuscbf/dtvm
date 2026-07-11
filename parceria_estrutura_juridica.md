# Estrutura da parceria — como funciona, artigo por artigo

> Responde às perguntas operacionais da parceria (diretor, custódia, distribuição, tipo de parceiro)
> com base em pesquisa de fontes primárias (jul/2026): Res. CVM 21/32/175 consolidadas, Lei 6.385,
> Lei 12.865, Res. CMN 2.099/2.607, 5.009, 5.050, 5.237, 2.122. Complementa (não substitui) a
> `due_diligence_juridica.md`. **Nada aqui dispensa a consulta jurídica formal antes de assinar.**

## 0. O desenho em uma frase

O **parceiro** (instituição autorizada BCB) registra-se na CVM como **administrador fiduciário**
(Res. 21, art. 1º, §2º, I — sem exigência de capital adicional da CVM); a **Argus** presta
**tecnologia e operação** como terceiro **não-habilitado** (Res. 175, art. 83, §3º), sob
**fiscalização** do administrador (Res. 21, art. 32) — o parceiro decide, controla e responde.

---

## 1. "O banco tem que ter um diretor para isso? Ou fica com a gente?"

**Fica no banco — sempre.** E são **dois** diretores estatutários **da instituição**:

| Diretor | Base | Requisitos | Atenuantes |
|---|---|---|---|
| **Responsável pela adm. fiduciária** | Res. 21, art. 4º, III | Deve ser **diretor estatutário** da PJ e ter **registro PF de administrador de carteiras na CVM** — que exige curso superior + certificação (**CGA ou CGE** ANBIMA; ou CFA nível III; ou ACIIA) ou dispensa da SIN por **7 anos de experiência** comprovada (art. 3º, §1º) | Se a instituição for **só** administrador fiduciário (sem categoria gestor), o diretor **pode acumular** outras funções — vedado só administrar os recursos próprios da instituição (art. 30, p.u.). Quem tem as duas categorias precisa de diretor **exclusivo** (art. 4º, §6º) |
| **Compliance/controles internos** | Res. 21, art. 4º, IV | **Não** precisa de registro PF nem certificação; precisa de experiência e independência (§3º) | Normalmente o diretor de compliance que o banco já tem |

**Pode ser uma pessoa NOSSA?** Só se **contratada pelo banco como diretor estatutário** (eleita em
ata — art. 4º, §7º; não precisa ser sócia). Aí ela vira **órgão do banco** e responde pessoalmente
perante a CVM. A Res. 21 impede o "diretor emprestado" de fora do grupo: o §4º do art. 4º só admite
acumulação de funções **dentro do mesmo grupo societário** (controladora/controlada/coligada/controle
comum). **Nosso trunfo real:** temos sócio com **CGA + CGE** — se o banco não tiver ninguém
certificado, podemos indicar essa pessoa para ser contratada/eleita pelo banco. É legítimo, mas ela
passa a dever lealdade ao banco (e é assim que a CVM quer).

**Linha vermelha (precedente Danivest, PAS RJ2014/8297):** o que a CVM pune é o **exercício de fato
sem registro** — se a Argus mandar na prática e o banco só assinar, é atividade irregular (Lei 6.385,
art. 23; multa + inabilitação). Diretoria real, fiscalização real.

## 2. "O custodiante fica no banco? Ou é externo?"

**As duas coisas, em fases — e depende do tipo do parceiro:**

- **Fase 1 (recomendada): custódia EXTERNA.** O administrador contrata, em nome do fundo, um
  **custodiante já autorizado** pela CVM (Res. 175, Anexo I, art. 25, III). Só pode ser custodiante
  autorizado (Res. 32, art. 19, §1º) e o contrato tem **responsabilidade solidária** obrigatória
  (§3º). Vira despesa do fundo (~R$ 1,5 mil/mês de mercado — nosso modelo absorve/negocia).
- **Fase 2: custódia INTERNA no parceiro** — só se o parceiro estiver no **rol fechado** da Res. 32,
  art. 4º: *bancos comerciais, múltiplos ou de investimento, caixas econômicas, CTVM, DTVM e
  clearings/depositárias*. Exige autorização própria (deferimento automático se a SMI não negar em
  **90 dias**, art. 7º), dois diretores próprios não-cumuláveis (art. 17), auditoria interna
  (art. 20) e a infraestrutura (adesões B3/SELIC/RSFN). Internalizar só quando a escala pagar
  (taxa CVM de custodiante ~R$ 38 mil/ano + estrutura).
- **Financeira (SCFI), IP e afins NUNCA custodiam** (fora do rol) — com esses parceiros a custódia é
  externa para sempre, ou o grupo constitui uma DTVM.

## 3. "Temos que criar uma distribuidora na instituição?"

**Não.** Três vias legais, nenhuma exige nova empresa:

1. **O próprio administrador distribui as cotas dos fundos que administra** — Res. 21, **art. 33**
   (com regras de suitability, cadastro, PLD, e um diretor responsável pela distribuição, que **pode
   ser o mesmo** do art. 4º, III). Confirmado também pela Res. 175, art. 85, §1º.
2. **O gestor de cada fundo distribui as cotas dos fundos que gere** — mesmo art. 33. É a via
   natural do nosso modelo: cada fundo pequeno tem gestor profissional que traz os próprios clientes.
3. **Distribuidores contratados** (plataformas, corretoras, **assessores de investimento**) — canal
   dos AAIs do Segmento 2. **Detalhe importante do art. 33, §2º:** quem **não** é instituição
   autorizada BCB não pode contratar AAI para distribuir — ou seja, o canal de assessores **depende
   do banco** (mais um motivo para o parceiro ser instituição BCB, e mais um argumento de venda:
   sem o banco, esse canal não existe).

A Argus **nunca** distribui — continua a linha vermelha da due diligence.

## 4. "Como muda por tipo de instituição?" — a matriz

| Tipo (universo `bancos_alvo`) | Adm. fiduciário? | Custódia própria? | Distribui? | Capital BCB já pago | Veredito como parceiro |
|---|---|---|---|---|---|
| **DTVM existente** | ✅ juridicamente | ✅ juridicamente | ✅ | R$ 0,55–1,5 mi | **EXCLUÍDA POR DECISÃO ESTRATÉGICA**: já tem retaguarda de mercado de capitais própria — agregamos pouco e o risco de absorver a ideia e dispensar a parceria é alto. Fora da lista |
| **Banco comercial/múltiplo/investimento** | ✅ direto (Res. 21, art. 1º, §2º, I) | ✅ elegível (Res. 32, art. 4º) | ✅ (sistema de distribuição + art. 33) | R$ 17,5/12,5 mi | **Parceiro completo** — o alvo ideal |
| **CTVM** (corretora de títulos, inclui "câmbio e títulos") | ✅ direto | ✅ elegível | ✅ (é distribuidor nato) | R$ 1,5 mi (com fundos) | **Parceiro completo**, sem conta Reservas (usa banco liquidante) |
| **Caixa econômica** | ✅ | ✅ | ✅ | — | Elegível, mas estatal (fora do perfil) |
| **Financeira (SCFI)** | ✅ **desde 1º/9/2025** — Res. CMN 5.237/25, art. 6º, p.u., **IV** ("administrar carteiras de valores mobiliários") | ❌ (fora do rol da Res. 32) | ✅ via art. 33 p/ fundos próprios | R$ 7 mi | **Parceiro de administração** com custódia externa — *novidade regulatória que ninguém está explorando* |
| **SCD (fintech de crédito)** | ❌ objeto **taxativo** (Res. 5.050, art. 7º, §1º — administrar fundos não consta) | ❌ | ❌ | R$ 1 mi | Só via **DTVM nova no grupo** (ver abaixo) |
| **IP (pagamentos)** | ⚠️ **defensável** — a Res. 175, art. 83, §1º cita "instituição de pagamento" como administrador; e adm. de carteiras não é privativa de IF (Lei 12.865, art. 6º, §2º). Mas é **inédito** — exigiria conforto jurídico/CVM | ❌ | ⚠️ | R$ 2 mi | Tese avançada — não usar como alvo primário |
| **Corretora de câmbio** (só câmbio) | ❌ objeto **exclusivo** câmbio (Res. 5.009, art. 2º) | ❌ | ❌ | R$ 0,35 mi | Só via DTVM nova |
| **Companhia hipotecária** | ❌ (só FII, Res. 2.122, art. 3º, IV) | ❌ | ❌ | R$ 3 mi | Não serve (nosso produto não é FII) |
| **PJ não-financeira (nós mesmos!)** | ✅ **Res. 21, art. 1º, §2º, II**: manter continuamente o maior entre 0,20% do AUM e **R$ 550 mil**, simultaneamente em (a) PL e (b) caixa+títulos públicos; DFs auditadas anuais | ❌ | ✅ art. 33 (mas **sem** poder contratar AAI — §2º) | — | **Plano B real**: registro próprio com ~R$ 550 mil imobilizados + diretor certificado (temos CGA/CGE). Perde custódia interna, canal AAI e a marca do banco |

**Rota "constituir DTVM"** (para quem não é elegível): capital regulamentar **R$ 1,5 mi** para
DTVM/CTVM *que administre fundos* (Anexo II da Res. 2.099, red. Res. 2.607, inciso V — menos 30% se
sede fora de RJ/SP), processo BCB (Res. 4.970 + IN BCB 704/2026) com exame de até **12 meses**
(prática: 6–12+). Muda o pitch de "ativar estrutura ociosa" para "montar do zero" — só vale para
parceiro muito motivado (ex.: fintech com capital que queira o vertical).

## 5. O que isso muda na prospecção (`bancos_alvo.xlsx`)

- **DTVMs: eliminadas da lista (decisão estratégica, jul/2026)** — juridicamente seriam o veículo
  perfeito, mas já têm retaguarda de mercado de capitais própria: agregamos pouco, e o risco de a
  DTVM absorver o conceito e dispensar a parceria é alto. Nenhuma DTVM nos Alvos.
- **Bancos + CTVM (52 dentro do teto)** — parceiros completos. Pitch: receita nova
  + custódia própria opcional + canal AAI habilitado. O modelo é "vocês já passaram a barreira
  de capital do BCB; nós montamos a área de tecnologia e ajudamos no licenciamento CVM"
  (dossiês de `custodiante/` e `administrador_fiduciario/` prontos; adm ~60 dias, custódia
  90 dias da SMI + adesões). **Prioridade 1.**
- **Financeiras (SCFI)** — promovidas de "precisa virar DTVM" para **elegíveis diretas à
  administração fiduciária** (custódia terceirizada). Pitch específico: "a Res. 5.237 abriu essa
  porta há menos de um ano — seja o primeiro a monetizá-la, sem montar nada". **Prioridade 2.**
- **SCD / IP / câmbio / hipotecária** — **não servem diretamente**; só via DTVM nova (R$ 1,5 mi +
  6–12 meses) ou, para IP, tese jurídica inédita. Rebaixados para "fundo do funil" — abordar apenas
  se demonstrarem apetite espontâneo.

## 6. Riscos/razoabilidade para o parceiro (resposta curta: é prática normal de mercado)

- **Terceirizar tecnologia/BPO de fundos é padrão da indústria** (toda administradora usa
  fornecedores; a Res. 175, art. 83, §3º, II disciplina exatamente isso). O que o parceiro NÃO pode
  é esvaziar-se: a Res. 21, art. 4º, VII/§8º exige que ele mantenha recursos humanos e computacionais
  próprios adequados — nosso desenho já prevê diretoria real + compliance com acesso total.
- A **responsabilidade regulatória fica no parceiro** (Res. 175, arts. 80–81) — por isso o pacote
  entrega manuais, trilha de auditoria e supervisão; e por isso a remuneração da Argus é enquadrada
  como **serviço** (art. 118, §1º permite o repasse, sem teto).
- **Risco reputacional/sancionador** concentra-se em fazer de conta (Danivest): mitigado com
  governança real, não com papel.

## 6-B. Investigação: dá para fazer SOZINHOS, sem capital? (resposta precisa)

**Custodiante sem ser instituição financeira: IMPOSSÍVEL — e não é questão de capital.** O rol do
art. 4º da Res. 32 é fechado (bancos com/mult/inv, caixas, CTVM, DTVM, clearings). Não existe valor
de PL que coloque uma PJ comum no rol; a única porta é **virar** um daqueles tipos — ou seja,
autorização BCB (a menor é CTVM/DTVM própria: capital regulamentar **R$ 1,5 mi** para quem
administra fundos, −30% se sede fora de RJ/SP → **R$ 1,05 mi**; processo de 6–12+ meses; carga
prudencial contínua). A premissa "temos barreira de capital para custodiar" está **confirmada**.

**Administrador fiduciário sem ser IF: POSSÍVEL — e a barreira é menor do que parece.** Res. 21,
art. 1º, §2º, II: manter **continuamente** o maior entre 0,20% do AUM e **R$ 550 mil**, em cada uma
de duas rubricas — (a) patrimônio líquido e (b) disponibilidades + títulos públicos federais.
Detalhe que muda a conta: **o mesmo dinheiro atende as duas ao mesmo tempo** — R$ 550 mil de capital
integralizado e aplicado em títulos públicos produz PL de 550 mil ✓ e disponibilidades+TPF de
550 mil ✓. Não é taxa nem custo perdido: é capital **nosso, estacionado, rendendo Selic**, com DFs
auditadas anuais (art. 1º, §5º). Somado ao diretor certificado (temos CGA/CGE) e à estrutura
(temos o piloto), o registro próprio é **viável com ~R$ 550 mil imobilizados** — mas perde custódia
interna (terceirizar para sempre), perde o canal AAI (art. 33, §2º) e perde a marca do banco.
A exigência escala com o AUM só acima de **R$ 275 mi** (0,20% × 275 mi = 550 mil) — administrável.

**O mapa estratégico honesto, portanto:**
| Plano | Capital nosso | Prazo | O que destrava |
|---|---|---|---|
| **A — parceiro banco/CTVM/SCFI** | R$ 0 | semanas p/ assinar + ~60/90 dias CVM | tudo (adm + custódia própria + AAI + marca) |
| **B — registro próprio (PJ não-IF)** | ~R$ 550 mil estacionados | ~60 dias CVM | só administração; custódia terceirizada; sem AAI |
| **C — DTVM própria** | R$ 1,05–1,5 mi + prudencial | 6–12+ meses BCB + CVM | tudo, sem sócio — mas capital e tempo |

O Plano B é relevante até como **BATNA na negociação**: não dependemos de um "sim" a qualquer custo.

## 7. O modelo "nós ajudamos a instituição a OBTER as licenças" — é robusto?

**Sim, e é o desenho mais limpo de todos.** Não existe vedação a que um terceiro prepare o pleito:
quem **requer e recebe** as autorizações é a instituição (Res. 21, art. 4º; Res. 32, art. 5º), e a
CVM avalia **a capacidade dela** — diretores, sistemas, manuais. O que entregamos (dossiês,
matrizes de conformidade, manuais, a plataforma como sistema licenciado) vira capacidade **dela**,
desde que o contrato garanta o que a Res. 21, art. 4º, VII/§8º exige: a instituição "constitui e
mantém" recursos humanos e computacionais adequados, auditáveis e protegidos — ou seja, licença de
software com direito de auditoria/continuidade, não "sistema emprestado". Barreiras reais:
**não é PL** (R$ 550 mil–1,5 mi — qualquer alvo da lista tem), é (a) o **tipo societário** (resolvido
escolhendo o alvo certo — DTVM/banco/CTVM/SCFI) e (b) o **diretor certificado** (resolvido com o
quadro do parceiro ou com contratação — inclusive de pessoa indicada por nós, eleita como estatutária).
Único cuidado permanente: substância (Danivest) — a instituição decide e fiscaliza de verdade.

## 8. Distribuição na prática — simples ou difícil?

**Para o nosso go-to-market, simples — e sem plataforma.** A captação dos fundos pequenos não é
varejo frio de prateleira: é **originada** (clube que vira fundo traz os próprios participantes;
gestor traz os próprios clientes; AAI traz a carteira dele). Para isso bastam as vias do art. 33
(administrador e gestor autodistribuem) + o parceiro como contratante dos AAIs. **Plataforma
(XP/BTG/órama)** só entra se um fundo quiser captar público desconhecido — é upgrade de fase 2,
negociado fundo a fundo, não pré-requisito.

**Recomendação sobre "constituir uma distribuidora depois": não constituir — provavelmente nunca.**
- Com **banco/CTVM/DTVM** parceiro: ele já é distribuidor (e pode contratar AAIs).
- Com **SCFI** parceira: ela autodistribui os fundos que administra (art. 33) e, por ser instituição
  autorizada BCB, **também pode contratar AAIs** (a vedação do art. 33, §2º só atinge administrador
  que NÃO é instituição BCB).
- Com **fintech SCD/IP** (que não pode ser administradora): o caminho é constituir uma **DTVM** no
  grupo — e a DTVM **já nasce distribuidora** ("D" é de distribuidora). Nunca se cria uma
  "distribuidora avulsa": o veículo que resolve a administração já resolve a distribuição.
- No **plano B** (registro próprio como PJ não-financeira): autodistribuímos os próprios fundos,
  mas **sem canal AAI** (art. 33, §2º) — é a maior perda desse cenário.

*Elaborado em 11/07/2026 com fontes primárias citadas; itens marcados como defensáveis/inéditos
exigem validação na consulta jurídica formal (pré-requisito já registrado antes de assinar com banco).*
