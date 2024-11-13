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
      <label class="col-lg-4 control-label">{{Clef API OpenWeatherMap}}</label>
      <div class="col-lg-4">
        <input class="configKey form-control" data-l1key="apikeyOwm" />
      </div>
      <a href="https://home.openweathermap.org/api_keys" target="_blank">Site Web OpenWeather ICI</a> <sup><i class="fas fa-question-circle tooltips" title="{{La clé API est à récupérer dans votre compte sur le site openweathermap.org<br/>Aprés génération d'une clé API, son activation n'est pas immédiate. Veuillez patienter.}}"></i></sup>
    </div>
    <div class="form-group">
      <label class="col-lg-4 control-label">{{Clef API WeatherApi}}</label>
      <div class="col-lg-4">
        <input class="configKey form-control" data-l1key="apikeyWapi" />
      </div>
      <a href="https://www.weatherapi.com/my/" target="_blank">Site Web WeatherAPI ICI</a> <sup><i class="fas fa-question-circle tooltips" title="{{La clé API est à récupérer dans votre compte sur le site WeatherApi}}"></i></sup>
    </div>
  </fieldset>
</form>

