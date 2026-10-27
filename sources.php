<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
$user = (string)($config['admin_user'] ?? '');
$pass = (string)($config['admin_password'] ?? '');
$providedUser = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
$providedPass = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
if ($user === '' || $pass === '' || $pass === 'AENDERE-DIESES-PASSWORT-UNBEDINGT' || !hash_equals($user, $providedUser) || !hash_equals($pass, $providedPass)) {
    header('WWW-Authenticate: Basic realm="NewsCenter Quellenverwaltung"');
    http_response_code(401);
    echo 'Zugriff verweigert. Bitte Zugangsdaten in config.php setzen.';
    exit;
}
function validFeedUrl(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!in_array($scheme, ['http','https'], true) || $host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return false;
    if (filter_var($host, FILTER_VALIDATE_IP)) return (bool)filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    $ip = gethostbyname($host);
    if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    return true;
}
$file = $config['custom_feeds_file'] ?? (__DIR__ . '/data/custom-feeds.json');
if (!is_dir(dirname($file))) mkdir(dirname($file), 0750, true);
$feeds = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
if (!is_array($feeds)) $feeds = [];
$categories = ['Deutschland','NRW','Kreis Wesel','Kamp-Lintfort','Finanzen','Wirtschaft','Technologie','Wetter','Sonstiges'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'Sonstiges'));
        $enabled = isset($_POST['enabled']);
        if ($name === '' || strlen($name) > 120) $error = 'Bitte einen Namen (max. 120 Zeichen) angeben.';
        elseif (!validFeedUrl($url)) $error = 'Bitte eine gültige HTTP- oder HTTPS-Feed-URL angeben.';
        elseif (!in_array($category, $categories, true)) $error = 'Bitte eine Kategorie aus der Liste auswählen.';
        else {
            $id = (string)($_POST['id'] ?? '');
            $entry = ['id'=>bin2hex(random_bytes(6)), 'name'=>$name, 'url'=>$url, 'category'=>$category, 'enabled'=>$enabled];
            if ($id !== '' && isset($feeds[(int)$id])) { $entry['id'] = $feeds[(int)$id]['id'] ?? $entry['id']; $feeds[(int)$id] = $entry; }
            else $feeds[] = $entry;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? -1);
        if (isset($feeds[$id])) array_splice($feeds, $id, 1);
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? -1);
        if (isset($feeds[$id])) $feeds[$id]['enabled'] = !$feeds[$id]['enabled'];
    }
    if ($error === '') {
        $tmp = $file . '.tmp';
        $json = json_encode(array_values($feeds), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) $error = 'Speichern fehlgeschlagen. Schreibrechte von data/ prüfen.';
        else { header('Location: sources.php?saved=1'); exit; }
    }
}
$edit = isset($_GET['edit']) && isset($feeds[(int)$_GET['edit']]) ? (int)$_GET['edit'] : null;
$val = $edit !== null ? $feeds[$edit] : ['name'=>'','url'=>'','category'=>'Kreis Wesel','enabled'=>true];
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NewsCenter – Quellenverwaltung</title><link rel="stylesheet" href="assets/style.css?v=2"><style>.admin{max-width:1000px;margin:2rem auto;padding:1rem}.admin form.inline{display:inline}.admin input,.admin select{padding:.7rem;border:1px solid var(--border);border-radius:.6rem;background:var(--panel);color:var(--text);width:100%;box-sizing:border-box}.admin label{display:block;margin:.8rem 0}.admin .source-card{border:1px solid var(--border);border-radius:.8rem;padding:1rem;margin:.7rem 0;display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap}.admin .source-card small{display:block;overflow-wrap:anywhere;color:var(--muted)}.admin .grid{display:grid;grid-template-columns:1fr 2fr;gap:1rem}@media(max-width:650px){.admin .grid{grid-template-columns:1fr}}</style></head><body><header class="top"><div class="brand"><span class="brand-icon">N</span><div><h1>Quellenverwaltung</h1><small>Eigene RSS-/Atom-Feeds verwalten</small></div></div><a class="button" href="index.php">Zum Dashboard</a></header><main class="admin">
<?php if (isset($_GET['saved'])): ?><p class="panel">Änderungen gespeichert.</p><?php endif; ?><?php if ($error!==''): ?><p class="panel"><?=h($error)?></p><?php endif; ?>
<section class="panel"><h2><?=$edit!==null?'Quelle bearbeiten':'Neue Quelle hinzufügen'?></h2><p class="small-text">Feed-URL muss ein öffentlich erreichbarer RSS- oder Atom-Feed sein. Aktivierte Quellen werden beim nächsten Cronjob abgerufen.</p><form method="post"><input type="hidden" name="action" value="save"><?php if($edit!==null): ?><input type="hidden" name="id" value="<?= $edit ?>"><?php endif; ?><div class="grid"><label>Name<input name="name" required maxlength="120" value="<?=h((string)$val['name'])?>" placeholder="z. B. Stadt Kamp-Lintfort"></label><label>Feed-URL<input type="url" name="url" required value="<?=h((string)$val['url'])?>" placeholder="https://example.org/rss.xml"></label></div><label>Kategorie<select name="category"><?php foreach($categories as $c): ?><option value="<?=h($c)?>" <?=$val['category']===$c?'selected':''?>><?=h($c)?></option><?php endforeach; ?></select></label><label><input type="checkbox" name="enabled" value="1" <?=$val['enabled']?'checked':''?> style="width:auto"> Quelle aktivieren</label><button class="button" type="submit">Speichern</button> <?php if($edit!==null): ?><a class="button" href="sources.php">Abbrechen</a><?php endif; ?></form></section>
<section class="panel" style="margin-top:1rem"><h2>Eigene Quellen (<?=count($feeds)?>)</h2><?php if(!$feeds): ?><p>Noch keine eigenen Quellen hinzugefügt.</p><?php endif; ?><?php foreach($feeds as $i=>$f): ?><div class="source-card"><div><b><?=h((string)$f['name'])?></b> · <?=h((string)$f['category'])?> · <?=!empty($f['enabled'])?'Aktiv':'Pausiert'?><small><?=h((string)$f['url'])?></small></div><div><a class="button" href="sources.php?edit=<?=$i?>">Bearbeiten</a> <form class="inline" method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$i?>"><button class="button" type="submit"><?=!empty($f['enabled'])?'Pausieren':'Aktivieren'?></button></form> <form class="inline" method="post" onsubmit="return confirm('Quelle wirklich löschen?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$i?>"><button class="button" type="submit">Löschen</button></form></div></div><?php endforeach; ?></section><p class="small-text">Sicherheit: Quellenverwaltung mit HTTP-Basic-Auth. Zugangsdaten in config.php unbedingt ändern und HTTPS verwenden.</p></main></body></html>
