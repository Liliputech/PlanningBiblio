<?php
/**
Planning Biblio, Version 2.5.3
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2017 Jérôme Combes

Fichier : include/ajax.menus.php
Création : 5 février 2017
Dernière modification : 5 février 2017
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Enregistre la liste des groupes de postes et des étages dans la base de données
Appelé lors du clic sur le bouton "Enregistrer" de la dialog box "Liste des groupes" ou "Lsite des étages" à partir de la fiche poste

TODO: Les étages peuvent être supprimés s'ils sont attachés à des postes supprimés. TODO : permettre la restauration des étages lors de la restauration des postes (via restauration des tableaux). 
TODO: Les groupes peuvent être supprimés s'ils sont attachés à des postes supprimés. TODO : permettre la restauration des groupes lors de la restauration des postes (via restauration des tableaux). 
*/

ini_set('display_errors',0);

session_start();

include "config.php";
$menu = FILTER_INPUT(INPUT_POST, 'menu', FILTER_SANITIZE_STRING);
$option = FILTER_INPUT(INPUT_POST, 'option', FILTER_SANITIZE_STRING);
$tab = $_POST['tab'];

$db=new db();
$db->delete("select_$menu");
foreach($tab as $elem){
  $elements = array("valeur"=>$elem[0],"rang"=>$elem[1]);
  if($option == 'type'){
    $elements['type'] = $elem[2];
  }
  if($option == 'categorie'){
    $elements['categorie'] = $elem[2];
  }
  
  $db=new db();
  $db->insert2("select_$menu", $elements);
}
echo json_encode('ok');
?>