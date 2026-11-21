#!/usr/bin/env bash
# Prüft das gebaute Upload-Paket (dist/fewo_tk2-<Version>.zip) in einer frischen Concrete-Installation:
# eigene Wegwerf-Container, kein eingebundener Quelltext, keine Entwicklungsabhängigkeiten.
set -euo pipefail
cd "$(dirname "$0")"
ZIP="$(ls -t ../dist/fewo_tk2-*.zip | head -1)"
NET=fewo-packtest
C5="./concrete/bin/concrete"

cleanup() { docker rm -f ${NET}-web ${NET}-db >/dev/null 2>&1 || true; docker network rm ${NET} >/dev/null 2>&1 || true; }
trap cleanup EXIT
cleanup

docker network create ${NET} >/dev/null
docker run -d --name ${NET}-db --network ${NET} -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=concrete \
  -e MARIADB_USER=concrete -e MARIADB_PASSWORD=concrete -e TZ=Europe/Berlin mariadb:11.4 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
docker run -d --name ${NET}-web --network ${NET} -e TZ=Europe/Berlin fehrenbacher/concrete5:9.5.7 >/dev/null
docker cp "${ZIP}" ${NET}-web:/tmp/package.zip >/dev/null
docker cp seed.php ${NET}-web:/tmp/seed.php >/dev/null

echo "Warte auf die Datenbank …"
for i in $(seq 1 60); do
  docker exec ${NET}-db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break
  sleep 2
done

docker exec -u www-data -w /srv/app/public ${NET}-web bash -euo pipefail -c "
  ${C5} c5:install -n --db-server=${NET}-db --db-username=concrete --db-password=concrete --db-database=concrete \
    --site='Pakettest' --starting-point=atomik_full --admin-email=admin@example.com --admin-password=paket-test-admin \
    --language=de_DE --site-locale=de_DE --timezone=Europe/Berlin 2>&1 | grep -vE 'passed|Deprecated' | tail -8
  php -r '\$zip = new ZipArchive(); \$zip->open(\"/tmp/package.zip\") === true or exit(1); \$zip->extractTo(\"packages\") or exit(1);'
  test ! -e packages/fewo_tk2/vendor
  ${C5} c5:package:install fewo_tk2 2>&1 | grep -v Deprecated
  ${C5} c5:config set -g concrete.debug.error_reporting 32767 >/dev/null 2>&1
  ${C5} c5:exec /tmp/seed.php 2>&1 | grep -v Deprecated
"

echo "Seiten abrufen …"
docker exec -u www-data -w /srv/app/public ${NET}-web bash -euo pipefail -c '
  for path in fewo-demo fewo-anfrage fewo-doppelt; do
    code=$(curl -s -o /tmp/page.html -w "%{http_code}" "http://localhost/index.php/$path")
    echo "  /$path: HTTP $code, $(grep -o "fewo-[a-z]*__" /tmp/page.html | sort -u | wc -l) Block-Bausteine"
    test "$code" = 200
  done
  curl -s http://localhost/index.php/fewo-demo > /tmp/demo.html
  grep -q "fewo-day--pm-booked" /tmp/demo.html     # Belegung (JSON) geladen
  grep -q "Nebensaison" /tmp/demo.html             # Preisliste (JSON) geladen
  ! grep -q "Vergangene Saison" /tmp/demo.html     # abgelaufene Saisons erscheinen nicht
  curl -s http://localhost/index.php/fewo-doppelt > /tmp/doppelt.html
  grep -q "fewo-day--pm-request" /tmp/doppelt.html # Belegung im ICS-Format über sabre/vobject aus dem Concrete-Kern
  grep -q "fewo-prices--list" /tmp/doppelt.html
  grep -q "Ganzjährig" /tmp/doppelt.html           # Preisliste im XML-Format
  grep -q "data-fewo-quote-calendar" /tmp/demo.html  # Preisrechner kennt die Belegung der Wohnung
  grep -q "data-fewo-tour" /tmp/demo.html          # 360°-Rundgang
  grep -q "assets/pannellum/pannellum.js" /tmp/demo.html
  grep -q "Weitere Monate anzeigen" /tmp/demo.html # deutsche Übersetzung (.mo) aktiv
  grep -q "/ Nacht" /tmp/demo.html
  ./concrete/bin/concrete c5:package:uninstall fewo_tk2 2>/dev/null | tail -1
'
# Nach dem Deinstallieren bleiben Anfragen und zwischengespeicherte Daten in der Datenbank
TABLES="$(docker exec ${NET}-db mariadb -uconcrete -pconcrete concrete -N -B -e "SELECT CONCAT((SELECT COUNT(*) FROM FewoApartments), ' Wohnungen, ', (SELECT COUNT(*) FROM FewoFeeds WHERE body IS NOT NULL), ' abgerufene Adressen, ', (SELECT COUNT(*) FROM FewoInquiries), ' Anfragen')")"
echo "Nach der Deinstallation erhalten: ${TABLES}"

# Nicht gezählt: Warnungen zum Sitzungsstart – sie entstehen, weil das Demo-Skript auf der Kommandozeile
# Text ausgibt, bevor Concrete eine Sitzung öffnet, und haben nichts mit dem Paket zu tun.
LOG="$(docker exec ${NET}-db mariadb -uconcrete -pconcrete concrete -N -B -e "SELECT CONCAT(channel, ' [', level, '] ', LEFT(message, 300)) FROM Logs WHERE level >= 300 AND message NOT LIKE '%after headers have already been sent%'")"
if [ -n "${LOG}" ]; then
  echo "Warnungen und Fehler im Concrete-Log:"
  echo "${LOG}"
  exit 1
fi
echo "OK: ${ZIP} lässt sich installieren, anzeigen und wieder deinstallieren."
