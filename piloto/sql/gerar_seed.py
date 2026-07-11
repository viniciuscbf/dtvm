#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gerador determinístico do seed.sql do piloto (v2).
Uso: python3 gerar_seed.py > seed.sql
Seed fixa (42) → a demo é sempre reprodutível.

v2: senhas bcrypt, admin Vinicius Fernandes, snapshots de carteira de 22 dias úteis,
fechamentos de cota (prévia → aprovação do gestor → liberação), tokens de acesso de
cotistas (UUID), checklist documental de abertura (CVM 175) e lançamentos da controladoria.
"""
import math
import random
from datetime import date, timedelta

random.seed(42)

HOJE = date(2026, 7, 5)
LB = HOJE
while LB.weekday() >= 5:
    LB -= timedelta(days=1)

N_DIAS = 250
N_SNAP = 22          # dias úteis com snapshot de carteira (visão retroativa)
CDI_AA = 0.105
CDI_DIARIO = math.exp(math.log(1 + CDI_AA) / 252)

# hash bcrypt de 'demo123' (PHP password_verify aceita $2b$)
HASH = '$2b$10$BbDXQP.p1ZG5geegTfkOKOb8uzy0krgbeITn8z3j0KPMmeFzi2/7K'
ADMIN = 'Vinicius Fernandes'

def dias_uteis_ate(fim, n):
    ds, d = [], fim
    while len(ds) < n:
        if d.weekday() < 5:
            ds.append(d)
        d -= timedelta(days=1)
    return list(reversed(ds))

DIAS = dias_uteis_ate(LB, N_DIAS)
SNAPS = DIAS[-N_SNAP:]

def s(txt):
    return "'" + str(txt).replace("'", "''") + "'"

def br(v, dec=2):
    txt = f'{v:,.{dec}f}'
    return txt.replace(',', 'X').replace('.', ',').replace('X', '.')

out = []
def w(x=''):
    out.append(x)

w('-- =============================================================')
w('-- SEED v2 do piloto — gerado por gerar_seed.py (determinístico)')
w(f'-- Data de referência (D-1): {LB.isoformat()} · {N_DIAS} dias de cota · {N_SNAP} snapshots de carteira')
w('-- Senha de todos os usuários: demo123 (hash bcrypt)')
w('-- =============================================================')
w('SET NAMES utf8mb4;')
w()

# ------------------------------------------------------------------
# 1. FUNDOS
# ------------------------------------------------------------------
FUNDOS = [
    (1, 'Aurora RF Crédito Privado FI', '41.552.803/0001-19', 'Renda Fixa', 'Investidores em geral',
     'Aurora Capital Gestão', 42_000_000, 0.10, 0.000055, 0.00022),
    (2, 'Vetor Multimercado FIM', '38.204.117/0001-50', 'Multimercado', 'Qualificados',
     'Vetor Asset Management', 95_000_000, 0.20, 0.00012, 0.0042),
    (3, 'Lumen Debêntures Incentivadas FI', '44.876.230/0001-02', 'Renda Fixa', 'Investidores em geral',
     'Lumen Gestão de Recursos', 27_000_000, 0.0, 0.00007, 0.00035),
    (4, 'Horizonte Ações FIA', '36.998.410/0001-77', 'Ações', 'Investidores em geral',
     'Horizonte Investimentos', 18_000_000, 0.20, 0.00028, 0.011),
    (5, 'Pioneira Multiestratégia FIM', '40.113.552/0001-31', 'Multimercado', 'Qualificados',
     'Pioneira Capital', 12_000_000, 0.20, 0.00009, 0.0038),
    (6, 'Sertão Agro RF FI', '43.220.981/0001-64', 'Renda Fixa', 'Investidores em geral',
     'Sertão Agro Gestora', 8_500_000, 0.0, 0.00005, 0.0003),
    (7, 'Atlas Small Caps FIA', '45.671.309/0001-88', 'Ações', 'Qualificados',
     'Atlas Capital', 1_300_000, 0.20, -0.00012, 0.014),
    (9, 'Aurora II FIC RF Crédito Privado FI', '46.887.412/0001-30', 'Renda Fixa', 'Investidores em geral',
     'Aurora Capital Gestão', 8_000_000, 0.0, 0.00005, 0.00022),
]
FUNDO_ABERTURA = (8, 'Nova Fronteira RF Simples FI', 'em registro', 'Renda Fixa', 'Investidores em geral',
                  'Nova Fronteira Gestão')
GESTOR_DE = {1: 'Ricardo Nunes', 4: 'Luísa Andrade', 7: 'Otávio Ferraz', 9: 'Ricardo Nunes'}
def gestor_nome(fid):
    return GESTOR_DE.get(fid, 'Gestor (portal)')

series = {}
for fid, nome, cnpj, classe, pub, gest, pl_alvo, perf, drift, vol in FUNDOS:
    cota = 1.0
    cotas_serie = []
    for d in DIAS:
        base = (CDI_DIARIO - 1) if classe != 'Ações' else 0.0
        ret = base + drift + random.gauss(0, vol)
        cota *= (1 + ret)
        cotas_serie.append(cota)
    shares = pl_alvo / cotas_serie[-1]
    series[fid] = [(DIAS[i], cotas_serie[i], cotas_serie[i] * shares) for i in range(N_DIAS)]

w('-- ---------- fundos ----------')
for fid, nome, cnpj, classe, pub, gest, pl_alvo, perf, drift, vol in FUNDOS:
    cota_final = series[fid][-1][1]
    abertura = DIAS[0] - timedelta(days=random.randint(30, 400))
    w(f"INSERT INTO fundos (id, nome, cnpj, classe, publico_alvo, condominio, status, gestora, benchmark, "
      f"taxa_adm, taxa_gestao, taxa_performance, caixa_atual, pl_atual, cota_atual, data_abertura) VALUES "
      f"({fid}, {s(nome)}, {s(cnpj)}, {s(classe)}, {s(pub)}, 'Aberto', 'Ativo', {s(gest)}, 'CDI', "
      f"0.0008, 0.007, {perf}, 0, {pl_alvo:.2f}, {cota_final:.8f}, {s(abertura.isoformat())});")
fid8, n8, c8, cl8, p8, g8 = FUNDO_ABERTURA
w(f"INSERT INTO fundos (id, nome, cnpj, classe, publico_alvo, condominio, status, gestora, benchmark, "
  f"taxa_adm, taxa_gestao, taxa_performance, caixa_atual, pl_atual, cota_atual, data_abertura) VALUES "
  f"({fid8}, {s(n8)}, {s(c8)}, {s(cl8)}, {s(p8)}, 'Aberto', 'Em abertura', {s(g8)}, 'CDI', "
  f"0.0008, 0.007, 0, 0, 3000000.00, 1.0, NULL);")
w()

# ------------------------------------------------------------------
# 2. CDI + COTAS
# ------------------------------------------------------------------
w('-- ---------- cdi_historico ----------')
vals = ', '.join(f"({s(d.isoformat())}, {CDI_DIARIO + random.gauss(0, 2e-6):.10f})" for d in DIAS)
w(f'INSERT INTO cdi_historico (data_ref, fator_diario) VALUES {vals};')
w()

w('-- ---------- cotas_historico ----------')
for fid, *_ in FUNDOS:
    vals = ', '.join(f"({fid}, {s(d.isoformat())}, {c:.8f}, {pl:.2f})" for d, c, pl in series[fid])
    w(f'INSERT INTO cotas_historico (fundo_id, data_ref, valor_cota, pl) VALUES {vals};')
w()

# ------------------------------------------------------------------
# 3. CARTEIRAS — SNAPSHOTS DE 22 DIAS (coerentes com o PL diário)
# ------------------------------------------------------------------
ATIVOS_RF = [
    ('LFT 2029', 'Título Público', 'ANBIMA', 16000.0), ('LTN 2028', 'Título Público', 'ANBIMA', 780.0),
    ('NTN-B 2030', 'Título Público', 'ANBIMA', 4300.0), ('DEB VALE29', 'Debênture', 'ANBIMA', 1050.0),
    ('DEB ENGIE31', 'Debênture', 'ANBIMA', 985.0), ('DEB LIGHT27', 'Debênture', 'Comitê', 870.0),
    ('CDB BCO MÉTRICA', 'CDB', 'Comitê', 1120.0), ('CRA AGRO25', 'CRI/CRA', 'ANBIMA', 1010.0),
    ('CRI URBE28', 'CRI/CRA', 'ANBIMA', 990.0),
]
ATIVOS_ACOES = [
    ('PETR4', 'Ação', 'B3', 41.20), ('VALE3', 'Ação', 'B3', 68.35), ('ITUB4', 'Ação', 'B3', 37.90),
    ('BBDC4', 'Ação', 'B3', 16.45), ('WEGE3', 'Ação', 'B3', 52.10), ('ABEV3', 'Ação', 'B3', 13.25),
    ('B3SA3', 'Ação', 'B3', 12.80), ('PRIO3', 'Ação', 'B3', 44.75), ('SMAL11', 'Cota de Fundo', 'B3', 118.40),
    ('NORD3', 'Ação', 'Comitê', 9.85), ('TASA4', 'Ação', 'B3', 18.30),
]
w('-- ---------- ativos_carteira (snapshots diários) ----------')
linhas_ativos = []
NORD3 = None
pl_por_dia = {fid: {d.isoformat(): pl for d, c, pl in series[fid]} for fid, *_ in FUNDOS}
for fid, nome, cnpj, classe, pub, gest, pl_alvo, *_ in FUNDOS:
    caixa_pct = random.uniform(0.03, 0.07)
    caixa = pl_alvo * caixa_pct
    alvo_ativos = pl_alvo - caixa
    if classe == 'Renda Fixa':
        universo = ATIVOS_RF; n = random.randint(6, 8)
    elif classe == 'Ações':
        universo = ATIVOS_ACOES; n = random.randint(8, 10)
    else:
        universo = ATIVOS_RF[:5] + ATIVOS_ACOES[:5]; n = random.randint(8, 11)
    escolhidos = random.sample(universo, min(n, len(universo)))
    if fid == 7:
        escolhidos = [a for a in escolhidos if a[0] != 'NORD3']
        escolhidos.insert(0, ('NORD3', 'Ação', 'Comitê', 9.85))
    if fid == 3 and not any(a[0] == 'CRI URBE28' for a in escolhidos):
        escolhidos[-1] = ('CRI URBE28', 'CRI/CRA', 'ANBIMA', 990.0)   # o alerta R2 referencia este ativo
    if fid == 2 and not any(a[0] == 'DEB VALE29' for a in escolhidos):
        escolhidos[-1] = ('DEB VALE29', 'Debênture', 'ANBIMA', 1050.0)  # conciliação/liquidação referenciam
    if fid == 9:  # FIC: a carteira é 100%% cotas do master (estrutura FIC→master, mesma conta de gestor)
        escolhidos = [('Cotas Aurora Master', 'Cota de Fundo', 'Cota do fundo', round(series[1][-1][1], 6))]
    pesos = [random.uniform(0.6, 1.6) for _ in escolhidos]
    if classe == 'Multimercado':
        pesos = [p * (0.55 if t in ('Ação', 'Cota de Fundo') else 1.0)
                 for p, (_, t, _, _) in zip(pesos, escolhidos)]
    if fid == 7:
        pesos[0] = 3.2
    tot = sum(pesos)
    # define quantidade e preço final por ativo (dia D-1)
    ativos_fundo = []
    for (cod, tipo, fonte, preco), peso in zip(escolhidos, pesos):
        valor = alvo_ativos * peso / tot
        mam_final = preco * random.uniform(0.97, 1.03)
        qtd = max(1, round(valor / mam_final))
        pm = mam_final / (1 + random.uniform(-0.06, 0.10))
        ativos_fundo.append({'cod': cod, 'tipo': tipo, 'fonte': fonte, 'qtd': qtd, 'pm': pm, 'mam': mam_final})
    # caixa exato do fundo: pl_final - soma dos ativos no dia final
    soma_final = sum(a['qtd'] * a['mam'] for a in ativos_fundo)
    caixa = pl_alvo - soma_final
    w(f"UPDATE fundos SET caixa_atual = {caixa:.2f} WHERE id = {fid};")
    carteiras_final = globals().setdefault('CARTEIRAS_FINAL', {})
    carteiras_final[fid] = ativos_fundo
    # snapshots: caminhada de preços para trás, reescalada para casar com o PL do dia
    walks = {a['cod']: 1.0 for a in ativos_fundo}
    fatores = {}
    for d in reversed(SNAPS):
        fatores[d] = dict(walks)
        for cod in walks:
            walks[cod] *= 1 + random.gauss(0, 0.004)
    for d in SNAPS:
        pl_dia = pl_por_dia[fid][d.isoformat()]
        alvo_dia = pl_dia - caixa
        raw = {a['cod']: a['mam'] * fatores[d][a['cod']] for a in ativos_fundo}
        soma_raw = sum(a['qtd'] * raw[a['cod']] for a in ativos_fundo)
        k = alvo_dia / soma_raw if soma_raw > 0 else 1.0
        for a in ativos_fundo:
            mam_d = raw[a['cod']] * k
            if fid == 7 and a['cod'] == 'NORD3':
                ref_d = mam_d / 1.082      # 8,2% acima da referência → alerta R1
            else:
                ref_d = mam_d * random.uniform(0.996, 1.004)
            linhas_ativos.append(
                f"({fid}, {s(a['cod'])}, {s(a['tipo'])}, {a['qtd']}, {a['pm']:.6f}, {mam_d:.6f}, {ref_d:.6f}, "
                f"{s(a['fonte'])}, {s(d.isoformat())})")
            if fid == 7 and a['cod'] == 'NORD3' and d == SNAPS[-1]:
                NORD3 = {'mam': mam_d, 'ref': ref_d, 'qtd': a['qtd'], 'valor': a['qtd'] * mam_d,
                         'pct': a['qtd'] * mam_d / pl_alvo * 100}
w('INSERT INTO ativos_carteira (fundo_id, codigo, tipo, quantidade, preco_medio, preco_mam, preco_referencia, fonte_preco, data_ref) VALUES')
w(',\n'.join(linhas_ativos) + ';')
w()

# ------------------------------------------------------------------
# 4. COTISTAS + USUÁRIOS + TOKENS
# ------------------------------------------------------------------
NOMES = ['Marina Costa', 'João Pereira', 'Fernanda Lima', 'Carlos Souza', 'Beatriz Rocha', 'André Martins',
         'Paula Gonçalves', 'Ricardo Alves', 'Juliana Freitas', 'Marcos Vieira', 'Sofia Cardoso', 'Pedro Ramos',
         'Larissa Melo', 'Gustavo Pinto', 'Camila Barros', 'Felipe Duarte', 'Renata Moraes', 'Thiago Neves',
         'Aline Castro', 'Bruno Teixeira', 'Vitória Santana', 'Eduardo Farias', 'Natália Cunha', 'Rafael Borges',
         'Helena Dias', 'Otávio Prado', 'Cecília Nunes', 'Diego Sales', 'Isabela Fontes', 'Leandro Matos']
PJS = ['Holding Miradouro LTDA', 'Família Azevedo Participações', 'Vila Rica Investimentos SA',
       'Boa Vista Participações', 'Alameda Capital LTDA', 'Serra Verde Holding']

w('-- ---------- cotistas ----------')
cot_id = 0
cotistas_por_fundo = {}
linhas = []
qtd_cotistas = {1: 24, 2: 40, 3: 18, 4: 15, 5: 9, 6: 7, 7: 5, 9: 12}
for fid, nome, cnpj, classe, pub, gest, pl_alvo, *_ in FUNDOS:
    n = qtd_cotistas[fid]
    cota_final = series[fid][-1][1]
    tot_cotas = pl_alvo / cota_final
    pesos = sorted([random.paretovariate(1.6) for _ in range(n)], reverse=True)
    tot_p = sum(pesos)
    ids = []
    for i in range(n):
        cot_id += 1
        ids.append(cot_id)
        pj = random.random() < 0.2
        nome_c = random.choice(PJS) if pj else NOMES[(cot_id * 7) % len(NOMES)]
        doc = (f"{random.randint(10,99)}.{random.randint(100,999)}.{random.randint(100,999)}/0001-{random.randint(10,99)}"
               if pj else f"***.{random.randint(100,999)}.{random.randint(100,999)}-**")
        cotas = tot_cotas * pesos[i] / tot_p
        entrada = DIAS[0] + timedelta(days=random.randint(0, 200))
        linhas.append(f"({cot_id}, {fid}, {s(nome_c)}, {s(doc)}, {s('PJ' if pj else 'PF')}, "
                      f"{cotas:.6f}, {s(entrada.isoformat())})")
    cotistas_por_fundo[fid] = ids
w('INSERT INTO cotistas (id, fundo_id, nome, documento, tipo_pessoa, cotas, data_entrada) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- usuarios (senha demo123, hash bcrypt) ----------')
w("INSERT INTO usuarios (id, nome, email, senha, perfil, fundo_id, gestora, telefone, kyc_status) VALUES")
w(f"(1, {s(ADMIN)}, 'admin@administradora.com.br', {s(HASH)}, 'admin', NULL, NULL, NULL, 'Aprovado'),")
w(f"(2, 'Ricardo Nunes', 'gestor@auroracapital.com.br', {s(HASH)}, 'gestor', 1, 'Aurora Capital Gestão', '(11) 98765-1001', 'Aprovado'),")
w(f"(3, 'Luísa Andrade', 'gestor@horizonteinvest.com.br', {s(HASH)}, 'gestor', 4, 'Horizonte Investimentos', '(11) 98765-1004', 'Aprovado'),")
w(f"(4, 'Otávio Ferraz', 'gestor@atlascapital.com.br', {s(HASH)}, 'gestor', 7, 'Atlas Capital', '(11) 98765-1007', 'Pendente'),")
w(f"(5, 'Camila Duarte', 'gestor@novafronteira.com.br', {s(HASH)}, 'gestor', 8, 'Nova Fronteira Gestão', '(11) 98765-1008', 'Pendente');")
w()
w('-- ---------- usuario_fundos (conta pode ter vários fundos: FIC/master, subclasses) ----------')
w('INSERT INTO usuario_fundos (usuario_id, fundo_id) VALUES (2,1),(2,9),(3,4),(4,7),(5,8);')
w()

w('-- ---------- tokens_acesso (UUIDs fixos p/ demo) ----------')
w("INSERT INTO tokens_acesso (fundo_id, token, nivel, descricao, status, criado_por, criado_em, revogado_em) VALUES")
w(f"(1, '3f2a1c9e-8b47-4d10-9e2f-6a5d4c3b2a19', 'realtime', 'Holding Miradouro (institucional)', 'Ativo', 'Ricardo Nunes', {s(DIAS[-15].isoformat() + ' 10:20:00')}, NULL),")
w(f"(1, '7c9e4b2a-1f3d-4e8c-a6b5-0d9f8e7c6b5a', 'delay_1m', 'Família Azevedo', 'Ativo', 'Ricardo Nunes', {s(DIAS[-30].isoformat() + ' 15:05:00')}, NULL),")
w(f"(1, 'a1b2c3d4-e5f6-4789-8abc-def012345678', 'delay_3m', 'Investidor pessoa física — teste', 'Revogado', 'Ricardo Nunes', {s(DIAS[-60].isoformat() + ' 09:00:00')}, {s(DIAS[-10].isoformat() + ' 11:30:00')}),")
w(f"(4, '9d8c7b6a-5e4f-4321-b0a9-8c7d6e5f4a3b', 'delay_3m', 'Cotistas do clube (visão padrão)', 'Ativo', 'Luísa Andrade', {s(DIAS[-20].isoformat() + ' 14:45:00')}, NULL);")
w()

# ------------------------------------------------------------------
# 5. FECHAMENTOS DE COTA (prévia → aprovação → liberação)
# ------------------------------------------------------------------
w('-- ---------- fechamentos ----------')
linhas = []
serie_por_data = {fid: {d.isoformat(): (c, pl) for d, c, pl in series[fid]} for fid, *_ in FUNDOS}
for fid, *_ in FUNDOS:
    for d in SNAPS[:-1]:   # dias anteriores a D-1: ciclo completo (aprovada + liberada)
        cota, pl = serie_por_data[fid][d.isoformat()]
        dia_seg = d + timedelta(days=1)
        linhas.append(f"({fid}, {s(d.isoformat())}, 1, {cota:.8f}, {pl:.2f}, 'Aprovada', 1, "
                      f"{s(d.isoformat() + ' 07:%02d:00' % random.randint(5, 55))}, {s(gestor_nome(fid))}, "
                      f"{s(dia_seg.isoformat() + ' 09:%02d:00' % random.randint(0, 59))}, NULL)")
d1 = SNAPS[-1].isoformat()
# D-1: casos diferentes por fundo (a demo do ciclo)
cota1, pl1 = serie_por_data[1][d1]
linhas.append(f"(1, {s(d1)}, 1, {cota1:.8f}, {pl1:.2f}, 'Aguardando aprovação', 0, {s(d1 + ' 07:12:00')}, NULL, NULL, NULL)")
cota2, pl2 = serie_por_data[2][d1]
linhas.append(f"(2, {s(d1)}, 1, {cota2 * 1.00031:.8f}, {pl2 * 1.00031:.2f}, 'Rejeitada', 0, {s(d1 + ' 07:18:00')}, "
              f"'Gestor (portal)', {s(d1 + ' 10:05:00')}, 'Preço da DEB VALE29 divergente do nosso controle interno (usamos fechamento ANBIMA)')")
linhas.append(f"(2, {s(d1)}, 2, {cota2:.8f}, {pl2:.2f}, 'Aguardando aprovação', 0, {s(d1 + ' 11:40:00')}, NULL, NULL, "
              f"'Reprocessamento após ajuste de preço da DEB VALE29 (lançamento da controladoria)')")
for fid in (3, 4, 6, 9):
    c, p = serie_por_data[fid][d1]
    linhas.append(f"({fid}, {s(d1)}, 1, {c:.8f}, {p:.2f}, 'Aguardando aprovação', 0, {s(d1 + ' 07:%02d:00' % random.randint(10, 50))}, NULL, NULL, NULL)")
c5, p5 = serie_por_data[5][d1]
linhas.append(f"(5, {s(d1)}, 1, {c5:.8f}, {p5:.2f}, 'Aprovada', 1, {s(d1 + ' 07:22:00')}, 'Gestor (portal)', {s(d1 + ' 09:31:00')}, NULL)")
# fundo 7: batch travado → sem prévia ainda (nenhuma linha em D-1)
w('INSERT INTO fechamentos (fundo_id, data_ref, versao, valor_cota, pl, status, liberado_download, calculada_em, decidido_por, decidido_em, motivo) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- lancamentos (trilha da controladoria) ----------')
w("INSERT INTO lancamentos (fundo_id, data_ref, tipo, ativo_codigo, descricao, valor_antigo, valor_novo, valor_caixa, autor, criado_em) VALUES")
w(f"(2, {s(d1)}, 'Ajuste de preço', 'DEB VALE29', 'Cota rejeitada pelo gestor: preço divergente. Ajustado para o fechamento ANBIMA.', 1049.310000, 1032.480000, NULL, {s(ADMIN)}, {s(d1 + ' 11:32:00')}),")
w(f"(6, {s(SNAPS[-4].isoformat())}, 'Provento', NULL, 'Cupom de CRA AGRO25 não capturado na importação do custodiante — lançado manualmente.', NULL, NULL, 18740.00, {s(ADMIN)}, {s(SNAPS[-4].isoformat() + ' 14:20:00')}),")
w(f"(1, {s(SNAPS[-8].isoformat())}, 'Taxa/Despesa', NULL, 'Ajuste de provisão da taxa de auditoria (competência anterior).', NULL, NULL, -1500.00, {s(ADMIN)}, {s(SNAPS[-8].isoformat() + ' 16:02:00')});")
w()

# ------------------------------------------------------------------
# 6. MOVIMENTAÇÕES + PREVISÃO
# ------------------------------------------------------------------
w('-- ---------- mov_cotistas ----------')
linhas = []
for fid, *_ in FUNDOS:
    cota_final = series[fid][-1][1]
    for _ in range(random.randint(8, 14)):
        cid = random.choice(cotistas_por_fundo[fid])
        d = random.choice(DIAS[-130:])
        tipo = 'Aplicação' if random.random() < 0.62 else 'Resgate'
        valor = round(random.uniform(8_000, 400_000), 2)
        linhas.append(f"({fid}, {cid}, {s(d.isoformat())}, {s(tipo)}, {valor:.2f}, "
                      f"{valor / cota_final:.6f}, {s(d.isoformat())}, {s((d + timedelta(days=2)).isoformat())})")
w('INSERT INTO mov_cotistas (fundo_id, cotista_id, data_ref, tipo, valor, cotas, data_cotizacao, data_liquidacao) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- movimentacoes (caixa, 6 meses) ----------')
TIPOS_MOV = [('Aplicação', 1, 'Aplicação de cotista'), ('Resgate', -1, 'Resgate de cotista'),
             ('Provento', 1, 'Dividendos / cupom recebido'), ('Taxa', -1, 'Taxa de administração'),
             ('Liquidação Compra', -1, 'Liquidação de compra de ativo'),
             ('Liquidação Venda', 1, 'Liquidação de venda de ativo')]
linhas = []
for fid, nome, cnpj, classe, pub, gest, pl_alvo, *_ in FUNDOS:
    for _ in range(random.randint(20, 28)):
        d = random.choice(DIAS[-126:])
        tipo, sinal, desc = random.choice(TIPOS_MOV)
        base = pl_alvo * random.uniform(0.001, 0.012)
        if tipo == 'Taxa':
            base = max(100.0, pl_alvo * 0.0008 / 12)
        linhas.append(f"({fid}, {s(d.isoformat())}, {s(tipo)}, {s(desc)}, {sinal * base:.2f})")
d_atipica = DIAS[-6]
linhas.append(f"(1, {s(d_atipica.isoformat())}, 'Resgate', 'Resgate atípico — 3,8σ acima do padrão histórico', -2850000.00)")
w('INSERT INTO movimentacoes (fundo_id, data_ref, tipo, descricao, valor) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- previsao_caixa (60 dias à frente) ----------')
linhas = []
for fid, nome, cnpj, classe, pub, gest, pl_alvo, *_ in FUNDOS:
    for _ in range(random.randint(5, 8)):
        d = HOJE + timedelta(days=random.randint(2, 58))
        tipo, sinal, desc = random.choice([
            ('Provento a receber', 1, 'Cupom / dividendo previsto'),
            ('Resgate agendado', -1, 'Resgate com cotização futura'),
            ('Taxa a pagar', -1, 'Taxa de administração da competência'),
            ('Vencimento de título', 1, 'Vencimento com liquidação em conta')])
        base = pl_alvo * random.uniform(0.001, 0.01)
        if tipo == 'Taxa a pagar':
            base = max(100.0, pl_alvo * 0.0008 / 12)
        linhas.append(f"({fid}, {s(d.isoformat())}, {s(tipo)}, {s(desc)}, {sinal * base:.2f})")
linhas.append(f"(1, {s((HOJE + timedelta(days=9)).isoformat())}, 'Resgate agendado', "
              f"'Resgate institucional agendado (Holding Miradouro)', -3900000.00)")
linhas.append(f"(1, {s((HOJE + timedelta(days=16)).isoformat())}, 'Vencimento de título', "
              f"'Vencimento LTN — recompõe o caixa', 4200000.00)")
w('INSERT INTO previsao_caixa (fundo_id, data_prevista, tipo, descricao, valor) VALUES')
w(',\n'.join(linhas) + ';')
w()

# ------------------------------------------------------------------
# 7. PROCESSAMENTO (batch D-1) + LOG
# ------------------------------------------------------------------
w('-- ---------- processamento (batch de D-1) ----------')
ETAPAS = ['Posição', 'Preços', 'Caixa', 'Conciliação', 'Cota', 'ANBIMA']
linhas = []
for fid, *_ in FUNDOS:
    hora = 6 * 60 + random.randint(0, 40)
    for i, etapa in enumerate(ETAPAS):
        hora += random.randint(3, 12)
        hh, mm = divmod(hora, 60)
        status, mensagem = 'OK', 'Executado sem ocorrências'
        if fid == 7:
            if i == 1:
                status, mensagem = 'Erro', 'Cotação de fechamento de NORD3 não recebida da B3 — reprocessar; marcar pelo último preço de mercado até a B3 publicar'
            elif i > 1:
                status, mensagem = 'Pendente', 'Aguardando etapa anterior'
        if fid == 5 and i == 5:
            status, mensagem = 'Pendente', 'Na fila de envio do webservice ANBIMA'
        htxt = f"'{hh:02d}:{mm:02d}:00'" if status == 'OK' else 'NULL'
        linhas.append(f"({fid}, {s(LB.isoformat())}, {s(etapa)}, {i + 1}, {s(status)}, {htxt}, {s(mensagem)})")
w('INSERT INTO processamento (fundo_id, data_ref, etapa, ordem, status, horario, mensagem) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- log_processamento ----------')
logs = [
    (7, 'Preços', 'ERRO', 'NORD3 sem cotação de fechamento na B3 no dia — marcada pelo último preço de mercado; reprocessar quando a B3 publicar'),
    (7, 'Posição', 'INFO', 'Posição do custodiante importada: 9 ativos'),
    (5, 'ANBIMA', 'WARN', 'Webservice ANBIMA com latência alta — reenvio automático programado'),
    (2, 'Cota', 'WARN', 'Prévia v1 REJEITADA pelo gestor: preço da DEB VALE29 divergente'),
    (2, 'Cota', 'INFO', 'Preço ajustado via lançamento; prévia v2 reenviada ao gestor'),
    (1, 'Cota', 'INFO', 'Prévia v1 enviada ao gestor para aprovação'),
    (1, 'Conciliação', 'INFO', 'Caixa × extrato: 100% conciliado'),
    (3, 'Preços', 'INFO', 'DEB LIGHT27 precificada por curva do comitê (ata de 30/06)'),
    (5, 'Cota', 'INFO', 'Cota aprovada pelo gestor e publicada; download liberado'),
    (6, 'Caixa', 'INFO', 'Extrato importado do banco custodiante: 12 lançamentos'),
]
vals = ', '.join(
    f"({fid}, {s(LB.isoformat())}, {s(et)}, {s(nv)}, {s(msg)}, {s(LB.isoformat() + ' 07:' + str(10 + i * 4) + ':00')})"
    for i, (fid, et, nv, msg) in enumerate(logs))
w(f'INSERT INTO log_processamento (fundo_id, data_ref, etapa, nivel, mensagem, criado_em) VALUES {vals};')
w()

# ------------------------------------------------------------------
# 8. CONCILIAÇÃO
# ------------------------------------------------------------------
w('-- ---------- conciliacao ----------')
FRENTES = ['Posição × Custodiante', 'Operações × Gestor', 'Caixa × Extrato']
linhas = []
for fid, *_ in FUNDOS:
    for fr in FRENTES:
        if fid == 2 and fr == 'Operações × Gestor':
            linhas.append(f"({fid}, {s(LB.isoformat())}, {s(fr)}, 'Divergente', 'Timing', "
                          f"'Boleta de compra de DEB VALE29 lançada pelo gestor em D0; custodiante registra liquidação em D+1', 412350.00, NULL, NULL, NULL)")
        elif fid == 6 and fr == 'Caixa × Extrato':
            linhas.append(f"({fid}, {s(LB.isoformat())}, {s(fr)}, 'Divergente', 'Timing', "
                          f"'Provento creditado no extrato ainda não refletido na posição de caixa interna', 18740.00, NULL, NULL, NULL)")
        elif fid == 7 and fr == 'Posição × Custodiante':
            qtd_cust = int(NORD3['qtd'] * 0.83)
            dif = (NORD3['qtd'] - qtd_cust) * NORD3['mam']
            det = (f"Quantidade de NORD3 na carteira ({NORD3['qtd']}) difere da posição do custodiante "
                   f"({qtd_cust}) — sem lastro para {NORD3['qtd'] - qtd_cust} ações")
            linhas.append(f"({fid}, {s(LB.isoformat())}, {s(fr)}, 'Divergente', 'Suspeita', {s(det)}, {dif:.2f}, NULL, NULL, NULL)")
        else:
            linhas.append(f"({fid}, {s(LB.isoformat())}, {s(fr)}, 'Conciliado', NULL, NULL, 0, NULL, NULL, NULL)")
d1r = DIAS[-4]; d2r = DIAS[-9]
linhas.append(f"(4, {s(d1r.isoformat())}, 'Caixa × Extrato', 'Resolvido', 'Erro', "
              f"'Tarifa bancária lançada em duplicidade pelo custodiante', 89.90, "
              f"'Estorno confirmado pelo custodiante em D+1', {s(ADMIN)}, {s(d1r.isoformat() + ' 15:42:00')})")
linhas.append(f"(1, {s(d2r.isoformat())}, 'Operações × Gestor', 'Resolvido', 'Timing', "
              f"'Aplicação de cotista processada após o corte — diferença de D+1 na cotização', 150000.00, "
              f"'Regularizada no batch seguinte; sem impacto na cota', {s(ADMIN)}, {s(d2r.isoformat() + ' 11:07:00')})")
w('INSERT INTO conciliacao (fundo_id, data_ref, origem, situacao, classificacao, detalhe, valor_diferenca, resolucao, resolvido_por, resolvido_em) VALUES')
w(',\n'.join(linhas) + ';')
w()

# ------------------------------------------------------------------
# 9. ALERTAS DE IA + PARTES RELACIONADAS
# ------------------------------------------------------------------
w('-- ---------- alertas_fraude ----------')
qtd_cust = int(NORD3['qtd'] * 0.83)
dif_qtd = NORD3['qtd'] - qtd_cust
dif_val = dif_qtd * NORD3['mam']
alertas = [
    (3, 'R1', 'Negócio a preço fora de mercado', 'Alta',
     "O fundo comprou o CRI URBE28 a R$ 1.140,00 — 12,7% acima da marcação de referência da administradora na data "
     "(R$ 1.011,50). A administradora marca o ativo de forma independente; pagar acima dessa referência numa compra drena "
     "o fundo em favor da contraparte. A originadora é a XYZ Securitizadora, parte relacionada ao gestor (ver R4).",
     "Preço da boleta de compra R$ 1.140,00 × marcação de referência R$ 1.011,50 · sobrepreço +12,7% · "
     "banda p/ ilíquido: 10% · contraparte XYZ Securitizadora (balcão, parte relacionada)", 'Aberto'),
    (3, 'R2', 'Valuation de ilíquido (nível 3) sem lastro', 'Alta',
     "Após comprar o CRI URBE28 acima do mercado (ver R1), o gestor propôs elevar a marcação do papel a R$ 1.050 com base "
     "em laudo cujas premissas não batem com transações comparáveis (~R$ 990) de risco e prazo equivalentes. Elevar a "
     "marcação mascararia a perda da compra e inflaria a cota. Por ser ativo nível 3 (sem preço observável) e com emissor "
     "parte relacionada, a administradora manteve a marcação independente (~R$ 1.011) e reteve a proposta, pedindo reforço documental.",
     "Laudo do gestor R$ 1.050 × comparáveis ~R$ 990 · marcação independente da adm. R$ 1.011 · ativo nível 3 (CPC 46/IFRS 13) · "
     "emissor parte relacionada · proposta retida pela controladoria", 'Em revisão'),
    (3, 'R4', 'Parte relacionada / conflito', 'Alta',
     'A contraparte XYZ Securitizadora, que originou o CRI URBE28 comprado pelo fundo, tem como sócio majoritário Carlos Mendes (71%) — '
     'que também é sócio da Lumen Gestão de Recursos (62%), gestora do fundo. Operação entre partes relacionadas sem divulgação; '
     'beneficiário final comum acima de 25%.',
     'CNPJ da securitizadora ↔ QSA da gestora · Carlos Mendes 71% XYZ / 62% Lumen · beneficiário final comum >25% · ver grafo de vínculos', 'Aberto'),
    (7, 'R5', 'Conciliação de custódia (sem lastro)', 'Alta',
     'O gestor lançou a compra de 5.668 ações NORD3 (≈ R$ 55,8 mil) que a conciliação diária não encontrou na posição '
     'confirmada pelo custodiante/B3 — os livros mostram 33.337 e a custódia 27.669. É boleta não liquidada ou lançamento sem '
     'lastro: a conciliação reteve o fechamento da cota até esclarecer a origem. Como o papel é listado, a B3 é a fonte '
     'autoritativa e a diferença tende a ser falha de liquidação; persistindo sem lastro, escala para fraude.',
     'Livros 33.337 × custodiante/B3 27.669 · diferença 5.668 ações ≈ R$ 55.789 · conciliação diária (Res. CVM 32) · boleta não confirmada · fechamento retido', 'Aberto'),
    (7, 'R6', 'Concentração acima do limite', 'Média',
     'NORD3 representa 25,2% do PL do fundo — acima do limite de 10% por emissor companhia aberta (art. 44). A concentração, '
     'somada à divergência de custódia (ver R5) e à baixa liquidez do papel, eleva o risco. Gestor notificado para reenquadrar; '
     'desenquadramento comunicável à CVM se não sanado no prazo.',
     'NORD3 25,2% do PL × limite 10% (cia aberta, art. 44 Anexo I) · enquadramento em aberto · liquidez baixa (small cap)', 'Aberto'),
    (4, 'R8', 'Movimentação atípica', 'Média',
     'Resgate de R$ 377,2 mil — muito acima do padrão histórico de movimentação do fundo (3,4 desvios). Pode ser legítimo '
     '(investidor institucional reduzindo posição), mas o valor exige verificação e, se confirmada a atipicidade, análise para eventual comunicação ao COAF.',
     'Resgate R$ 377.203 · média histórica R$ 95 mil · z-score 3,4 · limiar da regra: 3,0σ', 'Aberto'),
    (2, 'R9', 'Front-running / timing de remarcação', 'Média',
     'Aplicação relevante de cotista na véspera de uma remarcação positiva de ativo de crédito do fundo. O padrão '
     'aplicação → remarcação → ganho imediato pode indicar uso de informação sobre a precificação. O motor cruza o passivo '
     '(cotização) com o calendário de remarcação; sob revisão para descartar coincidência.',
     'Aplicação R$ 620 mil em D-1 · remarcação +2,4% em D0 · ganho imediato ≈ R$ 14,9 mil · referência: caso BB Asset (front-running)', 'Em revisão'),
    (2, 'R12', 'Ida-e-volta / fracionamento (PLD)', 'Média',
     'Cotista aplicou e resgatou montante equivalente em poucos dias, sem movimentação intermediária nem racional econômico — '
     'padrão de "ida e volta" que dá aparência lícita a recursos. Sinal de alerta de PLD; em análise para eventual comunicação '
     'de operação suspeita ao COAF.',
     'Aplicação R$ 285 mil → resgate R$ 280 mil em 4 dias · sem operação intermediária · Res. CVM 50 art. 20 · COS sem valor mínimo, COAF em 24h', 'Aberto'),
    (4, 'R11', 'Concentração de resgates', 'Baixa',
     'Resgates somaram 11,7% do PL em 5 dias úteis — acima da banda normal do fundo, abaixo do nível crítico. Monitorando '
     'liquidez: o ativo conversível na janela precisa cobrir o passivo exigível; risco de venda forçada de ativo menos líquido se o ritmo acelerar.',
     'Resgates R$ 2,1 mi / PL R$ 18 mi em 5 d.u. · limiar de atenção: 10% · teste de liquidez por bucket (D+0, D+1)', 'Aberto'),
    (1, 'R13', 'KYC/PLD incompleto / beneficiário final', 'Baixa',
     'Bloco de cotistas do fundo com KYC/PLD ainda "Pendente" e sem confirmação de beneficiário final. O cadastro primário é '
     'do distribuidor, mas a administradora, no monitoramento residual do passivo, sinaliza para regularização e classificação de PEP antes de novas movimentações.',
     '12 cotistas com KYC/PLD pendente · beneficiário final (>25%) não confirmado · Res. CVM 50 arts. 11-16 · classificação PEP pendente', 'Aberto'),
]
linhas = []
for fid, regra, tipo, sev, expl, evid, status in alertas:
    linhas.append(f"({fid}, {s(LB.isoformat())}, {s(regra)}, {s(tipo)}, {s(sev)}, {s(expl)}, {s(evid)}, {s(status)}, NULL, NULL, NULL)")
linhas.append(f"(5, {s(DIAS[-12].isoformat())}, 'R14', 'Cota anômala', 'Baixa', "
              f"'Retorno diário de +0,9% destoou da série histórica do fundo (4,2σ). Verificado: reprecificação legítima de posição vendida em dólar após feriado nos EUA — sem indício de irregularidade.', "
              f"'Retorno +0,9% · σ diário 0,21% · z-score 4,2 · verificação concluída', 'Falso positivo', {s(ADMIN)}, "
              f"{s(DIAS[-11].isoformat() + ' 09:35:00')}, 'Reprecificação legítima pós-feriado; documentação anexada ao dossiê do fundo')")
w('INSERT INTO alertas_fraude (fundo_id, data_ref, regra, tipo, severidade, explicacao, evidencia, status, tratado_por, tratado_em, justificativa) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- partes_relacionadas (grafo do fundo 3) ----------')
partes = [
    (3, 'Lumen Gestão de Recursos (gestora)', 'Lumen Debêntures Incentivadas FI', 'gestão', 0),
    (3, 'Lumen Gestão de Recursos (gestora)', 'Carlos Mendes', 'sócio (62%)', 0),
    (3, 'Carlos Mendes', 'XYZ Securitizadora', 'sócio (71%)', 1),
    (3, 'Lumen Debêntures Incentivadas FI', 'XYZ Securitizadora', 'contraparte (CRI URBE28)', 1),
    (3, 'Lumen Debêntures Incentivadas FI', 'Distribuidora Alfa DTVM', 'distribuição', 0),
    (3, 'Lumen Gestão de Recursos (gestora)', 'Beta Consultoria Imobiliária', 'mesmo endereço fiscal', 0),
]
vals = ', '.join(f"({fid}, {s(o)}, {s(d)}, {s(t)}, {su})" for fid, o, d, t, su in partes)
w(f'INSERT INTO partes_relacionadas (fundo_id, origem, destino, tipo_vinculo, suspeito) VALUES {vals};')
w()

# ------------------------------------------------------------------
# 10. ENQUADRAMENTO
# ------------------------------------------------------------------
w('-- ---------- enquadramento_regras ----------')
regras = [
    (1, 'Mínimo de 80% do PL em ativos de renda fixa', 'min_rf', 80),
    (1, 'Máximo de 25% do PL por ativo', 'max_ativo_unico', 25),
    (1, 'Máximo de 75% do PL em crédito privado', 'max_credito_privado', 75),
    (2, 'Máximo de 40% do PL em ações', 'max_acoes', 40),
    (2, 'Máximo de 20% do PL por ativo', 'max_ativo_unico', 20),
    (3, 'Mínimo de 85% do PL em ativos de renda fixa', 'min_rf', 85),
    (3, 'Máximo de 25% do PL por ativo', 'max_ativo_unico', 25),
    (4, 'Máximo de 20% do PL por ativo', 'max_ativo_unico', 20),
    (4, 'Máximo de 15% do PL em caixa', 'max_caixa', 15),
    (5, 'Máximo de 45% do PL em ações', 'max_acoes', 45),
    (6, 'Mínimo de 80% do PL em ativos de renda fixa', 'min_rf', 80),
    (7, 'Máximo de 10% do PL por ativo', 'max_ativo_unico', 10),
    (7, 'Máximo de 15% do PL em caixa', 'max_caixa', 15),
]
vals = ', '.join(f"({fid}, {s(d)}, {s(t)}, {l})" for fid, d, t, l in regras)
w(f'INSERT INTO enquadramento_regras (fundo_id, descricao, tipo_regra, limite) VALUES {vals};')
w()

w('-- ---------- enquadramento_eventos ----------')
w("INSERT INTO enquadramento_eventos (fundo_id, data_evento, regra, causa, situacao, prazo_reenquadramento) VALUES")
w(f"(7, {s(DIAS[-3].isoformat())}, {s('Máximo de 10% do PL por ativo (NORD3 em ' + br(NORD3['pct'], 1) + '%)')}, 'Ativo', 'Em aberto', {s((LB + timedelta(days=15)).isoformat())}),")
w(f"(2, {s(DIAS[-60].isoformat())}, 'Máximo de 40% do PL em ações (chegou a 42,3% após alta do mercado)', 'Passivo', 'Reenquadrado', {s(DIAS[-52].isoformat())});")
w()

# ------------------------------------------------------------------
# 11. COMUNICADOS, CHAMADOS, DOCUMENTOS, COMENTÁRIOS, ONBOARDING, DOCS DE ABERTURA
# ------------------------------------------------------------------
w('-- ---------- comunicados ----------')
w("INSERT INTO comunicados (fundo_id, titulo, mensagem, data_pub) VALUES")
w(f"(NULL, 'Portal do cotista por token no ar', 'O acesso do cotista agora é feito por token gerado pelo gestor, com nível de visão configurável (tempo real, 1 mês ou 3 meses de defasagem). Gere os acessos na aba Acessos de cotistas.', {s(DIAS[-8].isoformat())}),")
w(f"(NULL, 'Calendário de feriados de julho', 'Não haverá fechamento de cota no dia 09/07 (feriado estadual em SP). O batch do dia 10/07 consolidará os dois dias, como previsto no regulamento.', {s(DIAS[-3].isoformat())}),")
w(f"(1, 'Reabertura para captação', 'O fundo Aurora RF Crédito Privado FI está reaberto para novas aplicações a partir de hoje. O limite de captação desta janela é de R$ 10 milhões.', {s(DIAS[-5].isoformat())}),")
w(f"(1, 'Relatório mensal disponível', 'O relatório gerencial de junho/2026 já está disponível na área de documentos, com a carta do gestor e o detalhamento da carteira de crédito.', {s(DIAS[-2].isoformat())});")
w()

w('-- ---------- chamados ----------')
w("INSERT INTO chamados (fundo_id, usuario_id, assunto, mensagem, status, resposta, respondido_por, criado_em, respondido_em) VALUES")
w(f"(1, 2, 'Divergência na cota de {DIAS[-9].strftime('%d/%m')}', 'A cota publicada difere em 0,0004 do nosso controle interno. Podem verificar o preço usado para a DEB ENGIE31?', 'Respondido', "
  f"'Verificado: usamos o preço ANBIMA de fechamento (R$ 986,20); o controle de vocês usou o preço indicativo do meio-dia. A cota está correta — enviamos a memória de cálculo por e-mail.', {s(ADMIN)}, "
  f"{s(DIAS[-9].isoformat() + ' 10:12:00')}, {s(DIAS[-8].isoformat() + ' 09:30:00')}),")
w(f"(4, 3, 'Prazo de liquidação de resgate', 'Um cotista solicitou resgate ontem e pediu confirmação de quando o valor liquida. O regulamento fala em D+2, confere?', 'Aberto', NULL, NULL, "
  f"{s(LB.isoformat() + ' 14:47:00')}, NULL);")
w()

w('-- ---------- documentos ----------')
docs = [
    (1, 'Regulamento — Aurora RF Crédito Privado FI', 'Regulamento', 'v3', DIAS[-90]),
    (1, 'Lâmina de informações essenciais — jun/2026', 'Lâmina', 'v1', DIAS[-4]),
    (1, 'Política de investimento e riscos', 'Política', 'v2', DIAS[-90]),
    (1, 'Informe de rendimentos 2025', 'Informe', 'v1', date(2026, 2, 27)),
]
for fid, *_ in FUNDOS[1:]:
    docs.append((fid, f'Regulamento — fundo {fid}', 'Regulamento', 'v1', DIAS[-random.randint(60, 200)]))
    docs.append((fid, 'Lâmina de informações essenciais — jun/2026', 'Lâmina', 'v1', DIAS[-4]))
vals = ', '.join(f"({fid}, {s(n)}, {s(t)}, {s(v)}, {s(d.isoformat())})" for fid, n, t, v, d in docs)
w(f'INSERT INTO documentos (fundo_id, nome, tipo, versao, data_doc) VALUES {vals};')
w()

w('-- ---------- comentarios ----------')
w("INSERT INTO comentarios (fundo_id, autor, texto, criado_em) VALUES")
w(f"(7, {s(ADMIN)}, 'Aguardando ata do comitê de precificação para NORD3 — batch travado na etapa de preços até lá.', {s(LB.isoformat() + ' 08:15:00')}),")
w(f"(3, {s(ADMIN)}, 'Alerta de parte relacionada encaminhado ao jurídico para parecer antes de notificar o gestor.', {s(LB.isoformat() + ' 09:40:00')}),")
w(f"(2, {s(ADMIN)}, 'Cota v1 rejeitada pelo gestor (preço DEB VALE29). Ajuste lançado, v2 reenviada — aguardando aprovação.', {s(LB.isoformat() + ' 11:45:00')}),")
w(f"(1, {s(ADMIN)}, 'Resgate institucional grande confirmado por telefone com a Holding Miradouro — legítimo, mantendo monitoramento de liquidez.', {s(LB.isoformat() + ' 11:05:00')});")
w()

w('-- ---------- onboarding_etapas (fundo em abertura) ----------')
etapas_ob = [
    (1, 'Cadastro', 'Concluída', DIAS[-22], 'Plataforma'),
    (2, 'Documentos', 'Concluída', DIAS[-20], 'Gestora'),
    (3, 'Análise KYC/PLD', 'Concluída', DIAS[-18], 'Compliance'),
    (4, 'Registro CVM', 'Concluída', DIAS[-10], 'Administradora'),
    (5, 'CNPJ Receita', 'Concluída', DIAS[-6], 'Administradora'),
    (6, 'Conta custodiante', 'Em andamento', None, 'Banco parceiro'),
    (7, 'Fundo apto', 'Pendente', None, None),
]
vals = ', '.join(
    f"(8, {o}, {s(e)}, {s(st)}, {s(d.isoformat()) if d else 'NULL'}, {s(r) if r else 'NULL'})"
    for o, e, st, d, r in etapas_ob)
w(f'INSERT INTO onboarding_etapas (fundo_id, ordem, etapa, status, data_conclusao, responsavel) VALUES {vals};')
w()

w('-- ---------- documentos_abertura (checklist CVM 175 do fundo 8) ----------')
docs_ab = [
    ('Gestora', 'Contrato ou estatuto social consolidado da gestora', 1, 'Aprovado', 'contrato_social_nf.pdf', None),
    ('Gestora', 'Ato declaratório CVM de administrador de carteiras (Res. CVM 21)', 1, 'Aprovado', 'ato_cvm_nf.pdf', None),
    ('Gestora', 'Formulário de referência atualizado (Res. CVM 21, Anexo E)', 1, 'Aprovado', 'form_referencia_nf.pdf', None),
    ('Gestora', 'Política de gestão de riscos', 1, 'Aprovado', 'politica_riscos_nf.pdf', None),
    ('Gestora', 'Política de PLD/FT (Res. CVM 50)', 1, 'Aprovado', 'pld_ft_nf.pdf', None),
    ('Gestora', 'Certidões negativas de débitos (RFB/PGFN)', 1, 'Pendente', None, None),
    ('Responsável', 'Documento de identidade e CPF do responsável', 1, 'Aprovado', 'rg_cpf_camila.pdf', None),
    ('Responsável', 'Comprovante de endereço do responsável', 1, 'Aprovado', 'endereco_camila.pdf', None),
    ('Fundo', 'Minuta do regulamento (com anexo normativo da classe — Res. CVM 175)', 1, 'Aprovado', 'regulamento_nf_v2.pdf', None),
    ('Fundo', 'Política de investimento da classe', 1, 'Aprovado', 'politica_invest_nf.pdf', None),
    ('Fundo', 'Lâmina de informações essenciais', 1, 'Rejeitado', 'lamina_nf_v1.pdf', 'Cenários de rentabilidade fora do padrão ANBIMA — reenviar com a tabela obrigatória'),
    ('Fundo', 'Minuta do contrato de custódia', 1, 'Recebido', 'contrato_custodia_nf.pdf', None),
    ('Fundo', 'Minuta do contrato de auditoria independente', 1, 'Aprovado', 'contrato_auditoria_nf.pdf', None),
    ('Fundo', 'Contrato de distribuição', 0, 'Pendente', None, None),
    ('Fundo', 'Modelo de termo de adesão do cotista', 1, 'Aprovado', 'termo_adesao_nf.pdf', None),
]
linhas = []
for cat, nome_d, obr, st_d, arq, mot in docs_ab:
    linhas.append(f"(8, {s(cat)}, {s(nome_d)}, {obr}, {s(st_d)}, {s(arq) if arq else 'NULL'}, {s(mot) if mot else 'NULL'}, {s(DIAS[-random.randint(2, 18)].isoformat() + ' 10:00:00')})")
w('INSERT INTO documentos_abertura (fundo_id, categoria, nome, obrigatorio, status, arquivo, motivo, atualizado_em) VALUES')
w(',\n'.join(linhas) + ';')
w()

# ------------------------------------------------------------------
# 12. REPASSES
# ------------------------------------------------------------------
w('-- ---------- repasses ----------')
linhas = []
comps = ['2026-04', '2026-05', '2026-06']
status_por_comp = {'2026-04': 'Pago', '2026-05': 'Pago', '2026-06': 'Apurado'}
for fid, nome, cnpj, classe, pub, gest, pl_alvo, *_ in FUNDOS:
    for comp in comps:
        pl_medio = pl_alvo * random.uniform(0.94, 1.0)
        percentual = pl_medio * 0.0008 / 12
        valor = max(100.0, percentual)
        piso = 1 if percentual < 100.0 else 0
        banco, adm = valor * 0.25, valor * 0.75
        gestao = pl_medio * 0.007 / 12
        stt = status_por_comp[comp]
        if comp == '2026-06' and fid in (2, 4):
            stt = 'Instruído'
        linhas.append(f"({fid}, {s(comp)}, {pl_medio:.2f}, {valor:.2f}, {piso}, {banco:.2f}, {adm:.2f}, {gestao:.2f}, 0, {s(stt)})")
w('INSERT INTO repasses (fundo_id, competencia, pl_medio, taxa_adm_valor, piso_aplicado, parte_banco, parte_adm, taxa_gestao_valor, taxa_custodia_valor, status) VALUES')
w(',\n'.join(linhas) + ';')
w()

# ------------------------------------------------------------------
# 13. CUSTÓDIA: LIQUIDAÇÕES E EVENTOS CORPORATIVOS (coerentes com as carteiras)
# ------------------------------------------------------------------
CART = globals()['CARTEIRAS_FINAL']
def prox_util(d, n=1):
    while n > 0:
        d += timedelta(days=1)
        if d.weekday() < 5:
            n -= 1
    return d

def acha(fid, tipos=None, cod=None):
    for a in CART[fid]:
        if cod and a['cod'] == cod:
            return a
        if tipos and a['tipo'] in tipos:
            return a
    return CART[fid][0]

w('-- ---------- liquidacoes (fila de liquidação física/financeira) ----------')
linhas = []
deb2 = acha(2, cod='DEB VALE29')
linhas.append(f"(2, {s(LB.isoformat())}, 'DEB VALE29', 'Compra', {min(400, deb2['qtd'])}, 412350.00, "
              f"{s(prox_util(LB).isoformat())}, 'XP CTVM', 'Pendente', NULL, NULL)")
ac4 = acha(4, tipos=['Ação'])
qtd_v = max(1, int(ac4['qtd'] * 0.08))
linhas.append(f"(4, {s(LB.isoformat())}, {s(ac4['cod'])}, 'Venda', {qtd_v}, {qtd_v * ac4['mam']:.2f}, "
              f"{s(prox_util(LB, 2).isoformat())}, 'BTG Pactual CTVM', 'Pendente', NULL, NULL)")
tp1 = acha(1, tipos=['Título Público'])
qtd_c = max(1, int(tp1['qtd'] * 0.05))
linhas.append(f"(1, {s(DIAS[-2].isoformat())}, {s(tp1['cod'])}, 'Compra', {qtd_c}, "
              f"{qtd_c * tp1['mam']:.2f}, {s(LB.isoformat())}, 'Dealer SELIC', 'Liquidada', "
              f"{s(ADMIN)}, {s(LB.isoformat() + ' 07:55:00')})")
rf6 = acha(6, tipos=['Debênture', 'CRI/CRA', 'CDB'])
qtd_f = max(1, int(rf6['qtd'] * 0.1))
linhas.append(f"(6, {s(DIAS[-3].isoformat())}, {s(rf6['cod'])}, 'Venda', {qtd_f}, "
              f"{qtd_f * rf6['mam']:.2f}, {s(DIAS[-1].isoformat())}, 'Itaú BBA', 'Falha', NULL, NULL)")
w('INSERT INTO liquidacoes (fundo_id, data_operacao, ativo_codigo, operacao, quantidade, valor, data_liquidacao, contraparte, status, confirmado_por, confirmado_em) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- boletas (gestor boletou; custódia valida) ----------')
w("INSERT INTO boletas (fundo_id, data_operacao, operacao, ativo_codigo, tipo_ativo, quantidade, preco, valor, contraparte, status, motivo, liquidacao_id, criado_por, criado_em) VALUES")
w(f"(1, {s(LB.isoformat())}, 'Compra', 'CDB BCO MÉTRICA', 'CDB', 500.00, 1120.500000, 560250.00, 'Banco Métrica (emissor)', 'Enviada', NULL, NULL, 'Ricardo Nunes', {s(LB.isoformat() + ' 16:05:00')}),")
qtd_hist = max(1, int(ac4['qtd'] * 0.03))
w(f"(4, {s(DIAS[-8].isoformat())}, 'Venda', {s(ac4['cod'])}, 'Ação', {qtd_hist}.00, {ac4['mam']:.6f}, {qtd_hist * ac4['mam']:.2f}, 'BTG Pactual CTVM', 'Liquidada', NULL, NULL, 'Luísa Andrade', {s(DIAS[-8].isoformat() + ' 11:30:00')});")
w()

w('-- ---------- eventos_corporativos ----------')
linhas = []
vpu = round(random.uniform(0.4, 1.2), 4)
linhas.append(f"(4, {s(ac4['cod'])}, 'Dividendo', {s(DIAS[-2].isoformat())}, {s(LB.isoformat())}, "
              f"{s((HOJE + timedelta(days=8)).isoformat())}, {vpu}, {ac4['qtd'] * vpu:.2f}, 'Anunciado', NULL, NULL)")
deb1 = acha(1, tipos=['Debênture', 'CRI/CRA'])
cupom = deb1['qtd'] * deb1['mam'] * 0.028
linhas.append(f"(1, {s(deb1['cod'])}, 'Cupom', {s(DIAS[-6].isoformat())}, NULL, "
              f"{s((HOJE + timedelta(days=5)).isoformat())}, NULL, {cupom:.2f}, 'Provisionado', {s(ADMIN)}, {s(DIAS[-5].isoformat() + ' 09:10:00')})")
cri3 = acha(3, cod='CRI URBE28')
linhas.append(f"(3, 'CRI URBE28', 'Amortização', {s(DIAS[-4].isoformat())}, NULL, "
              f"{s((HOJE + timedelta(days=12)).isoformat())}, NULL, {cri3['qtd'] * cri3['mam'] * 0.05:.2f}, 'Anunciado', NULL, NULL)")
ac2 = acha(2, tipos=['Ação'])
jcp = ac2['qtd'] * 0.35
linhas.append(f"(2, {s(ac2['cod'])}, 'JCP', {s(DIAS[-12].isoformat())}, {s(DIAS[-10].isoformat())}, "
              f"{s(DIAS[-5].isoformat())}, 0.35, {jcp:.2f}, 'Liquidado', {s(ADMIN)}, {s(DIAS[-5].isoformat() + ' 11:20:00')})")
w('INSERT INTO eventos_corporativos (fundo_id, ativo_codigo, tipo, data_anuncio, data_ex, data_pagamento, valor_por_unidade, valor_total, status, processado_por, processado_em) VALUES')
w(',\n'.join(linhas) + ';')
w()

# ------------------------------------------------------------------
# 14. REGULATÓRIO: ENVIOS, OFÍCIOS E ASSEMBLEIAS
# ------------------------------------------------------------------
w('-- ---------- envios_regulatorios ----------')
linhas = []
d1s = LB.isoformat()
d2s = DIAS[-2].isoformat()
prazo_diario = prox_util(LB).isoformat()
prazo_mensal = '2026-07-14'
def prot(destino):
    return f"{destino}-{random.randint(20260600, 20260799)}-{random.randint(10000, 99999)}"
for fid, *_ in FUNDOS:
    linhas.append(f"({fid}, 'CVM', 'Informe Diário', {s(d2s)}, {s(d1s)}, 'Enviado', {s(prot('CVM'))}, {s(d1s + ' 08:%02d:00' % random.randint(5, 45))}, NULL)")
    if fid == 5:
        linhas.append(f"({fid}, 'CVM', 'Informe Diário', {s(d1s)}, {s(prazo_diario)}, 'Enviado', {s(prot('CVM'))}, {s(d1s + ' 10:02:00')}, NULL)")
    elif fid == 7:
        linhas.append(f"({fid}, 'CVM', 'Informe Diário', {s(d1s)}, {s(prazo_diario)}, 'Pendente', NULL, NULL, 'Batch travado na precificação (NORD3)')")
    else:
        linhas.append(f"({fid}, 'CVM', 'Informe Diário', {s(d1s)}, {s(prazo_diario)}, 'Aguardando cota', NULL, NULL, NULL)")
    st_cda = 'Pendente' if fid == 6 else 'Enviado'
    linhas.append(f"({fid}, 'CVM', 'CDA', '2026-06', {s(prazo_mensal)}, {s(st_cda)}, "
                  f"{s(prot('CVM')) if st_cda == 'Enviado' else 'NULL'}, "
                  f"{s('2026-07-02 16:%02d:00' % random.randint(0, 59)) if st_cda == 'Enviado' else 'NULL'}, NULL)")
    if fid == 2:
        linhas.append(f"({fid}, 'CVM', 'Balancete', '2026-06', {s(prazo_mensal)}, 'Erro', NULL, NULL, 'XML rejeitado pelo validador CVM — reenviar')")
    else:
        linhas.append(f"({fid}, 'CVM', 'Balancete', '2026-06', {s(prazo_mensal)}, 'Enviado', {s(prot('CVM'))}, {s('2026-07-02 17:%02d:00' % random.randint(0, 59))}, NULL)")
    st_pm = 'Enviado' if fid in (1, 2, 3) else 'Pendente'
    linhas.append(f"({fid}, 'CVM', 'Perfil Mensal', '2026-06', {s(prazo_mensal)}, {s(st_pm)}, "
                  f"{s(prot('CVM')) if st_pm == 'Enviado' else 'NULL'}, "
                  f"{s('2026-07-03 09:%02d:00' % random.randint(0, 59)) if st_pm == 'Enviado' else 'NULL'}, NULL)")
    linhas.append(f"({fid}, 'CVM', 'Demonstrações Contábeis', '2025', '2026-03-31', 'Enviado', {s(prot('CVM'))}, '2026-03-24 15:30:00', NULL)")
    st_anb = 'Pendente' if fid == 4 else 'Enviado'
    linhas.append(f"({fid}, 'ANBIMA', 'Estatísticas ANBIMA', '2026-06', '2026-07-10', {s(st_anb)}, "
                  f"{s(prot('ANB')) if st_anb == 'Enviado' else 'NULL'}, "
                  f"{s('2026-07-02 18:%02d:00' % random.randint(0, 59)) if st_anb == 'Enviado' else 'NULL'}, NULL)")
w('INSERT INTO envios_regulatorios (fundo_id, destino, tipo, competencia, prazo, status, protocolo, enviado_em, mensagem) VALUES')
w(',\n'.join(linhas) + ';')
w()

w('-- ---------- oficios_cvm ----------')
w("INSERT INTO oficios_cvm (fundo_id, origem, numero, assunto, teor, recebido_em, prazo_resposta, status, resposta, respondido_por, respondido_em) VALUES")
w(f"(7, 'CVM', 'Ofício nº 145/2026/CVM/SIN/GIFI', 'Pedido de esclarecimentos — desenquadramento por concentração', "
  f"'Nos termos do art. 60 da Res. CVM 175, solicitamos esclarecimentos sobre a posição do ativo NORD3 acima do limite de 10% do PL por ativo, bem como o plano de reenquadramento e as providências do administrador fiduciário.', "
  f"{s(DIAS[-2].isoformat())}, {s((LB + timedelta(days=10)).isoformat())}, 'Recebido', NULL, NULL, NULL),")
w(f"(NULL, 'CVM', 'Ofício Circular nº 3/2026/CVM/SIN', 'Orientações sobre marcação a mercado de ativos ilíquidos', "
  f"'Orientações gerais aos administradores fiduciários sobre metodologia e governança de precificação de ativos sem fonte primária líquida, incluindo a documentação das atas de comitê.', "
  f"{s(DIAS[-8].isoformat())}, NULL, 'Ciente', NULL, {s(ADMIN)}, {s(DIAS[-7].isoformat() + ' 10:15:00')}),")
w(f"(2, 'ANBIMA', 'Solicitação ANBIMA nº 887/2026', 'Divergência nas estatísticas de maio', "
  f"'A base de dados de maio apresentou divergência entre o PL informado e o PL do informe diário do dia 29/05. Favor confirmar o valor correto.', "
  f"{s(DIAS[-20].isoformat())}, {s(DIAS[-10].isoformat())}, 'Respondido', "
  f"'Confirmado o PL do informe diário (R$ 93,4 mi); a divergência decorreu de resgate processado após o corte. Base retificada.', {s(ADMIN)}, {s(DIAS[-12].isoformat() + ' 14:40:00')});")
w()

w('-- ---------- assembleias ----------')
w("INSERT INTO assembleias (fundo_id, tipo, pauta, origem, data_convocacao, data_realizacao, modo, status, quorum, resultado, criado_por, criado_em) VALUES")
w(f"(1, 'AGE', 'Alteração do regulamento — ampliar o limite de crédito privado de 75% para 80% do PL', 'Solicitação do gestor', NULL, NULL, 'Eletrônica', 'Solicitada', NULL, NULL, 'Ricardo Nunes', {s(LB.isoformat() + ' 15:20:00')}),")
w(f"(3, 'AGE', 'Criação de subclasse de cotas destinada a investidores qualificados', 'Solicitação do gestor', {s(DIAS[-10].isoformat())}, {s((HOJE + timedelta(days=20)).isoformat())}, 'Eletrônica', 'Convocada', NULL, NULL, 'Gestor (portal)', {s(DIAS[-12].isoformat() + ' 09:00:00')}),")
w(f"(2, 'AGO', 'Aprovação das demonstrações contábeis do exercício de 2025', 'Administradora', {s(DIAS[-70].isoformat())}, {s(DIAS[-40].isoformat())}, 'Eletrônica', 'Realizada', '68% do PL presente', 'Demonstrações aprovadas sem ressalvas', {s(ADMIN)}, {s(DIAS[-70].isoformat() + ' 10:00:00')});")
w()


# ------------------------------------------------------------------
# 15. PORTAL DO CUSTODIANTE: USUÁRIO, CONTAS NAS CENTRAIS, MENSAGERIA SPB
# ------------------------------------------------------------------
w("-- ---------- usuario da mesa de custodia ----------")
w(f"INSERT INTO usuarios (id, nome, email, senha, perfil, fundo_id, gestora, telefone, kyc_status) VALUES "
  f"(6, 'Paulo Siqueira', 'custodia@bancoparceiro.com.br', {s(HASH)}, 'custodia', NULL, 'Banco Parceiro S.A.', '(11) 98765-2001', 'Aprovado');")
w()

w("-- ---------- contas_centrais (segregação por fundo) ----------")
linhas = []
linhas.append("(NULL, 'STR/Reservas', '0918-7', 'Banco Parceiro S.A. — conta Reservas Bancárias (liquidação financeira)', 'Ativa')")
linhas.append("(NULL, 'SELIC', '6710.009-1', 'Banco Parceiro S.A. — conta própria de custódia', 'Ativa')")
for fid, nome, *_ in FUNDOS:
    linhas.append(f"({fid}, 'SELIC', '6710.{100 + fid}-{fid}', {s(nome + ' (conta individualizada)')}, 'Ativa')")
    linhas.append(f"({fid}, 'B3 Depositária', 'BD-{88000 + fid * 7}', {s(nome + ' (conta individualizada)')}, 'Ativa')")
    linhas.append(f"({fid}, 'B3 Balcão', 'BB-{54000 + fid * 11}', {s(nome + ' (conta individualizada)')}, 'Ativa')")
w("INSERT INTO contas_centrais (fundo_id, central, numero_conta, titularidade, status) VALUES")
w(',\n'.join(linhas) + ';')
w()

w("-- ---------- mensagens_spb (mensageria RSFN do dia) ----------")
CARTm = globals()['CARTEIRAS_FINAL']
tp1m = next(a for a in CARTm[1] if a['tipo'] == 'Título Público')
linhas = []
def msg(central, codigo, fid, ref, desc, valor, status, hora, quem=None):
    pe = s(LB.isoformat() + ' ' + hora)
    if status == 'Processada':
        linhas.append(f"({s(central)}, {s(codigo)}, {fid if fid else 'NULL'}, {s(ref)}, {s(desc)}, "
                      f"{valor if valor is not None else 'NULL'}, 'Processada', {pe}, {pe}, {s(quem or 'Rotina automática')})")
    else:
        linhas.append(f"({s(central)}, {s(codigo)}, {fid if fid else 'NULL'}, {s(ref)}, {s(desc)}, "
                      f"{valor if valor is not None else 'NULL'}, {s(status)}, {pe}, NULL, NULL)")
# Códigos SEL/STR/LDL são REAIS (Catálogo de Serviços do SFN): SEL1052 = operação definitiva;
# SEL1054 = compromissada (a zeragem over); SEL1081 = consulta posição de custódia; SEL1099 = SEL
# informa movimentação financeira; STR0008 = transferência entre contas de CLIENTES (a TED);
# LDL0001 = câmara informa resultado líquido. '052' = tipo de operação do NoMe (compra/venda
# definitiva, duplo comando). 'ARQ-POS'/'AGENDA-EV' = arquivos/feeds fora do catálogo RSFN.
msg('SELIC', 'SEL1052', 1, 'OP-77123', f"Operação definitiva de compra — {tp1m['cod']} creditada na conta 6710.101-1 (DVP LBTR em tempo real)", round(max(1, int(tp1m['qtd'] * 0.05)) * tp1m['mam'], 2), 'Processada', '07:52:00', ADMIN)
msg('SELIC', 'SEL1099', 1, 'OP-77123', 'SEL informa movimentação financeira — perna financeira da operação definitiva concluída', round(max(1, int(tp1m['qtd'] * 0.05)) * tp1m['mam'], 2), 'Processada', '07:53:00', 'Rotina automática')
msg('B3 Balcão', '052', 2, 'LIQ-1', 'Duplo comando: ponta comandada no NoMe (tipo 052 — compra/venda definitiva) — DEB VALE29 aguarda comando da contraparte · Bruta (DVP via STR), liq. D+1', 412350.00, 'Recebida', '08:10:00')
msg('B3 Depositária', 'LDL0001', 4, 'LIQ-2', 'Câmara B3 informa resultado líquido de negociações — venda ITUB4 na janela D+2 (saldo multilateral)', None, 'Recebida', '08:12:00')
msg('B3 Depositária', 'AGENDA-EV', 4, 'EV-DIV', 'Agenda de eventos da Depositária — anúncio de dividendo ITUB4 (feed de eventos, fora da RSFN)', None, 'Processada', '08:30:00', 'Rotina automática')
msg('SELIC', 'SEL1052', 6, 'OP-77488', 'Operação definitiva rejeitada — quantidade divergente do comando da contraparte; recomando necessário', None, 'Erro', '09:05:00')
msg('SELIC', 'SEL1054', 6, 'OVER-D0', 'Operação compromissada (zeragem over) — caixa livre aplicado com lastro em LFT; retorno na abertura (SEL1056)', None, 'Processada', '17:55:00', 'Rotina automática')
msg('STR', 'STR0008', 4, 'RESG-2201', 'IF requisita transferência entre contas de clientes (TED) — pagamento de resgate a cotista', 377203.23, 'Processada', '16:20:00', 'Rotina automática')
msg('B3 Balcão', 'ARQ-POS', None, 'EOD-D1', 'Arquivo de posição de fechamento do balcão (NoMe) disponível para conciliação — feed fora da RSFN', None, 'Processada', '18:40:00', 'Rotina automática')
msg('SELIC', 'SEL1081', None, 'EOD-D1', 'Consulta posição de custódia respondida — base do batimento diário (Res. CVM 32, art. 13, §1º, I)', None, 'Processada', '18:41:00', 'Rotina automática')
msg('B3 Depositária', 'ARQ-POS', 7, 'EOD-D1', 'Arquivo de posição da Depositária — NORD3 diverge do interno (33.337 × 27.669); batimento acusou', None, 'Erro', '18:45:00')
w("INSERT INTO mensagens_spb (central, codigo, fundo_id, referencia, descricao, valor, status, recebida_em, processada_em, processada_por) VALUES")
w(',\n'.join(linhas) + ';')
w()

w("-- ---------- posicao_custodiante (fonte INDEPENDENTE p/ conciliação e arquivo de posição) ----------")
w("-- Snapshot do último dia espelha a carteira, exceto a divergência semeada do NORD3 (fundo 7).")
w("INSERT INTO posicao_custodiante (fundo_id, data_ref, codigo, tipo, quantidade, central)")
w("SELECT fundo_id, data_ref, codigo, tipo, quantidade,")
w("       CASE WHEN tipo = 'Título Público' THEN 'SELIC'")
w("            WHEN tipo IN ('Debênture','CDB','CRI/CRA') THEN 'B3 Balcão'")
w("            ELSE 'B3 Depositária' END")
w(f"FROM ativos_carteira WHERE data_ref = {s(LB.isoformat())};")
w(f"UPDATE posicao_custodiante SET quantidade = {qtd_cust} WHERE fundo_id = 7 AND codigo = 'NORD3' AND data_ref = {s(LB.isoformat())};")
w()

print('\n'.join(out))
