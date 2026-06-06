<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
	<fieldset>
    <div class="form-group">
      <label class="col-md-3 control-label">{{Clef API OpenWeatherMap}}</label>
      <div class="col-lg-4">
        <input class="configKey form-control" data-l1key="apikeyOwm" />
      </div>
      <a href="https://home.openweathermap.org/api_keys" target="_blank">Site Web OpenWeather <sup><i class="fas fa-external-link-alt tooltips"></i></sup></a> <sup><i class="fas fa-question-circle tooltips" title="{{La clé API est à récupérer dans votre compte sur le site openweathermap.org<br/>Aprés génération d'une clé API, son activation n'est pas immédiate. Veuillez patienter.}}"></i></sup>
    </div>
    <div class="form-group">
      <label class="col-md-3 control-label">{{Clef API WeatherApi}}</label>
      <div class="col-lg-4">
        <input class="configKey form-control" data-l1key="apikeyWapi" />
      </div>
      <a href="https://www.weatherapi.com/my/" target="_blank">Site Web WeatherAPI <sup><i class="fas fa-external-link-alt tooltips"></i></sup></a> <sup><i class="fas fa-question-circle tooltips" title="{{La clé API est à récupérer dans votre compte sur le site WeatherApi}}"></i></sup>
    </div>
    <div class="form-group">
      <label class="col-md-3 control-label">{{APPLICATION_ID Météo France}}
        <a href="https://portail-api.meteofrance.fr/web/fr/faq" target="_blank">Site Web MF <sup><i class="fas fa-external-link-alt tooltips"></i></sup></a>
      </label>
      <div class="col-md-7">
        <input type="text" class="configKey form-control" data-l1key="credentialApiMeteoFrance"/>
      </div>
    </div>
    <div class="form-group">
			<label class="col-md-3 control-label">{{Utilisation de l'API vigilance Météo France}}</label>
			<div class="col-sm-1" style="width:2%">
				<input type="checkbox" class="configKey tooltips" data-l1key="useVigilanceAPI">
			</div>
      <label class="col-md-6 control-label" style="text-align: left;line-height:normal">{{Vous devez souscrire à l'API <a href="https://portail-api.meteofrance.fr/web/fr/api/DonneesPubliquesVigilance" target="_blank">Donnees Publiques Vigilance <sup><i class="fas fa-external-link-alt tooltips"></i></sup></a> de Météo France.}}
<!--
      <label class="col-md-6 control-label" style="text-align: left;line-height:normal">{{Le plugin peut récupérer les données de vigilances avec l'API <a href="https://portail-api.meteofrance.fr/web/fr/api/DonneesPubliquesVigilance" target="_blank">DonneesPubliquesVigilance</a> de Météo France ou sur le <a href="http://storage.gra.cloud.ovh.net/v1/AUTH_555bdc85997f4552914346d4550c421e/gra-vigi6-archive_public/" target="_blank">site d'archives de Météo France</a>.}}
-->
      </label>
    </div>
<!--
-->
    <div class="form-group">
			<label class="col-md-3 control-label">{{Utilisation de l'API Météo des forêts}}</label>
			<div class="col-sm-1" style="width:2%">
				<input type="checkbox" class="configKey tooltips" data-l1key="useForestAPI">
			</div>
      <label class="col-md-6 control-label" style="text-align: left;line-height:normal">{{Vous devez souscrire à l'API <a href="https://portail-api.meteofrance.fr/web/fr/api/DonneesPubliquesMeteoForets" target="_blank">Donnees Publiques Meteo Forets <sup><i class="fas fa-external-link-alt tooltips"></i></sup></a> de Météo France.}}
      </label>
		</div>
    <div class="form-group">
      <label class="col-md-3 control-label">{{Commentaires}}
      </label>
      <div class="col-md-7">
        <input type="text" class="configKey form-control" data-l1key="comment"/>
      </div>
    </div>
  </fieldset>
</form>

