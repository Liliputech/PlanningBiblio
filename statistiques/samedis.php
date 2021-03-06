<?php
/**
Planning Biblio, Version 2.5.4
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2017 Jérôme Combes

Fichier : statistiques/samedis.php
Création : 15 novembre 2013
Dernière modification : 8 février 2017
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Affiche les statistiques par agent sur les samedis travaillés : nombre de samedis travaillés, heures, prime ou récupération

Page appelée par le fichier index.php, accessible par le menu statistiques / Samedis
*/

require_once "class.statistiques.php";
require_once "absences/class.absences.php";
require_once "include/horaires.php";

// Initialisation des variables :
$debut=filter_input(INPUT_POST,"debut",FILTER_SANITIZE_STRING);
$fin=filter_input(INPUT_POST,"fin",FILTER_SANITIZE_STRING);
$post=filter_input_array(INPUT_POST,FILTER_SANITIZE_NUMBER_INT);

$debut=filter_var($debut,FILTER_CALLBACK,array("options"=>"sanitize_dateFr"));
$fin=filter_var($fin,FILTER_CALLBACK,array("options"=>"sanitize_dateFr"));

$post_agents=isset($post['agents'])?$post['agents']:null;
$post_sites=isset($post['selectedSites'])?$post['selectedSites']:null;

$agent_tab=null;
$exists_h19=false;
$exists_h20=false;
$exists_JF=false;
$exists_absences=false;
$exists_samedi=false;

//		--------------		Initialisation  des variables 'debut','fin' et 'agents'		-------------------
if(!$debut and array_key_exists('stat_debut',$_SESSION)) { $debut=$_SESSION['stat_debut']; }
if(!$fin and array_key_exists('stat_fin',$_SESSION)) { $fin=$_SESSION['stat_fin']; }

if(!$debut){ $debut="01/01/".date("Y"); }
if(!$fin){ $fin=date("d/m/Y"); }

$_SESSION['stat_debut']=$debut;
$_SESSION['stat_fin']=$fin;

$debutSQL=dateFr($debut);
$finSQL=dateFr($fin);

// Sélection des samedis entre le début et la fin
$dates=array();
$d=new datePl($debutSQL);
$current=$debutSQL<=$d->dates[5]?$d->dates[5]:date("Y-m-d",strtotime("+1 week",strtotime($d->dates[5])));
while($current<=$finSQL){
  $dates[]=$current;
  $current=date("Y-m-d",strtotime("+1 week",strtotime($current)));
}
$dates=join(",",$dates);

// Les agents
if(!array_key_exists('stat_samedis_agents',$_SESSION)){
  $_SESSION['stat_samedis_agents']=null;
}

$agents=array();
if($post_agents){
  foreach($post_agents as $elem){
    $agents[]=$elem;
  }
}else{
  $agents=$_SESSION['stat_samedis_agents'];
}
$_SESSION['stat_samedis_agents']=$agents;

// Filtre les sites
if(!array_key_exists('stat_samedis_sites',$_SESSION)){
  $_SESSION['stat_samedis_sites']=array();
}

$selectedSites=array();
if($post_sites){
  foreach($post_sites as $elem){
    $selectedSites[]=$elem;
  }
}else{
  $selectedSites=$_SESSION['stat_samedis_sites'];
}

if($config['Multisites-nombre']>1 and empty($selectedSites)){
  for($i=1;$i<=$config['Multisites-nombre'];$i++){
    $selectedSites[]=$i;
  }
}

$_SESSION['stat_samedis_sites']=$selectedSites;

// Filtre les sites dans les requêtes SQL
if($config['Multisites-nombre']>1){
  $sitesSQL="0,".join(",",$selectedSites);
}
else{
  $sitesSQL="0,1";
}

// Recherche des absences dans la table absences
$a=new absences();
$a->valide=true;
$a->fetch("`nom`,`prenom`,`debut`,`fin`",null,null,$debutSQL." 00:00:00",$finSQL." 23:59:59");
$absencesDB=$a->elements;

//		--------------		Récupération de la liste des agents pour le menu déroulant		------------------------
$db=new db();
$db->select2("personnel","*",array("actif"=>"Actif"),"ORDER BY `nom`,`prenom`");
$agents_list=$db->result;

$tab=array();
if(!empty($agents) and $dates){
  //	Recherche du nombre de jours concernés
  $db=new db();
  $db->select2("pl_poste","date",array("date"=>"IN{$dates}", "site"=>"IN{$sitesSQL}"),"GROUP BY `date`;");
  $nbJours=$db->nb;

  //	Recherche des infos dans pl_poste et postes pour tous les agents sélectionnés
  //	On stock le tout dans le tableau $resultat
  $agents_select=join(",",$agents);

  $db=new db();
  $db->selectInnerJoin(array("pl_poste","poste"),array("postes","id"),
    array("debut","fin","date","perso_id","poste","absent"),
    array(array("name"=>"nom","as"=>"poste_nom"),"etage","site"),
    array("date"=>"IN{$dates}", "supprime"=>"<>1", "perso_id"=>"IN{$agents_select}", "site"=>"IN{$sitesSQL}"),
    array("statistiques"=>"1"),
    "ORDER BY `poste_nom`,`etage`");

  $resultat=$db->result;
  
  //	Recherche des infos dans le tableau $resultat (issu de pl_poste et postes) pour chaque agent sélectionné
  foreach($agents as $agent){
    if(array_key_exists($agent,$tab)){
      $heures=$tab[$agent][2];
      $total_absences=$tab[$agent][5];
      $samedi=$tab[$agent][3];
      $dimanche=$tab[$agent][6];
      $h19=$tab[$agent][7];
      $h20=$tab[$agent][8];
      $absences=$tab[$agent][4];
      $feries=$tab[$agent][9];
      $sites=$tab[$agent]["sites"];
    }
    else{
      $heures=0;
      $total_absences=0;
      $samedi=array();
      $dimanche=array();
      $absences=array();
      $h19=array();
      $h20=array();
      $feries=array();
      $sites=array();
      for($i=1;$i<=$config['Multisites-nombre'];$i++){
	$sites[$i]=0;
      }
    }
    $postes=Array();
    if(is_array($resultat)){
      foreach($resultat as $elem){
		// Vérifie à partir de la table absences si l'agent est absent
		// S'il est absent : continue
		foreach($absencesDB as $a){
		  if($elem['perso_id']==$a['perso_id'] and $a['debut']< $elem['date'].' '.$elem['fin'] and $a['fin']> $elem['date']." ".$elem['debut']){
			continue 2;
		  }
		}

	if($agent==$elem['perso_id']){
	  if($elem['absent']!="1"){ // on compte les heures et les samedis pour lesquels l'agent n'est pas absent
	    if(!array_key_exists($elem['date'],$samedi)){ // on stock les dates et la somme des heures faites par date
	      $samedi[$elem['date']][0]=$elem['date'];
	      $samedi[$elem['date']][1]=0;
	    }
	    $samedi[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");

	    if(jour_ferie($elem['date'])){
	      if(!array_key_exists($elem['date'],$feries)){
		$feries[$elem['date']][0]=$elem['date'];
		$feries[$elem['date']][1]=0;
	      }
	      $feries[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	      $exists_JF=true;
	    }

	    foreach($agents_list as $elem2){
	      if($elem2['id']==$agent){	// on créé un tableau avec le nom et le prénom de l'agent.
		$agent_tab=array($agent,$elem2['nom'],$elem2['prenom'],$elem2['recup']);
		break;
	      }
	    }
	    //	On compte les 19-20
	    if($elem['debut']=="19:00:00"){
	      $h19[]=$elem['date'];
	      $exists_h19=true;
	    }
	    //	On compte les 20-22
	    if($elem['debut']=="20:00:00"){
	      $h20[]=$elem['date'];
	      $exists_h20=true;
	    }
	  }
	  else{				// On compte les absences
	    if(!array_key_exists($elem['date'],$absences)){
	      $absences[$elem['date']][0]=$elem['date'];
	      $absences[$elem['date']][1]=0;
	    }
	    $absences[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $total_absences+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $exists_absences=true;
	  }
			    // On met dans tab tous les éléments (infos postes + agents + heures)
	  $tab[$agent]=array($agent_tab,$postes,$heures,$samedi,$absences,$total_absences,$dimanche,$h19,$h20,$feries,"sites"=>$sites);
	}
      }
    }
  }
}
// passage en session du tableau pour le fichier export.php
$_SESSION['stat_tab']=$tab;

//		--------------		Affichage en 2 partie : formulaire à gauche, résultat à droite
echo "<h3>Statistiques sur les samedis travaill&eacute;s</h3>\n";
echo "<table><tr style='vertical-align:top;'><td id='stat-col1'>\n";
//		--------------		Affichage du formulaire permettant de sélectionner les dates et les agents		-------------
echo "<form name='form' action='index.php' method='post'>\n";
echo "<input type='hidden' name='page' value='statistiques/samedis.php' />\n";
echo "<table>\n";
echo "<tr><td><label class='intitule'>Début</label></td>\n";
echo "<td><input type='text' name='debut' value='$debut' class='datepicker'/>\n";
echo "</td></tr>\n";
echo "<tr><td><label class='intitule'>Fin</label></td>\n";
echo "<td><input type='text' name='fin' value='$fin' class='datepicker'/>\n";
echo "</td></tr>\n";
echo "<tr style='vertical-align:top'><td><label class='intitule'>Agents</label></td>\n";
echo "<td><select name='agents[]' multiple='multiple' size='20' onchange='verif_select(\"agents\");' class='ui-widget-content ui-corner-all' >\n";
if(is_array($agents_list)){
  echo "<option value='Tous'>Tous</option>\n";
  foreach($agents_list as $elem){
    if($agents){
      $selected=in_array($elem['id'],$agents)?"selected='selected'":null;
    }
    echo "<option value='{$elem['id']}' $selected>{$elem['nom']} {$elem['prenom']}</option>\n";
  }
}
echo "</select></td></tr>\n";
if($config['Multisites-nombre']>1){
  $nbSites=$config['Multisites-nombre'];
  echo "<tr style='vertical-align:top'><td><label class='intitule'>Sites</label></td>\n";
  echo "<td><select name='selectedSites[]' multiple='multiple' size='".($nbSites+1)."' onchange='verif_select(\"selectedSites\");' class='ui-widget-content ui-corner-all' >\n";
  echo "<option value='Tous'>Tous</option>\n";
  for($i=1;$i<=$nbSites;$i++){
    $selected=in_array($i,$selectedSites)?"selected='selected'":null;
    echo "<option value='$i' $selected>{$config["Multisites-site$i"]}</option>\n";
  }
  echo "</select></td></tr>\n";
}

echo "<tr><td colspan='2' style='text-align:center;padding:10pt;'>\n";
echo "<input type='button' value='Effacer' onclick='location.href=\"index.php?page=statistiques/samedis.php&amp;debut=&amp;fin=&amp;agents=\"' class='ui-button' />\n";
echo "&nbsp;&nbsp;<input type='submit' value='OK' class='ui-button' />\n";
echo "</td></tr>\n";
echo "<tr><td colspan='2'><hr/></td></tr>\n";
echo "<tr><td>Exporter </td>\n";
echo "<td><a href='javascript:export_stat(\"samedis\",\"csv\");'>CSV</a>&nbsp;&nbsp;\n";
echo "<a href='javascript:export_stat(\"samedis\",\"xsl\");'>XLS</a></td></tr>\n";
echo "</table>\n";
echo "</form>\n";

//		--------------------------		2eme partie (2eme colonne)		--------------------------
echo "</td><td>\n";

// 		--------------------------		Affichage du tableau de résultat		--------------------
if($tab){
  echo "<b>Statistiques sur les samedis du $debut au $fin</b><br/>\n";
  echo $nbJours>1?"$nbJours samedis":"$nbJours samedi";
  echo "<br/><br/>\n";
  echo "<table id='tableStatSamedis' class='CJDataTable'>\n";
  echo "<thead>\n";
  echo "<tr>\n";
  echo "<th>Agents</th>\n";
  echo "<th>Prime / Temps</th>\n";
  echo "<th>Nombre</th>\n";
  echo "<th class='dataTableHeureFR'>Heures</th>\n";
  echo "<th class='dataTableDateFR'>Dates</th>\n";

  if($exists_JF){
    echo "<th>J. Feri&eacute;s</th>\n";
  }
  if($exists_h19){
    echo "<th>19-20</th>\n";
  }
  if($exists_h20){
    echo "<th>20-22</th>\n";
  }
  if($exists_absences){
    echo "<th>Absences</th>\n";
  }
  echo "</tr></thead>\n";

  echo "<tbody>\n";
  foreach($tab as $elem){
    // Calcul des moyennes
    $jour=$elem[2]/$nbJours;

    $heures=0;
    foreach($elem[3] as $samedi){
      $heures+=$samedi[1];
    }
    $samedi=count($elem[3])>1?"samedis":"samedi";
    sort($elem[3]);				//	tri les samedis par dates croissantes

    echo "<tr style='vertical-align:top;'>\n";
    //	Affichage du nom des agents dans la 1ère colonne
    echo "<td>{$elem[0][1]} {$elem[0][2]}</td>\n";
    //	Affichage du choix Prime / Temps dans la seconde colonne
    echo "<td>{$elem[0][3]}</td>\n";
    //	Nombre de samedis travaillés
    echo "<td>".count($elem[3])."</td>\n";
    //	Nombre d'heures
    echo "<td>".heure4($heures)."</td>\n";
    //	Affichage du nombre de samedis travaillés et les heures faites par samedi
    echo "<td>";
    foreach($elem[3] as $samedi){			//	Affiche les dates et heures des samedis
      echo dateFr($samedi[0]);			//	date
      echo "&nbsp;:&nbsp;".heure4($samedi[1])."<br/>";	// heures
    }
    echo "</td>\n";

    if($exists_JF){
      echo "<td>";					//	Jours feries
      $ferie=count($elem[9])>1?"J. feri&eacute;s":"J. feri&eacute;";
      echo count($elem[9])." $ferie";		//	nombre de dimanche
      echo "<br/>\n";
      sort($elem[9]);				//	tri les dimanches par dates croissantes
      foreach($elem[9] as $ferie){		// 	Affiche les dates et heures des dimanches
	echo dateFr($ferie[0]);			//	date
	echo "&nbsp;:&nbsp;".heure4($ferie[1])."<br/>";	//	heures
      }
      echo "</td>";	
    }

    if($exists_h19){
      echo "<td>\n";				//	Affichage des 19-20
      if(array_key_exists(0,$elem[7])){
	sort($elem[7]);
	echo "Nb 19-20 : ";
	echo count($elem[7]);
	foreach($elem[7] as $h19){
	  echo "<br/>".dateFr($h19);
	}
      }
      echo "</td>\n";
    }

    if($exists_h20){
      echo "<td>\n";				//	Affichage des 20-22
      if(array_key_exists(0,$elem[8])){
	sort($elem[8]);
	echo "Nb 20-22 : ";
	echo count($elem[8]);
	foreach($elem[8] as $h20){
	  echo "<br/>".dateFr($h20);
	}
      }
      echo "</td>\n";
    }
	    
    if($exists_absences){
      echo "<td>\n";
      if($elem[5]){				//	Affichage du total d'heures d'absences
	echo "Total : ".heure4($elem[5])."<br/>";
      }
      sort($elem[4]);				//	tri les absences par dates croissantes
      foreach($elem[4] as $absences){		//	Affiche les dates et heures des absences
	echo dateFr($absences[0]);		//	date
	echo "&nbsp;:&nbsp;".heure4($absences[1])."<br/>";	// heures
      }
      echo "</td>\n";
    }
    echo "</tr>\n";
  }
  echo "</tbody>\n";
  echo "</table>\n";
}
//		----------------------			Fin d'affichage		----------------------------
echo "</td></tr></table>\n";
?>
