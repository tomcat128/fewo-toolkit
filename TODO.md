# ToDo – Fewo Toolkit 2.0 (Concrete CMS 9.5.7)

Stand: 20.11.2026. Das neue Paket liegt unter `packages/fewo_tk2/`, das alte unter `legacy/`.
Testprojekt: `dev/` (siehe Abschnitt unten). Beschreibung für Anwender: `packages/fewo_tk2/README.md`.

**Grundsatz:** Belegung und Preise kommen immer aus Fewo-Office (`/api/wohnungen/{kürzel}/belegung.json` bzw.
`preise.json`). Im Toolkit werden keine Preise gepflegt und keine Buchungen angelegt. Die Adressen werden
einmal je Wohnung im Dashboard hinterlegt (Fewo Toolkit 2 › Wohnungen), die Blöcke wählen nur die Wohnung.
Übernommen werden nur veröffentlichte, noch nicht abgelaufene Saisons.

## Erledigt und getestet

- [x] Paket-Grundgerüst für Concrete 9.5 / PHP 8.1+, Doctrine-Entities (Wohnungen, Anfragen, Zwischenspeicher für Daten aus Fewo-Office)
- [x] Installation und Deinstallation in Concrete 9.5.7 (PHP 8.4) getestet – auch mit dem gebauten ZIP in einer frischen Installation (`dev/test-package.sh`)
- [x] Unit-Tests (97 Tests): Preisberechnung nach den Regeln von Fewo-Office, Belegung und Preisliste aus Fewo-Office in allen Formaten (JSON, XML, ICS – mit unveränderten Antworten der Schnittstelle unter `tests/fixtures/fewo-office-*`), ungültige Antworten, Belegungslogik, Geldbeträge, Formularprüfung (`InquiryValidator`), ICS-Parser
- [x] Browser-Tests (61 Tests, Playwright, `dev/e2e`): Dashboard (Wohnungen, Anfragen), Block-Dialoge, Besucheransicht, mehrere gleiche Blöcke, Barrierefreiheit
- [x] Deutsche Übersetzung: alle 490 Texte (`languages/de_DE`), Frontend, Block-Formulare, Dashboard und E-Mails auf Deutsch geprüft
- [x] Dashboard **Wohnungen**: Adressen der Belegung und der Preisliste je Wohnung, Aktualisierungsintervall, Stand des letzten Abrufs mit Fehlergrund, "Jetzt aktualisieren", Prüfung der Eingaben, löschen; getrennte Zwischenspeicher für Belegung und Preisliste (eine falsch eingetragene Adresse stört keine andere Wohnung)
- [x] Block **Belegungsplan**: Belegung aus Fewo-Office (JSON empfohlen, XML und ICS werden am Inhalt erkannt), Zwischenspeicher in der Datenbank, halbe Wechseltage, ohne Cronjob; Fewo-Office nicht erreichbar oder unbrauchbare Antwort (letzter Stand bleibt, Hinweis nur für Redakteure, kein Abruf bei jedem Seitenaufruf), "jetzt neu laden"; aufgeklappte Monate in derselben Spaltenzahl wie die erste Zeile (= Anzahl der anfangs sichtbaren Monate)
- [x] Block **Preisübersicht**: Preisliste aus Fewo-Office (JSON oder XML), Anordnung wählbar (Kacheln nebeneinander / Liste untereinander), Preisbereiche ("1–2 Gäste"), Standardpreis als "Übrige Zeiträume", abgelaufene Saisons werden aussortiert, Bedingungen (Mindestaufenthalt, Endreinigung, Anzahlung)
- [x] Block **Preisrechner**: rechnet wie Fewo-Office (Preis des Tages, an dem die Nacht endet; Standardpreis als Lückenfüller; nächsthöhere Gästeanzahl), prüft die Belegung der Wohnung (die Felder Anreise/Abreise öffnen einen eigenen Kalender mit gesperrten belegten Tagen an Stelle des Browser-Kalenders, per Tastatur bedienbar; Prüfung auf dem Server auch ohne JavaScript), Ajax und ohne JavaScript, Link zum Anfrageformular mit Vorbelegung
- [x] Block **Anfrageformular**: Validierung, Honeypot, Zeitsperre, Einwilligung, Speicherung, Mail an Vermieter und Gast (Mailpit), Captcha (falscher und richtiger Code)
- [x] Block **Ausstattungsliste** (1–4 Spalten wählbar), **Bildergalerie** (Ordner/Dateiset, Großansicht mit Tastatur und Wischen), **Karte** (Leaflet/OSM lokal, Zwei-Klick-Lösung, "Karten immer laden")
- [x] Dashboard **Anfragen**: Liste, Filter, Detail, Status ändern, löschen, Löschfrist speichern, automatisches Löschen
- [x] Block **360°-Rundgang** (Pannellum 2.5.6, im Paket enthalten): Nachfolger des alten Blocks aus `legacy/application/blocks/pannellum` – gleiche Felder (Bilder, Bilder für Mobilgeräte, Einstellungen), ohne jQuery und ohne Geräteerkennung auf dem Server, Seitenverhältnis per CSS, Platzhalter im Bearbeitungsmodus, Fehlermeldung bei ungültigen Einstellungen
- [x] Block-Dialoge (Hinzufügen/Bearbeiten) aller 8 Blöcke inkl. dynamischer Zeilen, Koordinaten-Eingabe und Galerie-Quelle
- [x] Mehrere Blöcke desselben Typs auf einer Seite (`/fewo-doppelt`)
- [x] Mobile Ansicht (390 px), Barrierefreiheit und Kontraste (axe, WCAG 2.1 A/AA) im Theme Atomik
- [x] Concrete-Log und PHP-Log (E_ALL, PHP 8.4): keine Warnungen aus dem Paket
- [x] README für das Paket, Bildschirmfotos der Blöcke (`docs/screenshots/`, erzeugt mit `dev/e2e/make-screenshots.js`)
- [x] Paket für den Upload: `dev/build.sh` → `dist/fewo_tk2-2.1.0.zip` (ohne `vendor/`, `tests/`, `composer.*`)
- [x] **KI-Assistent** (Version 2.1.0): Block **Chat (KI-Assistent)** und Dashboard **KI-Assistent**. Ein Sprachmodell,
  das über Ollama auf dem eigenen Server läuft, beantwortet Fragen der Besucher aus den aktuellen Inhalten der Website:
  öffentliche Seiten (wie in der `sitemap.xml`, Texte der Blöcke) plus Preise nach Monaten und freie Zeiträume aus
  Fewo-Office. Antwort erscheint Wort für Wort; Begrenzung je Besucher und Stunde; Fragen werden nicht gespeichert.
  Im Dashboard: Adresse von Ollama, Modell (Liste der installierten), API-Schlüssel für einen Proxy, Umfang der
  Inhalte, ausgeschlossene Seiten, zusätzliche Anweisungen, Grenzen, Testfrage, Übersicht „Was der Assistent weiß“
- [x] KI-Assistent – Geschwindigkeit: Passt die Website ins Budget (Vorgabe 48.000 Zeichen), bekommt das Modell immer
  alle Inhalte in derselben Reihenfolge. Ollama liest sie einmal ein (beim Anzeigen des Chats angestoßen), danach
  beginnen Antworten nach 1–3 Sekunden – gemessen in der Seeherz-Testinstanz ohne Grafikkarte
- [x] Aktualisierung von 2.0.0 auf 2.1.0 (`c5:package:update`) im Testprojekt und in der Seeherz-Testinstanz: neuer Block,
  neue Dashboard-Seite und neue Tabelle kommen dazu, das Dashboard-Menü zeigt die neue Seite sofort

- [x] Abruf der echten Fewo-Office-Adressen (`…/wohnungen/sonnenseele/belegung.{json,xml,ics}`, `preise.{json,xml}`) aus dem Testprojekt heraus

### Entfernt (kommt jetzt aus Fewo-Office)

- Dashboard "Preislisten" samt Bearbeiten, Kopieren, "+1 Jahr" und Import aus dem alten Toolkit
- Tabellen `FewoPricelists`, `FewoPricelistSeasons`, `FewoCalendarFeeds` (ersetzt durch `FewoFeeds`)
- Kaution, Inklusivleistungen, Hinweistext und Mindestaufenthalt je Saison – diese Angaben gibt es in der Schnittstelle nicht
- Einstellung "Welcher Tag bestimmt den Saisonpreis einer Nacht?" – es gilt immer die Regel von Fewo-Office

### Dabei gefundene und behobene Fehler

- Zwei Anfrageformulare oder Preisrechner (ohne JavaScript) auf einer Seite: HTTP 500 beim Absenden
- "Hinzufügen"-Dialoge aller Blöcke: Vorgaben wurden ignoriert (z. B. "Bedingungen anzeigen"), PHP-Warnungen wegen undefinierter Variablen
- Belegungsplan: schmaler roter bzw. grüner Streifen am Rand halb belegter Tage (An- und Abreisetage)
- Großansicht der Galerie: Klick auf den Hintergrund schloss sie nicht
- Galerie "Neueste/Älteste zuerst": zufällige Reihenfolge bei Bildern, die in derselben Sekunde hochgeladen wurden
- Anfrageformular: Fehlermeldung bzw. Dank bekam nach dem Absenden nicht den Fokus (Screenreader)
- E-Mails (Klartext): Bezeichnung und Wert klebten zusammen bzw. waren bei Umlauten verschoben; doppelte Zeilenabstände in der HTML-Nachricht
- Karte: Getippte Koordinaten wurden zu früh aufgeteilt ("47.71, 9" → falsche Werte); Zeilenenden im Markierungstext
- Ausstattungsliste: Rücksprung-Link der Fußnoten nur an der Farbe erkennbar
- Sicherheit: Zeilenumbrüche in einzeiligen Formularfeldern (Name, Ort …) werden entfernt, damit sie nicht in Mail-Kopfzeilen landen
- Löschfrist: abgelaufene Anfragen wurden nur beim Öffnen des Dashboards gelöscht – jetzt auch bei jeder neuen Anfrage
- Testprojekt: `c5:exec` meldete auch bei Erfolg einen Fehler; Demo-Kalender gingen beim Neuanlegen des Containers verloren
- Anfrageformular: ohne Standard-Absenderadresse in Concrete (Auslieferungszustand, so auch auf seeherz.com) wurde die
  Anfrage gespeichert, aber keine E-Mail verschickt – jetzt Rückfall auf die Absenderadresse für Formulare bzw. die
  des Hauptbenutzers (gefunden mit der Seeherz-Testinstanz)
- Kollision mit dem alten Paket auf derselben Website: Paket heißt jetzt `fewo_tk2` (Namespace `FewoTk2`, Dashboard
  `/dashboard/fewo_tk2`), die Blöcke `fewo_belegungsplan`, `fewo_preisrechner` usw. – das alte `fewo_toolkit` belegt
  u. a. `preisrechner`, `kontaktformular`, `karte`

- Gestaltung in Themes ohne Bootstrap (gefunden mit der Seeherz-Testinstanz, Theme `fewobacher`, Schrift 24 px), Version 2.1.1 und 2.1.2:
  Eingabefelder von Preisrechner und Anfrageformular ragten über ihre Spalte und überlappten (`box-sizing` fehlte),
  Schaltflächen waren ungestaltet (`.btn` gibt es dort nicht), Beschriftungen und Kalender winzig neben der Schrift
  des Themes (Maße in `rem`). Jetzt: `box-sizing` in allen Blöcken, alle Maße in `em`, eigene Grundgestaltung der
  Schaltflächen (`.fewo-btn`, `--fewo-accent`), die hinter `.btn` des Themes zurücktritt. Außerdem: roter Rahmen
  fehlerhafter Eingabefelder im Anfrageformular fehlte, Sternchen der Einwilligung rutschte auf schmalen Bildschirmen
  an den rechten Rand

- [x] **KI-Assistent, Version 2.2.0:**
  - **Name** des Assistenten (Dashboard): steht im Chat über jeder Antwort, das Sprachmodell kennt ihn
  - **Beispielfragen** je Frage in einer Zeile – ändern, verschieben, entfernen (Block-Dialog und Dashboard)
  - **Chat auf jeder Seite** (Dashboard): unten rechts zuerst eine kleine Schaltfläche mit Hinweistext, ein Klick
    öffnet den Chat; eigene Texte und Beispielfragen; Gespräch und Zustand bleiben beim Seitenwechsel (nur im
    Browser); nicht im Dashboard, im Bearbeitungsmodus und auf Seiten mit Chat-Block. Eigene Adressen
    `/fewo_tk2/chat/{token,ask,warm}`, das Token wird erst beim Öffnen geholt (Seiten-Zwischenspeicher)
  - **Preise rechnet der Server:** Nennt eine Frage einen Aufenthalt (auch als Nachfrage „Was würde der Aufenthalt
    dann kosten?“), rechnet der Preisrechner des Pakets – mit Saisonwechsel, Endreinigung, Anzahlung und
    Belegung – und das Modell gibt die Zahlen nur wieder; für „eine Nacht im Dezember“ bekommt es die Preise des
    Monats je Zeitraum. Anlass: `gemma3:4b` ließ die Endreinigung weg und übersah den Saisonwechsel

- [x] **Version 2.3.0 – keine Buchungsanfragen, Name „Fewo Toolkit 2“:**
  - **Anfrageformular ohne Speicherung:** Aus dem Formular entsteht keine Buchungsanfrage mehr, sondern eine
    E-Mail an die im Block hinterlegte Adresse (dazu wie bisher die abschaltbare Eingangsbestätigung an den Gast).
    Entfallen: Dashboard-Seite „Buchungsanfragen“, Tabelle `FewoInquiries` (das Update löscht sie), Löschfrist,
    Block-Option „Anfragen im Dashboard speichern“. Lässt sich die E-Mail nicht verschicken, sieht der Gast
    eine Fehlermeldung. Damit sind die Einträge zu „Dashboard Anfragen“ und zur Löschfrist weiter oben überholt
  - Das Paket heißt in der Verwaltung von Concrete **Fewo Toolkit 2** (Paketliste, Dashboard-Menü, Block-Set),
    damit es neben dem alten „Fewo Toolkit“ zu unterscheiden ist
  - Aktualisierung der Daten aus Fewo-Office zusätzlich alle **5** und **10 Minuten** wählbar
  - Testinstanzen: Anmeldung jetzt `admin` / `admin`
  - Browser-Tests: zwei veraltete Erwartungen an die Testdaten korrigiert (Ordner „Fewo Demo“, Titel des zweiten
    Rundgangs). **Offen:** `block-dialogs.spec.js` „360°-Rundgang: Vorgaben …“ kennt noch den Dialog vor dem
    Szenen-Editor und schlägt fehl; die zwei Tests danach laufen deshalb nicht. Ebenso `frontend.spec.js`
    „360°-Rundgang: Räume wechseln“ (Hotspot fehlt)

- [x] **Version 2.4.0 – Erklärungen zu Stichwörtern, Preise im Dashboard:**
  - **KI-Assistent, Erklärungen zu Stichwörtern** (Dashboard › KI-Assistent): je Zeile Stichwörter und eine
    Erklärung. Kommt ein Stichwort in der Frage vor (auch in der Frage davor, unabhängig von Schreibweise,
    Umlauten und als Teil eines Wortes), steht die Erklärung für das Modell direkt bei der Frage und gilt vor den
    Inhalten der Website (`KeywordNotes`). Anlass: Der Assistent behauptete einen privaten Stellplatz.
    Mit `gemma3:4b` geprüft: „Gibt es einen privaten Stellplatz?“ → „Nein, … keinen privaten Stellplatz.“
    **Schwächen:** Bei der Nachfrage „Und kostet der etwas?“ erfand das Modell trotzdem kostenpflichtige
    Parkplätze; eine knappe Erklärung wendet es zu breit an („Kann ich mit dem Auto anreisen?“ → „Nein …“) –
    Stichwörter eng wählen, Erklärung ausführlich schreiben
  - **Dashboard „Preise“:** je Wohnung Mindestdauer, Endreinigung, Höchstbelegung, Anzahlung und alle Saisons
    mit Preis je Gästezahl und Mindestbelegung; „Jetzt aktualisieren“ je Wohnung und für alle. Die
    Mindestbelegung liefert Fewo-Office nicht als Feld – sie ist aus den Preisen abgeleitet (bis zu welcher
    Gästezahl der niedrigste Preis gilt). **Entscheidung offen:** Soll Fewo-Office sie als eigenes Feld liefern?

- [x] **Version 2.4.2 – Belegungsplan:** Monatsname und Zeile der Wochentage haben einen eigenen Hintergrund in den
  Farben des alten Belegungsplans (`#f7d291`, `#eddabb`; im Theme über `--fewo-month-bg`, `--fewo-weekdays-bg`
  änderbar). Behoben: Vergangene belegte Tage hatten weiße Zahlen auf Grau

- [x] **Version 2.4.3:** Pfeil vor „Weitere Monate anzeigen“. **Speicher:** Bildergalerie und 360°-Rundgang
  verkleinern ein Bild nur noch, wenn der Arbeitsspeicher von PHP dafür reicht (`ImageMemory`), sonst zeigen sie
  das Original – vorher fiel mit `memory_limit = 128M` und einem Panorama von 4096 × 2048 die ganze Seite aus
  (gefunden mit `dev/test-package.sh`). **Installation:** Der Ordner in `packages/` muss `fewo_tk2` heißen;
  unter anderem Namen meldet Concrete „Defektes Paket … valid controller.php“

- [x] **Version 2.5.0 – Chat unten rechts nur auf bestimmten Seiten:** Im Dashboard „Auf allen Seiten“ oder „Nur
  auf bestimmten Seiten“ mit einer Liste von Pfaden (Unterseiten gehören dazu, `/` = Startseite, eingefügte
  Adressen werden zum Pfad gekürzt). Der Abschnitt heißt jetzt „Chat unten rechts“ statt „Chat auf jeder Seite“

- [x] **Version 2.5.1 – Speicherprüfung nachgeschärft:** Auf dem Server (128 MB) brach der Dialog „360°-Rundgang
  bearbeiten“ beim Vorschaubild trotzdem ab. Die Schätzung war zu knapp (gemessen 11,9–12,6 Byte je Bildpunkt,
  gerechnet wird jetzt mit 14 plus Dateigröße und Reserve), und gezählt wird der tatsächlich belegte Speicher.
  Unter 128 MB nachgemessen: bis etwa 6,5 Megapixel wird verkleinert (Spitze 119 MB), darüber bleibt das Original.
  **Empfehlung für den Server:** `memory_limit` auf 256–512 MB anheben, sonst gibt es von großen Panoramen keine
  kleineren Fassungen für Mobilgeräte

- [x] **Version 2.6.0 – Neuigkeiten aus Fewo-Office:**
  - Wohnung: neues Feld **Adresse der Neuigkeiten** (`…/api/neuigkeiten/<kürzel>.json` oder `.xml`), abgerufen
    und zwischengespeichert wie Belegung und Preise (Art `news` in `FewoFeeds`), „Jetzt aktualisieren“ inklusive;
    die Übersicht zeigt die Zahl der heute gültigen Neuigkeiten. RSS wird mit Hinweis abgelehnt (ohne Enddatum)
  - **Block „Neuigkeiten“:** zeigt jede Neuigkeit nur, solange der heutige Tag in ihrem Zeitraum liegt – geprüft
    auf der Website selbst, nicht nur in Fewo-Office; ohne aktuelle Neuigkeit unsichtbar. Optionen: Überschrift,
    Zeitraum anzeigen. Speichern nur mit einer Wohnung, die eine Adresse der Neuigkeiten hat
  - **KI-Assistent** kennt die aktuellen Neuigkeiten (bei „Preise, freie Zeiträume … einbeziehen“).
    Mit `gemma3:4b` geprüft: „Kann ich im Schwimmbad schwimmen gehen?“ → „Das Schwimmbad macht Betriebspause!“

- [x] **Version 2.6.1 – Wichtigkeit der Neuigkeiten:** Fewo-Office liefert `importance` (`info`, `notice`,
  `important`). Der Block markiert danach: Info grün, Hinweis gelb, Wichtige Mitteilung rot (Farben wie in
  Fewo-Office), mit der Bezeichnung als Text über dem Titel; wichtige zuerst, innerhalb einer Stufe in der
  Reihenfolge von Fewo-Office. Ohne oder mit unbekannter Angabe: Info. Der Assistent bekommt die Stufe mit.
  Testdaten jetzt aus dem echten Feed (`fewo-office.com/api/neuigkeiten/seeherz.*`); die Seeherz-Testinstanz liest
  ihn direkt

## Offen

### Braucht echte Daten bzw. eine Entscheidung
- [ ] KI-Assistent auf dem Server: Ollama muss dort laufen (Container im selben Docker-Netz, Port 11434 nicht
  veröffentlichen) und braucht für `gemma3:4b` rund 3–4 GB freien Arbeitsspeicher; ohne Grafikkarte dauert das erste
  Einlesen der Website knapp eine Minute, danach kommen Antworten in Sekunden (README, Abschnitt „KI-Assistent“)
- [ ] KI-Assistent – Genauigkeit: Inhaltsfragen beantwortet `gemma3:4b` meist richtig. Preise eines Aufenthalts
  und eines Monats rechnet seit 2.2.0 der Server; das Modell formuliert nur noch und lässt dabei gelegentlich
  etwas weg (z. B. die Aufschlüsselung oder den Hinweis „belegt“). Fehleranfällig bleiben Preisfragen, in denen
  kein Datum und kein Monat erkannt wird („Was kostet es an Ostern?“), und Fragen nach freien Zeiträumen
- [ ] KI-Assistent – Wissen: Blöcke des alten Pakets liefern keinen Suchtext und fehlen deshalb (z. B. die alte
  Ausstattungsliste auf `/ausstattung`); mit den neuen Blöcken sind die Inhalte dabei
- [ ] Wie Fewo-Office einen Fehler meldet (z. B. unbekanntes Kürzel), ist nur angenommen: geprüft werden HTTP-Status und `status.type` ≠ `OK`
- [ ] Standardpreise (`default_prices`) sind bei der Wohnung "sonnenseele" leer – mit echten Werten gegenprüfen
- [ ] 360°-Rundgang mit den Bildern und Einstellungen eines **echten** Rundgangs der alten Website prüfen (bisher nur mit erzeugten Testpanoramen; Mehrfachauflösung/`multires` ist ungetestet)
- [ ] E-Mail-Versand über den echten SMTP-Server (Zustellung, Spam-Bewertung, Absenderadresse)
- [ ] Theme-Anbindung: CSS-Variablen an das neue Theme anpassen, Kontraste dort erneut prüfen (`dev/e2e/tests/accessibility.spec.js`)

### Sicherheits-Review – verbleibende Punkte (vor dem Livegang entscheiden)
Geprüft: Ausgaben maskiert, alle ändernden Dashboard-Aktionen mit Token und per POST, XML-Import ohne externe Entitäten,
Mail-Kopfzeilen, Größenbegrenzung für abgerufene Daten (5 MB), XML ohne Dokumenttyp-Definitionen.
- [ ] Das Anfrageformular prüft die Belegung nicht (Anfragen für belegte Zeiträume sind möglich) – so gewollt?
- [ ] Anfrageformular ohne Captcha hat keine Mengenbegrenzung je Absender – bei Spam Captcha einschalten oder Begrenzung ergänzen
- [ ] Adressen für Belegung und Preisliste: Der Server ruft jede http(s)-Adresse ab, die ein Redakteur einträgt (auch interne). Unkritisch, solange nur vertrauenswürdige Personen Blöcke bearbeiten – sonst auf die Fewo-Office-Domain beschränken
- [ ] Karte: Die "Quellenangabe (HTML)" wird unverändert ausgegeben – ebenfalls nur für Redakteure erreichbar
- [ ] 360°-Rundgang: Einstellungen in der Schreibweise des alten Blocks (kein reines JSON) werden im Browser der Besucher als JavaScript ausgewertet – wie beim HTML-Block von Concrete nur vertrauenswürdige Personen bearbeiten lassen; sonst auf JSON beschränken
- [ ] Fehlermeldungen des Abrufs (für Redakteure) sind bei Verbindungsfehlern englisch, weil sie vom HTTP-Client stammen

### Bewusst zurückgestellt (laut Absprache)
- [ ] PDF-Angebot/-Rechnung
- [ ] Preislisten-Export und Synchronisation zwischen Websites
- [ ] Plugin-Widgets (Pegelstand, Wetter, Bewertungen)
- [ ] Neues Theme als Ersatz für `fewo_themes`

## Testprojekt

Basis ist das Docker-Image `fehrenbacher/concrete5:9.5.7` (PHP 8.4, Apache) mit MariaDB 11.4 und Mailpit.

```bash
cd dev
./setup.sh                      # startet die Container; einmalig: Concrete + Paket + Demo-Inhalte
```

- Website: http://localhost:8095/index.php/fewo-demo, `/fewo-anfrage`, `/fewo-doppelt` (Admin: `admin` / `admin`)
- Wohnungen: http://localhost:8095/index.php/dashboard/fewo_tk2/apartments (drei Demo-Wohnungen)
- E-Mails: http://localhost:8025 (Mailpit)
- PHP-Meldungen: `docker compose logs web` (E_ALL)
- Pausieren: `docker compose stop` (Daten bleiben erhalten), alles löschen: `docker compose down -v`

| Aufgabe | Befehl |
| --- | --- |
| Unit-Tests | `cd packages/fewo_tk2 && docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 install` (einmalig), dann `docker run --rm -v "$PWD":/app -w /app --entrypoint php fehrenbacher/concrete5:9.5.7 vendor/bin/phpunit` |
| Browser-Tests | `cd dev/e2e && npm install && npx playwright install chromium` (einmalig), dann `npx playwright test` |
| Übersetzung aktualisieren | neue Texte in `dev/translations/de_DE.py` eintragen, dann `dev/translate.sh` |
| Bildschirmfotos | `cd dev/e2e && node make-screenshots.js` |
| Paket bauen und prüfen | `dev/build.sh`, danach `dev/test-package.sh` |
| KI-Assistent ohne Sprachmodell testen | läuft automatisch: Das Testprojekt enthält die Ollama-Attrappe `dev/ollama-stub/server.py` (Dienst `ollama-stub`, Modell `stub-model`), die sofort und vorhersagbar antwortet. Für echte Antworten im Dashboard die Adresse eines richtigen Ollama eintragen – oder die Seeherz-Testinstanz verwenden |

Die Demo-Seiten rufen nicht fewo-office.com ab, sondern Dateien im selben Format, die `dev/seed.php` unter
`application/files/fewo-demo-*` ablegt (Daten relativ zum heutigen Tag, inklusive einer abgelaufenen Saison),
dazu erzeugte 360°-Panoramen für den Rundgang.
`seed.php` und `php.ini` sind einzeln in den Container eingebunden: nach Änderungen `docker compose restart web`.
Ändert sich das Datenbankschema bei gleicher Paketversion, das Paket im Testprojekt deinstallieren und neu
installieren (`c5:package:uninstall`, `c5:package:install`, danach `c5:exec /seed/seed.php`).

Hinweis: Die Browser-Tests legen eigene Daten an (Wohnung "E2E Wohnung", Seite `/fewo-e2e`) und räumen sie wieder
ab; die E-Mails der Anfragen von `…@e2e.test` landen in Mailpit. Nicht gegen eine echte Installation laufen lassen.

## Testinstanz Seeherz (echte Website aus dem Backup)

`dev/seeherz/` baut die Website „Ferienapartment Seeherz“ lokal so auf, wie sie auf dem Server läuft: Concrete 9.5.7,
MariaDB 11.2.2 mit dem gesicherten Datenverzeichnis, die alten Pakete `fewo_toolkit` und `fewo_themes` – und daneben
das neue Paket `fewo_tk2`. Quelle ist das Server-Backup unter `legacy/backup_*/` (nicht in Git, enthält Kundendaten
und Zugangsdaten).

```bash
cd dev/seeherz
./restore.sh            # erster Aufruf: Backup entpacken und Instanz aufbauen; danach: starten und aktualisieren
./restore.sh --reset    # Instanz verwerfen und neu aus dem Backup aufbauen
```

- Website: http://localhost:8096 (Admin: `admin` / `admin`), E-Mails: http://localhost:8026 (Mailpit);
  von anderen Rechnern im Netz unter `http://<Rechner>:8096` – die Instanz hat bewusst keine feste Adresse
- Testseite mit allen zehn neuen Blöcken: http://localhost:8096/fewo-toolkit-2 (`page.php`) – Belegung und Preise aus
  Fewo-Office (`…/api/wohnungen/seeherz/…`), Ausstattung, Bilder und Rundgang aus den alten Blöcken übernommen
- Die alten Seiten (`/preise-verfuegbarkeit`, `/rundgang`, `/kontakt` …) laufen unverändert mit den alten Blöcken
- Nach außen abgeschottet (`local.php`): E-Mails von Concrete und vom alten Kontaktformular (eigenes SMTP-Konto im
  Block) gehen nur an Mailpit, Matomo ist abgeschaltet, das Kennwort des Hauptbenutzers ist lokal neu gesetzt
- KI-Assistent mit echtem Sprachmodell: eigener Ollama-Container (nur im internen Netz, `http://ollama:11434`).
  `restore.sh` lädt beim ersten Mal das Modell (`OLLAMA_MODEL`, Vorgabe `gemma3:4b`, 3,3 GB) und schaltet den Assistenten
  ein; Einstellungen unter http://localhost:8096/index.php/dashboard/fewo_tk2/assistant
- `data/` ist der entpackte Stand des Backups und bleibt unverändert; gearbeitet wird in Docker-Volumes
- Im Backup vom 21.11.2026 ist der SQL-Dump leer (0 Byte); die Datenbank stammt deshalb aus dem mitgesicherten
  Datenverzeichnis (`/var/www/database/seeherz`)
