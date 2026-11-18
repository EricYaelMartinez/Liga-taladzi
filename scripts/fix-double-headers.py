#!/usr/bin/env python3
"""Repara encabezados anidados, solo en Campos y Sanciones de ProLiga.
Conserva contenido Vue, eventos, permisos, colores y datos. Usa respaldos.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent.parent
TARGETS = {
    'Fields/Index.vue': ('Campos y disponibilidad', ('fields-hero','venues-hero','field-hero','facilities-hero','facilities-header','fields-header')),
    'Discipline/Index.vue': ('Sanciones y apelaciones', ('discipline-hero','discipline-header','sanctions-hero','sanctions-header')),
}
TAG = re.compile(r'<!--[^>]*-->|<(/?)([A-Za-z][\w.:-]*)(?=[\s/>])([^<>]*?)>', re.S)
VOID = {'input','img','br','hr','meta','link','source','area','wbr'}
CLASS = re.compile(r'\bclass\s*=\s*(["\'])(.*?)\1', re.S)

def nodes_of(src):
    stack=[]; nodes=[]
    for match in TAG.finditer(src):
        if match.group().startswith('<!--'): continue
        closing,name,attrs=match.group(1),match.group(2).lower(),match.group(3)
        if closing:
            for j in range(len(stack)-1,-1,-1):
                if stack[j]['name']==name:
                    stack[j]['end']=match.end()
                    del stack[j:]
                    break
        else:
            node={'name':name,'start':match.start(),'open_end':match.end(),'end':len(src),'attrs':attrs,'parent':stack[-1] if stack else None}
            nodes.append(node)
            if not (match.group().endswith('/>') or name in VOID):stack.append(node)
    return nodes

def classes(node):
    m=CLASS.search(node['attrs'])
    return m.group(2).split() if m else []

def replace_classes(tag,new_classes):
    m=CLASS.search(tag)
    val=' '.join(dict.fromkeys(new_classes))
    if m:return tag[:m.start(2)]+val+tag[m.end(2):]
    return tag[:-1]+' class="'+val+'">'

def fix(src,title,legacy_classes):
    if '<template>' not in src:return None,'La vista no tiene template; sin cambios'
    nodes=nodes_of(src)
    heading=next((n for n in nodes if n['name']=='h1' and title in src[n['open_end']:n['end']]),None)
    if not heading:return None,'No se encontró el título esperado; sin cambios'
    ancestors=[]; p=heading['parent']
    while p:
        if p['name'] in ('div','header','section'):ancestors.append(p)
        p=p['parent']
    inner=next((n for n in ancestors if 'proliga-unified-hero' in classes(n)),None)
    if inner is None:return None,'El encabezado ya no tiene clase interior; revisa manualmente'
    outer=next((n for n in ancestors if n['start']<inner['start'] and any(c in classes(n) for c in legacy_classes)),None)
    if outer is None:
        # Fallback only when the ancestor has a clearly named hero class, and is close.
        outer=next((n for n in ancestors if n['start']<inner['start'] and any(c.endswith('-hero') for c in classes(n))),None)
    if outer is None:
        # Already unified on a single container? Nothing to fix.
        return None,'No se detectó un contenedor exterior de encabezado duplicado'
    # Drop original background class, preserve layout classes and dynamic bindings.
    old_outer=src[outer['start']:outer['open_end']]
    old_inner=src[inner['start']:inner['open_end']]
    new_outer=replace_classes(old_outer,[c for c in classes(outer) if c not in legacy_classes and not c.endswith('-hero')]+['proliga-unified-hero'])
    new_inner=replace_classes(old_inner,[c for c in classes(inner) if c!='proliga-unified-hero' and c not in ('px-6','py-7','lg:px-9','lg:py-9','sm:px-8','lg:px-10','lg:py-9')])
    # Apply from last offset to first; only start tags are modified.
    edits=sorted([(outer['start'],outer['open_end'],new_outer),(inner['start'],inner['open_end'],new_inner)],reverse=True)
    result=src
    for a,z,rep in edits:result=result[:a]+rep+result[z:]
    return result,'Corregido: un solo encabezado y sin fondo duplicado'

def main():
    pages=ROOT/'resources/js/pages/League'
    if not pages.exists():raise SystemExit('Error: extrae el ZIP en la raíz del proyecto ProLiga')
    count=0
    for relative,(title,legacy) in TARGETS.items():
        path=pages/relative
        if not path.exists():print(f'{relative}: no encontrado; omitido');continue
        src=path.read_text(encoding='utf-8')
        updated,msg=fix(src,title,legacy)
        if updated and updated!=src:
            bak=path.with_suffix(path.suffix+'.doublehero.bak')
            if not bak.exists():bak.write_text(src,encoding='utf-8')
            path.write_text(updated,encoding='utf-8');count+=1
        print(f'{relative}: {msg}')
    print(f'Archivos corregidos: {count}. Después ejecuta scripts/setup.ps1 con Docker.')

if __name__=='__main__':main()
