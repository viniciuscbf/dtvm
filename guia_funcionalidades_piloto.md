# Guia de Funcionalidades — Tudo que o Piloto Faz (v5)

> Tour completo pelos **4 portais** do piloto (`piloto/` — PHP + MySQL no XAMPP). Credenciais e instalação: `piloto/README.md`.
> Convenção do ciclo: o dia operacional é **D-1** (2026-07-03 no seed); senha única `demo123`; dados 100% simulados.

---

## 0. Landing e segurança

- **Landing** (`/piloto/`): apresentação institucional + entrada dos 4 portais. Não há atalhos de login.
- **Autenticação**: senhas bcrypt; sessão revalidada no banco a cada página; sessões de versões antigas invalidam sozinhas; gestor de fundo em abertura só enxerga a tela de status da abertura; cotista tem **conta própria** (e-mail + senha forte) com autocadastro ou acesso criado pelo gestor.

## 1. Portal do Gestor (`/gestor/`)

**Conta multi-fundo:** um login pode administrar vários fundos (estrutura FIC→master, subclasses). Seletor de fundo em foco na lateral. Demo: `gestor@auroracapital.com.br` tem o **Aurora RF master** e o **Aurora II FIC** (carteira = 100% cotas do master).

| Aba | O que faz |
|---|---|
| **Visão geral** | KPIs (PL, cota publicada, mês/ano, % CDI, cotistas, caixa), gráfico cota × CDI base-100 (1M–início, via API), composição por classe, alertas de enquadramento, banner de prévias pendentes |
| **Aprovação de cota** | O coração: prévia de D-1 calculada pela administradora → gestor **aprova** (publica; relatórios viram OFICIAIS) ou **rejeita com motivo**; histórico de fechamentos com versões (o Vetor traz v1 rejeitada + v2 reprocessada); aprovação retroativa republica os dias seguintes em cascata |
| **Boletar operação** | Compra/venda de qualquer ativo (CDB, debênture, ação, título público, CRI/CRA, cota de fundo) com quantidade, preço e contraparte; venda valida a posição; boleta segue para a mesa de custódia; acompanhamento por status (Enviada → Aceita → Liquidada / Rejeitada com motivo) |
| **Carteira** | Posição por data (22 dias retroativos), preço médio × MaM, fonte de preço, resultado não realizado, filtro por tipo; selo **PRÉVIA/OFICIAL**; downloads CSV/JSON/**PDF por classe de ativo** |
| **Caixa & Fluxo** | Saldo na data, entradas × saídas por mês, extrato filtrável, **previsão de caixa 60 dias** com saldo projetado e alerta de liquidez negativa |
| **Cotistas** | Lista com participação, concentração top 5 com alerta de liquidez, movimentação de cotas (aplicação/resgate com cotização/liquidação) |
| **Acessos & transparência** | Define a **política GLOBAL de transparência da carteira** do fundo (tempo real / defasada 1 mês / 3 meses / não divulgada — vale para todos os cotistas); cria **contas de acesso** por cotista (senha provisória exibida uma vez), bloqueia/reativa/desvincula |
| **Performance** | Rentabilidade por período × CDI, % CDI, volatilidade anualizada, drawdown máximo, matriz mensal, memória de cálculo da taxa de performance — tudo **em qualquer data retroativa** |
| **Relatórios** | Central única: **fundo × data (dias processados, com selo) × tipo (carteira, fluxo de caixa, cotistas, série da cota, performance) × formato (CSV/JSON/PDF)** |
| **Enquadramento** | Regras da política medidas da carteira real (barras de utilização), histórico de desenquadramentos com causa e prazo |
| **Assembleias** | Solicita AGE (modelos de pauta: alterar regulamento, taxas, subclasse…), acompanha convocação/resultado/quórum |
| **Comunicados** | Avisos da administradora + abertura de chamados com resposta rastreável |
| **Cadastro/Abertura** | Constituição de fundo (público, sem login): gestora + responsável + fundo + **checklist documental CVM 175** com upload; depois do login, tela de **status da abertura** com etapas e análise documento a documento (reenvio de rejeitados) |

## 2. Portal do Cotista (`/cotista/`)

- **Conta própria** (e-mail + senha forte, bcrypt): autocadastro na entrada (com aceite LGPD) ou acesso criado pelo gestor; bloqueio vale na hora (sessão revalidada no banco). Demo: `ricardo.alves@email.com.br` / `Cotista@123`.
- **Início (consolidado)**: patrimônio total, valor aplicado, resultado e rentabilidade somando **todos os fundos** da conta; donut de alocação; últimas movimentações; eventos fiscais (come-cotas/IR); comunicados dos fundos + gerais.
- **Painel por fundo**: posição própria SEMPRE visível (cotas × última cota publicada); evolução × benchmark e composição **por classe** seguem a transparência global do fundo (tempo real / -1m / -3m / **não divulgada** — nesse caso a carteira some mas a posição fica). Seletor entre os fundos da conta.
- **Movimentar**: aplicação via **Pix (QR dinâmico com txid)** ou TED da conta de titularidade; resgate só para a **conta cadastrada**; seletor de fundo; simulação de pagamento (inclusive CPF divergente p/ demonstrar a trava de titularidade).
- **Dúvidas**: chamados cotista↔gestor presos à conta, com escolha do fundo.
- **Meus dados**: cadastro, vínculos (suitability/KYC por fundo), **conta bancária** (grava em todos os vínculos, auditada, sujeita a validação) e **troca de senha** (política forte).

## 3. Portal da Administradora (`/admin/`)

| Aba | O que faz |
|---|---|
| **Painel geral** | PL total, receita do mês com split 25/75, batch de hoje, cotas D-1 com o gestor/rejeitadas, pendências; mapa de saúde com semáforo e links profundos; feed de atividade |
| **Processamento & Cota** | Esteira do batch por fundo (Posição→Preços→Caixa→Conciliação→Cota→ANBIMA) com **reprocessar etapa**; fechamento D-1: **gerar prévia** (calcula cota do snapshot), acompanhar decisão do gestor, **reabrir**; histórico com todas as versões e selo PRÉVIA/OFICIAL; log técnico |
| **Lançamentos & Ajustes** | Correção de **preço/quantidade por ativo** (com justificativa) e **lançamentos de caixa** (provento, taxa, evento) em qualquer data com snapshot; **recalcular cota** gera nova versão de prévia; retroativo avisa da republicação em cascata; trilha completa de lançamentos |
| **Custódia & Liquidação** | Visão da administradora sobre a custódia: fila de liquidação, eventos corporativos (provisionar→liquidar), espelho da posição custodiada com batimento (divergência NORD3 em destaque) |
| **Conciliação** | 3 frentes (posição × custodiante, operações × gestor, caixa × extrato); divergências classificadas (timing/erro/**suspeita**) com resolver (justificativa) ou **escalar ao compliance** (vira alerta de IA); trilha de auditoria |
| **Regulatório CVM** | **Envios**: informe diário (bloqueado até a cota ser aprovada), balancete/CDA/perfil mensal (prazo 10 d.u.), DFs, estatísticas ANBIMA — com protocolo e reenvio de erro; **Ofícios recebidos** com prazo e resposta protocolada (seed: ofício cobrando o desenquadramento do Atlas); **Assembleias**: convocar solicitações do gestor, registrar resultado/quórum |
| **Aberturas de fundos** | Status de todos os fundos; por fundo em abertura: etapas operacionais + análise documental (aprovar/rejeitar com motivo) + **Lançar fundo** (só habilita com checklist completo) |
| **Carteiras** | Exposição agregada da casa por classe/ativo, carteira por fundo e data com desvio vs referência, fila do comitê de precificação, exports |
| **Pendências** | Fila única de tudo que precisa de humano: erros de batch, cotas rejeitadas, divergências, alertas IA, boletas aguardando aceite, liquidações, eventos, envios atrasados, ofícios, assembleias, preços de comitê, chamados (com resposta inline); comentários operacionais por fundo |
| **IA · Fraude** | Alertas priorizados com explicação em linguagem natural + evidência; ações (revisar/escalar/falso positivo) com trilha; **grafo de partes relacionadas** (vínculo suspeito tracejado); aba "Como funciona" documenta as 14 regras (R1–R14) em 7 categorias, com limiar e base legal/caso real |
| **Repasses** | Apuração 0,08% a.a. com piso R$ 100 por fundo/competência, split **25% banco / 75%**, evolução mensal, instruções de pagamento |
| **Fundos & Clientes** | Cadastro consultivo (fundos, taxas — gestão/performance definidas pelo gestor no regulamento), usuários com KYC, supervisão das **contas do portal do cotista** (titular, e-mail, fundos vinculados, status) |

## 4. Portal do Banco Custodiante (`/custodia/`)

| Aba | O que faz |
|---|---|
| **Painel da custódia** | Posição custodiada por central (SELIC / B3 Depositária / B3 Balcão), **contas individualizadas por fundo** + conta própria/Reservas do banco, resumo da mensageria e da fila operacional, divergências de batimento |
| **Mensageria SPB** | Fila RSFN simulada (códigos estilo catálogo: SEL1052, STR0008, MOV0001…) com filtro por central, processar/reprocessar erro — reflete no log da administradora |
| **Instruções & Liquidação** | **Aceite de boletas dos gestores** (gera instrução D+1 renda fixa / D+2 bolsa + mensagem SPB) ou rejeição com motivo; liquidação **DVP**: confirma → caixa do fundo movimenta, confirmação entra na mensageria e, se veio de boleta, **a posição entra/sai da carteira** (preço médio ponderado / baixa / inserção de ativo novo); eventos corporativos (provisionar → creditar) |
| **Arquivos & Extratos** | Gera os **CSVs diários reais**: posição custodiada (com central de guarda e preço de referência — insumo do batimento) e extrato da conta do fundo — por fundo e data |

## 5. Os fios que atravessam tudo (para a demo)

1. **Boleta → cota**: gestor boleta CDB → custódia aceita → liquida DVP → CDB na carteira + caixa debitado → admin recalcula → gestor aprova → CVM recebe o informe.
2. **Rejeição → reprocesso**: Vetor v1 rejeitada (preço DEB VALE29) → lançamento de ajuste → v2 → aprovação.
3. **Fraude ponta a ponta**: NORD3 marcado +8,2% e sem lastro no custodiante → batch trava → conciliação diverge → alertas R1/R4 → desenquadramento → **ofício da CVM** cobrando → tudo tratável com trilha.
4. **PRÉVIA → OFICIAL**: dia processado libera relatórios na hora como prévia; a aprovação do gestor os oficializa.
5. **Multi-fundo**: a mesma conta alterna master ↔ FIC no seletor lateral.
