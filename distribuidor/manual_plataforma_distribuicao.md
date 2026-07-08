# Manual da Plataforma de Distribuição — blueprint técnico-regulatório (os 2 casos)

> **Para quem:** quem vai **construir e operar a plataforma** que permite o investidor aplicar/resgatar nos
> fundos da estrutura de DTVM — nos dois cenários: **(1) banco distribuidor** e **(2) instituição financeira
> não-banco**. Cobre o que a **tecnologia precisa ter embutido**, os **diretores responsáveis** e as
> **certificações** por função.
> **Aviso:** orientação de estruturação, não parecer jurídico. Confirmar autorizações com CVM/BCB e jurídico/compliance.

## Princípio nº 1 — Tecnologia ≠ atividade regulada

**Construir a plataforma (software) não exige certificação, nem diretor responsável, nem licença.** É atividade
de **tecnologia**. O que é regulado é a **atividade de distribuição** que a plataforma executa — e essa
responsabilidade recai sobre **quem é o distribuidor de direito** (uma instituição autorizada), não sobre quem
escreveu o código. A startup pode ser **provedora de tecnologia/BPO** ("investment-as-a-service").

**A linha que não pode cruzar:** a plataforma tem que operar **sob a autorização do distribuidor** (o banco), com
o **banco como contraparte do investidor** (deixar explícito nos termos de uso e no termo de adesão). Se a
plataforma passar a **captar clientes e intermediar em nome próprio**, ela vira "distribuidor de fato" e passa a
exigir licença própria. Enquanto for **tecnologia contratada pelo distribuidor autorizado**, a responsabilidade
regulatória é dele.

## Os 2 casos

### Caso 1 — Banco distribuidor (plataforma white-label do banco)
- O **banco** já é integrante do sistema de distribuição (Lei 6.385, art. 15, I) → é o **distribuidor de direito**.
- A plataforma é operada **em nome do banco**; os **diretores responsáveis** (PLD, suitability, distribuição) e as
  **certificações** da equipe de atendimento são **do banco**.
- É o caminho mais simples. Ver [Manual A — Banco](manual_banco_distribuidor.md).

### Caso 2 — Instituição financeira não-banco
- **BCB ≠ CVM:** a autorização do BCB **não** cobre distribuir valores mobiliários (Lei 6.385, art. 16, I).
- **Cooperativa de crédito:** caminho **facilitado** (Res. CMN 3.261 + Res. CVM 79) — pode distribuir aos
  cooperados; a plataforma opera sob a condição da cooperativa + administrador (DTVM/banco cooperativo).
- **SCFI/SCD/demais:** **não** dá com a licença original — a distribuição de direito precisa ser de um **parceiro
  autorizado** (banco/DTVM) ou a instituição vira **DTVM/CTVM**; a plataforma opera sob a licença do autorizado.
- Ver [Manual B — Instituição não-banco](manual_instituicao_financeira_distribuidor.md).

> Em **ambos os casos**, a plataforma (tecnologia) é a mesma; o que muda é **quem é o distribuidor de direito
> por trás dela** e onde ficam os diretores/certificações.

## Papéis na cadeia (quem é o quê)

| Papel | Quem | Precisa de licença/cert? |
|---|---|---|
| **Provedor de tecnologia** (a plataforma) | sua startup | **Não** |
| **Distribuidor de direito** | banco (ou cooperativa/DTVM) | Sim — integrante do sistema de distribuição |
| **Administrador fiduciário** | você (Res. 21) ou o banco | Sim — Res. CVM 21 |
| **Gestor** | o cliente que estrutura o fundo | Sim — Res. CVM 21 (gestão) |
| **Custodiante** | banco | Sim — Res. CVM 32 |
| **Escriturador de cotas** | administrador ou contratado | Sim (escrituração) |

## Diretores responsáveis (do DISTRIBUIDOR, não da plataforma)
- **PLD/FT** — diretor estatutário (Res. CVM 50, **art. 8º**); nomeação à CVM em 7 dias úteis; comunicação ao COAF em 24h.
- **Suitability** — diretor estatutário (Res. CVM 30, **art. 8º**); relatório anual até o último dia útil de abril.
- **Distribuição** — diretor responsável pela atividade (no arranjo do intermediário; se for administrador
  distribuindo os próprios fundos, Res. 21, art. 33, II).

## Certificações por função
| Função | Certificação | Observação |
|---|---|---|
| **Gestão / administração de carteiras** | **CGA / CGE** / CFA III / ACIIA (Res. 21, Anexo A) | é a sua posição |
| **Distribuir/atender varejo geral** | **CPA-10** | equipe do distribuidor |
| **Atender alta renda/private/institucional** | **CPA-20** | equipe do distribuidor |
| **Recomendar/assessorar (especialista)** | **CEA** | ⚠️ CPA não substitui |
| **Assessor de investimento (AAI)** | exame + credenciamento **ANCORD** (Res. 178) | se usar preposto |

> **Transição 2026:** CPA-10/CPA-20/CEA estão sendo substituídas por **Nova CPA** + **C-Pro R** (recomendação) e
> **C-Pro I** (institucional). Confirmar o vigente ao certificar a equipe.
>
> **Plataforma self-service (execution only):** sem atendimento/recomendação humana, a exigência de **certificação
> de atendente praticamente some** — o que permanece é a **estrutura de compliance** e o **suitability rodando na
> plataforma**. Os diretores responsáveis (PLD, suitability) continuam sendo do distribuidor.

---

# O CORAÇÃO — o que a plataforma precisa ter embutido

Como é software, os deveres regulatórios viram **funcionalidades**. Abaixo, o blueprint mínimo (com a norma de
cada item). O piloto já esboça vários (onboarding, suitability como metadados, motor de passivo aplicar/resgatar/
come-cotas) — o que falta é o **front transacional do investidor** ligado a isso.

### 1. Onboarding / cadastro / KYC (Res. CVM 50)
- Identificação e **cadastro** do investidor (art. 11); **beneficiário final** até a pessoa natural, controle ≥ **25%** (art. 13).
- **Atualização cadastral** com intervalo máximo de **5 anos**, baseado em risco (art. 4º, III).
- Classificação do cliente por **grau de risco** (baixo/médio/alto); atenção reforçada a **PEP**.
- **Guarda** dos documentos por no mínimo **5 anos** (art. 26).
- Cadastro pode ser **eletrônico**, com procedimentos verificáveis e assinatura eletrônica (art. 12).

### 2. Suitability — adequação ao perfil (Res. CVM 30)
- **Questionário de perfil** cobrindo os 3 eixos (art. 3º): **objetivos de investimento**, **situação financeira**, **conhecimento**.
- **Classificar o cliente** em categorias de perfil (art. 4º) e **classificar os produtos** (art. 5º).
- **Bloqueio/alerta**: vedado "recomendar" produto inadequado, sem perfil, ou com perfil desatualizado (art. 6º);
  se o cliente ordenar mesmo assim (execution only), a plataforma deve **alertar** e obter **declaração expressa de ciência** (art. 7º).
- **Dispensas** (art. 10): investidor **profissional**/**qualificado**, PJ de direito público, carteira administrada.
  ⚠️ **Ressalva:** PF qualificada por **patrimônio** (> R$ 1 mi) ou **certificação** **continua sujeita** ao suitability
  (art. 10, I c/c art. 12) — não tratar todo qualificado como dispensado.
- **Reavaliação:** perfil segue o cadastro (≤ 5 anos); **categorias de produtos** reclassificadas em ≤ **24 meses** (art. 9º).
- Limiares (para dispensas): **profissional** > R$ 10 mi (art. 11); **qualificado** > R$ 1 mi (art. 12), ambos com termo.

### 3. Termo de adesão e ciência de risco (Res. CVM 175, art. 29 — Parte Geral)
- No ingresso, o investidor **atesta** que teve acesso ao **regulamento** (+ anexo da classe/apêndice da subclasse) e
  tem **ciência dos riscos**, da ausência de garantia, e do significado do registro na CVM.
- Requisitos do termo (art. 29, §1º): **máx. 5.000 caracteres**; identificação de **até 5 principais fatores de risco**.
- Se a classe for de **responsabilidade ilimitada**, ciência específica nos termos do **Suplemento A** (art. 29, §3º).
- Dispensa de novo termo na **reaplicação** na mesma classe sem alteração relevante (art. 29, §2º).

### 4. Catálogo e material ao investidor
- Disponibilizar **regulamento** vigente no ingresso (art. 28) e a **lâmina** de informações (FIF, quando aplicável).
- **Material publicitário** consistente com regulamento/lâmina; avisos obrigatórios (Código ANBIMA de Distribuição).
- (O **gerador de regulamentos** do piloto já produz regulamento/suplemento-base para alimentar o catálogo.)

### 5. Captura de ordens (aplicação/resgate)
- **Horário de corte** (cut-off) por fundo; pedidos após o corte ou em não-úteis processam no próximo dia útil.
- A cota usada é a da **data de conversão** (cotização), conforme o regulamento — não a do dia do pedido.

### 6. Cotização e liquidação
- Prazos de **cotização** (conversão) e **liquidação** (pagamento) definidos no **regulamento** (ex.: D+0/D+1/D+30).
- Pagamento do resgate via **SPB**; prazo máximo de pagamento **até 5 dias úteis** da conversão.
- **Multa de 0,5% ao dia** sobre o resgate em caso de atraso no pagamento (a cargo de quem der causa).
- **Liquidação financeira**: débito na conta do cliente → conta segregada do fundo (no custodiante) → emissão de cotas.

### 7. Modelo: conta e ordem (omnibus) vs. direto (Res. CVM 175, arts. 33–39)
- **Conta e ordem:** registro do fundo em nome do **distribuidor + código de investidor** (art. 34, §1º); a plataforma
  mantém o **registro complementar** dos clientes (art. 34). Exige estar **autorizado a escriturar** cotas **ou**
  depositá-las em central/registro em mercado organizado (art. 34, §2º). **Segregação patrimonial** (art. 35).
  **Conciliação diária** com o lastro no administrador (Ofício-Circular Conjunto CVM 2/2025).
  - **O distribuidor assume os ônus do cliente** (art. 36), inclusive **retenção/recolhimento de tributos** (inc. X).
- **Direto:** o investidor é cotista direto; IR e informe ficam com o **administrador**. Mais simples de construir.

### 8. Tributação e informe de rendimentos
- **Come-cotas** (fundos abertos RF/multi/cambial): último dia útil de **maio e novembro** — **15%** (longo prazo) /
  **20%** (curto prazo). Fundos de **ações** e FIP entidade de investimento: sem come-cotas.
- **Tabela regressiva** (Lei 11.033): 22,5% → 20% → 17,5% → 15% (por prazo). **IOF regressivo** em resgates < 30 dias.
- **Quem retém/recolhe e emite o informe:** regra geral = **administrador**; no **conta e ordem** = **distribuidor**
  (IN RFB 1.585/2015, art. 17, II; Res. 175, art. 36, X). A plataforma deve gerar o **informe anual de rendimentos**.

### 9. PLD — monitoramento e comunicação
- Monitoramento contínuo de operações; análise de atipicidades; **comunicação ao COAF em 24h** (Res. 50, art. 22);
  **declaração negativa** anual (art. 23).

### 10. Transversais
- **Trilha de auditoria** (append-only) de cada passo (ordem, cotização, liquidação, alterações de perfil).
- **Segurança** (sessão, CSRF, criptografia) e **LGPD** (base legal para KYC/PLD é cumprimento de obrigação legal).
- **Segregação de ambientes** e continuidade.

---

## Checklist de conformidade da plataforma
- [ ] Contraparte do investidor = **distribuidor autorizado** (banco), explícito nos termos
- [ ] **Onboarding/KYC** + beneficiário final + atualização ≤ 5 anos (Res. 50)
- [ ] **Suitability**: questionário (3 eixos), classificação cliente/produto, bloqueio/alerta, dispensas com a ressalva da PF qualificada (Res. 30)
- [ ] **Termo de adesão e ciência de risco** (≤ 5.000 caracteres, até 5 riscos, Suplemento A se ilimitada) (Res. 175 art. 29)
- [ ] **Regulamento/lâmina** disponíveis no ingresso; material publicitário consistente
- [ ] **Ordem** com horário de corte + **cota da data de conversão**
- [ ] **Cotização/liquidação** conforme regulamento; SPB; pagamento ≤ 5 DU; multa 0,5%/dia
- [ ] Modelo **conta e ordem** (registro complementar, código de investidor, segregação, conciliação diária, escrituração/depósito) **ou** direto
- [ ] **IR + informe de rendimentos** (distribuidor no conta e ordem; administrador no direto)
- [ ] **PLD**: monitoramento + COAF 24h
- [ ] **Auditoria, segurança, LGPD**
- [ ] Diretores responsáveis (PLD, suitability, distribuição) — **do distribuidor**
- [ ] Certificações (CPA/CEA/C-Pro) **só onde há atendimento/recomendação humana**

## O que o piloto já tem vs. o que falta (para virar demo de distribuição)
- ✅ **Motor de passivo** (`passivo.php`): aplicar / resgatar / come-cotas.
- ✅ **Onboarding de cotista** (KYC/suitability como metadados) no admin.
- ✅ **Gerador de regulamento/lâmina** (para o catálogo).
- ⚠️ **Portal do cotista é só leitura** — falta o **front transacional** (ordem de aplicação/resgate) ligado ao passivo.
- ❌ Falta: **suitability rodando** (questionário + bloqueio), **termo de adesão** eletrônico, **conta e ordem** (registro
  complementar + conciliação), **informe de IR**, **liquidação** simulada. São os itens a construir quando fechar com o banco.

## Fontes (verificadas em texto oficial)
- [Lei 6.385/76 (sistema de distribuição — arts. 15/16)](https://www.planalto.gov.br/ccivil_03/leis/l6385.htm)
- [Res. CVM 175 — Parte Geral (termo de adesão art. 29; conta e ordem arts. 33–39; distribuição art. 21)](https://conteudo.cvm.gov.br/export/sites/cvm/legislacao/resolucoes/anexos/100/resol175consolid_ParteGeral.pdf)
- [Res. CVM 30 — suitability (PDF consolidado)](https://conteudo.cvm.gov.br/export/sites/cvm/legislacao/resolucoes/anexos/001/resol030consolid.pdf)
- [Res. CVM 50 — PLD/FT (PDF consolidado)](https://conteudo.cvm.gov.br/export/sites/cvm/legislacao/resolucoes/anexos/001/resol050consolid.pdf)
- [CVM — distribuição por conta e ordem (orientação 2025)](https://www.gov.br/cvm/pt-br/assuntos/noticias/2025/areas-tecnicas-da-cvm-orientam-sobre-distribuicao-de-cotas-de-fundos-por-conta-e-ordem)
- IN RFB 1.585/2015, art. 17 (responsável tributário no conta e ordem) — Receita Federal.
- [Res. CVM 178 — assessor de investimento](https://conteudo.cvm.gov.br/legislacao/resolucoes/resol178.html)

> **Ressalva:** material de blueprint para estruturação com o parceiro — não substitui parecer jurídico nem a
> validação de compliance do distribuidor autorizado. Numeração de artigos conferida no texto consolidado vigente.
