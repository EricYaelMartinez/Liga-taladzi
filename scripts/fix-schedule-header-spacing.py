#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FILE = ROOT / 'resources/js/pages/League/Schedule/Index.vue'

OLD = '            <div class="relative z-10 mx-3 -mt-4 grid gap-3 sm:mx-6 sm:grid-cols-2 xl:grid-cols-4">'
NEW = '            <div class="relative z-10 mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">'

if not FILE.exists():
    print('No se encontró resources/js/pages/League/Schedule/Index.vue')
    raise SystemExit(1)

src = FILE.read_text(encoding='utf-8')
if NEW in src:
    print('La corrección ya estaba aplicada.')
    raise SystemExit(0)

if OLD not in src:
    print('No se encontró el bloque esperado. Revisa si la vista fue modificada manualmente.')
    raise SystemExit(1)

bak = FILE.with_suffix(FILE.suffix + '.spacingbak')
if not bak.exists():
    bak.write_text(src, encoding='utf-8')

FILE.write_text(src.replace(OLD, NEW, 1), encoding='utf-8')
print('Separación corregida: el encabezado de Jornadas y programación ya no se monta sobre las tarjetas.')
print('Respaldo creado en:', bak)
print('Ahora recompila assets con Docker usando scripts/setup.ps1')
