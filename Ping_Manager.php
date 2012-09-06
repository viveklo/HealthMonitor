<?php
// @(#) $Id$
// +-----------------------------------------------------------------------+
// | Copyright (C) 2008, http://yoursite                                   |
// +-----------------------------------------------------------------------+
// | This file is free software; you can redistribute it and/or modify     |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation; either version 2 of the License, or     |
// | (at your option) any later version.                                   |
// | This file is distributed in the hope that it will be useful           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of        |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          |
// | GNU General Public License for more details.                          |
// +-----------------------------------------------------------------------+
// | Author: pFa                                                           |
// +-----------------------------------------------------------------------+
//

require_once ('Thread.php');
require_once ('util.php');

function ping_camera($configfilename, $camdetails, $filestat, $filemedia) 
{

   $configarr = read_config_file($configfilename);
   date_default_timezone_set($configarr["timezone"]);
   $pingtimeout = $configarr["pingtimeout"];

   $username = $camdetails["username"];
   $password = $camdetails["password"];
   $pingurl = $camdetails["pingurl"];
   
   $date = date('Y-m-d H:i:s'); 
   $formatdate = str_replace (" " , "_", $date);
   $formatdate = str_replace ("-" , "_", $formatdate);
   $formatdate = str_replace (":" , "_", $formatdate);
   
   
   if($username == "notset")
   {
      $httpurl = "http://".$camdetails["ip"].":".$camdetails["port"]."/".$pingurl;
      
   }
   else
   {
      $httpurl = "http://".$username.":".$password."@".$camdetails["ip"].":".$camdetails["port"]."/".$pingurl;
   }
   
   echo "Ping URL...".$httpurl."\n";

   //set the timout
   $ctxt = stream_context_create(array('http'=>array('timeout'=>$pingtimeout)));

   $pingtimestampstart = date('Y-m-d H:i:s');
   //ping the url.. i.e get the url
   $pingcontent = file_get_contents($httpurl, false, $ctxt);

   $pingtimestampend = date('Y-m-d H:i:s');

   if ($pingcontent)
   {
      $camdetails["status"] = "true";
      fputs ($filemedia, $camdetails["cameraname"]." ".
                         $pingtimestampstart." ".
                         $pingtimestampend."\n");
   }
   else
   {
      //Check if not reachable or reachable butnot able to access th URL
      if(empty($http_response_header))
      {
         // not reachable
         $camdetails["status"] = "false";
         echo "Ping ".$httpurl." is not reachable \n";
      }
      else
      {
         $camdetails["status"] = "partial";
         echo "Ping ".$httpurl." reachable but with problem\n";
      }
   }

   fputs ($filestat, $camdetails["cameraname"]." ".
                     $camdetails["cameramake"]." ".
                     $camdetails["ip"]." ".
                     $camdetails["port"]." ".
                     $camdetails["username"]." ".
                     $camdetails["password"]." ".
                     $camdetails["status"]."\n");
}
   

function process_camera_ping($configfilename, $pingschedulefilename, $statusfilename) 
{
   //read config file
   $configarr = read_config_file($configfilename);
   
   //populate the schedule table
   //generate the list of the schedule cameras
   $filein = fopen($pingschedulefilename, "r") or exit("Unable to open file!...".$pingschedulefilename."\n");
   $i = 0;

   while(!feof($filein))
   {
     $line = fgets( $filein );
     if($line == "")
        continue;
   
      $ping_schedule[$i]["cameraname"] = rtrim(strtok($line, " "));
      $ping_schedule[$i]["cameramake"] = rtrim(strtok(" "));
      $ping_schedule[$i]["ip"] = rtrim(strtok(" "));
      $ping_schedule[$i]["port"] = rtrim(strtok(" "));
      $ping_schedule[$i]["username"] = rtrim(strtok(" "));
      $ping_schedule[$i]["password"] = rtrim(strtok(" "));
      $ping_schedule[$i]["pingurl"] = rtrim(strtok(" "));
      $ping_schedule[$i]["status"] = "TBU"; // TBU To Be Updated later
      $i = $i + 1;
   }//end while feof
   fclose($filein);


   date_default_timezone_set($configarr["timezone"]);

   $simultpingsessions = $configarr["simultpingsessions"];

   //Open a file to so that the child processes can write status of the output
   $filestatus = fopen($statusfilename, "w") or exit("Unable to open file!..".$statusfilename."\n");
   $filemedia = fopen("Pingdetails_".$statusfilename, "w") or exit("Unable to open file!..."."Pingdetails_".$statusfilename);

   //operate below 10% of max simltaneous ping sessions
   $noofthreads = (count($ping_schedule) < ((int)($simultpingsessions * 0.9)+1)) ? count($ping_schedule) : ((int)($simultpingsessions * 0.9)+1);

   echo "No of concurrent ping sessions...".$noofthreads."\n";
   echo "No of cameras to ping...".count($ping_schedule)."\n";

   $j = 0;
   while ($j < count($ping_schedule)) 
   {
      //construct an array for which the threads have to be generated
      $k = 0;
      while($k < $noofthreads)
      {
         $tempping_schedule[$k] = $ping_schedule[$j];
         $k = $k + 1;
         $j = $j + 1;
      }
      $threads = array();
      $index = 0;

      foreach ($tempping_schedule as $scheduleval)
      {
         $threads[$index] = new Thread( 'ping_camera' );
         $threads[$index]->start($configfilename, $scheduleval, $filestatus, $filemedia);
         ++$index;
      }

      // Let the cpu do its work till ping is done
      sleep(5);   
      // wait for all the threads to finish
      $index = 0;

      while( !empty( $threads ) ) 
      {    
         foreach( $threads as $index => $thread ) 
         {        
            if( ! $thread->isAlive() )
            {  
               unset( $threads[$index] );
            }
         }  
         echo "Ping Manager Sleeping before checking for Ping empty threads...\n";
         sleep(5); // Sleep for 5 secs
      } // end while empty
   }// end while count($ping_schedule)

   fclose($filestatus);
   fclose($filemedia);

}

?>
