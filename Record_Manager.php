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

function record_camera($camdetails, $recordduration, $recordir, $filestat) 
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
   
   if(!$username == "notset")
   {
      $rtspurl = "rtsp://".$username.":".$password."@".$camdetails["ip"].":".$camdetails["port"]."/".$stream;
   }
   else
   {
      $rtspurl = "rtsp://".$camdetails["ip"].":".$camdetails["port"]."/".$stream;
   }
   
   //construct command for vlc
   $command = "/usr/bin/cvlc ".$rtspurl." --run-time=".$recordduration." --sout=file/mp4:".$filenamefull." vlc://quit";

   echo $command."\n"; 
   exec($command);
   
   //check if recording happened successfully. check filze size > 2 Megabytes
   $recordfilesize = filesize($filenamefull);
   
   if ($recordfilesize > 2000000)
   {
      $camdetails["status"] = "true";
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

// calculate how many threads to run simultaneously
// assumption and formula
//1. Recording to be carried between 8.00 am to 8.00 pm (12 hrs)
//2. Total mins of available for recording (12hrs*60min)
//3. Recording duration - R (input from record.cofig file)
//4. Total Recording duration from all the cameras (no of cams * R)
//5. Simultaneous threads or recordings = (int) ((no of cams * R)/(12hrs*60min)*1.10)+ 1 (1.10 - 10% buffer and +1 in case no of threads are 0)

$noofthreads = (int)(((count($record_schedule) * $configarr["recordduration"])/(12*60)) * 1.10) + 1;

$threads = array();
$index = 0;

foreach ($record_schedule as $scheduleval)
{
   $threads[$index] = new Thread( 'record_camera' );
   $threads[$index]->start( $scheduleval, $configarr["recordduration"], $recordbase."/".$scheduleval["cameraname"], $filestatus);
   ++$index;
}

// wait for all the threads to finish
$i = 0;
while( !empty( $threads ) ) 
{    
   foreach( $threads as $index => $thread ) 
   {        
      if( ! $thread->isAlive() )
      {  
         unset( $threads[$index] );
      }
   }  // let the CPU do its work
   sleep( 1 );   
}


fclose($filestatus);

   // Sleep for 10 to check next process
   sleep( 10 ); 

} //end while infinite loop

?>
