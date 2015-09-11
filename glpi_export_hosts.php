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
 @link      https://github.com/Webelys/glpi_ansible
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

$cache_file = '/tmp/.glpi_ansible_hosts_cache.json';

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

if (isset($options['glpi'])) {
   $glpi = $options['glpi'];
} else {
   $glpi = 'http://localhost/glpi/plugins/webservices/rest.php';
}

if (!isset($options['cache']))
    $options['cache'] = "P01D";

if (isset($options['host'])) {
    die('{"Not Yet Implemented"}');
}

if (empty($options) || isset($options['help']) ) {
    echo "Usage: " . $_SERVER["SCRIPT_FILENAME"] . " [options]\n";
    echo "\n";
    echo "  --glpi      -g      : GLPI \"rest.php\" webservice URL (default: \"" . $glpi . "\")\n";
    echo "  --username  -u      : GLPI user name\n";
    echo "  --password  -p      : GLPI user password\n";
    echo "  --list              : Return a complete JSON document (default when called by Ansible)\n";
    echo "  --host [hostname]   : Return vars associated to this hostname\n";
    echo "  --cache [time]      : Set duration of local cache (default: \"" . $options['cache'] . "\" (P01D = 1 day))\n";
    echo "  --debug     -d      : Display debug information (default disabled)\n";
    echo "  --help      -h      : display this screen\n";
    echo "\n";
    die( "Any other options are used for REST call.\n");
}

//Check cache validity
if (file_exists($cache_file) && isset($options['list'])) {
    $now = new DateTime();
    $cache_date = new DateTime("@".filemtime($cache_file));
    $cache_interval = new DateInterval($options['cache']);

    $cache_expiration = $cache_date->add($cache_interval);

    //Return cached file if not expired
    if ($cache_expiration > $now) {
        die(file_get_contents($cache_file));
    }
}

function glpi_request($glpi,$method,$query_datas) {
    global $options;
    $query_datas['method']=$method;

    $query_str=http_build_query($query_datas);
    $url_request=$glpi."?".$query_str;

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

// Login to GLPI
$response = glpi_request($glpi,'glpi.doLogin',array('login_name' => $options['username'], 'login_password' => $options['password'] ));

if (!is_array($response)) {
   echo $file;
   die ("+ Bad response\n");
}

if (!isset($response['session'])) {
    die ("Bad Login/Password\nNo session set");
}

$session=$response['session'];

//Entities listing
$response = glpi_request($glpi,'glpi.listEntities',array('session' => $session));

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
$response = glpi_request($glpi,'glpi.listDropdownValues',array('session' => $session,'dropdown' =>'domains'));
$domains = array();
if (!empty($response)) {
    foreach($response as $row) {
        $domains[$row['id']] = $row['name'];
    }
}

//Get Computers
$start = 0;
$limit = 20;
$computers = array();
do {
    $response = glpi_request($glpi,'glpi.listObjects',array('session' => $session, 'itemtype' => 'Computer','start' => $start, 'limit' => $limit));
    if (!empty($response)) {
       $computers=array_merge($computers,$response);
    }
    $start += $limit;
} while (!empty($response));


//Computer Detail
foreach ($computers as $key => $computer) {

    $response = glpi_request($glpi,'glpi.getObject',array('session' => $session, 'itemtype' => 'Computer','id' => $computer['id']));

    if (!empty($response)) {
       $computers[$key]=array_merge($computers[$key],$response);
       $computers[$key]['entity'] = $entities[$computers[$key]['entities_id']];
       $computers[$key]['domain'] = (isset($computers[$key]['domains_id']) && !empty($computers[$key]['domains_id'])) ? $domains[$computers[$key]['domains_id']] : "";
    }
}

$response = glpi_request($glpi,'glpi.doLogout',array('session' => $session));

$inventory = array();
foreach($entities as $entity) {

    //Set Group
    $entity['name'] = transliterator_transliterate('Any-Latin; Latin-ASCII;', $entity['name']);
    $entity['name'] = str_replace(' ','',$entity['name']);
    $inventory[$entity['name']] = array('hosts' => array(), 'children' => array());
    //List computer
    foreach($computers  as $computer) {
        if ($computer['entity']['id'] == $entity['id']) {
            $fqdn = str_replace(' ','',$computer['name']). (!empty($computer['domain']) ? ".".$computer['domain']: "");
            //Prevent duplicate host
            if (!in_array($fqdn,$inventory[$entity['name']]['hosts']))
                $inventory[$entity['name']]['hosts'][] = $fqdn;
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
    $list_json = json_encode($inventory);
    file_put_contents($cache_file, $list_json);
    print_r($list_json);
}
