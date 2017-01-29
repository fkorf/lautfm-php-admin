<!-- Beispiel: Ausgabe der Live-Statistik -->
<?

include("config.php");
include("lautfmadmin.php");

$defaultTimespan = 1; // Standard: 0) Alle, 1) Aktuelle Woche, 2) Letzte Wcohe
$displayTimespanSelector = 1; // Anzeige der Zeitraumauswahl - 0) nein, 1) ja

// Bestimmung des Zeitpunkts des letzten Montags, 0:00 Uhr
function getStartOfWeek() {
  $daysAfterMonday = date('w') - 1;
  if($daysAfterMonday == -1) $daysAfterMonday = 6;
  $mon =  time() - ($daysAfterMonday * 60 * 60 * 24) - date('G') * 60 * 60 - date('i') * 60;
  return $mon;
}

// Festlegen des Start-/Endzeitpunkts fuer die Filterung
$minTime = 0;
$maxTime = time();

$timespan = isset($_GET['zeitraum']) ? $_GET['zeitraum'] : $defaultTimespan;
if($timespan > 0) {
	$weeks = ($timespan - 1) * 60 * 60 * 24 * 7; 
	$minTime = getStartOfWeek() - $weeks;
	$maxTime = $minTime + 60 * 60 * 24 * 7;
}
$selected = array();
$selected[$timespan]="selected";

// API-Call
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
		
	<form>
	<table class='lfmadmin'>
	  <tr class='lfmtitle'>
			<td colspan=4 style="padding:5pt" style='vertical-align:middle'>
				Live-Sessions
				<?
				if($displayTimespanSelector) {
					?>
					<select name='zeitraum' size=1 onchange="this.form.submit();" style='float:right'>
						<option value='0' <? echo $selected[0] ?>>&nbsp;Alle&nbsp;</option>
						<option value='1' <? echo $selected[1] ?>>&nbsp;Aktuelle Woche&nbsp;</option>
						<option value='2' <? echo $selected[2] ?>>&nbsp;Letzte Woche&nbsp;</option>
					</select>
				  <?
 			  }
				?>
			</td>
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
			if($logs[$i]->end >= $minTime && $logs[$i]->end <= $maxTime) {
				echo "<tr class='lfmcontent'>";
				echo "<td class='lfmcontent'>".$logs[$i]->getDayAsString()."</td>";
				echo "<td class='lfmcontent'>".$logs[$i]->getStartTimeAsString()." Uhr</td>";
				echo "<td class='lfmcontent'>".$logs[$i]->getEndTimeAsString()." Uhr</td>";
				echo "<td class='lfmcontent'>".$logs[$i]->getDurationAsString()."</td>";
				echo "</tr>";
				$total += $logs[$i]->getDuration();
  		}
			
		}
	  echo "<tr class='lfmcontent'>";
	  echo "<td class='lfmcontent' colspan=3>Gesamt</td>";
	  echo "<td class='lfmcontent'>".gmdate("H.i.s", $total)."</td>";
	  echo "</tr>";
		?>
	</table>	
  </form>		
		
	</body>
</html>
