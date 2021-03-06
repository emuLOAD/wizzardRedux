<?php
/*

New DAT creation system

This new DAT generator is based verbatim off the old one insofar as DAT creation goes, however it offers the
additional functionality of being entirely dynamic. When the user passes it the right parameters, the DAT is
automatically generated and compressed.

Requires:
	source		[Required by mode=custom] ID of the source as it appears in the database to create a DAT from
	system		ID of the system that is to be polled
	old			[Optional] set this to 1 for the old style output
	
	TODO: Create DAT selection menu/customization interface_exists
	TODO; Clean-up the code
	
*/
$mode = "lame";

// Check the output mode first
if (isset($_GET["source"]) && isset($_GET["system"]))
{
	$mode = "custom";
	$source = $_GET["source"];
	$system = $_GET["system"];
}
elseif (isset($_GET["system"]))
{
	$mode = "merged";
	$system = $_GET["system"];
}

if ($mode == "lame")
{
	echo "<b>You must have the following parameters:<br/>
		system (must be the number), mode (optional), source (required with mode=custom)</b><br/><br/>";
	echo "<a href=\"index.php\">Return to home</a>";
	
	die();
}

//echo "The mode is ".$mode."<br/>";

// Check if the given values for source and system are actually valid
$link = mysqli_connect('localhost', 'root', '', 'wod');
if (!$link)
{
	die('Error: Could not connect: ' . mysqli_error($link));
}

$query = "SELECT *
FROM systems
WHERE id='$system'";
$result = mysqli_query($link, $query);
if (gettype($result)=="boolean" || mysqli_num_rows($result) == 0)
{
	echo "<b>The system number provided was not valid, please check your code and try again</b><br/><br/>";
	echo "<a href=\"index.php\">Return to home</a>";
	
	die();
}

if ($mode == "custom")
{
	$query = "SELECT *
	FROM sources
	WHERE id='$source'";
	$result = mysqli_query($link, $query);
	if (gettype($result)=="boolean" || mysqli_num_rows($result) == 0)
	{
		echo "<b>The source number provided was not valid, please check your code and try again</b><br/><br/>";
		echo "<a href=\"index.php\">Return to home</a>";
	
		die();
	}
}

// Now that everything is checked, create the queries that will get all of the information for the DAT
$query = "SELECT systems.manufacturer AS manufacturer, systems.system AS system, sources.name AS source, sources.url AS url,
				games.name AS game, files.name AS name, files.type AS type, checksums.size AS size, checksums.crc AS crc,
				checksums.md5 AS md5, checksums.sha1 AS sha1
			FROM systems
			JOIN games
				ON systems.id=games.system
			JOIN sources
				ON games.source=sources.id
			JOIN files
				ON games.id=files.setid
			JOIN checksums
				ON files.id=checksums.file
			WHERE systems.id=".$system.
				($mode == "custom" ? " AND sources.id=$source" : "");
				
$result = mysqli_query($link, $query);

if (gettype($result)=="boolean" && !$result)
{
	echo "MYSQL Error! ".mysqli_error($link)."<br/>";
	die();
}

if (mysqli_num_rows($result) == 0)
{
	echo "There are no roms found for these inputs. Please try again<br/>";
	die();
}

$roms = Array();
while($rom = mysqli_fetch_assoc($result))
{
	array_push($roms, $rom);
}

// If creating a merged DAT, remove all duplicates and then sort back again
if ($mode == "merged")
{
	$roms = merge_roms($roms);
}

if (isset($_GET["debug"]) && $_GET["debug"] == "1")
{
	echo "<table border='1'>
		<tr><th>Source</th><th>Set</th><th>Name</th><th>Size</th><th>CRC32</th><th>MD5</th><th>SHA1</th></tr>";
	
	foreach ($roms as $rom)
	{
		echo "<tr><td>".$rom["source"]."</td><td>".$rom["game"]."</td><td>".$rom["name"]."</td><td>".$rom["size"]."</td><td>".$rom["crc"]."</td><td>".$rom["md5"]."</td><td>".$rom["sha1"]."</td></tr>";
	}
	
	echo "</table>";
}

$version = date("YmdHis");
$datname = $roms[0]["manufacturer"]." - ".$roms[0]["system"]." (".($mode == "custom" ? $roms[0]["source"] : "merged")." ".$version.")";

//First thing first, push the http headers
header('content-type: application/x-gzip');
header('Content-Disposition: attachment; filename="'.$datname.'.dat.gz"');

$old = (isset($_GET["old"]) && $_GET["old"] == "1");

$header_old = <<<END
clrmamepro (
	name "$datname"
	description "$datname"
	version "$version"
	comment ""
	author "The Wizard of DATz"
)\r\n
END;

$header = <<<END
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE datafile PUBLIC "-//Logiqx//DTD ROM Management Datafile//EN" "http://www.logiqx.com/Dats/datafile.dtd">

<datafile>
	<header>
		<name>$datname</name>
		<description>$datname</description>
		<category>The Wizard of DATz $mode</category>
		<version>$version</version>
		<date>$version</date>
		<author>The Wizard of DATz</author>
		<email></email>
		<homepage></homepage>
		<url></url>
		<comment></comment>
		<clrmamepro/>
	</header>\r\n
END;

$footer = "	r\n</datafile>";

$lastgame = "";
if ($old)
{
	echo  gzencode($header_old,9);
	foreach ($roms as $rom)
	{
		$state = "";		
		if ($lastgame != "" && $lastgame != $rom["game"])
		{
			$state = $state.")\r\n";
		}
		if ($lastgame != $rom["game"])
		{
			$state = $state."game (\r\n".
						"\t name \"".$rom["game"]."\"\r\n";
		}
		$state = $state."\t".$rom["type"]." ( name \"".$rom["name"]."\"".
				($rom["size"] != "" ? " size ".$rom["size"] : "").
				($rom["crc"] != "" ? " crc ".$rom["crc"] : "").
				($rom["md5"] != "" ? " md5 ".$rom["md5"] : "").
				($rom["sha1"] != "" ? " sha1 ".$rom["sha1"] : "").
				" )\n";

		$lastgame = $rom["game"];
		
		echo gzencode($state,9);
	}
	echo gzencode(")",9);
}
else
{
	echo gzencode($header,9);
	foreach ($roms as $rom)
	{
		$state = "";
		
		if ($lastgame != "" && $lastgame != $rom["game"])
		{
			$state = $state."\t</machine>\r\n\r\n";
		}
		if ($lastgame != $rom["game"])
		{
			$state = $state."\t<machine name=\"".$rom["game"]."\">\r\n".
				"\t\t<description>".$rom["game"]."</description>\r\n";
		}
		$state = $state."\t\t<".$rom["type"]." name=\"".$rom["name"]."\"".
			($rom["size"] != "" ? " size=\"".$rom["size"]."\"" : "").
			($rom["crc"] != "" ? " crc=\"".$rom["crc"]."\"" : "").
			($rom["md5"] != "" ? " md5=\"".$rom["md5"]."\"" : "").
			($rom["sha1"] != "" ? " sha1=\"".$rom["sha1"]."\"" : "").
			" />\n";
			
		$lastgame = $rom["game"];
		
		echo gzencode($state,9);
	}
	echo gzencode("\t</machine>\r\n",9);
	echo gzencode($footer,9);
}

//echo gzencode($allout,9);

mysqli_close($link);

// Functions
function merge_roms($roms)
{	
	// First sort all roms by name and crc (or md5 or sha1)
	usort($roms, function ($a, $b)
	{
		$crc_a = strtolower($a["crc"]);
		$md5_a = strtolower($a["md5"]);
		$sha1_a = strtolower($a["sha1"]);
		$source_a = $a["source"];
		$crc_b = strtolower($b["crc"]);
		$md5_b = strtolower($b["md5"]);
		$sha1_b = strtolower($b["sha1"]);
		$source_b = $b["source"];
		
		if ($crc_a == "" || $crc_b == "")
		{
			if ($md5_a == "" || $md5_b == "")
			{
				if ($sha1_a == "" || $sha1_b == "")
				{
					return $source_a - $source_b;
				}
				return strcmp($sha1_a, $sha1_b);
			}
			return strcmp($md5_a, $md5_b);
		}
		return strcmp($crc_a, $crc_b);
	});
		
	// Then, go through and remove any duplicates (size, CRC/MD5/SHA1 match)
	$lastsize = ""; $lastcrc = ""; $lastmd5 = ""; $lastsha1 = ""; $lasttype = "";
	$newroms = Array();
	foreach($roms as $rom)
	{
		if ($lastsize == "")
		{
			$lastsize = $rom["size"];
			$lastcrc = $rom["crc"];
			$lastmd5 = $rom["md5"];
			$lastsha1 = $rom["sha1"];
			$lasttype = $rom["type"];
			array_push($newroms, $rom);
		}
		else
		{
			// Determine which matching criteria is available and match on them
			$samesize = false; $samecrc = false; $samemd5 = false; $samesha1 = false;
			if ($rom["size"] != "")
			{
				$samesize = ($lastsize == $rom["size"]);
			}
			if ($rom["crc"] != "")
			{
				$samecrc = ($lastcrc == $rom["crc"]);
			}
			if ($rom["md5"] != "")
			{
				$samemd5 = ($lastmd5 == $rom["md5"]);
			}
			if ($rom["sha1"] != "")
			{
				$samesha1 = ($lastsha1 == $rom["sha1"]);
			}
			
			// If we have a rom, we need at least the size and one criteria to match
			if ($rom["type"] == "rom")
			{
				if (!($samesize && ($samecrc || $samemd5 || $samesha1)))
				{
					array_push($newroms, $rom);
				}
			}
			// If we have a disk, it generally only has an md5 or sha1
			else
			{
				if (!($samemd5 || $samesha1))
				{
					array_push($newroms, $rom);
				}
			}
				
			$lastsize = $rom["size"];
			$lastcrc = $rom["crc"];
			$lastmd5 = $rom["md5"];
			$lastsha1 = $rom["sha1"];
			$lasttype = $rom["type"];
		}
	}
	
	// Then rename the sets to include the proper source
	foreach ($newroms as &$rom)
	{
		$rom["game"] = $rom["game"]." [".$rom["source"]."]";
	}
	
	// Once it's pruned, revert the order of the files by sorting by game
	usort($newroms, function ($a, $b)
	{		
		$game_a = strtolower($a["game"]);
		$game_b = strtolower($b["game"]);
		
		return strcmp($game_a, $game_b);
	});
	
	// Finally, change the pointer of $roms to the new array
	return $newroms;
}

?>