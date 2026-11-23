<?php
/**
 * include/etablissement.inc.php
 * Fonctions dédiées à la couche multi-établissement RECIA.
 * Extrait de include/functions.inc.php le 2026-11-20 (déplacement pur,
 * aucun changement de comportement) - voir la note Obsidian
 * "GRR-MuE - inventaire couche établissement" pour le contexte complet.
 */

# Retourne l'établissement par défaut;
function get_default_etablissement()
{
	$res = grr_sql_query1("SELECT min(id) FROM ".TABLE_PREFIX."_etablissement");
	return $res;
}



function authGetUserLevelEtablissement($user,$id){

	//On regarde si l'utilisateur est administrateur du domaine dont l'id est $id
	$res3 = grr_sql_query("SELECT j.login
                     FROM ".TABLE_PREFIX."_j_useradmin_etablissement j
                           WHERE (j.login='".protect_data_sql($user)."' and j.id_etablissement='".protect_data_sql($id)."')");

	if (grr_sql_count($res3) > 0)
		return 6;
	// Sinon il s'agit d'un simple utilisateur
	return 2;
}

/* authUserAccesEtab($user,$id)
 *
 * Determines if the user access etab
 *
 * $user - The user name
 * $id -   Which etab are we checking
 *
 */
function authUserAccesEtab($user,$id)
{
	if ($id=='') {
		return 0;
		die();
	}

	$sql = "SELECT login FROM ".TABLE_PREFIX."_utilisateurs WHERE (login = '".protect_data_sql($user)."' and statut='administrateur')";
	$res = grr_sql_query($sql);
	if (grr_sql_count($res) != "0") return 1;

	$sql = "SELECT login FROM ".TABLE_PREFIX."_j_useradmin_etablissement j WHERE j.id_etablissement='".$id."' AND j.login='".protect_data_sql($user)."'";
	$res = grr_sql_query($sql);
	if (grr_sql_count($res) != "0") return 1;

	$sql = "SELECT login FROM ".TABLE_PREFIX."_j_user_etablissement j WHERE j.id_etablissement='".$id."' AND j.login='".protect_data_sql($user)."'";
	$res = grr_sql_query($sql);
	if (grr_sql_count($res) != "0") return 1;

	return 0;

}

/** supprimerReservationsUtilisateursEXTParEtab()
*
* Supprime les réservations des membres qui proviennent d'une source "EXT"
* $avec_resa supprime les reservations
* $avec_privileges supprime les privileges des utilisateur
*
* Returns:
*   0        - An error occured
*   non-zero - The entries were deleted
*/
function supprimerReservationsUtilisateursEXTParEtab($avec_resa,$avec_privileges)
{
	// Récupération de tous les utilisateurs de la source EXT
	$id_etablissement = getIdEtablissementCourant();

	$requete_users_ext = "SELECT U.login FROM ".TABLE_PREFIX."_utilisateurs AS U
						  JOIN ".TABLE_PREFIX."_j_user_etablissement AS UE
						  WHERE U.source='ext' and U.statut<>'administrateur'
						  AND UE.id_etablissement = $id_etablissement";

	$sqlRoomEtab = "SELECT R.id FROM ".TABLE_PREFIX."_room AS R
							JOIN ".TABLE_PREFIX."_site_area AS SA ON SA.id_area = R.area_id
							JOIN ".TABLE_PREFIX."_etablissement_site AS ES ON ES.id_site = SA.id_site
							WHERE SA.id_etablissement = $id_etablissement" ;
	$sqlAreaEtab = "SELECT SA.id_area FROM ".TABLE_PREFIX."_site_area AS SA
							JOIN ".TABLE_PREFIX."_etablissement_site AS ES ON ES.id_site = SA.id_site
							WHERE SA.id_etablissement = $id_etablissement" ;
	$sqlSiteEtab = "SELECT ES.id_site FROM ".TABLE_PREFIX."_etablissement_site AS ES
								WHERE ES.id_etablissement = $id_etablissement" ;

	$res = grr_sql_query($requete_users_ext);
	$loginsMulti = [];
	$logins = [];
	$logins_liaison  = [];
	if ($res) for ($i = 0; ($row = grr_sql_row($res, $i)); $i++) {

		$requete_users_ext_multi = "SELECT * FROM ".TABLE_PREFIX."_j_user_etablissement
								  WHERE U.login='$row[0]' and U.id_etablissement != $id_etablissement ";
		$res2 = grr_sql_query($requete_users_ext_multi);
		if ($res2 && grr_sql_count($res2) > 0 ){
			$loginsMulti[]=$row[0];
		} else {
			$logins[]=$row[0];
		}
	}

	// Construction des requêtes de suppression à partir des différents utilisateurs à supprimer
	if ($avec_resa=='y') {
		//Les utilisateurs mono-établissement

		// Pour chaque utilisateur, on supprime les réservations qu'il a créées et celles dont il est bénéficiaire
		// Table grr_entry
		$req_suppr_table_entry = "DELETE FROM ".TABLE_PREFIX."_entry WHERE create_by = ";
		$first=1; // 1er tour
		foreach($logins as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_entry .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_entry .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}

		// Pour chaque utilisateur, on supprime les réservations périodiques qu'il a créées et celles dont il est bénéficiaire
		// Table grr_repeat
		$req_suppr_table_repeat = "DELETE FROM ".TABLE_PREFIX."_repeat WHERE create_by = ";
		$first=1; // 1er tour
		foreach($logins as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_repeat .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_repeat .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}
		// Pour chaque utilisateur, on supprime les réservations périodiques qu'il a créées et celles dont il est bénéficiaire
		// Table grr_entry_moderate
		$req_suppr_table_entry_moderate = "DELETE FROM ".TABLE_PREFIX."_entry_moderate WHERE create_by = ";
		$first=1; // 1er tour
		foreach($logins as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_entry_moderate .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_entry_moderate .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}

		//les utilisateurs multi-etablissement

		// Pour chaque utilisateur, on supprime les réservations qu'il a créées et celles dont il est bénéficiaire
		// Table grr_entry

		$req_suppr_table_entry_multi = "DELETE FROM ".TABLE_PREFIX."_entry WHERE room_id IN (".$sqlRoomEtab.") AND create_by = ";
		$first=1; // 1er tour
		foreach($loginsMulti as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_entry_multi .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_entry_multi .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}

		// Pour chaque utilisateur, on supprime les réservations périodiques qu'il a créées et celles dont il est bénéficiaire
		// Table grr_repeat
		$req_suppr_table_repeat_multi = "DELETE FROM ".TABLE_PREFIX."_repeat WHERE room_id IN (".$sqlRoomEtab.") AND create_by = ";
		$first=1; // 1er tour
		foreach($loginsMulti as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_repeat_multi .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_repeat_multi .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}
		// Pour chaque utilisateur, on supprime les réservations périodiques qu'il a créées et celles dont il est bénéficiaire
		// Table grr_entry_moderate
		$req_suppr_table_entry_moderate_multi = "DELETE FROM ".TABLE_PREFIX."_entry_moderate WHERE room_id IN (".$sqlRoomEtab.") AND create_by = ";
		$first=1; // 1er tour
		foreach($loginsMulti as $log) {
			if ($first == 1) {
				// pas de OR devant le login
				$req_suppr_table_entry_moderate_multi .= "'$log' OR beneficiaire='$log'";
				$first=0;
			}
			else {
				$req_suppr_table_entry_moderate_multi .= " OR create_by = '$log' OR beneficiaire = '$log' ";
			}
		}
	}

	echo "<hr />\n";
	if ($avec_resa=='y') {
		$nb=0;
		$s = grr_sql_command ($req_suppr_table_entry);
		if ($s != -1) $nb +=$s;
		$s = grr_sql_command (	$req_suppr_table_repeat);
		if ($s != -1) $nb +=$s;
		$s = grr_sql_command (	$req_suppr_table_entry_moderate);
		if ($s != -1) $nb +=$s;
		$s = grr_sql_command ($req_suppr_table_entry_multi);
		if ($s != -1) $nb +=$s;
		$s = grr_sql_command (	$req_suppr_table_repeat_multi);
		if ($s != -1) $nb +=$s;
		$s = grr_sql_command (	$req_suppr_table_entry_moderate_multi);
		if ($s != -1) $nb +=$s;
		echo "<p class='avertissement'>".get_vocab("tables_reservations").get_vocab("deux_points").$nb.get_vocab("entres_supprimees")."</p>\n";
	}


	$nb=0;
	$req_j_mailuser_room = "DELETE FROM ".TABLE_PREFIX."_j_mailuser_room WHERE ";
	$req_j_mailuser_room_empty = true;
	$req_j_user_area = "DELETE FROM ".TABLE_PREFIX."_j_user_area WHERE ";
	$req_j_user_area_empty = true;
	$req_j_user_room = "DELETE FROM ".TABLE_PREFIX."_j_user_room WHERE ";
	$req_j_user_room_empty = true;
	$req_j_useradmin_area = "DELETE FROM ".TABLE_PREFIX."_j_useradmin_area WHERE ";
	$req_j_useradmin_area_empty = true;
	$req_j_useradmin_site = "DELETE FROM ".TABLE_PREFIX."_j_useradmin_site WHERE ";
	$req_j_useradmin_site_empty = true;

	foreach($logins as $log) {
		// Table grr_j_mailuser_room
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_mailuser_room WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_mailuser_room_empty) {
					// pas de OR devant le login
					$req_j_mailuser_room .= " login='".$log."'";
					$req_j_mailuser_room_empty = false;
				} else {
					$req_j_mailuser_room .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_user_area
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_user_area WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_user_area_empty) {
					// pas de OR devant le login
					$req_j_user_area = " login='".$log."'";
					$req_j_user_area_empty = false;
				} else {
					$req_j_user_area .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_user_room
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_user_room WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_user_room_empty) {
					// pas de OR devant le login
					$req_j_user_room = " login='".$log."'" ;
					$req_j_user_room_empty = false;
				} else {
					$req_j_user_room .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_useradmin_area
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_useradmin_area WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_useradmin_area_empty) {
					// pas de OR devant le login
					$req_j_useradmin_area = " login='".$log."'";
					$req_j_useradmin_area_empty = false;
				} else {
					$req_j_useradmin_area .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_useradmin_site
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_useradmin_site WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_useradmin_site_empty) {
					// pas de OR devant le login
					$req_j_useradmin_site = " login='".$log."'";
					${$req_j_useradmin_site_empty} = false;
				} else {
					$req_j_useradmin_site .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
	}
	// Suppression effective
	if ($avec_privileges=="y") {
		if ($req_j_mailuser_room != "") {
			$s = grr_sql_command ($req_j_mailuser_room);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_user_area != "") {
			$s = grr_sql_command ($req_j_user_area);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_user_room != "") {
			$s = grr_sql_command ($req_j_user_room);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_useradmin_area != "") {
			$s = grr_sql_command ($req_j_useradmin_area);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_useradmin_site != "") {
			$s = grr_sql_command ($req_j_useradmin_site);
			if ($s != -1) $nb +=$s;
		}
	}
	//Idem pour les utilisateurs multi
	$req_j_mailuser_room = "DELETE FROM ".TABLE_PREFIX."_j_mailuser_room WHERE id_room IN (".$sqlRoomEtab.") AND ";
	$req_j_mailuser_room_empty = true;
	$req_j_user_area = "DELETE FROM ".TABLE_PREFIX."_j_user_area WHERE id_area IN (".$sqlAreaEtab.") AND ";
	$req_j_user_area_empty = true;
	$req_j_user_room = "DELETE FROM ".TABLE_PREFIX."_j_user_room WHERE id_room IN (".$sqlRoomEtab.") AND  ";
	$req_j_user_room_empty = true;
	$req_j_useradmin_area = "DELETE FROM ".TABLE_PREFIX."_j_useradmin_area WHERE id_area IN (".$sqlAreaEtab.") AND ";
	$req_j_useradmin_area_empty = true;
	$req_j_useradmin_site = "DELETE FROM ".TABLE_PREFIX."_j_useradmin_site WHERE id_area IN (".$sqlSiteEtab.") AND ";
	$req_j_useradmin_site_empty = true;

	foreach($loginsMulti as $log) {
		// Table grr_j_mailuser_room
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_mailuser_room WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_mailuser_room_empty) {
					// pas de OR devant le login
					$req_j_mailuser_room .= " login='".$log."'";
					$req_j_mailuser_room_empty = false;
				} else {
					$req_j_mailuser_room .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_user_area
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_user_area WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_user_area_empty) {
					// pas de OR devant le login
					$req_j_user_area = " login='".$log."'";
					$req_j_user_area_empty = false;
				} else {
					$req_j_user_area .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_user_room
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_user_room WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_user_room_empty) {
					// pas de OR devant le login
					$req_j_user_room = " login='".$log."'" ;
					$req_j_user_room_empty = false;
				} else {
					$req_j_user_room .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_useradmin_area
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_useradmin_area WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_useradmin_area_empty) {
					// pas de OR devant le login
					$req_j_useradmin_area = " login='".$log."'";
					$req_j_useradmin_area_empty = false;
				} else {
					$req_j_useradmin_area .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
		// Table grr_j_useradmin_site
		$test = grr_sql_query1("select count(login) from ".TABLE_PREFIX."_j_useradmin_site WHERE login='".$log."'");
		if ($test >=1 ) {
			if ($avec_privileges=="y") {
				if ($req_j_useradmin_site_empty) {
					// pas de OR devant le login
					$req_j_useradmin_site = " login='".$log."'";
					${$req_j_useradmin_site_empty} = false;
				} else {
					$req_j_useradmin_site .= " OR login = '".$log."'";
				}
			} else {
				$logins_liaison[]=strtolower((string) $log);
			}
		}
	}
	// Suppression effective
	if ($avec_privileges=="y") {
		if ($req_j_mailuser_room != "") {
			$s = grr_sql_command ($req_j_mailuser_room);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_user_area != "") {
			$s = grr_sql_command ($req_j_user_area);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_user_room != "") {
			$s = grr_sql_command ($req_j_user_room);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_useradmin_area != "") {
			$s = grr_sql_command ($req_j_useradmin_area);
			if ($s != -1) $nb +=$s;
		}
		if ($req_j_useradmin_site != "") {
			$s = grr_sql_command ($req_j_useradmin_site);
			if ($s != -1) $nb +=$s;
		}
	}
	echo "<p class='avertissement'>".get_vocab("tables_liaison").get_vocab("deux_points").$nb.get_vocab("entres_supprimees")."</p>\n";

	if ($avec_privileges=="y") {
		// Enfin, suppression des utilisateurs de la source EXT qui ne sont pas administrateur
		$requete_suppr_users_ext = "DELETE FROM ".TABLE_PREFIX."_utilisateurs WHERE source='ext' and statut<>'administrateur'";
		$s = grr_sql_command($requete_suppr_users_ext);
		if ($s == -1) $s = 0;
		echo "<p class='avertissement'>".get_vocab("table_utilisateurs").get_vocab("deux_points").$s.get_vocab("entres_supprimees")."</p>\n";
	} else {
		$n=0;
		foreach($logins as $log) {
			if (!in_array(strtolower((string) $log),$logins_liaison)) {
				grr_sql_command("DELETE FROM ".TABLE_PREFIX."_utilisateurs WHERE login='".$log."'");
				$n++;
			}
		}
		echo "<p class='avertissement'>".get_vocab("table_utilisateurs").get_vocab("deux_points").$n.get_vocab("entres_supprimees")."</p>\n";
	}
}

/**
 * Valide que le site appartient à l'établissement.
 * @param  $idSite l'id du site.
 * @param  $idEtablissement l'id de l'établissement.
 * @return boolean vrai sss le site appartient à l'établissement.
 */
function isSiteInEtablissement($idSite, $idEtablissement){
	$sql = "SELECT 1 FROM ".TABLE_PREFIX."_j_etablissement_site WHERE id_etablissement= $idEtablissement AND id_site = $idSite";
	$res = grr_sql_query($sql);
	return $res && grr_sql_count($res) > 0 ;
}

/**
 * Recherche le premier id de site disponible pour l'établissement.
 * @param $idEtablissement l'id de l'établissement.
 * @return integer le premier id de site disponible dans l'établissement.
 */
function findFirstIdSiteInEtablissement($idEtablissement){
	$sql = "SELECT id_site FROM ".TABLE_PREFIX."_j_etablissement_site WHERE id_etablissement= $idEtablissement";
	$res = grr_sql_query($sql);
	if ($res && grr_sql_count($res) > 0 ){
		$row = grr_sql_row($res,0);
		return $row[0];
	} else {
		return -1;
	}
}

/**
 * Retourne le code établissement à partir de son ID.
 * @param unknown_type $idEtablissement l'id de l'établissement.
 *
 */
function getCodeEtablissementFromId($idEtablissement){
	$sql = "SELECT code FROM ".TABLE_PREFIX."_etablissement WHERE id = '$idEtablissement'";
	$res = grr_sql_query($sql);
	if ($res && grr_sql_count($res) == 1 ){
		$row = grr_sql_row($res,0);
		return $row[0];
	}
}


function getNomCourtEtablissementFromId($idEtablissement){
	$sql = "SELECT shortname FROM ".TABLE_PREFIX."_etablissement WHERE id = '$idEtablissement'";
	$res = grr_sql_query($sql);
	if ($res && grr_sql_count($res) == 1 ){
		$row = grr_sql_row($res,0);
		return $row[0];
	}
}

//Note l a ne appeller que si on est sur qu'on est en multiétablissement.
function getIdEtablissementCourant(){

	if(! isset($_SESSION['current_etablisement'])){
		setEtablissementInSession();
	}
	$codeEtablissementCourant = $_SESSION['current_etablisement'];
	$sql = "SELECT id FROM ".TABLE_PREFIX."_etablissement WHERE code = '$codeEtablissementCourant'";
	$res = grr_sql_query($sql);
	if ($res && grr_sql_count($res) == 1 ){
		$row = grr_sql_row($res,0);
		return $row[0];
	}
}

/**
 * Teste si l'utilisateur qui cherche à reserver une salle à bien accès à l'établissement.
 * @param unknown_type $room
 * @return boolean|number
 */
function auth_user_reserv_room_etab($room){
	if (Settings::get("module_multietablissement") != "Oui") {
		return true;
	} else {
		$user = getUserName();

		$res = grr_sql_query("SELECT 1 FROM ".TABLE_PREFIX."_utilisateurs  WHERE login='".protect_data_sql($user)."' AND statut = 'administrateur' AND etat='actif'");
		if (grr_sql_count($res) > 0) {
			grr_sql_free($res);
			return true;
		}

		//Calcul de l'id de l'établissment
	    $id_etablissement = getIdEtablissementCourant();

		$res = grr_sql_query("SELECT login FROM ".TABLE_PREFIX."_j_useradmin_etablissement j WHERE j.id_etablissement='".protect_data_sql($id_etablissement)."' AND j.login='".protect_data_sql($user)."'");
		if (grr_sql_count($res) > 0) {
			grr_sql_free($res);
			return true;
		}

		$res = grr_sql_query("SELECT login FROM ".TABLE_PREFIX."_j_user_etablissement j WHERE j.id_etablissement='".protect_data_sql($id_etablissement)."' AND j.login='".protect_data_sql($user)."'");
		if (grr_sql_count($res) > 0) {
			grr_sql_free($res);
			return true;
		}

		return false;
	}

}
