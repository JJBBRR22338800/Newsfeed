<?php
declare(strict_types=1);
// Read-only JSON endpoint. Cache file remains outside direct web access.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$file=__DIR__.'/data/cache.json';
if(!is_file($file)){http_response_code(503);echo json_encode(['error'=>'Noch kein Cache vorhanden. Cronjob ausführen.'],JSON_UNESCAPED_UNICODE);exit;}
readfile($file);
