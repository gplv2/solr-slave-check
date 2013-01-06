#!/usr/bin/php -q
<?php

/* Use like on solr master:

./check_solr_slave.php -i /var/log/jetty/2013_01_04.request.log -s 192.168.128.31:8080 -t

*/

error_reporting(E_ALL);
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
ini_set("memory_limit","500M");
set_time_limit(0);

/* Check if PHP version is sufficient for the things we use here */
$phpVersion = phpversion();
if (function_exists("version_compare") && version_compare($phpVersion, "5.2.0",'<')) {
   die("Sorry!  Your PHP version is too old.  PEAR and this script requires at least PHP 5.3.0 for stable operation.");
}

// working dir
$base= realpath( dirname(__FILE__));


/* Command line quick stuff */
$cliargs = array(
      'input' => array(
         'short' => 'i',
         'type' => 'required',
         'description' => "The input file to get the requests from",
         'default' => ''
         ),
      'slave' => array(
         'short' => 's',
         'type' => 'required',
         'description' => "host:port ( host can be an ip or a hostname)",
         'default' => ''
         ),
      'tail' => array(
         'short' => 't',
         'type' => 'switch',
         'description' => "Use tail on the file so you check in realtime"
         ),
      'debug' => array(
         'short' => 'd',
         'type' => 'optional',
         'description' => "Debug flag level. ( value range 1..6 )",
         'default' => 4
         )
);

/* command line errors are thrown hereafter */
$options = cliargs_get_options($cliargs);

if (empty($options['input']) && empty($options['slave'])) {
   cliargs_print_usage_and_exit($cliargs);
}

/* Real work starts here */
$pos = strrpos($options['slave'], ':');
if ($pos === false) { 
   // : is not found... so using standard port
   $options['port']=80;
} else {
   list($slave, $port) = preg_split('/:/', $options['slave'], -1, PREG_SPLIT_NO_EMPTY);
   if (preg_last_error() == PREG_NO_ERROR) {
      $options['slave']=$slave;
      $options['port']=$port;
   }
}

if (!empty($options['tail'])) {
   $file_handle = popen(sprintf("tail -f %s 2>&1",$options['input']), 'r');
} else {
   $file_handle = fopen($options['input'], "r");
}

while (!feof($file_handle)) {
   $buffer = fgets($file_handle);
   // echo "$buffer\n";
   check_slave($buffer, $options);
   flush();
}

if (!empty($options['tail'])) {
   pclose($file_handle);
} else {
   fclose($file_handle);
}

function check_slave($buf, $options) {
   preg_match("/(^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s-\s(\s+)-(\s+)\[(.*)\]\s\"(.*)\"\s(\d+)\s(\d+)/", $buf, $results);

   if (preg_last_error() == PREG_NO_ERROR) {
      $call['source_ip'] = $results[1];
      $path_r = preg_split('/ /', $results[5], -1, PREG_SPLIT_NO_EMPTY);
      $call['source_method'] = $path_r[0];
      $call['source_path'] = $path_r[1];
      $call['source_result'] = $results[6];
   } else { 
      if (preg_last_error() == PREG_INTERNAL_ERROR) {
         print 'There is an internal error!';
      }
      else if (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
         print 'Backtrack limit was exhausted!';
      }
      else if (preg_last_error() == PREG_RECURSION_LIMIT_ERROR) {
         print 'Recursion limit was exhausted!';
      }
      else if (preg_last_error() == PREG_BAD_UTF8_ERROR) {
         print 'Bad UTF8 error!';
      } else if (preg_last_error() == PREG_BAD_UTF8_OFFSET_ERROR) {
         // From php 5.3.0 onwards
         print 'Bad UTF8 offset error!';
      }
      exit();
   }

   /* Now build up a request for the slave */
   $url = sprintf('http://%s:%d%s',$options['slave'], $options['port'], $call['source_path']);
   // echo "Checking slave : $url\n";

   /* Only do GET request */
   if ($call['source_method'] !== 'GET') {
      echo "Skipping non GET request : $url\n";
      return;
   }

   /* Don't send calls the slave made to the master again tot the slave */
   if (preg_match(sprintf("/%s/",$options['slave']), $call['source_ip'])) {
      echo "Skipping slave log entry: $url\n";
      return;
   }

   /* Don't send calls to the slave that already failed on master */
   if (intval(trim($call['source_result']))!== 200) {
      echo sprintf("Skipping failed master log entry (%s)\n", $call['source_result']);
      return;
   }

   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_POST, false);
   // curl_setopt($ch, CURLOPT_POSTFIELDS, $post_arr); 
   // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 3);
   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000);
   curl_setopt($ch, CURLOPT_USERAGENT, 'php solr slave tester');
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

   $output = curl_exec($ch);
   $curl_info = curl_getinfo($ch);

   if (!$curl_info['http_code']==200) {
      echo "ERROR ON SLAVE\n"; exit;
   }  else {
      echo "Slave ok\n";
   }
}

/* command line argument functions below */
function cliargs_print_usage_and_exit($cliargs) {
   print "Usage:\n";

   foreach ($cliargs as $long => $arginfo) {
      $short = $arginfo['short'];
      $type = $arginfo['type'];
      $required = ($type=='required');
      $optional = ($type=='optional');
      $description = $arginfo['description'];

      print "\t-$short/--$long ";

      if ($optional||$required) {
         print "<value> ";
      }

      print ": $description";

      if ($required) {
         print " (required)";
      }
      print "\n";
   }
   exit();
}

function cliargs_strstartswith($source, $prefix) {
   return strncmp($source, $prefix, strlen($prefix)) == 0;
}

function cliargs_get_options($cliargs) {
   global $argv;
   global $argc;

   $options = array('unnamed' => array());
   for ($index=1; $index<$argc; $index+=1) {
      $currentarg = strtolower($argv[$index]);
      $argparts = preg_split('/=/', $currentarg);
      //print_r($argparts); exit;
      $namepart = $argparts[0];

      if (cliargs_strstartswith($namepart, '--')) {
         $longname = substr($namepart, 2);
      } else if (cliargs_strstartswith($namepart, '-')) {
         $shortname = substr($namepart, 1);
         $longname = $shortname;
         foreach ($cliargs as $name => $info) {
            if ($shortname===$info['short']) {
               $longname = $name;
               break;
            }
         }

      } else {
         $longname = 'unnamed';
      }

      if ($longname=='unnamed') {
         $options['unnamed'][] = $namepart;
      } else {
         if (empty($cliargs[$longname])) {
            print "Unknown argument '$longname'\n";
            cliargs_print_usage_and_exit($cliargs);
         }

         $arginfo = $cliargs[$longname];
         $argtype = $arginfo['type'];
         if ($argtype==='switch') {
            $value = true;
         } else if (isset($argparts[1])) {
            $value = $argparts[1];
         } else if (($index+1)<$argc) {
            $value = $argv[$index+1];
            $index += 1;
         } else {
            print "Missing value after '$longname'\n";
            cliargs_print_usage_and_exit($cliargs);
         }
         $options[$longname] = $value;
      }
   }

   foreach ($cliargs as $longname => $arginfo) {
      $type = $arginfo['type'];

      if (!isset($options[$longname])) {
         if ($type=='required') {
            print("Missing required value for '$longname'\n");
            cliargs_print_usage_and_exit($cliargs);
         }
         else if ($type=='optional') {
            if (!isset($arginfo['default'])) {
               die('Missing default value for '.$longname .  PHP_EOL);
            }

            $options[$longname] = $arginfo['default'];
         } else if ($type=='switch') {
            $options[$longname] = false;
         }
      }
   }

   foreach ($options as $longname => $value) {
      if ($longname!='unnamed'){
         if (strlen(trim($value))==0) {
            unset($options[$longname]);
            //print "Length '$longname' = " . strlen($value) . "\n";
         }
      }
   }
   unset($options['unnamed']);

   return $options;
}

?>
