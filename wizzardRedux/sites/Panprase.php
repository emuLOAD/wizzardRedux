<?php
	print "<pre>";

	if($_GET["start"])
	{
		$start=$_GET["start"];
		$fp = fopen($_GET["source"]."/start.txt", "w");
		fwrite($fp,	$start);
		fclose($fp);
	}
	else
	{
		$start=implode ('', file ($_GET["source"]."/start.txt"));
	}

	print "\nSearch for new uploads\n\n";

	for ($x=$start;$x<$start+50;$x++)
	{
		$query=implode ('', file ("http://atari.panprase.cz/?action=detail&co=".$x));

		$gametitle=explode ("<h2 class='titulek'>", $query);
		$gametitle=explode ('</h2>', $gametitle[1]);
		$gametitle=trim($gametitle[0]);

		if($gametitle)
		{
			$info=explode ("<br />Sekce: ", $query);
			$info=explode ("<br /><br />",$info[0]);
			$info=explode ("<br>",$info[1]);
		
			$title_info=array();

			foreach($info as $row){
				$row=explode (": ",$row);
				$row=trim($row[count($row)-1]);
				if(($row)&&($row!='?')) $title_info[]=$row;
			}

			if($title_info) $gametitle=$gametitle." (".implode(", ",$title_info).")";

			print $x."\t<a href=http://atari.panprase.cz/download.php?soubor=".$x." target=_blank>".$gametitle.".zip</a>\n";
			$last=$x;
		}
	}

	if($last) $start=$last+1;

	print "\nnext startnr\t<a href=?action=onlinecheck&source=Panprase&start=".($start).">".$start."</a>\nchecked until\t".$x."\n";

?>