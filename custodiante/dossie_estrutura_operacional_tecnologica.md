# Dossiê de Estrutura Operacional e Tecnológica da Atividade de Custódia

> **Anexo técnico ao pedido de autorização — Resolução CVM nº 32/2021, art. 6º e Anexo A, art. 1º, III e IV**
> **Requerente:** `[RAZÃO SOCIAL DA INSTITUIÇÃO]` — CNPJ `[__]` — instituição financeira de menor porte, já autorizada a funcionar pelo Banco Central do Brasil, que **constitui uma nova área de custódia** (ver §1.0 quanto à premissa de elegibilidade do art. 4º).
> **Documento de trabalho — v1.0.** Descreve a capacidade organizacional, técnica, operacional e financeira do requerente para prestar serviços de custódia de valores mobiliários a investidores (fundos de investimento), com a qualidade e a confidencialidade exigidas pelo art. 5º da Resolução.
> Os campos entre `[colchetes]` devem ser preenchidos pelo banco antes do protocolo.

---

## 1. Objeto e escopo do serviço

### 1.0 Perfil do requerente e premissa de elegibilidade (art. 4º)
O requerente é uma **instituição financeira de menor porte** — banco comercial, múltiplo ou de investimento de pequeno porte, caixa, CTVM ou DTVM — **já autorizada a funcionar pelo Banco Central do Brasil**, que **ainda não possui área de custódia** e a constitui, do zero, no âmbito deste projeto. A dimensão da estrutura (sistemas, equipe, contas nas centrais) é **proporcional ao porte** e escala conforme o volume de fundos custodiados (§2.4).

> **Premissa de elegibilidade.** A autorização de custódia da Res. CVM 32 pressupõe que o requerente **já seja** uma das entidades do art. 4º. Se a instituição-alvo ainda não for um tipo elegível, a obtenção prévia da autorização de funcionamento no BCB para o tipo societário adequado é **etapa anterior e independente**, fora do escopo deste dossiê.

### 1.1 Objeto
Prestação de serviço de **custódia de valores mobiliários para investidores** (art. 2º, §1º e §2º, I, da Res. CVM 32), compreendendo a **conservação, o controle e a conciliação** das posições de titularidade dos investidores, mantidas em contas de custódia individualizadas, e o **tratamento das instruções de movimentação e dos eventos** relativos aos ativos custodiados.

Público-alvo inicial: **fundos de investimento** administrados por estrutura de administração fiduciária (parceira ou da própria instituição, esta em etapa posterior do projeto), sob a Res. CVM 175.

### 1.2 Escopo incluído
- Guarda escritural de ativos em contas individualizadas por fundo nas infraestruturas de mercado (SELIC, B3 Central Depositária, B3 Balcão);
- Liquidação física e financeira das operações por **entrega contra pagamento (DVP)**;
- Tratamento de **eventos corporativos** (dividendos, JCP, cupons, amortizações, bonificações, desdobramentos);
- Captura e processamento da **mensageria** das centrais (via RSFN) e geração dos **arquivos diários** de posição e extrato;
- **Conciliação diária** entre as contas de custódia e as posições dos depositários centrais (art. 13, §1º, I).

### 1.3 Escopo excluído (declaração expressa)
- **Guarda física de valores mobiliários** (art. 5º, §3º e art. 13, §2º): **não aplicável** — a atividade abrange exclusivamente ativos escriturais e depósito centralizado. Caso venha a ser incluída, será objeto de aditamento do pedido.
- **Custódia para emissores** (art. 2º, §2º, II): fora do escopo inicial.

---

## 2. Arquitetura tecnológica

### 2.1 Visão geral
A plataforma de custódia é a **retaguarda tecnológica do banco custodiante**, segregada logicamente da plataforma de administração fiduciária (controladoria de fundos). No piloto (`piloto/`), essa retaguarda é o portal `/custodia/`, com perfil de acesso próprio (`custodia`), distinto do perfil da administradora (`admin`).

```
                 RSFN (SPB)                         Infraestruturas de mercado
   SELIC ─┐   B3 Depositária ─┐   B3 Balcão ─┐   STR/Reservas ─┐
          │                   │              │                 │
          ▼                   ▼              ▼                 ▼
     ┌───────────────────────────────────────────────────────────┐
     │  MENSAGERIA (captura)      →  mensagens_spb                 │
     │  MOTOR DE CUSTÓDIA:                                         │
     │   • Contas segregadas       →  contas_centrais             │
     │   • Liquidação DVP          →  liquidacoes                 │
     │   • Eventos corporativos    →  eventos_corporativos        │
     │   • Arquivos posição/extrato→  CSV (custodia/arquivos.php) │
     └───────────────────────────────────────────────────────────┘
                              │ arquivos diários (SFTP/API)
                              ▼
             ADMINISTRADORA (controladoria) → conciliação → cota
```

### 2.2 Componentes (referência à plataforma-piloto)
| Componente | Função (Res. 32) | Implementação-piloto |
|---|---|---|
| **Mapa de contas segregadas** | Art. 12; Anexo A III "b" | `contas_centrais` — conta por fundo em cada central + conta própria do banco |
| **Captura de mensageria** | Art. 5º, II; Art. 13, V | `mensagens_spb` (catálogo estilo SEL/STR/B3) com status e reprocessamento (`custodia/mensageria.php`) |
| **Liquidação DVP** | Art. 13, III | `liquidacoes` — ciclo D+1 (RF/títulos públicos) / D+2 (RV); confirmação move caixa e gera mensagem de confirmação (`custodia/instrucoes.php`) |
| **Eventos corporativos** | Art. 13, III | `eventos_corporativos` — fluxo anunciar → provisionar → creditar |
| **Arquivos de posição/extrato** | Art. 14 | `custodia/arquivos.php` — CSV de posição custodiada e extrato de conta |
| **Conciliação** | Art. 13, §1º, I | `conciliacao` (origem *Posição × Custodiante*) — batimento com a controladoria |
| **Controle de acesso e trilha** | Art. 5º, I; Art. 13, V | `includes/auth.php` (bcrypt, perfis, revalidação em banco); `log_processamento`; trilha em `conciliacao`/`lancamentos` |

### 2.3 Pilha tecnológica e ambientes
- **Aplicação:** `[stack — no piloto: PHP 8 + MySQL/MariaDB]`; em produção, ambiente do banco.
- **Ambientes segregados:** desenvolvimento, homologação e produção separados (Anexo A III "a"/"c").
- **Infraestrutura:** `[nuvem/on-prem do banco]` com alta disponibilidade; criptografia em trânsito (TLS) e em repouso; backup automatizado (ver `manual_contingencia_continuidade.md`).
- **Autenticação e acesso:** hash de senha (bcrypt), sessão com revalidação de perfil a cada requisição, MFA a implementar em produção (ver `politica_confidencialidade_seguranca.md`).
- **Endurecimento aplicado** (`piloto/includes/seguranca.php`): trilha de auditoria append-only (`auditoria`), proteção CSRF nos formulários da custódia, cabeçalhos de segurança (CSP, X-Frame-Options, nosniff, Referrer/Permissions-Policy) e hardening de sessão (cookie `HttpOnly`/`SameSite=Lax`, `Secure` sob HTTPS, timeout por inatividade de 30 min). Pendentes de produção (🟩): MFA e imutabilidade externa dos logs (WORM).

### 2.4 Dimensionamento e escala (art. 5º, §1º)
Os sistemas são compatíveis com o volume previsto: início com `[N]` fundos e crescimento planejado até `[M]`. A arquitetura processa o ciclo diário em lote (batch) por fundo, com capacidade de reprocessamento retroativo e versionamento (ver `fechamentos.versao` na controladoria). O dimensionamento de links RSFN, contas nas centrais e retaguarda acompanha as faixas de volume descritas na `../planilha_custos.md`.

---

## 3. Fluxos operacionais nucleares

### 3.1 Guarda segregada (art. 12)
Cada fundo possui **conta de custódia individualizada em seu nome** em cada central onde detém ativos (SELIC para títulos públicos; B3 Depositária para renda variável; B3 Balcão para crédito privado). A conta própria do banco é mantida **separada** das contas dos fundos. Os ativos dos fundos **nunca se confundem** com os do custodiante. No piloto, a segregação é representada por `contas_centrais` com `fundo_id` por fundo e `fundo_id = NULL` para a conta própria.

### 3.2 Liquidação por entrega contra pagamento — DVP (art. 13, III)
1. A ordem (boleta) do gestor chega autenticada; a custódia **valida e aceita** (ou rejeita com motivo);
2. O aceite gera **instrução de liquidação** com data conforme o ciclo do ativo (D+1 títulos públicos/RF; D+2 renda variável);
3. Na liquidação, ativo e financeiro mudam de mãos **simultaneamente** (DVP): confirma-se a movimentação, atualiza-se o caixa da conta do fundo e registra-se a **confirmação na mensageria**;
4. A posição resultante alimenta a conciliação e a controladoria.

### 3.3 Eventos corporativos (art. 13, III)
Fluxo **anunciar → provisionar → creditar**: o evento é anunciado (fonte da central/emissor), provisionado (o direito passa a refletir na posição/PL) e creditado na data de pagamento, movimentando o caixa da conta do fundo. Cobre dividendos, JCP, cupons, amortizações, bonificações e desdobramentos.

### 3.4 Mensageria RSFN/SPB (art. 5º, II; art. 13, V)
A retaguarda mantém uma **fila de mensagens** das centrais (padrão de catálogo SPB — famílias SEL, STR e mensageria/arquivos B3), com estados *Recebida → Processada → Erro* e **reprocessamento** de falhas. Cada processamento é registrado (autor, horário), atendendo ao dever de registro de acessos, erros e incidentes.

### 3.5 Informação ao contratante (art. 14)
Geração diária de **arquivo de posição custodiada** (por ativo, central e preço de referência) e **extrato de conta** (lançamentos financeiros), disponibilizados à administradora/investidor. Relatórios periódicos observam os prazos do art. 14 (mensal até o 10º dia do mês seguinte; anual até o fim de fevereiro).

### 3.6 Conciliação diária (art. 13, §1º, I)
Batimento diário entre a posição das contas de custódia e as posições fornecidas pelos depositários centrais, com classificação de divergências (timing/erro/suspeita), trilha de resolução e escalonamento ao compliance. Detalhado em `manual_conciliacao.md`.

---

## 4. Governança, segregação de funções e pessoas

### 4.1 Diretores estatutários (art. 17)
Serão designados, em ata, **dois diretores estatutários** — funções **não cumuláveis** (art. 17, §2º):
- **Diretor responsável pelo cumprimento da Res. CVM 32** (art. 17, I): `[nome]`;
- **Diretor responsável pela supervisão dos controles internos** (art. 17, II): `[nome]`.

A designação e eventuais substituições serão comunicadas à CVM, aos depositários centrais e às entidades administradoras de mercado em até **7 dias úteis** (art. 17, §1º). O diretor do inciso II encaminhará ao órgão de administração, até o último dia útil de **abril**, o relatório anual de controles internos (art. 18).

### 4.2 Organograma da área de custódia (Anexo A, art. 1º, IV)
```
Diretoria (art. 17, I e II — segregadas)
 ├─ Mesa de Custódia / Retaguarda (2–3): liquidação, eventos, mensageria, arquivos
 ├─ Controladoria (segregada — administração fiduciária): concilia e reflete na cota
 ├─ Compliance / PLD (1–2): controles internos, sigilo, terceiros
 └─ Tecnologia (2+): sistemas, segurança, contingência
```
A **segregação de funções** garante que quem **custodia** (mesa) não é quem **administra/contabiliza** (controladoria) — um confere o outro, atendendo ao regime de segregação exigido no Anexo A, art. 1º, III "e" e IV.

### 4.3 Recursos humanos e capacitação (art. 13, VII)
Equipe mínima descrita acima, com plano de **certificação** dos profissionais conforme função (ex.: certificações de mercado aplicáveis) e treinamento periódico (Anexo A III "e").

---

## 5. Controles internos, confidencialidade e continuidade

### 5.1 Regras e controles internos (art. 16)
Todas as rotinas são regidas por **manuais escritos** (art. 16, §1º, I), passíveis de verificação e disponíveis à CVM, aos depositários e à autorregulação (art. 16, §1º, II–III). Os controles internos verificam a aplicação e a eficácia das regras (art. 16, II), com revisão periódica e indicadores.

### 5.2 Confidencialidade e sigilo (art. 5º, I; art. 13, §1º, II)
Acesso segregado por perfil; sigilo sobre características e quantidades dos VM dos investidores; política de segurança da informação, criptografia, gestão de acessos e logs. Detalhado em `politica_confidencialidade_seguranca.md`.

### 5.3 Plano de contingência (art. 13, IX; Anexo A III "f")
Plano de continuidade de negócios com RTO/RPO definidos, backup, recuperação de arquivos e banco de dados, e teste periódico documentado. Detalhado em `manual_contingencia_continuidade.md`.

### 5.4 Auditoria interna (arts. 18 e 20)
Manutenção de **estrutura de auditoria interna** cobrindo a custódia; relatórios mantidos atualizados e à disposição da CVM; atendimento a auditorias extraordinárias. **Observação:** a Res. CVM 32 exige auditoria **interna** — não exige relatório de auditor independente/asseguração sobre a custódia (a auditoria independente do mercado/ANBIMA é boa prática, não requisito da norma).

### 5.5 Terceirização e apoio de estruturador (art. 19)
A **atividade regulada de custódia** só pode ser contratada de quem também seja **custodiante autorizado** (art. 19, §1º); a contratação de terceiros **não altera as responsabilidades** da instituição (art. 19, §2º) e o contrato preverá **responsabilidade solidária** (art. 19, §3º), com controles para mitigar conflitos de interesse (art. 19, §4º). Já o **licenciamento da plataforma** (contratos de software — Anexo A, III "g") e os **serviços de estruturação/consultoria e suporte acessório** de um parceiro são **lícitos** e **não constituem** a atividade regulada — desde que **diretores, responsabilidade e capacidade nuclear permaneçam na instituição** (arts. 5º e 17). Os arranjos de papéis possíveis (parceiro externo ou estruturador interno) estão detalhados em `modelo_parceria_papeis.md`.

### 5.6 Guarda de documentos (art. 22)
Retenção de documentos e informações por, no mínimo, **5 anos**, admitida a digitalização; logs e trilhas preservados conforme política de retenção.

---

## 6. Contrato de prestação de serviço (art. 10)
A prestação é formalizada por **contrato específico** (minuta em `minuta_contrato_custodia.md`), dispondo, no mínimo, sobre transmissão de ordens, guarda física (quando aplicável), contratação de terceiros e descrição de riscos (art. 10, I–IV), além das regras de transferência/portabilidade (art. 11, com a redação da Res. 209/24). O modelo de contrato instrui o pedido de autorização (Anexo A, art. 1º, VIII).

---

## 7. Roadmap pós-deferimento (itens 🟩 da matriz)
A autorização da CVM antecede as habilitações de mercado. Após o deferimento:
1. **Adesões:** B3 Central Depositária (agente de custódia) + Balcão + Termo de Adesão Participante Selic (liquidante, com conta Reservas);
2. **Homologação RSFN/mensageria** com BCB/B3 (testes obrigatórios), substituindo a simulação `mensagens_spb`;
3. **Adesão ao Código ANBIMA de Serviços Qualificados** + certificação dos profissionais;
4. **Ambiente de certificação B3/SELIC** — ciclo completo de testes ponta a ponta;
5. **Go-live assistido** com fundos-piloto.

Cronograma de referência: **12–18 meses** (detalhe em `../relatorio_gaps_producao_licenca.md`).

---

## 8. Índice de correspondência com o Anexo A
| Anexo A, art. 1º | Onde está neste dossiê / pacote |
|---|---|
| I (identificação) | Capa + `[dados do banco]` |
| II (atos constitutivos) | Anexos societários do banco |
| III "a"–"g" (capacidade) | §2 (arquitetura), §4 (pessoas), §5 (controles/segurança/contingência) |
| IV (organograma/segregação) | §4.2 |
| V (representantes legais) | Anexos societários |
| VI (ata dos diretores) | §4.1 + ata a anexar |
| VII (participações) | Anexos societários |
| VIII (modelo de contrato) | `minuta_contrato_custodia.md` |

---

*Referência normativa: Resolução CVM nº 32/2021 (consolidada, com Res. CVM 209/2024). Documento de trabalho — revisar com jurídico/compliance do banco antes do protocolo.*
