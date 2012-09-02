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

function record_camera($camdetails, $recordduration, $recordir, $filestat, $filemedia) 
{
   //construct the rtsp url
   $username = $camdetails["username"];
   $password = $camdetails["password"];
   $streamid = $camdetails["streamid"];
   $stream = ($streamid == "1") ? "live.sdp" : "live".$streamid.".sdp";
   
   date_default_timezone_set('Asia/Kolkata');
   $date = date('Y-m-d H:i:s'); 
   $formatdate = str_replace (" " , "_", $date);
   $formatdate = str_replace ("-" , "_", $formatdate);
   $formatdate = str_replace (":" , "_", $formatdate);
   
   /* if (!is_dir($recordir))
   {
      echo "In recording...".$recordir." does not exits \n";
      $camdetails["status"] = "false";
      return;
   } */
   $filename = $camdetails["cameraname"]."_".$formatdate.".mp4";
   $filenamefull = $recordir."/".$filename;
   $filerecordstarttime = date('Y-m-d H:i:s');
   
   if($username == "notset")
   {
      $rtspurl = "rtsp://".$camdetails["ip"].":".$camdetails["port"]."/".$stream;
      
   }
   else
   {
      $rtspurl = "rtsp://".$username.":".$password."@".$camdetails["ip"].":".$camdetails["port"]."/".$stream;
   }
   
   //construct command for vlc
   $command = "/usr/bin/cvlc ".$rtspurl." --run-time=".$recordduration." --sout=file/mp4:".$filenamefull." vlc://quit";

   echo $command."\n"; 
   exec($command);
   
   $filerecordendtime = date('Y-m-d H:i:s');
   //check if recording happened successfully. check filze size > 2 Megabytes
   $recordfilesize = filesize($filenamefull);
   
   if ($recordfilesize > 2000000)
   {
      $camdetails["status"] = "true";
      fputs ($filemedia, $camdetails["cameraname"]." ".
                         $filename." ".
                         $filerecordstarttime." ".
                         $filerecordendtime."\n");
                         
   }
   else
   {
      $camdetails["status"] = "false";
      echo "Recording did not succeed...".$recordfilesize."\n";
      echo "Removing file...".$filenamefull."\n";
      //remove the file
      unlink($filenamefull);
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
   $filecfg = fopen("recordconfig.txt", "r") or exit("Unable to open file!..recordconfig.txt");
   while(!feof($filecfg))
   {
      $line = fgets( $filecfg );
      if($line == "")
         continue;
      $configarr[strtok($line, "=")] = rtrim(strtok("=")); 
   }
   fclose($filecfg);

   //populate the schedule table
   //generate the list of the schedule cameras
   $filein = fopen("Record_Schedule.txt", "r") or exit("Unable to open file!...Record_Schedule.txt");
   $i = 0;

   while(!feof($filein))
   {
     $line = fgets( $filein );
     if($line == "")
        continue;
   
      $record_schedule[$i]["cameraname"] = rtrim(strtok($line, " "));
      $record_schedule[$i]["cameramake"] = rtrim(strtok(" "));
      $record_schedule[$i]["ip"] = rtrim(strtok(" "));
      $record_schedule[$i]["port"] = rtrim(strtok(" "));
      $record_schedule[$i]["username"] = rtrim(strtok(" "));
      $record_schedule[$i]["password"] = rtrim(strtok(" "));
      $record_schedule[$i]["streamid"] = rtrim(strtok(" "));
      $record_schedule[$i]["status"] = "TBU"; // TBU To Be Updated later
      $i = $i + 1;
   }//end while feof
   fclose($filein);


   //Recording is assumed to happen daily, hence we will start at 
   // start of day that is i.e within an hour of recorddaystarttime else
   //we will wait for one day except if recordanytime is true

   date_default_timezone_set('Asia/Kolkata');

   $needtowait = 1;
   $camrecordingtime = $configarr["recordduration"] + 5;
   $simultrecordsessions = $configarr["simultrecordsessions"];

   while ($needtowait == 1)
   {
      if ($configarr["recordanytime"] == "false")
      {
         //wait for the start of the day i.e. wait till current time greater than
         //recorddaystarttime 
         $recordstarttime =  strtotime($configarr["recorddaystarttime"].":00");
         $recordendtime =  strtotime($configarr["recorddayendtime"].":00");
         $currenttime =  strtotime(date('H:i'));
         if (($currenttime > $recordstarttime) && ($currenttime < $recordendtime))
         {
            $needtowait = 0;
            //calculate whethr time will be sufficient to record
            // just to give warning.. 
            $timeavailtorecord = (int) ($recordendtime - $recordstarttime)/3600;
            $timeavailtorecordsecs = $timeavailtorecord*60*60;
            echo "Time available to record...".$timeavailtorecord." hrs\n";
            //no of cams than can record in timeavailtorecord
            $camsinavailtime = (int)($timeavailtorecordsecs / $camrecordingtime);
            echo "Cameras that can record in available time...".$camsinavailtime."\n";
            echo "Cameras to record in available time...".count($record_schedule)."\n";
            if (count($record_schedule) > ($camsinavailtime * $simultrecordsessions* 0.9))
            {
               //cannot record all the cams in available time using concurrency
               //contnue with a warning message
               echo "No of cameras to record more than available time..\n";
            }// count record_schedule
         }
         else
         {
            $needtowait = 1;
            //we can sleep for some time to free the CPU - 5 mins
            echo "Current time not within recording range....".date('H:i')."\n";
            echo "Waiting...\n";
            sleep(300);
         } // currenttime > recordstarttim
      } 
      else
      {
         $needtowait = 0;
      } //end if configarr
   } //while needtowait

   $recordbase = $configarr["baserecorddir"];
   //create the required durectories
   foreach ($record_schedule as $scheduleval)
   {
      $recorddir = $recordbase."/".$scheduleval["cameraname"];
      $old = umask(0);
      if (!is_dir($recorddir))
      {
         if (!mkdir($recorddir, 0777, true)) 
         {
             // need a log
             echo "Could not create dierctory...".$recorddir;
         }//endif mkdir
         umask($old);
      }//end if is_dir
   } // foreach record schdule val

   //Open a file to so that the child processes can write status of the output
   $filestatus = fopen("Record_Status.txt", "w") or exit("Unable to open file!..Record_Status.txt");
   $filemedia = fopen("Record_Media_Status.txt", "w") or exit("Unable to open file!..Record_Media_Status.txt");

   //operate below 10% of max simltaneous recording
   $noofthreads = (count($record_schedule) < ((int)($simultrecordsessions * 0.9)+1)) ? count($record_schedule) : ((int)($simultrecordsessions * 0.9)+1);

   echo "No of concurrent sessions...".$noofthreads."\n";
   echo "No of cameras to record...".count($record_schedule)."\n";

   $j = 0;
   while ($j < count($record_schedule)) 
   {
      //construct an array for which the threads have to be generated
      $k = 0;
      while($k < $noofthreads)
      {
         $temprecord_schedule[$k] = $record_schedule[$j];
         $k = $k + 1;
         $j = $j + 1;
      }
      $threads = array();
      $index = 0;

      foreach ($temprecord_schedule as $scheduleval)
      {
         $threads[$index] = new Thread( 'record_camera' );
         $threads[$index]->start( $scheduleval, $camrecordingtime, $recordbase."/".$scheduleval["cameraname"], $filestatus, $filemedia);
         ++$index;
      }

      // Let the cpu do its work till recording is done
      sleep( $camrecordingtime + 10 );   
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
         echo "Sleeping before checking for empty threads...\n";
         sleep(1); // Sleep for a second
      } // end while empty
   }// end while count($record_schedule)

   fclose($filestatus);
   fclose($filemedia);

   // Sleep for 10s to check next schedule
   echo "Sleeping before the next schedule";
   sleep( 10 ); 

} //end while infinite loop

?>
