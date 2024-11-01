<?php

/**
	
	La requête permet de rassembler tout les morceaux du tar (chunks) dans un fichiers tar dans NextCloud

**/ 

// Règle un problème de sécurité indiqué dans le mail
if(!defined( 'ABSPATH' )){exit();}

//vérification du dossier de destination
//Récupère les dossiers du chemin dans un array
$tab_dir = explode('/', get_option('folder_dlwcloud'));
$chemin = '';

// Pour chaque dossier du chemin
foreach ($tab_dir as $dir){
	
	if( !empty( $dir ) ){

		// Ajoute le dossier au chemin. Cette variable permet de vérifier pour chaque dossier un par un avec son chemin
		$chemin .= '/' . $dir;

		// Prépare les arguments de la requête
		$args = array(
		  // Ordre de récupérer
		  'method' => 'GET',
		  'timeout' => 30,
		  'redirection' => 5,
		  'httpversion' => '1.0',
		  'blocking' => true,
		  'headers' => array(
			// Identifiant et mdp de NextCloud
			'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
		   ),
		  'body' => array(),
		  'cookies' => array()
		);

		// Envoi de la requête
		$resGetUserDestination = wp_remote_request( get_option('url_dlwcloud').'/remote.php/dav/files/'.get_option('login_dlwcloud').$chemin, $args );

		// Si le chemin de l'utilisateur n'existe pas 
		if($resGetUserDestination["response"]["code"] == 404){

			// Prépare les arguments de la requête
			$args = array(
			  // Ordre de créer un dossier
			  'method' => 'MKCOL',
			  'timeout' => 30,
			  'redirection' => 5,
			  'httpversion' => '1.0',
			  'blocking' => true,
			  'headers' => array(
				// Identifiant et mdp de NextCloud
				'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . 
				get_option("pass_dlwcloud")),
			   ),
			  'body' => array(),
			  'cookies' => array()
			);

			// Envoi de la requête
			$resCreateDestination = wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/files/'.
			get_option('login_dlwcloud').$chemin, $args );
				
		};
		
	};
	
};


// Prépare le headers
$finalName = "stn_save_" . stn_save_to_nextcloud::getDomain() . "_" . date('YmdHis') ;
$destination = get_option('url_dlwcloud').'/remote.php/dav/files/'.get_option('login_dlwcloud').get_option('folder_dlwcloud') . $finalName . ".zip";

$headers = array(
 	'content-type'  => 'application/binary',
  	// Login et mot de passe rentrée dans les champs
  	'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
  	// Va être envoyer vers la destination
  	'Destination'   => $destination,
);

// Préapre les arguments 
$args = array(
  // Requête qui change d'emplacement 
  'method' => 'MOVE',
  'timeout' => 30,
  'redirection' => 5,
  'httpversion' => '1.0',
  'blocking' => true,
  'headers' => $headers,
  'body' => array(),
  'cookies' => array()
);

// Envoi la requête (Rassemble les chunks dans 'Destination')
wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/uploads/' . get_option('login_dlwcloud'). '/' . $inProgress['uuid'] . "/.file", $args );	

// Change l'état de la sauvegarde suivant la case bdd only
$datafinish = array(
				"name" => $finalName,
				"finish" => 1
			  );
$wherefinish = array("finish" => 0);
$wpdb->update($wpdb->prefix.'stn_saveInProgress', $datafinish, $wherefinish);					  

$info= "La sauvegarde de votre site est terminée et enregistrée sur votre espace nextcloud !"; 
$this->sendInfo("SUCCES",$info);

//nettoyage des suavegardes
include ('CleanSave.php');

?>
