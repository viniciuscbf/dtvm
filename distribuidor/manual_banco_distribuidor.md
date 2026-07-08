# Manual A — Banco que quer distribuir cotas de fundos

> **Para quem:** banco **comercial, múltiplo ou de investimento** que atuará como **distribuidor**
> das cotas dos fundos administrados/custodiados na estrutura de DTVM.
> **Aviso:** orientação de estruturação — não é parecer jurídico. Confirmar o escopo da autorização
> específica do banco e validar com jurídico/compliance antes de operar.

## Conclusão rápida (leia isto primeiro)

Um banco **já integra o sistema de distribuição de valores mobiliários** (Lei 6.385/76, **art. 15**).
Distribuir cotas de fundos que ele administra/custodia aos seus clientes **está dentro do que ele já
pode fazer — não é uma licença nova pesada.** O trabalho é de **compliance + contratos + operação**,
não de obtenção de uma nova autorização de intermediário.

> Regra de ouro: **não licencie o que já existe.** O primeiro passo é confirmar, no registro do banco na
> CVM/BCB, que a atividade de distribuição de cotas de fundos está no escopo — para bancos, quase sempre está.

## Base legal

- **Lei 6.385/76, art. 15** — define o *sistema de distribuição de valores mobiliários*; instituições
  financeiras (bancos) são integrantes e podem distribuir valores mobiliários, inclusive **cotas de fundos**.
- **Resolução CVM 175/2022** — marco dos fundos. O **gestor** é quem **contrata os distribuidores** do
  fundo; a **taxa máxima de distribuição** é segregada no regulamento; o **termo de adesão e ciência de
  risco** é exigido (**art. 29** da Parte Geral). O **administrador que administra o fundo pode distribuí-lo**.
- **Resolução CVM 30/2021** — dever de **verificação da adequação (suitability)** do produto ao perfil do investidor.
- **Resolução CVM 50/2021** — **PLD/FT** (prevenção à lavagem de dinheiro e ao financiamento do terrorismo).
- **Código ANBIMA de Distribuição de Produtos de Investimento** — autorregulação (suitability, adequação, selo).

## Passo a passo

### Passo 1 — Confirmar o escopo da autorização
Verificar no cadastro do banco (CVM/BCB) que a **distribuição de cotas de fundos** está contemplada.
Bancos comerciais/múltiplos/de investimento normalmente já a possuem por serem integrantes do art. 15.
Se o banco pretende distribuir **apenas os fundos que administra** (estrutura da DTVM), a exigência é a
mais simples possível.

### Passo 2 — Governança e diretores responsáveis
- Designar **diretor responsável pela distribuição** de cotas de fundos.
- Designar **diretor responsável por PLD/FT** (Res. CVM 50) — pode ser o mesmo de PLD já existente no banco.
- Formalizar as **alçadas** e a **segregação** em relação à gestão (o distribuidor não decide a carteira).

### Passo 3 — Políticas obrigatórias
- **Suitability** (Res. CVM 30): política de adequação produto × perfil; questionário de perfil; regras de
  exceção (investidor **profissional/qualificado** tem dispensas); alertas de desenquadramento.
- **PLD/FT** (Res. CVM 50): KYC, monitoramento, comunicação ao COAF, avaliação interna de risco.
- **LGPD**: tratamento de dados do investidor.
- **Segurança e continuidade** (aproveitar as políticas já exigidas de custódia/administração).

### Passo 4 — Adesão à autorregulação ANBIMA
- Aderir ao **Código ANBIMA de Distribuição de Produtos de Investimento** (usual para quem distribui;
  exige política de suitability, adequação, publicidade e o **selo ANBIMA**).
- Manter as certificações exigidas da rede de distribuição (ex.: **CPA-10/CPA-20/CEA** conforme o público).

### Passo 5 — Contratos de distribuição
- Sob a Res. 175, **o gestor contrata o distribuidor**. Formalizar o **contrato de distribuição** entre o
  gestor (ou administrador, conforme o arranjo) e o banco distribuidor, definindo remuneração
  (**taxa de distribuição**, dentro da taxa máxima do regulamento), responsabilidades e regras de PLD/suitability.
- Se o banco distribui **fundos que ele mesmo administra**, o contrato interno formaliza a alocação do serviço.

### Passo 6 — Termo de adesão e material ao investidor
- **Termo de adesão e ciência de risco** (art. 29) assinado pelo investidor na primeira aplicação.
- Entregar/disponibilizar **regulamento**, **lâmina de informações essenciais** (quando exigida) e o
  **formulário de informações** do fundo. (O gerador de regulamentos do piloto já produz o regulamento/lâmina-base.)

### Passo 7 — Escolher o modelo de distribuição
- **Por conta e ordem (omnibus)** — Res. CVM 175, arts. **33 a 39**. O banco subscreve as cotas **por conta e
  ordem** dos clientes; no registro do fundo aparece o **nome do distribuidor + código de investidor**
  (art. 34, §1º), e o vínculo com o cliente final fica no **registro complementar** do distribuidor (art. 34).
  Pontos de atenção verificados:
  - O distribuidor por conta e ordem deve estar **autorizado a escriturar** cotas **ou** depositá-las em central
    depositária / registrá-las em mercado organizado (art. 34, **§2º** — novidade da 175).
  - **Segregação patrimonial** dos recursos dos clientes (art. 35).
  - O distribuidor **assume os ônus do cliente** (art. 36): cadastro, identificação, entrega de documentos,
    PLD, atendimento, e **retenção e recolhimento de tributos** (inc. X).
  - **Tributação e informe de rendimentos migram para o distribuidor:** no conta e ordem, quem **retém/recolhe o
    IR** e **emite o informe de rendimentos** ao cliente é o **distribuidor** (IN RFB 1.585/2015, art. 17, II;
    Res. 175, art. 36) — não o administrador. É um dever operacional pesado, considerar no projeto.
  - **Conciliação diária** da posição omnibus com o lastro no administrador (Ofício-Circular Conjunto CVM 2/2025).
- **Direta (cotista direto):** cada investidor é **cotista direto** no passivo do fundo; a retenção de IR e o
  informe ficam com o **administrador**. Mais simples para o distribuidor, menos "white-label".

### Passo 8 — Sistemas e operação
Cadastro/KYC → questionário de **suitability** → **captura de ordens** (aplicação/resgate) → **horário de
corte** → **cotização/liquidação** (D+n do regulamento, via conta do fundo no custodiante) →
**escrituração de cotas** (emissão no passivo; se omnibus, conciliação diária) → posição e
**informe de rendimentos/IR** ao investidor. (No piloto: o motor de **passivo** já faz aplicar/resgatar/
come-cotas; falta apenas o front transacional do investidor — que pode esperar o fechamento com o banco.)

## Fundos próprios/administrados × fundos de terceiros
- **Fundos administrados pelo banco (da casa):** caminho mais simples — administrar + distribuir é padrão.
- **Fundos de terceiros:** o banco também pode distribuí-los, com **contrato de distribuição** e as mesmas
  obrigações de suitability/PLD. Não muda a natureza da autorização (o banco já é integrante do sistema).

## Custos e prazos

**Autorização nova de distribuição:** em regra **não há** licença nova (o banco já é integrante do
sistema de distribuição). O custo é de **compliance + sistemas + certificação + autorregulação**.

**Taxa de fiscalização da CVM** — Lei 7.940/1989 (valores da Lei 14.317/2022, vigentes desde 01/2022).
É **anual** (não trimestral — a tabela trimestral antiga que circula na web está superada), paga até o
1º decêndio de maio. O distribuidor entra na **Faixa 3 — "Sistema de Distribuição de Valores Mobiliários"**,
por patrimônio líquido:

| Patrimônio líquido | Taxa anual |
|---|---|
| Até R$ 11 mi | R$ 3.759,06 |
| R$ 11 mi – 70 mi | R$ 7.518,11 |
| R$ 70 mi – 700 mi | R$ 22.431,42 |
| R$ 700 mi – 30 bi | R$ 97.097,71 |
| Acima de R$ 30 bi | R$ 530.880,38 |

- No **registro inicial** como participante do mercado, devidos **25%** da taxa anual aplicável.
- Se distribuir por **oferta pública**, há taxa adicional de **0,03% sobre o valor da oferta** (mín. R$ 809,16).
- Fonte: [tabela oficial CVM 2022 (PDF)](https://www.gov.br/cvm/pt-br/assuntos/regulados/taxa-de-fiscalizacao/valores/tabeladas%20taxa%20da%20cvmem2022_v3.pdf).
- ⚠️ Cada **classe de cota** do fundo também paga taxa própria (Faixa 5), mas isso é custo do **fundo/administrador**, não do distribuidor.

**ANBIMA** — adesão ao **Código de Distribuição** (via associação ou como "Aderente"); mensalidade
calculada pelo **PL da instituição**. ⚠️ Os valores exatos da mensalidade e da taxa de adesão ao Código
**não são publicados de forma isolada e confiável** — confirmar com a ANBIMA. Tabela oficial:
[ANBIMA — valores](https://www.anbima.com.br/data/files/CA/77/AF/B0/1CA8C9107E9166C9F82BA2A8/2026%20-%20TABELA%20DE%20VALORES.pdf).

**Certificação da rede** (por profissional): CPA-10 ~R$ 244–293; CPA-20 ~R$ 384–462 (2025). A partir de
**2026** as certificações mudam (Nova CPA ~R$ 225 + atualização anual ~R$ 115). Multiplica pelo nº de
distribuidores. Fonte: [ANBIMA — novas certificações 2026](https://www.anbima.com.br/pt_br/noticias/anbima-divulga-precos-e-atualizacao-anual-das-novas-certificacoes-de-distribuicao.htm).

**Prazos** (referência): adesão ANBIMA costuma levar **2–4 meses** (ciclo mensal do Conselho de Ética +
visita presencial; adesão concedida em caráter **provisório por 1 ano**). O rito de autorização SIN/CVM
(quando aplicável) tem prazo de análise de **60 dias corridos**. O tempo total ponta-a-ponta é dominado por
**compliance + sistemas + certificação**, tipicamente **alguns meses** — estimativa, não número oficial. ⚠️

## Checklist do banco distribuidor
- [ ] Escopo de distribuição confirmado no registro CVM/BCB
- [ ] Diretor responsável pela distribuição designado
- [ ] Diretor de PLD/FT designado
- [ ] Política de suitability (Res. 30) implantada
- [ ] Política de PLD/FT (Res. 50) implantada
- [ ] Adesão ao Código ANBIMA de Distribuição + selo
- [ ] Certificações da rede (CPA/CEA)
- [ ] Contrato(s) de distribuição firmado(s) com o gestor/administrador
- [ ] Termo de adesão e ciência de risco em produção
- [ ] Modelo definido: conta e ordem (com conciliação diária) ou direto
- [ ] Sistemas: cadastro/KYC, suitability, ordens, cotização/liquidação, escrituração, informe de IR

## Fontes
- [Lei 6.385/76 — sistema de distribuição (art. 15)](https://www.planalto.gov.br/ccivil_03/leis/l6385.htm)
- [Resolução CVM 175 (Parte Geral — termo de adesão art. 29; distribuição)](https://conteudo.cvm.gov.br/export/sites/cvm/legislacao/resolucoes/anexos/100/resol175consolid.pdf)
- [CVM — distribuição de cotas por conta e ordem (orientação 2025)](https://www.gov.br/cvm/pt-br/assuntos/noticias/2025/areas-tecnicas-da-cvm-orientam-sobre-distribuicao-de-cotas-de-fundos-por-conta-e-ordem)
- [ANBIMA — Resolução 175 e distribuição](https://www.anbima.com.br/pt_br/especial/nova-regra-fundos.htm)
- Res. CVM 30/2021 (suitability) e Res. CVM 50/2021 (PLD/FT) — site da CVM.
