<?php
declare(strict_types=1);
/* NewsCenter – Quellen und Standort. Nur private Nutzung der Feeds, Nutzungsbedingungen beachten. */
return [
    'site_title' => 'NewsCenter',
    'timezone' => 'Europe/Berlin',
    'location' => ['name' => 'Kamp-Lintfort', 'latitude' => 51.504, 'longitude' => 6.545],
    'max_items_per_feed' => 35,
    // Quellenverwaltung: vor Nutzung in sources.php unbedingt ändern!
    'admin_user' => 'newsadmin',
    'admin_password' => 'AENDERE-DIESES-PASSWORT-UNBEDINGT',
    'custom_feeds_file' => __DIR__ . '/data/custom-feeds.json',
    'max_age_days' => 7,
    'feeds' => [
        ['name'=>'tagesschau.de – Alle Meldungen','category'=>'Deutschland','url'=>'https://www.tagesschau.de/infoservices/alle-meldungen-100~rss2.xml'],
        ['name'=>'tagesschau.de – Inland','category'=>'Deutschland','url'=>'https://www.tagesschau.de/inland/index~rss2.xml'],
        ['name'=>'tagesschau.de – Wirtschaft','category'=>'Finanzen','url'=>'https://www.tagesschau.de/wirtschaft/index~rss2.xml'],
        ['name'=>'tagesschau.de – Finanzen','category'=>'Finanzen','url'=>'https://www.tagesschau.de/wirtschaft/finanzen/index~rss2.xml'],
        ['name'=>'WDR – Nachrichten','category'=>'NRW','url'=>'https://www1.wdr.de/wissen/uebersicht-nachrichten-100.feed'],
        ['name'=>'WDR – Rheinland','category'=>'NRW','url'=>'https://www1.wdr.de/nachrichten/rheinland/uebersicht-rheinland-100.feed'],
        ['name'=>'WDR – Ruhrgebiet','category'=>'NRW','url'=>'https://www1.wdr.de/nachrichten/ruhrgebiet/uebersicht-ruhrgebiet-100.feed'],
        ['name'=>'WDR – Verbraucher','category'=>'Wirtschaft','url'=>'https://www1.wdr.de/verbraucher/uebersicht-verbraucher-100.feed'],
        ['name'=>'Google News – Kreis Wesel','category'=>'Kreis Wesel','url'=>'https://news.google.com/rss/search?q=%22Kreis+Wesel%22&hl=de&gl=DE&ceid=DE:de'],
        ['name'=>'Google News – Kamp-Lintfort','category'=>'Kamp-Lintfort','url'=>'https://news.google.com/rss/search?q=%22Kamp-Lintfort%22&hl=de&gl=DE&ceid=DE:de'],
        ['name'=>'Google News – Niederrhein','category'=>'Kreis Wesel','url'=>'https://news.google.com/rss/search?q=Niederrhein+Nachrichten&hl=de&gl=DE&ceid=DE:de'],
        ['name'=>'Google News – Börse und Finanzen','category'=>'Finanzen','url'=>'https://news.google.com/rss/search?q=B%C3%B6rse+Finanzen+Deutschland&hl=de&gl=DE&ceid=DE:de'],
        ['name'=>'Google News – Energiepreise','category'=>'Finanzen','url'=>'https://news.google.com/rss/search?q=Energiepreise+Deutschland&hl=de&gl=DE&ceid=DE:de'],
    ],
    // DWD-API-Proxy der Open-Source-Community; für Warnungen nicht als alleinige Notfallquelle verwenden.
    'dwd_warnings_url' => 'https://s3.eu-central-1.amazonaws.com/app-prod-static.warnwetter.de/v16/gemeinde_warnings_v2.json',
    'cache_file' => __DIR__ . '/data/cache.json',
];
