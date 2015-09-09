#!/usr/bin/php
<?php
/**
 * @version 0.1
 -------------------------------------------------------------------------
 LICENSE

 This file is autonomous sript to export glpi inventory to ansible
 
 glpi-ansible is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

  glpi-ansible is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Webservices. If not, see <http://www.gnu.org/licenses/>.

 @package   glpi-ansible 
 @author    cam.lafit
 @copyright Copyright (c) 2015-2015 Webelys team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      http://www.glpi-project.org/
 @link      http://docs.ansible.com/ansible/intro_inventory.html
 @since     2015
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Purpose of file: Test the XML-RPC plugin from Command Line
// ----------------------------------------------------------------------

if (!function_exists("json_encode")) {
   die("Extension json_encode not loaded\n");
}

$url = "/plugins/webservices/rest.php";

$longoptions=array(
    'h' => 'help',
    'g' => 'glpi',
    'p' => 'password',
    'u' => 'username',
    'd' => 'debug'
);

$options = array();
if (sizeof($argv)>1) {
    //$argv[0] == filename
    for ($i=1 ; $i<count($argv) ; $i++) {

        //option getopt format
        $res = preg_match('/^--?(\w*)/',$argv[$i],$matches);
        $arg=$matches[1];
        $option="";
        if (isset($argv[$i+1]) and substr($argv[$i+1],0,1) != "-") {
            $option= $argv[$i+1];
            $i++;
        } 
        //option with = 
        if (preg_match('/^--?(\w*)=(\w*)/',$argv[$i],$matches)) {
            $arg=$matches[1];
            $option=$matches[2];        
        }        
        //Force to long option if set
        if (isset($longoptions[$arg]))
            $arg=$longoptions[$arg];
        
        $options[$arg]=$option;
    }
}

if (empty($options) || isset($options['help']) ) {
   echo "\nusage : ".$_SERVER["SCRIPT_FILENAME"]." [ options] \n\n";

   echo "\t-h --help            : display this screen\n";
   echo "\t-g --glpi            : server REST plugin URL, default : $url\n";
   echo "\t-u --username        : User name for security check (optionnal)\n";
   echo "\t-p --password        : User password (optionnal)\n";
   echo "\t--url                : URL REST call\n";
   echo "\t--deflate            : \n";
   echo "\t--base64             : \n";   
   echo "\t--ssl                : Act with SSL request (default http)";
   echo "\t-d --debug           : Display debug information (default disabled))'";
   echo "\t --list              : Return a complet json document";
   echo "\t --host [hostname]   : Return vars associated to this hostname";

   die( "\nOther options are used for REST call.\n\n");
}

if (isset($options['url'])) {
   $url = $options['url'];
}

if (isset($options['glpi'])) {
   $glpi = $options['glpi'];
} else {
   $glpi = 'localhost';
}

if (isset($options['method'])) {
   $method = $options['method'];
} else {
   $method='glpi.test';
}

$header = "Content-Type: text/html";

if (isset($options['deflate'])) {
   $header .= "\nAccept-Encoding: deflate";
}

if (isset($options['base64'])) {
   $content = @file_get_contents($args['base64']);
   if (!$content) {
      die ("File not found or empty (".$args['base64'].")\n");
   }
   $options['base64'] = base64_encode($content);
}

function glpi_request($glpi,$url,$method,$query_datas) {
    global $options;
    $query_datas['method']=$method;
    $protocol = isset($options['ssl']) ? "https" : "http";
    
    $query_str=http_build_query($query_datas);
    $url_request=$protocol."://".$glpi."/".$url."?".$query_str;

    if (isset($options['debug']))
        echo "+ Calling '".$method."' on $url_request\n";

    $file = file_get_contents($url_request, false);
    if (!$file) {
       die("+ No response\n");
    }

    $response = json_decode($file, true);

    if (!is_array($response)) {
       echo $file;
       die ("+ Bad response\n");
    }

    if (isset($response['faultCode'])) {
        die("REST error(".$response['faultCode']."): ".$response['faultString']."\n");
    }

    return $response;
}

if (isset($options['host'])) {
    die('{}');
}

// Login to GLPI
$response = glpi_request($glpi,$url,'glpi.doLogin',array('login_name' => $options['username'], 'login_password' => $options['password'] ));

if (!is_array($response)) {
   echo $file;
   die ("+ Bad response\n");
}

if (!isset($response['session'])) {
    die ("Bad Login/Password\nNo session set");
}

$session=$response['session'];

//Entities listing
$response = glpi_request($glpi,$url,'glpi.listEntities',array('session' => $session));

$entities = array();
if (!empty($response)) {       
    foreach($response as $row) {
        $row_entities = explode('>',$row['completename']);
        $entities[$row['id']] = array(
            'id' => $row['id'],
            'name' => trim(end($row_entities)),
            'parent' => trim(prev($row_entities)),
            'children' => array()
        );
    }
}
//Loop all entities
foreach($entities as $entity_id => $entity) {
        //Set children 
        foreach($entities as $key => $parent) {
            if ($parent['name'] == $entity['parent'])
                $entities[$key]['children'][] = $entity['id'];
        }      
}

//Domains Listing
$response = glpi_request($glpi,$url,'glpi.listDropdownValues',array('session' => $session,'dropdown' =>'domains'));
$domains = array();
if (!empty($response)) {       
    foreach($response as $row) {
        $domains[$row['id']] = $row['name'];
    }
}

//Get Computers
$start = 0;
$limit=20;
$computers=array();
do {    
    $response = glpi_request($glpi,$url,'glpi.listObjects',array('session' => $session, 'itemtype' => 'Computer','start' => $start, 'limit' => $limit));
    if (!empty($response)) {
       $computers=array_merge($computers,$response);
    }
    $start += $limit;
} while (!empty($response));


//Computer Detail
foreach ($computers as $key => $computer) {

    $response = glpi_request($glpi,$url,'glpi.getObject',array('session' => $session, 'itemtype' => 'Computer','id' => $computer['id']));

    if (!empty($response)) {
       $computers[$key]=array_merge($computers[$key],$response);
       $computers[$key]['entity'] = $entities[$computers[$key]['entities_id']];
       $computers[$key]['domain'] = (isset($computers[$key]['domains_id']) && !empty($computers[$key]['domains_id'])) ? $domains[$computers[$key]['domains_id']] : "";
    }
}

$response = glpi_request($glpi,$url,'glpi.doLogout',array('session' => $session));

$inventory = array();
foreach($entities as $entity) {
    
    //Set Group
    $entity['name'] = transliterator_transliterate('Any-Latin; Latin-ASCII;', $entity['name']);
    $entity['name'] = str_replace(' ','',$entity['name']);
    $inventory[$entity['name']] = array('hosts' => array(), 'children' => array());
    //List computer
    foreach($computers  as $computer) {
        if ($computer['entity']['id'] == $entity['id']) {
            $inventory[$entity['name']]['hosts'][] = str_replace(' ','',$computer['name']). (!empty($computer['domain']) ? ".".$computer['domain']: "");
        }    
    }
    //Set group children
    if (!empty($entity['children'])) {
        foreach ($entity['children'] as $child_id) {
            $inventory[$entity['name']]['children'][] =  $entities[$child_id]['name'];
        }
    }
    
    // Remove duplicate host
    $inventory[$entity['name']]['hosts'] = array_unique($inventory[$entity['name']]['hosts']);
}

//Return list json data
if (isset($options['list'])) {
    print_r(json_encode($inventory));
} 
?>
