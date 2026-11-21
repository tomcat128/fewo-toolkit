#!/usr/bin/env bash
# Installiert Concrete CMS im Container (Image fehrenbacher/concrete5) und danach das Fewo Toolkit.
# Kann beliebig oft aufgerufen werden: bereits Erledigtes wird übersprungen, das Paket aktualisiert.
set -euo pipefail
cd "$(dirname "$0")"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-fewo-dev-admin}"
C5="./concrete/bin/concrete"

docker compose up -d --wait db
docker compose up -d

docker compose exec -T -u www-data -w /srv/app/public web bash -euo pipefail -c "
  if [ ! -f application/config/database.php ]; then
    ${C5} c5:install -n --db-server=db --db-username=concrete --db-password=concrete \
      --db-database=concrete --site='Fewo Toolkit Dev' --starting-point=atomik_full \
      --admin-email=admin@example.com --admin-password='${ADMIN_PASSWORD}' \
      --language=de_DE --site-locale=de_DE --timezone=Europe/Berlin
  fi
  if ${C5} c5:info | grep -q '^Fewo Toolkit'; then
    ${C5} c5:package:update fewo_tk2 || true   # meldet einen Fehler, wenn die Version unverändert ist
  else
    ${C5} c5:package:install fewo_tk2
  fi

  # E-Mails an Mailpit (http://localhost:8025) statt an echte Empfänger
  ${C5} c5:config set -g concrete.mail.method smtp
  ${C5} c5:config set -g concrete.mail.methods.smtp.server mailpit
  ${C5} c5:config set -g concrete.mail.methods.smtp.port 1025
  ${C5} c5:config set -g concrete.email.default.address noreply@fewo-dev.test

  # Deutsche Oberfläche; alle PHP-Meldungen ins Log (docker compose logs web)
  [ -f application/languages/de_DE/LC_MESSAGES/messages.mo ] || ${C5} c5:language-install --add de_DE --core
  ${C5} c5:config set -g concrete.debug.error_reporting 32767

  # Demo-Inhalte (Belegung und Preislisten im Fewo-Office-Format, Bilder, Testseiten /fewo-demo, /fewo-anfrage, /fewo-doppelt)
  ${C5} c5:exec /seed/seed.php
"

echo "Fertig: http://localhost:8095  (admin / ${ADMIN_PASSWORD})"
