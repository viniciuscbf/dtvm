# Relatório — Verossimilhança do piloto e dos docs vs. uma custódia real

> Auditoria (jul/2026) do portal de custódia do piloto e dos documentos do projeto contra fontes
> primárias: **Catálogo de Serviços do SFN v5.12** (BCB/Deinf, 27/03/2026, 3 volumes lidos),
> **Manual de Procedimentos Operacionais da Câmara B3**, **MPO da Central Depositária B3**,
> **Manual de Operações do balcão B3 (NoMe/Cetip21)**, **Res. CVM 32 consolidada**,
> **Res. BCB 105/2021** (contas no STR) e **IN BCB 144/2021** (grade do Selic).
> Pergunta do usuário: *"o custodiante do piloto parece com um da realidade ou é uma simulação fantasiosa?"*

## Veredito em uma frase

**O esqueleto é realista — o piloto acerta as coisas difíceis (contas individualizadas por fundo,
conciliação diária como obrigação, segregação de papéis, D+2 em bolsa, beneficial owner) — mas a
"pele" tem fantasia em três lugares: a semântica da mensageria SPB, o modelo de liquidação de bolsa
(bilateral em vez de netting pela câmara) e dois vazamentos de segregação no código.**

---

## 1. O que está CERTO (e é o mais difícil de acertar)

| Item do piloto | Realidade | Veredito |
|---|---|---|
| Cada fundo tem conta individualizada no SELIC, B3 Depositária e B3 Balcão; só o banco tem conta Reservas | Modelo brasileiro de *beneficial owner*: contas no nível do investidor final desde 2010 (Selic) / sempre (B3). Fundo **não** tem conta no STR (Res. BCB 105) | ✅ fiel |
| Conciliação diária Posição × Custodiante como obrigação | Res. CVM 32, art. 13, §1º, I — literal | ✅ fiel |
| `posicao_custodiante` = fonte independente com divergências determinísticas de timing (~8%) | Duas pontas independentes conciliadas diariamente | ✅ desenho certo |
| D+2 para ações | D+2 hoje; D+1 só previsto para **fev/2028** (projeto B3 set/2025) | ✅ correto hoje |
| **SEL1052** para compra definitiva de título público | SEL1052 = "Participante requisita **Operação definitiva**" — existe e é isso mesmo | ✅ (acerto notável) |
| Eventos: anunciado → provisionado → creditado; JCP com IR; amortização baixa PU; bonificação ajusta qtd | Fluxo real da Depositária (MPO cap. 6): emissor paga a depositária → repassa ao agente de custódia → credita o fundo | ✅ conceito fiel |
| Aceite/rejeição de instrução com motivo e trilha | Balcão B3 real funciona por **duplo comando** (tela "Operações Não Casadas") — o aceite É o comando da ponta | ✅ para balcão (ver §2.3 para bolsa) |
| Falha de liquidação com estorno/repactuação no balcão | Real: modalidade bruta permite **estorno unilateral após 30 min** de "PENDENTE DE LIQUIDAÇÃO FINANCEIRA" | ✅ plausível p/ balcão |
| Notas honestas nos rodapés ("no produto final trafega por SFTP/API"; "o piloto simula o tráfego") | — | ✅ postura certa |

Os docs regulatórios (`custodiante/*.md`) citam artigos e prazos da Res. 32 **corretamente**
(diretores 7 d.u.; relatório anual até abril; mensal até o 10º dia; retenção 5 anos; auditoria
interna ≠ auditor independente; terceirização art. 19). A matriz de conformidade é sólida.

---

## 2. O que é FANTASIA (corrigir)

### 2.1 Semântica da mensageria SPB — o erro mais visível para um olho técnico

| No piloto | Na realidade (Catálogo SFN v5.12) | Gravidade |
|---|---|---|
| `STR0008` usado como "**confirmação** de liquidação financeira" | STR0008 = "IF **requisita** Transferência entre contas de **clientes**" — é a mensagem da TED, um comando, não uma confirmação. Confirmações são as classes R1/R2 da própria mensagem; no Selic, `SEL1099` ("SEL informa movimentação financeira") | 🔴 alta |
| `SEL1054` usado como "resgate antecipado informado — divergência" | SEL1054 = "Participante requisita **Operação compromissada**" — é literalmente a mensagem da **zeragem/overnight** (que o piloto não tem!) | 🔴 alta (ironia: usamos o código da zeragem para outra coisa) |
| `MOV0001`, `MOV0004`, `PRO0002`, `POS0900/0901/0902` | **Grupos MOV/PRO/POS não existem.** Grupos reais: SEL, STR, LDL, CAM, BMC, PAG, SLC, TES, CSD, GEN… Para posição de custódia no Selic existe `SEL1081` (consulta posição — a base real da conciliação); para bolsa, `LDL0001` (resultado líquido) e `LDL0005` (câmara paga credores) | 🟠 média (o rodapé diz "estilo catálogo", mas um back-office nota) |

### 2.2 Modelo de liquidação de bolsa — bilateral onde a realidade é multilateral

O piloto liquida **cada boleta de ação individualmente**, com botão "Liquidar DVP" por operação e
uma mensagem "STR" por trade. Na realidade a **Câmara B3 é CCP**: compensação **multilateral
líquida**, janela única diária (devedores pagam 14h10–14h50; DVP final ~15h50; `LDL0004`/`LDL0005`),
financeiro pelo **saldo líquido** do liquidante — nunca uma transferência STR por negócio.
O modelo por operação é correto **apenas no balcão** (bruta bilateral, duplo comando).

### 2.3 Papel do custodiante em bolsa

Custódia "aceitar/rejeitar boleta" de **ação** não existe: o negócio é executado pela corretora,
alocado até 15h e casado na CCP; o custodiante liquida o que a câmara instrui. O aceite bilateral
do piloto é o rito do **balcão** aplicado indevidamente a tudo.

### 2.4 Títulos públicos: D+1 "em lote" vs LBTR em tempo real

O Selic liquida **operação por operação, em tempo real (LBTR/DVP modelo 1), D+0**, grade 6h30–18h30
(TED cliente até 17h30). O piloto (e o `manual_liquidacao_dvp.md`) tratam título público como
"D+1 confirmado no fim do dia". A convenção comercial D+0/D+1 existe, mas o *sistema* é tempo real.

### 2.5 Dois vazamentos de segregação no código (contradizem os próprios manuais)

1. **`admin/custodia.php` executa atos privativos do custodiante** — tem os mesmos botões de
   "Liquidar DVP", "Provisionar" e "Creditar evento" do portal de custódia. Os manuais do projeto
   pregam "quem produz não é quem valida"; aqui a administradora produz. Herança de antes do portal
   de custódia existir. **Fix: tornar a tela da adm. somente-leitura (espelho + conciliação).**
2. **`custodia/arquivos.php` gera o "arquivo de posição do custodiante" a partir da `carteira()` da
   administradora** — circular: o arquivo que alimenta o batimento vem da mesma fonte que ele
   deveria conferir. A fonte independente (`posicao_custodiante`) existe e não é usada aqui.
   **Fix: exportar de `posicao_custodiante`.**

### 2.6 Liquidação fora da data

O botão "Liquidar DVP" funciona **antes** da `data_liquidacao` (D+1/D+2 é gravado mas não
respeitado) e movimenta o caixa na data de hoje. **Fix: bloquear antes da data (o Simulator Master
controla o relógio, então dá para validar contra a data simulada).**

---

## 3. O que está AUSENTE (simplificações aceitáveis — mas dizer, ou simular barato)

| Ausência | Realidade | Recomendação |
|---|---|---|
| **Zeragem/overnight do caixa** | Caixa livre vai para compromissada over lastreada em TPF — comando `SEL1054`, retorno `SEL1056` na abertura (6h30–6h45) | Simular no batch (barato e didático — e o catálogo do piloto já tem "COMPROM SELIC") |
| **Margem/garantias de derivativos** | Fundo colateraliza na câmara (modelo por comitente), título vira garantia via `SEL1023`, chamadas CORE, mensagens `LDL1001/1003/1016` | Nota honesta ou simulação simples de "margem depositada" |
| **Grades de horário** | Selic 6h30–18h30; TED cliente 17h30; janela da câmara 14h10–14h50; alocação RV até 15h | Exibir cut-offs nas telas (cosmético, alto ganho de realismo) |
| **Multa/buy-in em falha de bolsa** | Empréstimo compulsório → multa 0,5% (teto R$ 50 mil) → posição de falha D+1 → recompra | Nota na tela de falha |
| Empréstimo de ativos, subcustódia | Existem no mundo real | Fora de escopo — ok declarar |

---

## 4. Correções nos DOCS (.md)

| Doc | O que corrigir |
|---|---|
| `custodiante/manual_liquidacao_dvp.md` | Título público: LBTR/D+0 tempo real (não "D+1 via STR"); ações: netting multilateral pela câmara com perna financeira LDL (não STR por operação); citar STR0008 apenas como TED/transferência |
| `custodiante/matriz_conformidade_cvm32.md` | Mesmas correções de ciclo (TPF D+0; bolsa líquida multilateral) |
| `guia_custodia_conexoes.md` | Balcão: acrescentar duplo comando/Operações Não Casadas; bolsa: CCP/netting; grades de horário reais |
| `guia_tecnico_sistemas.md` | **Split 25/75 → 50/50** (desatualizado vs deck); resto está bom (o doc acerta iMercado, NEGS/TORDIST, "você não constrói conexão bruta com Selic/Cetip") |
| `especificacao_piloto_dashboard.md` | Códigos de mensagem reais na descrição do 4º portal |

## 5. Nota comercial

Numa reunião técnica com o back-office de um banco, os erros da seção 2 são os que um operador de
retaguarda **nota em 5 minutos** (STR0008 como confirmação; ação liquidando bilateral; MOV/PRO/POS).
Corrigi-los transforma o portal de "maquete bonita" em "maquete que fala a língua da retaguarda" —
que é exatamente o pitch. As correções P1 são baratas (semântica de mensagens + 2 fixes de
segregação + gate de data); a zeragem via SEL1054/1056 é o upgrade de maior efeito didático.

## 6. Plano de correção — ✅ APLICADO EM 11/07/2026

Todos os itens P1+P2+P3 e docs foram implementados, testados localmente (lint + reset + smoke +
passar-de-dia) e deployados (código via FTP; dados demo do prod migrados e script removido).
Além do plano, a **boleta** ganhou os campos de uma boleta real: **data de liquidação pactuada**
(travada em D+2 na bolsa; D+0/D+1 no TPF; D+0–D+2 no balcão), **taxa negociada** (RF) e
**modalidade de liquidação** do NoMe (Bruta/Bilateral/Sem modalidade) — com a custódia
respeitando a data pactuada em vez de recalcular. Fora do escopo declarado: banco liquidante,
corretagem/emolumentos, ISIN (nota honesta na tela).

### Plano original (referência)

- **P1 — mentiras factuais (código):** trocar códigos/semântica da mensageria (STR0008→uso correto;
  SEL1054→zeragem ou remover; MOV/PRO/POS→SEL1081/LDL0001/LDL0005/GEN); TPF D+0; distinguir
  balcão (duplo comando, DVP bruta por operação) de bolsa (netting em janela, liquidação em lote);
  `arquivos.php` exportar de `posicao_custodiante`; `admin/custodia.php` somente-leitura;
  bloquear liquidação antes da data.
- **P2 — realismo barato (código):** grades/cut-offs nas telas; nota de multa/buy-in na falha;
  renomear "Aceitar" → "Comandar ponta (duplo comando)" no balcão.
- **P3 — upgrades:** zeragem compromissada no batch (SEL1054/1056); margem simplificada de
  derivativos.
- **Docs:** correções da seção 4.

*Fontes primárias citadas no texto; extratos dos manuais no scratchpad da sessão de auditoria.*
