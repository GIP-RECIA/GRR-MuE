<?php
/**
 * Test de bout en bout : la confirmation de réservation n'est envoyée que si
 * la salle a un gestionnaire actif dans grr_j_mailuser_room (hypothèse
 * confirmée par lecture de code le 2026-11-23, voir "GRR-MuE - inventaire
 * couche établissement.md"). Vérifie la réception réelle via l'API Mailpit,
 * pas juste que la fonction a été appelée.
 *
 * Crée une salle + des utilisateurs jetables (préfixe test_mail_), teste
 * plusieurs profils, nettoie tout à la fin - y compris en cas d'échec
 * (try/finally).
 *
 * Usage (depuis Lando, à la racine du projet) :
 *   lando php tests/mail_confirmation_e2e_test.php
 *
 * Port API/web Mailpit confirmé le 2026-11-23 via
 * `lando ssh -s mailpit -c "env | grep MP_"` -> MP_UI_BIND_ADDR=0.0.0.0:80
 * (pas le 8025 par défaut de l'image Mailpit standard - ce setup Lando le
 * redéfinit).
 */

$base_path = dirname(__DIR__);

require_once $base_path . '/include/config.inc.php';
if (file_exists($base_path . '/include/connect.inc.php')) {
    include $base_path . '/include/connect.inc.php';
}
require_once $base_path . '/include/' . $dbsys . '.inc.php';
require_once $base_path . '/include/misc.inc.php';
require_once $base_path . '/include/functions.inc.php';
require_once $base_path . '/include/mrbs_sql.inc.php';
require_once $base_path . '/include/settings.class.php';
Settings::load();
require_once $base_path . '/include/language.inc.php';

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'grr-mue.lndo.site';

const MAILPIT_API = 'http://mailpit:80/api/v1/messages';
const TEST_ROOM_NAME = 'TEST_mail_e2e_room';
const TEST_LOGIN_ACTIF = 'test_mail_actif';
const TEST_LOGIN_INACTIF = 'test_mail_inactif';
const TEST_EMAIL_ACTIF = 'test-actif@example.test';
const TEST_EMAIL_INACTIF = 'test-inactif@example.test';
const TEST_LOGIN_CREATEUR = 'test_mail_createur';
const TEST_LOGIN_BENEFICIAIRE = 'test_mail_beneficiaire';
const TEST_EMAIL_BENEFICIAIRE = 'test-beneficiaire@example.test';

$failures = 0;

function mailpit_clear(): void
{
    $ch = curl_init(MAILPIT_API);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);

    // Vérifie que le nettoyage a bien pris effet avant de continuer (évite
    // une course avec le test suivant si la suppression est asynchrone).
    for ($i = 0; $i < 10; $i++) {
        if (mailpit_total_count() === 0) {
            return;
        }
        usleep(200000);
    }
    fwrite(STDERR, "mailpit_clear(): la boîte n'est toujours pas vide après 2s d'attente.\n");
}

function mailpit_total_count(): int
{
    $data = mailpit_raw(MAILPIT_API);
    return $data['total'] ?? -1;
}

function mailpit_raw(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $body = curl_exec($ch);
    if ($body === false) {
        fwrite(STDERR, "Impossible de joindre l'API Mailpit (" . MAILPIT_API . ") - adresse/port à vérifier.\n");
        return [];
    }
    return json_decode($body, true) ?? [];
}

function mailpit_messages_to(string $email): array
{
    // Le paramètre ?query=to:... de l'API Mailpit ne filtre pas de façon
    // fiable ici (constaté le 2026-11-23) - on récupère tout et on filtre
    // nous-mêmes sur le champ To.
    $data = mailpit_raw(MAILPIT_API);
    $messages = $data['messages'] ?? [];
    return array_values(array_filter($messages, function ($m) use ($email) {
        foreach ($m['To'] ?? [] as $to) {
            if (($to['Address'] ?? '') === $email) {
                return true;
            }
        }
        return false;
    }));
}

function check(bool $cond, string $label): void
{
    global $failures;
    echo ($cond ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$cond) {
        $failures++;
    }
}

function make_test_entry(int $roomId, string $beneficiaire = TEST_LOGIN_CREATEUR): int
{
    // Pour les scénarios 1-3 (chemin gestionnaire), create_by/beneficiaire
    // restent le même utilisateur jetable (test_mail_createur) : le chemin
    // bénéficiaire de send_mail() se déclenche quand même ici car
    // $_SESSION['login'] n'est jamais définie par défaut dans ce script
    // CLI - $user_login (= getUserName()) est vide, toujours différent du
    // bénéficiaire. On le laisse tourner vers une adresse à part sans
    // fausser les assertions sur les gestionnaires.
    //
    // Pour les scénarios 4-5 (chemin bénéficiaire), $_SESSION['login'] est
    // défini explicitement avant l'appel - voir plus bas.
    $start = time() + 3600;
    $end = $start + 3600;
    grr_sql_command(
        "INSERT INTO " . TABLE_PREFIX . "_entry (start_time, end_time, room_id, create_by, beneficiaire, name, description)
         VALUES ($start, $end, $roomId, '" . TEST_LOGIN_CREATEUR . "', '$beneficiaire', 'Test e2e mail', 'Entrée jetable - test mail e2e')"
    );
    return (int) grr_sql_query1("SELECT MAX(id) FROM " . TABLE_PREFIX . "_entry WHERE room_id = $roomId");
}

// --- Setup ---
$areaId = grr_sql_query1("SELECT id FROM " . TABLE_PREFIX . "_area LIMIT 1");
if ($areaId == -1) {
    fwrite(STDERR, "Aucun domaine existant en base - impossible de créer une salle de test.\n");
    exit(1);
}

grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_room WHERE room_name = '" . TEST_ROOM_NAME . "'");
grr_sql_command("INSERT INTO " . TABLE_PREFIX . "_room (area_id, room_name, description, comment_room) VALUES ($areaId, '" . TEST_ROOM_NAME . "', 'Salle jetable - test mail e2e', '')");
$roomId = (int) grr_sql_query1("SELECT id FROM " . TABLE_PREFIX . "_room WHERE room_name = '" . TEST_ROOM_NAME . "'");

$profiles = [
    [TEST_LOGIN_ACTIF, TEST_EMAIL_ACTIF, 'actif'],
    [TEST_LOGIN_INACTIF, TEST_EMAIL_INACTIF, 'inactif'],
    [TEST_LOGIN_CREATEUR, 'test-createur@example.test', 'actif'],
    [TEST_LOGIN_BENEFICIAIRE, TEST_EMAIL_BENEFICIAIRE, 'actif'],
];
foreach ($profiles as [$login, $email, $etat]) {
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_utilisateurs WHERE login='$login'");
    grr_sql_command(
        "INSERT INTO " . TABLE_PREFIX . "_utilisateurs (login, nom, prenom, email, statut, etat, source)
         VALUES ('$login', 'Test', 'Mail', '$email', 'utilisateur', '$etat', 'local')"
    );
}

$dformat = $dformat ?? '%A %d %B %Y';

// Fige send_always_mail_to_creator à '0' pour la durée du test (restauré en
// fin de script) : les scénarios 4/5 testent précisément ce que fait le
// chemin bénéficiaire QUAND ce réglage est désactivé - sans ça, le test
// dépendrait silencieusement de la valeur déjà en base.
$sendAlwaysMailToCreatorBefore = Settings::get('send_always_mail_to_creator');
Settings::set('send_always_mail_to_creator', '0');

try {
    // --- Scénario 1 : aucun gestionnaire ---
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_j_mailuser_room WHERE id_room = $roomId");
    mailpit_clear();
    send_mail(make_test_entry($roomId), 1, $dformat);
    sleep(2);
    check(count(mailpit_messages_to(TEST_EMAIL_ACTIF)) === 0, "aucun gestionnaire -> pas de mail");

    // --- Scénario 2 : gestionnaire actif ---
    grr_sql_command("INSERT INTO " . TABLE_PREFIX . "_j_mailuser_room (id_room, login) VALUES ($roomId, '" . TEST_LOGIN_ACTIF . "')");
    mailpit_clear();
    send_mail(make_test_entry($roomId), 1, $dformat);
    sleep(2);
    check(count(mailpit_messages_to(TEST_EMAIL_ACTIF)) === 1, "gestionnaire actif -> mail reçu");
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_j_mailuser_room WHERE id_room = $roomId AND login='" . TEST_LOGIN_ACTIF . "'");

    // --- Scénario 3 : gestionnaire inactif (doit être filtré par etat='actif') ---
    grr_sql_command("INSERT INTO " . TABLE_PREFIX . "_j_mailuser_room (id_room, login) VALUES ($roomId, '" . TEST_LOGIN_INACTIF . "')");
    mailpit_clear();
    send_mail(make_test_entry($roomId), 1, $dformat);
    sleep(2);
    check(count(mailpit_messages_to(TEST_EMAIL_INACTIF)) === 0, "gestionnaire inactif -> pas de mail (filtré par etat)");

    // --- Scénario 4 : on réserve pour soi-même (chemin bénéficiaire) ---
    // Simule une vraie session utilisateur ($_SESSION['login']) - sans ça,
    // user_login (= getUserName()) est vide et ce chemin se déclenche
    // toujours, peu importe le bénéficiaire (voir scénarios 1-3).
    $_SESSION['login'] = TEST_LOGIN_CREATEUR;
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_j_mailuser_room WHERE id_room = $roomId");
    mailpit_clear();
    send_mail(make_test_entry($roomId, TEST_LOGIN_CREATEUR), 1, $dformat);
    sleep(2);
    check(count(mailpit_messages_to('test-createur@example.test')) === 0, "réservation pour soi-même -> pas de mail bénéficiaire");

    // --- Scénario 5 : on réserve pour quelqu'un d'autre (chemin bénéficiaire) ---
    mailpit_clear();
    send_mail(make_test_entry($roomId, TEST_LOGIN_BENEFICIAIRE), 1, $dformat);
    sleep(2);
    check(count(mailpit_messages_to(TEST_EMAIL_BENEFICIAIRE)) === 1, "réservation pour quelqu'un d'autre -> mail bénéficiaire reçu");
    unset($_SESSION['login']);
} finally {
    // --- Teardown (toujours exécuté) ---
    Settings::set('send_always_mail_to_creator', $sendAlwaysMailToCreatorBefore ?? '0');
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_entry WHERE room_id = $roomId");
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_j_mailuser_room WHERE id_room = $roomId");
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_room WHERE id = $roomId");
    grr_sql_command("DELETE FROM " . TABLE_PREFIX . "_utilisateurs WHERE login IN ('" . TEST_LOGIN_ACTIF . "', '" . TEST_LOGIN_INACTIF . "', '" . TEST_LOGIN_CREATEUR . "', '" . TEST_LOGIN_BENEFICIAIRE . "')");
    mailpit_clear();
    echo "Nettoyage terminé.\n";
}

exit($failures > 0 ? 1 : 0);
