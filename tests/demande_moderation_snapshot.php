<?php
/**
 * Test de caractérisation de demandeModeration() (famille D de
 * "GRR-MuE - inventaire couche établissement").
 *
 * Usage (depuis Lando, à la racine du projet) :
 *   lando php tests/demande_moderation_snapshot.php > /tmp/snapshot_avant.txt
 *   ... refacto ...
 *   lando php tests/demande_moderation_snapshot.php > /tmp/snapshot_apres.txt
 *   diff /tmp/snapshot_avant.txt /tmp/snapshot_apres.txt
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

$users = fetch_column('SELECT login FROM ' . TABLE_PREFIX . '_utilisateurs ORDER BY login', USER_LIMIT);
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
        try {
            $result = demandeModeration($user);
            $resultStr = var_export($result, true);
        } catch (\Throwable $e) {
            $resultStr = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage();
        }
        $lines[] = "etab=$etabLabel|demandeModeration($user) = $resultStr";
    }
}

sort($lines);
foreach ($lines as $line) {
    echo $line . PHP_EOL;
}

fwrite(STDERR, count($lines) . " lignes générées (" . count($users) . " users x " . count($etabCodes) . " etabs).\n");
