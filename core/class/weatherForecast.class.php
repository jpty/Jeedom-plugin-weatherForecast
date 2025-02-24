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
  public static $_vigilanceType = array (
    1 => array("txt" => "Vent","icon" => "wi-strong-wind"),
    2 => array("txt" => "Pluie-inondation","icon" => "wi-rain-wind"),
    3 => array("txt" => "Orages","icon" => "wi-lightning"),
    4 => array("txt" => "Crues","icon" => "wi-flood"),
    5 => array("txt" => "Neige-verglas","icon" => "wi-snow"),
    6 => array("txt" => "Canicule","icon" => "wi-hot"),
    7 => array("txt" => "Grand-froid","icon" => "wi-thermometer-exterior"),
    8 => array("txt" => "Avalanches","icon" => "wi-na"),
    9 => array("txt" => "Vagues-submersion","icon" => "wi-tsunami"),
    10 => array("txt" => "Météo des forêts","icon" => "wi-fire"),
    100 => array("txt" => "Pas d'alerte"),
    101 => array("txt" => "Vent"),
    102 => array("txt" => "Neige-verglas"),
    103 => array("txt" => "Orages"),
    104 => array("txt" => "Brouillard"),
    105 => array("txt" => "Canicule"),
    106 => array("txt" => "Grand-froid"),
    107 => array("txt" => "Evénement côtier"),
    108 => array("txt" => "Feu de forêts"),
    109 => array("txt" => "Avalanches"),
    110 => array("txt" => "Pluie"),
    112 => array("txt" => "Inondation"),
    113 => array("txt" => "Pluie-inondation"),
    114 => array("txt" => "Marine hazard"),
    115 => array("txt" => "Sécheresse")
  );
  public static $_vigilanceColors = array (
    0 => array("desc" => "Non défini","color" => "#888888","hiColor" => "#FFFFFF"),
    1 => array("desc" => "Vert","color" => "#31AA35","hiColor" => "#FFFFFF"),
    2 => array("desc" => "Jaune","color" => "#FFF600","hiColor" => "#000000"),
    3 => array("desc" => "Orange","color" => "#FFB82B","hiColor" => "#000000"),
    4 => array("desc" => "Rouge","color" => "#CC0000","hiColor" => "#FFFFFF"),
  );

  public static function cronHourly() {
    $eqLogics = self::byType(__CLASS__, true);
    foreach ($eqLogics as $equipt) {
      $equipt->updateWeatherData(2,0);
    }
  }

  public static function cron() {
    $minute = date('i');
    $hour = date('G');
    $eqLogics = self::byType(__CLASS__, true);
    $recupVigMF = 1;
    $minuteVigilance = config::byKey('minuteVigilance', __CLASS__, -1);
    $fileAlert = __DIR__ ."/../../data/CDP_CARTE_EXTERNE.json";
    if ($minuteVigilance == -1) {
      $minuteVigilance = rand(11,59);
      config::save('minuteVigilance', $minuteVigilance, __CLASS__);
    }
    foreach ($eqLogics as $equipt) {
      if($recupVigMF == 1 && trim($equipt->getConfiguration('numDeptFr')) != '') {
        if($minute == $minuteVigilance || !file_exists($fileAlert)) {
          self::getVigilanceDataArchiveMF();
          $recupVigMF = 0;
        }
      }
      $country = strtolower(trim($equipt->getConfiguration('meteoAlarmCountry','')));
      if($country != '') {
        $file = __DIR__ ."/../../data/meteoalarm-$country.xml";
        // if($minute == $minuteVigilance) { // every hour
        if($minute == $minuteVigilance && date('G') % 3 == 0) { // every 3 hours
          $province = trim($equipt->getConfiguration('meteoAlarmArea',''));
          $language = substr(config::byKey('language','core', 'fr_FR'),0,2);
          $equipt->getMeteoalarmData($country, $province, $language);
        }
      }
      $refreshMinute = $equipt->getConfiguration('refreshMinute', -1);
      // log::add(__CLASS__, 'info', $equipt->getName() ." Refresh minute : $refreshMinute");
      if ($refreshMinute == -1) {
        $equipt->setConfiguration('refreshMinute', rand(0,4));
        $equipt->save(true);
        try {
          $equipt->updateWeatherData(0,1); // get weather data immediately
        } catch (Exception $e) {
          log::add(__CLASS__, 'info', $e->getMessage());
        }
      }
      else {
        $datasource = $equipt->getConfiguration('datasource', '');
        if($datasource == 'openweathermap') $mod = 30; // refresh 30min;
        elseif ($datasource == 'weatherapi') $mod = 15;// refresh 15min;
        else continue;
        if(($minute - $refreshMinute) % $mod == 0) {
          try {
            $equipt->updateWeatherData(0,0);
          } catch (Exception $e) {
            log::add(__CLASS__, 'info', $e->getMessage());
          }
        }
      }
    }
  }

    public static function extractValueFromJsonTxt($cmdValue, $request) {
    $txtJson = str_replace(array('&quot;','&#34;'),'"',$cmdValue);
    $json =json_decode($txtJson,true);
    if($json !== null) {
      $tags = explode('>', $request);
      foreach ($tags as $tag) {
        $tag = trim($tag);
        if (isset($json[$tag])) {
          $json = $json[$tag];
        } elseif (is_numeric(intval($tag)) && isset($json[intval($tag)])) {
          $json = $json[intval($tag)];
        } elseif (is_numeric(intval($tag)) && intval($tag) < 0 && isset($json[count($json) + intval($tag)])) {
          $json = $json[count($json) + intval($tag)];
        } else {
          $json = "Request error: tag[$tag] not found in " .json_encode($json);
          break;
        }
      }
      return (is_array($json)) ? json_encode($json) : $json;
    }
    return ("*** Unable to decode JSON: " .substr($txtJson,0,20));
  }

  public static function getJsonInfo($cmd_id, $request) {
    $id = cmd::humanReadableToCmd('#' .$cmd_id .'#');
    $cmd = cmd::byId(trim(str_replace('#', '', $id)));
    if(is_object($cmd)) {
      return self::extractValueFromJsonTxt($cmd->execCmd(), $request);
    }
    else log::add(__CLASS__, 'debug', "Command not found: $cmd");
    return(null);
  }

  public static function saintOfTheDay($month,$day) {
    switch($month) {
      case 1: $saints = array( 1 => "Jour de l'an", 2 => "St Basile", 3 => "Ste Geneviève", 4 => "St Odilon", 5 => "St Edouard", 6 => "Ste Mélanie", 7 => "St Raymond", 8 => "St Lucien", 9 => "Ste Alix", 10 => "St Guillaume", 11 => "St Paulin", 12 => "Ste Tatiana", 13 => "Ste Yvette", 14 => "Ste Nina", 15 => "St Rémi", 16 => "St Marcel", 17 => "Ste Roseline", 18 => "Ste Prisca", 19 => "St Marius", 20 => "St Sébastien", 21 => "Ste Agnès", 22 => "St Vincent", 23 => "St Barnard", 24 => "St François", 25 => "Conv de St Paul", 26 => "St Timothée", 27 => "Ste Angèle", 28 => "St Thomas d'Aquin", 29 => "St Gildas", 30 => "Ste Martine", 31 => "Ste Marcelle");
        break;
      case 2: $saints = array( 1 => "Ste Ella", 2 => "Présentation", 3 => "St Blaise", 4 => "Ste Véronique", 5 => "Ste Agathe", 6 => "St Gaston", 7 => "Ste Eugénie", 8 => "Ste Jacqueline", 9 => "Ste Apolline", 10 => "St Arnaud", 11 => "N-D de Lourdes", 12 => "St Félix", 13 => "Ste Béatrice", 14 => "St Valentin", 15 => "St Claude", 16 => "Ste Julienne", 17 => "St Alexis", 18 => "Ste Bernadette", 19 => "St Gabin", 20 => "Ste Aimée", 21 => "St Damien", 22 => "Ste Isabelle", 23 => "St Lazare", 24 => "St Modeste", 25 => "St Roméo", 26 => "St Nestor", 27 => "Ste Honorine", 28 => "St Romain", 29 => "St Auguste");
        break;
      case  3: $saints = array( 1 => "St Aubin", 2 => "St Charles", 3 => "St Guénolé", 4 => "St Casimir", 5 => "Ste Olive", 6 => "Ste Colette", 7 => "Ste Félicité", 8 => "St Jean de Dieu", 9 => "Ste Françoise", 10 => "St Vivien", 11 => "Ste Rosine", 12 => "Ste Justine", 13 => "St Rodrigue", 14 => "Ste Mathilde", 15 => "Ste Louise", 16 => "Ste Bénédicte", 17 => "St Patrice", 18 => "St Cyrille", 19 => "St Joseph", 20 => "St Herbert", 21 => "Ste Clémence", 22 => "Ste Léa", 23 => "St Victorien", 24 => "Ste Catherine de Suède", 25 => "Annonciation", 26 => "Ste Larissa", 27 => "St Habib", 28 => "St Gontran", 29 => "Ste Gwladys", 30 => "St Amédée", 31 => "St Benjamin");
        break;
      case   4: $saints = array( 1 => "St Hughes", 2 => "Ste Sandrine", 3 => "St Richard", 4 => "St Isidore", 5 => "Ste Irène", 6 => "St Marcellin", 7 => "St J-B de la Salle", 8 => "Ste Julie", 9 => "St Gautier", 10 => "St Fulbert", 11 => "St Stanislas", 12 => "Ste Jules", 13 => "Ste Ida", 14 => "St Maxime", 15 => "St Paterne", 16 => "St Benoît-Joseph", 17 => "St Anicet", 18 => "St Parfait", 19 => "Ste Emma", 20 => "Ste Odette", 21 => "St Anselme", 22 => "St Alexandre", 23 => "St Georges", 24 => "St Fidèle", 25 => "St Marc", 26 => "Ste Alida", 27 => "Ste Zita", 28 => "Ste Valérie", 29 => "Ste Cath. de Sienne", 30 => "St Robert");
        break;
      case 5: $saints = array( 1 => "Fête du Travail", 2 => "St Boris", 3 => "St Philippe - Jacques", 4 => "St Sylvain", 5 => "Ste Judith", 6 => "Ste Prudence", 7 => "Ste Gisèle", 8 => "Armistice 1945", 9 => "Ste Pacôme", 10 => "Ste Solange", 11 => "Ste Estelle", 12 => "St Achille", 13 => "Ste Rolande", 14 => "St Matthias", 15 => "Ste Denise", 16 => "St Honoré", 17 => "St Pascal", 18 => "St Eric", 19 => "St Yves", 20 => "St Bernardin", 21 => "St Constantin", 22 => "St Emile", 23 => "St Didier", 24 => "St Donatien", 25 => "Ste Sophie", 26 => "St Béranger", 27 => "St Augustin", 28 => "St Germain", 29 => "St Aymard", 30 => "St Ferdinand", 31 => "Visitation");
        break;
      case   6: $saints = array( 1 => "St Justin", 2 => "Ste Blandine", 3 => "St Kévin", 4 => "Ste Clotilde", 5 => "St Igor", 6 => "St Norbert", 7 => "St Gilbert", 8 => "St Médard", 9 => "Ste Diane", 10 => "St Landry", 11 => "St Barnabé", 12 => "St Guy", 13 => "St Antoine de P.", 14 => "St Elisée", 15 => "Ste Germaine", 16 => "St J-F Régis", 17 => "St Hervé", 18 => "St Léonce", 19 => "St Romuald", 20 => "St Silvère", 21 => "Eté", 22 => "St Alban", 23 => "Ste Audrey", 24 => "St Jean-Baptiste", 25 => "St Prosper", 26 => "St Anthelme", 27 => "St Fernand", 28 => "St Irénée", 29 => "St Pierre - Paul", 30 => "St Martial");
        break;
      case  7: $saints = array( 1 => "St Thierry", 2 => "St Martinien", 3 => "St Thomas", 4 => "St Florent", 5 => "St Antoine-Marie", 6 => "Ste Mariette", 7 => "St Raoul", 8 => "St Thibaut", 9 => "Ste Amandine", 10 => "St Ulrich", 11 => "St Benoît", 12 => "St Olivier", 13 => "St Henri - Joël", 14 => "Fête Nationale", 15 => "St Donald", 16 => "N-D du Mt-Carmel", 17 => "Ste Charlotte", 18 => "St Frédéric", 19 => "St Arsène", 20 => "Ste Marina", 21 => "St Victor", 22 => "Ste Marie-Madeleine", 23 => "Ste Brigitte", 24 => "Ste Christine", 25 => "St Jacques", 26 => "Ste Anne - Joachim", 27 => "Ste Nathalie", 28 => "St Samson", 29 => "Ste Marthe", 30 => "Ste Juliette", 31 => "St Ignace de Loyola");
        break;
      case  8: $saints = array( 1 => "St Alphonse", 2 => "St Julien Eymard", 3 => "Ste Lydie", 4 => "St J-M Vianney", 5 => "St Abel", 6 => "Transfiguration", 7 => "St Gaëtan", 8 => "St Dominique", 9 => "St Amour", 10 => "St Laurent", 11 => "Ste Claire", 12 => "Ste Clarisse", 13 => "St Hippolyte", 14 => "St Evrard", 15 => "Assomption", 16 => "St Armel", 17 => "St Hyacinthe", 18 => "Ste Hélène", 19 => "St Jean Eudes", 20 => "St Bernard", 21 => "St Christophe", 22 => "St Fabrice", 23 => "Ste Rose de Lima", 24 => "St Barthélémy", 25 => "St Louis", 26 => "Ste Natacha", 27 => "Ste Monique", 28 => "St Augustin", 29 => "Ste Sabine", 30 => "St Fiacre", 31 => "St Aristide");
        break;
      case     9: $saints = array( 1 => "St Gilles", 2 => "Ste Ingrid", 3 => "St Grégoire", 4 => "Ste Rosalie", 5 => "Ste Raïssa", 6 => "St Bertrand", 7 => "Ste Reine", 8 => "Nativité de N-D", 9 => "St Alain", 10 => "Ste Inès", 11 => "St Adolphe", 12 => "St Apollinaire", 13 => "St Aimé", 14 => "La Ste-Croix", 15 => "St Roland", 16 => "Ste Edith", 17 => "St Renaud", 18 => "Ste Nadège", 19 => "Ste Emilie", 20 => "St Davy", 21 => "St Matthieu", 22 => "St Maurice", 23 => "St Constant", 24 => "St Thècle", 25 => "St Hermann", 26 => "St Côme - Damien", 27 => "St Vincent de Paul", 28 => "St Venceslas", 29 => "St Michel", 30 => "St Jérôme");
        break;
      case  10: $saints = array( 1 => "Ste Thérèse de l'E-J", 2 => "St Léger", 3 => "St Gérard", 4 => "St François d'Assise", 5 => "Ste Fleur", 6 => "St Bruno", 7 => "St Serge", 8 => "Ste Pélagie", 9 => "St Denis", 10 => "St Ghislain", 11 => "St Firmin", 12 => "St Wilfrid", 13 => "St Géraud", 14 => "St Juste", 15 => "Ste Thérèse d'Avila", 16 => "Ste Edwige", 17 => "St Baudouin", 18 => "St Luc", 19 => "St René", 20 => "Ste Adeline", 21 => "Ste Céline", 22 => "Ste Elodie", 23 => "St Jean de Capistran", 24 => "St Florentin", 25 => "St Crépin", 26 => "St Dimitri", 27 => "Ste Emeline", 28 => "St Simon - Juda", 29 => "St Narcisse", 30 => "St Bienvenu", 31 => "St Quentin");
        break;
      case 11: $saints = array( 1 => "Toussaint", 2 => "Défunts", 3 => "St Hubert", 4 => "St Charles", 5 => "Ste Sylvie", 6 => "Ste Bertille", 7 => "Ste Carine", 8 => "St Geoffroy", 9 => "St Théodore", 10 => "St Léon", 11 => "St Martin", 12 => "St Christian", 13 => "St Brice", 14 => "St Sidoine", 15 => "St Albert", 16 => "Ste Marguerite", 17 => "Ste Elisabeth", 18 => "Ste Aude", 19 => "St Tanguy", 20 => "St Edmond", 21 => "Prés. de Marie", 22 => "Ste Cécile", 23 => "St Clément", 24 => "Ste Flora", 25 => "Ste Catherine", 26 => "Ste Delphine", 27 => "St Séverin", 28 => "St Jacques de la Marche", 29 => "St Saturnin", 30 => "St André");
        break;
      case 12: $saints = array( 1 => "Ste Florence", 2 => "Ste Viviane", 3 => "St Xavier", 4 => "Ste Barbara", 5 => "St Gérald", 6 => "St Nicolas", 7 => "St Ambroise", 8 => "Imm. Conception", 9 => "St P. Fourier", 10 => "St Romaric", 11 => "St Daniel", 12 => "Ste Jeanne F.-C.", 13 => "Ste Lucie", 14 => "Ste Odile", 15 => "Ste Ninon", 16 => "Ste Alice", 17 => "St Gaël", 18 => "St Gatien", 19 => "St Urbain", 20 => "St Abraham", 21 => "St Pierre Canisius", 22 => "Ste Françoise-Xavière", 23 => "St Armand", 24 => "St Adèle", 25 => "Noël", 26 => "St Etienne", 27 => "St Jean", 28 => "St Innocents", 29 => "St David", 30 => "St Roger", 31 => "St Sylvestre");
        break;
      default: return("NA");
    }
    if(isset($saints[$day])) return($saints[$day]);
    else return("NA");
  }

  public function dlMeteoalarmDataFeed($country,$file) {
    if($country == '') return false;
    $country = strtolower($country);
    log::add(__CLASS__, 'info', " Downloading meteoAlarm feeds for $country");
    $url = "https://feeds.meteoalarm.org/feeds/meteoalarm-legacy-atom-$country";
    $xml = @file_get_contents($url);
    if($xml !== false) {
      $hdle = fopen($file, "wb");
      if($hdle !== FALSE) { fwrite($hdle, $xml); fclose($hdle); }
    }
    return $xml;
  }

  public function mergeTimeSlots($slots) {
    // Sort the slots based on type, level, and onset time
    usort($slots, function($a, $b) {
        return $a['type'] <=> $b['type']
            ?: $a['level'] <=> $b['level']
            ?: $a['onset'] <=> $b['onset'];
    });

    $result = [];
    $currentSlot = $slots[0]; // Start with the first slot

    foreach ($slots as $slot) {
        if ($slot['onset'] <= $currentSlot['expires'] && $slot['type'] === $currentSlot['type'] && $slot['level'] === $currentSlot['level']) {
            // Merge: Extend the expiration time
            $currentSlot['expires'] = max($currentSlot['expires'], $slot['expires']);
        } else {
            // Save the previous slot and start a new merge
            $result[] = $currentSlot;
            $currentSlot = $slot;
        }
    }

    // Add the last merged slot
    $result[] = $currentSlot;

    return $result;
}

  public function processXmlAlertInfo($xmlAlert, $language, &$alerts) {
    $nbAlert = 0;
    $t = time();
    $infoAlerts = [];
    // log::add(__CLASS__, 'info', "    Sent: [" .(string)$xmlAlert->sent ."]");
    foreach ($xmlAlert->info as $info) {
      $lang = substr((string)$info->language, 0, 2);
      if($language == $lang) {
        // log::add(__CLASS__, 'info', "Rech $language [" .$info->language ."]");
        $expires = strtotime($info->expires);
        // log::add(__CLASS__, 'info', " Event: [" .$info->event ."] Effective: " .$info->effective ." Expires: " .$info->expires ." " .(($expires < $t)?"Expiré":""));
        if($expires === false || $expires < $t) continue;
        // foreach ($info->area as $area) { echo "Area: " .$area->areaDesc ." ".$area->geocode->valueName ." " .$area->geocode->value .PHP_EOL;
        $awareness_level = 0; $awareness_type = 0;
        foreach ($info->parameter as $parameter) {
          if($parameter->valueName == 'awareness_level')
            $awareness_level = intval((string)$parameter->value);
          elseif($parameter->valueName == 'awareness_type')
            $awareness_type = intval((string)$parameter->value);
          // else log::add(__CLASS__, 'debug', "    Unprocessed parameter: [" .$parameter->valueName ."] [" .$parameter->value ."]");
        }
        if($awareness_level < 2) continue; // ignore niveau vert ?
        $description = str_replace(array("\n","\r",'"'),array('<br>','',''),(string)$info->description);
        $description = html_entity_decode($description);
        if(strlen($description) > 450)
          $description = substr($description,0,450) .' ...';
        
        // echo "Expires: " .gmdate('c', $expires) .PHP_EOL;
        $onset = strtotime($info->onset);
        $infoAlerts = [ "language" => (string)$info->language,
          "onset" => $onset,
          "expires" => $expires,
          "level" => $awareness_level,
          "type" => $awareness_type,
          "event" => (string)$info->event,
          // "instruction" => (string)$info->instruction,
          "description" => $description,
          "severity" => (string)$info->severity ];
        $nbAlert++;
      }
    }
    if($nbAlert) {
      $alerts['info'][] = $infoAlerts;
      $alerts['info'] = $this->mergeTimeSlots($alerts['info']);
    }
    return($nbAlert);
  }

  public function getMeteoalarmData($country, $province, $language) {
log::add(__CLASS__, 'info', "getMeteoAlarmData for $country / $province / $language");
    $changed = false;
    $file = __DIR__ ."/../../data/meteoalarm-$country.xml";
    if (file_exists($file) && (time() - filemtime($file)) < 600) { // TTL 10 minutes
      $xml = simplexml_load_file($file);
    } else {
      $xmlTxt = $this->dlMeteoalarmDataFeed($country,$file);
      if($xmlTxt === false) $xml = false;
      else $xml = simplexml_load_string($xmlTxt);
    }
    if($xml === false) {
      log::add(__CLASS__, 'debug', "  Unable to get MeteoAlarmdata for $country URL: $url");
      return $changed;
    }
      // Définir l'espace de noms utilisé dans le fichier XML
    $namespaces = $xml->getNamespaces(true);
      // Parcourir chaque entrée pour récupérer les cap:
    $alerts = [];
    $alerts['status'] = "OK";
    $alerts['updated'] = (string)$xml->updated;
    $alerts['country'] = $country;
    $nbErr = 0;
    if($province == '') {
      $areas = [];
      foreach ($xml->entry as $entry) {
          // Access CAP namespace ( Common Alerting Protocol )
        $cap = $entry->children($namespaces['cap']);
        $t = time();
        foreach ($entry->link as $link) {
          $area = html_entity_decode((string)$cap->areaDesc);
          $expires = strtotime($cap->expires);
          if($expires === false || $expires < $t) {
            log::add(__CLASS__, 'debug', "  Expired alert for: $area. " .date('c', $expires));
            continue;
          }
          if(strpos($area, ';') !== false)
            log::add(__CLASS__, 'error', "  Semicolon in areaName. $area will be ignored"); 
          else {
            $newArea = explode(',', $area);
            $newArea = array_unique($newArea);
            for($i=0;$i<count($newArea);$i++) {
              $newVal = trim($newArea[$i]);
              if(!in_array($newVal, $areas)) $areas[] = $newVal;
            }
          }
        }
      }
      sort($areas);
      $alerts['areaList'] = $areas;
      log::add(__CLASS__, 'debug', "  Areas list: " .implode(', ',$areas));
      $contents = json_encode($alerts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $this->setConfiguration('otherCountryAlerts', implode(",\n",$areas));
      $this->save(true);
    }
    else {
      $this->setConfiguration('otherCountryAlerts', '');
      $this->save(true);
      $provinces = explode(',', $province);
      $provinces = array_unique($provinces);
      foreach($provinces as $region) {
        $region = trim($region);
        if($region == '') continue;
        $currCapArea = ['name' => $region,'info' => [] ];
        $urls = [];
        foreach ($xml->entry as $entry) {
          $cap = $entry->children($namespaces['cap']); // Access cap namespace Common Alerting Protocol
          $t = time();
          $areas = explode(',',strtolower($cap->areaDesc));
          for($i=0;$i<count($areas);$i++) $areas[$i] = trim($areas[$i]);
          if(in_array(strtolower($region), $areas)) {
            if(strtotime($cap->expires) > $t) {
              if($currCapArea === null) $currCapArea = ['name' => (string)$cap->areaDesc,'info' => [] ];
              foreach ($entry->link as $link) {
                if(strpos($link['href'],'?geocode=') !== false) {
                  $area = (string)$link['title'];
                }
                else $area = $region;
                if($link['type'] == 'application/cap+xml') {
                  $url = (string)$link['href'];
                  if(!in_array($url,$urls)) {
                    $id = substr($url,strrpos($url, '/')+1);
                    $alertTxt = @file_get_contents($url);
log::add(__CLASS__, 'info', "  Downloading meteoAlarm info for $country / $region $id");
                    // echo "File_get_contents : $url [$id]" .PHP_EOL;
                    if($alertTxt === false) {
                      log::add(__CLASS__, 'debug', "    Unable to get MeteoAlarm feed : $url");
                      $nbErr++;
                    }
                    else {
                      $urls[] = $url;
                      if($country == 'franc e') {
                        $file = __DIR__ ."/../../data/meteoalarm-alert-$country-$region-$id.xml";
                        $hdle = fopen($file, "wb");
                        if($hdle !== FALSE) { fwrite($hdle, $alertTxt); fclose($hdle); }
                      }
                      $xmlAlert = simplexml_load_string($alertTxt);
                      $alert = $this->processXmlAlertInfo($xmlAlert, $language, $currCapArea);
                      if(!$alert && $language != 'en') {
                        $alert = $this->processXmlAlertInfo($xmlAlert, 'en', $currCapArea);
                        if(!$alert) {
                          $lg = ($language == 'en')?"en":"$language/en";
                          log::add(__CLASS__, 'info', "Languages $lg not found for weather alerts of $country");
                        }
                      }
                    }
                    break;
                  }
                  else log::add(__CLASS__, 'info', "    URL $url already processed");
                }
              }
            }
          }
        }
        log::add(__CLASS__, 'debug', "  $region nbInfo: " .count($currCapArea['info']));
        if($currCapArea !== null) $alerts['capArea'][] = $currCapArea;
        unset ($currCapArea);
      }
      if(!$nbErr) {
        $contents = json_encode($alerts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      }
      else {
        message::add(__CLASS__, "$country $nbErr erreurs de recuperation des alertes"); 
        $cmd = $this->getCmd(null,'MeteoalarmAlertsJson');
        if(is_object($cmd)) {
          $json = $cmd->execCmd(); // Recup old command value for changing status
          $json = str_replace(array('&quot;','&#34;'), '"', $json);
          $alerts = json_decode($json,true);
          $alerts['status'] = "NOK $nbErr errors fetching data for $country / $province";
          $contents = json_encode($alerts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
      }
      $loglevel = log::convertLogLevel(log::getLogLevel(__CLASS__));
      if($loglevel == 'debug') {
        $file = __DIR__ ."/../../data/meteoalarm-$country-" .str_replace(array("/",":"),'',$province) .".json";
        $hdle = fopen($file, "wb");
        if($hdle !== FALSE) { fwrite($hdle, $contents); fclose($hdle); }
      }
    }
    $contents = str_replace('"','&#34;',$contents);
    $len = strlen($contents);
    if($len > 3000) log::add(__CLASS__, 'error', "  Commande MeteoalarmAlertsJson lg: $len");
    $changed = $this->checkAndUpdateCmd('MeteoalarmAlertsJson', $contents) ||$changed;
    log::add(__CLASS__, 'debug', "  " .json_encode($alerts));
      // Update other commands
    $t = time();
    $MeteoalarmColorMax = 1; $MeteoalarmColorMaxNow = 1;
    $MeteoalarmList = array();
    $nbCapArea = count($alerts['capArea']);
    foreach($alerts['capArea'] as $capArea) {
      foreach($capArea['info'] as $info) {
        $level = $info['level']; $type = $info['type'];
        $onset = $info['onset']; $expires = $info['expires'];
        if($level > 1) {
          $list = self::$_vigilanceType[$type+100]['txt'] ." : " .self::$_vigilanceColors[$level]['desc'];
          if($nbCapArea > 1)  $list .= " : " .$capArea['name'];
          $MeteoalarmList[] = $list;
        }
        if($level > $MeteoalarmColorMax) $MeteoalarmColorMax = $level;
        if($level > $MeteoalarmColorMaxNow && $t >= $onset && $t < $expires) {
          $MeteoalarmColorMaxNow = $level;
        }
      }
    }
    $MeteoalarmList = implode(', ', array_unique($MeteoalarmList));
    $changed = $this->checkAndUpdateCmd('MeteoalarmColorMax', $MeteoalarmColorMax) ||$changed;
    $changed = $this->checkAndUpdateCmd('MeteoalarmColorMaxNow', $MeteoalarmColorMaxNow) ||$changed;
    $changed = $this->checkAndUpdateCmd('MeteoalarmList', $MeteoalarmList) ||$changed;
    return($changed);
  }

  public static function getIconFromCondition($_condition_id, $datasource, $_dayNight, $_templateImg) {
    if($datasource == 'openweathermap') {
      if($_templateImg == 0) {
        return "wi wi-owm-$_dayNight-$_condition_id";
      } else {
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
          if ($_dayNight == "day") return 'meteo-nuageux'; // Pas assez de nuages
          // if ($_dayNight == "day") return 'fortement-nuageux';
          else return 'meteo-nuit-nuage';
        }
        if ($_condition_id == 800) {
          if ($_dayNight == "day") return 'meteo-soleil';
          else return 'far fa-moon';
        }
        /*
        if ($_dayNight == "day") return 'meteo-soleil';
        else return 'far fa-moon';
         */
        if($_condition_id != '')
          log::add(__CLASS__, 'error', "Unknown $datasource condition : $_condition_id");
      }
    } elseif($datasource == 'weatherapi') {
      if($_templateImg == 0) {
        return "Wapi-$_condition_id-$_dayNight";
      } else {
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
        if($_condition_id != '')
          log::add(__CLASS__, 'info', "Unknown $datasource condition : $_condition_id");
      }
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
      throw new Exception(__("Les coordonnées doivent être renseignées", __FILE__));
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
      throw new Exception(__("Coordonnées incorrectes [$gps]: Latitude , longitude", __FILE__));
    }
  }

	public function preSave() {
		if (trim($this->getConfiguration('positionGps','')) == '') {
			$this->setConfiguration('positionGps', config::byKey('info::latitude') .' , ' .config::byKey('info::longitude'));
		}
		if (trim($this->getConfiguration('timezone','')) == '') {
			$this->setConfiguration('timezone', config::byKey('timezone','core'));
		}
	}

  public function preInsert() {
    $this->setIsVisible(1);
    $this->setIsEnable(1);
    $this->setConfiguration('templateWeatherForecast','plugin');
  }

  public function postUpdate() {
    $datasource = trim($this->getConfiguration('datasource', ''));
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

    $wfCmd = $this->getCmd(null, 'clouds');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Nuages', __FILE__));
    $wfCmd->setLogicalId('clouds');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('%');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setDisplay('generic_type', 'WEATHER_CLOUDINESS');
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

    if($datasource == 'weatherapi') { // only weather_api
      $wfCmd = $this->getCmd(null, 'uv_Hcur');
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__('Indice UV heure', __FILE__));
      $wfCmd->setLogicalId('uv_Hcur');
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setUnite('');
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setDisplay('generic_type', 'WEATHER_UV');
      $wfCmd->save();
    }


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

    $wfCmd = $this->getCmd(null, 'sunriseTs');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Timestamp lever du soleil', __FILE__));
    $wfCmd->setLogicalId('sunriseTs');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setOrder(51);
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
    $wfCmd->setOrder(52);
    $wfCmd->setDisplay('generic_type', 'WEATHER_SUNSET');
    $wfCmd->save();

    $wfCmd = $this->getCmd(null, 'sunsetTs');
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(0);
    }
    $wfCmd->setName(__('Timestamp coucher du soleil', __FILE__));
    $wfCmd->setLogicalId('sunsetTs');
    $wfCmd->setEqLogic_id($this->getId());
    $wfCmd->setUnite('');
    $wfCmd->setType('info');
    $wfCmd->setSubType('numeric');
    $wfCmd->setOrder(52);
    $wfCmd->setDisplay('generic_type', 'WEATHER_SUNSET');
    $wfCmd->save();

    $ord = 200;
    if($datasource == 'weatherapi') $nbDays = 3;
    else $nbDays = 5;;
    for($i = 0;$i < $nbDays; $i++) {
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

      if($datasource == 'weatherapi' && $i < 3) { // 3 cmds only weather_api
        $id = "uvMax_$i";
        $wfCmd = $this->getCmd(null, $id);
        if (!is_object($wfCmd)) {
          $wfCmd = new weatherForecastCmd();
          $wfCmd->setIsVisible(0);
        }
        $wfCmd->setName(__("Indice UV max J+$i", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setUnite('');
        $wfCmd->setType('info');
        $wfCmd->setSubType('numeric');
        $wfCmd->setOrder($ord++);
        $wfCmd->setDisplay('generic_type', "WEATHER_UV_$i");
        $wfCmd->save();
      }
    }

    $id = "H0Json4Widget";
    $wfCmd = $this->getCmd(null, $id);
    if (!is_object($wfCmd)) {
      $wfCmd = new weatherForecastCmd();
      $wfCmd->setIsVisible(1);
      $wfCmd->setIsHistorized(0);
      $wfCmd->setName(__("H0Json pour widget", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setType('info');
      $wfCmd->setSubType('string');
      $wfCmd->setTemplate('dashboard', __CLASS__ .'::Clock');
      $wfCmd->setTemplate('mobile', __CLASS__ .'::Clock');
      $wfCmd->setOrder(300);
      $wfCmd->save();
    }

    $country = trim($this->getConfiguration('meteoAlarmCountry',''));
    $province = trim($this->getConfiguration('meteoAlarmArea',''));
    if($country != '') {
      $id = "MeteoalarmAlertsJson";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Meteoalarm alertes Json", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('string');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }
      $id = "MeteoalarmColorMax";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Meteoalarm couleur alerte maximum", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('numeric');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }
      $id = "MeteoalarmColorMaxNow";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Meteoalarm couleur alerte maximum actuelle", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('numeric');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }
      $id = "MeteoalarmList";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Meteoalarm liste des alertes", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('string');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }
    }

    if(trim($this->getConfiguration('numDeptFr')) != '') {
      $ord = 400;
      $id = "VigilanceJson";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(1);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Vigilance - Json", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('string');
        $wfCmd->setTemplate('dashboard', __CLASS__ .'::VigilanceWF');
        $wfCmd->setTemplate('mobile', __CLASS__ .'::VigilanceWF');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }

      $id = "Vigilancelist";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
        $wfCmd->setIsHistorized(0);
        $wfCmd->setName(__("Vigilance - Général", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('string');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }

      $id = "Vigilancecolor_max";
      $wfCmd = $this->getCmd(null, $id);
      if (!is_object($wfCmd)) {
        $wfCmd = new weatherForecastCmd();
        $wfCmd->setIsVisible(0);
      }
      $wfCmd->setName(__("Vigilance - Niveau actuel maximum", __FILE__));
      $wfCmd->setLogicalId($id);
      $wfCmd->setEqLogic_id($this->getId());
      $wfCmd->setType('info');
      $wfCmd->setSubType('numeric');
      $wfCmd->setOrder($ord++);
      $wfCmd->save();

      foreach(self::$_vigilanceType as $i => $vig) {
        if($i > 100) break; // Valeurs de meteoalarm
        if($i == 10) continue; // Pas de météo des forêts pour le moment.
        $id = "Vigilancephenomenon_max_color_id$i";
        $wfCmd = $this->getCmd(null, $id);
        if (!is_object($wfCmd)) {
          $wfCmd = new weatherForecastCmd();
          $wfCmd->setIsVisible(0);
        }
        $wfCmd->setName(__("Vigilance - " .$vig['txt'] ." niveau", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('numeric');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();

        $id = "Vigilancephases$i";
        $wfCmd = $this->getCmd(null, $id);
        if (!is_object($wfCmd)) {
          $wfCmd = new weatherForecastCmd();
          $wfCmd->setIsVisible(0);
        }
        $wfCmd->setName(__("Vigilance - " .$vig['txt'] ." conditions", __FILE__));
        $wfCmd->setLogicalId($id);
        $wfCmd->setEqLogic_id($this->getId());
        $wfCmd->setType('info');
        $wfCmd->setSubType('string');
        $wfCmd->setOrder($ord++);
        $wfCmd->save();
      }
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
      $this->updateWeatherData(1,1);
    }
  }

  public static function dateTimezone($format,$ts,$timezone) {
    $dateTime = new DateTime();
    $dateTime->setTimezone(new DateTimeZone($timezone));
    $dateTime->setTimestamp($ts);
    return $dateTime->format($format);
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
    $templateIMG = 0;
    if($templateF == 'none') return parent::toHtml($_version);
    elseif($templateF == 'plugin') $templateFile = 'weatherForecast';
    elseif($templateF == 'pluginImg') { $templateFile = 'weatherForecast'; $templateIMG = 1; }
    elseif($templateF == 'custom') $templateFile = 'custom.weatherForecast';
    else $templateFile = substr($templateF,0,-5);
    if(substr($templateFile,-3) == "IMG" )  $templateIMG = 1; 
    $datasource = trim($this->getConfiguration('datasource', ''));
// log::add(__CLASS__, 'debug', __FUNCTION__ ." [" .$this->getName() ."](" .$this->getId() .") Template: $templateFile IMG: $templateIMG Datasource: $datasource");
    $replaceDay = [];
    if($templateIMG == 1) { 
      $replace['#displayIcon#'] = 'none'; $replace['#displayImage#'] = 'block';
      $replace['#wImg#'] = '104px'; $replace['#hImg#'] = '65px';
      $replaceDay['#displayIcon#'] = 'none'; $replaceDay['#displayImage#'] = 'block';
    } else {
      if($datasource == 'weatherapi') {
        $replace['#displayIcon#'] = 'none'; $replace['#displayImage#'] = 'block';
        $replace['#wImg#'] = '100px'; $replace['#hImg#'] = '100px';
        $replaceDay['#displayIcon#'] = 'none'; $replaceDay['#displayImage#'] = 'block';
      } else {
        $replace['#displayIcon#'] = 'block'; $replace['#displayImage#'] = 'none';
        $replace['#wImg#'] = '100px'; $replace['#hImg#'] = '100px';
        $replaceDay['#displayIcon#'] = 'block'; $replaceDay['#displayImage#'] = 'none';
      }
    }

    $version = jeedom::versionAlias($_version);
    $replace['#forecast#'] = '';
    $timezone = $this->getConfiguration('timezone',config::byKey('timezone','core'));
    if ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1) {
      if (file_exists( __DIR__ ."/../template/$_version/custom.forecast.html"))
        $forcast_template = getTemplate('core', $version, 'custom.forecast', __CLASS__);
      else $forcast_template = getTemplate('core', $version, 'forecast', __CLASS__);
      $dateTime = new DateTime();
      $dateTime->setTimezone(new DateTimeZone($timezone));
      $dateTime->setTimestamp(time());
      $replace['#localDateTime#'] = "Date et heure locale: " .date_fr($dateTime->format('l d-m-Y H:i (\U\T\CP)'));
      $replace['#timezone#'] = $dateTime->format('e (\U\T\CP)');
      $replace['#sunrise_sunset#'] = '';
      $sunrise = null; $sunset = null;
      $H0Cmd = $this->getCmd(null, 'H0Json4Widget');
      if(is_object($H0Cmd)) {
        $H0 = $H0Cmd->execCmd();
        $H0 = str_replace('&#34;', '"', $H0);
        $json = json_decode($H0,true);
        if($json != null) {
          $sunrise = $json['sunrise'];
          if($sunrise === false) {
            $replace['#sunrise#'] = 'never';
            $replace['#sunrise_sunset#'] = '<i title="Nuit polaire" class="icon far fa-moon"></i>';
          }
          elseif($sunrise === true) {
            $replace['#sunrise#'] = 'always';
            $replace['#sunrise_sunset#'] = '<i title="Jour polaire" class="icon far fa-sun icon_yellow"></i>';
          }
          else {
            $replace['#sunrise_sunset#'] = '<i title="Lever - Coucher du soleil" class="fas fa-sun icon_yellow"></i> ';
            $dateTime->setTimestamp($sunrise);
            $replace['#sunrise#'] = $dateTime->format('H:i');
            $replace['#sunrise_sunset#'] .= $replace['#sunrise#'];
          }
          $sunset = $json['sunset'];
          if($sunset === false) {
            $replace['#sunset#'] = 'never';
            $replace['#sunrise_sunset#'] = '<i title="Nuit polaire" class="icon far fa-moon"></i>';
          }
          elseif($sunset === true) {
            $replace['#sunset#'] = 'always';
            $replace['#sunrise_sunset#'] = '<i title="Jour polaire" class="icon far fa-sun icon_yellow"></i>';
          }
          else {
            $dateTime->setTimestamp($sunset);
            $replace['#sunset#'] = $dateTime->format('H:i');
            $replace['#sunrise_sunset#'] .= ' - ' .$replace['#sunset#'];
          }
        }
      }
      
      $dateTime->setTimestamp(time());
      $hour = $dateTime->format('G');
      $nbForecastDays = $this->getConfiguration('forecastDaysNumber', 5);
      for ($i = 0; $i < $nbForecastDays; $i++) {
        if($i == 0) {
          $condition = $this->getCmd(null, "condition_$i");
          if(is_object($condition)) {
            $val = $condition->execCmd();
            if($val == '') continue;
          }
          // if($hour == 23) continue; // Pas d'affichage si dernière heure du jour
        }
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
          if($sunrise === false) $dayNight = "night";
          else if($sunrise !== true) {
            $t = time();
            if($t < $sunrise || $t > $sunset) $dayNight = "night";
          }
          /*
if(1 || $this->getId() == 2271) {
  $txt = '';
  if($t < $sunrise) $txt .= "Now: " .self::dateTimezone('c',$t,$timezone) ." Day/Night $dayNight";
  $txt .= " Sunrise: " .self::dateTimezone('c',$sunrise,$timezone); 
  if($t >= $sunrise && $t < $sunset) $txt .= " Now: $t " .self::dateTimezone('c',$t,$timezone) ." Day/Night $dayNight";
  $txt .= " Sunset: " .self::dateTimezone('c',$sunset,$timezone); 
  if($t >= $sunset) $txt .= " Now: " .self::dateTimezone('c',$t,$timezone) ." Day/Night $dayNight";
  message::add(__CLASS__,$txt);
}
           */
        }
        $replaceDay['#icone#'] = is_object($conditionID) ? self::getIconFromCondition($conditionID->execCmd(),$datasource,$dayNight,$templateIMG) : '';
        $condition = $this->getCmd(null, "condition_$i");
        $replaceDay['#condition#'] = is_object($condition) ? $condition->execCmd() : '';
        $rainCmd = $this->getCmd(null, "rain_$i");
        if(is_object($rainCmd)) {
          $val = $rainCmd->execCmd();
          $rain = ($val>0)?$val .'mm':'';
        }
        else $rain = '';
        $replaceDay['#rain#'] = $rain;
        $uvMaxCmd = $this->getCmd(null, "uvMax_$i");
        if(is_object($uvMaxCmd)) {
          $val = $uvMaxCmd->execCmd();
          if($val != -1) $uvMax = "UV: $val";
          else $uvMax = '';
        }
        else $uvMax = '';
        $replaceDay['#uvMax#'] = $uvMax;

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

    $uvCmd = $this->getCmd(null, 'uv_Hcur');
    $uv_Hcur = is_object($uvCmd) ? $uvCmd->execCmd() : -1;
    $replace['#uv_Hcur#'] = (($uv_Hcur == -1)? '' : "UV: $uv_Hcur"); 

    $clouds = $this->getCmd(null, 'clouds');
    $replace['#clouds#'] = is_object($clouds) ? $clouds->execCmd() : '0';

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

    $condition_id = $this->getCmd(null, 'condition_id');
    if (is_object($condition_id)) {
      $dayNight = "day"; // day icon
      if($sunrise === false) $dayNight = "night";
      else if($sunrise !== true) {
        $t = time();
        if($t < $sunrise || $t > $sunset) $dayNight = "night";
      }
      $replace['#icone#'] = self::getIconFromCondition($condition_id->execCmd(), $datasource, $dayNight,$templateIMG);
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

      // Vigilances météo france
    $numDept = trim($this->getConfiguration('numDeptFr'));
    $country = trim($this->getConfiguration('meteoAlarmCountry',''));
    $replace['#vigilance#'] = '';
    if($numDept != '' && $country == '') {
      $maxColorCmd = $this->getCmd(null,'Vigilancecolor_max');
      if(is_object($maxColorCmd)) {
        $maxColor = $maxColorCmd->execCmd();
        if($maxColor > 0) {
          $prevVigRecup = trim(config::byKey('prevVigilanceRecovery', __CLASS__));
          if(date('Ymd') != substr($prevVigRecup,0,8)) {
            $img = 'VIGNETTE_NATIONAL_J1_500X500.png';
            $localFile = __DIR__ ."/../../data/$img";
            $ts1 = @filemtime($localFile);
            $img .= "?ts=" .@filemtime($localFile);
            $ts1 += 86400;
            $img2 = '';
          }
          else  {
            $img = 'VIGNETTE_NATIONAL_J_500X500.png';
            $localFile = __DIR__ ."/../../data/$img";
            $ts1 = @filemtime($localFile);
            $img .= "?ts=" .$ts1;
            $img2 = 'VIGNETTE_NATIONAL_J1_500X500.png';
            $localFile = __DIR__ ."/../../data/$img2";
            $ts2 = @filemtime($localFile);
            $img2 .= "?ts=" .$ts2;
            $ts2 += 86400;
          }
          // if($_version != 'mobile')
          $replace['#vigilance#'] = '<table border=0 style="border-spacing: 0px; width: 100%;">
        <tr style="background-color:transparent !important;"><td class="tableCmdcss" style="width:10%;text-align: center" title="Vigilance: ' .date_fr(date('l  d  F',$ts1)) .'<br>Collecte: ' .date('d-m-Y H:i:s',$ts1) .'"><a href="https://vigilance.meteofrance.fr/fr" target="_blank"><img style="width:70px" src="plugins/weatherForecast/data/' .$img .'"/></a></td>';
          foreach(self::$_vigilanceType as $i => $vig) {
            if($i > 100) break; // Valeurs de Météoalarm 
            if($i == 10) continue; // Météo des forêts
            $vigilance = $this->getCmd(null, "Vigilancephenomenon_max_color_id$i");
            if(is_object($vigilance))  {
              $col = $vigilance->execCmd();
              if(!is_numeric($col)) $col = 0;
            }
            else $col = 0;
            $replace['#vig'.$i.'Colors#'] = ' color: '.self::$_vigilanceColors[$col]['color'];
            $replace['#vig'.$i.'Icon#'] =  $vig['icon'];
            $desc = '';
            $phase = $this->getCmd(null, "Vigilancephases$i");
            if(is_object($phase))  {
              $txt = $phase->execCmd();
              foreach(self::$_vigilanceColors as $color) {
                $txt = str_replace($color['desc'] .":", "<i class='fa fa-circle' style='color:" .$color['color'] ."'></i>", $txt);
              }
              $txt = str_replace('.', "<br>", $txt);
              if($txt != '') {
                $desc = ": &nbsp;$txt";
              }
            }
            $replace['#vig'.$i.'Desc#'] = $vig['txt'] .$desc;
            if($col > 0) {
              if($i >= 1 && $i < 10) {
                $file = __DIR__ ."/../template/images/Vigilance$i.svg";
                $svg = @file_get_contents($file);
                if($svg === false) log::add(__CLASS__, 'debug', "  Unable to read SVG : $file");
                else {
                  $svg = str_replace('#888888', self::$_vigilanceColors[$col]['color'], $svg);
                  $replace['#vigilance#'] .= '<td class="tableCmdcss" style="width:10%;height:20px;text-align: center" title="' .$vig['txt'] .$desc .'">' .$svg .'</td>';
                }
              }
              else
                $replace['#vigilance#'] .= '<td class="tableCmdcss" style="width:10%;height:20px;text-align: center" title="' .$vig['txt'] .$desc .'"><i class="wi ' .$vig['icon'] .'" style="font-size: 24px;color: '.self::$_vigilanceColors[$col]['color'] .'"></i></td>';
            }
          }
          // Météo des forêts TODO
          $forest = $this->getCmd(null, "Vigilance_color_forest");
          if(is_object($forest))  {
            $col = $forest->execCmd();
            if($col > 0) {
              $desc = ': ' .self::$_vigilanceColors[$col]['desc'];
              $file = __DIR__ ."/../template/images/VigilanceFire.svg";
              $svg = @file_get_contents($file);
              if($svg === false) {
                log::add(__CLASS__, 'debug', "  Unable to read SVG : $file");
                $replace['#vigilance#'] .= '<td class="tableCmdcss" style="width:10%;height:20px;text-align: center" title="' .$vig['txt'] .$desc .'"><a href="https://meteofrance.com/meteo-des-forets" target="_blank"><i class="wi ' .$vig['icon'] .'" style="font-size: 24px;color: '.self::$_vigilanceColors[$col]['color'] .'"></i></a></td>';
              }
              else {
                $svg = str_replace('#888888', self::$_vigilanceColors[$col]['color'], $svg);
                $replace['#vigilance#'] .= '<td class="tableCmdcss" style="width:10%;height:20px;text-align: center" title="' .$vig['txt'] .$desc .'"><a href="https://meteofrance.com/meteo-des-forets" target="_blank">' .$svg .'</a></td>';
              }
            }
          }
          // Carte demain
          if($img2 != '' && $_version != 'mobile')
            $replace['#vigilance#'] .= '<td class="tableCmdcss" style="width:10%;text-align: center" title="Vigilance: ' .date_fr(date('l  d  F',$ts2)) .'"><a href="https://vigilance.meteofrance.fr/fr/demain" target="_blank"><img style="width:70px" src="plugins/weatherForecast/data/' .$img2 .'"/></a></td>';
        }
        else {
          $replace['#vigilance#'] = '<table border=0 style="border-spacing: 0px; width: 100%;"><tr style="background-color:transparent !important;"><td class="tableCmdcss" style="width:10%;text-align: center" title="Vigilances">Pas de données de vigilance pour le département: ' .$numDept .'</td>';
        }
        $replace['#vigilance#'] .= '</tr> </table> <span style="margin-left: 4px;font-size: 10px;font-style: italic;">Vigilances du département ' .$numDept .' créées à partir de données de Météo-France</span>';
      }
    }

      // vigilanceMeteoalarm
    $replace['#vigilanceMeteoalarm#'] = '';
    $country = trim($this->getConfiguration('meteoAlarmCountry',''));
    $province = trim($this->getConfiguration('meteoAlarmArea',''));
    $cmd = $this->getCmd(null,'MeteoalarmAlertsJson');
    if(is_object($cmd)) {
      $json = $cmd->execCmd();
      $json = str_replace('&#34;', '"', $json);
      $dec = json_decode($json,true);
      if($country != '' ) {
        if($province == '') {
          $replace['#vigilanceMeteoalarm#'] = '<i class="icon fas fa-exclamation-triangle icon_red"></i> Province non définie. ';
            if($dec !== null && isset($dec['areaList']) && count($dec['areaList']))
              $replace['#vigilanceMeteoalarm#'] .= "Des alertes existent pour: " .implode(', ',$dec['areaList']);
            else 
              $replace['#vigilanceMeteoalarm#'] .= "Pas d'alerte actuellement pour: $country";
        }
        else {
          if($dec === null) {
            $replace['#vigilanceMeteoalarm#'] = 'Unable to decode Json cmd';
          } else {
            $nbArea = count($dec['capArea']);
            $txtAlarm = '';
            foreach($dec['capArea'] as $capArea) {
              if($txtAlarm != '') $txtAlarm .= '<td style="border-right:1px #3C73A5 solid"></td>';
              $nbInfo = count($capArea['info']);
              if($nbInfo) {
                for($j=101;$j<116;$j++) {
                  if($j == 111) continue;
                  $idx = $j - 100;
                  $imgFile = __DIR__ ."/../template/images/AlertCAP$idx.svg";
                  for($i=0;$i<$nbInfo;$i++) {
                    if($idx == $capArea['info'][$i]['type']) {
                      $level = $capArea['info'][$i]['level'];
                      $desc = '';
                      if(isset($capArea['info'][$i]['event'])) $desc .= $capArea['info'][$i]['event'];
                      if(isset($capArea['info'][$i]['description'])) {
                        $desc .= (($desc == '')? '' : '<br>') .$capArea['info'][$i]['description'];
                      }
                      $title = $country .' / ' .$capArea['name'] .' ' .self::$_vigilanceType[$j]['txt'];
                      if(time() < $capArea['info'][$i]['onset'])
                        $title .= ' de ' .self::dateTimezone('d-m-Y H:i',$capArea['info'][$i]['onset'],$timezone) .' à ' .self::dateTimezone('d-m-Y H:i (\U\T\CP)',$capArea['info'][$i]['expires'],$timezone);
                      else
                        $title .= " jusqu'à " .self::dateTimezone('d-m-Y H:i (\U\T\CP)',$capArea['info'][$i]['expires'],$timezone);
                      $title .= '<br>' .$desc;
                      $svg = @file_get_contents($imgFile);
                      if($svg === false) {
                        log::add(__CLASS__, 'warning', "$imgFile not found");
                        $icon = '<span style="display:flex;flex:none;padding:.5rem;margin:.25rem;background-color:' .self::$_vigilanceColors[$level]['color'] .';color:' .self::$_vigilanceColors[$level]['hiColor'].';border-radius:999px;" title="' .$title .'">' .self::$_vigilanceType[$j]['txt'] .'</span>';
                      }
                      else {
                        $svg = str_replace('#888888', self::$_vigilanceColors[$level]['hiColor'], $svg);
                        $icon = '<span style="display:flex;flex:none;padding:.5rem;margin:.25rem;background-color:' .self::$_vigilanceColors[$level]['color'] .';border-radius:999px;" title="' .$title .'">' .$svg .'</span>';
                      }
                      $txtAlarm .= '<td style="padding-left:4px;padding-right:4px;">' .$icon ."</td>";
                    }
                  }
                }
              }
              else {
                $svg = "<img src=\"plugins/weatherForecast/core/template/images/AlertCAP0.svg\" alt=\"No alert\"/>";
                $title = $country .' / ' .$capArea['name'] .'<br>' .self::$_vigilanceType[100]['txt'];
                $icon = '<span style="display:flex;flex:none;padding:.5rem;margin:.25rem;background-color:' .self::$_vigilanceColors[1]['color'] .';border-radius:999px;" title="' .$title .'">' .$svg .'</span>';
                $txtAlarm .= '<td style="padding-left:4px;padding-right:4px;">' .$icon ."</td>";
              }
            }
            if($txtAlarm != '') {
              if($dec['status'] == "OK")
                $message = "Alertes créées à partir de données de <a target=\"_blank\" href=\"https://meteoalarm.org/en/live\">Meteoalarm.org</a>";
              else
                $message = "<i  style=\"font-size:14px;\" class=\"icon fas fa-exclamation-triangle icon_red\"></i> TITI" .trim(substr($dec['status'],3));
              $txtAlarm .= "<td style=\"flex:auto;padding-left:4px;padding-right:4px;font-size:10px !important;font-style:italic;line-height:normal;text-align:left;\">$message</td>";

              $replace['#vigilanceMeteoalarm#'] = "<table border=0 style=\"width:100%\" title=\"Vigilances Meteoalarm\"><tr style=\"display:flex;align-content:center;background-color:transparent !important\">$txtAlarm</tr></table>";
            }
            else $replace['#vigilanceMeteoalarm#'] = '';
          }
        }
      }
    }
    if($datasource == 'weatherapi')
      $replace['#poweredBy#'] = 'Powered by <a target="_blank" href="https://www.weatherapi.com/" title="Free Weather API">WeatherAPI.com</a>';
    else
      $replace['#poweredBy#'] = 'Powered by <a target="_blank" href="https://openweathermap.org/">OpenWeather</a> &nbsp; <a target="_blank" href="https://dashboard.openweather.co.uk/dashboard">Weather Dashboard</a>';
    if (file_exists( __DIR__ ."/../template/$_version/$templateFile.html"))
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $templateFile, __CLASS__)));
    else
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, __CLASS__, __CLASS__)));
  }

  public function fetchOpenweather($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 40);
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
    $nbForecastDays = 5;
    if(isset($weather['cod']) && $weather['cod'] != 200) {
      if($_updateConfig == 1) { // memo dans la config de l'équipement
        $this->setConfiguration('lat', '');
        $this->setConfiguration('lon', '');
        $this->setConfiguration('ville', '');
        $this->setConfiguration('timezoneApi', '');
        $this->setConfiguration('country', '');
        $this->setConfiguration('forecastDaysNumber', 0);
      }
      $errMsg = "Erreur: " .$weather['cod'] ." " .$weather['message'];
      $this->setConfiguration('otherInfo', $errMsg);
      log::add(__CLASS__, 'warning', $errMsg);
      $this->save(true);
      return;
    }
    if($_updateConfig == 1) { // memo dans la config de l'équipement
      $lat = $weather['coord']['lat'];
      $lon = $weather['coord']['lon'];
      $this->setConfiguration('lat', $weather['coord']['lat']);
      $this->setConfiguration('lon', $weather['coord']['lon']);
      $this->setConfiguration('ville', $weather['name']);
      if(isset($weather['sys']['country']))
        $this->setConfiguration('country', $weather['sys']['country']);
      else
        $this->setConfiguration('country', '');
      $decal = $weather['timezone'];
      $absDecal = abs($decal);
      if($decal != $absDecal) $sign = '-'; else $sign ='+';
      $h = sprintf('%02d',intval($absDecal/3600));
      $min = sprintf('%02d',($absDecal % 3600)/60);
      $this->setConfiguration('otherInfo', "CityID : " .$weather['id']);
      $this->setConfiguration('timezoneApi', "UTC$sign$h:$min");
      $this->setConfiguration('forecastDaysNumber', $nbForecastDays);
      $this->save(true);
    }
    log::add(__CLASS__, 'debug', $url . ' : ' . substr(json_encode($weather),0,100));
    // log::add(__CLASS__, 'debug', json_encode($weather));
    $H0array['updated_on'] = $weather['dt'];
    $weatherTemp = round($weather['main']['temp'], 1);
    $weatherDesc = ucfirst($weather['weather']['0']['description']);
    $changed = $this->checkAndUpdateCmd('temperature', $weatherTemp) || $changed;
    $changed = $this->checkAndUpdateCmd('humidity', $weather['main']['humidity']) || $changed;
    $changed = $this->checkAndUpdateCmd('pressure', $weather['main']['pressure']) || $changed;
    $changed = $this->checkAndUpdateCmd('condition', $weatherDesc) || $changed;
    $changed = $this->checkAndUpdateCmd('condition_id', $weather['weather'][0]['id']) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_speed', round($weather['wind']['speed'] * 3.6)) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_direction', $weather['wind']['deg']) || $changed;
    $changed = $this->checkAndUpdateCmd("uv_Hcur", -1) || $changed; // not available with OWM
    $windGust =  (isset($weather['wind']['gust'])) ? round($weather['wind']['gust'] * 3.6) : 0;
    $changed = $this->checkAndUpdateCmd('wind_gust', $windGust) || $changed;
    $changed = $this->checkAndUpdateCmd('clouds', $weather['clouds']['all']) || $changed;
    $rain1h =  (isset($weather['rain']['1h'])) ? $weather['rain']['1h'] : 0;
    $changed = $this->checkAndUpdateCmd('rain', $rain1h) || $changed;
    $snow1h =  (isset($weather['snow']['1h'])) ? $weather['snow']['1h'] : 0;
    $dayNight = "day"; // day icon
    if($H0array['sunrise'] === false) $dayNight = "night";
    else if($H0array['sunrise'] !== true) {
      $t = time();
      if($t < $H0array['sunrise'] || $t > $H0array['sunset']) $dayNight = "night";
    }
    $icon = self::getIconFromCondition($weather['weather'][0]['id'], 'openweathermap', $dayNight,1);

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
    $timezone = $this->getConfiguration('timezone',config::byKey('timezone','core'));
    $dateTime = new DateTime();
    $dateTimeZone = new DateTimeZone($timezone);
    $dateTime->setTimezone($dateTimeZone);
    $dateTime->setTimestamp(time());
    $hour = $dateTime->format('G');
// log::add(__CLASS__, 'info', date('Y-m-d H:i:s') ." " .$this->getName() ." : 1st forecast " .$forecast['list'][0]['dt_txt'] ." Dt : " .date('Y-m-d H:i:s', $forecast['list'][0]['dt']) ." Timezone : ".$forecast['city']['timezone']);
    $tsNow = time();
    for ($i = 0; $i < $nbForecastDays; $i++) {
      $changed = $this->checkAndUpdateCmd("uvMax_$i", -1) || $changed; // not available with OWM
      $ts = strtotime("+{$i} day");
      $dateRech = new DateTime("+{$i} day 12:00:00", $dateTimeZone);
      $date = $dateRech->format('Y-m-d');
      $midday = $dateRech->format('Y-m-d 12:00:00');
      $maxTemp = -666;
      $minTemp = 666;
      $condition_id = 0;
      $condition = 0;
      $rain = 0;

      foreach ($forecast['list'] as $weather) {
        $sDate = substr($weather['dt_txt'],0,10);
        if ($date != $sDate) { // autre jour
          // log::add(__CLASS__, 'debug', "Another day");
          continue;
        }
        $dateTime = new DateTime($weather['dt_txt'], $dateTimeZone);
        $tsDt_txt = $dateTime->getTimestamp();

        $Tmin = round($weather['main']['temp_min'], 1);
        $Tmax = round($weather['main']['temp_max'], 1);
        // log::add(__CLASS__, 'debug', $weather['dt_txt'] ." [$sDate] Weather temp: " .$weather['main']['temp']));
        // log::add(__CLASS__, 'debug', "Weather date: $sDate");
        if ($minTemp > $Tmin) $minTemp = $Tmin;
        if ($maxTemp < $Tmax) $maxTemp = $Tmax;

          // cumul des pluies de la journée
        if(isset($weather['rain']['3h'])) {
          $rain += $weather['rain']['3h'];
          // log::add(__CLASS__, 'debug', "$i " .date('Y-m-d H:i',$tsDt_txt) ." Rain: $rain");
        }

        if($i == 0 && $hour > 8 && $tsDt_txt > $tsNow) { // plage de l'heure suivante recherchée
          $dateTime->setTimestamp($tsDt_txt);
          $title = $dateTime->format('G') ."h - ";
          $dateTime->setTimestamp($tsDt_txt +10800);
          $title .= $dateTime->format('G') ."h";
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
          $middayDate = $dateRech->format('D. j');
          $title = date_fr($middayDate);
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

  public function fetchWeatherApi($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 40);
    $content = curl_exec($ch);
    if ($content === false) {
      log::add(__CLASS__,'warning', __FUNCTION__ ." $url Failed curl_error: (" .curl_errno($ch) .") " .curl_error($ch));
      curl_close($ch); unset($ch);
      return(null);
    }
    curl_close($ch); unset($ch);
    return($content);
  }

  public function updateWeatherApi($_updateConfig, $lat, $lon, $lang, &$H0array) {
    $changed = false;
    $apikeyWapi = trim(config::byKey('apikeyWapi', __CLASS__));
    if(trim($apikeyWapi) == '' )
      throw new Exception(__("La clé API Weather API n'est pas renseignée.", __FILE__));
    $nbdays = 5;
    $url = "http://api.weatherapi.com/v1/forecast.json?key=$apikeyWapi&q=$lat,$lon&lang=$lang&days=$nbdays&aqi=yes&alerts=yes";
    $urlAnon = "http://api.weatherapi.com/v1/forecast.json?key=myKey&q=" .sprintf("%.1f",$lat) ."," .sprintf("%.1f",$lon) ."&lang=$lang&days=$nbdays&aqi=yes&alerts=yes";

    $content = $this->fetchWeatherApi($url);
    if($content == null) return;
    $datas = json_decode($content,true);
    if($datas == null) {
      log::add(__CLASS__, 'warning', __FUNCTION__ ." L:" .__LINE__ ." Json_decode error : " .json_last_error_msg() ." [" . substr($content,0,50) ."] ... [" .substr($content,-50) ."]");
      return;
    }
    $nbForecastDays = 3; // count($datas['forecast']['forecastday']);
    
    if(isset($datas['error'])) {
      log::add(__CLASS__, 'info', "ID:" .$this->getId() ." " .$url . ' : ' . json_encode($datas));
      $file = __DIR__ ."/../../data/weatherApi-error-" .$this->getId() .".json";
      $hdle = fopen($file, "wb");
      if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
      else log::add(__CLASS__, 'info', "Unable to write $file");
        // {"error":{"code":1006,"message":"No matching location found."}}
      if($_updateConfig == 1) { // memo dans la config de l'équipement
        if(isset($datas['error'])) {
          $this->setConfiguration('lat', '');
          $this->setConfiguration('lon', '');
          $this->setConfiguration('ville', '');
          $this->setConfiguration('country', '');
          $errMsg = "Erreur: " .$datas['error']['code'] ." " .$datas['error']['message'];
          $this->setConfiguration('otherInfo', $errMsg);
          log::add(__CLASS__, 'warning', $errMsg);
          $this->setConfiguration('timezoneApi', '');
          $this->setConfiguration('forecastDaysNumber', 0);
          $this->save(true);
        }
      }
      return;
    }
    
    $datas =  array_merge( ['state' => 'ok', 'datetime' => date('c') ],$datas);
    $file = __DIR__ ."/../../data/weatherForecastAPI-" .$this->getId() .".json";
    $hdle = fopen($file, "wb");
    if($hdle !== FALSE) {
      fwrite($hdle, json_encode($datas,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      fclose($hdle);
    }
    else message::add(__CLASS__, "Unable to write $file");
    log::add(__CLASS__, 'debug', $urlAnon . ' : ' . substr(json_encode($datas),0, 100) .'...');
    $current = $datas['current'];
    log::add(__CLASS__, 'debug', "  Data updated on " .$current['last_updated'] ." Condition: " .$current['condition']['text']); // ." Icon: " .$current['condition']['icon']);
    $H0array['updated_on'] = strtotime($current['last_updated']);

    if($_updateConfig == 1) { // memo dans la config de l'équipement
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
      $this->setConfiguration('timezoneApi', $datas['location']['tz_id']);
      $this->setConfiguration('forecastDaysNumber', $nbForecastDays);
      $this->save(true);
    }
    $weatherTemp = $current['temp_c'];
    $weatherDesc = $current['condition']['text'];
    $changed = $this->checkAndUpdateCmd('temperature', $weatherTemp) || $changed;
    $changed = $this->checkAndUpdateCmd('humidity', $current['humidity']) || $changed;
    $changed = $this->checkAndUpdateCmd('pressure', $current['pressure_mb']) || $changed;
    $changed = $this->checkAndUpdateCmd('condition', $weatherDesc) || $changed;
    $changed = $this->checkAndUpdateCmd('condition_id', $current['condition']['code']) || $changed;
    $windSpeed =  (isset($current['wind_kph'])) ? round($current['wind_kph']) : 0;
    $changed = $this->checkAndUpdateCmd('wind_speed', $windSpeed) || $changed;
    $changed = $this->checkAndUpdateCmd('wind_direction', $current['wind_degree']) || $changed;
    $changed = $this->checkAndUpdateCmd('rain', round($current['precip_mm'],1)) || $changed;
    $changed = $this->checkAndUpdateCmd('clouds', $current['cloud']) || $changed;
    $windGust =  (isset($current['gust_kph'])) ? round($current['gust_kph']) : 0;
    if($windGust < 30) $windGust = 0;
    $changed = $this->checkAndUpdateCmd('wind_gust', $windGust) || $changed;
    $dayNight = "day"; // day icon
    if($H0array['sunrise'] === false) $dayNight = "night";
    else if($H0array['sunrise'] !== true) {
      $t = time();
      if($t < $H0array['sunrise'] || $t > $H0array['sunset']) $dayNight = "night";
    }
    $icon = self::getIconFromCondition($current['condition']['code'], 'weatherapi', $dayNight,1);
    $H0array['weather'] = ['icon' => $icon, 'desc' => $weatherDesc];
    $H0array['T'] = ['value' => $weatherTemp, 'windchill' => $current['feelslike_c']];
    $H0array['wind'] = ['speed' => $windSpeed, 'gust' => $windGust,
                        'direction' => $current['wind_degree'],
                        'icon' =>  $this->convertDegrees2Compass($current['wind_degree'],0) ];
    $H0array['humidity'] = $current['humidity'];
    $H0array['sea_level'] = $current['pressure_mb'];
    $H0array['rain'] = ['1h' => $current['precip_mm']];
    $H0array['snow'] = ['1h' => 0];
    $H0array['clouds'] = $current['cloud'];
    /*
    $this->checkAndUpdateCmd('visibility_Hcur', $current['vis_km']);
     */
    $this->checkAndUpdateCmd('uv_Hcur', $current['uv']);
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
            $this->checkAndUpdateCmd("forecast_H{$numCmd}_JSON", str_replace('"','&#34;',json_encode($forecasthour[$i],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
            $numCmd++;
          }
        }
      }
    }
     */

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
      $maxUV = 0;
      for($k=0; $k<24; $k++) $maxUV = max( $forecastday[$i]['hour'][$k]['uv'], $maxUV);
      $this->checkAndUpdateCmd("uvMax_$i", $maxUV);
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
        $changed = $this->checkAndUpdateCmd("forecast_D{$i}_JSON", str_replace('"','&#34;',json_encode($forecastday[$i],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))) || $changed;
      }
    }
    return $changed;
  }

  // updateConfig 0 : immediatly / 1 : just after creating commands / 2 : cronDayly
  public function updateWeatherData($_updateConfig = 0, $_updateVig = 0) {
    $changed = false;
    $gps = trim($this->getConfiguration('positionGps', ''));
    $coord = explode(',', $gps);
    if(count($coord) > 1) {
      $lat = trim($coord[0]);
      $lon = trim($coord[1]);
    } else {
      throw new Exception(__("Coordonnées incorrectes [$gps]", __FILE__));
    }
    $datasource = trim($this->getConfiguration('datasource', ''));
    $H0Cmd = $this->getCmd(null, 'H0Json4Widget');
    if(is_object($H0Cmd)) {
      $H0 = $H0Cmd->execCmd();
      $H0 = str_replace('&#34;', '"', $H0);
      $H0array = json_decode($H0,true);
      if($H0array === null) $H0array = [];
      else {
        if(!isset($H0array['datasource']) || $datasource != $H0array['datasource']) { // Changement datasource
          unset($HOarray);
          $H0array = [];
        }
      }
    }
    else $H0array = [];
    $sun_info = date_sun_info(time(), $lat, $lon);
    $sunrise = $sun_info['sunrise'];
    $changed = $this->checkAndUpdateCmd('sunriseTs', $sunrise) || $changed;
    $H0array['sunrise'] = $sunrise;
    if($sunrise === false) $sunrise = 0;
    elseif($sunrise === true) $sunrise = 1;
    else $sunrise = date('Gi',$sunrise);
    $changed = $this->checkAndUpdateCmd('sunrise', $sunrise) || $changed;
    $sunset = $sun_info['sunset'];
    $changed = $this->checkAndUpdateCmd('sunsetTs', $sunset) || $changed;
    $H0array['sunset'] = $sunset;
    if($sunset === false) $sunset = 0;
    elseif($sunset === true) $sunset = 1;
    else $sunset = date('Gi',$sunset);
    $changed = $this->checkAndUpdateCmd('sunset', $sunset) || $changed;
    $timezone = $this->getConfiguration('timezone',config::byKey('timezone','core'));
    $dateTime = new DateTime();
    $dateTime->setTimezone(new DateTimeZone($timezone));
    $dateTime->setTimestamp(time());
    $date_z = $dateTime->format('z');
    $date_L = $dateTime->format('L');
    $date_n = $dateTime->format('n');
    $date_j = $dateTime->format('j');
    $H0array['dayOfTheYear'] = ($date_z+1) .'/' .($date_L? '366' : '365');
    $H0array['saintOfTheDay'] = self::saintOfTheDay($date_n,$date_j);
    $H0array['timezone'] = $timezone;
      // update vigilances if department is informed 
    $changed = $this->updateVigilanceMeteoFrance() || $changed;
      // update Meteoalarm vigilances if country and province are informed 
    $country = strtolower(trim($this->getConfiguration('meteoAlarmCountry','')));
    $province = trim($this->getConfiguration('meteoAlarmArea',''));
    $language = substr(config::byKey('language','core', 'fr_FR'),0,2);
    if($country != '') {
      if($_updateVig == 1)
        $changed = $this->getMeteoalarmData($country, $province, $language) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('MeteoalarmAlertsJson', '[]') || $changed;

    if($_updateConfig != 2) { // Not called by cronDaily. Request to sources
      $lang = substr(config::byKey('language','core', 'fr_FR'),0,2);
      if($datasource == "openweathermap") {
        $H0array['datasource'] = "openweathermap";
        $changed = $this->updateWeatherOwm($_updateConfig, $lat, $lon, $lang, $H0array) || $changed;
      }
      else if($datasource == "weatherapi") {
        $H0array['datasource'] = "weatherapi";
        $changed = $this->updateWeatherApi($_updateConfig, $lat, $lon, $lang, $H0array) || $changed;
      }
      else {
        throw new Exception(__("Type de données inconnu. $datasource", __FILE__));
      }
    }
    $contents = str_replace('"','&#34;',json_encode($H0array,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $changed = $this->checkAndUpdateCmd("H0Json4Widget", $contents) || $changed;
    if($_updateConfig == 2 || $changed) $this->refreshWidget();
  }

  public static function downloadVigDataArchive($fileUrl,$json,$fileResu) {
    log::add(__CLASS__, 'debug', "  " .__FUNCTION__ ." Fetching archive data Url: $fileUrl");
    $curl = curl_init();
    curl_setopt_array($curl, array( CURLOPT_URL => $fileUrl,
      CURLOPT_SSL_VERIFYPEER => false, CURLOPT_RETURNTRANSFER => true));
    $contents = curl_exec($curl);
    $curl_error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if($contents !== false && $http_code == 200) {
      if($json) {
        $dec = json_decode($contents,true);
        $jsonError = json_last_error();
        if($jsonError != JSON_ERROR_NONE) {
          log::add(__CLASS__, 'warning', "  Unable to get data from MeteoFrance. Json error: ($jsonError) ".json_last_error_msg());
          return 1;
        }
        if(isset($dec['product']['version_vigilance'])) {
          $version = $dec['product']['version_vigilance'];
          if($version != "V6") {
            log::add(__CLASS__, 'warning', "  Unknown vigilance_version $version. Unable to process data. Plugin must be updated.");
            return 1;
          }
        }
        else {
          log::add(__CLASS__, 'warning', "  Vigilance_version not set. Fetch error ?");
          return 1;
        }
      }
      $hdle = fopen($fileResu, "wb");
      if($hdle !== FALSE) { fwrite($hdle, $contents); fclose($hdle); }
      else {
        log::add(__CLASS__, 'warning', "  Unable to open file for writing: $fileResu");
        return 1;
      }
    }
    else {
      log::add(__CLASS__, 'warning', "  Unable to fetch $fileUrl. Http_code: $http_code Curl_error: $curl_error");
      return 1;
    }
    // recherche du timestamp à appliquer au fichier dans $fileUrl
    // 2023/07/22/040101
    // 0123 56 89 1 3 5
    $lastSlash = strrpos($fileUrl, '/');
    $date = substr($fileUrl, $lastSlash - 17, 17);
    $timestamp = strtotime(substr($date,0,4) .'-' .substr($date,5,2) .'-' .substr($date,8,2) .' '
      .substr($date,11,2) .':' .substr($date,13,2) .':' .substr($date,15,2) .'Z');
    // message::add(__FUNCTION__, "Date: [$date] " .date('d-m-Y H:i:s',$timestamp));
    touch($fileResu,$timestamp);
    return 0;
  }

  public static function getVigilanceDataArchiveMF() {
    log::add(__CLASS__, 'debug', __FUNCTION__ ." http://storage.gra.cloud.ovh.net/v1/AUTH_555bdc85997f4552914346d4550c421e/gra-vigi6-archive_public");
    $fileAlert = __DIR__ ."/../../data/CDP_CARTE_EXTERNE.json";
    $fileAlertTxt = __DIR__ ."/../../data/CDP_TEXTES_VIGILANCE.json";
    $fileVignetteJ = __DIR__ ."/../../data/VIGNETTE_NATIONAL_J_500X500.png";
    $fileVignetteJ1 = __DIR__ ."/../../data/VIGNETTE_NATIONAL_J1_500X500.png";
    $recupAPI = 0;
      // Recover vigilances with MF archives
    if(date('H') < 6) $timeRecup = strtotime("yesterday");
    else $timeRecup = time();
    $dateRecup = date('Y/m/d',$timeRecup);
    $url = "http://storage.gra.cloud.ovh.net/v1/AUTH_555bdc85997f4552914346d4550c421e/gra-vigi6-archive_public/$dateRecup/";
    // log::add(__CLASS__, 'debug', "  Fetching MF archives $url");
    $doc = new DOMDocument();
    libxml_use_internal_errors(true); // disable xmlWarning
    $doc->preserveWhiteSpace = false;
    if(@$doc->loadHTMLFile($url) !== false ) {
      $xpath = new DOMXpath($doc);
      $subdir = $xpath->query('//html/body/table/tr[@class="item subdir"]/td/a');
      $nb = count($subdir);
      $prevRecup = trim(config::byKey('prevVigilanceRecovery', __CLASS__));
      $prevRecup = substr($prevRecup,0,-1);
// echo "Found: $nb PrevRecup: $prevRecup Daterecup: $dateRecup<br>";
      $latest = '0';
      $latestFileAlert = ''; $latestFileVignetteJ = '';
      $latestFileVignetteJ1 = ''; $latestFileAlertTxt = '';
      $filesOK = file_exists($fileAlert) && file_exists($fileVignetteJ) && file_exists($fileVignetteJ1) && file_exists($fileAlertTxt);
      log::add(__CLASS__, 'debug', "  Files present: " .($filesOK?"OK":"NOK"));
      log::add(__CLASS__, 'debug', "  Nb : $nb");
      for($i=0;$i<$nb;$i++) {
        $val = $subdir[$i]->getAttribute('href');
        $val2 = substr($val,0,-1);
        $url2 = $url .$val2;
        $currRecup = date('Ymd',$timeRecup);
        $newRecup = $currRecup.$val2;
        if($prevRecup >= $newRecup && $filesOK) {
          log::add(__CLASS__, 'debug', "  Data already processed: $dateRecup/$val2");
          continue;
        }
        $latest = $val2;
        
// echo "New Data Recup: $dateRecup/$val2<br>";
        log::add(__CLASS__, 'debug', "  Fetching MF archives $url2");
        $doc2 = new DOMDocument();
        libxml_use_internal_errors(true); // disable xmlWarning
        $doc2->preserveWhiteSpace = false;
        if(@$doc2->loadHTMLFile($url2) !== false ) {
          $xpath2 = new DOMXpath($doc2);
          $subdir2 = $xpath2->query('//html/body/table/tr/td[@class="colname"]/a');
          $nb2 = count($subdir2);
          log::add(__CLASS__, 'debug', "  Nb2 : $nb2");
          for($i2=0;$i2<$nb2;$i2++) {
            $val3 = $subdir2[$i2]->getAttribute('href');
            log::add(__CLASS__, 'debug', "  Val3 : $val3");
            if($val3 == "CDP_CARTE_EXTERNE.json") {
              $latestFileAlert = "$url2/$val3";
            }
            elseif($val3 == "VIGNETTE_NATIONAL_J_500X500.png") {
              $latestFileVignetteJ = "$url2/$val3";
            }
            elseif($val3 == "VIGNETTE_NATIONAL_J1_500X500.png") {
              $latestFileVignetteJ1 = "$url2/$val3";
            }
            elseif($val3 == "CDP_TEXTES_VIGILANCE.json") {
              $latestFileAlertTxt = "$url2/$val3";
            }
          }
        }
        else {
          log::add(__CLASS__, 'warning', "  Unable to fetch $url2");
          return 1; // erreur
        }
        log::add(__CLASS__, 'debug', "  Val: [$val] Latest: $latest");
      }
      $err = 0;
      if($latestFileAlert != '') {
        $err += self::downloadVigDataArchive($latestFileAlert,1,$fileAlert);
      }
      if($latestFileVignetteJ != '') {
        $err += self::downloadVigDataArchive($latestFileVignetteJ,0,$fileVignetteJ);
      }
      if($latestFileVignetteJ1 != '') {
        $err += self::downloadVigDataArchive($latestFileVignetteJ1,0,$fileVignetteJ1);
      }
      if($latestFileAlertTxt != '') {
        $err += self::downloadVigDataArchive($latestFileAlertTxt,1,$fileAlertTxt);
      }
      if($err == 0 && $latest != 0) {
        $latestFull = date('Ymd',$timeRecup) .$latest .'Z';
        config::save('prevVigilanceRecovery', $latestFull, __CLASS__);
      }
    }
    else {
      log::add(__CLASS__, 'debug', "  Unable to fetch $url");
      return 1; // erreur
    }
    return 0; // OK
  }

  public function updateVigilanceMeteoFrance() {
    $changed = false;
    $numDept = $this->getConfiguration('numDeptFr');
    if($numDept == '') {
      // log::add(__CLASS__, 'debug', __FUNCTION__ ." ID:" .$this->getId() ." Département non défini.");
      foreach(self::$_vigilanceType as $i => $vig) {
        if($i > 100) break; // Valeurs de Météoalarm 
        $changed = $this->checkAndUpdateCmd("Vigilancephenomenon_max_color_id$i",0) || $changed;
        $changed = $this->checkAndUpdateCmd("Vigilancephases$i",'') || $changed;
        $changed = $this->checkAndUpdateCmd('Vigilancecolor_max', 0) || $changed;
        $changed = $this->checkAndUpdateCmd('Vigilancelist', '') || $changed;
        $changed = $this->checkAndUpdateCmd("VigilanceJson", '[]') || $changed;
      }
      return $changed;
    }
    log::add(__CLASS__, 'debug', __FUNCTION__ .". Département: $numDept");
      // Météo des forêts
    if(config::byKey('useForestAPI', __CLASS__, 0)) {
      $fileData = __DIR__ ."/../../data/DataMeteoForetsJ1.json";
      $contents = @file_get_contents($fileData);
      if($contents !== false) {
        $return = json_decode($contents,true);
        unset($contents);
        if($return !== false) {
          if(isset($dept['error'])) {
              log::add(__CLASS__, 'info', "  Forest. " .$dept['error']);
            // {"error":"InvalidNameSpace: mdf_departement"}
          } else {
            $found = 0;
            foreach($return as $dept) {
              if(isset($dept['dep_code']) && $dept['dep_code'] == $numDept) {
                $ts = strtotime($dept['reference_time']); // J1 est le lendemain de cette date
                // message::add(__CLASS__, "Found " .$dept['dep_nom'] ."($numDept) refTime: " .date('c',$ts) ." J1:" .$dept['niveau_j1']);
                // TODO verification si j1 suivant heure OK
                if(date('Y-m-d') == date('Y-m-d',$ts+86400)) {
                  $colForest = $dept['niveau_j1'];
                  $changed = $this->checkAndUpdateCmd('Vigilance_color_forest', $colForest) || $changed;
                  log::add(__CLASS__, 'debug', "Meteo des forets ($numDept) J1 $colForest Date:" .date('Y-m-d'));
                }
                else log::add(__CLASS__, 'debug', "Meteo des forets ($numDept) color not found " .date('Y-m-d',$ts+86400) .". Value unchanged");
                $found = 1;
                break;
              }
            }
            if(!$found)  {
              log::add(__CLASS__, 'warning', "  Forest. Department $numDept not found");
              $changed = $this->checkAndUpdateCmd('Vigilance_color_forest', -1) || $changed;
            }
          }
        }
        else {
          log::add(__CLASS__, 'warning', "  Unable to json_decode data from $fileData");
          @unlink($fileData);
          $changed = $this->checkAndUpdateCmd('Vigilance_color_forest', -1) || $changed;
        }
      }
      else {
        log::add(__CLASS__, 'warning', "  Unable to load data from $fileData");
        $changed = $this->checkAndUpdateCmd('Vigilance_color_forest', -1) || $changed;
      }
    }
    else $changed = $this->checkAndUpdateCmd('Vigilance_color_forest', 0) || $changed;

    $fileData = __DIR__ ."/../../data/CDP_CARTE_EXTERNE.json";
    $contents = @file_get_contents($fileData);
    if($contents === false) {
      log::add(__CLASS__, 'warning', "  Unable to load data from $fileData");
      // TODO clean des cmds ou pas ?
      return $changed;
    }
    $return = json_decode($contents,true);
    if($return === false) {
      @unlink($fileData);
      // TODO clean des cmds ou pas ?
      return $changed;
    }
    /* $txtTsAlerts = array(); */ $phenomColor = array(); $txtPhases = array();
        // init all values
    foreach(self::$_vigilanceType as $i => $vig) {
      /* $txtTsAlerts[$i] = ''; */ $phenomColor[$i] = 0; $txtPhases[$i] = '';
    }
    $maxColor = 0; $now = time();
    $vigJson = array();
    $listVigilance = array();
    foreach($return['product']['periods'] as $period) {
      $startPeriod = strtotime($period['begin_validity_time']);
      $endPeriod = strtotime($period['end_validity_time']);
      if($now > $endPeriod || $now < $startPeriod) continue; // just one day
      $vigJson['begin_validity_time'] = $period['begin_validity_time'];
      $vigJson['end_validity_time'] = $period['end_validity_time'];
      $vigJson['domain_id_picture'] = "none";
      $prevVigRecup = trim(config::byKey('prevVigilanceRecovery', __CLASS__));
      if(date('Ymd') != substr($prevVigRecup,0,8)) $img = 'VIGNETTE_NATIONAL_J1_500X500.png';
      else $img = 'VIGNETTE_NATIONAL_J_500X500.png';
      $vigJson['image'] = "$img?ts=".@filemtime(__DIR__ ."/../../data/$img");
      // log::add(__CLASS__, 'debug', "  Validity period start: " .date("d-m-Y H:i",$startPeriod) ." end: " .date("d-m-Y H:i",$endPeriod));
      foreach($period['timelaps']['domain_ids'] as $domain_id) {
        $dept = $domain_id['domain_id'];
        if($dept == $numDept || $dept == $numDept .'10') { // concat 10 si departement bord de mer
          log::add(__CLASS__, 'debug', "  Domain: $dept JSON: " .json_encode($domain_id));
          if(strlen($dept) == 2 ) $txt = 'dept';
          else $txt = 'littoral';
          $vigJson[$txt] = $domain_id;
          foreach($domain_id['phenomenon_items'] as $phenomenonItem) {
            $phenId = $phenomenonItem['phenomenon_id'];
            $color = $phenomenonItem['phenomenon_max_color_id'];
            if($color > $maxColor) $maxColor = $color;
            $phenomColor[$phenId] = $color;
            if($color > 1) {
              $listVigilance[] = self::$_vigilanceType[$phenId]['txt'] .' : ' .self::$_vigilanceColors[$color]['desc'];
              foreach($phenomenonItem['timelaps_items'] as $timelapsItem) {
                $colorTs = $timelapsItem['color_id'];
                if($colorTs != 0) {
                  $begin = strtotime($timelapsItem['begin_time']);
                  $end = strtotime($timelapsItem['end_time']);
                  if($now < $end) {
                    $txtPhases[$phenId] .= '. ' .self::$_vigilanceColors[$colorTs]['desc'] .":  " .date('H:i',$begin) ." - " .date('H:i',$end);
                    // $txtTsAlerts[$phenId] .= "<br><i class='fa fa-circle' style='color:" .self::$_vigilanceColors[$colorTs]['color'] ."'></i> " .date('H:i',$begin) ." - " .date('H:i',$end);
                    log::add(__CLASS__, 'debug', "  PhenomId: $phenId Color: $color start:" .date("d-m-Y H:i:s",$begin)." End:" .date("d-m-Y H:i:s",$end) ." MaxColor: $maxColor"); 
                  }
                }
              }
            }
          }
        }
      }
    }
    $changed = $this->checkAndUpdateCmd('Vigilancecolor_max', $maxColor) || $changed;
    $changed = $this->checkAndUpdateCmd('Vigilancelist', implode(', ',$listVigilance)) || $changed;
      // save departement file
    $fileDept = __DIR__ ."/../template/images/dept_fr_$numDept-grey.svg";
    $contents = @file_get_contents($fileDept);
    if($contents !== false) {
      $val = str_replace('#888888',self::$_vigilanceColors[$maxColor]['color'],$contents);
      $fileNewDept = __DIR__ ."/../../data/dept_fr_$numDept.svg";
      $res = file_put_contents($fileNewDept,$val);
      if($res === false) log::add(__CLASS__,'debug',"Unable to save file: $fileNewDept");
      else $vigJson['domain_id_picture'] = "dept_fr_$numDept.svg?ts=".time();
    }
    // else log::add(__CLASS__, 'debug', "  File $fileDept not found");
      // Save Json command
    if(count($vigJson)) {
      $contents = str_replace('"','&#34;',json_encode($vigJson,JSON_UNESCAPED_UNICODE));
      $changed = $this->checkAndUpdateCmd("VigilanceJson", $contents) || $changed;
      if(strlen($contents) > 3000)
        message::add(__CLASS__, "Cmd VigilanceJson Lg:". strlen($contents));
      /*
      $file = __DIR__ ."/../../data/" .__FUNCTION__ ."-$numDept.json";
      $hdle = fopen($file, "wb");
      if($hdle !== FALSE) { fwrite($hdle, $contents); fclose($hdle); }
       */
    }
      // Other commands
    foreach(self::$_vigilanceType as $i => $vig) {
      // if($phenomColor[$i] > 1) message::add(__CLASS__, "Vigilance $i " .$phenomColor[$i] .$txtTsAlerts[$i]);
      $changed = $this->checkAndUpdateCmd("Vigilancephases$i",
        self::$_vigilanceColors[$phenomColor[$i]]['desc'] .$txtPhases[$i]) || $changed;
      $changed = $this->checkAndUpdateCmd("Vigilancephenomenon_max_color_id$i", $phenomColor[$i]) || $changed;
    }
    return $changed;
  }

}

class weatherForecastCmd extends cmd {
  public function execute($_options = [] ) {
    if ($this->getLogicalId() == 'refresh') {
      $this->getEqLogic()->updateWeatherData(0,1);
    }
    return false;
  }
}
