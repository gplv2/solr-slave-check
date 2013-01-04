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


/* Check if PHP version is sufficient for the things we use here
   $phpVersion = phpversion();
   if (function_exists("version_compare") && version_compare($phpVersion, "5.3.0",'<')) {
   die("Sorry!  Your PHP version is too old.  PEAR and this script requires at least PHP 5.3.0 for stable operation.");
   }*/

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

/* GLENN */

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
   /*
      192.168.128.20 -  -  [04/Jan/2013:20:27:49 +0000] "GET /solr-3.6.1/event/select/?qt=dismax&fq=%28id:1002458313+OR+uniqueid:1002458313%29&fq=channelid:%28118%29&fq=isevent:1&fq=countryid:%283%29&fq=-eventstatus60:%22-1%22&fl=id%2Ctown%2Csubtown%2Cuniqueid%2Ceventhtml%2Csourceid%2Ctitle%2Csummary%2Ccontent%2Cdatefrom%2Cdateto%2Cdatetext%2Curl%2CpriceticketingURL%2Cpricetext%2Cpricemin%2Cpricemax%2Cpricecurrency%2Cfree%2Csoldout%2Ccancelled%2Cpostponed%2Cimage%2Ceventimage%2Clocationtext%2Clocationid%2Cregionid%2Cregion%2CURLtext%2Ccategorisationtext%2Cvenuetext%2Cemailtext%2Corganizertext%2Cgeocodingaccuracy%2Cgeocodingaddress%2Ceventstatus60%2Clatitude%2Clongitude%2Csourceicon%2Cdateextracting%2Cday%2Cishidden%2Cmaintown%2Csubtown%2Cthemeid%2Cmineventdate0%2Ceventranking60%2Ceventcustomfield60%2Cday%2Cmappingid%2Cthemeid%2Cuniqueid&rows=1&start=0&wt=phps HTTP/1.1" 200 7729
    */

   preg_match("/(^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s-\s(\s+)-(\s+)\[(.*)\]\s\"(.*)\"\s(\d+)\s(\d+)/", $buf, $results);

   /*
      [0] => 192.168.128.20 -  -  [04/Jan/2013:20:52:03 +0000] "GET /solr-3.6.1/event/select/?qt=dismax&fq=channelid:%2836%29&fq=isevent:1&fq=isdouble:0&fq=-crosssourcedoublechannelid:%2836%29&fq=-ishidden:1&fq=eventgroup21:%28+OR+%29&fq=(eventgroupstartdate21_:%5B%2A+TO+2013-01-04T23%3A59%3A59.999Z%5D+OR+eventgroupstartdate21_%3A%5B%2A+TO+2013-01-04T23%3A59%3A59.999Z%5D%29&fq=(eventgroupenddate21_:%5B2013-01-04T00%3A00%3A00.000Z+TO+%2A%5D+OR+eventgroupenddate21_%3A%5B2013-01-04T00%3A00%3A00.000Z+TO+%2A%5D%29&fq=day:%5B2013-01-04T00%3A00%3A00Z+TO+%2A%5D&fq=-id:%281502775932+OR+1502844256+OR+1502549632+OR+1502862230+OR+1502732576%29&fq=countryid:%281%29&fq=-eventstatus21:%22-1%22&fl=id%2C+uniqueid%2C+locationid%2C+title%2C+summary%2C+image%2C+town%2C+subtown%2C+region%2C+regionid%2C+datenext%2C+datefrom%2C+dateto%2C+venuetext%2C+ranking%2C+priceticketingURL%2C+pricemin%2C+eventimage%2C+eventgroup21+%2Cday%2Cmappingid%2Cthemeid%2Cuniqueid&sort=random_1138879541+desc&rows=3&start=0&wt=phps HTTP/1.1" 400 1379
      [1] => 192.168.128.20
      [2] =>  
      [3] =>   
      [4] => 04/Jan/2013:20:52:03 +0000
      [5] => GET /solr-3.6.1/event/select/?qt=dismax&fq=channelid:%2836%29&fq=isevent:1&fq=isdouble:0&fq=-crosssourcedoublechannelid:%2836%29&fq=-ishidden:1&fq=eventgroup21:%28+OR+%29&fq=(eventgroupstartdate21_:%5B%2A+TO+2013-01-04T23%3A59%3A59.999Z%5D+OR+eventgroupstartdate21_%3A%5B%2A+TO+2013-01-04T23%3A59%3A59.999Z%5D%29&fq=(eventgroupenddate21_:%5B2013-01-04T00%3A00%3A00.000Z+TO+%2A%5D+OR+eventgroupenddate21_%3A%5B2013-01-04T00%3A00%3A00.000Z+TO+%2A%5D%29&fq=day:%5B2013-01-04T00%3A00%3A00Z+TO+%2A%5D&fq=-id:%281502775932+OR+1502844256+OR+1502549632+OR+1502862230+OR+1502732576%29&fq=countryid:%281%29&fq=-eventstatus21:%22-1%22&fl=id%2C+uniqueid%2C+locationid%2C+title%2C+summary%2C+image%2C+town%2C+subtown%2C+region%2C+regionid%2C+datenext%2C+datefrom%2C+dateto%2C+venuetext%2C+ranking%2C+priceticketingURL%2C+pricemin%2C+eventimage%2C+eventgroup21+%2Cday%2Cmappingid%2Cthemeid%2Cuniqueid&sort=random_1138879541+desc&rows=3&start=0&wt=phps HTTP/1.1
      [6] => 400
      [7] => 1379
    */

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
      }
      else if (preg_last_error() == PREG_BAD_UTF8_ERROR) {
         print 'Bad UTF8 offset error!';
      }
      exit();
   }

   /* Now build up a request for the slave */
   $url = sprintf('http://%s:%d%s',$options['slave'], $options['port'], $call['source_path']);
   // echo "Checking slave : $url\n";

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

   /* Only do GET request */
   if ($call['source_method'] !== 'GET') {
      echo "Skipping non GET request : $url\n";
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
