"""Arma la vista previa de un tutorial: diez tramos de 1,8 s repartidos en el video, 640×360, sin sonido.

La tarjeta del Manual la repite en bucle para que el video se vea «andando» sin bajar
el MP4 entero (~200 KB contra 15–20 MB).
Uso: python docs/manual/vista-previa.py ser-pescador
"""

import os
import subprocess
import sys

clave = sys.argv[1]
raiz = os.path.join(os.path.dirname(__file__), "..", "..")
video = os.path.join(raiz, "resources", "manual", f"{clave}.mp4")
dura = float(subprocess.check_output(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video]))
# Diez tramos repartidos a lo largo del video, salteando la presentación (los primeros 8 s).
segundos = [8 + (dura - 12) * i / 9 for i in range(10)]

salida = os.path.join(raiz, "resources", "manual", f"{clave}.previa.mp4")
# Corrido 1,5 s: así ningún tramo arranca justo en un corte de cámara.
tramos = [f"[0:v]trim=start={s + 1.5}:duration=1.8,setpts=PTS-STARTPTS[t{i}]" for i, s in enumerate(segundos)]
filtro = ";".join(tramos) + ";" + "".join(f"[t{i}]" for i in range(len(segundos))) \
    + f"concat=n={len(segundos)}:v=1:a=0,scale=640:-2,fps=24[v]"
subprocess.check_call(["ffmpeg", "-y", "-loglevel", "error", "-i", video, "-filter_complex", filtro, "-map", "[v]",
                       "-an", "-c:v", "libx264", "-preset", "slow", "-crf", "28", "-pix_fmt", "yuv420p",
                       "-movflags", "+faststart", salida])
print(salida, os.path.getsize(salida) // 1024, "KB", len(segundos), "tramos")
