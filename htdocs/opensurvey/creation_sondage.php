<?php
//==========================================================================
//
//Université de Strasbourg - Direction Informatique
//Auteur : Guilhem BORGHESI
//Création : Février 2008
//
//borghesi@unistra.fr
//
//Ce logiciel est régi par la licence CeCILL-B soumise au droit français et
//respectant les principes de diffusion des logiciels libres. Vous pouvez
//utiliser, modifier et/ou redistribuer ce programme sous les conditions
//de la licence CeCILL-B telle que diffusée par le CEA, le CNRS et l'INRIA
//sur le site "http://www.cecill.info".
//
//Le fait que vous puissiez accéder à cet en-tête signifie que vous avez
//pris connaissance de la licence CeCILL-B, et que vous en avez accepté les
//termes. Vous pouvez trouver une copie de la licence dans le fichier LICENCE.
//
//==========================================================================
//
//Université de Strasbourg - Direction Informatique
//Author : Guilhem BORGHESI
//Creation : Feb 2008
//
//borghesi@unistra.fr
//
//This software is governed by the CeCILL-B license under French law and
//abiding by the rules of distribution of free software. You can  use,
//modify and/ or redistribute the software under the terms of the CeCILL-B
//license as circulated by CEA, CNRS and INRIA at the following URL
//"http://www.cecill.info".
//
//The fact that you are presently reading this means that you have had
//knowledge of the CeCILL-B license and that you accept its terms. You can
//find a copy of this license in the file LICENSE.
//
//==========================================================================

if (session_id() == "") {
  session_start();
}

include_once('fonctions.php');


/**
 * Generate a random id
 *
 * @return	void
 */
function dol_survey_random($car)
{
	$string = "";
	$chaine = "abcdefghijklmnopqrstuvwxyz123456789";
	srand((double)microtime()*1000000);
	for($i=0; $i<$car; $i++) {
		$string .= $chaine[rand()%strlen($chaine)];
	}
	return $string;
}

/**
 * Add a poll
 *
 * @param	string	$origin		Origin of poll creation
 * @return	void
 */
function ajouter_sondage($origin)
{
	global $conf, $db;

	$sondage=dol_survey_random(16);
	$sondage_admin=$sondage.dol_survey_random(8);

	if ($_SESSION["formatsondage"]=="A"||$_SESSION["formatsondage"]=="A+") {
		//extraction de la date de fin choisie
		if ($_SESSION["champdatefin"]) {
			if ($_SESSION["champdatefin"]>time()+250000) {
				$date_fin=$_SESSION["champdatefin"];
			}
		} else {
			$date_fin=time()+15552000;
		}
	}

	if ($_SESSION["formatsondage"]=="D"||$_SESSION["formatsondage"]=="D+") {
		//Calcul de la date de fin du sondage
		$taille_tableau=sizeof($_SESSION["totalchoixjour"])-1;
		$date_fin=$_SESSION["totalchoixjour"][$taille_tableau]+200000;
	}

	if (is_numeric($date_fin) === false) {
		$date_fin = time()+15552000;
	}

	// Insert survey
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'opensurvey_sondage';
	$sql.= '(id_sondage, commentaires, mail_admin, nom_admin, titre, id_sondage_admin, date_fin, format, mailsonde, canedit, origin, sujet)';
	$sql.= "VALUES ('".$db->escape($sondage)."', '".$db->escape($_SESSION['commentaires'])."', '".$db->escape($_SESSION['adresse'])."', '".$db->escape($_SESSION['nom'])."',";
	$sql.= " '".$db->escape($_SESSION['titre'])."', '".$sondage_admin."', '".$db->idate($date_fin)."', '".$_SESSION['formatsondage']."', '".$db->escape($_SESSION['mailsonde'])."',";
	$sql.= " '".$_SESSION['formatcanedit']."', '".$db->escape($origin)."',";
	$sql.= " '".$db->escape($_SESSION['toutchoix'])."'";
	$sql.= ")";
	dol_syslog($sql);
	$resql=$db->query($sql);

	if ($origin == 'dolibarr') $urlback=dol_buildpath('/opensurvey/adminstuds_preview.php',1).'?sondage='.$sondage_admin;
	else
	{
		// Define $urlwithroot
		$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
		$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
		//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

		$url=$urlwithouturlroot.dol_buildpath('/opensurvey/public/studs.php',1).'?sondage='.$sondage;

		$urlback=$url;

		//var_dump($urlback);exit;
	}

	unset($_SESSION["titre"]);
	unset($_SESSION["nom"]);
	unset($_SESSION["adresse"]);
	unset($_SESSION["commentaires"]);
	unset($_SESSION["canedit"]);
	unset($_SESSION["mailsonde"]);

	header("Location: ".$urlback);
	exit();
}
