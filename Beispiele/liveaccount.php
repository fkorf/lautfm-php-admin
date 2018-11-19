<!-- Beispiel: Ausgabe der Live-Statistik -->
<?php

include("config.php");
include("lautfmadmin.php");

$reset = $_GET["pwreset"];

// API-Call
$lfm = new LautfmAdmin();
$lfm->token = $token;

if($reset == 1) {
	$lfm->resetLivePassword($station);
	if(!is_null($lastLautError)) {
	  exit("Fehler: ".$lastLautError);
	}	
}


$account = $lfm->getLiveAccount($station);
if(!is_null($lastLautError)) {
  exit("Fehler: ".$lastLautError);
}	

?>

<html>
	<head>
		<title>Live - Zugangsdaten</title>
		<link rel="stylesheet" href="lautfmadmin.css">
	</head>
	<body>
		
	<form>
		
	<table class='lfmadmin'>
	  <tr class='lfmtitle'>
			<td colspan=4 style="padding:5pt" style='vertical-align:middle'>
				Zugangsdaten
			</td>
	  </tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Protokoll</td>
			<td class='lfmcontent'><? echo $account->protocol; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Server</td>
			<td class='lfmcontent'><? echo $account->server; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Mountpoint</td>
			<td class='lfmcontent'><? echo $account->mountpoint; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>URL</td>
			<td class='lfmcontent'><? echo $account->url; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Bitrate</td>
			<td class='lfmcontent'><? echo $account->bitrate; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Format</td>
			<td class='lfmcontent'><? echo $account->format; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>User</td>
			<td class='lfmcontent'><? echo $account->user; ?></td>
		</tr>
		<tr class='lfmcontent'>
			<td class='lfmlabel'>Passwort</td>
			<td class='lfmcontent'>
				<? echo $account->password; ?>
				<form method="get">
					<input type="hidden" name="pwreset" value="1">
					&nbsp;
					<input type="submit" value="Neu erzeugen">
				</form>
			</td>
		</tr>
	</table>	
  </form>		

	</body>
</html>