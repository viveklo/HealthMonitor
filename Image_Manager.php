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

function image_capture_camera($camdetails, $imagedir, $filestat, $filemedia) 
{

   $configarr = read_config_file("recordconfig.txt");
   date_default_timezone_set($configarr["timezone"]);
   $cvlcpath = $configarr["cvlcpath"];
   //construct the rtsp url
   $username = $camdetails["username"];
   $password = $camdetails["password"];
   $imageurl = $camdetails["imageurl"];
   $streamid = $camdetails["streamid"];
   //$stream = ($streamid == "1") ? "live.sdp" : "live".$streamid.".sdp";
   
   
   $date = date('Y-m-d H:i:s'); 
   $formatdate = str_replace (" " , "_", $date);
   $formatdate = str_replace ("-" , "_", $formatdate);
   $formatdate = str_replace (":" , "_", $formatdate);
   
   $filename = $camdetails["cameraname"]."_".$formatdate.".jpg";
   $filenamefull = $imagedir."/".$filename;
   $imagetimestampstart = date('Y-m-d H:i:s');
   
   if($username == "notset")
   {
      $httpurl = "http://".$camdetails["ip"].":".$camdetails["port"]."/".$imageurl."?&streamid=".$streamid;
      
   }
   else
   {
      $httpurl = "http://".$username.":".$password."@".$camdetails["ip"].":".$camdetails["port"]."/".$imageurl."?&streamid=".$streamid;
   }
   
   echo "Get image from...".$httpurl."\n";
   //get the image
   $imagecontent = file_get_contents($httpurl);

   $imagetimestampend = date('Y-m-d H:i:s');

   if ($imagecontent)
   {
      file_put_contents($filenamefull, $imagecontent);
      $camdetails["status"] = "true";
      fputs ($filemedia, $camdetails["cameraname"]." ".
                         $filename." ".
                         $imagetimestampstart." ".
                         $imagetimestampend."\n");
   }
   else
   {
      $camdetails["status"] = "false";
      unlink($filenamefull);
      echo $httpurl." is not reachable \n";
      echo "Image Capture failed \n";
   }

   


   fputs ($filestat, $camdetails["cameraname"]." ".
                     $camdetails["cameramake"]." ".
                     $camdetails["ip"]." ".
                     $camdetails["port"]." ".
                     $camdetails["username"]." ".
                     $camdetails["password"]." ".
                     $camdetails["streamid"]." ".
                     $camdetails["status"]."\n");
}
   

//infinite loop
while (1)
{
   //read config file
   $configarr = read_config_file("recordconfig.txt");
   
   //check if recordvideo=true
   if($configarr["imagesnapshot"] == "false")
   {
      //sleep for 5mins  to free the CPU
      sleep(300);
      continue;
   }

   //populate the schedule table
   //generate the list of the schedule cameras
   $filein = fopen("Image_Schedule.txt", "r") or exit("Unable to open file!...Image_Schedule.txt");
   $i = 0;

   while(!feof($filein))
   {
     $line = fgets( $filein );
     if($line == "")
        continue;
   
      $image_schedule[$i]["cameraname"] = rtrim(strtok($line, " "));
      $image_schedule[$i]["cameramake"] = rtrim(strtok(" "));
      $image_schedule[$i]["ip"] = rtrim(strtok(" "));
      $image_schedule[$i]["port"] = rtrim(strtok(" "));
      $image_schedule[$i]["username"] = rtrim(strtok(" "));
      $image_schedule[$i]["password"] = rtrim(strtok(" "));
      $image_schedule[$i]["imageurl"] = rtrim(strtok(" "));
      $image_schedule[$i]["streamid"] = rtrim(strtok(" "));
      $image_schedule[$i]["status"] = "TBU"; // TBU To Be Updated later
      $i = $i + 1;
   }//end while feof
   fclose($filein);


   //Recording is assumed to happen daily, hence we will start at 
   // start of day that is i.e within an hour of recorddaystarttime else
   //we will wait for one day except if recordanytime is true

   date_default_timezone_set($configarr["timezone"]);

   $needtowait = 1;
   $simultimagesessions = $configarr["simultimagesessions"];

   while ($needtowait == 1)
   {

      //check if the time is in range
      $imagesnapshotdaytime_start =  strtotime($configarr["imagensnapshotdaytimestart"].":00");
      $imagesnapshotdaytime_end =  strtotime($configarr["imagensnapshotdaytimeend"].":00");
      $imagesnapshotnighttime_start =  strtotime($configarr["imagensnapshotnighttimestart"].":00");
      $imagesnapshotnighttime_end =  strtotime($configarr["imagensnapshotnighttimeend"].":00");
      $currenttime =  strtotime(date('H:i'));
      
      if (($currenttime > $imagesnapshotdaytime_start) && ($currenttime < $imagesnapshotdaytime_end))
      {
         $needtowait = 0;
      }
      else if (($currenttime > $imagesnapshotnighttime_start) && ($currenttime < $imagesnapshotnighttime_end))
      {
         $needtowait = 0;
      }
      else
      {
         $needtowait = 1;
         //we can sleep for some time to free the CPU - 5 mins
         echo "Current time not within image capture range....".date('H:i')."\n";
         echo "Waiting...\n";
         sleep(300);
      } // currenttime > recordstarttim
   } //while needtowait

   $imagebase = $configarr["baseimagedir"];
   //create the required durectories
   foreach ($image_schedule as $scheduleval)
   {
      $imagedir = $imagebase."/".$scheduleval["cameraname"];
      $old = umask(0);
      if (!is_dir($imagedir))
      {
         if (!mkdir($imagedir, 0777, true)) 
         {
             // need a log
             echo "Could not create dierctory...".$imagedir;
         }//endif mkdir
         umask($old);
      }//end if is_dir
   } // foreach record schdule val

   //Open a file to so that the child processes can write status of the output
   $filestatus = fopen("Image_Status.txt", "w") or exit("Unable to open file!..Image_Status.txt");
   $filemedia = fopen("Image_Media_Status.txt", "w") or exit("Unable to open file!..Image_Media_Status.txt");

   //operate below 10% of max simltaneous recording
   $noofthreads = (count($image_schedule) < ((int)($simultimagesessions * 0.9)+1)) ? count($image_schedule) : ((int)($simultimagesessions * 0.9)+1);

   echo "No of concurrent image sessions...".$noofthreads."\n";
   echo "No of cameras images to capture...".count($image_schedule)."\n";

   $j = 0;
   while ($j < count($image_schedule)) 
   {
      //construct an array for which the threads have to be generated
      $k = 0;
      while($k < $noofthreads)
      {
         $tempimage_schedule[$k] = $image_schedule[$j];
         $k = $k + 1;
         $j = $j + 1;
      }
      $threads = array();
      $index = 0;

      foreach ($tempimage_schedule as $scheduleval)
      {
         $threads[$index] = new Thread( 'image_capture_camera' );
         $threads[$index]->start( $scheduleval, $imagebase."/".$scheduleval["cameraname"], $filestatus, $filemedia);
         ++$index;
      }

      // Let the cpu do its work till image capture is done
      sleep(1);   
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
      } // end while empty
   }// end while count($record_schedule)

   fclose($filestatus);
   fclose($filemedia);

   // Sleep for 10s to check next schedule
   echo "Sleeping before the next schedule";
   sleep( 10 ); 

} //end while infinite loop

?>

		
