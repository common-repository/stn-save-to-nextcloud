<?php
/*
* Fusion des fichiers à sauvegarder dans un Zip global
*
*/
if( !defined( 'ABSPATH' ) ){ exit(); }

//création du zip
$zipFusion = new ZipArchive();

if ( !file_exists( ABSPATH . "stnSave_final.zip" ) ){
	
	$zipFusion->open(ABSPATH . "stnSave_final.zip", ZipArchive::CREATE);
	
}else{
	
	$zipFusion->open(ABSPATH . "stnSave_final.zip");

};

//ajout des fichiers complémentaires
$sqlFiles = glob(ABSPATH . "*.sql");

// Boucle d'ajout des SQL, utilisé pour OthersApps
foreach ($sqlFiles as $sqlFile) {
	$sqlFilename = basename($sqlFile);
	$zipFusion->addFile($sqlFile, $sqlFilename);
};

//add config file Wordpress seulement si bdd only n'est pas actif
if( get_option("bdd_only_dlwcloud") !== "true"){
	
	$zipFusion->addFile(ABSPATH . "wp-config.php", "wordpress/wp-config.php");
	$zipFusion->addFile(ABSPATH . ".htaccess", "wordpress/.htaccess");

};

$zipFusion->close();

foreach ($sqlFiles as $sqlFile) {
	$sqlFilename = basename($sqlFile);
	unlink($sqlFilename);
};

//On change l'état de la sauvegarde
$datafinish = array(
				"etat" => 3
			  );
$wherefinish = array( "finish" => 0 );
$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );

//lancement de la prochaine étape
wp_schedule_single_event(time(),'stn_SaveInProgress');

?>
