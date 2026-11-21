#!/usr/bin/env python3
"""Trägt die Übersetzungen aus de_DE.py in die von Concrete erzeugte messages.po ein.

Aufruf: apply.py <pfad/zur/messages.po>
Bricht mit Fehlercode 1 ab, wenn Texte ohne Übersetzung übrig bleiben.
"""
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from de_DE import TRANSLATIONS  # noqa: E402

PLACEHOLDER = re.compile(r"%(?:\d+\$)?[sd]|%%")


def unquote(block: str) -> str:
    text = "".join(re.findall(r'^"(.*)"$', block, re.M))
    return text.replace('\\"', '"').replace("\\n", "\n").replace("\\\\", "\\")


def quote(text: str) -> str:
    return '"' + text.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n") + '"'


def field(entry: str, key: str):
    match = re.search(r"^" + key + r' ((?:".*"\n?)+)', entry, re.M)
    return unquote(match.group(1)) if match else None


def main(path: Path) -> int:
    header, *entries = path.read_text(encoding="utf-8").strip().split("\n\n")
    header = header.replace('"Last-Translator: \\n"', '"Last-Translator: Fewo Toolkit\\n"')
    out, missing, used, problems = [header], [], set(), []
    for entry in entries:
        msgid = field(entry, "msgid")
        plural = field(entry, "msgid_plural")
        translation = TRANSLATIONS.get(msgid)
        comments = [line for line in entry.split("\n") if line.startswith("#")]
        lines = comments + ["msgid " + quote(msgid)]
        if translation is None:
            missing.append(msgid)
            translation = ["", ""] if plural else ""
        used.add(msgid)
        if plural is not None:
            if not isinstance(translation, list) or len(translation) != 2:
                problems.append(f"Mehrzahlform fehlt: {msgid}")
                translation = ["", ""]
            lines.append("msgid_plural " + quote(plural))
            lines += [f"msgstr[{i}] " + quote(text) for i, text in enumerate(translation)]
            pairs = [(msgid, translation[0]), (plural, translation[1])]
        else:
            if isinstance(translation, list):
                problems.append(f"Unerwartete Mehrzahlform: {msgid}")
                translation = translation[0]
            lines.append("msgstr " + quote(translation))
            pairs = [(msgid, translation)]
        for source, target in pairs:
            if target and sorted(PLACEHOLDER.findall(source)) != sorted(PLACEHOLDER.findall(target)):
                problems.append(f"Platzhalter weichen ab: {source!r} -> {target!r}")
        out.append("\n".join(lines))
    path.write_text("\n\n".join(out) + "\n", encoding="utf-8")

    for msgid in missing:
        print(f"FEHLT: {msgid}")
    for msgid in sorted(set(TRANSLATIONS) - used):
        print(f"Nicht mehr verwendet: {msgid}")
    for problem in problems:
        print(problem)
    print(f"{len(entries) - len(missing)} von {len(entries)} Texten übersetzt")
    return 1 if missing or problems else 0


if __name__ == "__main__":
    sys.exit(main(Path(sys.argv[1])))
