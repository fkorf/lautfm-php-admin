<!-- Beispiel: Zeigt die aktuelle Playlist mit Markierung des laufenden Songs -->
<?php
include("config.php");
include("lautfmadmin.php");

$colorHighlight = "#DDDDDD";
$colorBackground = "#FAFAFA";

?>
<html>
	<head>
		<meta charset="utf-8" /> 
		<title><?php echo $station ?> - Aktuelle Playlist</title>
		<link rel="stylesheet" href="lautfmadmin.css">
		
		<script type="text/javascript" src="//api.laut.fm/js_tools/lautfm_js_tools.0.9.0.js" ></script>
		<script>
			var lastMarkedSong = 0;
			
			function updateCurrentSong(song) {
				var row;
				if(lastMarkedSong > 0) {
					row = document.getElementById('track' + lastMarkedSong);
					row.style.backgroundColor='<?php echo $colorBackground; ?>';
				}
				row = document.getElementById('track' + song.id);
				if(row != null) {
					row.style.backgroundColor='<?php echo $colorHighlight; ?>';
					row.scrollIntoView();
					lastMarkedSong = song.id;
				}
			}
		</script>
	</head>
	<body>

		<?php
		
		$lfm = new LautfmAdmin();
		$lfm->token = $token;
		$playlistId = $lfm->getCurrentPlaylistId($station);
		$playlist = $lfm->getPlaylist($station, $playlistId, 1);
		if(!is_null($lastLautError)) {
		  exit("Fehler: ".$lastLautError);
		}	
		?>
		
		<table class='lfmadmin' style="margin: 0 auto">
			<!-- Top-Zeile mit dem Namen der aktuellen Playlist -->
			<tr class='lfmtitle lfmbig'>
				<td colspan=4 style="padding:5pt"><?php echo $playlist->name; ?></td>
			</tr>
			<!-- Spaltenueberschriften -->
			<tr class='lfmhead'>
				<td class='lfmhead'>Start</td>
				<td class='lfmhead'>Interpret</td>
				<td class='lfmhead'>Titel</td>
				<td class='lfmhead'>L&auml;nge</td>
			</tr>
			<!-- Songs -->
			<?php
			$offset = 0;
			$usedIds = array();
			for($i = 0; $i < count($playlist->tracks); $i++) {
				$id = $playlist->tracks[$i]->id;
				
				// avoid reusing ids - just use first track occurence as target
				$trId = $id;
				while($usedIds[$trId] == 1) {
					$trId = $trId."x";
				}
				$usedIds[$trId]=1;
				
				echo "<tr class='lfmcontent' id='track$trId'>";
				echo "<td class='lfmcontent'>".gmdate("H:i:s", $offset)."</td>";
				echo "<td class='lfmcontent'>".$playlist->tracks[$i]->artist."</td>";
				echo "<td class='lfmcontent'>".$playlist->tracks[$i]->title."</td>";
				echo "<td class='lfmcontent'>".gmdate("i:s", $playlist->tracks[$i]->duration)."</td>";
				echo "</tr>\n";
				$offset += $playlist->tracks[$i]->duration;
			}
			?>
		</table>
		
		<script>
		  laut.fm.station('<?php echo $station; ?>').current_song(updateCurrentSong, true);
		</script>

</body>
</html>
