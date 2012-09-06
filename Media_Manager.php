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
require_once ('Record_Manager.php');
require_once ('Image_Manager.php');
require_once ('Ping_Manager.php');

$videothreadstarted = false;
$imagethreadstarted = false;
$pingthreadstarted = false;
while (1)
{
  // Logic to get record schedule, image schedule or if none/done for the day
   
   $configarr = read_config_file("mediaconfig.txt");
   
   //check if recordvideo=true
   if($configarr["recordvideo"] == "true" && $videothreadstarted == false)
   {
      echo "Starting Recording Manager \n";
      //start the video record thread
      $videothread = new Thread('process_camera_recoding');
      $videothread->start( "mediaconfig.txt", "Record_Schedule.txt", "Record_Status.txt");
      $videothreadstarted = true;
   }

   if ($configarr["imagesnapshot"] == "true" && $imagethreadstarted == false)
   {
      echo "Starting Image Manager \n";
      $imagethread = new Thread( 'process_camera_image_capture');
      $imagethread->start( "mediaconfig.txt", "Image_Schedule.txt", "Image_Status.txt");
      $imagethreadstarted = true;
   }

   if ($configarr["pingcamera"] == "true" && $pingthreadstarted == false)
   {
      echo "Starting Ping Manager \n";
      $pingthread = new Thread( 'process_camera_ping');
      $pingthread->start( "mediaconfig.txt", "Ping_Schedule.txt", "Ping_Status.txt");
      $pingthreadstarted = true;
   }

   // sleep for 5 mins before going back in loop
   echo "Media Manager Sleeping for 5 mins before checking any request \n";
   sleep(300); 

   if ($videothreadstarted)
   {
      if( ! $videothread->isAlive() )
      {  
         $videothreadstarted = false;
      }
   }

   if ($imagethreadstarted)
   {
      if( ! $imagethread->isAlive() )
      {  
         $imagethreadstarted = false;
      }
   }
   
   if ($pingthreadstarted)
   {
      if( ! $pingthread->isAlive() )
      {  
         $pingthreadstarted = false;
      }
   }
}

?>
