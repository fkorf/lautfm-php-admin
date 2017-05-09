<!-- Beispiel: Track-Statistik -->
<?php
include("config.php");
include("lautfmadmin.php");

?>
<html>
	<head>
		<title><?php echo $station ?> - Trackstatistik</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>

		<?php
		
		$days = $_GET['days'];
		if($days == "") {
			$days = 1;
		}
		
		$lfm = new LautfmAdmin();
		$lfm->token = $token;
		$entries = $lfm->getTrackStatistics($station, $days);
		if(!is_null($lastLautError)) {
		  exit("Fehler: ".$lastLautError);
		}	
		?>
		
		<table class='lfmadmin' style="margin: 0 auto">
			<!-- Top-Zeile mit dem Namen der aktuellen Playlist -->
			<tr class='lfmtitle lfmbig'>
				<td colspan=5 style="padding:5pt">Gespielte Titel</td>
			</tr>
			<!-- Spaltenueberschriften -->
			<tr class='lfmhead'>
				<td class='lfmhead'>Datum</td>
				<td class='lfmhead'>Zeit</td>
				<td class='lfmhead'>Interpret</td>
				<td class='lfmhead'>Titel</td>
				<td class='lfmhead'>H&ouml;rer</td>
			</tr>
			<!-- Entries -->
			<?php
			for($i = 0; $i < count($entries); $i++) {
				echo "<tr class='lfmcontent'>";
				echo "<td class='lfmcontent'>".$entries[$i]->getDayAsString()."</td>";
				echo "<td class='lfmcontent'>".$entries[$i]->getStartTimeAsString()."</td>";
				echo "<td class='lfmcontent'>".$entries[$i]->artist."</td>";
				echo "<td class='lfmcontent'>".$entries[$i]->title."</td>";
				echo "<td class='lfmcontent'>".$entries[$i]->listeners."</td>";
				echo "</tr>\n";
			}
			?>
		</table>


</body>
</html>
