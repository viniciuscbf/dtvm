# E-mails de abordagem — v2 comercial, por tipo de instituição

## Mapa jurídico por tipo (o que JÁ têm × o que FALTA — base das mensagens)

| Tipo | O que JÁ têm | O que FALTA | Base legal | Template / One-pager |
|---|---|---|---|---|
| **Banco** (comercial/múltiplo/investimento) | Autorização BCB (capital R$ 12,5–17,5 mi já integralizado); sistema de distribuição; conta Reservas | Credenciamento de **adm. fiduciário** (prazo legal 60 dias) e de **custodiante** (90 dias) — ambos documentais, capital adicional R$ 0 | Res. CVM 21, art. 1º, §2º, I (elegível direto) · Res. CVM 32, art. 4º (no rol de custódia) · distribui via art. 33 | A / `Argus_OnePager_Banco.pdf` |
| **CTVM** (corretora) | Autorização BCB (R$ 1,5 mi); distribuidora nata; membro B3 | Os mesmos 2 credenciamentos CVM — nada a constituir | Res. 21, art. 1º, §2º, I · Res. 32, art. 4º | A2 / `Argus_OnePager_CTVM.pdf` |
| **DTVM não-administradora** | Autorização BCB (R$ 0,55–1,5 mi); licença juridicamente idêntica à CTVM (equiparação CMN 1986); algumas **já custodiam** | Só o credenciamento de adm. fiduciário (60 dias); as custodiantes já têm a 2ª licença rodando | Res. 21, art. 1º, §2º, I · Res. 32, art. 4º | A3 / `Argus_OnePager_DTVM.pdf` + **NDA** |
| **DTVM administradora** (2ª opção, último recurso) | **TUDO**: credenciamento de administração (e às vezes custódia) já ativos, retaguarda própria | Nada regulatório — falta **viabilidade econômica no fundo pequeno** (os pisos de custo delas não alcançam PL < R$ 20 mi). A oferta muda: tecnologia/operação da vertical, não licença | Administrador pode contratar prestador de tecnologia/serviços qualificados (Res. 175, art. 82 §§; responsabilidade indelegável permanece com ele) | A4 / **sem one-pager** no 1º contato + **NDA reforçado** |
| ~~SCFI / SCD / IP / câmbio~~ | — | FORA da campanha: SCFI nunca custodia (fora do rol art. 4º); SCD/IP/câmbio têm objeto taxativo que não comporta | Res. 32, art. 4º · Res. 5.050, art. 7º, §1º · Res. 5.009, art. 2º | — |

> Detalhe completo com fontes primárias: `../../parceria_estrutura_juridica.md`.

> **Segmentação da campanha** (alinhada ao `contatos_prospeccao.xlsx` e à `parceria_estrutura_juridica.md`) —
> só tipos elegíveis às **DUAS licenças** (administração + custódia própria, rol da Res. CVM 32):
> **A — Banco pequeno** → anexa `Argus_OnePager_Banco.pdf` · **A2 — CTVM/corretora** → `Argus_OnePager_CTVM.pdf`
> · **A3 — DTVM que NÃO administra fundos** → `Argus_OnePager_DTVM.pdf` (as que já custodiam são os
> melhores alvos da lista). **REGRA DA A3: NDA antes do deck e divulgação em camadas** — DTVM entende o
> produto rápido demais; a demo mostra o piloto funcionando, nunca a arquitetura nem os dossiês (só com contrato).
> **FORA da campanha** (critério 12/07/2026): SCFI (administra pela Res. 5.237 mas NUNCA custodia — fora do
> rol da 32) e SCD/IP/câmbio/hipotecária (objeto não comporta; só via DTVM nova). Ficam no Master do
> `bancos_alvo.xlsx` se a estratégia mudar.
>
> Regras de ouro: personalize **[Nome]** e **[Instituição]** (nunca "Prezados"); **um** pedido só
> (20 minutos); envie de **vinicius.fernandes@argusdtvm.com.br** (nominal — nome de exibição
> "Vinicius Fernandes (Argus)"; autenticação SPF/DKIM/DMARC validada em 12/07/2026; `contato@` fica
> como caixa institucional do site); **não** anexe o deck (é da reunião); follow-up em ~5 dias úteis;
> 10–20 envios/dia no aquecimento; **nunca trocar o remetente no meio da campanha**.

---

## A) Banco pequeno (Alvo A)

**Assunto:** Uma linha de receita nova sobre a licença que o [Instituição] já tem

[Nome], tudo bem?

Sou Vinicius Fernandes, CGA, CGE — CTO da Argus.

Sendo direto: eu e minha equipe estamos estruturando um projeto de DTVM com um diferencial claro — viabilizar fundos de investimento de menor patrimônio, com constituição muito mais rápida e barata do que o mercado pratica hoje. É um segmento enorme que os grandes administradores abandonaram.

O gargalo do projeto é regulatório: administração fiduciária e custódia exigem uma instituição autorizada pelo Banco Central — uma empresa de tecnologia não pode rodar essa estrutura sozinha. É exatamente aí que enxergamos o [Instituição].

Importante: **não buscamos investimento**. A equipe de desenvolvimento, a arquitetura dos sistemas e o capital para construir e manter tudo em produção já são nossos.

A parceria consiste no [Instituição] entrar com a licença e a estrutura jurídica para constituir a administradora fiduciária e a casa de custódia — e com a supervisão de compliance que já exerce. A contrapartida: **participação nos lucros da operação, sem absorver nenhum custo** (os custos são integralmente nossos).

Para mostrar que não é ideia em slide, construímos um piloto funcional que replica a operação de ponta a ponta em 4 portais (gestor, administradora, custódia e cotista): https://argusdtvm.com.br/piloto/

Em anexo, um documento com o tour completo do piloto e um one-pager com o desenho da parceria para o caso do [Instituição].

Podemos marcar uma conversa de **20 minutos** (Zoom ou Meet) nesta ou na próxima semana para eu apresentar a ideia e discutirmos o projeto? Se preferir o caminho mais direto, meu WhatsApp está na assinatura.

[Assinatura]

---

## A2) CTVM / corretora (Alvo A)

**Assunto:** Receita recorrente para a [Instituição] — além da corretagem

[Nome], tudo bem?

Sou Vinicius Fernandes, CGA, CGE — CTO da Argus.

Sendo direto: eu e minha equipe estamos estruturando um projeto de administração fiduciária focado em viabilizar fundos de menor patrimônio, com constituição muito mais rápida e barata do que o mercado pratica — o segmento que os grandes administradores abandonaram. Corretagem é receita transacional; administração de fundos é **% do PL, todo mês** — e a [Instituição] tem o que ninguém tem: gestores como clientes e a distribuição na mão. Hoje esses gestores executam com vocês e a administração dos fundos deles fica **fora de casa**.

O gargalo do projeto é regulatório: essa operação exige uma instituição autorizada pelo Banco Central — uma empresa de tecnologia não pode rodá-la sozinha. A vantagem no caso de vocês: **a licença de CTVM já habilita a corretora a administrar fundos** — não é preciso constituir nada novo, só o credenciamento na CVM (~60 dias, e o dossiê nós entregamos pronto).

Importante: **não buscamos investimento**. Equipe de desenvolvimento, arquitetura dos sistemas e o capital para construir e manter tudo em produção já são nossos.

A parceria: a [Instituição] entra com a licença e a supervisão de compliance que já exerce; nós entramos com toda a tecnologia e a operação. Contrapartida: **participação nos lucros, sem absorver nenhum custo**.

Para mostrar que não é ideia em slide, construímos um piloto funcional que replica a operação de ponta a ponta em 4 portais (gestor, administradora, custódia e cotista): https://argusdtvm.com.br/piloto/

Em anexo, o tour completo do piloto e um one-pager com o desenho da parceria para o caso de uma corretora.

Podemos marcar uma conversa de **20 minutos** (Zoom ou Meet) nesta ou na próxima semana? Se preferir o caminho mais direto, meu WhatsApp está na assinatura.

[Assinatura]

---

## A3) DTVM que NÃO administra fundos

> As que **já são custodiantes** (coluna "Já custodia?" do CRM) são os melhores alvos: personalize a
> 1ª frase com isso. **NDA antes do deck, sempre** — o e-mail frio abaixo não revela nada sensível.

**Assunto:** A licença da [Instituição] vale duas linhas de receita que ela não usa

[Nome], tudo bem?

Sou Vinicius Fernandes, CGA, CGE — CTO da Argus.

Sendo direto: eu e minha equipe estamos estruturando um projeto de administração de fundos focado em viabilizar fundos de menor patrimônio, com constituição muito mais rápida e barata do que o mercado pratica — o segmento que os grandes administradores abandonaram. E a licença que a [Instituição] já tem é exatamente a mais completa para isso: **a DTVM é elegível às duas pontas — administração fiduciária e custódia** — sem constituir nada novo, só o credenciamento na CVM (~60 dias, e o dossiê nós entregamos pronto).

O gargalo do projeto é regulatório: essa operação exige uma instituição autorizada pelo Banco Central — uma empresa de tecnologia não pode rodá-la sozinha. É por isso que procuramos uma distribuidora sem área de fundos montada: vocês têm a licença parada para isso; nós temos tudo o que falta.

Importante: **não buscamos investimento**. Equipe de desenvolvimento, arquitetura dos sistemas e o capital para construir e manter tudo em produção já são nossos.

A parceria: a [Instituição] entra com a licença e a supervisão de compliance que já exerce; nós entramos com toda a tecnologia e a operação. Contrapartida: **participação nos lucros, sem absorver nenhum custo** — incluindo, na sequência, a receita de custódia dentro de casa.

Para mostrar que não é ideia em slide, construímos um piloto funcional que replica a operação de ponta a ponta em 4 portais (gestor, administradora, custódia e cotista): https://argusdtvm.com.br/piloto/

Em anexo, o tour completo do piloto e um one-pager com o desenho da parceria para o caso de uma distribuidora.

Podemos marcar uma conversa de **20 minutos** (Zoom ou Meet) nesta ou na próxima semana? Se preferir o caminho mais direto, meu WhatsApp está na assinatura.

[Assinatura]

---

## D) Prioritário — versão personalizada (para os 10–15 melhores alvos)

> 1 linha de personalização REAL dobra a taxa de resposta. Use os dados da planilha:
> "vi que vocês já gerem X fundos", "já custodiam Y classes", "atuam forte em [nicho/região]".

**Assunto:** [gancho específico] — parceria de administração de fundos

[Nome], tudo bem?

Sou Vinicius Fernandes, CGA, CGE — CTO da Argus.

[1 frase de personalização REAL — ex.: "vi que a [Instituição] já gere N fundos — vocês estão a um passo do que vou propor."] É por isso que escrevo: estamos estruturando um projeto de administração fiduciária para fundos de menor patrimônio — o segmento que os grandes abandonaram — e o que falta no nosso projeto é exatamente o que a [Instituição] já tem: a licença de instituição autorizada.

Não buscamos investimento: tecnologia, equipe e capital já são nossos. A parceria é a [Instituição] entrar com a licença e o compliance, e participar dos lucros **sem absorver nenhum custo**. Construímos um piloto funcional de ponta a ponta em 4 portais para provar a tese: https://argusdtvm.com.br/piloto/

Em anexo, o tour completo do piloto e um one-pager com o desenho da parceria para o caso de vocês. Vale uma conversa de **20 minutos** (Zoom ou Meet) nesta ou na próxima semana? Se preferir o caminho mais direto, meu WhatsApp está na assinatura.

[Assinatura]

---

## E) Follow-up (~5 dias úteis — sempre AGREGANDO um dado, nunca só "retomando")

**Assunto:** Re: [assunto original]

[Nome], um dado que talvez ajude a dimensionar: nas administradoras tradicionais, um fundo carrega
**R$ 150–300 mil/ano** de custo fixo (os pisos de mercado somam R$ 12–25 mil/mês entre administração,
custódia e controladoria) — é por isso que fundo abaixo de ~R$ 20 milhões de PL não fecha a conta.
Na nossa estrutura, o mesmo fundo roda por **menos de R$ 10 mil/ano** all-in — é isso que viabiliza
fundos a partir de R$ 1 milhão e destrava o segmento inteiro que os grandes ignoram, com a
[Instituição] participando da receita.

Se fizer sentido, sigo à disposição para os 20 minutos. Se não for o momento, me diga sem cerimônia
— não insisto.

[Assinatura]

---

## A4) DTVM administradora (SEGUNDA OPÇÃO — último recurso, NDA reforçado)

> Só usar depois de esgotar o CRM principal. Aqui a instituição **já tem tudo juridicamente** — a
> oferta muda de "destravar a sua licença" para "destravar um segmento que o seu custo não alcança".
> **Sem one-pager e sem detalhes de arquitetura**: o e-mail vende só a tese econômica; deck e demo
> exigem NDA assinado. É o público com maior capacidade de copiar — revelar o mínimo.

**Assunto:** O segmento que a [Instituição] não atende — e a conta de por quê

[Nome], tudo bem?

Sou Vinicius Fernandes, CGA, CGE — CTO da Argus.

Sendo direto: a [Instituição] já tem o credenciamento e a retaguarda de administração — mas, como todo o mercado, opera com um piso de custo que torna inviável administrar fundo abaixo de ~R$ 20 milhões de PL. Esse corte deixa de fora a maior parte dos ~31 mil fundos do país e todos os gestores emergentes que ainda não nasceram.

Nós construímos a tecnologia que muda essa conta: uma plataforma que automatiza a operação (cota, conciliação, enquadramento, tributação, reporte e monitoramento antilavagem com IA) a ponto de viabilizar fundos a partir de R$ 1 milhão — com margem. Não é projeto em slide: há um piloto funcional de ponta a ponta no ar (https://argusdtvm.com.br/piloto/).

A proposta: a vertical de fundos pequenos rodando sobre a licença e a supervisão da [Instituição], com a nossa tecnologia e a nossa operação — custos integralmente nossos, participação nos resultados da vertical. Vocês capturam um mercado que hoje simplesmente não conseguem precificar; nós não tocamos no negócio atual de vocês.

Importante: **não buscamos investimento** — equipe, sistemas e capital de operação já são nossos.

Podemos conversar **20 minutos** (Zoom ou Meet)? Pela natureza do que vamos mostrar, o detalhamento técnico e comercial vem sob acordo de confidencialidade — o que já diz o quanto levamos a sério proteger também o que for de vocês.

[Assinatura]

---

## Assinatura padrão

Vinicius Fernandes, CGA, CGE
CTO · Argus — administração fiduciária e custódia de fundos
vinicius.fernandes@argusdtvm.com.br · argusdtvm.com.br
WhatsApp: +55 84 98778-8089

> **WhatsApp na assinatura:** no mercado financeiro brasileiro o WhatsApp é canal de negócio
> normal (diretor de instituição pequena resolve quase tudo por lá) e reduz a fricção de
> resposta. Formato sóbrio: só o número na linha da assinatura, sem call-to-action agressivo.
> O mesmo número já está no rodapé das cartas físicas (`cartas/prontas/`). Dica: ative o
> WhatsApp Business nesse número (grátis, mesmo aparelho) para ter perfil "Vinicius Fernandes ·
> Argus" com site e descrição quando um diretor te adicionar.

> Versão HTML (com a logo, pronta para o Roundcube): `assinatura_email.html` nesta pasta —
> abrir no navegador, selecionar a versão escolhida, copiar e colar no campo de assinatura.

---

## Checklist antes de cada disparo

- [ ] Segmento certo → e-mail certo → **one-pager certo** (Banco / CTVM / DTVM).
- [ ] DTVM (A3): **NDA pronto para a resposta positiva** — deck e demo só depois de assinado.
- [ ] Personalizei **[Nome]** e **[Instituição]** (nada de "Prezados").
- [ ] **Um** pedido só: 20 minutos. **Dois anexos, sempre os mesmos**: `Argus_Piloto_Tour.pdf` (tour do piloto) + one-pager do segmento. Sem deck (é da reunião).
- [ ] Enviado de **vinicius.fernandes@argusdtvm.com.br** com a assinatura padrão (autenticação já validada — ver `entregabilidade_email.md`).
- [ ] Follow-up agendado para +5 d.u. (modelo E, com o dado de custo).
- [ ] Prioritários (10–15): versão **D personalizada** com dado da planilha.
- [ ] Canal único: **e-mail do projeto** — nada de abordagem por redes/perfis pessoais.
- [ ] SCD/IP/câmbio: **não abordar** na onda 1.
