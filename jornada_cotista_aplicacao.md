# Jornada do cotista — como uma PF aplica R$ 100 mil num fundo aberto nosso

> A pergunta: "uma pessoa física tem R$ 100.000 e quer aplicar num dos fundos abertos — como ela
> faz?" Passo a passo real (modelo direto, sem distribuidor terceiro: o administrador/gestor
> autodistribuem — Res. CVM 21, art. 33), o que o piloto já cobre e o que falta.

## O processo exato, com linha do tempo

### Etapa 0 — Como ela chega (originação)
No nosso modelo a captação é **originada**, não de prateleira: ela é cliente do **gestor** do fundo
(ou veio do clube que virou fundo, ou de um assessor contratado pelo banco parceiro). O gestor
apresenta o fundo com a **lâmina/regulamento** (gerados pela plataforma).

### Etapa 1 — Onboarding (uma única vez, ~10 min, 100% eletrônico)
| O que | Base | Detalhe |
|---|---|---|
| **Cadastro** | Res. CVM 50, Anexo B | identificação, endereço, **capacidade financeira** (renda/patrimônio — os R$ 100 mil precisam ser compatíveis), FATCA/CRS |
| **Suitability** | Res. CVM 30 | perfil (conservador/moderado/arrojado) × público-alvo da classe; desalinhado exige termo de ciência |
| **KYC/PLD** | Res. CVM 50, arts. 11–17 | screening PEP/sanções; classificação de risco; monitoramento contínuo depois |
| **Adesão** | Res. CVM 175 | aceite eletrônico do regulamento com ciência de riscos, taxas e prazos de resgate |

*Quem faz na parceria:* o dever é de **quem distribui** (o parceiro, via nossa plataforma — o
ferramental de KYC/suitability/termo é nosso; a responsabilidade é dele).

### Etapa 2 — Ordem de aplicação (D+0, minutos)
Ela informa "quero aplicar R$ 100.000". Recebe as **instruções de liquidação**: os dados bancários
da **conta do fundo** (conta de titularidade do FUNDO, no custodiante/banco parceiro — o dinheiro
nunca passa por conta da Argus nem do gestor).

### Etapa 3 — Liquidação financeira (D+0)
Ela faz **TED/PIX da conta bancária DELA** para a conta do fundo. Regra de ouro de PLD: **mesma
titularidade** — recurso vindo de conta de terceiro é red flag do art. 20 da Res. 50 (o piloto tem
essa regra no motor antifraude, R12/R13). O back-office confere a entrada no extrato
(conciliação Caixa × Extrato).

### Etapa 4 — Cotização (D+0 ou conforme regulamento)
Confirmada a entrada, o valor **cotiza pela cota do dia** definida no regulamento (nosso padrão:
cota de fechamento de D+0). Exemplo com cota de R$ 1,257900:
`R$ 100.000 ÷ 1,257900 = 79.497,575324 cotas` — fração de 6 casas, sem arredondar contra o cotista.
O passivo registra a movimentação e o **custo médio** dela (base do IR futuro).

### Etapa 5 — Confirmação e vida de cotista (D+0 em diante)
- Aviso/nota da aplicação (data, valor, cota, quantidade).
- Acesso ao **portal do cotista** (no piloto: token) — posição, rentabilidade vs CDI, extrato.
- Dali em diante: **come-cotas** (mai/nov, se classe tributável), extrato mensal, informe de
  rendimentos anual; no **resgate**: cotiza pela regra do regulamento, retém **IOF (<30 dias) e IR
  regressivo** na fonte e paga o líquido — na conta **dela** (mesma titularidade de novo).

### Linha do tempo típica (fundo D+0/D+0)
`manhã: ordem + TED → tarde: batch confirma entrada → fechamento: cotiza → D+1: posição no portal`

## O que o PILOTO já cobre (e onde)

| Etapa | Tela | Status |
|---|---|---|
| Onboarding completo (cadastro, suitability × público-alvo, FATCA/CRS, termo, screening KYC/PLD) | `admin/onboarding_cotista.php` | ✅ |
| Lançamento da aplicação + cotização pela cota do dia | `admin/passivo.php` → `passivo_aplicar()` | ✅ |
| Resgate com IOF→IR na ordem correta + DARF como passivo | `admin/passivo.php` → `passivo_resgatar()` | ✅ |
| Come-cotas mai/nov por regime de tributação | `includes/passivo.php` | ✅ |
| Portal do cotista (posição, rentabilidade, extrato) | `cotista/painel.php` (token) | ✅ |
| PLD da movimentação (ida-e-volta, atipicidade, KYC pendente) | motor antifraude R8/R12/R13 | ✅ |
| **Porta de entrada: cotista SOLICITA e paga (TED/Pix)** | `cotista/movimentar.php` | ✅ **implementado (11/07/2026)** |
| **Confirmação de recebimento + validação de titularidade antes de cotizar** | `admin/passivo.php` (fila "Ordens do portal") | ✅ **implementado** |

## Implementado (11/07/2026) — o ciclo completo, com as regras reais

`cotista/movimentar.php` (token vinculado ao cotista) → **Aplicar**: valor + método (Pix com **QR
dinâmico simulado e txid** que casa o crédito com a ordem; ou TED com os dados da conta do FUNDO no
custodiante) → pagamento (simulado no piloto; com opção didática "CPF divergente") → fila da
administradora em `admin/passivo.php`: **valida a titularidade** (pagador = documento do cotista) →
**Confirmar e cotizar** (`passivo_aplicar`, cota do dia) ou **Devolver à origem** (divergente; evento
de PLD registrado) → posição reflete no painel. **Resgatar**: solicitação → processamento com IOF/IR
retidos → líquido pago **na conta cadastrada** (TED, mensagem STR0008 real). Conta bancária de mesma
titularidade agora é coletada no onboarding (`admin/onboarding_cotista.php`).

## Como funciona NA VIDA REAL — pesquisa (11/07/2026, fontes nos agentes)

- **Métodos**: TED e Pix **de conta de mesma titularidade** são o padrão (BTG/XP/Órama/Daycoval/
  Warren); **débito em conta** quando o administrador é banco (BB/Itaú/Sicredi); boleto é exceção
  (Warren). **Cartão de crédito NÃO existe** para subscrição de cotas (prática universal ancorada em
  PLD — não há vedação literal em norma); **espécie** não é proibida mas é red-flag (Circ. BCB 3.978:
  registro >R$ 2 mil, COAF ≥R$ 50 mil) e plataformas não têm caixa; **DOC foi extinto** (emissão até
  15/01/2024; desligado 29/02/2024 — Febraban).
- **Mesma titularidade**: não é artigo de lei — é a forma operacional de eliminar na origem as
  atipicidades do art. 20 da Res. CVM 50 (II, "i"/"j": pagamentos por terceiros). No Pix, a mensagem
  pacs.008 carrega o CPF do pagador e o **txid do QR dinâmico (26–35 chars)** concilia com a ordem —
  Warren estorna automaticamente Pix de CPF divergente; a Clear nem habilitou Pix enquanto não
  conseguia restringir titularidade.
- **Conta cadastrada**: o Anexo B da Res. 50 **não** exige dados bancários — a coleta é prática
  universal/regulamento (ex.: regulamento Itaú: resgate "na conta de titularidade do cotista
  registrada no cadastro"). Trocar a conta exige comprovante + confirmação fora de banda (callback)
  e 1–2 d.u. (XP/Rico). Conta conjunta vale com prova de cotitularidade.
- **Devolução**: TED de terceiro volta **no mesmo dia**; Pix pela **devolução do recebedor**
  (pacs.004, até 90 dias, total/parcial). O **MED** (Res. BCB 103) só cobre fraude/falha do PSP —
  **não** aporte errado.
- **Corte/cotização**: definido por fundo (regulamento; Res. 175 Anexo I exige os prazos
  pedido→conversão→pagamento); mercado típico 13h–14h30 (XP/Rico: 14h30). Após o corte → D+1
  (FAQ Santander Asset). Fundos de ações têm cotização de resgate mais longa (D+1 a D+30).
- **Registro externo? NÃO no modelo direto**: cotas são **escriturais** (Res. 175, arts. 14–15) e o
  único registro jurídico é o **livro do escriturador** — nenhum registro na B3/CVM por operação.
  B3 (Fundos21/conta-e-ordem) só entra com distribuidor terceiro não-escriturador (art. 34, §2º).
  Reportes: **informe diário agregado** à CVM (captações/resgates/nº cotistas — D+1), mensais
  (balancete/CDA/perfil — 10 d.u.), anuais (DFs auditadas — 90 dias); **e-Financeira semestral** à
  Receita por cotista (IN RFB 2219: saldos + movimentações mensais, limiares R$ 5 mil PF/15 mil PJ);
  PL/cota/captação diários à ANBIMA (HUB).

*Referências: Res. CVM 21 art. 33 (autodistribuição); Res. CVM 30 (suitability); Res. CVM 50
(cadastro/PLD); Res. CVM 175 (adesão, classe aberta sem registro de oferta); Lei 14.754 e
Dec. 6.306 (tributação — implementados no piloto).*

## Reformulado (11/07/2026) — de token para CONTA do cotista

O acesso por token foi aposentado. O portal agora funciona como uma plataforma real:

- **Conta própria** (`cotista_contas`: e-mail único + senha forte bcrypt). Autocadastro na entrada
  (nome, CPF, e-mail, senha validada, aceite LGPD) ou acesso criado pelo gestor com **senha
  provisória exibida uma vez**. Bloqueio pelo gestor derruba a sessão na hora.
- **Multi-fundo**: uma conta enxerga todas as posições vinculadas (`cotistas.conta_id`). Demo:
  Ricardo Alves (`ricardo.alves@email.com.br` / `Cotista@123`) tem posições nos fundos 1 e 2.
- **Transparência virou política GLOBAL por fundo** (`fundos.transparencia`: tempo real / defasada
  1m / 3m / não divulgada) — o gestor define em "Acessos & transparência" para TODOS os cotistas,
  não mais por token. Espelha a prática real: a CVM admite defasagem de até 90 dias na carteira.
  **A posição própria do cotista é sempre visível** (é dele); só a composição do fundo é regulada.
- **Páginas**: Início (consolidado com donut de alocação, movimentações, eventos fiscais,
  comunicados) · Painel por fundo · Movimentar (Pix QR/TED, resgate p/ conta cadastrada) ·
  Dúvidas (chamados presos à conta) · Meus dados (conta bancária auditada + troca de senha).
- No piloto tudo é simulado (pagamentos, validações); em produção entrariam confirmação de e-mail,
  2FA e validação de conta bancária por micro-depósito/callback.
