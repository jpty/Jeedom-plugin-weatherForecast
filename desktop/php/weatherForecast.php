<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('weatherForecast');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
$apikeyOwm = trim(config::byKey('apikeyOwm', 'weatherForecast', ''));
$apikeyWapi = trim(config::byKey('apikeyWapi', 'weatherForecast', ''));
?>

<div class="row row-overflow">
  <!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
    <!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes équipements Météos}}</legend>
			<?php
    if (count($eqLogics) == 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
    } else {
      // Champ de recherche
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn">';
      echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
      echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
      echo '</div>';
      echo '</div>';
      // Liste des équipements du plugin
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor ' .$opacity .'" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '<img src="' . $plugin->getPathImgIcon() . '">';
        echo '<br>';
        echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '<span class="hiddenAsCard displayTableRight hidden">';
        echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
        echo '</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>
	</div> <!-- /.eqLogicThumbnailDisplay -->
	
  <!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
    <!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
        <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
        </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
        </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
        </a>
			</span>
		</div>
    <!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
      <!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <!-- Partie gauche de l'onglet "Equipements" -->
        <!-- Paramètres généraux et spécifiques de l'équipement -->
				<form class="form-horizontal">
					<fieldset>
            <div class="col-lg-6">
              <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                <div class="col-sm-4">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement météo}}"/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                <div class="col-sm-4">
                  <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                    <option value="">{{Aucun}}</option>
                    <?php
                    $options = '';
                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                      $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                    }
                    echo $options;
                    ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                <div class="col-sm-6">
                  <?php
                  foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                    echo '</label>';
                  }
                  ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label"></label>
                <div class="col-sm-9">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                </div>
              </div>

              <legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Source des données}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{Sélection de la source de données météo}}"></i></sup>
                </label>
                <div class="col-sm-4">
                  <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="datasource">
                    <?php
                      $nbkey= 0;
                      if($apikeyOwm != '') {
                        echo '<option value="openweathermap">{{OpenWeather}}</option>';
                        $nbkey++;
                      }
                      if($apikeyWapi != '') {
                        echo '<option value="weatherapi">{{Weather API}}</option>';
                        $nbkey++;
                      }
                      if($nbkey == 0) {
                        echo '<option value="">{{Aucune clé API renseignée dans la configuration du plugin}}</option>';
                      }
                    ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" style="line-height: normal">{{Coordonnées}} </label>
                <div class="col-sm-4">
                  <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="positionGps" type="text" placeholder="{{Latitude,longitude}}">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Département vigilances FR}}</label>
                <div class="col-sm-4">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="numDeptFr"/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Fuseau horaire}}
                  <sup><i class="fas fa-question-circle" tooltip="{{Sélection du fuseau horaire pour l'affichage de la météo}}"></i></sup>
                </label>
                <div class="col-sm-4">
                  <div class="input-group">
                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="timezone">
<?php
                      $timezone_identifiers = DateTimeZone::listIdentifiers();
                      $nb = count($timezone_identifiers);
                      $group = '';
                      for ($i=0; $i < $nb; $i++) {
                        $tz = $timezone_identifiers[$i];
                        $lieu = date_create('now', timezone_open($tz));
                        $decal = date_offset_get($lieu);
                        $h = sprintf('%+d',intval($decal/3600));
                        $min = sprintf('%02d',($decal % 3600)/60);
                        $pos= strpos($tz,'/');
                        $newgroup = substr($tz,0,$pos);
                        if($group != $newgroup) {
                          if($group != '') echo "</optrgoup>";
                          echo "<optgroup label=\"--- $newgroup ---\">";
                          $group = $newgroup;
                        }
                        echo "<option value=\"$tz\">$tz (UTC$h:$min)</option>";
                      }
?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" ></label>
                <div class="col-sm-4">
                  <label class="checkbox-inline">
                    <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="fullMobileDisplay" />{{Affichage complet en mobile}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Cocher la case pour afficher les informations météo des jours suivants sur les appareils mobiles}}"></i></sup>
</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Template}}
                </label>
                <div class="col-sm-4">
                  <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="templateWeatherForecast">
                    <option value="plugin">{{Template du plugin Icônes}}</option>
                    <option value="pluginImg">{{Template du plugin Images}}</option>
                    <option value="none">{{Pas de template}}</option>
  <?php
                    $files = array();
                    if ($dh = opendir(__DIR__ .'/../../core/template/dashboard')) {
                      while (($file = readdir($dh)) !== false) {
                        if(substr($file,-5) == '.html') {
                          if($file == 'custom.weatherForecast.html')
                            $files[] = array('name' => 'Template custom Icônes', 'fileName' => $file);
                          elseif(substr($file,0,23) == 'custom.weatherForecast.') {
                            $files[] = array('name' => substr($file,23,-5).' (custom Icônes)', 'fileName' => $file);
                          }
                          elseif($file == 'custom.weatherForecastIMG.html')
                            $files[] = array('name' => 'Template custom Images', 'fileName' => $file);
                          elseif(substr($file,0,26) == 'custom.weatherForecastIMG.')
                            $files[] = array('name' => substr($file,26,-5).' (custom Images)', 'fileName' => $file);
                          // else $files[] = array('name' => 'Out', 'fileName' => $file);
                        }
                      }
                      closedir($dh);
                    }
                    if(count($files)) {
                      sort($files);
                      foreach($files as $file) {
                        echo '<option value="' .$file['fileName'] .'">' .$file['name'] .'</option>';
                      }
                    }
  ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Partie droite de l'onglet "Équipement" -->
            <div class="col-lg-6">
              <legend><i class="fas fa-info"></i> {{Données de la localisation}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Latitude}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="lat" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Longitude}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="lon" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Ville}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="ville" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Pays}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="country" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Infos}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="otherInfo" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Refresh minute}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="refreshMinute" readonly/>
                </div>
              </div>
<!--
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Décalage horaire}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="timezoneWF" readonly/>
                </div>
              </div>
-->
            </div>
          </fieldset>
        </form>
      </div><!-- /.tabpanel #eqlogictab-->

      <!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<br>
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:50px;">{{LogicalId}}</th>
                <th style="min-width:100px;">{{Nom}}</th>
                <th>{{Paramètres}}</th>
                <th>{{Etat}}</th>
                <th style="min-width:80px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
    </div><!-- /.tabpanel #commandtab-->
		
    </div><!-- /.tab-content -->
  </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'weatherForecast', 'js', 'weatherForecast');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>
