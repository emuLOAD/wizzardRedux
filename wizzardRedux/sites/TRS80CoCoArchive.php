<?php

$dirs=Array(
	'http://www.colorcomputerarchive.com/coco/',
);

$r_query=implode ('', file ($_GET["source"]."/ids.txt"));
$r_query=explode ("\r\n","\r\n".$r_query);
$r_query=array_flip($r_query);

$newURLs=Array();

function listDir($dir,$add){

	GLOBAL $newURLs, $r_query;

	print "load: ".$dir."\n";

	$query=implode ('', file ($dir));
	$query=explode('>Parent Directory<',$query);
	$query=explode(' href="',$query[1]);
	$query[0]=null;

	$new=0;
	$old=0;

	$last='';

	foreach($query as $row){
		if($row){
			$url=explode('"',$row);
			$url=html_entity_decode($url[0]);
		
			if($last!=$url){
				$xadd=str_replace('/','}{',$add);
				$xadd='{'.$xadd.'}';
				$xadd=str_replace('{}','',$xadd);
	
				if(substr($url, -1)=='/'){
					listDir($dir.$url,$add.$url);
				}else{
					if(!$r_query[$xadd.$url])
					{
						$newURLs[]=Array($dir.$url,$xadd.$url);
						$new++;
					}
					else
					{
						$old++;
					}
				}
			}

			$last=$url;
		}
	}

	print "close: ".$dir."\n";
	print "new: ".$new.", old: ".$old."\n";
}

print "<pre>check folders:\n\n";

foreach($dirs as $dir)
{
	listDir($dir,'');
}

print "\nnew urls:\n\n";

foreach($newURLs as $url)
{
	print "<a href=\"".$url[0]."\">".$url[1]."</a>\n";
}

?>