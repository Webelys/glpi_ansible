#!/usr/bin/php
<?php
/**
 * @version $Id: testrest.php 395 2014-11-16 18:39:27Z yllen $
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Webservices plugin for GLPI.

 Webservices is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Webservices is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Webservices. If not, see <http://www.gnu.org/licenses/>.

 @package   Webservices
 @author    Nelly Mahu-Lasson
 @copyright Copyright (c) 2009-2014 Webservices plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.indepnet.net/projects/webservices
 @link      http://www.glpi-project.org/
 @since     2009
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
    'm' => 'method',
    'h' => 'host',
    'p' => 'password',
    'u' => 'username'
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

if (empty($options) || isset($options['help']) || !isset($options['method'])) {
   echo "\nusage : ".$_SERVER["SCRIPT_FILENAME"]." [ options] \n\n";

   echo "\t--help        : display this screen\n";
   echo "\t-h --host     : server REST plugin URL, default : $url\n";
   echo "\t-u --username : User name for security check (optionnal)\n";
   echo "\t-p --password : User password (optionnal)\n";
   echo "\t-m --method   : REST method to call, default : glpi.test\n";
   echo "\t--url         : URL REST call\n";
   echo "\t--deflate     : \n";
   echo "\t--base64      : \n";   


   die( "\nOther options are used for REST call.\n\n");
}

if (isset($options['url'])) {
   $url = $options['url'];
}

if (isset($options['host'])) {
   $host = $options['host'];
} else {
   $host = 'localhost';
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

// Login to GLPI
$url_request="http://$host/$url?method=glpi.doLogin&login_name=".$options['username']."&login_password=".$options['password'];
echo "+ Calling 'glpi.doLogin' on $url_request\n";
$file = file_get_contents($url_request, false);
if (!$file) {
   die("+ No response\n");
}

$response = json_decode($file, true);

if (!is_array($response)) {
   echo $file;
   die ("+ Bad response\n");
}

if (!isset($response['session'])) {
    die ("Bad Login/Password\nNo session set");
}

$session=$response['session'];

//Entities listing
$entities = array();
$url_request="http://$host/$url?method=glpi.listEntities&session=".$session;
echo "+ Calling 'glpi.listEntities' on $url_request\n";

$file = file_get_contents($url_request, false);
if (!$file) {
   die("+ No response\n");
}

$response = json_decode($file, true);
if (!is_array($response)) {
   echo $file;
   die ("+ Bad response\n");
   break;
}

if (isset($response['faultCode'])) {
    echo("REST error(".$response['faultCode']."): ".$response['faultString']."\n");
    break;
} else {
       
    foreach($response as $row) {
       $entities[$row['id']] = $row['completename'];        
    }
}

//Get Computers
$start = 0;
$limit=20;
$computers=array();
do {    
    $url_request="http://$host/$url?method=glpi.listObjects&session=".$session."&itemtype=Computer&start=$start&limit=$limit";
    echo "+ Calling 'glpi.listObjects' on $url_request\n";

    $file = file_get_contents($url_request, false);
    if (!$file) {
       die("+ No response\n");
    }

    $response = json_decode($file, true);
    if (!is_array($response)) {
       echo $file;
       die ("+ Bad response\n");
       break;
    }

    if (isset($response['faultCode'])) {
        echo("REST error(".$response['faultCode']."): ".$response['faultString']."\n");
        break;
    } else {       
       $computers=array_merge($computers,$response);
    }
  $start += $limit;
} while (!empty($response));


//Computer Detail
foreach ($computers as $key => $computer) {

    $url_request="http://$host/$url?method=glpi.getObject&session=".$session."&itemtype=Computer&id=$computer[id]";
    echo "+ Calling 'glpi.getObject' on $url_request\n";

    $file = file_get_contents($url_request, false);
    if (!$file) {
       die("+ No response\n");
    }

    $response = json_decode($file, true);
    if (!is_array($response)) {
       echo $file;
       die ("+ Bad response\n");
       break;
    }

    if (isset($response['faultCode'])) {
        echo("REST error(".$response['faultCode']."): ".$response['faultString']."\n");
        break;
    } else {       
       $computers[$key]=array_merge($computers[$key],$response);
       $computers[$key]['entitie_name'] = $entities[$computers[$key]['entities_id']];
    }
}

$url_request="http://$host/$url?method=glpi.doLogout&session=".$session."&itemtype=Computer&id=$computer[id]";
echo "+ Calling 'glpi.doLogout' on $url_request\n";

$file = file_get_contents($url_request, false);


print_r($computers);
?>
