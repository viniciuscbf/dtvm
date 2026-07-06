# Piloto v3 — Custódia e Administração Fiduciária (PHP + MySQL, XAMPP)

Réplica funcional do sistema que o banco terá: **quatro portais separados** e o **ciclo operacional
completo** de custódia + administração fiduciária — batch diário, prévia de cota D-1 com aprovação
do gestor, **boletagem de operações** (gestor boleta → custódia aceita → liquidação DVP reflete na
carteira), lançamentos e reprocessamento retroativo, relatórios disponíveis assim que o dia é
processado (**PRÉVIA** antes da aprovação, **OFICIAL** depois), custódia (mensageria SPB, liquidação
e eventos corporativos), regulatório CVM/ANBIMA (envios, ofícios, assembleias), contas multi-fundo
(FIC/master), tokens de cotista e PDF por classe de ativo.

## Instalação no XAMPP (5 minutos)

1. Instale o XAMPP (https://www.apachefriends.org) e inicie **Apache** e **MySQL**.
2. Copie esta pasta `piloto/` para `C:\xampp\htdocs\piloto\`.
3. `http://localhost/phpmyadmin` → crie o banco **administradora** (utf8mb4_unicode_ci)
   → importe `sql/schema.sql` e depois `sql/seed.sql`.
   _(Já tinha uma base de uma versão anterior? aplique também `sql/hardening.sql` (tabela `auditoria`) e `sql/passivo.sql` (passivo do cotista + tributação).)_
4. Confira `config/db.php` (padrão XAMPP: `localhost`, `root`, senha vazia).
5. Acesse **http://localhost/piloto/**.

## Os três portais (sem atalhos de login)

| Portal | Entrada | Conteúdo |
|---|---|---|
| **Gestor** | e-mail + senha (bcrypt) | **boletagem de operações**, aprovação de cota D-1, central de relatórios (fundo × data × tipo × formato), acessos de cotistas, assembleias, constituição de fundo com checklist CVM 175 — conta pode ter **vários fundos** (FIC/master) com seletor na lateral |
| **Cotista** | apenas token UUID | evolução vs benchmark + carteira por classe, na defasagem do token |
| **Administradora** | e-mail + senha | processamento & cota, lançamentos, custódia & liquidação, conciliação, regulatório CVM, **passivo & tributação (come-cotas/IR/IOF)**, aberturas, IA, repasses |
| **Banco Custodiante** | e-mail + senha | mesa de custódia: contas segregadas nas centrais (SELIC/B3), mensageria RSFN/SPB, liquidação DVP, arquivos de posição/extrato, **trilha de auditoria** |

Sessões antigas são invalidadas automaticamente (revalidação no banco a cada página).

## Credenciais demo (senha única: `demo123`)

| Perfil | E-mail |
|---|---|
| Administradora (**Vinicius Fernandes**) | admin@administradora.com.br |
| Gestor Aurora (2 fundos: RF master + FIC — prévia D-1 a aprovar e boleta pendente) | gestor@auroracapital.com.br |
| Gestor Horizonte FIA | gestor@horizonteinvest.com.br |
| Gestor Atlas (batch travado + ofício CVM) | gestor@atlascapital.com.br |
| Gestor Nova Fronteira (**fundo em abertura**) | gestor@novafronteira.com.br |
| Mesa de Custódia do banco (Paulo Siqueira) | custodia@bancoparceiro.com.br |

**Tokens de cotista (Aurora RF):** tempo real `3f2a1c9e-8b47-4d10-9e2f-6a5d4c3b2a19` ·
1 mês `7c9e4b2a-1f3d-4e8c-a6b5-0d9f8e7c6b5a` · revogado `a1b2c3d4-e5f6-4789-8abc-def012345678`

## Simulador Master (painel de controle) — `/simulador/`

Segundo site, à parte da plataforma, para **conduzir a simulação** (senha própria — demo `master123`, independente dos portais). Acesse por **http://localhost/piloto/simulador/** ou pelo link no rodapé da landing.

- **Passar de dia** — o "hoje" é hipotético; o master avança para o próximo dia útil, gerando novos preços (renda variável varia, renda fixa acretem CDI), recalculando cota/PL e montando a esteira de processamento de todos os fundos;
- **Reset** — recria o schema e recarrega o `seed.sql` (volta ao estado de fábrica; apaga aplicações, resgates, tributos e avanços de dia);
- **Injetar eventos** — recebimento no caixa do fundo (aporte/provento/vencimento), boleta de compra (→ Mesa de Custódia), mensagem RSFN/SPB (→ custódia), divergência de conciliação e ofício da CVM (→ administradora).

A data do simulador vive na tabela `sim_estado` (criada automaticamente no primeiro acesso — sem migração).

> **Nota — contas bancárias:** o piloto **não modela conta bancária real** (agência/conta, extrato, TED/PIX). O dinheiro do fundo é o campo `caixa_atual` + o livro `movimentacoes`. O "recebimento" do simulador credita esse caixa. Um módulo de tesouraria/conta bancária pode ser adicionado depois.

## O que o piloto representa (mapa das funções reais)

**Administração fiduciária:** cálculo e prévia da cota D-1 → aprovação do gestor → publicação;
lançamentos e reprocessamento (inclusive retroativo, com republicação em cascata); enquadramento;
conciliação (posição × custodiante, operações × gestor, caixa × extrato); **envios à CVM/ANBIMA**
(informe diário em 1 d.u. — bloqueado até a cota ser aprovada —, balancete, CDA e perfil mensal em
10 d.u., DFs anuais, estatísticas ANBIMA, com protocolo); **ofícios recebidos do regulador** com
prazo e resposta protocolada; **assembleias de cotistas** (gestor solicita alteração de regulamento,
administradora convoca, conduz e registra resultado); taxas e repasses com split 25/75.

**Custódia (portal próprio do banco — Res. CVM 32):** contas individualizadas por fundo nas centrais
(SELIC para títulos públicos, B3 Depositária para ações, B3 Balcão para crédito privado, conta
Reservas/STR do banco), **mensageria RSFN/SPB simulada** (códigos estilo catálogo SEL/STR, com
processamento e reprocesso de erro), **fila de liquidação física/financeira DVP** (D+1/D+2 —
confirmar movimenta o caixa e gera confirmação na mensageria), **eventos corporativos**
(anunciar → provisionar → creditar) e **geração dos arquivos diários de posição/extrato** (CSVs
reais) que alimentam a conciliação da administradora. A mesma operação aparece nas duas pontas:
mesa de custódia executa, administradora concilia.

**Passivo do cotista & tributação (novo, simulado):** aplicações e resgates com cotização pela cota vigente,
**IR regressivo** (22,5%→15%) e **IOF** (&lt; 30 dias) no resgate, e **come-cotas** de maio/novembro (15%/20%,
com *step-up* de base; fundo de ações isento), gerando eventos fiscais e informe de rendimentos (CSV) — em
**Administradora → Passivo & Tributação**. É simulação de demonstração (ganho apurado pela valorização da cota).

**O que ainda NÃO representa** (fica para a fase de produção): provisão diária de IR na cota e apuração
contábil de acrual (COSIF), geração de DARF e obrigações acessórias reais (DIRF/e-Financeira), onboarding
de cotistas com suitability/PLD completo, integrações reais (SELIC/B3/ANBIMA/custodiante), motor homologado
de precificação, tesouraria do banco, PLD transacional em tempo real.

## Roteiro de demo (25 min)

1. Landing → 4 portais; tente entrar sem credencial (não dá);
2. Nova Fronteira → status da abertura; Admin → Aberturas → aprove docs e **lance o fundo**;
3. Gestor Aurora (repare no **seletor de fundos**: master + FIC) → confira a carteira (PRÉVIA) →
   **aprove a cota D-1** (vira OFICIAL); no Vetor, veja a v1 **rejeitada** e a v2 reenviada;
4. Gestor → **Boletar operação**: já há uma boleta de CDB pendente — vá à **Mesa de Custódia**,
   aceite (gera D+1), liquide DVP e veja o CDB **entrar na carteira** e no caixa; admin reprocessa a cota;
5. Admin → Lançamentos: ajuste preço → recalcule → nova prévia versionada;
6. Admin → **Regulatório**: envie o informe diário (só passa com cota aprovada), responda o
   **ofício da CVM** sobre o desenquadramento do Atlas, convoque a assembleia solicitada pelo gestor;
7. Gestor → **Relatórios**: fundo × data × tipo × formato (carteira/caixa/cotistas/cota, CSV/JSON/PDF);
8. Feche em Repasses (split 25/75).

## Regenerar dados

```
python3 sql/gerar_seed.py > sql/seed.sql
```

## Endurecimento de segurança (suporte à Res. CVM 32)

O portal de custódia já implementa controles reais que sustentam o dossiê de autorização:

- **Trilha de auditoria append-only** (`auditoria`) — cada login, aceite/rejeição de boleta, liquidação
  DVP, tratamento de evento e processamento de mensagem gera registro com ator, horário e origem (IP).
  Visível em **Custódia → Trilha de auditoria** (art. 13, V);
- **Proteção CSRF** em todos os formulários da custódia (token por sessão, validação em tempo constante);
- **Cabeçalhos de segurança** (CSP, X-Frame-Options, X-Content-Type-Options, Referrer/Permissions-Policy);
- **Hardening de sessão** — cookie `HttpOnly`/`SameSite=Lax` (e `Secure` sob HTTPS), timeout por
  inatividade (30 min) e rotação de id na autenticação.

Detalhe em `../custodiante/politica_confidencialidade_seguranca.md` e `../custodiante/dossie_estrutura_operacional_tecnologica.md`.

## Realismo e integridade (v2 — remediação da auditoria)

Rodada de correções após a auditoria (`../relatorio_auditoria_piloto.md`). As tabelas novas são **auto-criadas** no primeiro acesso (sem migração manual).

**Recursos novos (fiéis a plataformas reais de DTVM/controladoria):**
- **Catálogo de ativos + boletagem** — o gestor só boleta ativos **cadastrados no catálogo** (`ativos_catalogo`); se faltar, **solicita o cadastro** (Gestor → Catálogo de ativos), que a administradora aprova (Admin → Base de instrumentos). Reflete o mercado: base de instrumentos mantida pela administradora, alimentada por feeds B3/ANBIMA.
- **Enquadramento PRÉ-TRADE** — a boleta é **validada contra os limites do fundo antes do envio** (dever do gestor, Res. 175 art. 89); viola o mandato → boleta barrada.
- **Suporte / tickets** — Gestor abre chamados por tema (cadastro de ativo, conciliação, cota, resgate, tributação, enquadramento, documento…) e a administradora responde (Gestor/Admin → Suporte).
- **Posição do custodiante independente + conciliação real** — o "passar de dia" gera uma **fonte de posição separada** (`posicao_custodiante`) e **computa** as divergências carteira × custodiante (não são mais fixas do seed).
- **Provisão diária de despesas** — o avanço de dia provisiona pro-rata a taxa de adm+gestão+custódia, **reduzindo a cota** (antes a cota era bruta de taxas).

**Integridade (correções de bug):**
- **Transações** (`com_transacao`) em toda operação financeira (passivo, liquidação DVP, publicação de cota, lançamentos, boleta) — falha no meio **reverte tudo**;
- **Idempotência** (nonce) contra duplo-clique em aplicação/resgate/boleta;
- **CSRF** validado em **todos** os handlers que movem estado (antes faltava em ~8);
- **Fuso** fixo (America/Sao_Paulo) + **calendário de feriados** B3 (antes só pulava fim de semana);
- **Amortização** agora **devolve principal e baixa o PU do ativo** (antes creditava como "Provento" sem baixar o ativo); tributo no resgate **separado** do líquido no caixa.

**Núcleo funcional (Balde 2):**
- **Versionamento por data** — caixa e cotas **da data** (não de hoje) → recálculo retroativo correto;
- **Contabilidade de dupla entrada** — plano de contas, razão, diário e **balancete que fecha** (Ativo = Passivo + PL) em *Admin → Contabilidade*;
- **MaM homologada** — feed de preços **independente** (`precos_mercado`) + **Comitê de Precificação** (homologa preço com ata) + cascata ANBIMA→B3→Comitê, em *Admin → Precificação*;
- **Onboarding com KYC / suitability / PLD** (screening simulado) + FATCA/CRS + termo de adesão, em *Admin → Onboarding de cotistas*;
- **Classes / subclasses** (Res. 175: público-alvo, taxas, prazos) em *Admin → Classes & Subclasses* — segregação plena de patrimônio por classe segue como evolução.

## Avisos

Piloto local: por padrão roda sem HTTPS (o cookie `Secure` ativa sozinho quando houver TLS); uploads
guardam só o nome do arquivo; "IA" é motor de regras documentado. Itens ainda de produção: MFA,
imutabilidade externa dos logs (WORM), integrações reais (SELIC/B3/ANBIMA). Não usar em produção como está.
