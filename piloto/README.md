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
   → importe `sql/schema.sql` (26+5 tabelas) e depois `sql/seed.sql`.
4. Confira `config/db.php` (padrão XAMPP: `localhost`, `root`, senha vazia).
5. Acesse **http://localhost/piloto/**.

## Os três portais (sem atalhos de login)

| Portal | Entrada | Conteúdo |
|---|---|---|
| **Gestor** | e-mail + senha (bcrypt) | **boletagem de operações**, aprovação de cota D-1, central de relatórios (fundo × data × tipo × formato), acessos de cotistas, assembleias, constituição de fundo com checklist CVM 175 — conta pode ter **vários fundos** (FIC/master) com seletor na lateral |
| **Cotista** | apenas token UUID | evolução vs benchmark + carteira por classe, na defasagem do token |
| **Administradora** | e-mail + senha | processamento & cota, lançamentos, custódia & liquidação, conciliação, regulatório CVM, aberturas, IA, repasses |
| **Banco Custodiante** | e-mail + senha | mesa de custódia: contas segregadas nas centrais (SELIC/B3), mensageria RSFN/SPB, liquidação DVP, arquivos de posição/extrato |

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

**O que ainda NÃO representa** (fica para a fase de produção): come-cotas/IR do cotista,
distribuição (onboarding de cotistas com suitability), integrações reais (SELIC/B3/ANBIMA/custodiante),
motor homologado de precificação, tesouraria do banco, PLD transacional em tempo real.

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

## Avisos

Piloto local: sem HTTPS/hardening, uploads guardam só o nome do arquivo, "IA" é motor de regras
documentado. Não usar em produção.
