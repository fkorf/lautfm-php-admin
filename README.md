# lautfm-php-admin
PHP-Library und Beispiele für die laut.fm-Radioadmin-API

## Benutzung

Die `LautfmAdmin`-Klasse bietet über ein PHP-Interface Zugriff auf einige Endpunkte der laut.fm-Radioadmin-API. Nachdem Du eine Instanz erzeugt hast musst Du noch ein Token zuweisen. Ein Token kannst Du über den Radioadmin anfordern. Wenn nichts anderes konfiguriert wurde musst Du LautfmPhpLib als Origin angeben: [https://new.radioadmin.laut.fm/login?callback_url=LautfmPhpLib](https://new.radioadmin.laut.fm/login?callback_url=LautfmPhpLib)

```php
$lfm = new LautfmAdmin();
$lfm->token = "DeinToken";
// z. B. Abruf der Statistik
$stats = $lfm->getStatistics("DeineStation");
```

Wenn Du ein anderes Origin als LautfmPhpLib verwenden möchtest kannst Du dies über `$lfm->origin` setzen.

## Beispiele

[Hier](Beispiele/) findest Du einige Beispiele.
