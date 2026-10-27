<?php
declare(strict_types=1);
// Wird idealerweise ausschließlich durch einen IONOS-Cronjob per PHP-CLI gestartet.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Berlin');

function fetchUrl(string $url, int $timeout = 12): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'NewsCenter/1.0',
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            return null;
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => "User-Agent: NewsCenter/1.0\r\nAccept-Encoding: gzip\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }

        // Manche Server liefern gzip auch dann, wenn PHP es
        // nicht automatisch dekomprimiert.
        if (strncmp($body, "\x1f\x8b", 2) === 0) {
            $decoded = @gzdecode($body);
            if ($decoded === false) {
                return null;
            }
            $body = $decoded;
        }
    }

    return $body;
}
function cleanText(string $text): string { return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
function pointInRing(float $lat, float $lon, array $ring): bool {
    $n = count($ring);
    if ($n < 3) {
        return false;
    }

    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = (float)($ring[$i][0] ?? 0);
        $yi = (float)($ring[$i][1] ?? 0);
        $xj = (float)($ring[$j][0] ?? 0);
        $yj = (float)($ring[$j][1] ?? 0);

        if ((($yi > $lat) !== ($yj > $lat))
            && ($lon < ($xj - $xi) * ($lat - $yi)
                / (($yj - $yi) ?: 1e-12) + $xi)) {
            $inside = !$inside;
        }
    }

    return $inside;
}

function dwdRegionContains(
    array $region,
    float $lat,
    float $lon
): bool {
    $geometry = $region['polygonGeometry'] ?? null;

    if (is_array($geometry)) {
        $type = $geometry['type'] ?? '';
        $coordinates = $geometry['coordinates'] ?? [];

        // GeoJSON Polygon: Außenring plus gegebenenfalls Innenringe.
        if ($type === 'Polygon' && isset($coordinates[0])) {
            if (!pointInRing($lat, $lon, $coordinates[0])) {
                return false;
            }

            foreach (array_slice($coordinates, 1) as $hole) {
                if (pointInRing($lat, $lon, $hole)) {
                    return false;
                }
            }

            return true;
        }

        // GeoJSON MultiPolygon.
        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (isset($polygon[0])
                    && pointInRing($lat, $lon, $polygon[0])) {
                    $inHole = false;

                    foreach (array_slice($polygon, 1) as $hole) {
                        if (pointInRing($lat, $lon, $hole)) {
                            $inHole = true;
                            break;
                        }
                    }

                    if (!$inHole) {
                        return true;
                    }
                }
            }
        }
    }

    // DWD-Fallback: flaches Array in der Reihenfolge
    // Breitengrad, Längengrad, Breitengrad, Längengrad ...
    $flat = $region['polygon'] ?? [];
    if (is_array($flat) && count($flat) >= 6) {
        $ring = [];

        for ($i = 0; $i + 1 < count($flat); $i += 2) {
            $ring[] = [
                (float)$flat[$i + 1],
                (float)$flat[$i]
            ];
        }

        return pointInRing($lat, $lon, $ring);
    }

    return false;
}
function parseFeed(string $xml, array $feed, int $limit): array {
    libxml_use_internal_errors(true); $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA); if (!$root) return [];
    $entries = [];
    if (isset($root->channel->item)) {
        foreach ($root->channel->item as $item) {
            $entries[] = ['title'=>(string)$item->title, 'url'=>(string)$item->link, 'date'=>(string)$item->pubDate, 'description'=>(string)$item->description];
            if (count($entries) >= $limit) break;
        }
    } elseif (isset($root->entry)) {
        foreach ($root->entry as $item) {
            $link = ''; foreach ($item->link as $l) { $attrs=$l->attributes(); if ((string)($attrs['rel'] ?? 'alternate') === 'alternate') { $link=(string)($attrs['href'] ?? ''); break; } }
            $entries[] = ['title'=>(string)$item->title, 'url'=>$link, 'date'=>(string)($item->published ?? $item->updated), 'description'=>(string)($item->summary ?? $item->content)];
            if (count($entries) >= $limit) break;
        }
    }
    $out=[];
    foreach ($entries as $e) {
        $title=cleanText($e['title']); $url=trim($e['url']); if ($title==='' || !filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)) continue;
        $ts=strtotime($e['date']) ?: time();
        $out[]=['title'=>$title,'url'=>$url,'timestamp'=>$ts,'date'=>date(DATE_ATOM,$ts),'description'=>mb_substr(cleanText($e['description']),0,400),'category'=>$feed['category'],'source'=>$feed['name']];
    }
    return $out;
}
$now=time(); $items=[]; $feedStatus=[];
$feeds = $config['feeds'];
$customFile = $config['custom_feeds_file'] ?? (__DIR__.'/data/custom-feeds.json');
if (is_file($customFile)) {
    $custom = json_decode((string)file_get_contents($customFile), true);
    if (is_array($custom)) foreach ($custom as $feed) {
        if (!is_array($feed) || empty($feed['enabled']) || empty($feed['name']) || empty($feed['url']) || empty($feed['category'])) continue;
        if (!filter_var($feed['url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($feed['url'], PHP_URL_SCHEME)), ['http','https'], true)) continue;
        $feeds[] = ['name'=>trim((string)$feed['name']), 'category'=>trim((string)$feed['category']), 'url'=>trim((string)$feed['url'])];
    }
}
foreach ($feeds as $feed) {
    $xml=fetchUrl($feed['url']); $added=0;
    if ($xml !== null) { $parsed=parseFeed($xml,$feed,(int)$config['max_items_per_feed']); foreach($parsed as $item) { if ($item['timestamp'] >= $now - ((int)$config['max_age_days']*86400)) { $items[]=$item; $added++; } } }
    $feedStatus[]=['name'=>$feed['name'],'category'=>$feed['category'],'ok'=>$xml!==null,'count'=>$added,'checkedAt'=>date(DATE_ATOM,$now)];
}
// Deduplizieren nach normalisiertem Titel, dann URL.
$seen=[]; $unique=[];
foreach ($items as $item) { $key=mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u',' ', $item['title']) ?? $item['title']); if(isset($seen[$key])) continue; $seen[$key]=true; $unique[]=$item; }
usort($unique, fn($a,$b)=>$b['timestamp']<=>$a['timestamp']); $unique=array_slice($unique,0,250);
$weather=null; $loc=$config['location'];
$weatherUrl='https://api.open-meteo.com/v1/forecast?latitude='.rawurlencode((string)$loc['latitude']).'&longitude='.rawurlencode((string)$loc['longitude']).'&current=temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=Europe%2FBerlin&forecast_days=3';
$w=fetchUrl($weatherUrl); if($w!==null) { $decoded=json_decode($w,true); if(is_array($decoded) && isset($decoded['current'])) $weather=$decoded; }
$warnings = [];
$warningError = null;

$dw = fetchUrl($config['dwd_warnings_url']);

if ($dw === null) {
    $warningError = 'DWD-Warnungsdienst nicht erreichbar.';
} else {
    $d = json_decode($dw, true);

    if (!is_array($d) || !isset($d['warnings'])
        || !is_array($d['warnings'])) {
        $warningError = 'DWD-Warnungsdaten ungültig.';
    } else {
        $lat = (float)$loc['latitude'];
        $lon = (float)$loc['longitude'];

        foreach ($d['warnings'] as $warning) {
            if (!is_array($warning)) {
                continue;
            }

            $regions = $warning['regions'] ?? [];
            $isLocal = false;

            foreach ($regions as $region) {
                if (is_array($region)
                    && dwdRegionContains($region, $lat, $lon)) {
                    $isLocal = true;
                    break;
                }
            }

            if (!$isLocal) {
                continue;
            }

            // DWD-Zeitstempel sind Millisekunden;
            // das Dashboard verwendet Sekunden.
            $start = (int)($warning['start'] ?? 0);
            $end = (int)($warning['end'] ?? 0);

            if ($start > 9999999999) {
                $start = (int)floor($start / 1000);
            }
            if ($end > 9999999999) {
                $end = (int)floor($end / 1000);
            }

            // Bereits abgelaufene Warnungen nicht anzeigen.
            if ($end > 0 && $end <= time()) {
                continue;
            }

            $warnings[] = [
                'areaId' => 'local',
                'headline' => (string)(
                    $warning['headLine']
                    ?? $warning['event']
                    ?? 'Wetterwarnung'
                ),
                'description' => (string)(
                    $warning['descriptionText']
                    ?? $warning['description']
                    ?? ''
                ),
                'level' => (int)($warning['level'] ?? 0),
                'start' => $start,
                'end' => $end,
                'source' => 'DWD'
            ];
        }
    }
}
$payload=['updatedAt'=>date(DATE_ATOM),'location'=>$loc,'items'=>$unique,'feeds'=>$feedStatus,'weather'=>$weather,'warnings'=>$warnings,'warningError'=>$warningError,'meta'=>['app'=>'NewsCenter','version'=>'1.2']];
$file=$config['cache_file']; $tmp=$file.'.tmp';
if(!is_dir(dirname($file))) mkdir(dirname($file),0750,true);
$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
if($json===false || file_put_contents($tmp,$json,LOCK_EX)===false || !rename($tmp,$file)) { fwrite(STDERR,"Cache konnte nicht geschrieben werden\n"); exit(1); }
echo date('c').': '.count($unique).' Meldungen; Wetter '.($weather?'OK':'nicht verfügbar').'; '.count($warnings).' Warnungen.\n';
