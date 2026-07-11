# -*- coding: utf-8 -*-
"""
Argus — One-pagers de abordagem, personalizados por tipo de instituição.
Gera 1 PPTX A4-retrato por tipo (Banco/CTVM, SCD/Fintech, Financeira), no visual da marca.
Depois exporte para PDF (PowerPoint COM) — ver README/abordagem.

Rodar: python onepagers.py
"""
from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE
import os

EMU_IN = 914400
NAVY   = RGBColor(0x6a, 0x50, 0xac); NAVY_DK = RGBColor(0x17, 0x12, 0x3a)  # roxo / roxo escuro (marca)
TEAL   = RGBColor(0x4a, 0x6d, 0xc0); GOLD = RGBColor(0x1f, 0xc0, 0xef)     # azul / ciano (marca)
INK    = RGBColor(0x2b, 0x32, 0x32); MUTED = RGBColor(0x5b, 0x6b, 0x7f)
FAINT  = RGBColor(0x90, 0x9d, 0xac); LINE = RGBColor(0xe7, 0xe8, 0xf2)
PAPER  = RGBColor(0xff, 0xff, 0xff); SOFT = RGBColor(0xf4, 0xf5, 0xfb)
D_TXT  = RGBColor(0xf3, 0xf6, 0xfa); D_MUT = RGBColor(0xa8, 0xb6, 0xc6); D_FAINT = RGBColor(0x6d, 0x80, 0x96)
D_LINE = RGBColor(0x2e, 0x26, 0x52)
SERIF, SANS, MONO = "Georgia", "Calibri", "Consolas"
PW, PH, M = 8.27, 11.69, 0.7  # A4 retrato

BASE = os.path.dirname(os.path.abspath(__file__))

def _run(run, t, size, font, color, bold, italic, char):
    run.text = t; f = run.font
    f.name = font; f.size = Pt(size); f.bold = bold; f.italic = italic; f.color.rgb = color
    if char is not None:
        run._r.get_or_add_rPr().set('spc', str(int(char * 100)))

def txt(s, x, y, w, h, text, size, *, font=SANS, color=INK, bold=False, italic=False,
        align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP, spacing=1.0, char=None):
    tb = s.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h)); tf = tb.text_frame
    tf.word_wrap = True; tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
    tf.vertical_anchor = anchor
    for i, line in enumerate(text.split('\n')):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = align; p.line_spacing = spacing; p.space_after = Pt(0); p.space_before = Pt(0)
        _run(p.add_run(), line, size, font, color, bold, italic, char)
    return tb

def rich(s, x, y, w, h, paras, anchor=MSO_ANCHOR.TOP):
    tb = s.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h)); tf = tb.text_frame
    tf.word_wrap = True; tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
    tf.vertical_anchor = anchor
    for i, (runs, o) in enumerate(paras):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = o.get('align', PP_ALIGN.LEFT); p.line_spacing = o.get('line', 1.0)
        p.space_before = Pt(o.get('sb', 0)); p.space_after = Pt(o.get('sa', 0))
        for (t, oo) in runs:
            _run(p.add_run(), t, oo.get('size', 12), oo.get('font', SANS), oo.get('color', INK),
                 oo.get('bold', False), oo.get('italic', False), oo.get('char'))
    return tb

def rect(s, x, y, w, h, fill=None, line=None, lw=0.75, radius=None):
    shp = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE if radius else MSO_SHAPE.RECTANGLE,
                             Inches(x), Inches(y), Inches(w), Inches(h))
    if radius is not None:
        try: shp.adjustments[0] = radius
        except Exception: pass
    if fill is None: shp.fill.background()
    else: shp.fill.solid(); shp.fill.fore_color.rgb = fill
    if line is None: shp.line.fill.background()
    else: shp.line.color.rgb = line; shp.line.width = Pt(lw)
    shp.shadow.inherit = False; return shp

def hline(s, x, y, w, color=LINE, weight=0.75):
    from pptx.enum.shapes import MSO_CONNECTOR
    ln = s.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Inches(x), Inches(y), Inches(x + w), Inches(y))
    ln.line.color.rgb = color; ln.line.width = Pt(weight); ln.shadow.inherit = False; return ln

def oval(s, cx, cy, r, fill=None, line=None, lw=1.0):
    shp = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(cx - r), Inches(cy - r), Inches(2 * r), Inches(2 * r))
    if fill is None: shp.fill.background()
    else: shp.fill.solid(); shp.fill.fore_color.rgb = fill
    if line is None: shp.line.fill.background()
    else: shp.line.color.rgb = line; shp.line.width = Pt(lw)
    shp.shadow.inherit = False; return shp

def _quad(p0, c, p2, n=16):
    out = []
    for i in range(n + 1):
        t = i / n; mt = 1 - t
        out.append((mt*mt*p0[0]+2*mt*t*c[0]+t*t*p2[0], mt*mt*p0[1]+2*mt*t*c[1]+t*t*p2[1]))
    return out

MARK = r"C:\Users\vinic\OneDrive\Desktop\dtvm\brand\favicon_argus.png"

def eye(s, cx, cy, height, outline=D_TXT):
    # marca oficial (malha roxo→ciano, fundo transparente) centrada em (cx, cy). favicon é quadrado.
    s.shapes.add_picture(MARK, Inches(cx - height/2.0), Inches(cy - height/2.0), height=Inches(height))

def kicker(s, x, y, text):
    hline(s, x, y + 0.08, 0.24, color=GOLD, weight=1.6)
    txt(s, x + 0.34, y, 6, 0.25, text.upper(), 8.5, font=MONO, color=GOLD, char=1.8)

def bullets(s, x, y, w, itens, gap=0.52):
    for i, it in enumerate(itens):
        yy = y + i * gap
        oval(s, x + 0.05, yy + 0.09, 0.04, fill=GOLD)
        txt(s, x + 0.22, yy, w - 0.22, gap, it, 10.5, font=SANS, color=INK, spacing=1.14)

def onepager(cfg):
    prs = Presentation(); prs.slide_width = Emu(int(PW*EMU_IN)); prs.slide_height = Emu(int(PH*EMU_IN))
    s = prs.slides.add_slide(prs.slide_layouts[6])
    s.background.fill.solid(); s.background.fill.fore_color.rgb = PAPER
    # faixa superior escura
    rect(s, 0, 0, PW, 2.75, fill=NAVY_DK)
    eye(s, M + 0.28, 0.98, 0.5)
    txt(s, M + 0.62, 0.72, 4, 0.4, "ARGUS", 20, font=SERIF, color=D_TXT, bold=True, char=0.5)
    txt(s, M + 0.64, 1.2, 6, 0.3, "ADMINISTRAÇÃO FIDUCIÁRIA E CUSTÓDIA DE FUNDOS", 7.5, font=MONO, color=D_MUT, char=1.5)
    rich(s, M, 1.68, PW - 2*M, 0.9, [([(cfg['titulo'], {'size': 21, 'font': SERIF, 'color': D_TXT})], {'line': 1.06})])
    txt(s, M, 2.42, PW - 2*M, 0.3, cfg['sub'], 10, font=SANS, color=GOLD, italic=True)

    y = 3.05
    kicker(s, M, y, "A proposta")
    txt(s, M, y + 0.32, PW - 2*M, 1.1, cfg['proposta'], 10.5, font=SANS, color=INK, spacing=1.22)

    y = 4.45
    kicker(s, M, y, cfg['porque_titulo'])
    bullets(s, M, y + 0.36, PW - 2*M, cfg['valor'])

    y = 4.45 + 0.36 + len(cfg['valor']) * 0.52 + 0.18
    rect(s, M, y, PW - 2*M, 0.92, fill=SOFT, line=LINE, radius=0.05)
    txt(s, M + 0.28, y + 0.16, 2.4, 0.3, "SEU PONTO DE PARTIDA", 8, font=MONO, color=NAVY, char=1.2, bold=True)
    txt(s, M + 0.28, y + 0.4, PW - 2*M - 0.56, 0.5, cfg['partida'], 10, font=SANS, color=INK, spacing=1.16)

    y = y + 1.15
    kicker(s, M, y, "A prova — não é ideia em guardanapo")
    txt(s, M, y + 0.34, PW - 2*M, 0.9,
        "Protótipo operacional de ponta a ponta no ar (argusdtvm.com.br, dados simulados) — do checklist de "
        "abertura ao informe diário à CVM. Dossiês de credenciamento prontos para protocolar: administração "
        "fiduciária (prazo legal de 60 dias) e custódia (90 dias), com manuais e matrizes de conformidade.",
        10.5, font=SANS, color=INK, spacing=1.22)

    y = y + 1.32
    rect(s, M, y, PW - 2*M, 0.95, fill=NAVY, radius=0.05)
    txt(s, M + 0.3, y + 0.16, 3.6, 0.4, "0,08% a.a. + R$ 100/mês", 15, font=SERIF, color=D_TXT, bold=True)
    txt(s, M + 0.3, y + 0.56, 4.2, 0.3, "4–5× mais barato — competitivo a partir de R$ 2 milhões de PL", 8.5, font=SANS, color=D_MUT)
    txt(s, PW - M - 2.7, y + 0.16, 2.4, 0.7, "Só 1.277 dos ~31 mil\nfundos passam de R$ 1 bi.\nA cauda pequena é enorme.",
        8.5, font=MONO, color=RGBColor(0xcf, 0xee, 0xfb), char=0.2, align=PP_ALIGN.RIGHT, spacing=1.12)

    # CTA explícito — material comercial precisa pedir
    y = y + 1.1
    rect(s, M, y, PW - 2*M, 0.44, fill=SOFT, line=GOLD, lw=1.0, radius=0.12)
    rich(s, M + 0.28, y + 0.12, PW - 2*M - 0.56, 0.3, [
        ([("PRÓXIMO PASSO   ", {'size': 9, 'font': MONO, 'color': NAVY, 'bold': True, 'char': 1.2}),
          ("20 minutos, sem compromisso — basta responder o e-mail.", {'size': 10.5, 'font': SANS, 'color': INK})], {'line': 1.0}),
    ])

    # rodapé
    hline(s, M, PH - 0.62, PW - 2*M, color=LINE)
    txt(s, M, PH - 0.5, 4.2, 0.25, "contato@argusdtvm.com.br  ·  argusdtvm.com.br", 8.5, font=MONO, color=NAVY, char=0.2)
    txt(s, PW - M - 2.9, PH - 0.5, 2.9, 0.25, "CONFIDENCIAL · DADOS SIMULADOS",
        7, font=MONO, color=FAINT, char=0.5, align=PP_ALIGN.RIGHT)

    out = os.path.join(BASE, cfg['arquivo']); prs.save(out); return out

PROPOSTA_PADRAO = (
    "Vocês entram com a licença e a estrutura que já têm; a Argus entra com a tecnologia e a operação como "
    "prestadora de serviço — vocês continuam o administrador de verdade, com o compliance de vocês no controle. "
    "Atendemos juntos um mercado que os grandes decidiram ignorar: fundos de R$ 1–10 milhões. A receita de "
    "administração começa dividida 50/50 — e vocês não investem em tecnologia nem contratam equipe.")

CFGS = [
    {"arquivo": "Argus_OnePager_Banco.pptx",
     "titulo": "Uma nova linha de receita recorrente\npara o seu banco.",
     "sub": "Administração fiduciária e custódia de fundos — proposta de parceria.",
     "proposta": PROPOSTA_PADRAO,
     "porque_titulo": "Por que faz sentido para o seu banco",
     "valor": ["Receita nova sobre uma estrutura que já existe e já custa — a licença já pagou a barreira de capital.",
               "Custo de entrada ~zero: sem investir em tecnologia nem contratar equipe.",
               "Custódia própria opcional na fase 2 — mais uma linha de receita sobre a mesma máquina.",
               "Risco reduzido, não aumentado: operação documentada, monitoramento diário em 100% dos fundos, trilha de auditoria completa."],
     "partida": "Vocês já são elegíveis (Res. CVM 21). Falta só o credenciamento de administrador fiduciário — prazo legal de 60 dias, com o dossiê que entregamos pronto para protocolar."},
    {"arquivo": "Argus_OnePager_CTVM.pptx",
     "titulo": "Da corretagem espremida à receita\nrecorrente — com a licença que você já tem.",
     "sub": "Administração fiduciária e custódia de fundos — proposta de parceria.",
     "proposta": PROPOSTA_PADRAO,
     "porque_titulo": "Por que faz sentido para a sua corretora",
     "valor": ["Receita recorrente (% do PL, todo mês) para equilibrar a receita transacional de corretagem.",
               "Vocês já são distribuidores natos — captação e base de clientes viram ativo da parceria.",
               "Elegíveis às DUAS licenças CVM: administração fiduciária e custódia (Res. 21 e Res. 32).",
               "Os gestores que hoje só executam com vocês passam a ter os fundos deles DENTRO de casa."],
     "partida": "Vocês já são elegíveis. Credenciamento de administrador fiduciário na CVM em ~60 dias, com o dossiê pronto — e a custódia (90 dias) quando a escala justificar."},
    {"arquivo": "Argus_OnePager_Financeira.pptx",
     "titulo": "A porta que abriu em 2025 — e quase\nninguém percebeu.",
     "sub": "Administração fiduciária de fundos — proposta de parceria para a sua financeira.",
     "proposta": ("Desde 1º/9/2025, a Resolução CMN 5.237 permite às financeiras (SCFI) administrar carteiras de "
                  "valores mobiliários (art. 6º, p.u., IV) — uma linha de receita nova que o mercado ainda não explorou. "
                  "Vocês entram com a instituição; a Argus entra com a tecnologia e a operação como prestadora de serviço. "
                  "Receita dividida 50/50, sem investir em tecnologia nem contratar equipe."),
     "porque_titulo": "Por que faz sentido para a sua financeira",
     "valor": ["Ser a PRIMEIRA financeira da sua praça a monetizar a Res. 5.237 — vantagem de pioneiro real.",
               "Diversificação recorrente (% do PL) descorrelacionada do ciclo de crédito.",
               "Custódia dos fundos terceirizada com custodiante autorizado — nós estruturamos, vocês não montam nada.",
               "Por ser instituição BCB, vocês podem contratar assessores de investimento para distribuir — canal de captação pronto."],
     "partida": "O objeto já comporta (Res. 5.237); falta o registro de administrador fiduciário na CVM — ~60 dias, com o dossiê que entregamos pronto para protocolar."},
]

if __name__ == "__main__":
    for c in CFGS:
        print("gerado:", onepager(c))
