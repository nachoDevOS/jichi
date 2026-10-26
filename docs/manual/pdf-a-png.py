"""Pasa cada página de un PDF a PNG para mostrarla en grabar-impresion.cjs.

A 3 px por punto (4,5 la hoja chica del carnet): en el visor se ven grandes y nítidas.
Uso: python docs/manual/pdf-a-png.py documento.pdf carpeta  → imprime la lista JSON.
"""

import json
import os
import sys

import fitz

pdf, carpeta = sys.argv[1], sys.argv[2]
rutas = []
with fitz.open(pdf) as doc:
    for i, pagina in enumerate(doc):
        # Las hojas chicas (el carnet) se agrandan más, para que llenen el visor igual.
        escala = 3 if pagina.rect.width > 400 else 4.5
        ruta = os.path.join(carpeta, f"hoja{i}.png")
        pagina.get_pixmap(matrix=fitz.Matrix(escala, escala)).save(ruta)
        rutas.append({"ruta": ruta, "vertical": pagina.rect.height > pagina.rect.width})
print(json.dumps(rutas))
