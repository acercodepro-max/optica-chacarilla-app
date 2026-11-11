<?php
/**
 * API de la app marca blanca (sin base de datos).
 * Guarda todo en data/db.json (protegido por data/.htaccess).
 * Requiere PHP 7.4+ (cPanel / WHM).
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$cfg  = require __DIR__ . '/config.php';
date_default_timezone_set($cfg['zona'] ?? 'America/Lima');
$dir  = __DIR__ . '/data';
$file = $dir . '/db.json';
const ESTADOS = ['Nuevo', 'Confirmado', 'Pagado', 'En camino', 'Entregado', 'Cancelado'];
const MAX_PROD = 20;
const MAX_PROMO = 10;

function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $msg, int $code = 400): void { out(['error' => $msg], $code); }
function r2($n): float { return round((float)$n + 1e-9, 2); }
function hoy(): string { return date('Y-m-d'); }

if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
$lock = fopen($dir . '/.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) fail('No se pudo bloquear el almacenamiento', 500);

function leer(string $f): ?array { if (!is_file($f)) return null; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : null; }
function escribir(string $f, array $d): void {
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE)) === false) fail('No se pudo escribir en data/. Revisa permisos.', 500);
  rename($tmp, $f);
}
function auth(array $cfg, array $in): void {
  if (!hash_equals((string)$cfg['pin'], (string)($in['pin'] ?? ''))) { usleep(400000); fail('PIN incorrecto', 401); }
}
function empresa(array &$db, string $id): ?array { foreach ($db['empresas'] as $i => $e) if ($e['id'] === $id) return [$i]; return null; }

/* ---- reglas de precio (idénticas a index.html) ---- */
function promoActiva(array $p): bool { $h = hoy(); return !empty($p['activa']) && (empty($p['inicio']) || $p['inicio'] <= $h) && (empty($p['fin']) || $p['fin'] >= $h); }
function precioFinal(array $emp, array $prod): float {
  $mejor = null;
  foreach ($emp['promos'] as $pr) if ($pr['productoId'] === $prod['id'] && promoActiva($pr) && (!$mejor || $pr['descuento'] > $mejor['descuento'])) $mejor = $pr;
  return $mejor ? r2($prod['precio'] * (1 - $mejor['descuento'] / 100)) : (float)$prod['precio'];
}
function calcular(array $emp, array $items, string $entrega): array {
  $sub = 0; $desc = 0; $lineas = [];
  foreach ($items as $it) {
    $cant = (int)($it['cant'] ?? 0); if ($cant < 1 || $cant > 99) continue;
    foreach ($emp['productos'] as $p) if ($p['id'] === ($it['id'] ?? '') && !empty($p['activo'])) {
      $pf = precioFinal($emp, $p);
      $sub += $p['precio'] * $cant; $desc += ($p['precio'] - $pf) * $cant;
      $lineas[] = ['id' => $p['id'], 'nombre' => $p['nombre'], 'tipo' => $p['tipo'], 'precio' => (float)$p['precio'], 'precioFinal' => $pf, 'cant' => $cant];
    }
  }
  $sub = r2($sub); $desc = r2($desc); $neto = r2($sub - $desc); $envio = 0;
  $hayProd = (bool)array_filter($lineas, fn($l) => $l['tipo'] === 'producto');
  if ($entrega === 'delivery' && $hayProd) $envio = $neto >= (float)$emp['envio']['gratisDesde'] ? 0 : (float)$emp['envio']['costo'];
  return ['lineas' => $lineas, 'subtotal' => $sub, 'descuento' => $desc, 'envio' => r2($envio), 'total' => r2($neto + $envio)];
}
function limpiarDb(array $db): array {
  if (!isset($db['empresas']) || !is_array($db['empresas']) || !$db['empresas']) fail('Datos inválidos');
  foreach ($db['empresas'] as $e) {
    if (count($e['productos'] ?? []) > MAX_PROD) fail('Máximo ' . MAX_PROD . ' productos por empresa');
    if (count($e['promos'] ?? []) > MAX_PROMO) fail('Máximo ' . MAX_PROMO . ' promociones por empresa');
  }
  return ['version' => 1, 'empresas' => array_values($db['empresas'])];
}

$action = $_GET['action'] ?? '';
$raw = file_get_contents('php://input');
if (strlen((string)$raw) > 12 * 1024 * 1024) fail('Datos demasiado grandes', 413);
$in = json_decode((string)$raw, true) ?: [];
$db = leer($file);

switch ($action) {
  case 'load':
    if (!$db) out(['empty' => true]);
    unset($db['pedidos']);
    out($db);

  case 'init': // solo la primera vez, cuando no existen datos
    if ($db) fail('Ya inicializado', 409);
    $nuevo = limpiarDb($in['db'] ?? []); $nuevo['pedidos'] = [];
    escribir($file, $nuevo); out(['ok' => true]);

  case 'login':
    out(['ok' => hash_equals((string)$cfg['pin'], (string)($in['pin'] ?? ''))]);

  case 'save':
    auth($cfg, $in);
    $nuevo = limpiarDb($in['db'] ?? []);
    $nuevo['pedidos'] = $db['pedidos'] ?? [];
    escribir($file, $nuevo); out(['ok' => true]);

  case 'orders':
    auth($cfg, $in);
    out(['pedidos' => $db['pedidos'] ?? []]);

  case 'status': // el cliente consulta sus propios pedidos por id
    $ids = array_slice(array_filter(explode(',', (string)($_GET['ids'] ?? ''))), 0, 50);
    $res = array_values(array_filter($db['pedidos'] ?? [], fn($p) => in_array($p['id'], $ids, true)));
    out(['pedidos' => $res]);

  case 'order':
    if (!$db) fail('La tienda aún no está configurada');
    $ix = empresa($db, (string)($in['empresaId'] ?? '')); if (!$ix) fail('Empresa no encontrada', 404);
    $emp = &$db['empresas'][$ix[0]];
    $c = $in['cliente'] ?? [];
    $cli = [
      'nombre' => trim(mb_substr((string)($c['nombre'] ?? ''), 0, 80)),
      'telefono' => preg_replace('/\D/', '', (string)($c['telefono'] ?? '')),
      'email' => trim(mb_substr((string)($c['email'] ?? ''), 0, 120)),
      'entrega' => in_array($c['entrega'] ?? '', ['delivery', 'recojo', 'servicio'], true) ? $c['entrega'] : 'recojo',
      'direccion' => trim(mb_substr((string)($c['direccion'] ?? ''), 0, 200)),
      'fechaServicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($c['fechaServicio'] ?? '')) ? $c['fechaServicio'] : '',
      'pago' => in_array($c['pago'] ?? '', $emp['pagos'], true) ? $c['pago'] : '',
      'nota' => trim(mb_substr((string)($c['nota'] ?? ''), 0, 300)),
    ];
    if (mb_strlen($cli['nombre']) < 3) fail('Nombre inválido');
    if (!preg_match('/^9\d{8}$/', $cli['telefono'])) fail('Celular inválido');
    if (!$cli['pago']) fail('Método de pago inválido');
    $t = calcular($emp, (array)($in['items'] ?? []), $cli['entrega']);
    if (!$t['lineas']) fail('El carrito está vacío');
    $hayServ = (bool)array_filter($t['lineas'], fn($l) => $l['tipo'] === 'servicio');
    if (($cli['entrega'] === 'delivery' || $hayServ) && mb_strlen($cli['direccion']) < 5) fail('Dirección inválida');
    if ($hayServ && (!$cli['fechaServicio'] || $cli['fechaServicio'] <= hoy())) fail('Fecha de servicio inválida');
    foreach ($t['lineas'] as $l) foreach ($emp['productos'] as $p)
      if ($p['id'] === $l['id'] && $p['tipo'] === 'producto' && $p['stock'] < $l['cant']) fail("Stock insuficiente de {$p['nombre']} (quedan {$p['stock']})", 409);
    foreach ($t['lineas'] as $l) foreach ($emp['productos'] as &$p) { if ($p['id'] === $l['id'] && $p['tipo'] === 'producto') $p['stock'] -= $l['cant']; } unset($p);
    $n = count(array_filter($db['pedidos'] ?? [], fn($x) => $x['empresaId'] === $emp['id'])) + 1;
    $ahora = date('c');
    $pedido = array_merge([
      'id' => strtoupper(bin2hex(random_bytes(8))),
      'num' => ($emp['codigo'] ?: 'PD') . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT),
      'empresaId' => $emp['id'], 'fecha' => $ahora, 'cliente' => $cli,
    ], $t, ['estado' => 'Nuevo', 'historial' => [['estado' => 'Nuevo', 'fecha' => $ahora]]]);
    array_unshift($db['pedidos'], $pedido);
    unset($emp);
    escribir($file, $db);
    out(['pedido' => $pedido]);

  case 'setStatus':
    auth($cfg, $in);
    $estado = (string)($in['estado'] ?? '');
    if (!in_array($estado, ESTADOS, true)) fail('Estado inválido');
    foreach ($db['pedidos'] as &$ped) {
      if ($ped['id'] !== ($in['id'] ?? '')) continue;
      $ix = empresa($db, $ped['empresaId']);
      if ($ix) {
        $emp = &$db['empresas'][$ix[0]];
        $signo = 0;
        if ($estado === 'Cancelado' && $ped['estado'] !== 'Cancelado') $signo = 1;      // devuelve stock
        if ($ped['estado'] === 'Cancelado' && $estado !== 'Cancelado') $signo = -1;     // vuelve a reservar
        if ($signo === -1) foreach ($ped['lineas'] as $l) foreach ($emp['productos'] as $p)
          if ($p['id'] === $l['id'] && $p['tipo'] === 'producto' && $p['stock'] < $l['cant']) fail('No hay stock para reactivar el pedido', 409);
        if ($signo) foreach ($ped['lineas'] as $l) foreach ($emp['productos'] as &$p) { if ($p['id'] === $l['id'] && $p['tipo'] === 'producto') $p['stock'] += $signo * $l['cant']; } unset($p);
        unset($emp);
      }
      $ped['estado'] = $estado; $ped['historial'][] = ['estado' => $estado, 'fecha' => date('c')];
      unset($ped); escribir($file, $db); out(['ok' => true]);
    }
    fail('Pedido no encontrado', 404);

  default:
    fail('Acción no válida', 404);
}
