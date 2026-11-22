#!/usr/bin/env bash
# Lokale Test-Instanz der Website "Ferienapartment Seeherz" aus dem Server-Backup (legacy/backup_*/).
#
#   ./restore.sh           Instanz starten bzw. beim ersten Mal aus dem Backup aufbauen; danach lokale
#                          Einstellungen, neues Paket (fewo_tk2) und die Testseite /fewo-toolkit-2 einspielen
#   ./restore.sh --reset   Datenbank, Dateien und Konfiguration der Instanz verwerfen und neu aus dem Backup aufbauen
#
# Das Backup wird nur gelesen. Entpackt wird nach data/ (nicht in Git); für ein neueres Backup data/ löschen.
# Die Instanz versendet nichts nach außen: E-Mails landen in Mailpit, die Besucherstatistik ist abgeschaltet.
set -euo pipefail
cd "$(dirname "$0")"
PROJECT=fewo-seeherz-test
URL=http://localhost:8096
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin}"
# Sprachmodell für den KI-Assistenten (Block "Chat"); wird beim ersten Mal heruntergeladen (gemma3:4b: 3,3 GB)
OLLAMA_MODEL="${OLLAMA_MODEL:-gemma3:4b}"
# Neuigkeiten für den Block "Neuigkeiten" (für das lokale Fewo-Office die Adresse dieses Rechners im Netz angeben,
# z. B. http://192.168.…:4200/api/neuigkeiten/see.json – localhost wäre im Container der Container selbst)
NEWS_URL="${NEWS_URL:-https://fewo-office.com/api/neuigkeiten/seeherz.json}"

c5() { docker compose exec -T -u www-data -w /srv/app/public web ./concrete/bin/concrete "$@"; }

if [ "${1:-}" = "--reset" ]; then
  docker compose down -v
fi

# 1. Backup entpacken: Website (Konfiguration, Dateien, Sprachen), Datenverzeichnis der MariaDB und der
#    Quelltext der alten Pakete, den sich auf dem Server alle Websites teilen
if [ ! -d data/sites/seeherz ] || [ ! -d data/database/seeherz ] || [ ! -d data/sites/concrete5 ]; then
  BACKUP="$(ls -d ../../legacy/backup_*/ 2>/dev/null | sort | tail -1)"
  [ -n "${BACKUP}" ] && [ -f "${BACKUP}seeherz.tar" ] && [ -f "${BACKUP}concrete5.tar" ] \
    || { echo "Kein Backup gefunden: legacy/backup_*/seeherz.tar und concrete5.tar werden gebraucht." >&2; exit 1; }
  echo "Entpacke ${BACKUP} …"
  rm -rf data && mkdir -p data
  tar -xzf "${BACKUP}seeherz.tar" -C data --strip-components=2 \
    --exclude='var/www/sites/seeherz/seeherz_neu' --exclude='var/www/sites/seeherz/application/files.zip' \
    --exclude='var/www/sites/seeherz/packages' --exclude='var/www/sites/seeherz_httpd' \
    var/www/sites/seeherz var/www/database/seeherz
  tar -xzf "${BACKUP}concrete5.tar" -C data --strip-components=2 --exclude='.git' var/www/sites/concrete5
fi

# 2. Beim ersten Start: Volumes mit dem Stand des Backups füllen (data/ selbst bleibt unverändert)
if ! docker volume inspect "${PROJECT}_db" >/dev/null 2>&1; then
  echo "Übertrage Datenbank und Dateien in die Volumes …"
  docker compose create >/dev/null
  docker run --rm -v "${PROJECT}_db":/dst -v "$PWD/data/database/seeherz":/src:ro mariadb:11.2.2 \
    bash -c 'cp -a /src/. /dst/ && chown -R mysql:mysql /dst'
  docker run --rm -u root --entrypoint bash \
    -v "${PROJECT}_config":/dst/config -v "${PROJECT}_files":/dst/files -v "${PROJECT}_languages":/dst/languages \
    -v "$PWD/data/sites/seeherz/application":/src:ro fehrenbacher/concrete5:9.5.7 \
    -c 'for d in config files languages; do cp -a /src/$d/. /dst/$d/ && chown -R www-data:www-data /dst/$d; done'
fi

# 3. Starten. Einzeln eingebundene Dateien (local.php, page.php) sieht der Container erst nach einem Neustart.
docker compose up -d --wait
docker compose restart web >/dev/null
for i in $(seq 1 30); do curl -s -o /dev/null "${URL}/" && break; sleep 1; done

# 4. Für den lokalen Betrieb einrichten – vor allem anderen, damit keine E-Mail die Instanz verlässt
c5 c5:exec /seed/local.php "${ADMIN_PASSWORD}" 2>&1 | grep -v Deprecated

# 5. Neues Paket neben dem alten (das alte ist Version 0.x), Sprachmodell und die Testseite mit allen neuen Blöcken
if c5 c5:info 2>/dev/null | grep -Eq 'Fewo Toolkit( 2)? \([2-9]\.'; then
  c5 c5:package:update fewo_tk2 2>&1 | grep -v Deprecated || true   # meldet einen Fehler, wenn die Version unverändert ist
else
  c5 c5:package:install fewo_tk2 2>&1 | grep -v Deprecated
fi
# Nach dem Update kennt der Zwischenspeicher von Concrete neue Felder der Wohnungen sonst erst beim nächsten Lauf
c5 c5:clear-cache -q 2>&1 | grep -v Deprecated || true
docker compose exec -T ollama ollama list | grep -q "^${OLLAMA_MODEL}[[:space:]]" || {
  echo "Lade das Sprachmodell ${OLLAMA_MODEL} …"
  docker compose exec -T ollama ollama pull "${OLLAMA_MODEL}" 2>&1 | tail -1
}
c5 c5:exec /seed/page.php "${OLLAMA_MODEL}" "${NEWS_URL}" 2>&1 | grep -v Deprecated
c5 c5:clear-cache -q 2>&1 | grep -v Deprecated || true

echo "Fertig: ${URL}/fewo-toolkit-2  (E-Mails: http://localhost:8026) – von anderen Rechnern im Netz: http://$(hostname -I | cut -d' ' -f1):8096"
