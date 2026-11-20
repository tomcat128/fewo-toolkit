#!/usr/bin/env bash
# Installiert Concrete CMS 9.5.7 im Container und danach das Fewo Toolkit.
set -euo pipefail
cd "$(dirname "$0")"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-fewo-dev-admin}"

docker compose exec -T web bash -euo pipefail -c '
  if [ ! -f composer.json ]; then
    composer create-project -n --no-install concretecms/composer /tmp/skeleton
    cp -a /tmp/skeleton/. /var/www/concrete/
    composer require -n -W "concrete5/core:9.5.7"
  fi
  mkdir -p public/application/files && chown -R www-data:www-data public/application && chown www-data:www-data public/packages
'
docker compose exec -T -u www-data web bash -euo pipefail -c "
  if [ ! -f public/application/config/database.php ]; then
    for i in \$(seq 1 30); do php -r 'exit(@fsockopen(\"db\",3306)?0:1);' && break; sleep 2; done
    ./vendor/bin/concrete c5:install -n --db-server=db --db-username=concrete --db-password=concrete \
      --db-database=concrete --site='Fewo Toolkit Dev' --starting-point=atomik_full \
      --admin-email=admin@example.com --admin-password='${ADMIN_PASSWORD}' --language=de_DE --site-locale=de_DE
  fi
  ./vendor/bin/concrete c5:package:install fewo_toolkit || ./vendor/bin/concrete c5:package:update fewo_toolkit
"

# E-Mails an Mailpit (http://localhost:8025) statt an echte Empfänger
docker compose exec -T -u www-data web bash -c "
  ./vendor/bin/concrete c5:config set -g concrete.mail.method smtp
  ./vendor/bin/concrete c5:config set -g concrete.mail.methods.smtp.server mailpit
  ./vendor/bin/concrete c5:config set -g concrete.mail.methods.smtp.port 1025
  ./vendor/bin/concrete c5:config set -g concrete.email.default.address noreply@fewo-dev.test
"

# Demo-Inhalte (Preisliste, Bilder, Testseiten /fewo-demo und /fewo-anfrage)
docker compose exec -T -u www-data web ./vendor/bin/concrete c5:exec /seed/seed.php

echo "Fertig: http://localhost:8095  (admin / ${ADMIN_PASSWORD})"
