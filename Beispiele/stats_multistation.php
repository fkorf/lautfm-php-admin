<!-- Beispiel: Statistik fuer mehrere Stationen -->
<?

include("lautfmadmin.php");

// --- Konfiguration ---
// Token anfordern: https://new.radioadmin.laut.fm/login?callback_url=LautfmPhpLib
$token = "DEIN TOKEN";
$stations = array("station1", "station2", "station3");
$stationLabels = array("Name 1", "Name 2", "Name 3");
// --- Ende Konfiguration ---

$lfm = new LautfmAdmin();
$lfm->token = $token;

for($i = 0; $i < count($stations); $i++) {
  $stats[$i] = $lfm->getStatistics($stations[$i]);
}
?>

<style>
	table {
		font-family: arial, tahoma, helvetica, geneva, sans-serif; 
		font-size:10pt;		
	}
	td.head {
		font-weight:bold;
	}
	td.content {
		text-align:right;
	}
</style>

<table cellpadding="4">
	<tr>
		<td></td>
		<?
		for($i = 0; $i < count($stations); $i++) {
			echo "<td class='head'>";
			echo $stationLabels[$i];
			echo "</td>";
		}
		?>
	</tr>
	<tr>
		<td class='head'>H&ouml;rer</td>
		<?
		for($i = 0; $i < count($stations); $i++) {
			echo "<td class='content'>";
			echo $stats[$i]->listeners;
			echo "</td>";
		}
		?>
	</tr>
	<tr>
		<td class='head'>Rang</td>
		<?
		for($i = 0; $i < count($stations); $i++) {
			echo "<td class='content'>";
			echo $stats[$i]->position;
			echo "</td>";
		}
		?>
	</tr>
	<tr>
		<td class='head'>Std heute</td>
		<?
		for($i = 0; $i < count($stations); $i++) {
			echo "<td class='content'>";
			echo $stats[$i]->listeningHours[0];
			echo "</td>";
		}
		?>
	</tr>
	<tr>
		<td class='head'>Std gestern</td>
		<?
		for($i = 0; $i < count($stations); $i++) {
			echo "<td class='content'>";
			echo $stats[$i]->listeningHours[1];
			echo "</td>";
		}
		?>
	</tr>
</table>

