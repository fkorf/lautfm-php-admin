<!-- Beispiel: Ausgabe der Live-Statistik -->
<?

include("config.php");
include("lautfmadmin.php");

$lfm = new LautfmAdmin();
$lfm->token = $token;
$logs = $lfm->getLiveLog($station);

?>

<html>
	<head>
		<title>Live-Statistik</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>
	
	<table class='lfmadmin'>
	  <tr class='lfmtitle'>
			<td colspan=4 style="padding:5pt">Live-Sessions</td>
	  </tr>
		<tr class="lfmhead">
			<td class='lfmhead'>Datum</td>
			<td class='lfmhead'>Start</td>
			<td class='lfmhead'>Ende</td>
			<td class='lfmhead'>Dauer</td>
		</tr>
		<?
		$total = 0;
		for($i = 0; $i < count($logs); $i++) {
			echo "<tr class='lfmcontent'>";
			echo "<td class='lfmcontent'>".$logs[$i]->getDayAsString()."</td>";
			echo "<td class='lfmcontent'>".$logs[$i]->getStartTimeAsString()." Uhr</td>";
			echo "<td class='lfmcontent'>".$logs[$i]->getEndTimeAsString()." Uhr</td>";
			echo "<td class='lfmcontent'>".$logs[$i]->getDurationAsString()."</td>";
			echo "</tr>";
			
			$total += $logs[$i]->getDuration();
		}
	  echo "<tr class='lfmcontent'>";
	  echo "<td class='lfmcontent' colspan=3>Gesamt</td>";
	  echo "<td class='lfmcontent'>".gmdate("H.i.s", $total)."</td>";
	  echo "</tr>";
		?>
	</table>	
		
		
	</body>
</html>