<?php
/**
 * Test de caractérisation du cluster droits/permissions étab (famille B de
 * "GRR-MuE - inventaire couche établissement") : authGetUserLevelGlobal,
 * authGetUserLevelSite, authGetUserLevelArea, authGetUserLevelRoom,
 * authUserAccesArea, canAccesAdmin.
 *
 * Usage (depuis Lando, à la racine du projet) :
 *   lando php tests/etablissement_access_snapshot.php > /tmp/snapshot_avant.txt
 *   ... refacto ...
 *   lando php tests/etablissement_access_snapshot.php > /tmp/snapshot_apres.txt
 *   diff /tmp/snapshot_avant.txt /tmp/snapshot_apres.txt
 *
 * Capture le comportement RÉEL, bugs compris (les exceptions mysqli levées
 * par les bugs déjà repérés dans canAccesAdmin()/authGetUserLevelSite() sont
 * attrapées et consignées comme résultat, pas comme échec du script) - voir
 * "GRR-MuE - inventaire couche établissement.md" pour le détail de ces bugs.
 * Toute différence entre les deux snapshots qui ne correspond pas à un de ces
 * bugs connus est une régression introduite par le refacto.
 */

$base_path = dirname(__DIR__);

require_once $base_path . '/include/config.inc.php';
if (file_exists($base_path . '/include/connect.inc.php')) {
    include $base_path . '/include/connect.inc.php';
}
require_once $base_path . '/include/' . $dbsys . '.inc.php';
require_once $base_path . '/include/misc.inc.php';
require_once $base_path . '/include/functions.inc.php';
require_once $base_path . '/include/settings.class.php';
Settings::load();

const USER_LIMIT = 30;
const SITE_LIMIT = 20;
const AREA_LIMIT = 20;
const ROOM_LIMIT = 20;
const ETAB_LIMIT = 10;

function fetch_column(string $sql, int $limit): array
{
    $res = grr_sql_query($sql);
    $out = [];
    if ($res) {
        for ($i = 0; $i < $limit && ($row = grr_sql_row($res, $i)); $i++) {
            $out[] = $row[0];
        }
    }
    return $out;
}

function safe_call(string $fn, array $args): string
{
    try {
        $result = call_user_func_array($fn, $args);
        $resultStr = var_export($result, true);
    } catch (\Throwable $e) {
        $resultStr = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage();
    }
    return $fn . '(' . implode(',', $args) . ') = ' . $resultStr;
}

$users = fetch_column('SELECT login FROM ' . TABLE_PREFIX . '_utilisateurs ORDER BY login', USER_LIMIT);
$sites = fetch_column('SELECT id FROM ' . TABLE_PREFIX . '_site ORDER BY id', SITE_LIMIT);
$areas = fetch_column('SELECT id FROM ' . TABLE_PREFIX . '_area ORDER BY id', AREA_LIMIT);
$rooms = fetch_column('SELECT id FROM ' . TABLE_PREFIX . '_room ORDER BY id', ROOM_LIMIT);
$etabCodes = fetch_column('SELECT code FROM ' . TABLE_PREFIX . "_etablissement WHERE code IS NOT NULL ORDER BY code", ETAB_LIMIT);

if (empty($etabCodes)) {
    $etabCodes = [null];
}

$lines = [];

foreach ($etabCodes as $etabCode) {
    if ($etabCode !== null) {
        $_SESSION['current_etablisement'] = $etabCode;
    }
    $etabLabel = $etabCode ?? 'NONE';

    foreach ($users as $user) {
        $_SESSION['login'] = $user;

        $lines[] = "etab=$etabLabel|" . safe_call('authGetUserLevelGlobal', [$user]);
        $lines[] = "etab=$etabLabel|" . safe_call('canAccesAdmin', []);

        foreach ($sites as $site) {
            $lines[] = "etab=$etabLabel|" . safe_call('authGetUserLevelSite', [$user, $site]);
        }
        foreach ($areas as $area) {
            $lines[] = "etab=$etabLabel|" . safe_call('authGetUserLevelArea', [$user, $area]);
            $lines[] = "etab=$etabLabel|" . safe_call('authUserAccesArea', [$user, $area]);
        }
        foreach ($rooms as $room) {
            $lines[] = "etab=$etabLabel|" . safe_call('authGetUserLevelRoom', [$user, $room]);
        }
    }
}

sort($lines);
foreach ($lines as $line) {
    echo $line . PHP_EOL;
}

fwrite(STDERR, count($lines) . " lignes générées (" . count($users) . " users x " . count($etabCodes) . " etabs x " . count($sites) . " sites x " . count($areas) . " areas x " . count($rooms) . " rooms).\n");
