# ToDo – Fewo Toolkit 2.0 (Concrete CMS 9.5.7)

Stand: 20.11.2026. Das neue Paket liegt unter `packages/fewo_toolkit/`, das alte unter `legacy/`.
Testumgebung: `dev/` (siehe Abschnitt unten).

## Erledigt und getestet

- [x] Paket-Grundgerüst für Concrete 9.5 / PHP 8.1+, Doctrine-Entities (Preislisten, Saisons, Anfragen, ICS-Zwischenspeicher)
- [x] Installation und Deinstallation über `c5:package:install` in Concrete 9.5.7 getestet
- [x] Unit-Tests (27 Tests): Preisberechnung, ICS-Parser, Belegungslogik, XML-Import im alten Format, Geldbeträge
- [x] Block **Belegungsplan**: beliebiger ICS-Feed, Zwischenspeicher in der Datenbank, halbe Wechseltage, "auf Anfrage", ohne Cronjob
- [x] Block **Preisübersicht**: Saisons mit Preisbereichen ("1–2 Gäste"), Bedingungen
- [x] Block **Preisrechner**: Ajax und ohne JavaScript, Link zum Anfrageformular mit Vorbelegung
- [x] Block **Anfrageformular**: Validierung, Honeypot, Zeitsperre, Einwilligung, Speicherung, Mail an Vermieter und Gast (mit Mailpit getestet)
- [x] Block **Ausstattungsliste**, **Bildergalerie** (Ordner/Dateiset, Lightbox ohne Bibliothek), **Karte** (Leaflet/OSM lokal, Zwei-Klick-Lösung)
- [x] Dashboard: Preislisten (Liste, Bearbeiten, Kopieren, Löschen, Import) und Anfragen (Liste, Detail, Status, Löschfrist) laden fehlerfrei

## Offen

### Übersetzung (wichtig)
- [ ] Alle Texte sind derzeit englische Quelltexte. Deutsche Übersetzung anlegen:
      `c5:package:translate fewo_toolkit` → `languages/de_DE/LC_MESSAGES/messages.po` ausfüllen → `.mo` erzeugen
- [ ] Danach Frontend, Block-Formulare, Dashboard und E-Mails auf Deutsch prüfen

### Noch nicht getestet
- [ ] Dashboard-Formulare absenden: Preisliste speichern, Validierungsfehler, Saison "+1 Jahr", Spalten bei geänderter Gästeanzahl
- [ ] Import einer echten Preisliste aus dem alten Toolkit (XML-Datei und eingefügter Text)
- [ ] Anfragen-Dashboard: Status ändern, Löschen, Löschfrist speichern und automatisches Löschen
- [ ] Block-Bearbeitungsdialoge im Browser (Hinzufügen/Bearbeiten aller 7 Blöcke, dynamische Zeilen in Ausstattungsliste, Karte, Galerie-Quelle)
- [ ] Im Browser: Lightbox (Tastatur, Wischen), Karte nach Zustimmung, "Karten immer laden", Preisrechner-Ajax, mobile Ansicht
- [ ] Captcha im Anfrageformular
- [ ] Concrete-Logs nach Warnungen durchsehen (die letzte Prüfung wurde abgebrochen)
- [ ] Belegungsplan: Verhalten bei nicht erreichbarem Feed, "jetzt neu laden", Feeds von Airbnb, Booking.com, Fewo-direkt, Smoobu
- [ ] Mehrere Blöcke desselben Typs auf einer Seite

### Ergänzungen
- [ ] Unit-Tests für `InquiryValidator`
- [ ] README für das Paket: Installation, Konfiguration (SMTP, Captcha), Umstieg vom alten Toolkit
- [ ] Barrierefreiheit und Kontraste prüfen (z. B. axe/Lighthouse), Screenshots der Blöcke
- [ ] Theme-Anbindung: CSS-Variablen an das neue Theme anpassen
- [ ] Paket für den Upload bauen (`c5:package:pack`, ohne `vendor/`, `tests/`, `composer.*`)
- [ ] Sicherheits-Review vor dem Livegang

### Bewusst zurückgestellt (laut Absprache)
- [ ] PDF-Angebot/-Rechnung
- [ ] Preislisten-Export und Synchronisation zwischen Websites
- [ ] Plugin-Widgets (Pegelstand, Wetter, Bewertungen)
- [ ] Neues Theme als Ersatz für `fewo_themes`

## Testumgebung

```bash
cd dev
docker compose up -d --build
./setup.sh                      # einmalig: Concrete 9.5.7 + Paket + Demo-Inhalte
```

- Website: http://localhost:8095/index.php/fewo-demo und /fewo-anfrage (Admin: `admin` / `fewo-dev-admin`)
- E-Mails: http://localhost:8025 (Mailpit)
- Unit-Tests: `cd packages/fewo_toolkit && composer install && vendor/bin/phpunit`
- Pausieren: `docker compose stop` (Daten bleiben erhalten)
