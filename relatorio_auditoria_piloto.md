# Relatório de Auditoria de Correção do Piloto

> **Documento de trabalho — v1.0.** Auditoria técnica read-only do piloto (`piloto/`) sob a ótica de custódia + administração fiduciária. Foco em **correção e completude** (diferente de `relatorio_gaps_producao_licenca.md`, que trata do que falta para produção/licença). Cada achado tem âncora em `arquivo:linha`.

---

## Veredito executivo

O piloto é uma **demonstração de fluxo**, coerente na navegação e no ciclo prévia→aprovação de cota. Porém, o **padrão central** que atravessa quase tudo é:

> **Dados estáticos de seed + ações que só mudam status.** Posições, conciliações, repasses, alertas e envios são pré-carregados; os cliques (resolver, escalar, instruir, liquidar, enviar) apenas alteram um campo de status. Os únicos fluxos que **recalculam** algo de dados reais são o cálculo da cota (`calcular_cota`) e o motor de passivo/tributação (`includes/passivo.php`).

Isso é aceitável para vender o conceito, mas há **erros de correção** e **simplificações não sinalizadas** que um analista técnico (ou a due diligence de um banco) apontaria. Abaixo, por tema.

---

## Status pós-remediação (rodada v2)

Uma rodada de correções atacou os achados de correção/integridade. **Corrigidos:**
- ✅ **Transações** em todas as operações financeiras (1.1); **idempotência** anti-duplo-clique (1.2); **CSRF** em todos os handlers de estado (1.3); **fuso + feriados B3** (1.4);
- ✅ **Posição do custodiante como fonte independente** + **conciliação computada** de verdade (2.1, 3.2, 3.3 — para a frente Posição × Custodiante);
- ✅ **Provisão diária de despesas** reduzindo a cota (2.3); **amortização** baixa principal e reduz o PU do ativo (2.5); **classificação de caixa pelo tipo** escolhido, não pelo sinal (3.4); **tributo separado do líquido** no resgate (3.5);
- ✅ **Enquadramento PRÉ-TRADE** no gestor (5.3); **DVP atômico** (4.1, atomicidade);
- ✅ **Novos módulos** (fiéis a plataformas reais): **catálogo de ativos + boletagem** (o gestor só boleta o que está cadastrado; solicita inclusão; admin aprova) e **sistema de tickets/suporte**.

**Rodada v3 (Balde 2 — núcleo funcional):**
- ✅ **Caixa e cotas versionados por data** (2.2) — `caixa_na_data` e `total_cotas_na_data`; `calcular_cota` agora usa o caixa/cotas DA data (recálculo retroativo correto);
- ✅ **Contabilidade de dupla entrada** (2.4) — plano de contas, partidas dobradas, razão, diário e **balancete** que fecha (Ativo = Passivo + PL), em Admin → Contabilidade (`includes/contabilidade.php`, `admin/contabilidade.php`).

**Rodada v4 (Balde 2 — fases 2-4):**
- ✅ **MaM homologada** (2.6, parcial) — **feed de preços independente** (`precos_mercado`) do qual nasce o preço de referência; **Comitê de Precificação** que homologa preço com ata; cascata ANBIMA→B3→Comitê; divergências marcação × referência destacadas (Admin → Precificação). Fontes reais (ANBIMA/B3) seguem como integração;
- 🟡 **KYC / suitability / PLD** (5.2) — onboarding do cotista com suitability, KYC, PLD (screening simulado), FATCA/CRS e termo de adesão, com regra suitability × público-alvo (Admin → Onboarding de cotistas);
- 🟡 **Classes/subclasses** (5.4) — modeladas como estrutura (público-alvo, taxas, prazos de cotização/liquidação; Res. 175 art. 5º) em Admin → Classes & Subclasses. A **segregação plena de patrimônio por classe** (contabilidade e cota próprias por classe) segue como evolução maior, declarada.

**Ainda deferidos:** republicação em cascata 100% correta (2.7 — mitigada pelo versionamento por data), provento na data-ex (2.8), performance com HWM (2.9), fails/janela temporal de liquidação (4.3), mensageria com parsing (4.4), envios regulatórios com conteúdo real (5.1), segregação plena de patrimônio por classe, motor de fraude com ML real (5.5), armazenamento de uploads (5.6).

---

## 1. Integridade técnica (transversal — o mais grave tecnicamente)

| # | Achado | Hoje | Faltaria | Sev. |
|---|---|---|---|---|
| 1.1 | **Sem transações** em operações financeiras | `passivo_aplicar/resgatar/come_cotas` (`includes/passivo.php:64-71,110-134,175-188`), `publicar_cota` (`gestor/cotas.php:21-40`), `lancamentos.php`, `aplicar_boleta_na_carteira` (`custodia/instrucoes.php:12-46`) fazem múltiplos UPDATE/INSERT soltos | `beginTransaction/commit/rollBack` por operação atômica | **Crítica** |
| 1.2 | **Sem idempotência / anti-duplo-clique** | Só come-cotas tem guarda (`passivo.php:154`); resgate/aplicação/aceite/lançamento duplicam em clique duplo | Token de submissão único + constraints | **Crítica** |
| 1.3 | **CSRF só em 7 de ~25 handlers** | Custódia e passivo validam; **não validam** `admin/lancamentos`, `processamento`, `regulatorio`, `aberturas`, `fraude`, `gestor/cotas`, `assembleias`, `repasses` (alguns têm o campo, sem `csrf_validar()` no back) | Validar CSRF em todo handler que muda estado | **Alta** |
| 1.4 | **Sem fuso e sem feriados** | `date_default_timezone_set` nunca chamado; `proximo_dia_util` e a liquidação só pulam fim de semana (`simulador.php:37`, `instrucoes.php:63`); "dias úteis" regulatórios nunca calculados | Timezone fixo + calendário de feriados (ANBIMA/B3) | **Média** |

## 2. Modelo econômico e contábil (o que compromete a correção da cota)

| # | Achado | Hoje | Faltaria | Sev. |
|---|---|---|---|---|
| 2.1 | **Fonte única de posição** | Custodiante e administradora leem a **mesma** `ativos_carteira` (`helpers.php:164`, `custodia/index.php:16`, `admin/custodia.php:69`); a "posição do custodiante" não existe como fonte separada | Tabela de posição do custodiante independente, por conta em `contas_centrais` | **Alta** |
| 2.2 | **Caixa e cotas não versionados por data** | `fundos.caixa_atual` é escalar único; `total_cotas` = `SUM(cotistas.cotas)` atual (`helpers.php:264-281`); a cota retroativa usa caixa/cotas de **hoje** | Saldo de caixa e cotas emitidas **por `data_ref`** | **Alta** (quebra o recálculo retroativo) |
| 2.3 | **Sem provisão de despesas na cota** | Taxa adm/gestão/custódia/CVM/ANBIMA/auditoria **não** reduzem o PL diário; `apurar_taxa_mensal` só alimenta dashboard; `repasses.php` só lê seed | Accrual pro-rata diário reduzindo PL/cota + débito periódico em caixa | **Alta** (cota publicada superestimada) |
| 2.4 | **Sem contabilidade de dupla entrada** | Só snapshot de carteira + `caixa_atual` + `movimentacoes` (log unilateral); "Balancete/CDA" são rótulos de envio | Plano de contas, partidas dobradas, razão/diário/balancete | **Alta** |
| 2.5 | **Amortização creditada como "Provento" e sem baixa de principal** | Liquidar evento grava sempre `movimentacoes.tipo='Provento'` com `valor_total` cheio e **não reduz** a posição do ativo (`instrucoes.php:131-135`, `admin/custodia.php:45-47`) | Distinguir amortização (reduz principal/qtd) de rendimento (receita) | **Alta** (erro contábil e de base tributável) |
| 2.6 | **MaM sintética** | Preço estático do seed, movido por fator CDI (RF) ou random ±1,5% (ações) no simulador (`simulador.php:96-103`); `fonte_preco` é rótulo | Fonte real (ANBIMA/B3), curva por papel, comitê, ágio/deságio, níveis 1/2/3 | **Alta** |
| 2.7 | **Republicação em cascata por fator constante** | `publicar_cota` multiplica `pl`/`cota` dos dias seguintes por `cota_nova/cota_antiga` (`cotas.php:22-30`), ignorando fluxos próprios desses dias; não reprocessa tributos/taxas | Recálculo dia-a-dia a partir dos snapshots reais | **Alta** |
| 2.8 | **Provento só no pagamento** | "Provisionado" é rótulo sem efeito no PL; crédito só na data de pagamento (`instrucoes.php:120-137`); data-ex ignorada | Reconhecer direito a receber na data-ex | **Média** |
| 2.9 | **Taxa de performance sem linha d'água** | Cálculo pontual 12m × PL, nunca provisionado/debitado (`performance.php:52-60`) | High-water mark por cotista + provisão diária | **Média-Alta** |

## 3. Classificação de caixa e conciliação (a sua pergunta)

| # | Achado | Hoje | Faltaria | Sev. |
|---|---|---|---|---|
| 3.1 | **Classificação invertida** | O sistema fixa o `movimentacoes.tipo` na origem (é ele que cria o lançamento); **não há extrato "cru" a classificar**, nem importador (zero `fgetcsv` no projeto) | Importar extrato do custodiante e **classificar** cada linha (aplicação/amortização/provento/taxa/tributo…) | **Alta** (é o cerne do dever da controladoria) |
| 3.2 | **Extrato do custodiante = dump da própria tabela** | `custodia/arquivos.php` exporta `movimentacoes` como CSV; as duas pontas leem a mesma tabela → nunca divergem; nada é reimportado | Fonte independente do custodiante + rotina de matching | **Alta** |
| 3.3 | **Conciliação 100% estática** | `conciliacao` vem do seed com valores hardcoded (412350, 18740, fator 0,83 — `gerar_seed.py:431-441`) ou `mt_rand` no simulador; `admin/conciliacao.php` só resolve/escala; nenhum SELECT compara somas | Batimento **computado** razão × extrato e carteira × posição | **Alta** |
| 3.4 | **Classificação por heurística de sinal** | Lançamento manual: entrada→'Aplicação', saída→'Taxa' (`lancamentos.php:52-53`) | Classificação explícita pelo operador / motor de regras | **Média** |
| 3.5 | **Resgate não separa tributo do líquido no caixa** | Debita `-$bruto` num único lançamento; IR/IOF só em `eventos_fiscais` (`passivo.php:131-134`) | Lançamentos distintos: líquido ao cotista vs. tributo à Receita | **Baixa-Média** |

## 4. Custódia e liquidação

| # | Achado | Hoje | Faltaria | Sev. |
|---|---|---|---|---|
| 4.1 | **DVP não atômico nem casado** | Caixa e posição em UPDATEs sequenciais sem transação; entrega física só se houver `boleta_id`; débito incondicional ao clicar (`instrucoes.php:85-119`) | Transação + entrega física obrigatória casada com a financeira | **Alta** |
| 4.2 | **`contas_centrais` sem saldo; Reservas/STR é rótulo** | Só número/titularidade/status (`schema.sql:133-141`); nenhuma liquidação debita conta do banco liquidante | Saldo/posição por conta×ativo + conta Reservas com saldo | **Alta** |
| 4.3 | **Fails decorativos; janela D+1/D+2 não temporal** | Status `Falha` só via seed; "reliquidar" repete o sucesso; `data_liquidacao` é exibida mas não bloqueia (`instrucoes.php:60-67,208-215`) | Geração real de falha + reversão; gate por data | **Média** |
| 4.4 | **Mensageria sem parsing/efeito** | "Processar" só muda status + log textual (`mensageria.php:17-31`); o `codigo` nunca é interpretado; liquidação **gera** a mensagem (fluxo invertido) | Parser por código que dispare liquidação/ajuste | **Média** |
| 4.5 | **Bonificação/Desdobramento sem efeito em quantidade** | Enum previsto, sem tratamento; creditaria caixa por `valor_total` | Ajuste de `quantidade` sem impacto de caixa | **Média** |

## 5. Módulos regulatórios e de risco (muitos são "casca")

| # | Achado | Hoje | Faltaria | Sev. |
|---|---|---|---|---|
| 5.1 | **Envios regulatórios fictícios** | "Enviar" gera protocolo aleatório + status; sem arquivo/CDA/conteúdo; prazos vêm do seed (`regulatorio.php:27`) | Geração de conteúdo (CDA/balancete/perfil) + prazos em d.u. + protocolo real | **Alta** |
| 5.2 | **KYC/Suitability/PLD inexistentes** | Cotista = nome + valor (`admin/passivo.php:53`); `kyc_status='Pendente'` é rótulo; etapa "Análise KYC/PLD" não executa nada | Validação documental, suitability, screening PLD (Res. CVM 50) | **Alta** |
| 5.3 | **Enquadramento só pós-trade** | `medir_regra` compara snapshot já formado (`helpers.php:182`); nenhum check no aceite de boleta; `enquadramento_eventos` são seed | Check **pré-trade** no aceite; geração automática de eventos (dever do gestor, art. 89) | **Alta** |
| 5.4 | **Classes/subclasses (Res. 175) não modeladas** | "Fundo" é entidade plana; `classe` é rótulo; subclasse só em comentário | Tabela de classes/subclasses, PL e cota por classe (patrimônio segregado) | **Média-Alta** |
| 5.5 | **IA/fraude é vitrine** | Regras R1–R7 são array de exibição, **nunca executadas**; alertas 100% de seed (`fraude.php:57`) | Motor de regras avaliando dados reais | **Média** |
| 5.6 | **Uploads não armazenados** | Só o nome do arquivo entra no checklist; "análise documental" aprova um nome (`cadastro.php:67,177`); CNPJ gerado fake | Armazenamento + validação de documentos | **Média** |
| 5.7 | **Assembleias simplificadas** | Quorum é texto livre; data +30d hardcoded sem prazo mínimo de convocação; sem votação/ata | Cálculo de quorum por cotas; convocação com prazo legal; ata | **Média** |
| 5.8 | **Batch de processamento fake** | 6 etapas sempre `'OK'` (`simulador.php:123-127`); "reprocessar" só flipa status (`processamento.php:18`) | Etapas que de fato precifiquem/concilie/calculem | **Média** |

---

## Roadmap de remediação (3 níveis)

**Nível 1 — Correção e integridade (o que um técnico chamaria de bug).** Não muda o escopo de simulação; conserta o que está *errado* ou perigoso:
- Envolver operações financeiras em **transações** (1.1) e adicionar **idempotência** (1.2);
- **CSRF** em todos os handlers de estado (1.3); **timezone** fixo + calendário de feriados (1.4);
- **Amortização** baixando principal do ativo e classificada corretamente (2.5);
- `come_cotas`/`cota_em` sem cair para 1.0 silenciosamente (2.2 parcial);
- Separar tributo do líquido no resgate (3.5).

**Nível 2 — Realismo do modelo (torna a simulação *correta*, não só ilustrativa).** Maior esforço:
- **Posição do custodiante como fonte independente** + **conciliação computada** (2.1, 3.1–3.3, 4.1–4.2);
- **Caixa e cotas versionados por data** → recálculo retroativo correto (2.2, 2.7);
- **Provisão diária de despesas** na cota (2.3);
- **Classificação de caixa** a partir de um extrato importado (3.1, 3.4).

**Nível 3 — Completar módulos "casca" e estrutura Res. 175.** Escopo de produto:
- Contabilidade de dupla entrada (2.4); MaM com fonte/comitê (2.6); performance com HWM (2.9);
- KYC/suitability/PLD (5.2); enquadramento pré-trade (5.3); classes/subclasses (5.4);
- Envios com conteúdo real (5.1); motor de fraude (5.5); uploads (5.6); assembleias (5.7).

**O que é legítimo deixar como simulação** (só rotular honestamente): MaM sintética, mensageria sem parsing, envios sem arquivo real, IA como vitrine — desde que o discurso de venda não os apresente como prontos.

---

*Auditoria conduzida em 4 frentes (caixa/conciliação, contabilidade/cota, custódia/liquidação, gaps estruturais). Todos os achados têm âncora em arquivo:linha no piloto. Documento de trabalho.*
