<?php

/**
	
	Ce fichier permet de créer un fichier dans le FTP contenant le script de la BDD

**/

// Règle un problème de sécurité indiqué dans le mail
if( !defined( 'ABSPATH' ) ){ exit(); }

//configuration Others APPS
$OA_SQL = array(
	array("NameApp"=>"Wordpress","DB_HOST"=>DB_HOST,"DB_USER"=>DB_USER,"DB_PASSWORD"=>DB_PASSWORD,"DB_NAME"=>DB_NAME,"Prefix"=>$wpdb->prefix),
);

// OTHER APPS
// inclusion d'un fichier OtherApps.php
$otherSqlFiles = glob(PLUGIN_PATH_STN.'inc/OthersApps_*.php');
foreach ($otherSqlFiles as $sqlFile) {
    include $sqlFile;
}

foreach ( $OA_SQL as $thisBDD ) {
	
	// Règle un problème de sécurité indiqué dans le mail
	if( !defined( 'ABSPATH' ) ){ exit(); }

	// Connexion à la base de données
	$mysqli = new mysqli($thisBDD['DB_HOST'], $thisBDD['DB_USER'], $thisBDD['DB_PASSWORD'], $thisBDD['DB_NAME']);

	// Récupération de toutes les tables de la base de données
	$tables = array();
	$showTables = $mysqli->query("SHOW TABLES LIKE '" . $thisBDD['Prefix'] . "%'");

	while ($row = $showTables->fetch_array()) {
		$tables[] = $row[0];
	}

	// Créer et ouvrir le fichier de sauvegarde dans le serveur en mode écriture binaire
	$bddfile = ABSPATH . "stnSave_BDD_".$thisBDD['NameApp'].".sql";
	$handle = fopen($bddfile, "wb");

	fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n/*!40101 SET NAMES utf8mb4 */;\n\n");

	// Pour chaque table
	foreach ($tables as $table) {
		
		// Si la table existe, on la supprime
		fwrite($handle, "DROP TABLE IF EXISTS $table;\n");
		
		// Récupère le script de création de la table
		$createTable = $mysqli->query("SHOW CREATE TABLE $table");
		$row2 = $createTable->fetch_row();
		fwrite($handle, $row2[1] . ";\n\n");
		
		// Prépare la requête SELECT pour récupérer les données de la table
		$selectAllFromTable = $mysqli->prepare("SELECT * FROM $table");
		$selectAllFromTable->execute();
		$result = $selectAllFromTable->get_result();
		
		$count=1;
		
		// Boucle sur les enregistrements
		while ($row = $result->fetch_assoc()) {
			
			$values = array();
			
			// Échappe les valeurs et les formate pour le script SQL
			foreach ($row as $columnName => $value) {
				
				if ( !empty($value)){
					// Déterminez le type de données
					if (preg_match('/[^\x20-\x7E]/', $value)) {
						$values[] = "0x".bin2hex($value);
					}else{
						$values[] = "'" . $mysqli->real_escape_string($value) . "'";
					}
				}
			}
			

			if ($count==1){
				
				fwrite($handle, "INSERT INTO $table (" . implode(", ", array_keys($row)) . ") VALUES\n(" . implode(", ", $values) . ")");

			}else{
				
				fwrite($handle, ",\n(" . implode(", ", $values) . ")");

			}
			$count++;
		}

		fwrite($handle, ";\n\n");
	}

	// Ferme le fichier
	fclose($handle);
};

// Change l'état de la sauvegarde suivant la case bdd only
if( get_option("bdd_only_dlwcloud") == "true"){
	
	$datafinish = array(
		"etat" => 2,
		"fileNumber"  => 0
	);
	
}else{
	
	$datafinish = array(
		"etat" => 1,
		"fileNumber"  => 0
	);
}

$wherefinish = array("finish" => 0);
$wpdb->update($wpdb->prefix.'stn_saveInProgress', $datafinish, $wherefinish);

// Lance la prochaine étape
wp_schedule_single_event(time(), 'stn_SaveInProgress');
?>
