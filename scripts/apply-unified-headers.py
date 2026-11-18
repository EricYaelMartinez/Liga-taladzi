#!/usr/bin/env python3
"""ProLiga: aplica el encabezado visual a todas las vistas con título de liga.
Ejecutar desde la raíz del proyecto: python scripts/apply-unified-headers.py
No modifica formularios, rutas, permisos ni datos. Idempotente. Crea .bak antes de cambios.
"""
from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parent.parent
PAGES = ROOT / 'resources/js/pages/League'
CSS = ROOT / 'resources/css/app.css'
IMPORT = '@import "./proliga-unified-headers.css";'

# Tags de Vue (no reescribe ni serializa las plantillas Vue).
TAG = re.compile(r'<!--[\s\S]*?-->|<(/?)([A-Za-z][\w.:-]*)(?=[\s/>])([^<>]*?)>', re.S)
VOID = {'input','img','br','hr','meta','link','source','area','wbr'}
# Cabeceras especiales conocidas, que mantienen todas sus acciones.
HERO = ('participants-hero','competition-hero','match-operations-hero',
        'schedule-hero','dashboard-hero','league-hero','page-hero',
        'proliga-unified-hero')


def nodes_of(template):
    stack, nodes = [], []
    for m in TAG.finditer(template):
        if m.group(0).startswith('<!--'): continue
        close, name, attributes = m.group(1), m.group(2).lower(), m.group(3)
        if close:
            for j in range(len(stack)-1,-1,-1):
                if stack[j]['name'] == name:
                    node = stack[j]
                    node['end'] = m.end()
                    del stack[j:]
                    break
        else:
            node = {'name':name, 'start':m.start(), 'open_end':m.end(),
                    'attrs':attributes, 'parent':stack[-1] if stack else None,
                    'end':len(template)}
            nodes.append(node)
            if not (m.group(0).endswith('/>') or name in VOID):stack.append(node)
    return nodes


def choose_node(template):
    nodes=nodes_of(template)
    headings=[n for n in nodes if n['name']=='h1']
    if not headings:return None
    h=headings[0]
    ancestors=[]
    a=h['parent']
    while a:
        if a['name'] in ('header','div','section'):
            content=template[a['open_end']:a['end']]
            classes=re.search(r'\bclass\s*=\s*["\']([^"\']*)',a['attrs'])
            cl=classes.group(1) if classes else ''
            ancestors.append((a,cl,content))
        a=a['parent']
    # If a known hero exists, change just that element.
    for a,cl,_ in ancestors:
        if any(token in cl.split() for token in HERO):return a
    # A compact header often contains the H1 and the action button.
    for a,cl,content in ancestors:
        size=a['end']-a['start']
        if a['name']=='header' and size < 4400:return a
        if 'flex' in cl.split() and size < 3100 and '<p' in content:return a
    # If no compact header, scope to title + subtitle, never the full page.
    for a,cl,content in ancestors:
        size=a['end']-a['start']
        if size < 1600 and '<p' in content:return a
    return None


def change_file(path):
    source=path.read_text(encoding='utf-8')
    if 'proliga-unified-hero' in source:return 'already'
    pos=source.find('<template>')
    if pos < 0:return 'skip: no template'
    area=source[pos:]
    node=choose_node(area)
    if node is None:
        # Settings pages have a section heading followed by a large form.
        # Wrap ONLY the heading text; never apply the hero class to the whole form.
        if '/Settings/' in str(path).replace('\\', '/'):
            h=re.search(r'<h1\b[^>]*>[\s\S]*?</h1>',area)
            if h:
                pre=area.rfind('<p ',0,h.start())
                if pre<0:pre=h.start()
                post=re.search(r'<p\b[^>]*>[\s\S]*?</p>',area[h.end():])
                end=h.end()
                if post and post.start()<90:end=h.end()+post.end()
                # preserve leading section; insert a dedicated visual wrapper
                result=source[:pos+pre]+'<div class=\"proliga-unified-hero\">\n'+source[pos+pre:pos+end]+'\n</div>'+source[pos+end:]
                bak=path.with_suffix(path.suffix+'.bak')
                if not bak.exists():bak.write_text(source,encoding='utf-8')
                path.write_text(result,encoding='utf-8')
                return 'updated'
        return 'skip: no compact header' 
    head=area[node['start']:node['open_end']]
    class_match=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',head,re.S)
    if class_match:
        replacement=head[:class_match.start(2)] + class_match.group(2) + ' proliga-unified-hero' + head[class_match.end(2):]
    else:
        replacement=head[:-1]+' class="proliga-unified-hero">'
    result=source[:pos+node['start']]+replacement+source[pos+node['open_end']:]
    bak=path.with_suffix(path.suffix+'.bak')
    if not bak.exists():bak.write_text(source,encoding='utf-8')
    path.write_text(result,encoding='utf-8')
    return 'updated'


def main():
    if not PAGES.is_dir() or not CSS.exists():
        sys.exit('ERROR: Ejecuta el script desde el proyecto con resources/js/pages/League y resources/css/app.css')
    styles=CSS.read_text(encoding='utf-8')
    if IMPORT not in styles:
        # Tailwind must be imported before the new CSS layer.
        backup=CSS.with_suffix('.css.bak')
        if not backup.exists():backup.write_text(styles,encoding='utf-8')
        CSS.write_text(styles.replace('@import "tailwindcss";', '@import "tailwindcss";\n'+IMPORT, 1),encoding='utf-8')
        print('[updated] resources/css/app.css')
    status={}
    for path in sorted(PAGES.rglob('*.vue')):
        outcome=change_file(path)
        status[str(path.relative_to(ROOT))]=outcome
        print(f'[{outcome}] {path.relative_to(ROOT)}')
    # Dashboard uses league context too; align its existing hero only.
    dashboard=ROOT/'resources/js/pages/Dashboard.vue'
    if dashboard.exists():
        # Dashboard hero receives same paint without touching its contextual actions.
        print(f'[{change_file(dashboard)}] resources/js/pages/Dashboard.vue')
    changed=sum(v=='updated' for v in status.values())
    print(f'\nEncabezados actualizados: {changed}. Los archivos originales se respaldaron con extensión .vue.bak.')
    print('Verifica las pantallas en Docker. El script puede ejecutarse de nuevo sin duplicar cambios.')

if __name__=='__main__':main()
