<?php

/*
Check for new downloadable ROMs from all available sites

Requires:
	source		The sourcename to check against

Note: This is the most tedious one of all. All of the checks should be named as "sites/<sitename>.php".

TODO: Retool existing onlinecheck.php files to follow the new format
		2 passes: 1) reformat file and categorize, 2) check code flow to try to optimize
TODO: Add a way to figure out if a site is dead based on the original list that WoD created
TODO: Most explode/implode can probably be changed to preg_match, just need to decipher them
*/

// Site whose checkers have been once-overed (not all checked for dead)
$checked = array (
		"6502dude",
		"8BitChip",						// Probably dead
		"8BitCommodoreItalia",			// Probably dead
		"AcornPreservation",
		"ADVAnsCEne",					// External site only
		"alexvampire",
		"AmstradESP",
		"ANN",
		"Apple2Online",
		"AppleIIgsInfo",
		"Arise64",
		"AtariAge",
		"Atarimania",
		"AtariOnline",
		"BananaRepublic",
		"bjars",
		"BrutalDeluxeSoftware",
		"c16de",
		"C64ch",
		"c64com",
		"c64gamescom",					// Empty checker page?
		"c64gamesde",
		"C64Heaven",
		"C64intros",
);

if (!isset($_GET["source"]))
{
	echo "<h2>Please Choose a Site</h2>\n";
	
	// List all files, auto-generate links to proper pages
	$files = scandir("../sites/", SCANDIR_SORT_NONE);
	foreach ($files as $file)
	{
		if (preg_match("/^.*\.php$/", $file))
		{
			$file = substr($file, 0, sizeof($file) - 5);
			echo "<a href=\"?page=onlinecheck&source=".$file."\">".htmlspecialchars($file)."</a><br/>";
		}
	}

	echo "<br/><a href='".$path_to_root."/index.php'>Return to home</a>";

	die();
}
elseif (!file_exists("../sites/".$_GET["source"].".php"))
{
	echo "<b>The file you supply must be in /wod/sites/</b><br/>";
	echo "<a href='index.php'>Return to home</a>";

	die();
}

$source = $_GET["source"];

if (in_array($source, $checked))
{
	echo "<h2>Loading pages and links...</h2>";
	
	$r_query = file("../sites/".$source.".txt");
	$r_query = array_flip($r_query);
	
	// There is the case that all keys will contain a whitespace character at the end
	$s_query = array();
	while (list($k, $v) = each($r_query))
	{
		$s_query[trim($k)] = $r_query[$k];
	}
	$r_query = $s_query;
	unset($s_query);
	
	$found = array();
	$base_dl_url = "";

	// Original code: The Wizard of DATz
	include_once("../sites/".$source.".php");

	echo "<h2>New files:</h2>";
	
	foreach ($found as $row)
	{
		echo htmlspecialchars($row)."<br/>";
		echo "<a href='".$base_dl_url.$row[0]."'>".$row[0]."</a><br/>";
	}
}

?>