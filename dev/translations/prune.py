#!/usr/bin/env python3
"""Entfernt nicht mehr verwendete Übersetzungen aus de_DE.py.

Aufruf (im Ordner dev/):
    ./translate.sh 2>&1 | python3 translations/prune.py

Liest die Ausgabe von translate.sh und löscht alle Einträge, die dort als "Nicht mehr verwendet: …"
gemeldet werden – auch solche, die über mehrere Zeilen gehen. Leere Abschnitte verschwinden mit.
"""
import json
import re
import sys
from pathlib import Path

PREFIX = "Nicht mehr verwendet: "
FILE = Path(__file__).parent / "de_DE.py"


def main() -> int:
    unused = [line[len(PREFIX):].rstrip("\n") for line in sys.stdin if line.startswith(PREFIX)]
    if not unused:
        print("Nichts zu entfernen.")
        return 0
    keys = {json.dumps(key, ensure_ascii=False): key for key in unused}
    lines = FILE.read_text(encoding="utf-8").split("\n")
    out, removed, i = [], set(), 0
    while i < len(lines):
        stripped = lines[i].strip()
        hit = next((key for quoted, key in keys.items() if stripped.startswith(quoted + ":")), None)
        if hit is None:
            out.append(lines[i])
            i += 1
            continue
        removed.add(hit)
        depth = 0
        while True:
            # Klammern außerhalb von Zeichenketten zählen: Mehrzahlformen stehen in [ … ]
            code = re.sub(r'"(?:\\.|[^"\\])*"', '""', lines[i])
            depth += code.count("[") - code.count("]")
            done = depth == 0 and code.rstrip().endswith(",")
            i += 1
            if done:
                break
    text = "\n".join(out)
    text = re.sub(r"\n    # [^\n]+\n(?=\n    # |\n?\})", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    FILE.write_text(text, encoding="utf-8")
    for key in unused:
        print(("entfernt: " if key in removed else "NICHT GEFUNDEN: ") + key)
    return 0 if removed == set(unused) else 1


if __name__ == "__main__":
    sys.exit(main())
