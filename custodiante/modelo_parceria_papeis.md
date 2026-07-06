# Modelo de Parceria e Papéis — Estruturação da Área de Custódia

> **Documento de trabalho — v1.0** · Define **quem faz o quê** na constituição da área de custódia de uma instituição financeira de menor porte, em duas configurações possíveis (A e B), e a **fronteira regulatória** que ambas precisam respeitar (Res. CVM 32/2021, arts. 4º, 5º, 17 e 19).
> Complementa o `dossie_estrutura_operacional_tecnologica.md` e o `requerimento_anexoA_checklist.md`.

---

## 1. Princípio inegociável (vale para as duas versões)

**A instituição financeira é a custodiante.** É ela quem:

- **requer e detém a autorização** da CVM (art. 4º — precisa ser um tipo elegível já autorizado pelo BCB);
- designa os **dois diretores estatutários** responsáveis (art. 17, I e II) — pessoas com vínculo estatutário **na instituição**, funções não cumuláveis;
- **carrega a responsabilidade** perante a CVM, os investidores e os depositários centrais;
- precisa demonstrar **capacidade operacional e tecnológica próprias** (art. 5º).

O papel do estruturador — seja empresa externa (Versão A) ou agente interno (Versão B) — é **habilitar** essa capacidade, **não substituí-la**. O "sonho grande" é vendável desde que a mensagem seja **"você constrói e opera a área, com o nosso motor e o nosso método"** — e nunca *"você empresta o CNPJ e nós operamos por baixo"*, o que colide com os arts. 17 e 19, §§1º–2º.

---

## 2. A fronteira regulatória (art. 19) — o que pode e o que não pode

| Atividade | Pode ser provida por um parceiro **não** custodiante? | Base |
|---|---|---|
| **Licenciamento de software / plataforma** (o motor de custódia) | **Sim** — é insumo tecnológico; entra como "contratos de software" | Anexo A, art. 1º, III, "g" |
| **Consultoria de estruturação** (dossiê, manuais, treinamento, roadmap) | **Sim** — serviço acessório, não é a atividade regulada | Fora do rol do art. 2º |
| **Tarefas acessórias / suporte operacional** (infra, monitoramento, mesa de apoio) | **Sim, como terceirização** — mas **não transfere responsabilidade** e exige contrato com solidariedade e controle de conflitos | Art. 19, II, §2º, §3º, §4º |
| **A atividade regulada de custódia em si** (ser o custodiante) | **Não** — só pode ser contratada de **outro custodiante autorizado** | Art. 19, §1º |
| **Os diretores estatutários responsáveis** (art. 17) | **Não** — têm de ser da instituição | Art. 17 |

> **Leitura prática:** o parceiro entrega **tecnologia + método + apoio**. A instituição mantém **a titularidade da atividade, os diretores e a responsabilidade**. Quanto mais a operação depender do parceiro, mais robustos precisam ser (a) a demonstração de capacidade própria da instituição e (b) os contratos de terceirização com solidariedade e planos de reversibilidade/saída.

---

## 3. O que o estruturador entrega (comum às duas versões)

1. **Plataforma de custódia** (o portal `/custodia/` evoluído) — motor de contas segregadas, liquidação DVP, eventos, mensageria, arquivos e conciliação, com o endurecimento de segurança já implementado;
2. **Método e documentação** — este pacote inteiro (dossiê, matriz, manuais, minutas, checklist do Anexo A);
3. **Condução do processo** — montagem do dossiê, interlocução técnica, preparação para as exigências da SMI (art. 7º);
4. **Capacitação** — treinamento da equipe da instituição e apoio à certificação dos profissionais;
5. **Roadmap pós-deferimento** — adesões B3/SELIC, homologação RSFN, Código ANBIMA, go-live assistido.

O que **muda** entre A e B é o **vínculo** por meio do qual isso é entregue — e as consequências regulatórias/contratuais disso.

---

## 4. Versão A — Empresa externa em parceria com a instituição

**Estrutura.** Uma empresa sua (PJ) presta à instituição serviços de **tecnologia** (licenciamento/SaaS da plataforma) e de **estruturação/consultoria**, e eventualmente de **suporte operacional acessório**, mediante contrato.

**Como se encaixa na norma:**
- A plataforma licenciada é o **"contrato de software" do Anexo A, III "g"** — junte o contrato com a sua empresa ao dossiê;
- O suporte operacional acessório é **terceirização (art. 19)**: contrato com **cláusula de responsabilidade solidária** (§3º), previsão de que **não altera a responsabilidade** da instituição (§2º) e **controles de conflito de interesse** (§4º);
- A **atividade regulada, os diretores e a capacidade nuclear permanecem na instituição** — o organograma (Anexo A, IV) mostra a equipe da instituição, com o apoio do parceiro claramente delimitado.

**Instrumentos contratuais:**
- Contrato de licenciamento/SaaS da plataforma;
- Contrato de prestação de serviços de estruturação (consultoria) e de suporte operacional acessório;
- Acordo de confidencialidade e SLA/plano de reversibilidade (saída sem descontinuidade).

**Impacto no dossiê/requerimento:** III "g" aponta para o contrato com a sua empresa; a seção de terceirização (dossiê §5.5) descreve o escopo do parceiro e os controles; a capacidade própria da instituição (art. 5º) precisa ficar **evidente apesar** do apoio externo.

**Prós:** escalável como **produto replicável** para vários bancos de interior; receita recorrente (licença + serviço); você mantém o ativo (a plataforma).
**Contras/atenções:** a CVM olha com rigor a **dependência de terceiro**; exige demonstrar que a instituição tem capacidade e governança próprias e que a operação é **reversível** se a parceria terminar; risco de conflito de interesse a mitigar.

---

## 5. Versão B — Estruturador interno (tudo dentro do banco)

**Estrutura.** Você atua **dentro da instituição** — como executivo, consultor alocado ou membro da governança — guiando **todos os passos** para ela se tornar custodiante. A plataforma e a operação são **da instituição** (desenvolvidas internamente ou licenciadas, mas operadas pela casa).

**Como se encaixa na norma:**
- **Pouca ou nenhuma terceirização da atividade regulada** — a história de **capacidade própria** (art. 5º) fica mais simples e forte;
- Se a plataforma for licenciada de um fornecedor, ainda há o **III "g"**; se for própria, descreve-se o desenvolvimento interno;
- Você pode, se tiver **vínculo estatutário**, integrar a **governança** — inclusive ser **um** dos diretores do art. 17, observada a **não-cumulação** (art. 17, §2º): não pode acumular as funções dos incisos I e II, nem funções conflitantes.

**Instrumentos:**
- Vínculo com a instituição (estatutário/CLT/contrato de consultoria com dedicação);
- Eventual contrato de licenciamento da plataforma (se não for desenvolvida internamente).

**Impacto no dossiê/requerimento:** organograma **interno**; capacidade operacional e tecnológica apresentada como **própria da instituição**; menor superfície de terceirização a justificar.

**Prós:** narrativa **mais sólida perante a CVM** (capacidade própria); menos pontos de conflito/terceirização; governança direta.
**Contras/atenções:** exige a sua **presença dentro** de cada instituição — **menos escalável** como produto para muitos bancos ao mesmo tempo; a plataforma, se sua, precisa de um modelo de licenciamento à parte.

---

## 6. Comparação lado a lado

| Dimensão | Versão A — Empresa externa | Versão B — Estruturador interno |
|---|---|---|
| Quem é a custodiante | A instituição | A instituição |
| Seu vínculo | PJ externa (fornecedor/consultor) | Interno (executivo/consultor alocado) |
| Plataforma | Licenciada da sua empresa (III "g") | Própria da instituição ou licenciada |
| Terceirização (art. 19) | Relevante — precisa de contrato + solidariedade + controles | Mínima ou inexistente |
| Diretores art. 17 | Da instituição (você não é) | Da instituição (você **pode** ser, com vínculo estatutário e sem cumulação) |
| Percepção da CVM sobre capacidade própria | Exige reforço (dependência de terceiro) | Mais natural |
| Escalabilidade do seu negócio | **Alta** (produto replicável) | Menor (1 a 1) |
| Receita típica | Licença + serviço recorrente | Remuneração/participação interna |
| Risco principal | Dependência/reversibilidade e conflito de interesse | Concentração em você; menos alavancagem |

---

## 7. Impacto em cada documento do pacote

| Documento | Ajuste na Versão A | Ajuste na Versão B |
|---|---|---|
| `dossie...` §2 (arquitetura) | Plataforma = sistema **licenciado** (III "g") | Plataforma **própria/interna** |
| `dossie...` §4.2 (organograma) | Equipe da instituição **+ apoio do parceiro** delimitado | Equipe **interna**; você pode figurar na governança |
| `dossie...` §5.5 (terceirização) | Descrever escopo do parceiro, solidariedade, conflitos, reversibilidade | Terceirização mínima; foco em capacidade própria |
| `requerimento_anexoA_checklist.md` III "g" | Anexar contrato com a **sua empresa** | Anexar contrato do fornecedor **ou** descrição do desenvolvimento interno |
| `minuta_contrato_custodia.md` | Inalterada (é o contrato instituição × fundo) | Inalterada |
| `matriz...` Art. 19 | Status ativo — controles de terceirização | Pouco aplicável — anotar |

---

## 8. Recomendação

- **Se o objetivo é um produto replicável** (levar isso a vários bancos de interior): **Versão A**, com o cuidado de blindar a **capacidade própria** de cada instituição e a **reversibilidade** — é o que torna a parceria defensável perante a CVM.
- **Se o objetivo é um caso âncora sólido** (uma instituição, do zero, com a sua condução direta): **Versão B** — narrativa mais forte de capacidade própria e menos pontos de atrito.
- **Caminho híbrido comum:** começar em **B** (um banco âncora, você por dentro, prova o modelo e a licença) e depois migrar para **A** (empacotar a plataforma + método e replicar), levando o histórico do caso âncora como credencial.

> Em qualquer versão, a linha vermelha é a mesma: **a instituição é a custodiante de verdade** — diretores, responsabilidade e capacidade são dela. O estruturador acelera; não substitui.

---

*Referência normativa: Resolução CVM nº 32/2021 (consolidada, com Res. CVM 209/2024), arts. 4º, 5º, 17 e 19. Documento de trabalho — revisar com jurídico/compliance antes de formalizar contratos ou vínculos.*
