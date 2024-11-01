<?php 
	
/**
	
	Nettoyage des sauvegardes obsolètes

**/

// Règle un problème de sécurité indiqué dans le mail
if( !defined( 'ABSPATH' ) ){ exit(); }

//selection des noms des sauvegardes à supprimer
$sql = "SELECT name,id_zip,uuid FROM " . $wpdb->prefix . "stn_saveInProgress WHERE finish = '1' ORDER BY id_zip DESC LIMIT ".get_option("nb_save_dlwcloud").",10";
$result = $wpdb->get_results($sql);

foreach ($result as $save){

		// Prépare le headers
		$headers = array(
		  // Identifiant et mdp de NextCloud
		  'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
		);
		
		// Prépare les arguments
		$args = array(
		  // Requête de suppression
		  'method' => 'DELETE',
		  'timeout' => 30,
		  'redirection' => 5,
		  'httpversion' => '1.0',
		  'blocking' => true,
		  'headers' => $headers,
		  'body' => array(),
		  'cookies' => array()
		);
		
		//suppression du fichier zip Nextcloud si existant
		wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/files/'.get_option('login_dlwcloud').get_option('folder_dlwcloud') . $save->name . ".zip", $args );
	
		//suppression du dossier chunk sur Nextcloud si existant
		wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/uploads/' . get_option('login_dlwcloud').'/' . $save->uuid, $args );		
			
		//suppression BDD
		$where = array("id_zip" => $save->id_zip);
		$wpdb->delete($wpdb->prefix.'stn_saveInProgress', $where);
	
};

//nettoyage des fichiers résiduels si existant
$filesInFtp = glob(ABSPATH . "stnSave_*");
foreach($filesInFtp as $file){ 	unlink($file);	};

?>
