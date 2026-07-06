# Dossiê de Estrutura Operacional e Tecnológica — Administração Fiduciária

> **Anexo técnico ao pedido de autorização — Resolução CVM nº 21/2021 (registro na categoria administrador fiduciário; pedido à SIN, instruído com o Anexo C).**
> **Requerente:** `[RAZÃO SOCIAL DA INSTITUIÇÃO]` — CNPJ `[__]` — instituição financeira de menor porte, já autorizada a funcionar pelo Banco Central do Brasil, que **constitui uma nova área de administração fiduciária**.
> **Documento de trabalho — v1.0.** Campos entre `[colchetes]` a preencher pela instituição.

---

## 1. Objeto e escopo

### 1.0 Perfil do requerente e premissa de elegibilidade
O requerente é uma **instituição financeira de menor porte já autorizada pelo BCB**, elegível a atuar como administrador fiduciário nos termos do **art. 1º, §2º, I, da Res. CVM 21** (instituições autorizadas a funcionar pelo BCB) — **sem** depender da via patrimonial do inciso II (0,20% dos recursos sob administração ou > R$ 550 mil). A área é constituída **do zero**, com apoio na estruturação (ver `../custodiante/modelo_parceria_papeis.md`, compartilhado com o pacote de custódia).

> **Premissa.** Se a instituição-alvo ainda não for autorizada pelo BCB, essa autorização é **etapa anterior e independente**, fora do escopo deste dossiê.

### 1.1 Objeto
Exercício da atividade de **administração de carteiras na categoria administrador fiduciário** (Res. CVM 21, art. 1º, §1º e art. 2º, §2º) — **excluída a gestão de recursos**, que permanece com o **gestor** contratado. Compreende a **controladoria de ativos e de passivos** dos fundos, o cálculo e a divulgação da cota e do PL, a manutenção dos registros contábeis, a conciliação com o custodiante, o cumprimento das obrigações regulatórias e a condução das assembleias, nos termos da **Res. CVM 175**.

### 1.2 Escopo excluído (declaração)
- **Gestão de recursos** (decisão de investimento): é do gestor — segregada da controladoria (Res. 21, art. 30, caput).
- **Custódia de valores mobiliários** (Res. CVM 32): atividade autônoma, objeto de pacote próprio (`../custodiante/`); pode ser do banco parceiro custodiante ou de terceiro autorizado.

---

## 2. Arquitetura tecnológica

### 2.1 Visão geral
A plataforma de administração fiduciária é a **controladoria** — no piloto, o portal `/admin/`, com perfil de acesso próprio (`admin`), **segregado** dos perfis de gestão (`gestor`) e de custódia (`custodia`), atendendo à segregação exigida pela Res. 21 (arts. 27 e 30).

```
   Gestor (decisão)        Custodiante (guarda/liquidação)
        │ boletas                 │ posição/extrato
        ▼                         ▼
   ┌───────────────────────────────────────────────┐
   │  CONTROLADORIA (administrador fiduciário):     │
   │   • Carteira & MaM        → ativos_carteira    │
   │   • Cálculo de cota D-1    → fechamentos        │
   │   • Lançamentos/ajustes    → lancamentos        │
   │   • Conciliação            → conciliacao        │
   │   • Regulatório (CVM)      → envios_regulatorios│
   │   • Aberturas de fundos    → documentos_abertura│
   └───────────────────────────────────────────────┘
        │ prévia D-1 → aprovação do gestor → publicação
        ▼
   Cotistas · CVM/ANBIMA · Auditoria
```

### 2.2 Componentes (referência ao piloto `/admin/`)
| Componente | Função | Implementação-piloto |
|---|---|---|
| Carteira e MaM | valorização dos ativos | `ativos_carteira` (`preco_mam`, `fonte_preco`); `admin/carteiras.php` |
| Cálculo e prévia de cota D-1 | cota/PL diários com aprovação do gestor | `fechamentos` (`versao`, `status`); `admin/processamento.php`; `calcular_cota()` |
| Lançamentos e reprocessamento | ajustes com trilha e cascata retroativa | `lancamentos`; `fechamentos.versao` |
| Conciliação | batimento com custodiante/caixa | `conciliacao`; `admin/conciliacao.php` |
| Regulatório | envios CVM/ANBIMA, ofícios, assembleias | `envios_regulatorios`, `oficios_cvm`, `assembleias`; `admin/regulatorio.php` |
| Aberturas de fundos | constituição/registro | `documentos_abertura`, `onboarding_etapas`; `admin/aberturas.php` |
| Monitoramento | partes relacionadas, alertas | `alertas_fraude`, `partes_relacionadas`; `admin/fraude.php` |
| Controle de acesso e trilha | segurança | `includes/auth.php`, `seguranca.php`; tabela `auditoria` |

### 2.3 Pilha, ambientes e segurança
- **Aplicação:** `[stack — no piloto: PHP 8 + MySQL/MariaDB]`; ambientes segregados dev/homolog/prod.
- **Recursos computacionais protegidos e auditáveis (Res. 21, art. 4º, §8º):** senhas bcrypt, sessão com revalidação de perfil, **trilha de auditoria append-only**, proteção CSRF, cabeçalhos de segurança e hardening de sessão — implementados em `piloto/includes/seguranca.php` (ver `politica_confidencialidade_seguranca.md`). Pendentes de produção (🟩): MFA e imutabilidade externa dos logs.

---

## 3. Fluxos operacionais nucleares

### 3.1 Ciclo diário da cota (D-1 → aprovação → publicação)
Importação da posição do custodiante → marcação a mercado → apuração de caixa → **conciliação** → cálculo da **prévia da cota** → **aprovação do gestor** → publicação; o informe diário à CVM só é liberado após a cota aprovada. Reprocessamento retroativo gera **nova versão** (`fechamentos.versao`) com republicação em cascata.

### 3.2 Controladoria e registros contábeis (Res. 175, art. 66 e 104, I)
Manutenção da escrituração contábil própria e segregada do fundo, registros de cotistas e atas, e trilha de lançamentos/ajustes. **Nota honesta:** o piloto calcula a cota de forma simplificada (ativos + caixa ÷ cotas); a **contabilidade de acrual (COSIF), o provisionamento pro-rata de despesas e o balancete no leiaute do Anexo Normativo** são **roadmap de produção (🟩)**.

### 3.3 Conciliação e fiscalização de prestadores (Res. 21, art. 32)
Conciliação diária posição × custodiante e caixa × extrato, com classificação de divergências, trilha de resolução e escalonamento — e fiscalização dos sistemas do custodiante e demais prestadores. Detalhe em `manual_conciliacao.md`.

### 3.4 Obrigações regulatórias (Res. 175)
Fila de envios periódicos e eventuais (art. 61–62 e Anexos Normativos), ofícios do regulador com prazo e resposta, e assembleias de cotistas (art. 70–79, admitida a forma **eletrônica**, art. 75). Detalhe em `manual_regulatorio_envios.md`.

### 3.5 Passivo do cotista e tributação (simulado no piloto)
Aplicações e resgates com cotização pela cota vigente, e o **motor fiscal** (o administrador é **responsável tributário** — Lei 14.754/2023: **IR regressivo** 22,5%→15% e **IOF** no resgate; **come-cotas** de maio/novembro 15%/20% com *step-up* de base; fundo de ações isento) já rodam como **simulação** em `piloto/admin/passivo.php` (motor em `includes/passivo.php`), com eventos fiscais e informe de rendimentos exportável. **Roadmap de produção (🟩):** provisão diária de IR na cota, contabilidade de acrual, geração de DARF e obrigações acessórias reais (DIRF/e-Financeira), KYC/suitability completos. Especificação em `manual_passivo_tributacao.md`.

---

## 4. Governança, diretores e segregação

### 4.1 Diretores estatutários (Res. CVM 21)
- **Diretor responsável pela administração de carteiras / administração fiduciária** (art. 4º, III): `[nome]`. Como a instituição se registra **apenas** como administrador fiduciário, aplica-se a **dispensa** do diretor exclusivo (art. 30, par. único), **vedada** a acumulação com a gestão dos recursos da própria instituição.
- **Diretor responsável por regras e controles internos** (art. 4º, IV), com **independência**: `[nome]`.
- Substituição por impedimento > 30 dias comunicada à CVM em **7 d.u.** (art. 5º).

### 4.2 Organograma (Anexo E / Anexo C)
```
Diretoria
 ├─ Administração Fiduciária / Controladoria (2–3): cota, contabilidade, conciliação, regulatório
 ├─ Compliance / Controles Internos / PLD (1–2): independente (art. 4º, IV)
 └─ Tecnologia (2+): sistemas, segurança, continuidade
```
A **gestão de recursos é externa** (gestor contratado) — segregação total ante a controladoria (art. 30, caput).

### 4.3 Segregação (arts. 27, 28 e 30)
Segregação funcional e de sistemas entre **administração fiduciária (controladoria)**, **gestão** e **custódia**; manuais escritos de segregação e de confidencialidade (art. 28). No piloto, a segregação é evidenciada pelos perfis distintos (`admin`, `gestor`, `custodia`) com revalidação a cada requisição.

---

## 5. Controles internos e confidencialidade

- **Controles internos escritos e conflitos de interesse** (arts. 22–23): manuais + política; monitoramento de partes relacionadas.
- **Relatório anual de compliance** ao órgão de administração até o último d.u. de **abril** (art. 25) — 🟩 rotina a formalizar.
- **Confidencialidade e segurança** (arts. 4º §8º, 28): `politica_confidencialidade_seguranca.md`.

---

## 6. Prestadores de serviço (Res. CVM 175)

O administrador contrata, em nome do fundo (art. 83): **controladoria/processamento de ativos** (I) — que a instituição **executa internamente** (art. 83, §1º, por ser autorizada pelo BCB), **escrituração de cotas** (II) e **auditoria independente** (III, art. 69). A **custódia** é contratada de custodiante autorizado (Res. 32), com o administrador como interveniente anuente (art. 80, par. único). O gestor contrata intermediação/distribuição (art. 85).

---

## 7. Regime de responsabilidade (Res. 175, art. 81)

Cada prestador essencial responde perante a CVM **na sua esfera de atuação**, por seus próprios atos — **sem solidariedade automática** —, sem prejuízo do **dever de fiscalizar** (Res. 21, art. 32). A trilha de conciliação e de fiscalização é a evidência da diligência do administrador.

---

## 8. Roadmap — o que falta para operar fundos reais (🟩)

Posterior à autorização (Res. 21), para operar cotistas reais sob a Res. 175:
1. **Passivo do cotista + tributação** (Lei 14.754) — o maior módulo;
2. **Contabilidade de acrual (COSIF)** e balancete no leiaute do Anexo Normativo;
3. **MaM homologada** (manual de precificação formal + captura automática de preços);
4. **Integrações reais** de envio (CVMWeb/Fundos.NET, HUB ANBIMA);
5. **Risco de liquidez** de produção (testes de estresse).

Detalhe e priorização em `../relatorio_gaps_producao_licenca.md`.

---

## 9. Correspondência com o pedido (Res. 21)

| Exigência | Onde |
|---|---|
| Requerimento à **SIN** + **Anexo C** | `requerimento_anexoC_checklist.md` |
| Atos constitutivos / objeto social | Anexos societários da instituição |
| Diretores responsáveis (art. 4º III/IV) + ata | §4.1 + ata a anexar |
| Recursos humanos e computacionais (art. 4º VII/§8º) | §2, §4.2, `politica...` |
| Formulário de referência (**Anexo E**) | preenchido pela instituição |
| Segregação e confidencialidade (arts. 27, 28, 30) | §4.3, `politica...` |

---

*Referências: Res. CVM 21/2021 e Res. CVM 175/2022 (consolidadas); Lei 14.754/2023. Documento de trabalho — revisar com jurídico/compliance antes do protocolo.*
