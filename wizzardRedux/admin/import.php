<?php

/* ------------------------------------------------------------------------------------
Import an existing DAT into the system

Requires:
	filename	File name in the format of "Manufacturer - SystemName (Source .*)\.dat"

TODO: Auto-generate DATs affected by import (merged and custom)?
------------------------------------------------------------------------------------ */

echo "<h2>Import From Datfile</h2>";

ini_set('max_execution_time', 0); // Set the execution time to infinite. This is a bad idea in production.

$auto = isset($_GET["auto"]) && $_GET["auto"] == "1";

if (!isset($_GET["filename"]))
{
	// List all files, auto-generate links to proper pages
	echo "<p><a href='?page=import&auto=1'>Automatically add all DATs</a></p>\n";
	
	$files = scandir("../temp/");
	if (sizeof($files) != 0)
	{
		foreach ($files as $file)
		{
			if (preg_match("/^.*\.dat$/", $file))
			{
				// If we want to import everything in the folder...
				if ($auto)
				{
					import_dat($file, $link);
					echo "<script type='text/javascript'>window.location='?page=import&auto=1'</script>";
				}
				else
				{
					echo "<a href=\"?page=import&filename=".$file."\">".htmlspecialchars($file)."</a><br/>\n";
				}
			}
		}
	}
}
else
{
	import_dat($_GET["filename"], $link);
	echo "<script type='text/javascript'>window.location='?page=import'</script>";
}

function import_dat($filename, $link)
{
	global $normalize_chars, $search_pattern;
	
	// First, get the pattern of the file name. This is required for organization.
	$datpattern = "/^(.+?) - (.+?) \((.*) (.*)\)\.dat$/";
	
	// Check the file is valid
	if (!file_exists("../temp/".$filename))
	{
		echo "<b>The file you supply must be in /wod/temp/</b><br/>";
		echo "<a href='index.php'>Return to home</a>";
	
		return;
	}
	elseif (!preg_match($datpattern, $filename, $fileinfo))
	{
		echo "<b>DAT not in the proper pattern! (Manufacturer - SystemName (Source .*)\.dat)</b><br/>\n";
		echo "<a href='index.php'>Return to home</a>";
	
		return;
	}
	
	echo "<p>The file ".$filename." has a proper pattern!</p>\n";
	
	// Next, get information from the database on the current machine
	$manufacturer = $fileinfo[1];
	$system = $fileinfo[2];
	$source = $fileinfo[3];
	$datestring = $fileinfo[4];
	preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $datestring, $date);
	$date = $date[1]."-".$date[2]."-".$date[3]." ".$date[4].":".$date[5].":".$date[6];
	
	$query = "SELECT id
		FROM systems
		WHERE manufacturer='$manufacturer'
			AND system='$system'";
	$result = mysqli_query($link, $query);
	
	if (!gettype($result) == "boolean" || mysqli_num_rows($result) == 0)
	{
		echo('Error: No suitable system found! Please add the system and then try again<br/>');
		return;
	}
	
	$sysid = mysqli_fetch_assoc($result);
	$sysid = $sysid["id"];
	
	if ($sourceid == "")
	{
		$query = "SELECT id
			FROM sources
			WHERE name='".$source."'";
		$result = mysqli_query($link, $query);
		
		if (!gettype($result) == "boolean" || mysqli_num_rows($result) == 0)
		{
			echo('Error: No suitable source found! Please add the source and then try again<br/>');
			return;
		}
		
		$sourceid = mysqli_fetch_assoc($result);
		$sourceid = $sourceid["id"];
	}
	
	// Then, parse the file and read in the information. Echo it out for safekeeping for now.
	$handle = fopen("../temp/".$filename, "r");
	if ($handle)
	{
		$format = "";
		$machinefound = false;
		$machinename = "";
		$description = "";
		$gameid = 0;
		$comment = false;
		
		echo "<h3>Roms Added:</h3>
	<table border='1'>
		<tr><th>Machine</th><th>Rom</th><th>Size</th><th>CRC32</th><th>MD5</th><th>SHA1</th></tr>\n";
		while (($line = fgets($handle)) !== false)
		{
			// If a machine or game tag is found, check to see if it's in the database
			// If it's not, add it to the database and then save the gameID
			
			// Normalize the whole line, just in case
			$line = strtr($line, $normalize_chars);
			
			// If the input style hasn't been set, set it according to the header
			if ($format == "")
			{
				if (strpos($line, "<!DOCTYPE datafile") !== false)
				{
					$format = "logiqx";
				}
				elseif (strpos($line, "<!DOCTYPE softwarelist") !== false)
				{
					$format = "softwarelist";
				}
				elseif (strpos($line, "clrmamepro (") !== false || strpos($line, "romvault (") !== false)
				{
					$format = "romvault";
				}
				else
				{
					$format = "unknown";
				}
			}
			
			// If there's an XML-style comment, stop the presses and skip until it's over
			elseif (strpos($line, "-->") !== false)
			{
				$comment = false;
			}
			elseif (strpos($line, "<!--") !== false)
			{
				$comment = true;
			}
			
			// Process Logiqx XML-derived DATs
			elseif ($format == "logiqx" && !$comment)
			{
				if ((strpos($line, "<machine") !== false || strpos($line, "<game") !== false))
				{
					$machinefound = true;
					$xml = simplexml_load_string($line.(strpos($line, "<machine")?"</machine>":"</game>"));
					$machinename = $xml->attributes()["name"];
					$machinename = preg_replace($search_pattern['EXT'], $search_pattern['REP'], $machinename);
					$gameid = add_game($sysid, $machinename, $sourceid, $link);
				}
				elseif (strpos($line, "<rom") !== false && $machinefound)
				{
					add_rom($line, $machinename, $link, "rom", $gameid, $date);
				}
				elseif (strpos($line, "<disk") !== false && $machinefound)
				{
					add_rom($line, $machinename, $link, "disk", $gameid, $date);
				}
				elseif ((strpos($line, "</machine>") !== false || strpos($line, "</game>") !== false))
				{			
					$machinefound = false;
					$machinename = "";
					$description = "";
					$gameid = 0;
				}
			}
			
			// Process SoftwareList XML-derived DATs
			elseif ($format == "softwarelist" && !$comment)
			{
				if (strpos($line, "<software") !== false)
				{
					$machinefound = true;
					$xml = simplexml_load_string($line."</software>");
					$machinename = $xml->attributes()["name"];
					$machinename = preg_replace($search_pattern['EXT'], $search_pattern['REP'], $machinename);
					$gameid = add_game($sysid, $machinename, $sourceid, $link);
				}
				elseif (strpos($line, "<rom") !== false)
				{
					add_rom($line, $machinename, $link, "rom", $gameid, $date);
				}
				elseif (strpos($line, "<disk") !== false)
				{
					add_rom($line, $machinename, $link, "disk", $gameid, $date);
				}
				elseif (strpos($line, "</software>") !== false)
				{
					$machinefound = false;
					$machinename = "";
					$description = "";
					$gameid = 0;
				}
			}
			
			// Process original style RomVault DATs
			elseif ($format == "romvault")
			{
				if (strpos($line, "game (") !== false)
				{
					$old = true;
				}
				elseif (strpos($line, "name") !== false && !$machinefound)
				{
					$machinefound = true;
					preg_match("/^\s*name \"(.*)\"$/", $line, $machinename);
					$machinename = $machinename[1];
					$machinename = preg_replace($search_pattern['EXT'], $search_pattern['REP'], $machinename);
					$gameid = add_game($sysid, $machinename, $sourceid, $link);
				}
				elseif (strpos($line, "rom (") !== false && $machinefound)
				{
					add_rom_old($line, $machinename, $link, "rom", $gameid, $date);
				}
				elseif (strpos($line, "disk (") !== false && $machinefound)
				{
					add_rom_old($line, $machinename, $link, "disk", $gameid, $date);
				}
				elseif (strpos($line, ")") !== false)
				{
					$machinefound = false;
					$machinename = "";
					$description = "";
					$gameid = 0;
				}
			}
		}
		echo "</table><br/>\n";
		
		fclose($handle);
		rename("../temp/".$filename, "../temp/imported/".$filename);
		
		return;
	}
	else
	{
		echo("Could not open file ".$filename."<br/>");
		return;
	}
}

function add_game ($sysid, $machinename, $sourceid, $link)
{
	// WoD gets rid of anything past the first "(" as the name, we will do the same
	$machinename = preg_replace("/^(.*?) (\(|\[).*$/", "\1", $machinename);
	
	$query = "SELECT id
	FROM games
	WHERE system=".$sysid."
	AND name='".$machinename."'
	AND source=".$sourceid;
	
	$result = mysqli_query($link, $query);
	if (gettype($result) == "boolean" || mysqli_num_rows($result) == 0)
	{
		$query = "INSERT INTO games (system, name, source)
		VALUES (".$sysid.", '".htmlspecialchars($machinename)."', ".$sourceid.")";
		$result = mysqli_query($link, $query);
		$gameid = mysqli_insert_id($link);
	}
	else
	{
		$gameid = mysqli_fetch_assoc($result);
		$gameid = $gameid["id"];
	}
	
	return $gameid;
}

function add_rom ($line, $machinename, $link, $romtype, $gameid, $date)
{
	$xml = simplexml_load_string($line);
	add_rom_helper($machinename, $link, $romtype, $gameid, $xml->attributes()["name"], $date, 
			$xml->attributes()["size"], $xml->attributes()["crc"], $xml->attributes()["md5"],
			$xml->attributes()["sha1"]);
}
	
function add_rom_old($line, $machinename, $link, $romtype, $gameid, $date)
{
	preg_match("/name \"(.*)\"/", $line, $name);
	$name = $name[1];
	$rominfo = explode(" ", $line);
	$size = ""; $crc = ""; $md5 = ""; $sha1 = ""; 
	
	$next = "";
	foreach ($rominfo as $info)
	{
		if ($info == "size" || $info == "crc" || $info == "md5" || $info == "sha1")
		{
			$next = $info;
		}
		elseif ($next != "")
		{
			switch ($next)
			{
				case "size": $size = $info; break;
				case "crc": $crc = $info; break;
				case "md5": $md5 = $info; break;
				case "sha1": $sha1 = $info; break;
				default: break;
			}
			$next = "";
		}
	}
	
	add_rom_helper($machinename, $link, $romtype, $gameid, $name, $date, $size, $crc, $md5, $sha1);
}
	
function add_rom_helper($machinename, $link, $romtype, $gameid, $name, $date, $size, $crc, $md5, $sha1)
{
	if ($romtype != "rom" && $romtype != "disk")
	{
		$romtype = "rom";
	}
	
	// Check for the existance of the rom in the given system and game
	// If it doesn't exist, create the rom with the information provided	
	$query = "SELECT files.id
	FROM files
	JOIN checksums
	ON files.id=checksums.file
	WHERE files.name='".addslashes($name)."'
		AND files.type='".$romtype."'
		AND files.setid=".$gameid."
		AND checksums.size=".$size."
		AND checksums.crc='".$crc."'
		AND checksums.md5='".$md5."'
		AND checksums.sha1='".$sha1."'";
	$result = mysqli_query($link, $query);
	if (gettype($result)=="boolean" || mysqli_num_rows($result) == 0)
	{
		$query = "SELECT files.id FROM files WHERE files.name='".addslashes($name)."'";
		$result = mysqli_query($link, $query);

		$query = "INSERT INTO files (setid, name, type, lastupdated)
		VALUES (".$gameid.",
		'".addslashes($name)."',
		'".$romtype."',
		'".$date."')";
		$result = mysqli_query($link, $query);

		if (gettype($result)=="boolean" && $result)
		{
			$romid = mysqli_insert_id($link);

			$query = "INSERT INTO checksums (file, size, crc, md5, sha1)
		VALUES (".$romid.",
				".$size.",
				'".$crc."',
				'".$md5."',
				'".$sha1."')";
			$result = mysqli_query($link, $query);

			if (gettype($result)=="boolean" && $result)
			{
				echo "<tr><td>".$machinename."</td><td>".$name."</td><td>".$size."</td><td>".$crc."</td><td>".$md5."</td><td>".$sha1."</td></tr>\n";
			}
			else
			{
				echo("MYSQL Error! ".mysqli_error($link)."<br/>");
				return;
			}
		}
		else
		{
			echo("MYSQL Error! ".mysqli_error($link)."<br/>");
			return;
		}
	}
}
	
?>