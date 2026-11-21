#!/usr/bin/env bash
# Baut das Paket für den Upload: dist/fewo_tk2-<Version>.zip
# Ohne Entwicklungsdateien (vendor/, tests/, composer.*, phpunit.*); die Übersetzung wird als .mo mitgeliefert.
set -euo pipefail
cd "$(dirname "$0")"
VERSION="$(sed -n "s/.*\$pkgVersion = '\([^']*\)'.*/\1/p" ../packages/fewo_tk2/controller.php)"
ZIP="fewo_tk2-${VERSION}.zip"

./translate.sh >/dev/null 2>&1

docker compose exec -T -w /srv/app/public web bash -euo pipefail -c "
  rm -rf /tmp/fewo-build && mkdir -p /tmp/fewo-build
  cp -a packages/fewo_tk2 /tmp/fewo-build/fewo_tk2
  cd /tmp/fewo-build/fewo_tk2
  rm -rf vendor tests composer.json composer.lock phpunit.xml.dist .phpunit.cache .phpunit.result.cache .gitignore
  cd /srv/app/public
  ./concrete/bin/concrete c5:package:pack /tmp/fewo-build/fewo_tk2 -q -z /tmp/fewo-build/${ZIP} 2>/dev/null
"
mkdir -p ../dist
docker compose cp web:/tmp/fewo-build/"${ZIP}" ../dist/"${ZIP}" >/dev/null 2>&1
docker compose exec -T web rm -rf /tmp/fewo-build
echo "Paket: dist/${ZIP} ($(du -h ../dist/"${ZIP}" | cut -f1))"
