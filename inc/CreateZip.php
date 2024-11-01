<?php
/*
* Création des fichiers Zip partiels
*
*/
if( !defined( 'ABSPATH' ) ){ exit(); }

//Listing des fichiers à sauvegarder avec exclusion des dossiers de cache
$content_file = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(ABSPATH . "wp-content/", RecursiveDirectoryIterator::SKIP_DOTS),
        function ($file, $key, $iterator) {
            // Vérifier si le fichier est un dossier et s'il contient "cache"
            return !$file->isDir() || strpos($file->getRealPath(), 'wp-content/cache') === false;
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);				
				
//compteur de fichier
$numFichier = 1;
$taille = 0;

//création du zip
$zip = new ZipArchive();

if ( !file_exists( ABSPATH . "stnSave_final.zip" ) ){
	
	$zip->open(ABSPATH . "stnSave_final.zip", ZipArchive::CREATE);
	
}else{
	
	$zip->open(ABSPATH . "stnSave_final.zip");

};

$memoryFree = stn_save_to_nextcloud::stn_get_memory();

foreach($content_file as $name => $file) {	

	//comparatif du compteur avec fileNumber en cours
	if( $numFichier > $inProgress['fileNumber'] )	{
		
		$filePathName = $file->getPathname();
		
		// Skip les dossiers (ajoutés automatiquement)
		if ( !$file->isDir() ) {

			// Get real and relative path for current file
			$filePath = $file->getRealPath();
			$relativePath = substr($filePath, strlen(ABSPATH . "wp-content/"));
			
			// Ajoute la taille du fichier à la variable
			$taille += filesize($filePath);
			
			if ( $taille < $memoryFree ) {
				
				try {
					// Code potentiellement problématique
					$zip->addFile($filePath, "wordpress/wp-content/" . $relativePath);

				} catch (Exception $e) {
					// Gestion de l'exception

					// Construire le chemin du fichier d'erreur au même endroit que le fichier original
					$cheminFichierErreur = "wordpress/wp-content/" . $relativePath . "_erreur.txt";

					// Ajouter le fichier d'erreur vide au zip avec le chemin correspondant
					$zip->addFromString($cheminFichierErreur, '');
				};	
				
			}else{
				
				$zip->close();

				// Nouvelles données
				$data = array( "fileNumber"  => $numFichier );
				$where = array("finish" => 0 );

				// Execute la requête
				$wpdb->update($wpdb->prefix.'stn_saveInProgress', $data, $where);
						
				//lancement du prochain ZIP
				wp_schedule_single_event(time(),'stn_SaveInProgress');
						
				//fin du script avant relance par cron pour eviter le timeout
				exit();
				
			};
		};
	};
	
	//incrémentation du compteur
	$numFichier++;	

};

$zip->close();

//On change l'état de la sauvegarde
$datafinish = array(
				"etat" => 2,
				"fileNumber"  => 0 
			  );
$wherefinish = array( "finish" => 0 );
$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );

//lancement de la prochaine étape
wp_schedule_single_event(time(),'stn_SaveInProgress');
?>
