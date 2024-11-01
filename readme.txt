=== STN- SAVE TO NEXTCLOUD ===
Contributors: davelopweb.fr
Tags: nextcloud, save, webdav, davelopweb, stn
Requires at least: 7.0
Requires PHP: 7.3
Tested up to: 6.5
Stable tag: 2.4.6
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Ce plugin est un outil simple et efficace pour sauvegarder votre site WordPress et sa base de données directement sur votre compte NextCloud. Avec celui-ci, vous pouvez définir les paramètres pour créer des copies régulières de votre site, incluant la fréquence et le moment de l’opération.

Une fois que vous avez configuré les paramètres, le plugin fonctionnera automatiquement en arrière-plan pour créer des sauvegardes de votre site et de sa base de données selon votre planning. Les sauvegardes sont stockées directement sur votre compte NextCloud, vous permettant ainsi d’y accéder de n’importe où, à tout moment.

Ce plugin est facile à installer et à configurer, et il offre une solution de sauvegarde complète pour protéger votre site contre la perte de données en cas de problème technique ou de piratage. En choisissant ce plugin, vous pouvez avoir l’esprit tranquille en sachant que votre site est en sécurité.

ATTENTION : la restauration automatique des sauvegardes n'est pas encore possible, il faudra le faire manuellement en remplaçant les fichiers sur l'hébergement et en restaurant la BDD.

== Installation ==

Installez STN depuis la store Wordpress et activez-le
Configurer les paramètres dans le menu 'Save To Nextcloud' -> 'Sauvegarder'

Le bouton "Enregistrer la planification" programme votre prochaine sauvegarde.
Le bouton "Faire une sauvegarde maintenant" lance la sauvegarde

Options avancées :
- Définissez le nombre de sauvegarde à conserver (jusqu'à 10)
- Activer le blocage des mises à jours automatiques : le core, les plugins et les thèmes étant tagués pour être mis à jour automatiquement, ne le seront qu'après une sauvegarde programmée afin d'aviter que celle-ce ne soit polluée par un plugin defaillant. Les mises à jours manuelles sont toujours possibles. 

Plusieurs étapes sont necessaires et chacune est découpée suivant la taille de mémoire maximun alloué par votre hébergement, le procéssus peut donc être long (quelques heures pour un site de 10Go et une taille mémoire de 64Mo) :

1- Extraction BDD
2- Zip du dossier wp-content
3- Ajout des fichier .htaccess, wp-config et BDD au Zip
4- Envoi du zip suivant la méthode Chunck sur l'espace Nextcloud
5- Reconstitution des chunks et MOVE sur le dossier final Nextcloud
6- Nettoyage des fichiers résiduels sur l'hébergement et suppression des sauvegardes obsolètes sur Nextcloud


API, Deux point d'accès sont disponibles :
/wp-json/STN/saves pour récupérer le nombre de sauvegardes actives et leur nom, ainsi que la prochaine sauvegarde
/wp-json/STN/param pour récupérer la fréquence de sauvegarde, le jour, l'heure et le nombre à conserver


Désactiver le plugin : supprime les programmations
Supprimer le plugin : supprime les tables et les options

== Changelog ==

= 2.4.6 =

Nettoyage des anciennes sauvegardes

= 2.4.5 =

Optimisation de MAJ auto

= 2.4.4 =

Clean Save lors du lancement manuel

= 2.4.3 =

Debug BDD only

= 2.4.1 =

Possibilité de sauvegarder d'autres app en meme temps en modifiant le fichier OtherApp.php

= 2.3.4 =

Correction de bugs

= 2.3.3 =

Ajout 'permission_callback' en public les API

= 2.3.1 =

Ajout NeedUpdate dans l'API param

= 2.3.1 =

Calcul la mémoire disponible pour les différentes étapes de la sauvegarde

= 2.3 =

Ajout détection update et suppression

= 2.1.0 -> 2.2.4 =

Correctifs Bugs

= 1.0.0 -> 2.1.0 =

Mise en conformité du code pour le store Wordpress

== Links ==

[Knowledge Base](https://davelopweb.fr/)
