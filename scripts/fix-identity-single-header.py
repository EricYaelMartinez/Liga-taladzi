#!/usr/bin/env python3
"""ProLiga: corrige solamente el encabezado de Identidad de la liga.
Elimina la clase unificada de un contenedor interno y la aplica al header exterior.
No reescribe el formulario ni altera scripts/acciones.
"""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parent.parent
PATH = ROOT / 'resources/js/pages/League/Settings/Edit.vue'
if not PATH.is_file():
    sys.exit('No se encontró resources/js/pages/League/Settings/Edit.vue. Extrae el ZIP en la raíz de ProLiga.')
src = PATH.read_text(encoding='utf-8')
# Se exige la estructura original conocida antes de modificarla.
start = re.search(r'<header\b[^>]*\bclass\s*=\s*["\'][^"\']*identity-hero[^"\']*["\'][^>]*>', src)
if not start:
    # Ya corregido previamente
    if re.search(r'<header\b[^>]*\bclass\s*=\s*["\'][^"\']*proliga-unified-hero[^"\']*["\'][^>]*>', src):
        print('Encabezado unificado ya aplicado; no se realizaron cambios.')
        sys.exit(0)
    sys.exit('No se identificó el encabezado original; no se modificó ningún archivo.')
end = src.find('</header>', start.end())
if end == -1:
    sys.exit('No se encontró el cierre del encabezado; se omitió la modificación.')
segment = src[start.start():end + len('</header>')]
if 'Identidad de la liga' not in segment:
    sys.exit('El encabezado no corresponde a Identidad de la liga; no se modificó.')
# Eliminar la clase aplicada erróneamente a un hijo, conservando sus demás clases.
head_end = segment.find('>') + 1
outer, inner = segment[:head_end], segment[head_end:]
inner = re.sub(r'(\bclass\s*=\s*["\'])([^"\']*)(["\'])',
               lambda m: m.group(1) + ' '.join(c for c in m.group(2).split() if c != 'proliga-unified-hero') + m.group(3), inner)
# Descarta clase antigua de fondo en el header y deja una única clase de hero.
outer = re.sub(r'(\bclass\s*=\s*["\'])([^"\']*)(["\'])',
               lambda m: m.group(1) + ' '.join(dict.fromkeys([c for c in m.group(2).split() if c not in ('identity-hero','proliga-unified-hero')] + ['proliga-unified-hero','identity-hero--single'])) + m.group(3), outer, count=1)
# El layout interior debe ocupar todo el ancho; no se modifica el contenido del botón.
updated_segment = outer + inner
if updated_segment == segment:
    print('Sin cambios necesarios.')
    sys.exit(0)
backup = PATH.with_suffix('.vue.identity-hero.bak')
if not backup.exists():
    backup.write_text(src, encoding='utf-8')
PATH.write_text(src[:start.start()] + updated_segment + src[end+len('</header>'):], encoding='utf-8')
print('Corregido: encabezado único en Identidad de la liga.')
print('Respaldo:', backup.relative_to(ROOT))
print('Recompila usando el flujo Docker de ProLiga: .\\scripts\\setup.ps1')
