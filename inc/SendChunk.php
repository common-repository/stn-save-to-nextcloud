<?php 
	
/**
	Ce fichier permet d'envoyer les morceaux de fichiers (chunks) à NextCloud
**/

// Règle un problème de sécurité indiqué dans le mail
if( !defined( 'ABSPATH' ) ){ exit(); }

//Ouverture du zip
$handle = fopen(ABSPATH . "stnSave_final.zip", 'rb');

// avancement fread déjà traité
fseek( $handle , intval( $inProgress['fileNumber'] ) );

$memoryFree = stn_save_to_nextcloud::stn_get_memory();

$thisChunk = fread($handle, ( $memoryFree ) );

// Tant que le fichier n'est pas entierement lu
if ( !empty( $thisChunk ) ){

	// Prépare le headers
	$headers = array(
	  'content-type'  => 'application/binary',
	  // Identifiant et mdp de NextCloud
	  'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
	);
				
	// Prépare les arguments
	$args = array(
	  // Requête de création
	  'method' => 'PUT',
	  'timeout' => 30,
	  'redirection' => 5,
	  'httpversion' => '1.0',
	  'blocking' => true,
	  'headers' => $headers,
	  'body' => $thisChunk,
	  'cookies' => array(),
	);

	// Envoi la requête (créer le morceau chunk dans le dossier uuid)
	$firstBit = str_pad( $inProgress['fileNumber'], 15, '0', STR_PAD_LEFT );
	$lastBit =  str_pad( ( $inProgress['fileNumber'] + $memoryFree ), 15, '0', STR_PAD_LEFT);
	
	$resSendChunk = wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/uploads/' . get_option('login_dlwcloud'). '/' . $inProgress['uuid'] ."/".$firstBit."-".$lastBit, $args);
	
	fclose($handle);

	// Nouvelles données
	$data = array( "fileNumber"  => ( $inProgress['fileNumber'] + $memoryFree ) );
	$where = array("finish" => 0 );
	$wpdb->update($wpdb->prefix.'stn_saveInProgress', $data, $where);	
					
	//on relance le cron et on sort
	wp_schedule_single_event(time(),'stn_SaveInProgress');
		
	exit();
		
};

fclose($handle);

//On change l'état de la sauvegarde
$datafinish = array(
				"etat" => 4,
				"fileNumber"  => 0
			  );
$wherefinish = array( "finish" => 0 );
$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );

//lancement de la prochaine étape
wp_schedule_single_event(time(),'stn_SaveInProgress');

?>
