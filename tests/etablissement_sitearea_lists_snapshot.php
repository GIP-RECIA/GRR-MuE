<?php
/**
 * Test de caractérisation de la famille C ("GRR-MuE - inventaire couche
 * établissement") : make_site_select_html, make_area_select_html,
 * make_site_list_html, make_area_list_html, make_site_item_html,
 * make_area_item_html.
 *
 * Usage (depuis Lando, à la racine du projet) :
 *   lando php tests/etablissement_sitearea_lists_snapshot.php > /tmp/snapshot_avant.txt
 *   ... refacto ...
 *   lando php tests/etablissement_sitearea_lists_snapshot.php > /tmp/snapshot_apres.txt
 *   diff /tmp/snapshot_avant.txt /tmp/snapshot_apres.txt
 *
 * Même principe que tests/etablissement_access_snapshot.php : capture le
 * HTML/texte réellement produit (via return ou echo, peu importe), bugs
 * compris, pour un échantillon réel d'utilisateurs x sites x domaines x
 * établissements tirés de la base.
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

$_SERVER['PHP_SELF'] = '/day.php';

const USER_LIMIT = 10;
const SITE_LIMIT = 10;
const AREA_LIMIT = 10;
const ETAB_LIMIT = 5;

const LINK = 'day.php';
const YEAR = 2026;
const MONTH = 11;
const DAY = 23;

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

function safe_call(string $label, callable $fn, array $args): string
{
    try {
        ob_start();
        $result = call_user_func_array($fn, $args);
        $echoed = ob_get_clean();
        $resultStr = 'RETURN=' . var_export($result, true) . ' ECHO=' . var_export($echoed, true);
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $resultStr = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage();
    }
    return $label . '(' . implode(',', $args) . ') = ' . $resultStr;
}

$users = fetch_column('SELECT login FROM ' . TABLE_PREFIX . '_utilisateurs ORDER BY login', USER_LIMIT);
$sites = fetch_column('SELECT id FROM ' . TABLE_PREFIX . '_site ORDER BY id', SITE_LIMIT);
$areas = fetch_column('SELECT id FROM ' . TABLE_PREFIX . '_area ORDER BY id', AREA_LIMIT);
$etabCodes = fetch_column('SELECT code FROM ' . TABLE_PREFIX . "_etablissement WHERE code IS NOT NULL ORDER BY code", ETAB_LIMIT);

// -1 = "tous les sites/domaines", valeur spéciale testée partout dans ce cluster
array_unshift($sites, -1);
array_unshift($areas, -1);

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

        foreach ($sites as $site) {
            $lines[] = "etab=$etabLabel|" . safe_call('make_site_select_html', 'make_site_select_html', [LINK, $site, YEAR, MONTH, DAY, $user]);
            $lines[] = "etab=$etabLabel|" . safe_call('make_site_list_html', 'make_site_list_html', [LINK, $site, YEAR, MONTH, DAY, $user]);
            $lines[] = "etab=$etabLabel|" . safe_call('make_site_item_html', 'make_site_item_html', [LINK, $site, YEAR, MONTH, DAY, $user]);

            foreach ($areas as $area) {
                $lines[] = "etab=$etabLabel|" . safe_call('make_area_select_html', 'make_area_select_html', [LINK, $site, $area, YEAR, MONTH, DAY, $user]);
                $lines[] = "etab=$etabLabel|" . safe_call('make_area_list_html', 'make_area_list_html', [LINK, $site, $area, YEAR, MONTH, DAY, $user]);
                $lines[] = "etab=$etabLabel|" . safe_call('make_area_item_html', 'make_area_item_html', [LINK, $site, $area, YEAR, MONTH, DAY, $user]);
            }
        }
    }
}

sort($lines);
foreach ($lines as $line) {
    echo $line . PHP_EOL;
}

fwrite(STDERR, count($lines) . " lignes générées (" . count($users) . " users x " . count($etabCodes) . " etabs x " . count($sites) . " sites x " . count($areas) . " areas).\n");
