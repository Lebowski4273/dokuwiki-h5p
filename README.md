# DokuWiki-Plugin H5P


Dieses Plugin kann h5p-Inhalte auf dokuwiki-Seiten anzeigen. Die h5p-Dateien müssen aber
extern erstellt werden (Lumi, moodle, h5p.org, ...). Sie werden dann mit dem Medienmanager
hochgeladen und können auf Wiki-Seiten eingebunden werden.  Eine kleine Erleichterung gibt
es: Unter den h5p-Inhalten kann man einen Button Ersetzen`` anzeigen, dies erspart den Weg
über den Medienmanager, wenn man eine vorhandene Datei mit dem externen Werkzeug verändert hat.
Die H5P-Bibliotheken (Inhaltstypen) werden **nicht** in jeder H5P-Datei gespeichert,
sondern einmal zentral auf dem Server.

Die Adminseite bietet die Möglichkeit h5p-Bibliothken zu installieren. Außerdem können 
verwaiste Inhalte (entpackte h5p-Dateien, deren Ursprungsdatei aber gelöscht wurde) entfernt
werden. In der Konfiguration kann man angeben, welche Nutzer oder Nutzergruppen gestattet ist
Bibliotheken zu installieren. Standarmäßig können die admin-Nutzer dies immer.

Zum Abspielen wird der Player [h5p-standalone](https://github.com/tunapanda/h5p-standalone)
(MIT-Lizenz) verwendet. Er läuft komplett im Browser. Das Plugin muss deshalb nur Dateien
entpacken und ausliefern.
Ich verwende die schon mit npm gepackten Dateien von https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist
diese liegen in vendor/h5p-standalone


## Installation

1. Den Ordner `h5p` nach `lib/plugins/h5p/` kopieren oder die Installation über die Admin-Seite anstoßen
2. Unter `lib/plugins/h5p/vendor/h5p-standalone/` liegen doe schon mit npm gepackten Datein von
   https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist
   
   Sollte man eine andere Vesion besorgen, die .zip von [h5p-standalone](https://github.com/tunapanda/h5p-standalone)
   herunterladen und  und den Inhalt des `dist`-Ordners nach
   `lib/plugins/h5p/vendor/h5p-standalone/` kopieren. Zum Beispiel:
   ```
   npm pack h5p-standalone
   tar xzf h5p-standalone-*.tgz
   cp -r package/dist/* lib/plugins/h5p/vendor/h5p-standalone/
   ```
   Danach müssen dort mindestens `main.bundle.js`, `frame.bundle.js` und `styles/h5p.css`
   liegen. Die Admin-Seite zeigt an, ob der Player gefunden wurde.
3. **Dateityp `.h5p` erlauben:** in `conf/mime.local.conf` diese Zeile ergänzen:
   ```
   h5p   application/zip
   ```
4. **Webserver:** Die Dateien werden über Pfade wie
   `lib/plugins/h5p/file.php/c/…/h5p.json` ausgeliefert.
   - Apache: funktioniert in der Regel ohne Änderungen.
   - nginx: Die PHP-Location muss auch Pfade *nach* `.php` weiterreichen, z. B.
     ```
     location ~ \.php(/|$) {
         fastcgi_split_path_info ^(.+?\.php)(/.*)$;
         fastcgi_param PATH_INFO $fastcgi_path_info;
         # … übrige fastcgi-Einstellungen
     }
     ```
5. Das Datenverzeichnis muss für den Webserver beschreibbar sein. Standardmäßig wird
   `data/h5p/` angelegt.
6. Unter Administration → H5P-Bibliotheken die fehlenden Bibliotheken vom Hub installieren.
   Für den Hub braucht der Server ausgehende HTTPS-Verbindungen zu api.h5p.org.

## Benutzung

```
{{h5p>unterricht:informatik:variablen.h5p}}
{{h5p>variablen.h5p?width=800&frame=1&copyright=1}}
```

Relative Angaben werden wie bei normalen Medien zum Namensraum der Seite aufgelöst.

### Inhalte aktualisieren („H5P-Datei ersetzen“)

Bearbeitet wird außerhalb des Wikis, z. B. in Lumi. Unter jedem H5P-Inhalt erscheint für
Benutzer, die die Datei überschreiben dürfen (ACL-Recht *Löschen* auf die Mediendatei), der
Knopf **H5P-Datei ersetzen**. Ablauf:

1. Mit „Reuse“ herunterladen (oder die eigene Lumi-Datei nehmen) und in Lumi bearbeiten.
2. Unter dem Inhalt auf „H5P-Datei ersetzen“ klicken und die neue Datei wählen.
3. Die Seite lädt neu und zeigt die neue Version.

Das Ersetzen läuft über die normale Upload-Funktion von DokuWiki: Die alte Version bleibt
in der Versionsgeschichte des Medienmanagers, Bibliotheken werden wie beim Upload behandelt.
Der Knopf erscheint auch unter einer Fehlermeldung, sodass sich kaputte Dateien direkt
austauschen lassen. Abschaltbar mit `replace`.

| Parameter    | Bedeutung                                      |
|--------------|------------------------------------------------|
| `width`      | maximale Breite, z. B. `800`, `800px`, `80%`   |
| `frame`      | Aktionsleiste anzeigen (`1`/`0`)               |
| `copyright`  | Copyright-Schaltfläche (`1`/`0`, braucht frame) |
| `fullscreen` | Vollbild-Schaltfläche (`1`/`0`)                |
| `icon`       | H5P-Logo (`1`/`0`, braucht frame)              |
| `export`     | „Reuse“-Download (`1`/`0`, braucht frame)      |

Die Höhe passt der Player automatisch an den Inhalt an.

## Wie die Bibliotheken verwaltet werden

Viele H5P-Dateien enthalten **keine Bibliotheken** mehr. Downloads von h5p.org zum
Beispiel enthalten seit Anfang 2025 nur noch `h5p.json` und `content/`. Das Plugin ist
darauf ausgelegt. Inhalte und Bibliotheken liegen getrennt.

```
data/h5p/
├── libraries/                  zentrale Bibliotheken
│   ├── H5P.ArithmeticQuiz-1.1/
│   ├── H5P.JoubelUI-1.3/
│   └── …
├── libraries.updated           Zeitstempel, leert bei Änderungen den Seiten-Cache
├── hub.json                    Hub-Registrierung und Liste der Inhaltstypen
└── content/<md5>/              entpackter Inhalt je H5P-Datei (h5p.json + content/)
```

**Upload in den Medienmanager:** Jede gültige H5P-Datei wird angenommen. Fehlen
Bibliotheken, gibt es einen Hinweis, und auf der Seite erscheint statt des Players eine
Meldung mit den fehlenden Bibliotheken. Lädt ein Manager (oder ein Mitglied von
`libmanagers`) eine Datei *mit* Bibliotheken hoch, werden diese zentral installiert und
aus der Datei entfernt (abschaltbar mit `strip_on_upload`). Bibliothekscode von anderen
Benutzern wird nie übernommen.

**Admin-Seite** (Administration → H5P-Bibliotheken), nur für Manager und Admins:

1. *Fehlende Bibliotheken:* durchsucht alle H5P-Dateien im Medienmanager und zeigt, was
   fehlt und welche Datei es braucht. Mit einem Knopf lassen sie sich einzeln oder alle
   zusammen vom Hub installieren.
2. *Inhaltstyp vom H5P-Hub installieren:* lädt die neueste Version eines Inhaltstyps mit
   allen Abhängigkeiten von api.h5p.org. Beim ersten Mal registriert sich das Wiki beim
   Hub (wie WordPress oder Moodle) und speichert die erhaltene UUID in `hub.json`. Der
   Server baut die Verbindung nur auf Knopfdruck auf. Abschaltbar mit `hub`.
3. *Bibliotheken aus einer Datei installieren:* H5P-Dateien mit Bibliotheken oder
   ZIP-Dateien mit Bibliotheksordnern (jeder Ordner mit `library.json`, auch verschachtelt),
   z. B. der Bibliotheksordner aus Lumi oder Moodle. Das funktioniert ohne Hub.
4. *Installierte Bibliotheken:* Übersicht mit Abhängigkeiten, Löschen.

Eine Bibliothek wird nur durch eine höhere Patch-Version ersetzt. Verschiedene
Minor-Versionen (z. B. `H5P.Question-1.4` und `-1.5`) liegen nebeneinander.

### Versionsproblem und Minor-Fallback

Der Hub liefert immer nur die **neueste** Version. Verlangt ein Inhalt eine ältere
Minor-Version (z. B. 1.1, im Hub gibt es 1.2), bleibt er als „fehlend“ markiert. Die
Admin-Seite weist darauf hin. Lösungen:

- den Inhalt in Lumi öffnen, aktualisieren und neu exportieren, oder
- die Einstellung `minor_fallback` einschalten: Dann wird die höchste installierte
  Minor-Version derselben Major-Version verwendet. Das funktioniert meistens, H5P
  garantiert es aber nicht.

## Sicherheit

- Entpackt werden nur erlaubte Dateitypen (angelehnt an die H5P-Whitelist), in Inhalten
  ohne `.js`/`.css`. Pfade mit `..` oder absolute Pfade führen zur Ablehnung der Datei.
- Inhalte werden nur ausgeliefert, wenn der Benutzer Leserechte auf die H5P-Datei hat.
  Bibliotheken sind für alle abrufbar, sie enthalten nur Programmcode.
- H5P-Bibliotheken sind JavaScript, das im Kontext des Wikis läuft (wie bei WordPress oder
  Moodle auch). Deshalb dürfen nur vertrauenswürdige Personen Bibliotheken installieren.

## Einstellungen

Im Konfigurationsmanager unter „h5p“: `libmanagers`, `strip_on_upload`, `frame`,
`copyright`, `fullscreen`, `icon`, `export`, `replace`, `maxsize` (MB, entpackt), `storagedir`, `hub`,
`minor_fallback`.

## Bekannte Grenzen

- „Reuse“ lädt die Datei aus dem Medienmanager herunter (mit Prüfung der Leserechte).
  Sie enthält keine Bibliotheken; Moodle, WordPress und Lumi holen diese beim Import
  selbst vom H5P-Hub. „Embed“ wird nicht unterstützt.

- Nur Anzeige: Ergebnisse (xAPI) werden nicht gespeichert, es gibt keinen Editor.
- Wird eine H5P-Datei im Medienmanager gelöscht, entfernt das Plugin auch ihren entpackten
  Inhalt. Verschwindet eine Datei auf anderem Weg (FTP, Move-Plugin), hilft der Knopf
  „Verwaiste Inhalte löschen“ auf der Admin-Seite. Entpackte Inhalte werden bei Bedarf
  ohnehin neu erzeugt.
- Beim Entfernen der Bibliotheken wird die Datei nach dem Upload verändert. Das
  Medien-Änderungsprotokoll zeigt deshalb ggf. die ursprüngliche Dateigröße.

## Test der Kernlogik

```
php _test/selftest.php
```
