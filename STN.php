<?php 

// Règle un problème de sécurité indiqué dans le mail
if( !defined( 'ABSPATH' ) ){ exit(); }

/** 
 * Plugin Name: STN - Save To Nextcloud
 *  * Plugin URI: httsp://www.davelopweb.fr/
 * Description: Sauvegarde wordpress + Bdd mensuelle vers votre instance Nextcloud
 *  * Version: 2.4.6
 * Author: Dave DELALLEAU
 * Author URI: https://www.davelopweb.fr/#contact
 * Network: True
 * Contributors: Lucas BOUTEVIN SANCE <lucas@davelopweb.fr>
 *
 */

// Dossier principal du plugin
define('PLUGIN_PATH_STN', dirname(plugin_dir_path( __FILE__ )) . "/stn-save-to-nextcloud/");

class stn_save_to_nextcloud{	
	
	// activation
	function activate(){
		
		// Création des tables
		global $wpdb;

		// Va chercher la type de la base
		$charset_collate = $wpdb->get_charset_collate();

		// Nom de la table
		$nameTable = $wpdb->prefix.'stn_saveInProgress';
		// Requête la la création de la table
		$sql = "CREATE TABLE IF NOT EXISTS $nameTable ( 
					id_zip int(11) NOT NULL auto_increment,
					name text DEFAULT NULL,
					fileNumber varchar(100) DEFAULT 0,
					etat int(2) DEFAULT 0,
					uuid text,
					finish int(2) DEFAULT 0, 
					PRIMARY KEY (id_zip)
				)$charset_collate;";		
				
		// Va chercher le doc pour modifier la BDD
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');

		// Applique les requêtes
		dbDelta($sql);
	}
		
	//désactivation
	function desactivate(){

		//suppression des crons
		if ( wp_next_scheduled ('stn_Save') ) {
			
			wp_clear_scheduled_hook('stn_Save');
		
		};	
		
		if ( wp_next_scheduled ('stn_Save',array('next')) ) {
			
			wp_clear_scheduled_hook('stn_Save',array('next'));
		
		};
		
		// Supprime les tables
		global $wpdb;
		$nameTable =$wpdb->prefix.'stn_saveInProgress';	
		$wpdb->query( "DROP TABLE IF EXISTS $nameTable" );
		
		//suppression des options	
		$plugin_options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%dlwcloud'");
		foreach ($plugin_options as $option) {
			delete_option($option->option_name);
		}		
						
		
	}

	//sauvegarde
	function stn_SaveInProgress($NbrRelance = 0){
		
		global $wpdb;

		//stockage de l'état de la sauvegarde
		$table_site=$wpdb->prefix."stn_saveInProgress";
		$rows = $wpdb->get_row("SELECT * from $table_site WHERE finish = 0 ");
		
		//si aucune save n'est en cours, création d'une nouvelle sauvegarde
		if ( empty( $rows->id_zip ) ) {
										
				//création d'un nouvelle sauvegarde
				$nomTable = $wpdb->prefix.'stn_saveInProgress';

				$inProgress = array(
					"fileNumber" => 0,
					"etat" => 0
				);
				
				$wpdb->insert($nomTable, $inProgress);
								
		}else{
			
				$inProgress = array(
					"fileNumber" => $rows->fileNumber ,
					"uuid" => $rows->uuid ,
					"etat" => $rows->etat
				);			
	
		};
				
		//reprise suivant l'état de la sauvegarde
		//switch
		switch ($inProgress['etat']) {  
				          	    
	          	    
			case "0":
			
				//Export de la BDD
				include ('inc/CreateBDD.php');
						
				//fin du script avant relance par cron pour eviter le timeout
				exit();

			
			
			case "1":
			
				//création du Zip
				include ('inc/CreateZip.php');
										
				//fin du script avant relance par cron pour eviter le timeout
				exit();				

			
			case "2":
			
				//Fusion des fichiers à sauvegarder
				include ('inc/FusionZip.php');
						
				//fin du script avant relance par cron pour eviter le timeout
				exit();				

			
			case "3":
			
				// Si la connexion avec NextCloud est correct
				if(stn_save_to_nextcloud::is_NextCloud_good()){
										
					//création de l'uuid sur Nextcloud si inxistant
					if ( empty( $inProgress['uuid'] )){
						
						// Génère un chaine de 16bits aléatoire
						$data = random_bytes(16);
						// Met la version à 0100
						$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
						// Change les 6-7 bits à 10
						$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

						// Prépare le nom du dossier avec un UUID
						$dirChunk = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
						
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
							'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
						   ),
						  'body' => array(),
						  'cookies' => array()
						);

						// Envoi de la requête
						$resCreateFolder = wp_remote_request(get_option('url_dlwcloud').'/remote.php/dav/uploads/' . get_option('login_dlwcloud'). '/' . $dirChunk, $args );
					
						//Stockage de l'UUID en bdd
						$datafinish = array(
										"uuid" => $dirChunk
									  );
						$wherefinish = array( "finish" => 0 );
						$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );	
						
						//Ajout de l'UUID crée dans le tableau inProgress
						$inProgress['uuid'] = $dirChunk;					
						
					};
					
					//Envoi du fichier Zip par morceau sur Nextcloud (Méthode recommandée Nextcloud)
					include ('inc/SendChunk.php');
		
					
				} else {

					//On change l'état de la sauvegarde, rien n'est envoyé sur Nextcloud et on alerte qu'il faut récupérer le zip sur le ftp
					$datafinish = array(
									"finish" => 1
								  );
					$wherefinish = array( "finish" => 0 );
					$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );
						
					// envoi du mail de notification et nettoyage
					$info= "La connexion avec votre instance Nextcloud n'a pas été établie, votre sauvegarde doit être récupérée directement sur votre serveur web (ftp).<br>Veuillez vérifier les informations concernant votre instance Nextcloud et que celle-ci est bien accessible en ligne."; 
					$this->sendInfo("ERREUR",$info);								
					
				};			
			
			exit();
			
			case "4":
				
				// Si la connexion avec NextCloud est correct
				if(stn_save_to_nextcloud::is_NextCloud_good()){
					
					//Reconstruction des chunks sur Nextcloud
					include ('inc/MergeChunk.php');
					
				} else {
					
					
					//relance dans 10 minutes avec un paramètre de relance pour ne relancer que 3 fois avant d'alerter
 					if ( $NbrRelance < 3 ) {
						
						$NbrRelance++;
						wp_schedule_single_event(time() + 600 ,'stn_SaveInProgress', array($NbrRelance));
					
					}else{
						
						//On change l'état de la sauvegarde
						$datafinish = array(
										"finish" => 1
									  );
						$wherefinish = array( "finish" => 0 );
						$wpdb->update( $wpdb->prefix.'stn_saveInProgress' , $datafinish, $wherefinish );
							
						// envoi du mail de notification et nettoyage
						$info= "La connexion avec votre instance Nextcloud a été coupée pendant l'envoi de votre sauvegarde, celle-ci doit être récupérée directement sur votre serveur web (ftp).<br>Veuillez vérifier les informations concernant votre instance Nextcloud et que celle-ci est bien accessible en ligne."; 
						$this->sendInfo("ERREUR",$info);							
						
					}
					

				};				
						
			exit();					

		};
		
	}
	
	// Programmation de la sauvegarde instantanée
	static function stn_Save($next=null){
		
		//clean
		global $wpdb;
		$wpdb->delete( $wpdb->prefix.'stn_saveInProgress', array("finish" => "0" ) );
		$filesInFtp = glob(ABSPATH . "stnSave_*");
		foreach($filesInFtp as $file){ 	unlink($file);	};	
		

		//lancement de la sauvegarde
		if (!wp_next_scheduled ('stn_SaveInProgress')) {
			
			wp_schedule_single_event(time(),'stn_SaveInProgress');
			
		};
		
		//lancement direct
		if ( !$next ){

			//redirection page admin
			if(is_multisite()){
				// Redirige vers la page
				wp_redirect('/wp-admin/network/admin.php?page=stn_nextcloud-sauvegarder&save=now');
			}
			// Si on est sur un site classique
			else{
				// Redirige vers la page
				wp_redirect('/wp-admin/admin.php?page=stn_nextcloud-sauvegarder&save=now');

			}
			
		//lancement programmé
		}else{
			
			stn_save_to_nextcloud::stn_programSave();
						
		};
		
	}
	
	// Retourne le nom de domain du site WordPress
	static function getDomain(){
	
		// Va chercher le nom de domain du WordPress
		$urlparts = parse_url(home_url());	
		// Va chercher le nom de domain du WordPress
		return $urlparts['host'];
	
	}	
	
	// Envoi des infos de sauvegardes par mail
	static function sendInfo($type,$text){
	
		// Objets
		$sujet = $type . ' > A propos de la sauvegarde de '. stn_save_to_nextcloud::getDomain();
		$headers[] = 'From: Save to Nextcloud <savetonextcloud@'. stn_save_to_nextcloud::getDomain().'>';
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		wp_mail( get_option('email_dlwcloud') , $sujet, $text, $headers);
		
	}
	
	// Fonction qui test la connexion avec NextCloud
	static function is_NextCloud_good(){

		// Lance un test de connexion avec NextCloud
		$headers = array(
			// Login et mot de passe rentrée dans les champs
			'Authorization' => 'Basic ' . base64_encode(get_option("login_dlwcloud") . ":" . get_option("pass_dlwcloud")),
		);

		// Lance la requête de test de connexion
		$nextcloud_response = wp_remote_head(get_option('url_dlwcloud').'/remote.php/dav/files', array('headers' => $headers));	
			
		// Si la connexion est incorrect
		if(is_wp_error($nextcloud_response)){
			return false;
		}
		// Si la connexion est correct
		else{
			return true;
		}
	}
	
	// Sauvegarde programmée
	static function stn_programSave(){
		
		if ( wp_next_scheduled ('stn_Save',array('next')) ) {
			
			wp_clear_scheduled_hook('stn_Save',array('next'));
		
		};		
			
		// Si sauvegarde mensuelle
		if(get_option('frequency_dlwcloud') == "month"){
			$timestamp = strtotime('first '.get_option('day_dlwcloud').' of next month '.get_option('hour_dlwcloud').":00");
		}
		// Si sauvegarde hebdomadaire
		else if(get_option('frequency_dlwcloud') == "week"){
			$timestamp = strtotime('next '.get_option('day_dlwcloud').' '.get_option('hour_dlwcloud').":00");
		}
		// Sinon bi-mensuel
		else{
			switch(true){
				
				// Si on a pas dépassé le premier $jour du mois
				case time() < strtotime('first '.get_option('day_dlwcloud').' of this month '.get_option('hour_dlwcloud').":00"):
					$timestamp = strtotime('first '.get_option('day_dlwcloud').' of this month '.get_option('hour_dlwcloud').":00");
					break;
					
				// Si on a pas dépassé le troisième $jour du mois
				case time() < strtotime('third '.get_option('day_dlwcloud').' of this month '.get_option('hour_dlwcloud').":00"):
					$timestamp = strtotime('third '.get_option('day_dlwcloud').' of this month '.get_option('hour_dlwcloud').":00");
					break;
					
				// Sinon
				default:
					// Le premier $jour du mois suivant
					$timestamp = strtotime('first '.get_option('day_dlwcloud').' of next month '.get_option('hour_dlwcloud').":00");
					break;

			}
		}

		wp_schedule_single_event($timestamp,'stn_Save',array('next'));

	}
	
	function stn_get_memory() {
		
		$memoryBefore = memory_get_usage();
		
		//récupération de la taille de mémoire allouée
		$memoryMax = ini_get('memory_limit');

		switch ( substr ($memoryMax, -1) ) {
			case 'M': case 'm': $memoryMax = (int)$memoryMax * 1048576;break;
			case 'K': case 'k': $memoryMax = (int)$memoryMax * 1024;break;
			case 'G': case 'g': $memoryMax = (int)$memoryMax * 1073741824;break;
			default:break;
		};
		
		//calcul de la mémoire restante moins 10% pour le reste du script
		$memoryLimit = ( $memoryMax - $memoryBefore ) * 0.9 ;

		//limit max
		if ( $memoryLimit > 314572800 ){ $memoryLimit = 314572800; };
		
		return (int)$memoryLimit;
	}	

};

//Vue admin
$save_to_nextcloud=new stn_save_to_nextcloud();
register_activation_hook( PLUGIN_PATH_STN . 'STN.php',array($save_to_nextcloud,'activate'));
register_deactivation_hook( PLUGIN_PATH_STN . 'STN.php',array($save_to_nextcloud,'desactivate'));
add_action('stn_Save', array($save_to_nextcloud,'stn_Save'));
add_action('stn_SaveInProgress', array($save_to_nextcloud,'stn_SaveInProgress'));
add_action('admin_post_ProgramSave', array($save_to_nextcloud,'stn_ProgramSave'));
add_action('admin_post_saveNow',array($save_to_nextcloud,'stn_Save'));


//activation des maj auto pour WP
$next_event_timestamp = wp_next_scheduled('stn_SaveInProgress');

// Si on a activer l'option pour gérer les auto update
if ( get_option("auto_update_dlwcloud") == "true" ) {
	
	global $wpdb;

	// date dernière sauvegarde
	$sql = "SELECT name FROM " . $wpdb->prefix . "stn_saveInProgress ORDER BY id_zip DESC LIMIT 1";
	$lastSave = $wpdb->get_results($sql);

	// Check if there are results
	if ($lastSave) {
		// Extract the last saved date from the result
		$date_str = $lastSave[0]->name;
		
        if (!empty($date_str)){
			// Extract the last 14 characters (assuming the date format is consistent)
			$date_substr = substr($date_str, -14, 14);

			// Extract date components
			$year = substr($date_substr, 0, 4);
			$month = substr($date_substr, 4, 2);
			$day = substr($date_substr, 6, 2);
			$hour = substr($date_substr, 8, 2);
			$minute = substr($date_substr, 10, 2);
			$second = substr($date_substr, 12, 2);

			// Calculate the difference between the current date and the last save date
			$last_save_date = new DateTime("$year-$month-$day $hour:$minute:$second");
			$current_date = new DateTime();
			$date_diff = $current_date->diff($last_save_date);
		
			// Check if the difference is less than two days
			if ($date_diff->days < 2) {
				add_filter('auto_update_core', '__return_true');
				add_filter('auto_update_theme', '__return_true');
				add_filter('auto_update_plugin', '__return_true');
				add_filter('auto_update_translation', '__return_true');
			} else {
				add_filter('auto_update_core', '__return_false');
				add_filter('auto_update_theme', '__return_false');
				add_filter('auto_update_plugin', '__return_false');
				add_filter('auto_update_translation', '__return_false');
			}
		}else{
			//sauvegarde en cours, pas d'auto-update
			add_filter('auto_update_core', '__return_false');
			add_filter('auto_update_theme', '__return_false');
			add_filter('auto_update_plugin', '__return_false');
			add_filter('auto_update_translation', '__return_false');			
		}
		
		
	}
};

/*menu administration*/
if (is_admin()){ 
	
	// Si on est sur un multisite
	if(is_multisite()){
		// Ajoute le menu, pas en mode sous-menu
		add_action('network_admin_menu','stn_savetonextcloud_setup_menu');
	}
	// Si on est sur un seul site
	else{
		// Ajoute le menu dans les réglages
		add_action('admin_menu','stn_savetonextcloud_setup_menu');
	}
	
	// Fonction d'ajout du menu 
	function stn_savetonextcloud_setup_menu(){
		// Création du menu
		add_menu_page('Save To Nextcloud', 'Save To Nextcloud', 'manage_options', 'stn_nextcloud');
		// Ajoute un sous-menu "Sauvegarder"
		add_submenu_page('stn_nextcloud', 'Sauvegarder', 'Sauvegarder', 'manage_options', 'stn_nextcloud-sauvegarder', 'stn_savetonextcloud_param'); 

		// La méthode "add_menu_page()" créer aussi un sous-menu "Save To NextCloud" alors on le supprime 
		remove_submenu_page('stn_nextcloud', 'stn_nextcloud');
		 	  
	}

	// Déclaration des settings admin
	add_action( 'admin_init', 'stn_savetonextcloud_settings' );	
	
	function stn_savetonextcloud_settings() {
		
	  register_setting( 'nextcloud-group', 'url_dlwcloud' );
	  register_setting( 'nextcloud-group', 'login_dlwcloud' );
	  register_setting( 'nextcloud-group', 'pass_dlwcloud' );
	  register_setting( 'nextcloud-group', 'frequency_dlwcloud');
	  register_setting( 'nextcloud-group', 'day_dlwcloud' );
	  register_setting( 'nextcloud-group', 'hour_dlwcloud' );
	  register_setting( 'nextcloud-group', 'folder_dlwcloud' );
	  register_setting( 'nextcloud-group', 'email_dlwcloud' );
	  register_setting( 'nextcloud-group', 'nb_save_dlwcloud' );
	  register_setting( 'nextcloud-group', 'auto_update_dlwcloud' );
	  register_setting( 'nextcloud-group', 'bdd_only_dlwcloud' );
	  
	}	
	
	
	function stn_notification() {
		// Vérifier si les paramètres ont été mis à jour avec succès
		if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
			
			$timestamp = wp_next_scheduled('stn_Save','next');
			$date_format = 'j F Y, H:i'; // Format de date personnalisé
			$formatted_date = date_i18n($date_format, $timestamp);			

			$notif = "Votre prochaine sauvegarde est programmée pour le ".$formatted_date.". Si vous ne l'avez pas encore fait, cliquez sur le bouton \"Faire une sauvegarde maintenant\" pour ne pas attendre";
			add_settings_error('stn', 'stn_success', $notif, 'updated-nag');
        
		}else if(isset($_GET['save'])){
			
			$notif = "La sauvegarde est en cours, cela peut prendre quelques minutes. Vous recevrez un mail lorsque celle-ci sera terminée.";
			add_settings_error('stn', 'stn_success', $notif, 'updated-nag');

		};
	}
	//add_action('admin_notices', 'stn_notification');
	add_action('admin_init', 'stn_notification');

	
}

/*
 * API
 * 
 */

function all_user_param() {
	$nameParam = array("frequency_dlwcloud", "day_dlwcloud", "hour_dlwcloud", "nb_save_dlwcloud");
	
	$allParam = array();
	
	foreach($nameParam as $param){
		$allParam[$param] = get_option($param);
	}

	// Vérification d'un update
	$allParam['NeedUpdate'] = 'noneed';

	// Charger les fichiers d'administration si nécessaire (surtout en front-end)
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php'; // Charger le fichier update.php
	}

	// Vérifier si le site est multisite et récupérer les mises à jour appropriées
	$plugin_updates = is_multisite() ? get_site_transient('update_plugins') : get_transient('update_plugins');
	$theme_updates = is_multisite() ? get_site_transient('update_themes') : get_transient('update_themes');
	$core_updates = get_core_updates(); // Fonctionne pour les deux types d'installation

	// Vérifier s'il y a des mises à jour pour les plugins, thèmes ou core
	if ( ! empty($plugin_updates->response) || ! empty($theme_updates->response) || (!empty($core_updates) && isset($core_updates[0]->response) && $core_updates[0]->response === 'upgrade') ) {
		$allParam['NeedUpdate'] = 'need';
	}
	
	return $allParam;
};

add_action( 'rest_api_init', function () {
	// Créer la route "parametre" dans l'API
	register_rest_route("STN", 'param', array(
	'methods' => 'GET',
	'callback' => 'all_user_param',
	'permission_callback' => '__return_true',
	) );
} );
	

function get_all_saves() {

	global $wpdb;
	
	// Déclare le tableau qui va contenir le resultat
	$result = array();

	// Compte le nombre de sauvegarde 
	$sql = "SELECT * FROM " . $wpdb->prefix . "stn_saveInProgress";
	
	// Execute la requête
	$allSaves = $wpdb->get_results($sql);
	
	// Ajoute le nombre de sauvegardes au résultat
	$result["nbSaves"] = count($allSaves);
	
	//récupération de la date de la prochaine suavegarde
	$timestamp = wp_next_scheduled( 'stn_Save',array('next'));

	if ( $timestamp ) {
		
		$date = date( 'Y-m-d H:i:s', $timestamp );
		$result["nextSave"] =  $date;
		
	} else {
		
		$result["nextSave"] =  "vide";
	};	
	
	// Pour chaque sauvegardes
	foreach($allSaves as $save){
		
		// On change le format de la variable
		$save = get_object_vars($save);
		
		$result[] = array(
			"date" => $save["name"],
			"uuid" => $save["uuid"],
			"etat" => $save["etat"],
			"Nombre de fichiers" => $save["fileNumber"],
			"finish" => $save["finish"]
		);
	}

	// Retourne le tableau result
	return $result;
	
};
	
// Créer l'action "get_user_param"
add_action( 'rest_api_init', function () {
		// Créer la route "parametre" dans l'API
		register_rest_route("STN", 'saves', array(
		// Méthode GET
		'methods' => 'GET',
		// Appelle la méthode "all_user_param"
		'callback' => 'get_all_saves',
		'permission_callback' => '__return_true',
	) );
} );



/*page admin*/
function stn_savetonextcloud_param(){?>
	
<div class="wrap">
	<h2>"Save To Nextcloud"</h2>
	<h2 style="margin-left:50px;">Sauvegarder</h2>
	<p>Veuillez renseigner vos paramètres</p>
	<form method="post" action="<?php echo admin_url( 'options.php' );?>">
		<input type="hidden" name="action" value="ProgramSave">
		<?php 
		settings_fields( 'nextcloud-group' );
		do_settings_fields( 'nextcloud-group','dlwcloud' );?>

		<table class="form-table">
			<tr valign="top">
			<th scope="row">URL ( https://cloud.domaine.fr )</th>
			<td><input type="text" name="url_dlwcloud" value="<?php echo esc_url(get_option('url_dlwcloud')); ?>" required/></td>
			</tr>

			<tr valign="top">	
			<th scope="row">Identifiant</th>
			<td><input type="text" name="login_dlwcloud" value="<?php echo esc_html(get_option('login_dlwcloud')); ?>" required/></td>
			</tr>

			<tr valign="top">
			<th scope="row">Mot de passe</th>
		        <td><input type="password" name="pass_dlwcloud" value="<?php echo esc_html(get_option('pass_dlwcloud')); ?>" required/>
		        </td>
			</tr>

			<tr valign="top">
			<th scope="row">Fréquence de sauvegarde</th>
			<td><select name="frequency_dlwcloud">
			<option value="week" <?php if(get_option('frequency_dlwcloud') == "week"){ ?> selected <?php } ?>>hebdomadaire</option>
			<option value="twicemonth" <?php if(get_option('frequency_dlwcloud') == "twicemonth"){ ?> selected <?php } ?>>bimensuel
			</option>
			<option value="month" <?php if(empty(get_option('frequency_dlwcloud')) || get_option('frequency_dlwcloud') == "month"){ 
			?> selected <?php } ?>>mensuel</option>
			</select>
			</td>
			</tr>

			<tr valign="top">
			<th scope="row">Jour de sauvegarde</th>
			<td><select name="day_dlwcloud">
			<option value="Monday" <?php if(empty(get_option('day_dlwcloud')) || get_option('day_dlwcloud') == "Monday"){ ?> selected <?php } ?>>Lundi</option>
			<option value="Tuesday" <?php if(get_option('day_dlwcloud') == "Tuesday"){ ?> selected <?php } ?>>Mardi</option>
			<option value="Wednesday" <?php if(get_option('day_dlwcloud') == "Wednesday"){ ?> selected <?php } ?>>Mercredi</option>
			<option value="Thursday" <?php if(get_option('day_dlwcloud') == "Thursday"){ ?> selected <?php } ?>>Jeudi</option>
			<option value="Friday" <?php if(get_option('day_dlwcloud') == "Friday"){ ?> selected <?php } ?>>Vendredi</option>
			<option value="Saturday" <?php if(get_option('day_dlwcloud') == "Saturday"){ ?> selected <?php } ?>>Samedi</option>
			<option value="Sunday" <?php if(get_option('day_dlwcloud') == "Sunday"){ ?> selected <?php } ?>>Dimanche</option>
			</select>
			</td>
			</tr>

			<tr valign="top">
			<th scope="row">Heure de sauvegarde</th>
			<td><input type="time" name="hour_dlwcloud" value="<?php echo esc_html(get_option('hour_dlwcloud')); ?>" required/></td>
			</tr> 

			<tr valign="top">
			<th scope="row">Dossier de sauvegarde distant ( /dossier/de/destination/ )</th>
			<td><input type="text" name="folder_dlwcloud" value="<?php if (!empty(get_option('folder_dlwcloud'))){echo 
			esc_html(get_option('folder_dlwcloud'));}else{echo "/save_wordpress/";};?>" required/></td>
			</tr>

			<tr valign="top">
			<th scope="row">Email de notification séparés par ;</th>
			<td><input type="text" name="email_dlwcloud" value="<?php if (!empty(get_option('email_dlwcloud'))){echo 
			esc_html(get_option('email_dlwcloud'));}?>" required/></td>
			</tr>    
		</table>
		
		<table>
			<tr valign="top">
			<th scope="row"></th>
			<td>
			<details>
				<summary>Voir les réglages avancés</summary>
				<table class="form-table"> 

					<th scope="row">Nombre de sauvegardes à conserver sur serveur</th>
					<td>
					<select name="nb_save_dlwcloud">
						<option value="1" <?php if(get_option('nb_save_dlwcloud') == "1"){ ?> selected <?php } ?>>1</option>
						<option value="2" <?php if(get_option('nb_save_dlwcloud') == "2"){ ?> selected <?php } ?>>2</option>
						<option value="3" <?php if(empty(get_option('day_dlwcloud')) || get_option('nb_save_dlwcloud') == "3"){ ?> selected <?php } ?>>3</option>
						<option value="4" <?php if(get_option('nb_save_dlwcloud') == "4"){ ?> selected <?php } ?>>4</option>
						<option value="5" <?php if(get_option('nb_save_dlwcloud') == "5"){ ?> selected <?php } ?>>5</option>
						<option value="6" <?php if(get_option('nb_save_dlwcloud') == "6"){ ?> selected <?php } ?>>6</option>
						<option value="7" <?php if(get_option('nb_save_dlwcloud') == "7"){ ?> selected <?php } ?>>7</option>
						<option value="8" <?php if(get_option('nb_save_dlwcloud') == "8"){ ?> selected <?php } ?>>8</option>
						<option value="9" <?php if(get_option('nb_save_dlwcloud') == "9"){ ?> selected <?php } ?>>9</option>
						<option value="10" <?php if(get_option('nb_save_dlwcloud') == "10"){ ?> selected <?php } ?>>10</option>
					</select></td>
					</tr>

					<th scope="row">Voulez-vous activez vos mises à jours automatique seulement après une sauvegarde ?</th>
					<td><select name="auto_update_dlwcloud">
					<option value="true" <?php if(get_option('auto_update_dlwcloud') == "true"){ ?> selected <?php } ?>>Oui
					</option>
					<option value="false" <?php if(empty(get_option('auto_update_dlwcloud')) || 
					get_option('auto_update_dlwcloud') == "false"){ ?> selected <?php } ?>>Non</option>					
					</select>
					</br><p>Pour la bonne marche de votre site, les mises à jours ne devraient être effectuées qu'après une sauvegarde complète.
					Si vous activez cette option, les plugins, le core et les thèmes seront mis à jour automatiquement uniquement après la programmation enregistrée.
					En cas de soucis, vous pourrez donc restaurez la dernière sauvegarde de votre site.</p>
					</tr> 

					<th scope="row">Voulez-vous ne sauvegarder que la/les BDD ?</th>
					<td><select name="bdd_only_dlwcloud">
					<option value="true" <?php if(get_option('bdd_only_dlwcloud') == "true"){ ?> selected <?php } ?>>Oui
					</option>
					<option value="false" <?php if(empty(get_option('bdd_only_dlwcloud')) || 
					get_option('bdd_only_dlwcloud') == "false"){ ?> selected <?php } ?>>Non</option>					
					</select>
					</tr> 
				</table>

			</details>
			</td>
			</tr> 

		</table>
		<?php
		// Si on enregistre de nouvelles option
		if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == true) {
			// Programme la prochaine sauvegarde
			stn_save_to_nextcloud::stn_ProgramSave();
		}
		submit_button("Enregistrer la planification"); ?>
	</form>
	<form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
		<input type="hidden" name="action" value="saveNow">
		<?php submit_button('Faire une sauvegarde maintenant');?>
	</form>		
	<p>Le bouton \"Enregistrer les modifications\" permet d'automatiser le lancement des futurs sauvegardes selon les 
		préférences indiquées : </br> - hebdomadaire : Toute les semaines à l'heure et au jour choisis à partir de la semaine prochaine. 
		</br> - bimensuel : La première et troisième semaine du mois à l'heure et au jour choisis </br> - mensuel : La première semaine du 
		mois à l'heure et au jour choisis à partir du mois prochain</br>Le bouton \"Faire une sauvegarde maintenant\" permet de 
		lancer une sauvegarde sans attendre.</br></p>	
</div>
<?php settings_errors('stn'); };?>
