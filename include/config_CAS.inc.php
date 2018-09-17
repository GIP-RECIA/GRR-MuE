<?php
/*
Ce fichier permet de configurer la r�cup�ration dans GRR
d'attributs LDAP des utilisateurs envoy�s par le serveur CAS
Lire attentivement la documentation avant de modifier ce fichier
*/

/*
On ne peut pas invoquer directement la fonction "phpCAS::getAttribute()"
car elle n'est pas impl�ment�e dans "CAS/CAS.php"
Dans cette biblioth�que, il n'y a que "phpCAS::getAttributes()" qui soit d�finie, contrairement � ce qui se passe avec "CAS/CAS/client.php".
*/
function getAttribute($key)
{
    global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
    if (!is_object($PHPCAS_CLIENT)) {
        phpCAS:: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
    }
    if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
        phpCAS:: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
    }
    if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
        phpCAS:: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
    }
    return $PHPCAS_CLIENT->getAttribute($key);
}

/*
 R�cup�ration des diff�rents attributs de l'annuaire LDAP envoy�s par le serveur CAS
 Explication de la premi�re ligne :
 phpCAS::getAttribute('user_nom_ldap') est la variable envoy� par CAS
 La fonction recuperer_nom() permet de traiter cette variable pour r�cup�rer la valeur utilis�e dans GRR
 Le r�sultat est alors stock� dans $user_nom

 Il en va de m�me des autres variables ci-dessous
 Vous pouvez personnaliser les fonctions de traitements des attributs LDAP envoy�s par le serveur CAS
 en modifiant le code des fonctions ci-dessous.
*/
$user_nom = recuperer_nom(phpCAS::getAttribute('sn'));
$user_prenom = recuperer_prenom(phpCAS::getAttribute('givenName'));
$user_mail = recuperer_mail(phpCAS::getAttribute('mail'));
$user_code_etablissement = recuperer_code_etablissement(phpCAS::getAttribute('ESCOUAICourant'));
$user_language = recuperer_language(phpCAS::getAttribute('preferredLanguage'));
$user_code_fonction = recuperer_code_fonction(phpCAS::getAttribute('ENTPersonFonctions'));
$user_libelle_fonction = recuperer_libelle_fonction(phpCAS::getAttribute('ENTPersonFonctions'));
$user_default_style = "default";

/*
 Fonction permettant de r�cup�rer le nom dans le champ LDAP $user_nom
*/
function recuperer_nom($user_nom)
{
    # Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP
    return $user_nom;
}

/*
 Fonction permettant de r�cup�rer le pr�nom dans le champ LDAP $user_prenom
*/
function recuperer_prenom($user_prenom)
{
//Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP
    return $user_prenom;
}

/*
 Fonction permettant de r�cup�rer la langue � partir de l'attribut $user_language de l'annuaire LDAP
 Exemple (Cas de l'ENT Esco-Portail) :
 -------------------------------------
 function recuperer_language($user_language) {
	$res = substr($user_language, 0, 2);
	if (strcasecmp($res, "fr") == 0) {
		$lang = "fr";
	}
	else if (strcasecmp($res, "en") == 0) {
		$lang = "en";
	}
	else if (strcasecmp($res, "de") == 0) {
		$lang = "de";
	}
	else if (strcasecmp($res, "it") == 0) {
		$lang = "it";
	}
	else if (strcasecmp($res, "es") == 0) {
		$lang = "es";
	}
	else {
		$lang = "fr";
	}
	return $lang;
 }
*/
function recuperer_language($user_language)
{
//Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP (voir exemple ci-dessous)
    return $user_language;
}

/*
 Fonction permettant de r�cup�rer le code de la fonction dans le champ LDAP $user_code_fonction
 Exemple (Cas de l'ENT Esco-Portail) :
 -------------------------------------
 R�cup�ration du code de la fonction dans le champ LDAP multivalu� ENTPersonFonctions

 function recuperer_code_fonction($user_code_fonction) {
	$tab = explode ("$", $user_code_fonction);
	  return $tab[1];
 }
*/
function recuperer_code_fonction($user_code_fonction)
{
//Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP (voir exemple ci-dessous)
# Remplacement par la r�cup�ration du code fonction depuis le ticket CAS
#	return $user_code_fonction;
    $tab = explode("$", $user_code_fonction);
    return $tab[1];
}


/*
 Fonction permettant de r�cup�rer le libell� de la fonction dans le champ LDAP $user_libelle_fonction
 Exemple (Cas de l'ENT Esco-Portail) :
 -------------------------------------
 R�cup�ration du libell� de la fonction dans le champ LDAP multivalu� ENTPersonFonctions

 function recuperer_libelle_fonction($user_libelle_fonction) {
	$tab = explode ("$", $user_libelle_fonction);
	  return $tab[2];
 }
*/

function recuperer_libelle_fonction($user_libelle_fonction)
{
//Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP (voir exemple ci-dessous)
# Remplacement par la r�cup�ration du code fonction depuis le ticket CAS
#	return $user_libelle_fonction;
    $tab = explode("$", $user_libelle_fonction);
    return $tab[2];
}

/*
 Fonction permettant de r�cup�rer le mail dans le champ LDAP $user_mail
*/
function recuperer_mail($user_mail)
{
//Le cas �ch�ant, remplacez la ligne suivante par le code PHP ad�quat, correspondant � votre annuaire LDAP
    return $user_mail;
}


/*
Fonction permettant de r�cup�rer le code etablissement dans le champ LDAP $user_etablissement
*/
function recuperer_code_etablissement($user_etablissement)
{

    $etablissement_principal = recuperer_code_etablissement_principal($user_etablissement);
    if ($etablissement_principal) {
        return $etablissement_principal;
    }

    return $user_etablissement;
}


// MODIFICATION RECIA | DEBUT | 2012-04-17

// INCLUDES
if (file_exists("connect.inc.php")) {
    include_once "connect.inc.php";
}

// FUNCTIONS
// Ajout d'une methode permettant de verifier si un etablissement 
// est considere comme secondaire.
// Dans ce cas, l'UAI de l'etablissement principal est retourne.

function recuperer_code_etablissement_principal($etablissement)
{
    global $dbHost, $dbUser, $dbPass, $dbDb;

    $db = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbDb);
    // Connection MySQL
    if (!$db) {
        return null;
    }

    // Recuperation (eventuelle) de l'UAI principal
    $query = "SELECT code_etablissement_principal";
    $query .= " FROM " . TABLE_PREFIX . "_etablissement_regroupement";
    $query .= " WHERE code_etablissement_secondaire = '" . $etablissement . "'";
    $result = @mysqli_query($db, $query);
    $row = @mysqli_fetch_assoc($result);

    if ($row) {
        return $row["code_etablissement_principal"];
    }
    return null;
}

// MODIFICATION RECIA | FIN
?>
