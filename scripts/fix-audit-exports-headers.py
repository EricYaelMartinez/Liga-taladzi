#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent.parent
PAGES = ROOT / 'resources/js/pages/League'
APP_CSS = ROOT / 'resources/css/app.css'
HEADER_CSS = ROOT / 'resources/css/proliga-unified-headers.css'

AUDIT_REL = 'Audit/Index.vue'
EXPORTS_REL = 'Exports/Index.vue'


def backup(path: Path, original: str):
    bak = path.with_suffix(path.suffix + '.hdrbak')
    if not bak.exists():
        bak.write_text(original, encoding='utf-8')


def patch_audit(text: str) -> str:
    pattern = re.compile(r'<header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between proliga-unified-hero">.*?</header>', re.S)
    repl = '''<header class="proliga-unified-hero proliga-unified-hero--split">
            <div class="proliga-hero-copy">
                <p class="competition-eyebrow text-sm font-semibold uppercase tracking-[0.18em]">Módulo 19 · Auditoría</p>
                <h1 class="mt-1">Bitácora de cambios</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6">Consulta quién realizó cada operación, cuándo ocurrió y qué información fue modificada dentro de esta liga.</p>
            </div>
            <div class="proliga-hero-actions">
                <a :href="exportUrl" class="btn-secondary">Exportar CSV</a>
            </div>
        </header>'''
    return pattern.sub(repl, text, count=1)


def patch_exports(text: str) -> str:
    pattern = re.compile(r'<header class="mb-7 proliga-unified-hero">.*?</header>', re.S)
    repl = '''<header class="mb-7 proliga-unified-hero">
            <div class="proliga-hero-copy">
                <p class="competition-eyebrow text-sm font-semibold uppercase tracking-[0.18em]">Módulo 18</p>
                <h1 class="mt-1">Reportes y exportaciones</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6">Genera documentos oficiales en PDF o libros de Excel. Cada descarga conserva un folio y queda registrada en la bitácora.</p>
            </div>
        </header>'''
    return pattern.sub(repl, text, count=1)


def patch_file(path: Path, patcher) -> str:
    if not path.exists():
        return 'No existe; omitido'
    src = path.read_text(encoding='utf-8')
    fixed = patcher(src)
    if fixed == src:
        return 'Sin cambios; revisa si la vista ya fue editada manualmente'
    backup(path, src)
    path.write_text(fixed, encoding='utf-8')
    return 'Encabezado corregido'


def ensure_import():
    if not APP_CSS.exists():
        return 'app.css no existe; importa manualmente resources/css/proliga-unified-headers.css'
    src = APP_CSS.read_text(encoding='utf-8')
    imp = '@import "./proliga-unified-headers.css";'
    if imp in src:
        return 'Import existente'
    backup(APP_CSS, src)
    target = '@import "tailwindcss";'
    if target in src:
        APP_CSS.write_text(src.replace(target, target + '\n' + imp, 1), encoding='utf-8')
    else:
        APP_CSS.write_text(imp + '\n' + src, encoding='utf-8')
    return 'Import agregado'


def main():
    print(f'{AUDIT_REL}: {patch_file(PAGES / AUDIT_REL, patch_audit)}')
    print(f'{EXPORTS_REL}: {patch_file(PAGES / EXPORTS_REL, patch_exports)}')
    print(ensure_import())
    print('Listo. Ejecuta nuevamente scripts/setup.ps1 en PowerShell para recompilar assets dentro de Docker.')


if __name__ == '__main__':
    main()
