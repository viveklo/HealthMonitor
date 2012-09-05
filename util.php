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

   //read config file
function read_config_file ($configfile) 
{
   $configarr = array();
   $filecfg = fopen($configfile, "r"); 
   if($filecfg)
   {
      while(!feof($filecfg))
      {
         $lineraw = fgets( $filecfg );
	 $line = trim($lineraw);
         if($line == "")
            continue;
	 //check for comment line
	 if ($line[0] == "#") 
	    continue;
	 
         $configarr[strtok($line, "=")] = rtrim(strtok("=")); 
      }
      fclose($filecfg);
      return $configarr;
   }
   else
   {
      echo "Unable to open file!...".$configfile."\n";
      exit;
      return $configarr;
   } 

}

?>

		
