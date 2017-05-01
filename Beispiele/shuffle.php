<!-- Shuffeln einer ausgewaehlten Playlist mit Anzeige der Titel. -->
<?
include("config.php");
include("lautfmadmin.php");
include("lautfmadmin_shuffle.php");
$colorHighlight = "#EFEFEF";

?>
<html>
	<head>
		<meta charset="utf-8" /> 
		<title><? echo $station ?> - Aktuelle Playlist</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>

		<?
		$lfm = new LautfmAdmin();
		$lfm->token = $token;
		$shuffler = new PlaylistShuffler($lfm, $station);
		
		// ------- Konfiguration ---------
		
		// Abstand zwischen zwei Jingles in Minuten. 0 = gleichmaessig verteilen
	  $shuffler->defaultSettings->jingleInterval = 0;
	  
	  // Wenn erster Track Jingle ist: Stehen lassen?
		$shuffler->defaultSettings->protectFirstJingle = 1;
		
		// Jingles shuffeln (1) oder in Originalreihenfolge einfuegen (0)?
		$shuffler->defaultSettings->shuffleJingles = 1;
		
		// Maximale Zahl der Tacks pro Artist. Nur relevant wenn nicht ueber die volle Laenge geschuffelt wird.
		// 0 = Playlistlaenge in Stunden (z. B. 3 Tracks pro Artist in Playlist von 3 Stunden Laenge)
		$shuffler->defaultSettings->maxTracksPerArtist = 0;
		
		// Gewichtungen fuer Tags. Nur relevant wenn nicht ueber die volle Laenge geschuffelt wird.
		// $shuffler->defaultSettings->weights["uebergewichten"] = Weight::HIGH_MEDIUM;
		
		// Angepasste Settings fuer Playlists
		// $ps = $s->defaultSettings->copy();
		// $ps->maxTracksPerArtist = 2;
		// $s->registerPlaylistSettings("Meine Playlist", $ps);

		// ----- Ende Konfiguration ------
		
		$playlists = $lfm->getPlaylists($station);
		if(!is_null($lastLautError)) {
		  exit("Fehler: ".$lastLautError);
		}	
		
		$playlistId = $_GET['playlistId'];
		$hours = $_GET['hours'];
		if(!isset($hours)) {
			$hours = 0;
		}
		?>

		<table class='lfmadmin' style="margin: 0 auto">
			<!-- Top-Zeile mit dem Namen der aktuellen Playlist -->
			<tr class='lfmtitle lfmbig'>
					<td colspan=4 style="padding:5pt">Playlist shuffeln</td>
			</tr>
			<tr class='lfmhead'>
				<td colspan=4 class='lfmhead'>
					<form>
					<select size=1 name='playlistId'>
						<?
						for($i = 0; $i < count($playlists); $i++) {
							$selected = $playlists[$i]->id == $playlistId ? "selected" : "";
							echo "<option value='".$playlists[$i]->id."' $selected>";
							echo $playlists[$i]->name;
							echo "</option>";
						}
						?>
					</select>
					<?
					$hoursDisplay = $hours > 0 ? $hours : "";
					?>
					<input type="text" size="2" name="hours" value="<? echo $hoursDisplay; ?>"> Stunden
					<input type="submit" value="Shuffeln">
					</form>					
				</td>
			</tr>
			
		<?
		if($playlistId) {
			$playlist = $lfm->getPlaylist($station, $playlistId, 1);
			$tracks = $shuffler->shuffle($playlist, $hours);
			$lfm->setPlaylistTracks($station, $playlist->id, $tracks);
			if(!is_null($lastLautError)) {
			  exit("Fehler: ".$lastLautError);
			}	
			?>
				<!-- Spaltenueberschriften -->
				<tr class='lfmhead'>
					<td class='lfmhead'>Start</td>
					<td class='lfmhead'>Interpret</td>
					<td class='lfmhead'>Titel</td>
					<td class='lfmhead'>L&auml;nge</td>
				</tr>
				<!-- Songs -->
				<?
				$offset = 0;
				for($i = 0; $i < count($tracks); $i++) {
					$id = $tracks[$i]->id;
					$colorStyle = "";
					if($offset < $hours * 60 * 60) {
						$colorStyle = "style='background-color:$colorHighlight'";
					}
					echo "<tr class='lfmcontent'>";
					echo "<td class='lfmcontent' $colorStyle>".gmdate("H:i:s", $offset)."</td>";
					echo "<td class='lfmcontent'>".$tracks[$i]->artist."</td>";
					echo "<td class='lfmcontent'>".$tracks[$i]->title."</td>";
					echo "<td class='lfmcontent'>".gmdate("i:s", $tracks[$i]->duration)."</td>";
					echo "</tr>\n";
					$offset += $tracks[$i]->duration;
				}
		}
		?>	
		</table>
		
</body>
</html>
