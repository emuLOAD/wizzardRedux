<?php
	print "<pre>";

	$query=implode ('', file ("http://vjetnam.hopto.org/index.php?frame=games"));
 	$query=explode ("HREF='index.php?frame=lett&dir=", $query);
	$query[0]=null;

	print "found";

	$dirArray=Array();

	foreach($query as $row)
	{
		if($row)
		{
			$dir=explode('&',$row);
			$dir=$dir[0];
			$dirArray[$dir]=$dir;
			print " ".$dir;
		}
	}
	
	print "\n";

	$r_query=implode ('', file ($_GET["source"]."/ids.txt"));
	$r_query=explode ("\r\n",$r_query);
	$r_query=array_flip($r_query);

	$URLs=Array();

	foreach($dirArray as $dir)
	{
		for ($page=0;$page<100;$page++)
		{
			$query=implode ('', file ("http://vjetnam.hopto.org/index.php?frame=lett&dir=".$dir."&page=".$page));
		 	$query=explode ("<span class='name'>&nbsp;", $query);
	
			if($query[1])
			{
				$query[0]=null;
	
				$found=0;
				$new=0;
			
				foreach($query as $row)
				{
					if($row)
					{
						$gametitle=explode('</span>',$row);
						$gametitle=$gametitle[0];
			
						$copyright=explode('&nbsp;Copyright: </font>',$row);
						$copyright=explode('<font',$copyright[1]);
						$copyright=$copyright[0];
			
						$developer=explode('&nbsp;Developer: </font>',$row);
						$developer=explode('</span',$developer[1]);
						$developer=$developer[0];
			
						$year=explode('&nbsp;Year: </font>',$row);
						$year=explode('</span',$year[1]);
						$year=$year[0];
			
						$url=explode('HREF="./dow/',$row);
						$url=explode('"',$url[1]);
						$url=$url[0];
			
						if($copyright!="????") $gametitle=$gametitle." (".$copyright.")";
						if($developer!="????") $gametitle=$gametitle." (".$developer.")";
						if($year!="????") $gametitle=$gametitle." (".$year.")";
			
						$found++;
					
						if(!$r_query[$url])
						{
							$URLs[]=Array($url,$gametitle);
							$new++;
						}
					}
				}
		
				print "load ".$dir.", page ".$page.", found ".$found.", new ".$new."\n";
			}
			else
			{
				break;
			}
		}
	}

	print "<table><tr><td><pre>";

	foreach($URLs as $row)
	{
		print $row[0]."\n";
	}

	print "</td><td><pre>";

	foreach($URLs as $row)
	{
		print "<a href=\"http://vjetnam.hopto.org/dow/".$row[0]."\">".$row[1].".zip</a>\n";
	}

	print "</td></tr></table>";

?>