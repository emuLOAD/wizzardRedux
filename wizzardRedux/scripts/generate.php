<!-- 

Create a DAT from the database

Requires:
	mode		[Optional, defaults to merged] Either custom or merged dats, if custom, requires second param
	source		[Required by mode=custom] ID of the source as it appears in the database to create a DAT from
	system		ID of the system that is to be polled
	old			[Optional] set this to 1 for the old style output
	
Note: Can we automatically figure out what systems are related to what sources? We can!

SELECT systems.id AS sysid, systems.manufacturer AS manufacturer, systems.system AS system, sources.id AS sourceid, sources.name AS source
FROM systems
JOIN games ON systems.id=games.system
JOIN sources ON games.source=sources.id
-->

<?php

echo "<h2>Export to Datfile</h2>";

$mode = "lame";
$path_to_root = (getcwd() == "/wod/" ? "" : "..");

// Check the output mode first
if ($_GET["mode"] == "custom" && $_GET["source"] && $_GET["system"])
{
	$mode = "custom";
	$source = $_GET["source"];
	$system = $_GET["system"];
}
elseif (gettype($_GET["system"])=="integer")
{
	$mode = "merged";
	$system = $_GET["system"];
}

if ($mode == "lame")
{
	echo "<b>You must have the following parameters:<br/>
		system (must be the number), mode (optional), source (required with mode=custom)</b><br/><br/>";
	echo "<a href=\"".$path_to_root."/index.php\">Return to home</a>";
	
	die();
}

echo "The mode is ".$mode."<br/>";

// Check if the given values for source and system are actually valid
$link = mysqli_connect('localhost', 'root', '', 'wod');
if (!$link)
{
	die('Error: Could not connect: ' . mysqli_error($link));
}

echo "Connection established!<br/>";

$query = "SELECT *
FROM systems
WHERE id='$system'";
$result = mysqli_query($link, $query);
if (gettype($result)=="boolean" || mysqli_num_rows($result) == 0)
{
	echo "<b>The system number provided was not valid, please check your code and try again</b><br/><br/>";
	echo "<a href=\"".$path_to_root."/index.php\">Return to home</a>";
	
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
		echo "<a href=\"".$path_to_root."/index.php\">Return to home</a>";
	
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
			WHERE systems.id=$system".
				($mode == "custom" ? " AND sources.id=$source" : "");

$result = mysqli_query($link, $query);

if (gettype($result)=="boolean" && !$result)
{
	echo "MYSQL Error! ".mysqli_error($link)."<br/>";
	die();
}

$roms = mysqli_fetch_array($result);

if (sizeof($roms) == 0)
{
	echo "There are no roms found for these inputs. Please try again<br/>";
	die();
}

echo "<table border='1'>
	<tr><th>Source</th><th>Set</th><th>Name</th><th>Size</th><th>CRC32</th><th>MD5</th><th>SHA1</th></tr>";

$machine = ""; // To help figure out when we hit a new game, check for the set name to change

foreach ($roms as $rom)
{
	echo "<tr><td>".$rom["source"]."</td><td>".$rom["game"]."</td><td>".$rom["name"]."</td><td>".$rom["size"]."</td><td>".$rom["crc"]."</td><td>".$rom["md5"]."</td><td>".$rom["sha1"]."</td></tr>";
}
echo "</table>";

/*

NOTE: The format that WoD used was a VERY old format which is described first

clrmamepro (
	name "DATNAME"
	description "DESCRIPTION"
	version "VERSION"
	comment "COMMENT"
	author "The Wizard of DATz"
)

game (
	name "NAME"
	rom ( name "ROMNAME" size SIZE crc CRC md5 MD5 sha1 SHA1 )
	disk ( name "DISKNAME" sha1 SHA1 )
)

The modern datfile output format:

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE datafile PUBLIC "-//Logiqx//DTD ROM Management Datafile//EN" "http://www.logiqx.com/Dats/datafile.dtd">

<datafile>
	<header>
		<name>DATNAME</name>
		<description>DESCRIPTION</description>
		<category>CATEGORY</category>
		<version>VERSION</version>
		<date>DATE</date>
		<author>The Wizard of DATz</author>
		<email>EMAIL</email>
		<homepage>HOMEPAGE</homepage>
		<url>URL</url>
		<comment>COMMENT</comment>
		<clrmamepro/>
	</header>
	<machine name="NAME" romof="PARENT">
		<description>ROMDESC</description>
		<rom name="ROMNAME" size="SIZE" crc="CRC" md5="MD5" sha1="SHA1"/>
		<disk name="DISKNAME" md5="MD5" sha1="SHA1" />
	</machine>
</datafile>

*/

// Create and open an output file for writing (currently uses current time, change to "last updated time"
$handle = fopen($path_to_root."/temp/".$system." (".($mode == "custom" ? $source : "merged")." ".date("YmdHis").").dat", "w");

$header_old = <<<END
clrmamepro (
	name "DATNAME"
	description "DESCRIPTION"
	version "VERSION"
	comment "COMMENT"
	author "The Wizard of DATz"
)
END;

$header = <<<END
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE datafile PUBLIC "-//Logiqx//DTD ROM Management Datafile//EN" "http://www.logiqx.com/Dats/datafile.dtd">

<datafile>
	<header>
		<name>DATNAME</name>
		<description>DESCRIPTION</description>
		<category>CATEGORY</category>
		<version>VERSION</version>
		<date>DATE</date>
		<author>The Wizard of DATz</author>
		<email>EMAIL</email>
		<homepage>HOMEPAGE</homepage>
		<url>URL</url>
		<comment>COMMENT</comment>
		<clrmamepro/>
	</header>
END;

$footer = "\r\n</datafile>";

if ($_GET["old"] == "1")
{
	fwrite($handle, $header_old);
}
else
{
	fwrite($handle, $header);
	fwrite($handle, $footer);
}
fclose($handle);

mysqli_close($link);

?>