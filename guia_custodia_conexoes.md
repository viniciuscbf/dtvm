# Guia — Como o Banco se Torna Custodiante e as Conexões com as Infraestruturas (B3, SELIC, SPB)

> **Documento de trabalho — v1.0 (baseado em pesquisa de jul/2026)**
> Responde três perguntas: (1) **como o banco vira custodiante** e o que isso exige; (2) **quais conexões técnicas** existem com B3, SELIC e o Sistema de Pagamentos Brasileiro; (3) **o que fazer depois da licença** para ter tudo rodando. Fecha com a análise do **sandbox regulatório da CVM**.
> O piloto em `piloto/` já representa a ponta operacional disso: o 4º portal (Mesa de Custódia) simula contas segregadas nas centrais, mensageria SPB, liquidação DVP e os arquivos diários de posição/extrato.

---

## 1. O que é "ser custodiante" — e por que é uma autorização separada

Custódia de valores mobiliários é atividade autorizada pela CVM por norma **própria** — a **Resolução CVM 32/2021** (que substituiu a Instrução CVM 542/2013 e foi alterada pela Res. CVM 209/24). Ou seja: **a licença de DTVM/banco não basta por si**; o banco precisa pedir à CVM a autorização específica para prestar serviços de custódia.

**Quem pode pedir:** bancos comerciais, múltiplos ou de investimento, caixas econômicas, corretoras e distribuidoras (CTVMs/DTVMs) e as próprias entidades de mercado. Um **banco múltiplo pequeno ou uma DTVM já autorizada pelo BCB é elegível** — o que joga a favor da tese do plano (parceiro que já tem a licença bancária).

**O que a CVM exige no pedido (Res. CVM 32):** identificação completa da instituição, e principalmente a demonstração de **capacidade operacional e tecnológica** para prestar o serviço com qualidade e confidencialidade de informações — sistemas, procedimentos, planos de contingência, segregação de funções, diretor responsável pela atividade. A autorização pode ser cassada se as condições deixarem de ser atendidas.

**As três funções nucleares do custodiante** (é isso que o portal de custódia do piloto simula):

1. **Guarda** dos ativos em contas **individualizadas por fundo** nas centrais depositárias (segregação patrimonial — os ativos do fundo nunca se misturam com os do banco);
2. **Liquidação física e financeira** das operações (entrega contra pagamento — DVP), nos ciclos de mercado (D+1 títulos públicos, D+2 ações);
3. **Tratamento de eventos** e cobrança de direitos: dividendos, JCP, cupons, amortizações, bonificações — anunciar → provisionar → creditar; mais a **informação**: arquivos diários de posição e extrato que alimentam a conciliação da administradora.

---

## 2. As conexões — com quem o banco precisa se plugar

### 2.1 Mapa das infraestruturas

| Infraestrutura | O que guarda/liquida | Vínculo necessário |
|---|---|---|
| **SELIC** (BCB) | Títulos públicos federais (LFT, LTN, NTN-B) | Conta de custódia própria + contas individualizadas de clientes; participante liquidante precisa de conta **Reservas Bancárias ou Conta de Liquidação** no BCB |
| **B3 — Central Depositária** (renda variável) | Ações, ETFs, BDRs, cotas listadas | Adesão como **agente de custódia** (termo de adesão + cadastro na Central de Cadastro de Participantes da B3) |
| **B3 — Balcão** (antiga Cetip) | Debêntures, CDBs, CRI/CRA, cotas de fundos não listados | Adesão ao segmento de balcão; contas de cliente 1/2 |
| **STR** (BCB) | Liquidação financeira em tempo real (dinheiro) | Conta Reservas/Conta de Liquidação + mensageria |
| **RSFN** | A "rede física" do SPB — mensageria padronizada entre instituições, BCB e câmaras | Conexão dedicada (dupla, com redundância), certificados digitais, catálogo de mensagens (SEL, STR, etc.) |

Pontos práticos que a pesquisa confirmou nos manuais da B3: a adesão ao SELIC via B3 se faz com **"Termo de Adesão Participante Selic"** protocolado na Central de Cadastro; o participante escolhe ser **liquidante** (liquida o financeiro na própria conta Reservas — é o caso de um banco) ou **não-liquidante** (liquida através de um banco liquidante); e parte dos processos roda **por tela** (sistemas web das câmaras) enquanto outra parte roda **exclusivamente por mensageria** — por isso a mesa de custódia precisa de um monitor de mensagens (o `mensageria.php` do piloto é a maquete disso).

### 2.2 O lado ANBIMA

Além da CVM, o mercado espera o **Código ANBIMA de Serviços Qualificados** (custódia e controladoria): adesão ao código e certificação dos processos. Não é obrigação legal para operar, mas na prática é exigido por gestores institucionais e facilita o credenciamento comercial.

### 2.3 "Preciso de outro site?" — como isso vira sistema

Sim — custódia é **outra função, com outra equipe e outro sistema** (a retaguarda do banco), separada da administração fiduciária. Não é outra empresa nem outro data center obrigatoriamente, mas é **outro domínio de acesso, outros perfis e outros dashboards**, porque:

- os usuários são outros (mesa de custódia/retaguarda do banco, não a controladoria de fundos);
- o dado nasce ali (mensageria das centrais) e é **entregue** à administradora como arquivo/serviço — a separação é inclusive uma exigência de governança (quem custodia confere quem administra, e vice-versa);
- em auditoria/fiscalização, a CVM olha a custódia como atividade autônoma (autorização própria, diretor responsável próprio).

No piloto isso virou o **4º portal** (`/custodia/`): painel de contas nas centrais, mensageria SPB, instruções & liquidação DVP e geração dos arquivos de posição/extrato — tudo sobre a mesma base, imitando a troca real entre custodiante e administradora.

---

## 3. Depois da licença — o roadmap para "tudo rodando"

Assumindo o banco autorizado pelo BCB (licença bancária/DTVM) e com a autorização de custódia da CVM em mãos, a sequência prática é:

**Fase A — Habilitações de mercado (2–4 meses, em paralelo):**
1. Adesão à B3: Central Depositária (agente de custódia) + Balcão + Termo de Adesão Participante Selic; cadastro de contas próprias e estrutura de contas de clientes;
2. Conexão RSFN: contratação dos links, certificados, homologação da mensageria com BCB e B3 (há roteiro de homologação formal com testes obrigatórios);
3. Conta Reservas/Conta de Liquidação no BCB operacional para o papel de liquidante;
4. Adesão ao Código ANBIMA de Serviços Qualificados.

**Fase B — Sistemas e homologação (3–6 meses, sobrepõe a A):**
5. Motor de custódia: cadastro de contas segregadas, captura da mensageria, liquidação DVP, agenda de eventos corporativos, geração de arquivos de posição/extrato (o que o piloto simula — vira o requisito funcional);
6. Motor da administradora: precificação/MaM, cálculo de cota com prévia D-1 e aprovação do gestor, conciliação automática custodiante×carteira×caixa, enquadramento, envios CVM/ANBIMA (informe diário 1 d.u.; balancete/CDA/perfil mensal 10 d.u.) — o ciclo já desenhado no piloto;
7. Testes de ponta a ponta em ambiente de homologação das centrais (B3 e SELIC têm ambientes de certificação) + plano de contingência.

**Fase C — Operação assistida (1–2 meses):**
8. Fundos-piloto reais (3–5), rodando o ciclo completo com dupla checagem manual;
9. Auditoria dos processos, ajuste dos manuais (a CVM cobra manuais operacionais da custódia), e só então escala comercial.

> ⚠️ **Custos fixos novos a orçar:** tarifas de participante B3 (adesão + manutenção + custódia por conta), links RSFN redundantes, equipe de retaguarda (mesmo enxuta: 2–3 pessoas + diretor responsável), auditoria dos controles de custódia. Entram na planilha de custos do plano.

---

## 4. Sandbox regulatório da CVM — serve para o nosso caso?

**O que é:** regime da **Resolução CVM 29/2021** em que a CVM dá **autorização temporária com dispensas de requisitos regulatórios** para modelos de negócio inovadores testarem no mercado real, com limites e salvaguardas. O 1º ciclo rodou de 2021 a 2026; de 33 propostas, **4 foram autorizadas** (Basement, Vórtx QR Tokenizadora, BEE4, SMU/Estar). Um novo ciclo está em discussão pública em 2026 (sem edital aberto de sandbox no momento; o que está aberto é o **LEAP 2026**, laboratório de inovação CVM/Fenasbac — útil para networking e visibilidade regulatória, mas não dá autorização).

**Resposta direta para o seu caso:**

- **Se o caminho é a parceria com banco licenciado (o plano A):** o sandbox **não é necessário** — a atividade roda sob a licença do banco (BCB) + autorizações CVM normais (administração fiduciária e custódia). O sandbox não aceleraria nada e adicionaria limites de escala (tetos de clientes/volume próprios do regime).
- **Quando o sandbox faria sentido:** se você quisesse operar **sem** o banco — ex.: uma "administradora digital" tecnológica pedindo dispensa temporária de requisitos que hoje travam um entrante (capital, estrutura) para provar o modelo. É um plano B defensável, mas depende de **abrir um novo ciclo** e de a proposta ser selecionada (taxa de aprovação do 1º ciclo: ~12%).
- **Quando ir atrás:** não precisa (nem deve) esperar fechar com um banco para *acompanhar* o tema — monitore o edital do próximo ciclo desde já e participe do LEAP se quiser expor a ideia à CVM. Mas o **esforço sério de candidatura ao sandbox só se justifica se as conversas com bancos falharem** — é o seguro do plano, não o plano. Em paralelo: as reuniões exploratórias com bancos (Fase 0 do plano de negócio) continuam sendo o teste mais rápido de viabilidade.

---

## 5. Como o piloto espelha este guia

| Realidade | No piloto |
|---|---|
| Autorização Res. CVM 32 + mesa de custódia própria | 4º portal (`/custodia/`) com perfil e login próprios |
| Contas individualizadas nas centrais | `contas_centrais` (SELIC, B3 Depositária, B3 Balcão, STR/Reservas) |
| Mensageria RSFN/SPB | `mensagens_spb` com códigos estilo catálogo (SEL1052, STR0008…) e reprocessamento |
| Liquidação DVP D+1/D+2 | `liquidacoes` — confirmar movimenta o caixa e gera confirmação na mensageria |
| Eventos corporativos | `eventos_corporativos` — anunciar → provisionar → creditar |
| Arquivos diários de posição/extrato | `custodia/arquivos.php` gera CSVs reais que a administradora concilia |
| Batimento custodiante × administradora | Conciliação + divergência NORD3 (ativo fantasma) atravessando os dois portais |

---

*Fontes principais: Resolução CVM 32 (consolidada) e Res. 209/24; manuais e regulamento da Central Depositária da B3; glossário e manuais de procedimentos operacionais B3; documentação SELIC/BCB (DFAM); Resolução CVM 29/2021 (sandbox) e balanço do 1º ciclo; notícias CVM/Fenasbac (LEAP 2026).*
