# Fewo Toolkit 2.0

Paket für Concrete CMS mit den Bausteinen einer Ferienwohnungs-Website:
Belegungsplan, Preisübersicht, Preisrechner, Anfrageformular, Ausstattungsliste,
Bildergalerie, Karte, 360°-Rundgang und ein Chat mit KI-Assistent.

**Belegung und Preise kommen immer aus Fewo-Office.** Auf der Website werden weder Preise gepflegt noch
Buchungen angelegt: Die Blöcke rufen die öffentlichen Export-Schnittstellen von Fewo-Office ab und zeigen an,
was dort hinterlegt ist. Im Dashboard der Website stehen die Wohnungen mit ihren Adressen in Fewo-Office und
die eingegangenen Anfragen.

- **Voraussetzungen:** Concrete CMS ab 9.5, PHP ab 8.1. Getestet mit Concrete 9.5.7 und PHP 8.4
  (Docker-Image `fehrenbacher/concrete5:9.5.7`), auch ohne die PHP-Erweiterung `intl`.
- **Sprache:** Die Quelltexte sind englisch, die deutsche Übersetzung (`de_DE`) wird mitgeliefert.
- **Keine fremden Server im Browser der Besucher:** Schriften, Skripte und Kartensymbole kommen aus dem
  Paket. Nur die Karte lädt Kacheln von OpenStreetMap – in der Voreinstellung erst nach Zustimmung des
  Besuchers. Fewo-Office wird vom Webserver abgerufen, nicht vom Browser.
- **KI ohne Cloud:** Der KI-Assistent fragt ein Sprachmodell, das über [Ollama](https://ollama.com) auf dem
  eigenen Server läuft. Fragen und Website-Inhalte gehen an keinen fremden Dienst. Ohne Ollama bleibt der Chat
  einfach ausgeblendet – alle anderen Blöcke brauchen es nicht.

## Installation

1. `fewo_tk2-<Version>.zip` entpacken und den Ordner `fewo_tk2` nach `packages/` der Website kopieren.
2. Verwaltung › Concrete erweitern › **Funktionen hinzufügen** › Fewo Toolkit › **Installieren**
   (oder `concrete/bin/concrete c5:package:install fewo_tk2`).
3. Die Blöcke stehen danach im Block-Set **Fewo Toolkit** bereit, die Verwaltung unter
   Verwaltung › **Fewo Toolkit**.
4. Nur für den Chat: Ollama installieren und unter Verwaltung › Fewo Toolkit › **KI-Assistent** eintragen
   (siehe [KI-Assistent](#ki-assistent-chat-mit-ollama)).

Aktualisieren: Ordner ersetzen, dann an derselben Stelle **Aktualisieren** wählen
(oder `c5:package:update fewo_tk2`).

Deinstallieren entfernt die Blöcke von allen Seiten, die Dashboard-Seiten und die Einstellungen des Pakets.
Die Tabellen mit den Wohnungen (`FewoApartments`), den Anfragen (`FewoInquiries`) und den zwischengespeicherten
Daten aus Fewo-Office (`FewoFeeds`) bleiben in der Datenbank und stehen nach einer erneuten Installation
wieder zur Verfügung. Ebenfalls erhalten bleibt die kleine Zähltabelle des Chats (`FewoAssistantRequests`,
leert sich von selbst).

## Einrichtung

### Wohnungen und ihre Daten aus Fewo-Office

Die Adressen in Fewo-Office werden **einmal je Wohnung** hinterlegt: Verwaltung › Fewo Toolkit › **Wohnungen** ›
*Wohnung hinzufügen*. In den Blöcken (Belegungsplan, Preisübersicht, Preisrechner, Anfrageformular) wird danach
nur noch die Wohnung ausgewählt.

Jede Wohnung hat in Fewo-Office ein Kürzel (z. B. `sonnenseele`). Daraus ergeben sich die beiden Adressen:

| Feld | Adresse | Verwendet von |
| --- | --- | --- |
| Adresse der Belegung | `https://fewo-office.com/api/wohnungen/<kürzel>/belegung.json` | Belegungsplan, Preisrechner (Verfügbarkeit) |
| Adresse der Preisliste | `https://fewo-office.com/api/wohnungen/<kürzel>/preise.json` | Preisübersicht, Preisrechner, Anfrageformular (optional) |

Mindestens eine der beiden Adressen ist nötig. Die Übersicht zeigt je Wohnung den Stand des letzten Abrufs
(OK, noch nicht geladen, letzte Aktualisierung fehlgeschlagen – mit Grund) und die Zahl der aktuellen Saisons.

- **Format:** Empfohlen ist **JSON**. Angenommen werden alle Formate der Schnittstelle – Belegung als `.json`,
  `.xml` oder `.ics`, Preisliste als `.json` oder `.xml`. Das Format wird am Inhalt erkannt, die Endung der
  Adresse spielt keine Rolle. Alle Formate führen zur selben Anzeige.
- **Nur ein Anbieter:** `…/belegung/airbnb.json` zeigt nur die Buchungen eines Anbieters (siehe Fewo-Office).
- **Was übernommen wird:** Fewo-Office liefert nur veröffentlichte Saisons, die noch nicht abgelaufen sind.
  Läuft eine Saison ab, verschwindet sie am Folgetag von der Website – auch wenn die Daten seither nicht neu
  abgerufen wurden. Die Website bewahrt keine alten Preislisten auf.
- **Aktualisierung:** Die Antwort wird in der Datenbank zwischengespeichert und nach Ablauf des bei der Wohnung
  eingestellten Intervalls (15 Minuten bis 24 Stunden, Vorgabe 1 Stunde) beim nächsten Seitenaufruf neu
  geladen – ein Cronjob ist nicht nötig. **Jetzt aktualisieren** in der Übersicht und das Speichern einer
  Wohnung rufen sofort ab; ein Tippfehler in der Adresse fällt so gleich auf.
- **Störungen:** Ist Fewo-Office nicht erreichbar oder liefert es etwas Unbrauchbares (z. B. eine
  Fehlerseite), bleibt der letzte gute Stand sichtbar; angemeldete Redakteure sehen auf der Website einen
  Hinweis mit dem Grund. Bis zum nächsten Versuch vergeht wieder ein Intervall, damit eine Störung die eigene
  Website nicht ausbremst. Gab es noch nie einen erfolgreichen Abruf, sehen Besucher „Preise auf Anfrage“ bzw.
  einen Hinweis statt des Belegungsplans; der Preisrechner wird nicht angezeigt.
- **Wohnung gelöscht:** Blöcke, die sie verwenden, zeigen keine Daten mehr; Redakteure sehen dort einen Hinweis.

### E-Mail-Versand (Anfrageformular)

Das Formular verschickt eine Benachrichtigung an den Vermieter und – wenn im Block eingeschaltet – eine
Eingangsbestätigung an den Gast. Verwendet werden die E-Mail-Einstellungen von Concrete:

- Verwaltung › System & Einstellungen › E-Mail › **E-Mail Versandmethode**: „Externer SMTP Server“ wählen
  und die Zugangsdaten des Postfachs eintragen. Ohne SMTP landen die Nachrichten bei vielen Anbietern im Spam.
- Verwaltung › System & Einstellungen › E-Mail › **System E-Mail-Adressen**: Die Absenderadresse muss zum
  SMTP-Postfach passen. Antworten gehen automatisch an den Gast bzw. an den Vermieter (Reply-To).
  Ist keine Standardadresse hinterlegt, gilt die Absenderadresse für Formulare, sonst die des Hauptbenutzers.

Kann eine Anfrage weder gespeichert noch verschickt werden, sieht der Gast eine Fehlermeldung; der Grund
steht im Concrete-Log (Verwaltung › Berichte › Protokolle).

### Spamschutz und Captcha

Ein verstecktes Köderfeld und eine Mindestausfüllzeit von drei Sekunden sind immer aktiv; Nachrichten mit
Links werden abgelehnt. Zusätzlich lässt sich im Block **Captcha verwenden** einschalten. Welches Captcha
erscheint, legt Concrete fest: Verwaltung › System & Einstellungen › Berechtigungen & Zugriff ›
**CAPTCHA-Einstellungen**.

### Datenschutz

- Im Formular lässt sich die Seite mit der Datenschutzerklärung auswählen; sie wird in der verpflichtenden
  Einwilligung verlinkt.
- Gespeicherte Anfragen werden nach der **Löschfrist** entfernt (Verwaltung › Fewo Toolkit › Buchungsanfragen,
  Vorgabe 365 Tage, 0 = nie). Gelöscht wird ohne Cronjob: beim Öffnen der Anfragen im Dashboard und jedes Mal,
  wenn eine neue Anfrage eingeht. Die E-Mails im Postfach des Vermieters sind davon nicht betroffen.
- Die Karte überträgt erst nach einem Klick Daten an OpenStreetMap. „Karten immer laden“ merkt sich die
  Zustimmung im Browser des Besuchers (`localStorage`), nicht auf dem Server.
- Der Chat schickt Fragen nur an den eigenen Server und von dort an Ollama. Fragen und Antworten werden
  nicht gespeichert; Einzelheiten unter [KI-Assistent](#ki-assistent-chat-mit-ollama).

### KI-Assistent (Chat) mit Ollama

Der Block **Chat (KI-Assistent)** beantwortet Fragen der Besucher („Was kostet eine Nacht im Juli?“, „Gibt es
WLAN?“, „Wann ist eine Woche frei?“) auf Grundlage der aktuellen Inhalte der Website. Die Antworten schreibt ein
Sprachmodell, das mit [Ollama](https://ollama.com) auf dem eigenen Server läuft.

**Was der Assistent weiß**

- Die **öffentlichen Seiten** der Website – dieselben, die Concrete in die `sitemap.xml` aufnimmt
  (veröffentlicht, für Gäste sichtbar) – mit den Texten, die ihre Blöcke für die Suche bereitstellen. Seiten mit
  dem Attribut „Vom Suchindex ausschließen“ und die im Dashboard ausgeschlossenen Pfade gehören nicht dazu.
- **Preise und freie Zeiträume** der Wohnungen aus Fewo-Office (Verwaltung › Fewo Toolkit › Wohnungen): Preise
  pro Nacht nach Monaten für die nächsten 15 Monate, Endreinigung, Mindestaufenthalt, Anzahlung und die freien
  Zeiträume der nächsten 12 Monate.
- Änderungen gelten sofort: Sobald eine Seite veröffentlicht, verschoben oder gelöscht wird, liest der
  Assistent die Inhalte neu ein (spätestens nach zehn Minuten); Preise und Belegung folgen dem
  Aktualisierungsintervall der Wohnung.
- Nicht erfasst werden Blöcke, die Concrete keinen Suchtext liefern – etwa die Blöcke des alten Fewo Toolkit.
  Unter Verwaltung › Fewo Toolkit › KI-Assistent › **Was der Assistent weiß** steht, welche Seiten mit wie viel
  Text dabei sind.

**1. Ollama installieren**

Ollama braucht keinen Cloud-Zugang und keinen Schlüssel. Zwei übliche Wege:

- *Als Docker-Container neben der Website* (so läuft es im Testprojekt, `dev/seeherz/docker-compose.yml`):

  ```yaml
  ollama:
    image: ollama/ollama
    restart: always
    volumes:
      - /var/www/ollama:/root/.ollama   # hier liegen die Modelle (mehrere GB)
    # keine "ports": Ollama ist dann nur für die anderen Container erreichbar
  ```

  ```bash
  docker compose up -d ollama
  docker compose exec ollama ollama pull gemma3:4b
  ```

  Adresse für das Dashboard: `http://ollama:11434` (Name des Dienstes; Website und Ollama müssen im selben
  Docker-Netz liegen). Ein Ollama-Container kann mehrere Websites bedienen.

- *Direkt auf dem Server:* `curl -fsSL https://ollama.com/install.sh | sh`, danach `ollama pull gemma3:4b`.
  Adresse: `http://localhost:11434`. Läuft die Website in einem Docker-Container, muss Ollama auch außerhalb
  von `localhost` lauschen (`OLLAMA_HOST=0.0.0.0` in der Dienstkonfiguration) und ist dann z. B. unter
  `http://host.docker.internal:11434` erreichbar.

Ollama hat **keine Anmeldung**: Port 11434 darf nicht aus dem Internet erreichbar sein (kein veröffentlichter
Port, Firewall). Liegt Ollama auf einem anderen Rechner hinter einem Reverse-Proxy mit Bearer-Token, lässt sich
das Token im Dashboard als API-Schlüssel hinterlegen.

**2. Modell wählen**

Jedes in Ollama installierte Modell lässt sich verwenden; im Dashboard steht die Liste. Entscheidend sind
Arbeitsspeicher und – ohne Grafikkarte – die Geduld der Besucher. Gemessen in der Testinstanz
(`dev/seeherz`: 16 CPU-Kerne, keine Grafikkarte, Website mit rund 35.000 Zeichen = 10.000 Token):

| Modell | Speicher, solange geladen | Erstes Einlesen der Website | Antwort danach | Eindruck |
| --- | --- | --- | --- | --- |
| `gemma3:4b` (3,3 GB Download) | ca. 3 GB | ca. 50 Sekunden | beginnt nach 1–3 Sekunden, fertig nach 2–5 | Empfehlung ohne Grafikkarte. Fragen zum Inhalt (Ausstattung, Lage, Umgebung) meist richtig; antwortet in der Sprache der Frage. Bei Preisen und Zeiträumen gelegentlich falsch. |
| `gemma3:12b` (8,1 GB Download) | ca. 8,5 GB | ca. 3,5 Minuten | fertig nach 5–20 Sekunden | Bei Zeiträumen etwas genauer, aber nicht fehlerfrei – ohne Grafikkarte den Aufpreis an Wartezeit nicht wert. |

Mit einer Grafikkarte sind größere Modelle sinnvoll und alle Zeiten um ein Vielfaches kürzer. Wichtig bei der
Wahl: Das Modell sollte Deutsch gut beherrschen und mindestens 16.000 Token Kontext verarbeiten können.
Die **Testfrage im Dashboard** ist der schnellste Weg, ein Modell mit den eigenen Inhalten zu beurteilen –
am besten mit Fragen nach Preisen und freien Terminen, dort unterscheiden sich die Modelle am meisten.

**3. Im Dashboard einrichten** (Verwaltung › Fewo Toolkit › **KI-Assistent**)

1. **Adresse von Ollama** eintragen und speichern – die Seite zeigt, ob die Verbindung steht und welche Modelle
   installiert sind.
2. **Modell** wählen, unter **Ausprobieren** ein paar typische Fragen stellen (geht, bevor der Assistent
   eingeschaltet ist; zeigt bei Problemen die Fehlermeldung von Ollama).
3. **Assistent einschalten** und den Block **Chat (KI-Assistent)** auf einer Seite einfügen.

Weitere Einstellungen:

| Einstellung | Bedeutung |
| --- | --- |
| Website-Inhalte je Frage | So viel Text bekommt das Modell höchstens (Vorgabe 48.000 Zeichen). Passt die ganze Website hinein, bekommt es immer alles – das ist der genaue und schnelle Fall. Sonst werden je Frage nur die passendsten Abschnitte ausgewählt; das Dashboard warnt dann. |
| Preise und freie Zeiträume einbeziehen | Angaben aus Fewo-Office mitgeben (Vorgabe: ja). |
| Seiten, die der Assistent nicht kennen soll | Pfade, je Zeile einer, z. B. `/impressum`; gilt auch für Unterseiten. |
| Zusätzliche Anweisungen | Tonfall („Sprich die Gäste mit du an“) oder Angaben, die nicht auf der Website stehen („Hunde auf Anfrage“). |
| Längste Wartezeit | Nach so vielen Sekunden ohne Antwort bricht die Anfrage ab (Vorgabe 120). |
| Fragen je Besucher / insgesamt pro Stunde | Schutz vor Überlastung (Vorgabe 20 / 200). Wer die Grenze erreicht, wird um Geduld gebeten. |

**Geschwindigkeit**

Passen alle Inhalte ins Budget, schickt das Paket sie bei jeder Frage in derselben Reihenfolge. Ollama
verarbeitet diesen gleichbleibenden Teil nur einmal und merkt ihn sich, solange das Modell geladen ist
(30 Minuten nach der letzten Frage). Danach beginnt jede Antwort nach ein bis drei Sekunden. Das erste Einlesen
dauert ohne Grafikkarte je nach Umfang der Website und Modell eine halbe bis mehrere Minuten – deshalb stößt
der Chat es schon beim **Anzeigen der Seite** an, nicht erst bei der ersten Frage. Wer in dieser Zeit fragt,
wartet, bis das Einlesen fertig ist. Nach einer Änderung an den Inhalten wird einmal neu eingelesen.

Arbeitsspeicher: Solange das Modell geladen ist, belegt Ollama den Speicher des Modells (siehe Tabelle). Auf
einem Server ohne freien Speicher in dieser Größe ist der Assistent nicht sinnvoll.

**Grenzen – bitte ernst nehmen**

- Sprachmodelle machen Fehler, kleine häufiger. Die Regeln des Pakets (nur aus den Inhalten antworten, nichts
  verneinen, was nicht dasteht, bei fehlenden Angaben an die Gastgeber verweisen) senken das Risiko, schließen
  es aber nicht aus. Der Hinweis unter dem Eingabefeld („Die Antworten erstellt eine KI …“) sollte stehen
  bleiben.
- Am fehleranfälligsten sind **Preise und Zeiträume**: Im Test nannte `gemma3:4b` für „eine Nacht im Dezember“
  nur den Weihnachtspreis statt beider Saisons, `gemma3:12b` für drei Gäste den Preis für zwei. Verbindlich
  bleiben Preisrechner, Belegungsplan und das Angebot der Gastgeber.
- Preise nennt der Assistent pro Nacht; für den Gesamtpreis verweist er auf den Preisrechner. Freie Zeiträume
  sind unverbindlich wie im Belegungsplan.
- Der Assistent kann nichts buchen, nichts ändern und keine Nachrichten weitergeben.
- Jeder Besucher führt sein eigenes Gespräch; es endet mit dem Verlassen der Seite.

**Datenschutz**

- Die Frage geht vom Browser an die eigene Website und von dort an Ollama – an keinen fremden Dienst.
- Fragen und Antworten werden **nicht gespeichert** (auch nicht im Log). Für die Begrenzung wird je Frage ein
  Zeitpunkt mit einem gesalzenen Hash der IP-Adresse abgelegt und nach 24 Stunden gelöscht
  (`FewoAssistantRequests`).
- In die Datenschutzerklärung gehört ein Hinweis, dass Eingaben im Chat automatisiert von einem Sprachmodell
  auf dem eigenen Server verarbeitet werden.

**Wenn es nicht geht**

| Anzeige | Ursache und Abhilfe |
| --- | --- |
| Dashboard: „Keine Verbindung zu Ollama“ | Adresse falsch oder Ollama von der Website aus nicht erreichbar (anderes Docker-Netz, Ollama lauscht nur auf `localhost`). Die Meldung nennt den Grund. |
| Dashboard: Modell „…“ ist dort nicht installiert | `ollama pull <modell>` ausführen bzw. den Namen genau wie bei `ollama list` eintragen. |
| Chat: „Der Assistent kann gerade nicht antworten“ | Ollama hat einen Fehler gemeldet oder nicht rechtzeitig geantwortet; der Grund steht im Concrete-Log (Verwaltung › Berichte › Protokolle). Häufig: zu wenig Arbeitsspeicher, Wartezeit zu kurz für das erste Einlesen. |
| Chat: „Der Assistent ist gerade stark gefragt“ | Grenze je Besucher oder je Stunde erreicht. |
| Antworten kommen erst vollständig statt Wort für Wort | Ein Proxy sammelt die Antwort (Pufferung, Komprimierung). Der Chat funktioniert trotzdem; für nginx sendet das Paket bereits `X-Accel-Buffering: no`. |
| Besucher sehen den Block nicht | Assistent ausgeschaltet oder kein Modell gewählt – Redakteure sehen an der Stelle einen Hinweis. |

## Die Blöcke

| Block | Zweck | Hinweise |
| --- | --- | --- |
| **Belegungsplan** | Freie und belegte Tage der gewählten Wohnung | An- und Abreisetage werden halb belegt dargestellt. Pro Zeile stehen so viele Kalenderblätter, wie anfangs Monate sichtbar sind – auch bei den aufgeklappten Monaten; auf schmalen Bildschirmen entsprechend weniger. |
| **Preisübersicht** | Saisons und Preise der gewählten Wohnung | Anordnung wählbar: **Kacheln nebeneinander** oder **Liste untereinander**. Gleiche Preise werden zu Bereichen zusammengefasst („1–2 Gäste“). Der Standardpreis der Wohnung erscheint als „Übrige Zeiträume“. Bedingungen: Mindestaufenthalt, Endreinigung, Anzahlung. |
| **Preisrechner** | Gäste berechnen den Preis ihres Aufenthalts | Rechnet wie Fewo-Office und prüft die Belegung der Wohnung (siehe unten). Funktioniert auch ohne JavaScript. Optional führt eine Schaltfläche mit Reisedaten zum Anfrageformular. |
| **Anfrageformular** | Unverbindliche Anfrage mit Reisezeitraum, Gästen und Kontaktdaten | Die Anfrage wird per E-Mail verschickt und im Dashboard gespeichert – eine Buchung entsteht dadurch nicht. Mit einer Wohnung steht der unverbindliche Preis in der Benachrichtigung und im Dashboard. |
| **Ausstattungsliste** | Liste mit optionalen Fußnoten, auf Wunsch aufklappbar | Spaltenzahl wählbar (1 bis 4); auf schmalen Bildschirmen werden es automatisch weniger. |
| **Bildergalerie** | Bilder aus einem Ordner der Dateiverwaltung oder einem Dateiset mit Großansicht | Bildunterschrift = Beschreibung oder Titel der Datei. Bedienung per Tastatur und Wischen. |
| **Karte** | OpenStreetMap-Karte mit Markierungen | Koordinaten lassen sich als Paar einfügen („47.7102, 9.1024“). |
| **360°-Rundgang** | 360°-Panoramen und Rundgänge durch mehrere Räume (Pannellum) | Nachfolger des Blocks „Pannellum 360° Viewer“ der alten Website; Bilder und Einstellungen lassen sich unverändert übernehmen (siehe unten). |
| **Chat (KI-Assistent)** | Besucher stellen Fragen und bekommen Antworten aus den Inhalten der Website | Braucht Ollama (siehe [KI-Assistent](#ki-assistent-chat-mit-ollama)). Überschrift, Begrüßung, Beispielfragen zum Anklicken und der Hinweis unter dem Eingabefeld sind frei wählbar. Solange der Assistent nicht eingerichtet ist, sehen Besucher den Block nicht. |

Mehrere Blöcke desselben Typs auf einer Seite sind möglich.

### So rechnet der Preisrechner

Der Rechner folgt den Regeln von Fewo-Office, damit die Website denselben Preis nennt wie ein Angebot von dort:

- Über den Preis einer Nacht entscheidet der Tag, an dem sie **endet** (die Nacht vom 23. auf den 24. kostet
  den Preis des 24.).
- Deckt keine Saison diesen Tag ab, gilt der Standardpreis der Wohnung. Gibt es auch den nicht, bittet der
  Rechner um eine Anfrage.
- Ist für die Gästeanzahl kein Preis hinterlegt, gilt der Preis der nächsthöheren hinterlegten Anzahl, sonst
  der der höchsten.
- Mindestaufenthalt, maximale Gästeanzahl, Endreinigung und Anzahlung (in Prozent, auf volle Euro
  abgerundet) stammen aus der Preisliste.

### Preisrechner und Belegung

Ist bei der Wohnung die Adresse der Belegung hinterlegt, gibt es für belegte Zeiträume keinen Preis:

- Die Felder **Anreise** und **Abreise** öffnen einen eigenen Kalender mit der Belegung der Wohnung – an Stelle
  des Kalenders des Browsers, in dem sich einzelne Tage nicht sperren lassen. Einen zusätzlichen, dauerhaft
  sichtbaren Kalender gibt es im Preisrechner nicht.
- Belegte Tage sind gesperrt. Nach der Wahl der Anreise wechselt der Kalender zum Feld „Abreise“; sie lässt
  sich nur bis zum Beginn der nächsten Buchung wählen. An- und Abreise an einem Wechseltag sind möglich.
- Bedienung mit Maus, Finger und Tastatur: Eingabetaste oder Pfeil nach unten im Feld wechselt in den
  Kalender, Pfeiltasten bewegen den Tag, Eingabetaste wählt, Escape schließt.
- Sollte dennoch ein belegter Zeitraum abgeschickt werden, erscheint die Meldung „Die Wohnung ist im gewählten
  Zeitraum bereits belegt“; berechnet wird nicht.
- Ohne JavaScript bleiben die Datumsfelder des Browsers; der Server prüft beim Absenden und zeigt dieselbe
  Meldung.

Ohne Adresse der Belegung (oder solange sie noch nie geladen werden konnte) bleiben die Datumsfelder des
Browsers, und der Preisrechner rechnet ohne diese Prüfung. Das Anfrageformular prüft die Belegung nicht – eine Anfrage ist immer möglich.

### 360°-Rundgang

Der Block zeigt ein 360°-Panorama oder einen Rundgang durch mehrere Räume. Die Pannellum-Bibliothek (2.5.6)
wird mit dem Paket ausgeliefert; es werden keine fremden Server kontaktiert, solange die Bilder auf der
eigenen Website liegen.

- **Bilder:** ein Bild pro Zeile in der Form `name->Adresse`, z. B.
  `wohnzimmer->/application/files/1234/wohnzimmer.jpg`. In den Einstellungen wird das Bild als `{wohnzimmer}`
  verwendet.
- **Bilder für Mobilgeräte (optional):** kleinere Fassungen unter denselben Namen. Sie werden auf Telefonen
  und Tablets verwendet (entschieden wird im Browser, nicht mehr auf dem Server).
- **Pannellum-Einstellungen:** die Konfiguration des Viewers ohne die umschließenden geschweiften Klammern
  ([Referenz](https://pannellum.org/documentation/reference/)). Angenommen wird JSON und die
  JavaScript-Schreibweise des alten Blocks (Schlüssel ohne Anführungszeichen, Kommentare,
  `createTooltipFunc: hotspotFontAwesome`). In JSON wird die Hotspot-Funktion als Name angegeben:
  `"createTooltipFunc": "hotspotFontAwesome"`.
- **Seitenverhältnis:** 16:9, 3:2, 4:3, 2:1, 1:1 oder „Manuell“ – dann bestimmt das Theme die Höhe über die
  CSS-Klasse des Blocks (`--fewo-tour-height`).

Einzelnes Panorama (Vorgabe beim Hinzufügen):

```
"type": "equirectangular",
"panorama": "{panorama}",
"autoLoad": true
```

Rundgang durch zwei Räume:

```
default: { firstScene: "wohnzimmer", sceneFadeDuration: 800, autoLoad: true },
scenes: {
    wohnzimmer: {
        title: "Wohnzimmer", type: "equirectangular", panorama: "{wohnzimmer}",
        hotSpots: [{ pitch: -5, yaw: 30, type: "scene", sceneId: "schlafzimmer", text: "Zum Schlafzimmer",
                     cssClass: "custom-hotspot", createTooltipFunc: hotspotFontAwesome,
                     createTooltipArgs: { "fa-icon": "fa-bed" } }]
    },
    schlafzimmer: {
        title: "Schlafzimmer", type: "equirectangular", panorama: "{schlafzimmer}",
        hotSpots: [{ pitch: -5, yaw: -30, type: "scene", sceneId: "wohnzimmer", text: "Zurück ins Wohnzimmer" }]
    }
}
```

Im Bearbeitungsmodus zeigt der Block einen Platzhalter. Fehlerhafte Einstellungen führen auf der Website zur
Meldung „Die 360°-Ansicht kann nicht angezeigt werden“; angemeldete Redakteure sehen zusätzlich den Grund.
Hotspots mit `hotspotFontAwesome` brauchen Font Awesome im Theme (in den Themes von Concrete enthalten).

Einstellungen, die kein reines JSON sind, werden im Browser der Besucher als JavaScript ausgewertet – wie beim
HTML-Block von Concrete sollten deshalb nur vertrauenswürdige Personen diesen Block bearbeiten dürfen.

### „Auf Anfrage“ im Belegungsplan

Im Block lassen sich Stichwörter hinterlegen. Einträge, deren Infotext (JSON/XML) bzw. Titel (ICS) eines der
Wörter enthält, erscheinen als „auf Anfrage“ statt als „belegt“. Fewo-Office liefert derzeit nur den Infotext
„Betriebsferien“; ohne Stichwörter (Vorgabe) gilt jeder Eintrag als belegt.

## Umstieg vom alten Fewo Toolkit (Concrete 5.7/8)

Das neue Paket ist eine Neuentwicklung und übernimmt **keine Blöcke oder Tabellen** des alten Pakets; die
Inhalte werden mit den neuen Blöcken neu angelegt. Preislisten und Belegung müssen nicht übertragen werden: Sie
liegen in Fewo-Office, im Dashboard wird je Wohnung nur die Adresse hinterlegt.

Beide Pakete können auf derselben Website nebeneinander installiert sein. Das neue Paket hat dafür eigene Namen:
Ordner und Handle `fewo_tk2` (alt: `fewo_toolkit`), die Blöcke heißen `fewo_belegungsplan`, `fewo_preisrechner`
usw. (alt: `preisrechner` …), die Verwaltung liegt unter `/dashboard/fewo_tk2`. So lassen sich die Seiten
nacheinander umstellen; das alte Paket wird erst deinstalliert, wenn keiner seiner Blöcke mehr verwendet wird.

Rundgänge aus dem alten Block „Pannellum 360° Viewer“: einen Block **360°-Rundgang** hinzufügen und die drei
Felder (Bilder, Bilder für Mobilgeräte, Pannellum-Einstellungen) aus dem alten Block hineinkopieren. Das
Seitenverhältnis wird neu gewählt; die frühere feste Größe (`div.panorama`, 728 × 486 px) entspricht 3:2.

## Gestaltung

Die Blöcke bringen eine zurückhaltende Grundgestaltung mit und übernehmen Schrift und Schaltflächen des
Themes. Farben und Rundungen lassen sich im Theme über CSS-Variablen anpassen, zum Beispiel:

```css
.fewo-calendar {
    --fewo-free: #dff3e4;      /* freie Tage */
    --fewo-booked: #e5484d;    /* belegte Tage */
    --fewo-request: #f5b83d;   /* auf Anfrage */
    --fewo-radius: 6px;
}
.fewo-prices { --fewo-card-bg: #f6f3ee; }
.fewo-form   { --fewo-focus: #0b6e4f; --fewo-error: #b42318; }
```

Weitere Variablen: `--fewo-free-text`, `--fewo-booked-text`, `--fewo-request-text`, `--fewo-past`,
`--fewo-past-text`, `--fewo-border`, `--fewo-month-min` und `--fewo-month-max` (kleinste und größte Breite
eines Kalenderblatts im Belegungsplan), `--fewo-card-border`
(Preisübersicht), `--fewo-muted` (Preisrechner), `--fewo-success-bg`, `--fewo-success-text`
(Anfrageformular), `--fewo-gap` (Bildergalerie), `--fewo-selected` (gewählter Zeitraum im Kalender der
Datumsfelder des Preisrechners), `--fewo-tour-ratio` und `--fewo-tour-height` (360°-Rundgang),
`--fewo-chat-bg`, `--fewo-chat-user-bg`, `--fewo-chat-assistant-bg` und `--fewo-chat-height` (Chat: Hintergrund
des Gesprächs, der eigenen Fragen und der Antworten, größte Höhe des Gesprächs). Wer Farben ändert, sollte die
Kontraste prüfen.

## Entwicklung und Tests

Im Repository liegt unter `dev/` ein Testprojekt (Docker) mit Demo-Inhalten; siehe `TODO.md` im
Wurzelverzeichnis.

- Unit-Tests: `composer install && vendor/bin/phpunit` (Preisberechnung, Belegung und Preisliste aus Fewo-Office in
  allen Formaten, Formularprüfung, KI-Assistent: Aufbereitung der Inhalte, Nachrichten an das Modell, Gespräch mit Ollama)
- Browser-Tests: `dev/e2e` (Playwright) – Dashboard, Block-Dialoge, Besucheransicht, Barrierefreiheit.
  Das Testprojekt liefert dafür selbst Dateien im Format der Fewo-Office-Schnittstelle aus (`dev/seed.php`);
  statt eines Sprachmodells antwortet eine Ollama-Attrappe (`dev/ollama-stub`) – sofort und vorhersagbar.
- Echte Antworten eines Sprachmodells: Testinstanz `dev/seeherz` mit eigenem Ollama-Container.
- Übersetzung aktualisieren: `dev/translate.sh`, Paket bauen: `dev/build.sh`, Paket prüfen: `dev/test-package.sh`
