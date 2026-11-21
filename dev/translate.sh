#!/usr/bin/env bash
# Aktualisiert die deutsche Übersetzung des Pakets:
#   1. Texte aus dem Quelltext sammeln (messages.pot / messages.po)
#   2. Übersetzungen aus dev/translations/de_DE.py eintragen
#   3. messages.mo erzeugen
# Neue Texte: in dev/translations/de_DE.py ergänzen und dieses Skript erneut aufrufen.
set -euo pipefail
cd "$(dirname "$0")"
PO=../packages/fewo_tk2/languages/de_DE/LC_MESSAGES/messages.po

extract() {
  # Läuft als root, weil www-data im eingebundenen Paketordner nicht schreiben darf.
  docker compose exec -T -w /srv/app/public web bash -euo pipefail -c "
    ./concrete/bin/concrete c5:package:translate fewo_tk2 --allow-as-root -l de_DE -x -q
    chown -R $(id -u):$(id -g) packages/fewo_tk2/languages
  "
}

extract
python3 translations/apply.py "$PO"
extract   # zweiter Lauf erzeugt die .mo aus der ausgefüllten .po
docker compose exec -T -u www-data -w /srv/app/public web ./concrete/bin/concrete c5:clear-cache -q
echo "Übersetzung aktualisiert: $PO"
