<?php
  if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
  }
  $plugin = plugin::byId('weatherForecast');
  sendVarToJS('eqType', $plugin->getId());
  $eqLogics = eqLogic::byType($plugin->getId());
  $apikeyOwm = trim(config::byKey('apikeyOwm', 'weatherForecast', ''));
  $apikeyWapi = trim(config::byKey('apikeyWapi', 'weatherForecast', ''));
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br/>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br/>
				<span >{{Configuration}}</span>
			</div>
		</div>
		<legend><i class="icon meteo-soleil"></i> {{Mes Météos}}</legend>
		<input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
		<div class="eqLogicThumbnailContainer">
			<?php
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
				echo '<br/>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '</div>';
			}
			?>
		</div>
	</div> <!-- /.eqLogicThumbnailDisplay -->
	
	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-default eqLogicAction btn-sm roundedLeft" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<br/>
				<form class="form-horizontal">
					<fieldset>
            <div class="col-lg-6">
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Nom de l'équipement météo}}</label>
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
                <label class="col-sm-3 control-label">{{Catégorie}}</label>
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
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Source des données}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionnez la source de données}}"></i></sup>
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
                <label class="col-sm-3 control-label" style="line-height: normal">{{Coordonnées GPS}} </label>
                <div class="col-sm-3">
                  <input class="eqLogicAttr" data-l1key="configuration" data-l2key="positionGps" type="text" placeholder="{{Latitude,longitude}}"></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" ></label>
                <div class="col-sm-9">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="fullMobileDisplay" />{{Affichage complet en mobile}}</label>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Template}}
                </label>
                <div class="col-sm-6">
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
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="timezone" readonly/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Précision distance}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{A partir de la localisation utilisée, OpenweatherMap fournit les prévisions pour l'endroit décrit ci-dessus. La distance entre ces 2 localisations est de:}}"></i></sup>
                </label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="distanceLocMF" readonly/>
                </div>
              </div>
-->
            </div>
          </fieldset>
        </form>
      </div><!-- /.tabpanel #eqlogictab-->

      <!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<br/>
				<table id="table_cmd" class="table table-bordered table-condensed">
					<thead>
						<tr>
							<th>{{ID}}</th><th>Logical ID</th><th>{{Nom}}</th><th>{{Options}}</th><th>{{Etat}}</th><th>{{Actions}}</th>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
    </div><!-- /.tabpanel #commandtab-->
		
    </div><!-- /.tab-content -->
  </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<?php include_file('desktop', 'weatherForecast', 'js', 'weatherForecast');?>
<?php include_file('core', 'plugin.template', 'js');?>
