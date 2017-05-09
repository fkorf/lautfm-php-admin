<!-- 
Shuffeln der naechsten Playlists 
Parameter:
- hours = Zeitraum fuer den Playlits geshuffelt werden sollen (Default = 1)
-->
<?php
include("config.php");
include("lautfmadmin.php");
include("lautfmadmin_shuffle.php");

?>
<html>
	<head>
		<meta charset="utf-8" /> 
		<title><?php echo $station ?> - Aktuelle Playlist</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>

<?php
$lfm = new LautfmAdmin();
$lfm->token = $token;
$shuffler = new PlaylistShuffler($lfm, $station);

// ------- Konfiguration ---------

// Name oder IDs von Playlists die NICHT geshuffelt werden sollten
$excludedPlaylists = array();
// Beispiel: 
// $excludedPlaylists = array("Charts", "Countdown");

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

$hours = $_GET['hours'];
if(!isset($hours)) {
	$hours = 1;
}

$entries = $lfm->getSchedule($station);
$cnt = 0;
?>

<table class='lfmadmin' style="margin: 0 auto" cellpadding='5'>
	<!-- Top-Zeile mit dem Namen der aktuellen Playlist -->
	<tr class='lfmtitle lfmbig'>
		<td colspan=4 style="padding:5pt">Playlists shuffeln</td>
	</tr>
	<?php
	$weekday = date("D");
	$startHour = date("G") + 1;
	for($i = 0; $i < count($entries); $i++) {
		if($entries[$i]->getWeekdayAsShortString() == $weekday && $entries[$i]->getStartHour() >= $startHour && $entries[$i]->getStartHour() < $startHour + $hours) {
			$cnt++;
			$playlist = $lfm->getPlaylist($station, $entries[$i]->playlistId, 1);
			echo "<tr class='lfmcontent'>";
			echo "<td>".$entries[$i]->getWeekdayAsShortString()."</td>";
			echo "<td>".$entries[$i]->getStartHour()." Uhr</td>";
			echo "<td>".$playlist->name."</td>";
			
			$status = "nicht geshuffelt";
			if($playlist->shuffled) {
				$status = "wird durch laut.fm geshuffelt";
			}
			else if(in_array($playlist->name, $excludedPlaylists) || in_array($playlist->id, $excludedPlaylists)) {
				$status = "vom Shuffeln augeschlossen";
			}
			else {
				$status = "Shuffeln f&uuml;r ".$entries[$i]->duration." Stunden";
				$tracks = $shuffler->shuffle($playlist,$entries[$i]->duration);
				$lfm->setPlaylistTracks($station, $playlist->id, $tracks);
				if(!is_null($lastLautError)) {
				  exit("Fehler: ".$lastLautError);
				}	
			}
			
			echo "<td>$status</td>";
			echo "</tr>\n";
		}
	}
	
	if($cnt == 0) {
		echo "<tr class='lfmcontent'><td>Im ausgew&auml;hlten Zeitraum starten keine Playlists</td></tr>";
	}
	?>
</table>


	</body>
</html>