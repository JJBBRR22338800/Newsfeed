# NewsCenter für 1blu Shared Hosting

PHP 8.4, ohne Datenbank und ohne Composer. Enthält Nachrichten-Dashboard, Lauf-Ticker mit Tempo-/Schriftgrößenwahl, einklappbare Wetter-/Warnungs-/Suchbereiche, Kategorien, Suche, Dark Mode, Open-Meteo-Wetter und eine zusätzliche DWD-Warnungsabfrage.

## Installation

1. ZIP entpacken und den **Inhalt** des Ordners `newscenter` per SFTP/IONOS-WebFTP in einen Webordner hochladen, z. B. `/news/`. `index.php`, `api.php`, `config.php`, `cron.php`, `.htaccess`, `assets/` und `data/` müssen zusammenliegen.
2. In bei 1blu PHP 8.4 aktivieren. Die Erweiterungen **SimpleXML** und **mbstring** sollten verfügbar sein; cURL wird empfohlen. HTTPS muss funktionieren.
3. PHP muss Schreibrechte auf den Ordner `data/` haben. Üblicherweise reichen Ordnerrechte 750/755 und Dateien 640/644. Keine pauschalen 777-Rechte setzen.
4. In IONOS einen Cronjob einrichten, der `cron.php` alle fünf Minuten per PHP-CLI ausführt. Beispiel – PHP-Pfad und absoluten Verzeichnispfad im IONOS-Kundenmenü prüfen und ersetzen:

   `*/5 * * * * /usr/bin/php8.4 /PFAD/ZU/DEINEM/WEBSPACE/news/cron.php >> /PFAD/ZU/DEINEM/WEBSPACE/news/cron.log 2>&1`

   1blu-Cronjobs und verfügbare PHP-CLI-Pfade unterscheiden sich je nach Paket. Diesen Beispielpfad nicht ungeprüft übernehmen. `cron.php` ist absichtlich für HTTP gesperrt und muss über die CLI laufen.
5. Den Cronjob einmal manuell starten. Er sollte `data/cache.json` erstellen. Danach `/news/` im Browser öffnen. `api.php` liefert den Cache aus, während `data/.htaccess` den direkten Zugriff auf den Cache verhindert.

## Eigene RSS-/Atom-Quellen verwalten

1. In `config.php` die Werte `admin_user` und `admin_password` ändern. Das Standardpasswort ist absichtlich gesperrt; `sources.php` bleibt damit zunächst nicht zugänglich.
2. HTTPS aktivieren und danach `https://deine-domain/news/sources.php` öffnen. Der Browser fragt nach den Zugangsdaten (HTTP Basic Auth).
3. Namen, Feed-URL und Kategorie eintragen. Quellen lassen sich bearbeiten, pausieren oder löschen. Eigene Kategorien werden im Dashboard automatisch ergänzt.
4. Beim nächsten Cronjob werden aktivierte Quellen abgerufen. Im Feed-Status erscheinen Erfolg/Fehler und der letzte Prüfzeitpunkt.

Eigene Feeds werden in `data/custom-feeds.json` gespeichert. Der Ordner `data/` muss serverseitig schreibbar und per `.htaccess` gegen direkten HTTP-Zugriff gesperrt sein. Nur vertrauenswürdige RSS-/Atom-URLs hinzufügen; die Quellenverwaltung ist mit HTTP Basic Auth geschützt und sollte ausschließlich über HTTPS genutzt werden.

## Cronjob auf 1blu per SSH

Die 1blu-FAQ beschreibt die Einrichtung über SSH und `crontab -e` für unterstützte Tarife. PHP-CLI-Pfade können je nach PHP-Version und Paket unterschiedlich sein. Prüfe sie zuerst:

```sh
php -v
command -v php
ls -d /opt/php* 2>/dev/null
pwd
```

Bei 1blu ist ein Beispielpfad für PHP 8.3 `/opt/php83/bin/php`; für PHP 8.4 kann der Pfad abweichen. Verwende den in deinem Tarif verfügbaren absoluten CLI-Pfad. Beispiel nach dem Wechsel in den Ordner der Anwendung:

```cron
*/5 * * * * /ABSOLUTER/PFAD/ZUR/PHP-CLI /ABSOLUTER/PFAD/news/cron.php >> /ABSOLUTER/PFAD/news/cron.log 2>&1
```

Nicht den Beispielpfad blind übernehmen. `cron.php` akzeptiert absichtlich nur CLI-Aufrufe.

## Was ist enthalten?

- Zusatzbereiche Wetter, Warnungen und Suche sind einklappbar; auf schmalen Handybildschirmen standardmäßig geschlossen.
- Lauf-Ticker: Tempo und Schriftgröße über `Aa` einstellen; die Auswahl wird lokal im Browser gespeichert.
- WDR-Feed korrigiert auf die in der offiziellen Übersicht gelisteten `.feed`-URLs für Nachrichten, Rheinland, Ruhrgebiet und Verbraucher.

- Kategorien: Alle, Deutschland, NRW, Kreis Wesel, Kamp-Lintfort, Finanzen & Börse
- Suche über Überschrift, Beschreibung, Quelle und Kategorie
- Lauf-Ticker, Pause/Weiter, Dark Mode
- Wetter aktuell plus 3-Tage-Vorschau für Kamp-Lintfort
- zusätzliche DWD-Warnungsabfrage (abhängig von Verfügbarkeit und API-Schema)
- Feed-Status, letzter Prüfzeitpunkt, Quellenlinks, Zeitstempel und Duplikatfilter\n- Geschützte Quellenverwaltung für eigene RSS-/Atom-Feeds
- Cronjob-Cache als JSON; kein Datenbankserver notwendig

## Quellen und Nutzungsbedingungen

- Tagesschau RSS-Übersicht: https://www.tagesschau.de/infoservices/rssfeeds
- WDR-Feeds und Bedingungen: https://www1.wdr.de/global/feeds100.html
- WDR-Feeds im Paket: Nachrichten, Rheinland, Ruhrgebiet und Verbraucher entsprechend dem aktuellen Feed-Verzeichnis; URLs mit `.feed`.
- Google News RSS-Suchfeeds für Kreis Wesel, Kamp-Lintfort, Niederrhein, Börse und Energiepreise
- Wetter: Open-Meteo https://open-meteo.com/ (Attribution erforderlich)
- DWD-Warnungen über einen Community-Proxy: https://dwd.api.proxy.bund.dev/ – die API kann sich ändern; offizielle Warnseite: https://www.dwd.de/DE/wetter/warnungen_gemeinden/warnWetter_node.html

**Wichtige Grenzen:**

- Fünf Minuten ist das Aktualisierungsintervall des eigenen Abrufs, keine Garantie, dass Herausgeber so oft neue Meldungen liefern.
- Google News ist ein Suchfeed und keine amtliche Quelle; die lokale Abdeckung variiert.
- Der DWD-Endpunkt ist ein Community-Proxy und kann ausfallen oder sein Format ändern. Prüfe die Warnungsanzeige nach Installation und nutze für Entscheidungen zusätzlich die offizielle DWD-WarnWetter-App/Webseite bzw. NINA. Diese Seite ist keine Notfallquelle.
- Der WDR erlaubt laut eigener Bedingungen nur private, nicht-kommerzielle Nutzung seiner Feeds, verlangt Quellenangabe und Verlinkung und untersagt Archivierung. Tagesschau nennt ebenfalls Einschränkungen für die Veröffentlichung. Daher nur privat einsetzen und Anbieterbedingungen beachten.
- Open-Meteo ist im kostenlosen Tarif für nicht-kommerzielle Nutzung vorgesehen; Quellenangabe/Lizenzhinweis beachten.
- Das Paket ist ein Startstand: Cronjob, Schreibrechte, Feed-Verfügbarkeit und Warnungsdaten müssen nach dem Upload geprüft werden.
