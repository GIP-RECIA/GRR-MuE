<?php

/**
 * La classe Session permet de gérer les sessions en base de données
 *
 * @author Aurore
 */

declare(strict_types=1);

final class Session
{

    public $session_time = 7200;//2 heures
    public $session = array();
    private $db;

    public function __construct()
    {

    }

    //pour l'ouverture
    public function open(): bool
    {
        $this->gc();
        return true;
    }

    //lecture
    public function read($sid): string
    {
        $sid = session_id();

        $sql = "SELECT sess_datas FROM " . TABLE_PREFIX . "_session
            WHERE sess_id = '$sid' ";

        $query = grr_sql_query($sql) or exit(grr_sql_error());
        $data = mysqli_fetch_array($query);

        if (empty($data)) return "";
        else return $data['sess_datas'];
    }

    //écriture
    public function write($sid, $data): bool
    {

        // si $GLOBALS['db_c] n'est pas défini, on le redéfinit
        if (!isset($GLOBALS['db_c'])) {
            $GLOBALS['db_c'] = @mysqli_connect('p:' . $GLOBALS['dbHost'], $GLOBALS['dbUser'], $GLOBALS['dbPass'], $GLOBALS['dbPort']);
            if (!$GLOBALS['db_c'] || !mysqli_select_db($GLOBALS['db_c'], $GLOBALS['dbDb'])) {
                echo "\n<p>Database connection failure</p>\n";
                exit;
            }
        }

        //calcul de l'expiration de la session
        $expire = intval(time() + $this->session_time);

        $sid = session_id();

        $sql = "SELECT COUNT(sess_id) AS total
				FROM " . TABLE_PREFIX . "_session
				WHERE sess_id = '" . protect_data_sql($sid) . "' ";

        $query = grr_sql_query($sql) or exit(grr_sql_error());
        $return = mysqli_fetch_array($query);

        //si la session n'existe pas encore
        if ($return['total'] == 0) {
            $sql = "INSERT INTO " . TABLE_PREFIX . "_session
				VALUES('" . protect_data_sql($sid) . "','" . protect_data_sql($data) . "','" . $expire . "')";//alors on la crée

        } else {//sinon

            $sql = "UPDATE " . TABLE_PREFIX . "_session
					SET sess_datas = '" . protect_data_sql($data) . "',
						sess_expire = '$expire'
					WHERE sess_id = '" . protect_data_sql($sid) . "' ";//on la modifie
        }
        $query = grr_sql_query($sql) or exit(grr_sql_error());

        return $query;
    }

    //fermeture
    public function close(): bool
    {

        return true;
        //if ($this->connect){
        //	mysql_close($this->connect);//on ferme la bdd
        //}
    }

    //destruction
    public function destroy($sid) : bool
    {

        $sid = session_id();
        $sql = "DELETE FROM " . TABLE_PREFIX . "_session
				WHERE sess_id = '$sid' ";//on supprime la session de la bdd
        $query = grr_sql_query($sql) or exit(grr_sql_error());
        return $query;
    }

    //nettoyage
    public function gc() : int | false
    {
        $sql = "DELETE FROM " . TABLE_PREFIX . "_session
            WHERE sess_expire < " . time(); //on supprime les vieilles sessions

        $query = grr_sql_query($sql) or exit(grr_sql_error());

        return grr_sql_command($sql);
    }

}
