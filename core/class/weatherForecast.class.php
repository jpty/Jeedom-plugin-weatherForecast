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

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class weatherForecast extends eqLogic {
  /*     * *************************Attributs****************************** */
  public static $_widgetPossibility = array('custom' => true, 'custom::layout' => true);

  /*     * ***********************Methode static*************************** */
  public static function cron() {
    $minute = date('i');
    $eqLogics = self::byType(__CLASS__, true);
    foreach ($eqLogics as $equipt) {
      $refreshMinute = $equipt->getConfiguration('refreshMinute', -1);
      // log::add(__CLASS__, 'info', $equipt->getName() ." Refresh minute : $refreshMinute");
      if ($refreshMinute == -1) {
        $equipt->setConfiguration('refreshMinute', rand(0,4));
        $equipt->save(true);
      }
      $datasource = $equipt->getConfiguration('datasource', '');
      if($datasource == 'openweathermap') $mod = 30; // refresh 30min;
      elseif ($datasource == 'weatherapi') $mod = 15;// refresh 15min;
      else continue;
      if(($minute - $refreshMinute) % $mod == 0) {
        try {
          $equipt->updateWeatherData(0);
        } catch (Exception $e) {
          log::add(__CLASS__, 'info', $e->getMessage());
        }
      }
    }
  }

  public static function getIconFromCondition($_condition_id, $datasource, $_dayNight) {
    if($datasource == 'openweathermap') {
      if ($_condition_id >= 200 && $_condition_id <= 299) {
        return 'meteo-orage';
      }
      if (($_condition_id >= 300 && $_condition_id <= 399)) {
        return 'meteo-brouillard';
      }
      if ($_condition_id >= 500 && $_condition_id <= 510) {
        if ($_dayNight == "day") return 'meteo-nuage-soleil-pluie';
        else return 'meteo-pluie'; // TODO icone avec lune
      }
      if ($_condition_id >= 520 && $_condition_id <= 599) {
        return 'meteo-pluie';
      }
      if (($_condition_id >= 600 && $_condition_id <= 699) || ($_condition_id == 511)) {
        return 'meteo-neige';
      }
      if ($_condition_id >= 700 && $_condition_id < 770){
        return 'meteo-brouillard';
      }
      if ($_condition_id >= 770 && $_condition_id < 799){
        return 'meteo-vent';
      }
      if ($_condition_id > 800 && $_condition_id <= 899) {
        if ($_dayNight == "day") return 'meteo-nuageux';
        else return 'meteo-nuit-nuage';
      }
      if ($_condition_id == 800) {
        if ($_dayNight == "day") return 'meteo-soleil';
        else return 'far fa-moon';
      }
      if ($_dayNight == "day") return 'meteo-soleil';
      else return 'far fa-moon';
      log::add(__CLASS__, 'info', "Unknown $datasource condition : $_condition_id");
    } elseif($datasource == 'weatherapi') {
      if (in_array($_condition_id, array(1087, 1273, 1276, 1279, 1282))) {
        return 'meteo-orage';
      }
      if (in_array($_condition_id, array(1135, 1030, 1072, 1147, 1150, 1153, 1168, 1171))) {
        return 'meteo-brouillard';
      }
      if (in_array($_condition_id, array(1189, 1195, 1063, 1180, 1186, 1201, 1240, 1243, 1246, 1183, 1207, 1198, 1192))) {
        return 'meteo-pluie';
      }
      if (in_array($_condition_id, array(1066, 1069, 1114, 1117, 1204, 1210, 1213, 1216, 1219, 1222, 1225, 1237, 1249, 1252, 1255, 1258, 1261, 1264))) {
        return 'meteo-neige';
      }
      if (in_array($_condition_id, array(1006, 1003, 1009))) {
        if ($_dayNight == "day") return 'meteo-nuageux';
        else return 'meteo-nuit-nuage';
      }
      if ($_dayNight == "day") return 'meteo-soleil';
      else return 'far fa-moon';
      log::add(__CLASS__, 'info', "Unknown $datasource condition : $_condition_id");
    }
    else log::add(__CLASS__, 'info', __FUNCTION__ ." Unknown datasource : $datasource");
  }

  /*     * *********************Methode d'instance************************* */

  public function preUpdate() {
    if (trim($this->getConfiguration('datasource')) == '') {
      throw new Exception(__("La source des données doit être renseignée", __FILE__));
    }
    $gps = trim($this->getConfiguration('positionGps'));
    if ($gps == '') {
      throw new Exception(__("Les coordonnées GPS doivent être renseignées", __FILE__));
    }
    $coord = explode(',', $gps);
    if(count($coord) == 2) {
      $lat = trim($coord[0]);
      $lon = trim($coord[1]);
      if(!is_numeric($lat))
        throw new Exception(__("La latitude doit être un nombre [$lat]", __FILE__));
      if(!is_numeric($lon))
        throw new Exception(__("La longitude doit être un nombre [$lon]", __FILE__));
    } else {
      throw new Exception(__("Coordonnées GPS incorrectes [$gps]: Latitude , longitude", __FILE__));
    }
  }

  public function preInsert() {
    $this->setIsVisible(1);
    $this->setIsEnable(1);
    $this->setConfiguration('templateWeatherForecast','plugin');
  }

  public function postUpdate() {
    $wfCmd = $this->getCmd(null, 'temperature');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Température', __FILE__));
    $wfCmd->setLogicalId('temperature');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('°C');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_TEMPERATURE');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'humidity');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Humidité', __FILE__));
    $wfCmd->setLogicalId('humidity');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('%');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_HUMIDITY');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'pressure');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Pression', __FILE__));
    $wfCmd->setLogicalId('pressure');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('hPa');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_PRESSURE');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'wind_speed');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Vitesse du vent', __FILE__));
    $wfCmd->setLogicalId('wind_speed');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('km/h');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_WIND_SPEED');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'wind_direction');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Direction du vent', __FILE__));
    $wfCmd->setLogicalId('wind_direction');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('°');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_WIND_DIRECTION');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'wind_gust');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Rafales de vent', __FILE__));
    $wfCmd->setLogicalId('wind_gust');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('km/h');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_WIND_GUST');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'condition');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Condition actuelle', __FILE__));
    $wfCmd->setLogicalId('condition');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('string');
    $wfCmd->setDisplay('generic_type', 'WEATHER_CONDITION');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'condition_id');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Numéro condition actuelle', __FILE__));
    $wfCmd->setLogicalId('condition_id');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_CONDITION_ID');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'rain');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Pluie', __FILE__));
    $wfCmd->setLogicalId('rain');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('mm');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_RAIN');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'sunrise');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Lever du soleil', __FILE__));
    $wfCmd->setLogicalId('sunrise');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setOrder(50);
    $wfCmd->setDisplay('generic_type', 'WEATHER_SUNRISE');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'sunset');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Coucher du soleil', __FILE__));
    $wfCmd->setLogicalId('sunset');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setOrder(51);
    $wfCmd->setDisplay('generic_type', 'WEATHER_SUNSET');
    $wfCmd->save();

    $ord = 200;
    for($i = 0;$i < 5; $i++) {
      $id = "title_day$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Titre J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('');
      $wfCmd->setType('info');
      $wfCmd->setSubType('string');
      $wfCmd->setOrder($ord++);
      $wfCmd->save();

      $id = "temperature_min_$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Température Min J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('°C');
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setOrder($ord++);
      $wfCmd->setDisplay('generic_type', "WEATHER_TEMPERATURE_MIN_$i");
      $wfCmd->save();

      $id = "temperature_max_$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Température Max J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('°C');
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setOrder($ord++);
      $wfCmd->setDisplay('generic_type', "WEATHER_TEMPERATURE_MAX_$i");
      $wfCmd->save();

      $id = "condition_$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Condition J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('');
      $wfCmd->setType('info');
      $wfCmd->setSubType('string');
      $wfCmd->setOrder($ord++);
      $wfCmd->setDisplay('generic_type', "WEATHER_CONDITION_$i");
      $wfCmd->save();

      $id = "condition_id_$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Numéro condition J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('');
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setOrder($ord++);
      $wfCmd->setDisplay('generic_type', "WEATHER_CONDITION_ID_$i");
      $wfCmd->save();

      $id = "rain_$i";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Pluie J+$i", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('mm');
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setOrder($ord++);
      $wfCmd->setDisplay('generic_type', "WEATHER_RAIN_$i");
      $wfCmd->save();
    }

    $id = "H0Json4Widget";
    $wfCmd = $this->getCmd(null, $id);
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(1);
      $wfCmd->setIsHistorized(0);
      $wfCmd->setName(__("Météo H0 - Json pour widget", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setType('info');
      $wfCmd->setSubType('string');
      $wfCmd->setTemplate('dashboard', __CLASS__ .'::Clock');
      $wfCmd->setTemplate('mobile', __CLASS__ .'::Clock');
      $wfCmd->setOrder(300);
      $wfCmd->save();
    }

    $refresh = $this->getCmd(null, 'refresh');
    if (!is_object($refresh)) {
      $refresh = new weatherForecastCmd();
      $refresh->setIsVisible(1);
      $refresh->setName(__('Rafraichir', __FILE__));
    }
    $refresh->setEqLogic_id($this->getId());
    $refresh->setLogicalId('refresh');
    $refresh->setType('action');
    $refresh->setSubType('other');
    $refresh->setOrder(0);
    $refresh->save();

    if ($this->getIsEnable() == 1) {
      $this->updateWeatherData(1);
    } else {
      $cron = cron::byClassAndFunction(__CLASS__, 'pull', array('weather_id' => intval($this->getId())));
      if (is_object($cron)) {
        $cron->remove();
      }
    }
  }

  public function convertDegrees2Compass($degrees,$deg=0) {
    $sector = array("Nord","NNE","NE","ENE","Est","ESE","SE","SSE","Sud","SSO","SO","OSO","Ouest","ONO","NO","NNO","Nord");
    $degrees %= 360;
    $idx = round($degrees/22.5);
    $ret = $sector[$idx];
    if($deg) $ret .= " $degrees" ."°";
    return($ret);
  }

  public function toHtml($_version = 'dashboard') {
    $replace = $this->preToHtml($_version);
    if (!is_array($replace)) {
      return $replace;
    }
    $templateF = $this->getConfiguration('templateWeatherForecast','plugin');
    if($templateF == 'none') return parent::toHtml($_version);
    elseif($templateF == 'plugin') $templateFile = 'weatherForecast';
    elseif($templateF == 'pluginImg') $templateFile = 'weatherForecastIMG';
    elseif($templateF == 'custom') $templateFile = 'custom.weatherForecast';
    else $templateFile = substr($templateF,0,-5);
    // log::add(__CLASS__, 'debug', __FUNCTION__ ." \"" .$this->getName() ."\" Template: $templateFile");

    $version = jeedom::versionAlias($_version);
    $replace['#forecast#'] = '';
    $datasource = trim($this->getConfiguration('datasource', ''));
    if ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1) {
      if (strpos($templateFile, 'weatherForecastIMG') !== false) {
        $forcast_template = getTemplate('core', $version, 'forecastIMG', __CLASS__);
      } else {
        $forcast_template = getTemplate('core', $version, 'forecast', __CLASS__);
      }
      $sunriseCmd = $this->getCmd(null, 'sunrise');
      $replace['#sunid#'] = is_object($sunriseCmd) ? $sunriseCmd->getId() : '';
      $replace['#sunrise_sunset#'] = '';
      if(is_object($sunriseCmd)) {
        $sunrise = $sunriseCmd->execCmd();
        if($sunrise == 0) {
          $replace['#sunrise#'] = 'never';
          $replace['#sunrise_sunset#'] = '<i title="Nuit polaire" class="icon far fa-moon"></i>';
        }
        elseif($sunrise == 1) {
          $replace['#sunrise#'] = 'always';
          $replace['#sunrise_sunset#'] = '<i title="Jour polaire" class="icon far fa-sun"></i>';
        }
        else {
          $replace['#sunrise_sunset#'] = '<i title="Lever - Coucher du soleil" class="fas fa-sun icon_yellow"></i> ';
          if(strlen($sunrise) == 1) $replace['#sunrise#'] = "0:0$sunrise";
          if(strlen($sunrise) == 2) $replace['#sunrise#'] = "0:$sunrise";
          else $replace['#sunrise#'] = substr_replace($sunrise,':',-2,0);
          $replace['#sunrise_sunset#'] .= $replace['#sunrise#'];
        }
      }
      else $sunrise = null;

      $sunsetCmd = $this->getCmd(null, 'sunset');
      $sunset = is_object($sunsetCmd) ? $sunsetCmd->execCmd() : '';
      if(is_object($sunsetCmd)) {
        $sunset = $sunsetCmd->execCmd();
        if($sunset == 0)
          $replace['#sunset#'] = 'never';
        elseif($sunset == 1)
          $replace['#sunset#'] = 'always';
        else  {
          if(strlen($sunset) == 2) $replace['#sunset#'] = "0:$sunset";
          else $replace['#sunset#'] = substr_replace($sunset,':',-2,0);
          $replace['#sunsetid#'] = $sunsetCmd->getId();
          $replace['#sunrise_sunset#'] .= ' - ' .$replace['#sunset#'];
        }
      }
      else $sunset = null;

      $hour =  date('G');
      $nbForecastDays = $this->getConfiguration('forecastDaysNumber', 5);
      for ($i = 0; $i < $nbForecastDays; $i++) {
        if($i == 0) {
          $condition = $this->getCmd(null, "condition_$i");
          if(is_object($condition)) {
            $val = $condition->execCmd();
            if($val == '') continue;
          }
          if($hour == 23) continue; // Pas d'affichage si dernière heure du jour
        }
        $replaceDay = array();
        $titleCmd = $this->getCmd(null, "title_day$i");
        $replaceDay['#day#'] = is_object($titleCmd) ? $titleCmd->execCmd() : '';

        $temperature_min = $this->getCmd(null, "temperature_min_$i");
        $replaceDay['#low_temperature#'] = is_object($temperature_min) ? $temperature_min->execCmd() : '';

        $temperature_max = $this->getCmd(null, "temperature_max_$i");
        $replaceDay['#high_temperature#'] = is_object($temperature_max) ? $temperature_max->execCmd() : '';
        $replaceDay['#tempid#'] = is_object($temperature_max) ? $temperature_max->getId() : '';
        $conditionID = $this->getCmd(null, "condition_id_$i");
        $dayNight = "day"; // day icon
        if($i == 0) {
          $t = date('Gi');
          if($t < $sunrise || $t > $sunset) $dayNight = "night";
        }
        $replaceDay['#icone#'] = is_object($conditionID) ? self::getIconFromCondition($conditionID->execCmd(),$datasource,$dayNight) : '';
        $condition = $this->getCmd(null, "condition_$i");
        $replaceDay['#condition#'] = is_object($condition) ? $condition->execCmd() : '';
        $rainCmd = $this->getCmd(null, "rain_$i");
        if(is_object($rainCmd)) {
          $val = $rainCmd->execCmd();
          if($val > 0) $rain = $val .'mm';
          else $rain = '';
        }
        else $rain = '';
        $replaceDay['#rain#'] = $rain;


        $replace['#forecast#'] .= template_replace($replaceDay, $forcast_template);
      }
    }
    $temperature = $this->getCmd(null, 'temperature');
    $replace['#temperature#'] = is_object($temperature) ? $temperature->execCmd() : '';
    $replace['#tempid#'] = is_object($temperature) ? $temperature->getId() : '';

    $humidity = $this->getCmd(null, 'humidity');
    $replace['#humidity#'] = is_object($humidity) ? $humidity->execCmd() : '';

    $rain = $this->getCmd(null, 'rain');
    $replace['#rain#'] = is_object($rain) ? $rain->execCmd() : '0';

    $pressure = $this->getCmd(null, 'pressure');
    $replace['#pressure#'] = is_object($pressure) ? $pressure->execCmd() : '';
    $replace['#pressureid#'] = is_object($pressure) ? $pressure->getId() : '';

    $wind_speed = $this->getCmd(null, 'wind_speed');
    $replace['#windspeed#'] = is_object($wind_speed) ? $wind_speed->execCmd() : '';
    $replace['#windid#'] = is_object($wind_speed) ? $wind_speed->getId() : '';
    $windGustCmd = $this->getCmd(null, 'wind_gust');
    if(is_object($windGustCmd)) {
      $raf = $windGustCmd->execCmd();
      if(!is_numeric($raf)) $raf = 0;
    }
    else $raf = 0;
    if($raf) {
      $replace['#windGust#'] = "&nbsp; {$raf}km/h &nbsp;";
      $replace['#spacer#'] = '';
    } else {
      $replace['#windGust#'] = '';
      $replace['#spacer#'] = '<br>';
    }

    $wind_direction = $this->getCmd(null, 'wind_direction');
    if(is_object($wind_direction)) {
      $windDirection = $wind_direction->execCmd();
      if(!is_numeric($windDirection)) $windDirection = 0;
      $replace['#wind_direction#'] = $windDirection;
      $replace['#winddir#'] = $this->convertDegrees2Compass($windDirection,0);
      $replace['#windIcon#'] = '<svg data-v-47880d39="" width="30px" height="30px" viewBox="0 0 1000 1000" enable-background="new 0 0 1000 1000" xml:space="preserve" class="icon-wind-direction" style="transform: rotate(' .($windDirection+180) .'deg);"><g data-v-47880d39="" fill="#3C73A5"><path data-v-47880d39="" d="M510.5,749.6c-14.9-9.9-38.1-9.9-53.1,1.7l-262,207.3c-14.9,11.6-21.6,6.6-14.9-11.6L474,48.1c5-16.6,14.9-18.2,21.6,0l325,898.7c6.6,16.6-1.7,23.2-14.9,11.6L510.5,749.6z"></path><path data-v-47880d39="" d="M817.2,990c-8.3,0-16.6-3.3-26.5-9.9L497.2,769.5c-5-3.3-18.2-3.3-23.2,0L210.3,976.7c-19.9,16.6-41.5,14.9-51.4,0c-6.6-9.9-8.3-21.6-3.3-38.1L449.1,39.8C459,13.3,477.3,10,483.9,10c6.6,0,24.9,3.3,34.8,29.8l325,898.7c5,14.9,5,28.2-1.7,38.1C837.1,985,827.2,990,817.2,990z M485.6,716.4c14.9,0,28.2,5,39.8,11.6l255.4,182.4L485.6,92.9l-267,814.2l223.9-177.4C454.1,721.4,469,716.4,485.6,716.4z"></path></g></svg>';
    } else {
      $replace['#wind_direction#'] = 0;
      $replace['#winddir#'] = '';
      $replace['#windIcon#'] = '';
    }

    $refresh = $this->getCmd(null, 'refresh');
    $replace['#refresh_id#'] = is_object($refresh) ? $refresh->getId() : '';

    $sunset_time = is_object($sunset) ? $sunset->execCmd() : null;
    $sunrise_time = is_object($sunrise) ? $sunrise->execCmd() : null;
    $condition_id = $this->getCmd(null, 'condition_id');
    if (is_object($condition_id)) {
      $dayNight = "day"; // day icon
      $t = date('Gi');
      if($t < $sunrise_time || $t > $sunset_time) $dayNight = "night";
      $replace['#icone#'] = self::getIconFromCondition($condition_id->execCmd(), $datasource, $dayNight);
      $replace['#condition_id#'] = $condition_id->execCmd();
    } else {
      $replace['#icone#'] = '';
      $replace['#condition_id#'] = '';
    }
    $replace['#cityName#'] = $this->getConfiguration('ville', "NA");
    $replace['#country#'] = $this->getConfiguration('country', "NA");
    $replace['#lat#'] = $this->getConfiguration('lat', "--");
    $replace['#lon#'] = $this->getConfiguration('lon', "--");

      // Condition current hour
    $condition = $this->getCmd(null, 'condition');
    if (is_object($condition)) {
      $replace['#condition#'] = $condition->execCmd();
      $replace['#conditionid#'] = $condition->getId();
      $replace['#collectDate#'] = $condition->getCollectDate();
    } else {
      $replace['#condition#'] = '';
      $replace['#collectDate#'] = '';
      $replace['#collectDate#'] = '';
    }

    if (file_exists( __DIR__ ."/../template/$_version/$templateFile.html"))
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $templateFile, __CLASS__)));
    else
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, __CLASS__, __CLASS__)));
  }

  public function fetchOpenweather($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $content = curl_exec($ch);
    if ($content === false) {
      log::add(__CLASS__,'warning', __FUNCTION__ ." $url Failed curl_error: (" .curl_errno($ch) .") " .curl_error($ch));
      curl_close($ch); unset($ch);
      return(null);
    }
    curl_close($ch); unset($ch);
    return($content);
  }

  public function updateWeatherOwm($_updateConfig, $lat, $lon, $lang, &$H0array) {
    $changed = false;
    $apikeyOwm = trim(config::byKey('apikeyOwm', __CLASS__, ''));
    if(trim($apikeyOwm) == '' )
      throw new Exception(__("La clé API OpenWeatherMap n'est pas renseignée.", __FILE__));
      // current weather
    $url = "http://api.openweathermap.org/data/2.5/weather?units=metric&lang=$lang&APPID=$apikeyOwm&lat=$lat&lon=$lon";
    $content = $this->fetchOpenweather($url);
    if($content == null) return;
    $hdle = fopen(__DIR__ ."/../../data/OpenWeather-current-" .$this->getId().".json", "wb");
    if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
    $weather = json_decode($content,true);
    if($weather == null) {
      log::add(__CLASS__, 'warning', __FUNCTION__ ." L:" .__LINE__ ." Json_decode error : " .json_last_error_msg() ." [" . substr($content,0,50) ."] ... [" .substr($content,-50) ."]");
      return;
    }
    if(isset($weather['cod']) && $weather['cod'] != 200) {
      if($_updateConfig) { // memo dans la config de l'équipement
        $this->setConfiguration('lat', '');
        $this->setConfiguration('lon', '');
        $this->setConfiguration('ville', '');
        $this->setConfiguration('country', '');
      }
      $errMsg = "Erreur: " .$weather['cod'] ." " .$weather['message'];
      $this->setConfiguration('otherInfo', $errMsg);
      log::add(__CLASS__, 'warning', $errMsg);
      $this->save(true);
      return;
    }
    if($_updateConfig) { // memo dans la config de l'équipement
      $this->setConfiguration('lat', $weather['coord']['lat']);
      $this->setConfiguration('lon', $weather['coord']['lon']);
      $this->setConfiguration('ville', $weather['name']);
      if(isset($weather['sys']['country']))
        $this->setConfiguration('country', $weather['sys']['country']);
      else
        $this->setConfiguration('country', '');
      $this->setConfiguration('otherInfo', "CityID : " .$weather['id']);
      $this->save(true);
    }
    log::add(__CLASS__, 'debug', $url . ' : ' . substr(json_encode($weather),0,100));
    // log::add(__CLASS__, 'debug', json_encode($weather));
    $weatherTemp = round($weather['main']['temp'], 1);
    $weatherDesc = ucfirst($weather['weather']['0']['description']);
    $changed = $this->checkAndUpdateCmd('temperature', $weatherTemp) || $changed;
    $changed = $this->checkAndUpdateCmd('humidity', $weather['main']['humidity']) || $changed;
    $changed = $this->checkAndUpdateCmd('pressure', $weather['main']['pressure']) || $changed;
    $changed = $this->checkAndUpdateCmd('condition', $weatherDesc) || $changed;
    $changed = $this->checkAndUpdateCmd('condition_id', $weather['weather'][0]['id']) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_speed', round($weather['wind']['speed'] * 3.6)) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_direction', $weather['wind']['deg']) || $changed;
    $windGust =  (isset($weather['wind']['gust'])) ? round($weather['wind']['gust'] * 3.6) : 0;
    $changed = $this->checkAndUpdateCmd('wind_gust', $windGust) || $changed;
    $changed = $this->checkAndUpdateCmd('cloudiness', $weather['clouds']['all']) || $changed;
    $rain1h =  (isset($weather['rain']['1h'])) ? $weather['rain']['1h'] : 0;
    $changed = $this->checkAndUpdateCmd('rain', $rain1h) || $changed;
    $snow1h =  (isset($weather['snow']['1h'])) ? $weather['snow']['1h'] : 0;
    $timezone = config::byKey('timezone', 'core', 'Europe/Paris');
    $dayNight = "day"; // day icon
    $t = time();
    if($t < $H0array['sunrise'] || $t > $H0array['sunset']) $dayNight = "night";
    $icon = self::getIconFromCondition($weather['weather'][0]['id'], 'openweathermap', $dayNight);

    $H0array['weather'] = ['icon' => $icon, 'desc' => $weatherDesc];
    $H0array['T'] = ['value' => $weatherTemp, 'windchill' => $weather['main']['feels_like']];
    $H0array['wind'] = ['speed' => $weather['wind']['speed'],
                        'gust' => $windGust,
                        'direction' => $weather['wind']['deg'],
                        'icon' =>  $this->convertDegrees2Compass($weather['wind']['deg'],0) ];
 
    $H0array['humidity'] = $weather['main']['humidity'];
    $H0array['sea_level'] = $weather['main']['sea_level'];
    $H0array['rain'] = ['1h' => $rain1h];
    $H0array['snow'] = ['1h' => $snow1h];
    $H0array['clouds'] = $weather['clouds']['all'];

    // forecast
    $url = "http://api.openweathermap.org/data/2.5/forecast?units=metric&lang=$lang&APPID=$apikeyOwm&lat=$lat&lon=$lon";
    $content = $this->fetchOpenweather($url);
    if($content == null) return;
    $hdle = fopen(__DIR__ ."/../../data/OpenWeather-forecast-".$this->getId() .".json", "wb");
    if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
    $forecast = json_decode($content,true);
    if($forecast == null) {
      log::add(__CLASS__, 'error', __FUNCTION__ ." L:" .__LINE__ ." Json_decode error : " .json_last_error_msg() ." [" . substr($content,0,50) ."] ... [" .substr($content,-50) ."]");
      return;
    }
    // log::add(__CLASS__, 'debug', "Nb forecast: " .count($forecast['forecast']['time']));
    $hour = date('G');
    $nbForecastDays = 5;
    if($_updateConfig) { // memo dans la config de l'équipement
      $this->setConfiguration('forecastDaysNumber', $nbForecastDays);
      $this->save(true);
    }
// log::add(__CLASS__, 'info', date('Y-m-d H:i:s') ." " .$this->getName() ." : 1st forecast " .$forecast['list'][0]['dt_txt'] ." Dt : " .date('Y-m-d H:i:s', $forecast['list'][0]['dt']) ." Timezone : ".$forecast['city']['timezone']);
    $tsNow = time();
    for ($i = 0; $i < $nbForecastDays; $i++) {
      $ts = strtotime("+{$i} day");
      $date = date('Y-m-d', $ts);
      $midday = date('Y-m-d 12:00:00', $ts);
      $maxTemp = -666;
      $minTemp = 666;
      $condition_id = 0;
      $condition = 0;
      $rain = 0;

      foreach ($forecast['list'] as $weather) {
        if(!isset($weather['dt_txt'])) {
          log::add(__CLASS__, 'warning'," From value not set: " .json_encode($weather));
          continue;
        }
        $tsDt_txt = strtotime($weather['dt_txt']);
        $sDate = date('Y-m-d',$tsDt_txt);
        $Tmin = round($weather['main']['temp_min'], 1);
        $Tmax = round($weather['main']['temp_max'], 1);
        // log::add(__CLASS__, 'debug', $weather['dt_txt'] ." [$sDate] Weather temp: " .$weather['main']['temp']));
        // log::add(__CLASS__, 'debug', "Weather date: $sDate");
        if ($date != $sDate) { // autre jour
          // log::add(__CLASS__, 'debug', "Another day");
          continue;
        }
        if ($minTemp > $Tmin) $minTemp = $Tmin;
        if ($maxTemp < $Tmax) $maxTemp = $Tmax;

          // cumul des pluies de la journée
        if(isset($weather['rain']['3h'])) {
          $rain += $weather['rain']['3h'];
          // log::add(__CLASS__, 'debug', "$i " .date('Y-m-d H:i',$tsDt_txt) ." Rain: $rain");
        }

        if($i == 0 && $hour > 8 && $tsDt_txt > $tsNow) { // plage de l'heure suivante recherchée
          $title = date('G', $tsDt_txt) ."h - " .date('G',($tsDt_txt + 10800)) ."h";
          $changed = $this->checkAndUpdateCmd("title_day$i", $title) || $changed;
          $condition_id = $weather['weather'][0]['id'];
          $condition = ucfirst($weather['weather'][0]['description']);
          $changed = $this->checkAndUpdateCmd("condition_$i", $condition) || $changed;
          $changed = $this->checkAndUpdateCmd("condition_id_$i", $condition_id) || $changed;
          $rain3h =  (isset($weather['rain']['3h'])) ? $weather['rain']['3h'] : 0;
          $changed = $this->checkAndUpdateCmd("rain_$i", $rain3h) || $changed;
// log::add(__CLASS__, 'debug', "J$i $title Cond:$condition_id Desc:$condition Pluie: $rain");
          break;
        }
        else if($weather['dt_txt'] == $midday) { // condition à 12h uniquement
          $title = date_fr(date('D. j', $tsDt_txt));
          $changed = $this->checkAndUpdateCmd("title_day$i", $title) || $changed;
          $condition_id = $weather['weather'][0]['id'];
          $condition = ucfirst($weather['weather'][0]['description']);
// log::add(__CLASS__, 'debug', "J$i $title Cond:$condition_id Desc:$condition");
          $changed = $this->checkAndUpdateCmd("condition_$i", $condition) || $changed;
          $changed = $this->checkAndUpdateCmd("condition_id_$i", $condition_id) || $changed;
        }
      }
      if (abs($minTemp) != 666) {
        $changed = $this->checkAndUpdateCmd("temperature_min_$i", $minTemp) || $changed;
      }
      if (abs($maxTemp) != 666) {
        $changed = $this->checkAndUpdateCmd("temperature_max_$i", $maxTemp) || $changed;
      }
      if($i != 0) {
// log::add(__CLASS__, 'debug', "$i " .date('Y-m-d H:i',$tsDt_txt) ." Rain: $rain");
        $changed = $this->checkAndUpdateCmd("rain_$i", round($rain,1)) || $changed;
      }
    }
    return $changed;
  }

  public function getDistanceBetweenGPSPoints($pos1, $pos2, $unit = 'kilometers') {
    $pos = explode(',',$pos1); $lat1 = trim($pos[0]);
    if(count($pos) > 1) $lon1 = trim($pos[1]); else return(-1);
    $pos = explode(',',$pos2); $lat2 = trim($pos[0]);
    if(count($pos) > 1) $lon2 = trim($pos[1]); else return(-1);
    $theta = $lon1 - $lon2; 
    $distance = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta))); 
    $distance = acos($distance); 
    $distance = rad2deg($distance); 
    $distance = $distance * 60 * 1.1515; 
    switch($unit) { 
      case 'miles': 
        break; 
      case 'kilometers' : 
        $distance = $distance * 1.609344; 
        break; 
    } 
    return (round($distance,2)); 
  }

  public function updateWeatherApi($_updateConfig, $lat, $lon, $lang, &$H0array) {
    $changed = false;
    $apikeyWapi = trim(config::byKey('apikeyWapi', __CLASS__));
    if(trim($apikeyWapi) == '' )
      throw new Exception(__("La clé API Weather API n'est pas renseignée.", __FILE__));
    $nbdays = 14;
    $url = "http://api.weatherapi.com/v1/forecast.json?key=$apikeyWapi&q=$lat,$lon&lang=$lang&days=$nbdays&aqi=yes&alerts=yes";
    $request_http = new com_http($url);
    $resu = $request_http->exec(10);
    
    $datas = json_decode($resu, true);
    if(isset($datas['error'])) {
      log::add(__CLASS__, 'info', $url . ' : ' . json_encode($datas));
      $file = __DIR__ ."/../../data/weatherApi-error-" .$this->getId() .".json";
      $hdle = fopen($file, "wb");
      if($hdle !== FALSE) { fwrite($hdle, $resu); fclose($hdle); }
      else message::add(__CLASS__, "Unable to write $file");
        // {"error":{"code":1006,"message":"No matching location found."}}
      if(isset($datas['error'])) {
        $this->setConfiguration('lat', '');
        $this->setConfiguration('lon', '');
        $this->setConfiguration('ville', '');
        $this->setConfiguration('country', '');
        $errMsg = "Erreur: " .$datas['error']['code'] ." " .$datas['error']['message'];
        $this->setConfiguration('otherInfo', $errMsg);
        log::add(__CLASS__, 'warning', $errMsg);
        $this->save(true);
      }
      return;
    }
    
    $datas =  array_merge(array('state' => 'ok', 'datetime' => date('c')),$datas);
    $file = __DIR__ ."/../../data/weatherAPI-" .$this->getId() .".json";
    $hdle = fopen($file, "wb");
    if($hdle !== FALSE) {
      fwrite($hdle, json_encode($datas,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      fclose($hdle);
    }
    else message::add(__CLASS__, "Unable to write $file");
    log::add(__CLASS__, 'debug', $url . ' : ' . substr(json_encode($datas),0, 100) .'...');
    $current = $datas['current'];
    log::add(__CLASS__, 'debug', "  Datas updated on " .$current['last_updated'] ." Condition: " .$current['condition']['text']); // ." Icon: " .$current['condition']['icon']);

    if($_updateConfig) { // memo dans la config de l'équipement
      $this->setConfiguration('lat', $datas['location']['lat']);
      $this->setConfiguration('lon', $datas['location']['lon']);
      $this->setConfiguration('ville', $datas['location']['name']);
      if(isset($datas['location']['country']))
        $this->setConfiguration('country', $datas['location']['country']);
      else
        $this->setConfiguration('country', '');
      $pos1 = "$lat,$lon";
      $pos2 = $datas['location']['lat'] ."," .$datas['location']['lon'];
      $dist = $this->getDistanceBetweenGPSPoints($pos1, $pos2, 'kilometers');
      $this->setConfiguration('otherInfo', "Distance: $dist km");
      $this->save(true);
    }
    $weatherTemp = $current['temp_c'];
    $weatherDesc = $current['condition']['text'];
    $changed = $this->checkAndUpdateCmd('temperature', $weatherTemp) || $changed;
    $changed = $this->checkAndUpdateCmd('humidity', $current['humidity']) || $changed;
    $changed = $this->checkAndUpdateCmd('pressure', $current['pressure_mb']) || $changed;
    $changed = $this->checkAndUpdateCmd('condition', $weatherDesc) || $changed;
    $changed = $this->checkAndUpdateCmd('condition_id', $current['condition']['code']) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_speed', round($current['wind_kph'])) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_direction', $current['wind_degree']) || $changed;
    $changed = $this->checkAndUpdateCmd('rain', round($current['precip_mm'],1)) || $changed;
    $windGust =  (isset($current['gust_kph'])) ? round($current['gust_kph']) : 0;
    $changed = $this->checkAndUpdateCmd('wind_gust', $windGust) || $changed;
    $dayNight = "day"; // day icon
    $t = time();
    if($t < $H0array['sunrise'] || $t > $H0array['sunset']) $dayNight = "night";
    $icon = self::getIconFromCondition($current['condition']['code'], 'weatherapi', $dayNight);
    $H0array['weather'] = ['icon' => $icon, 'desc' => $weatherDesc];
    $H0array['T'] = ['value' => $weatherTemp, 'windchill' => $current['feelslike_c']];
    $H0array['wind'] = ['speed' => ($current['wind_kph']/3.6),
                        'gust' => round($windGust/3.6),
                        'direction' => $current['wind_degree'],
                        'icon' =>  $this->convertDegrees2Compass($current['wind_degree'],0) ];
    $H0array['humidity'] = $current['humidity'];
    $H0array['sea_level'] = $current['pressure_mb'];
    $H0array['rain'] = ['1h' => $current['precip_mm']];
    $H0array['snow'] = ['1h' => 0];
    $H0array['clouds'] = $current['cloud'];
    /*
    $this->checkAndUpdateCmd('visibility_Hcur', $current['vis_km']);
    $this->checkAndUpdateCmd('uv_Hcur', $current['uv']);
     */
    /*
    if(0) { // commande JSON par heures sur 7 heures
      $found = 0; $now = time(); $searchStart = date("Y-m-d H:00",strtotime("now")); // $searchStartHour = date("H:00",strtotime("now")); 
      $numCmd = 0;
      for ($j = 0; $j < 7; $j++) {
        $forecasthour = $datas['forecast']['forecastday'][$j]['hour'];
        for ($i = 0; $i < 24; $i++) {
          if($numCmd >= 8) break;
          if(!$found && !strcmp($forecasthour[$i]['time'],$searchStart)) {
            $found = 1;
          }
          if($found) {
            // message::add(__CLASS__, "$numCmd Trouvé " .$forecasthour'][$i]['time']);
            unset($forecasthour[$i]['temp_f']);
            unset($forecasthour[$i]['wind_mph']);
            unset($forecasthour[$i]['pressure_in']);
            unset($forecasthour[$i]['precip_in']);
            unset($forecasthour[$i]['feelslike_f']);
            unset($forecasthour[$i]['windchill_f']);
            unset($forecasthour[$i]['heatindex_f']);
            unset($forecasthour[$i]['dewpoint_f']);
            unset($forecasthour[$i]['vis_miles']);
            unset($forecasthour[$i]['gust_mph']);
            $json = '
             Structure de la commande JSON nettoyée HOUR
               { "time_epoch":1730638800, ==> 2024-11-03 14:11:00
                 "time":"2024-11-03 14:00",
                 "temp_c":13.4,
                 "is_day":1,
                 "condition":{
                   "text":"Ensoleillé",
                   "icon":"//cdn.weatherapi.com/weather/64x64/day/113.png",
                   "code":1000
                 },
                 "wind_kph":9.4,
                 "wind_degree":72,
                 "wind_dir":"ENE",
                 "pressure_mb":1028,
                 "precip_mm":0,
                 "snow_cm":0,
                 "humidity":72,
                 "cloud":0,
                 "feelslike_c":12.9,
                 "windchill_c":12.9,
                 "heatindex_c":13.5,
                 "dewpoint_c":6.6,
                 "will_it_rain":0,
                 "chance_of_rain":0,
                 "will_it_snow":0,
                 "chance_of_snow":0,
                 "vis_km":10,
                 "gust_kph":11.3,
                 "uv":1.3,
                 "air_quality":{
                   "co":395.9,"no2":12.395,"o3":39,"so2":3.33,"pm2_5":31.08,"pm10":35.705,
                   "us-epa-index":2,"gb-defra-index":3*
                 }
               }
             ';
            $this->checkAndUpdateCmd("forecast_H{$numCmd}_JSON", str_replace('"','&quot;',json_encode($forecasthour[$i],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            $numCmd++;
          }
        }
      }
    }
     */

    $nbForecastDays = 3; // count($datas['forecast']['forecastday']);
    if($_updateConfig) { // memo dans la config de l'équipement
      $this->setConfiguration('forecastDaysNumber', $nbForecastDays);
      $this->save(true);
    }
    for ($i = 0; $i < $nbForecastDays; $i++) {
      if(!isset($datas['forecast']['forecastday'][$i]['day'])) { 
        break;
      }
      $forecastday = $datas['forecast']['forecastday'];
      $title = date_fr(date('D. j', strtotime($forecastday[$i]['date'])));
      $changed = $this->checkAndUpdateCmd("title_day$i", $title) || $changed;
      $changed = $this->checkAndUpdateCmd("temperature_min_$i", $forecastday[$i]['day']['mintemp_c']) || $changed;
      $changed = $this->checkAndUpdateCmd("temperature_max_$i", $forecastday[$i]['day']['maxtemp_c']) || $changed;
      $changed = $this->checkAndUpdateCmd("condition_$i", $forecastday[$i]['day']['condition']['text']) || $changed;
      $changed = $this->checkAndUpdateCmd("condition_id_$i", $forecastday[$i]['day']['condition']['code']) || $changed;
      $changed = $this->checkAndUpdateCmd("rain_$i", round($forecastday[$i]['day']['totalprecip_mm'],1)) || $changed;
      $this->checkAndUpdateCmd("temperature_$i", $forecastday[$i]['day']['avgtemp_c']);
      $this->checkAndUpdateCmd("uv_$i", $forecastday[$i]['day']['uv']);
      $this->checkAndUpdateCmd("wind_speed_$i", $forecastday[$i]['day']['maxwind_kph']);
      if(0) { // commande JSON
        unset($forecastday[$i]['hour']);
        unset($forecastday[$i]['day']['maxtemp_f']);
        unset($forecastday[$i]['day']['mintemp_f']);
        unset($forecastday[$i]['day']['avgtemp_f']);
        unset($forecastday[$i]['day']['maxwind_mph']);
        unset($forecastday[$i]['day']['totalprecip_in']);
        unset($forecastday[$i]['day']['avgvis_miles']);
        /* Structure de la commande JSON nettoyée DAY
            {"date":"2024-11-03",
             "date_epoch":1730592000, ==> 24-11-03 01:11:00
             "day":{
               "maxtemp_c":13.8, "mintemp_c":4.1, "avgtemp_c":7.6,
               "maxwind_kph":10.4,
               "totalprecip_mm":0,
               "totalsnow_cm":0,
               "avgvis_km":7.7,
               "avghumidity":85,
               "daily_will_it_rain":0, "daily_chance_of_rain":0,
               "daily_will_it_snow":0, "daily_chance_of_snow":0,
               "condition":{
                 "text":"Ensoleillé",
                 "icon":"\/\/cdn.weatherapi.com\/weather\/64x64\/day\/113.png",
                 "code":1000
               },
               "uv":0.3,
               "air_quality":{
                 "co":393.828, "no2":19.965199999999996, "o3":30.76, "so2":2.3236,
                 "pm2_5":30.798799999999996, "pm10":34.9354, "us-epa-index":2, "gb-defra-index":3
               }
             },
             "astro":{
               "sunrise":"07:26 AM", "sunset":"05:11 PM",
               "moonrise":"09:45 AM", "moonset":"05:40 PM",
               "moon_phase":"Waxing Crescent", "moon_illumination":2,
               "is_moon_up":0, "is_sun_up":0
             }
            }
             */
        $this->checkAndUpdateCmd("forecast_D{$i}_JSON", str_replace('"','&quot;',json_encode($forecastday[$i],JSON_UNESCAPED_UNICODE)));
      }
    }
    return $changed;
  }

  public function updateWeatherData($_updateConfig = 0) {
    $gps = trim($this->getConfiguration('positionGps', ''));
    $coord = explode(',', $gps);
    if(count($coord) > 1) {
      $lat = trim($coord[0]);
      $lon = trim($coord[1]);
    } else {
      throw new Exception(__("Coordonnées GPS incorrectes [$gps]", __FILE__));
    }
    $H0array = array();
    $sun_info = date_sun_info(time(), $lat, $lon);
    $sunrise = $sun_info['sunrise'];
    $H0array['sunrise'] = $sunrise;
    if($sunrise === false) $sunrise = 0;
    elseif($sunrise === true) $sunrise = 1;
    else $sunrise = date('Gi',$sunrise);
    $this->checkAndUpdateCmd('sunrise', $sunrise);
    $sunset = $sun_info['sunset'];
    $H0array['sunset'] = $sunset;
    if($sunset === false) $sunset = 0;
    elseif($sunset === true) $sunset = 1;
    else $sunset = date('Gi',$sunset);
    $this->checkAndUpdateCmd('sunset', $sunset);

    $datasource = trim($this->getConfiguration('datasource', ''));
    $lang = substr(config::byKey('language','core', 'fr_FR'),0,2);
    if($datasource == "openweathermap") {
      $H0array['datasource'] = "OpenWeatherMap";
      $changed = $this->updateWeatherOwm($_updateConfig, $lat, $lon, $lang, $H0array);
      if ($changed) $this->refreshWidget();
      $contents = str_replace('"','&quot;',json_encode($H0array,JSON_UNESCAPED_UNICODE));
      $this->checkAndUpdateCmd("H0Json4Widget", $contents);
    }
    else if($datasource == "weatherapi") {
      $H0array['datasource'] = "WeatherApi";
      $changed = $this->updateWeatherApi($_updateConfig, $lat, $lon, $lang, $H0array);
      if ($changed) $this->refreshWidget();
      $contents = str_replace('"','&quot;',json_encode($H0array,JSON_UNESCAPED_UNICODE));
      $this->checkAndUpdateCmd("H0Json4Widget", $contents);
    }
    else {
      throw new Exception(__("Type de données inconnu. $datasource", __FILE__));
    }
  }

}

class weatherForecastCmd extends cmd {
  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh') {
      $this->getEqLogic()->updateWeatherData(0);
    }
    return false;
  }
}
