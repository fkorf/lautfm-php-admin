<!-- Beispiel: Statik fuer einzelne Station -->
<?php

include("config.php");
include("lautfmadmin.php");

$lfm = new LautfmAdmin();
$lfm->token = $token;
$stats = $lfm->getStatistics($station);
if(!is_null($lastLautError)) {
	exit("Fehler: ".$lastLautError);
}	
?>
<html>
	<head>
		<title>Statistik</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>

		<table class='lfmadmin'>
		  <tr class='lfmtitle'>
				<td colspan=2 style="padding:5pt">Statistik</td>
		  </tr>
			<tr class='lfmcontent'>
				<td class='lfmlabel'>H&ouml;rer</td>
				<td class='lfmcontent lfmright'><?php echo $stats->listeners ?></td>
			</tr>
			<tr class='lfmcontent'>
				<td class='lfmlabel'>Rang</td>
				<td class='lfmcontent lfmright'><?php echo $stats->position ?></td>
			</tr>
			<tr class='lfmcontent'>
				<td class='lfmlabel'>Std heute</td>
				<td class='lfmcontent lfmright'><?php echo $stats->listeningHours[0] ?></td>
			</tr>
			<tr class='lfmcontent'>
				<td class='lfmlabel'>Std gestern</td>
				<td class='lfmcontent lfmright'><?php echo $stats->listeningHours[1] ?></td>
			</tr>
		</table>
		
  </body>
</html>