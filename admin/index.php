<?php
/**
Planning Biblio, Version 2.1
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2017 Jérôme Combes

Fichier : admin/index.php
Création : mai 2011
Dernière modification : 22 janvier 2016
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Affiche les liens vers les différentes pages de configurations (activités, agents, postes, ...)

Page appelée par la page index.php
*/

// Contrôle si ce script est appelé directement, dans ce cas, affiche Accès Refusé et quitte
if(__FILE__ == $_SERVER['SCRIPT_FILENAME']){
  include_once "../include/accessDenied.php";
  exit;
}

echo "<h3>Administration</h3>\n";
echo "<ul>\n";
if(in_array(23,$droits))
  echo "<li><a href='index.php?page=infos/index.php'>Informations</a></li>\n";
if(in_array(5,$droits))
  echo "<li><a href='index.php?page=activites/index.php'>Les activités</a></li>\n";
if(in_array(4,$droits))
  echo "<li><a href='index.php?page=personnel/index.php'>Les agents</a></li>\n";
if(in_array(5,$droits))
  echo "<li><a href='index.php?page=postes/index.php'>Les postes</a></li>\n";
if(in_array(12,$droits))
  echo "<li><a href='index.php?page=planning/modeles/index.php'>Les modèles</a></li>\n";
if(in_array(22,$droits))
  echo "<li><a href='index.php?page=planning/postes_cfg/index.php'>Les tableaux</a></li>\n";
if(in_array(24,$droits))
  echo "<li><a href='index.php?page=admin/feries.php'>Jours feri&eacute;s</a></li>\n";
if(in_array(24,$droits) and $config['PlanningHebdo'])
  echo "<li><a href='index.php?page=planningHebdo/index.php'>Plannings de pr&eacute;sence</a></li>\n";
if(in_array(20,$droits))
  echo "<li><a href='index.php?page=admin/config.php'>Configuration</a></li>\n";
echo "</ul>\n";
?>