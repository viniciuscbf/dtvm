# Kit de abordagem comercial — Argus

Materiais para prospectar os alvos de `../bancos_alvo.xlsx`.

| Arquivo | O que é |
|---|---|
| `guia_abordagem.md` | **Comece aqui.** Estratégia, camadas, cadência, tom, do/don't. |
| `emails.md` | Templates de e-mail por tipo (Banco · CTVM · DTVM não-adm) + personalizado + follow-up. |
| `Argus_Piloto_Tour.pdf` | **Anexo 1 de todo e-mail** — tour do piloto com telas reais (40 págs.): 4 portais, funcionalidades e mapa de conformidade por norma. |
| `Argus_OnePager_Banco.pdf` | **Anexo 2** — one-pager (a ideia + como a parceria se constrói) para bancos. |
| `Argus_OnePager_CTVM.pdf` | One-pager — corretoras (CTVM). |
| `Argus_OnePager_DTVM.pdf` | One-pager — distribuidoras (DTVM) que não administram fundos: as duas licenças na mesma casa. |
| `assinatura_email.html` | Assinatura HTML (com logo) pronta para colar no Roundcube. |
| `cartas/` | **Campanha física**: 3 templates .docx (campos amarelos) + **`cartas/prontas/` — as 43 cartas 100% PREENCHIDAS** (nome, cargo, instituição, endereço, WhatsApp, data 14/07), uma por alvo do CRM: imprimir, assinar à mão e enviar registrada com AR (~R$ 700–1.000 no total). Se enviar em outro dia, ajustar a data no Word (Ctrl+H: "14 de julho" → nova data). |
| `contatos_prospeccao.xlsx` | **O CRM da campanha** — Alvos com as DUAS licenças ao alcance, em abas por tipo (Bancos · Corretoras · DTVMs não-adm · Auditores-canal) + Consolidado + Legenda com o critério e as exclusões. Coluna **E-mail** = melhor contato p/ proposta (pesquisa 13/07; vários separados por `;`, melhor primeiro; fonte e confiança em Notas); **E-mail 2 (regulatório)** = a caixa compliance@ original, último recurso. Colunas amarelas = suas; follow-up calcula sozinho. |
| `contatos_segunda_opcao.xlsx` | **Reserva estratégica** — só abrir depois de esgotar o CRM principal: bancos/CTVMs médios (R$ 0,4–20 bi, sem gigantes/estrangeiros) + DTVMs administradoras (último recurso, NDA reforçado). Sem sobreposição com o CRM principal. |
| `alvos_prioritarios.xlsx` | Recorte dos prioritários (Alvo A independente nacional, menor primeiro) — subconjunto do CRM principal. |
| `onepagers.py` · `prioritarios.py` | Geradores (regeneram os PDFs / a planilha). |
| `*.pptx` | Fontes editáveis dos one-pagers (edite no PowerPoint se quiser). |
| `entregabilidade_email.md` | **Antes de disparar:** SPF/DKIM/DMARC (como validar o DKIM) + boas práticas para não cair no spam. |
| `carta_de_intencoes.md` | Minuta **não-vinculante** de carta de intenções (75/25, papéis, piloto) — leva para a reunião. |
| `quem_somos_TEMPLATE.md` | One-pager de **equipe** (preencher) — credibilidade na reunião. |
| `preparacao_reuniao.md` | As **3 perguntas difíceis** + respostas, roteiro de **demo do piloto** e checklist pré-reunião. |

## Fluxo sugerido
1. Ler `guia_abordagem.md`.
2. Abrir `alvos_prioritarios.xlsx`, escolher os 10–15 do topo, achar o **decisor** de cada (LinkedIn/site/CVM) e preencher.
3. Enviar e-mail (template de `emails.md`) com **2 anexos**: `Argus_Piloto_Tour.pdf` + one-pager do tipo certo. Follow-up em 5 dias úteis.
4. Interesse → reunião com o deck (`../../apresentacao/`) e o protótipo. Meta: sair com a reunião técnica marcada.

## Antes de disparar
- Ativar a caixa **contato@argusdtvm.com.br** (usada nos e-mails e one-pagers).
- Definir o **nome que assina** (placeholder `[Seu nome]` nos templates).
- Garantir o **SSL** do site no ar (os materiais apontam para argusdtvm.com.br).
