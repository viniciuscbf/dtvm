# Entregabilidade de e-mail — para não cair no spam do banco

> Guia prático para o disparo dos e-mails de prospecção chegarem na **caixa de entrada**, não no spam. Banco tem filtro agressivo; um domínio novo mal configurado é barrado antes de qualquer pessoa ler.

## 1. O que são (e por que importam)

Três registros no DNS do domínio provam que o e-mail é legítimo:

| | O que é | Analogia |
|---|---|---|
| **SPF** | Lista de servidores autorizados a enviar em nome do domínio | Lista de remetentes autorizados na portaria |
| **DKIM** | Assinatura digital em cada e-mail (chave pública no DNS) | Selo de autenticidade lacrado |
| **DMARC** | Política: o que fazer se SPF **e** DKIM falharem | Regra da portaria para carta sem remetente válido |

Se os três estão certos, o Gmail/Outlook/servidor do banco confia no remetente. Se faltam, o e-mail é "não autenticado" → spam.

## 2. Estado atual de `argusdtvm.com.br` (verificado em 2026-07-09)

| Item | Status | Ação |
|---|---|---|
| **MX** (recebe e-mail) | ✅ Locaweb configurado | — |
| **SPF** | ✅ `v=spf1 include:_spf.locaweb.com.br -all` | nada a fazer |
| **DMARC** | ⚠️ existe, mas `p=none` (só monitora) | ok para começar; endurecer depois |
| **DKIM** | ❓ não confirmado | **verificar/ativar no painel Locaweb** |
| **SSL do site** | ✅ Let's Encrypt válido | — |

**Conclusão: o alicerce já está posto.** Falta confirmar o DKIM e seguir as boas práticas de envio.

## 3. To-do antes de disparar

1. **Ativar e validar o DKIM (o único item que falta).**

   **Achado (2026-07-09):** testei os seletores DKIM mais comuns (`mail`, `default`, `loc1`, `locaweb`, `dkim`, `selector1`…) no DNS de `argusdtvm.com.br` e **nenhum retornou registro** → o DKIM **provavelmente ainda não está publicado**. Precisa ser ativado.

   **Como VALIDAR (teste definitivo, independe do seletor):**
   - Envie um e-mail de `contato@argusdtvm.com.br` para um **Gmail** seu.
   - No Gmail, abra a mensagem → menu **⋮ → "Mostrar original"**.
   - Olhe as três linhas no topo: devem estar **SPF: PASS · DKIM: PASS · DMARC: PASS**.
   - Hoje, o esperado é **DKIM: 'neutral' ou ausente** (porque não está publicado). Quando aparecer **DKIM: PASS**, está resolvido.
   - Alternativa: enviar para o endereço do **[mail-tester.com](https://www.mail-tester.com)** → ele mostra explicitamente se o DKIM assina e passa, e dá uma nota (mire 9–10/10).

   **Como ATIVAR (Locaweb):**
   - **Caminho 1 (mais simples):** abrir chamado no **suporte da Locaweb** pedindo para *"habilitar DKIM para o e-mail profissional do domínio argusdtvm.com.br"*. Alguns planos de e-mail profissional exigem que o suporte gere/publique a chave. É o caminho mais garantido.
   - **Caminho 2 (você mesmo, no DNS):** painel **painel-dns2.locaweb.com.br** → zona de `argusdtvm.com.br` → adicionar as entradas **CNAME/TXT `_domainkey`** que a Locaweb fornece (o painel de e-mail mostra os valores exatos, com o seletor). Aguardar propagação (algumas horas).
   - Depois de ativado, **refaça o teste do Gmail** acima → confirmar **DKIM: PASS**.

   > Contexto: SPF e DMARC **já estão publicados e corretos** — o DKIM é a única peça que falta para os três "carimbos" ficarem verdes.
2. **Confirmar que a caixa `contato@argusdtvm.com.br` existe e alguém a monitora** (não adianta disparar e não ver a resposta).
3. **(Opcional, depois de ~2 semanas sem problemas)** endurecer o DMARC para `p=quarantine` — mais proteção contra alguém falsificar seu domínio.
4. **Teste de nota:** rode o domínio no [mail-tester.com](https://www.mail-tester.com) (envie um e-mail para o endereço que ele dá) — a nota diz se algo ainda derruba a entregabilidade. Mire **9–10/10**.

## 4. Boas práticas de envio (tão importantes quanto o DNS)

- **Aquecimento (warm-up):** domínio novo não pode disparar 300 e-mails no dia 1 — parece spam. Comece com **10–20/dia** na 1ª semana, suba gradualmente.
- **Nada de BCC gigante.** Um e-mail com 200 destinatários ocultos = spam na hora. Envie **individualmente** (mala direta 1-a-1) ou em lotes minúsculos.
- **Personalize o começo.** "Prezado [Nome], [1 linha específica]" — filtro e humano gostam. E-mail idêntico em massa é penalizado.
- **Texto > imagem.** E-mail só de imagem, muitos links ou muitas cores = gatilho de spam. Mantenha texto real, 1 link no máximo, assinatura simples.
- **Evite palavras-gatilho** e excesso de "!!!", CAIXA ALTA, "grátis", "urgente", "garantido".
- **Um anexo só** (o one-pager em PDF), leve. Nada de deck de 38 slides no e-mail frio.
- **Assinatura completa e real** (nome, empresa, e-mail próprio, site) — passa legitimidade.
- **Consistência de remetente:** sempre do mesmo `contato@argusdtvm.com.br`, nunca misturando Gmail.
- **Follow-up com moderação:** 1 retomada após ~5 dias úteis. Não persiga.

## 5. Ferramentas (opcional)
- Disparo 1-a-1 com bom controle: a própria caixa Locaweb/Outlook para os prioritários; para lotes segmentados, uma ferramenta de mala direta que envie individualmente (não BCC).
- Verificadores: mail-tester.com (nota), mxtoolbox.com (checar SPF/DKIM/DMARC), Google Postmaster Tools (reputação, se usar Gmail/Workspace no futuro).

> **Resumo:** DNS já está ~90% pronto (SPF ✅, DMARC ✅ fraco, MX ✅). Falta **confirmar o DKIM** e disparar com **aquecimento + personalização + sem BCC**. Feito isso, você chega na caixa de entrada.
