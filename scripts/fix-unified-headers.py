#!/usr/bin/env python3
"""Corrige de forma conservadora clases insertadas por el instalador previo.
No cambia datos ni acciones Vue. Deja copia .fixbak al modificar archivos.
"""
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parent.parent
PAGES=ROOT/'resources/js/pages/League'
CSS=ROOT/'resources/css/app.css'
TARGETS=['Fields/Index.vue','Schedule/Index.vue','Statistics/Index.vue','Discipline/Index.vue']
MARK='proliga-unified-hero'
TAG=re.compile(r'<(/?)([A-Za-z][\w.:-]*)(?=[\s/>])([^<>]*?)>',re.S)
VOID={'input','img','br','hr','meta','link','source','area','wbr'}
def nodes(text):
    stack=[]; res=[]
    for m in TAG.finditer(text):
        closing,name,attrs=m.group(1),m.group(2).lower(),m.group(3)
        if closing:
            for i in range(len(stack)-1,-1,-1):
                if stack[i]['name']==name:
                    stack[i]['end']=m.end(); del stack[i:]; break
            continue
        n={'name':name,'start':m.start(),'open_end':m.end(),'attrs':attrs,'end':len(text),'parent':stack[-1] if stack else None}
        res.append(n)
        if not (m.group().endswith('/>') or name in VOID):stack.append(n)
    return res

def classes(node):
    match=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',node['attrs'],re.S)
    return match.group(2).split() if match else []

def change_class(text,node,add=False):
    tag=text[node['start']:node['open_end']]
    match=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',tag,re.S)
    if not match:return text
    old=match.group(2).split()
    new=[c for c in old if c!=MARK]
    if add:new.append(MARK)
    fixed=tag[:match.start(2)]+' '.join(new)+tag[match.end(2):]
    return text[:node['start']]+fixed+text[node['open_end']:]

def repair(path):
    if not path.exists():return 'No existe; omitido'
    src=path.read_text(encoding='utf-8'); found=nodes(src)
    marked=[n for n in found if MARK in classes(n)]
    if not marked:return 'Sin clase anterior; omitido'
    replacements=[]
    for n in marked:
        parent=n['parent']; move_to=None
        for _ in range(3):
            if parent is None:break
            cl=classes(parent)
            parent_hero=any(c.endswith('-hero') or c.endswith('-banner') for c in cl)
            # no mover a una sección que también contiene otras tarjetas
            if parent_hero and parent['end']-parent['start']<10000 and MARK not in cl:
                move_to=parent;break
            parent=parent['parent']
        if move_to is not None:
            replacements.extend([(n,False),(move_to,True)])
    result=src
    for n,add in sorted(replacements,key=lambda pair:pair[0]['start'],reverse=True):
        result=change_class(result,n,add)
    if result==src:return 'Encabezado identificado; CSS corregido'
    bak=path.with_suffix(path.suffix+'.fixbak')
    if not bak.exists():bak.write_text(src,encoding='utf-8')
    path.write_text(result,encoding='utf-8')
    return 'Clases anidadas corregidas'

def main():
    if not CSS.exists() or not PAGES.exists():raise SystemExit('Ejecuta desde la raíz de ProLiga')
    for rel in TARGETS:
        print(f'{rel}: {repair(PAGES/rel)}')
    contents=CSS.read_text(encoding='utf-8')
    imp='@import "./proliga-unified-headers.css";'
    if imp not in contents:
        bak=CSS.with_suffix('.css.fixbak')
        if not bak.exists():bak.write_text(contents,encoding='utf-8')
        CSS.write_text(contents.replace('@import "tailwindcss";', '@import "tailwindcss";\n'+imp,1),encoding='utf-8')
        print('Estilos importados')
    print('Aplica cambios con scripts/setup.ps1 desde PowerShell.')
if __name__=='__main__':main()
