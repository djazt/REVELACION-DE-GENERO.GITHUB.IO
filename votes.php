<?php
// Guarda y entrega las apuestas de la revelación. Debe estar en la misma carpeta que index.html.
// Crea automáticamente el archivo votes.json (la carpeta necesita permiso de escritura).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$file = __DIR__ . '/votes.json';

function leer($file) {
    if (!file_exists($file)) return [];
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}
function publico($votes) {
    $out = [];
    foreach ($votes as $x) $out[] = ['v' => $x['v'], 'name' => $x['name']];
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in   = json_decode(file_get_contents('php://input'), true);
    $id   = preg_replace('/[^a-z0-9]/i', '', $in['id'] ?? '');
    $v    = $in['v'] ?? '';
    $name = trim(mb_substr(strip_tags($in['name'] ?? ''), 0, 24));
    if ($id === '' || !in_array($v, ['b', 'g'], true) || $name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'datos invalidos']);
        exit;
    }
    $fp = fopen($file, 'c+');
    if (!$fp) { http_response_code(500); echo json_encode(['error' => 'sin permiso de escritura']); exit; }
    flock($fp, LOCK_EX);
    $cur = json_decode(stream_get_contents($fp), true);
    if (!is_array($cur)) $cur = [];
    $found = false;
    foreach ($cur as &$x) {
        if ($x['id'] === $id) { $x['v'] = $v; $x['name'] = $name; $x['t'] = time(); $found = true; }
    }
    unset($x);
    if (!$found) {
        if (count($cur) >= 500) { flock($fp, LOCK_UN); fclose($fp); http_response_code(400); echo json_encode(['error' => 'limite']); exit; }
        $cur[] = ['id' => $id, 'v' => $v, 'name' => $name, 't' => time()];
    }
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($cur, JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    echo json_encode(['votes' => publico($cur)], JSON_UNESCAPED_UNICODE);
    exit;
}

$votes = leer($file);
$id = preg_replace('/[^a-z0-9]/i', '', $_GET['id'] ?? '');
$mine = null;
foreach ($votes as $x) if ($id !== '' && $x['id'] === $id) $mine = $x['v'];
echo json_encode(['votes' => publico($votes), 'mine' => $mine], JSON_UNESCAPED_UNICODE);
