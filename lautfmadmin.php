<?
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$lastLautError = null;

function stripHttpHeader($response) {
	$headerEnd = strpos($response, "\r\n\r\n");
	if($headerEnd) {
	  return substr($response, $headerEnd + 4);
	}
	else {
		return $response;
	}
}

function laut_checkError($response) {
	global $lastLautError;
  if(preg_match("/HTTP.*?(\d{3})/", $response, $match)) {
  	if($match[1] >= 400) {
  		$content = stripHttpHeader($response);
  		if(strpos($content, "{") == 0) {
  			$errResponse = json_decode($content, true);
  			$values = array_values($errResponse['_errors:']);
  			$lastLautError = $match[1]." ".$values[0];
  		}
  		else {
  			$lastLautError = $match[1]." ".strip_tags($content);
    	}
    	return true;
  	}
	}
	return false;
}

function laut_get($origin, $token, $path) {
	global $lastLautError;
	$response = "";
	$errno = 0;
	$errstr = null;
	$lastLautError = null;
	if ( $fp = fsockopen('ssl://api.radioadmin.laut.fm', 443, $errno, $errstr, 30) ) {
	
	    $msg  = "GET $path HTTP/1.1\r\n";
	    $msg .= "Host: api.radioadmin.laut.fm\r\n";
	    $msg .= "Authorization: Bearer $token\r\n";
	    $msg .= "Origin: $origin \r\n";
	    $msg .= "Connection: close\r\n\r\n";
	    if ( fwrite($fp, $msg) ) {
	        while ( !feof($fp) ) {
	            $response .= fgets($fp, 1024);
	        }
	    }
	    fclose($fp);
			if(laut_checkError($response)) {
				return "";
			}
			
	    // response enthaelt kompletten http-Header - wir wollen nur den eigentlichen Content
	    return stripHttpHeader($response);
	} 
	else {
		$lastLautError = $errstr;
	}
	return $response;
}

function laut_patch($origin, $token, $path, $content) {
	global $lastLautError;
	$response = "";
	$errno = 0;
	$errstr = null;
	$lastLautError = null;
	if ( $fp = fsockopen('ssl://api.radioadmin.laut.fm', 443, $errno, $errstr, 30) ) {
	
	    $contentLength = strlen($content) + 2;
	
	    $msg  = "PATCH $path HTTP/1.1\r\n";
	    $msg .= "Host: api.radioadmin.laut.fm\r\n";
	    $msg .= "Authorization: Bearer $token\r\n";
	    $msg .= "Origin: $origin \r\n";
	    $msg .= "Content-Type: application/json; charset=UTF-8\r\n";
	    $msg .= "Content-Length: ".$contentLength."\r\n";
	    $msg .= "Connection: close\r\n\r\n";
	    
	    $msg .= "\r\n";
	    $msg .= $content;
	    $msg .= "\r\n";
	    	    
	    if ( fwrite($fp, $msg) ) {
	        while ( !feof($fp) ) {
	            $response .= fgets($fp, 1024);
	        }
	    }
	    fclose($fp);
	    
	    // response enthaelt kompletten http-Header - wir wollen nur den eigentlichen Content
	    return stripHttpHeader($response);
	} 
	else {
		$lastLautError = $errstr;
	}
	return $response;
}


class LautfmAdmin {
	
	public $origin = "LautfmPhpLib";
	public $token = ""; // muss gesetzt werden
	
	public $stationIds = array();
	
	// Liefert Namen aller Stationen auf die Zugriff besteht +
	// Initialisiert $this->stationIds
	function getStationNames() {
		$names = array();
		$raw = laut_get($this->origin, $this->token, "/stations");
		if($raw != "") {
			$response = json_decode($raw);
			for($s = 0; $s < count($response->{'stations'}); $s++) {
				$id = $response->{'stations'}[$s]->{'id'};
				$station = $response->{'stations'}[$s]->{'name'};
				$names[$s] = $station;
				// echo "$station = $id<br>";
				$this->stationIds[$station] = $id;
			}		
  	}
		return $names;
	}
	
	// Liefert Id einer gegebenen Station
	function getStationId($stationName) {
		if(count($this->stationIds) == 0) {
			$this->getStationNames();
		}
		return $this->stationIds[$stationName];
	}
	
	function getStationPath($stationName, $path) {
		$id = $this->getStationId($stationName);
		return "/stations/".$id.$path;
	}
	
	// Statstik
	// Return: Statistics
	function getStatistics($stationName) {
		$path = $this->getStationPath($stationName, "/stats");
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		
		
		$stats = new Statistics();
		$stats->listeners = $response->{'listeners_now'};
		$stats->position = $response->{'position_now'};
	  				
		for($i = 0; $i < 5; $i++) {
			$ts = time() - $i * 24 * 60 * 60;
			$key = date("Y-m-d", $ts);
			$stats->switchOns[$i] = $response->{'switchons_log'}->{$key};
			$stats->listeningHours[$i] = $response->{'tlh_log'}->{$key};
		}
		
		return $stats;
	}

  // Live-Statistik
  // Return: LiveSession[]
	function getLiveLog($stationName) {
		$logs = array();
		$path = $this->getStationPath($stationName, "/live/log");
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);

		for($i = 0; $i < count($response); $i++) {
			$session = new LiveSession();
			$session->start =  strtotime($response[$i]->{'started_at'});
			$session->end =  strtotime($response[$i]->{'ended_at'});
			$session->ip =  strtotime($response[$i]->{'ip'});
			$duration = $session->end - $session->start;
			$logs[$i] = $session;
		}
		
		return $logs;
	}
	
	function getCurrentPlaylistId($stationName) {
		$raw = file_get_contents("http://api.laut.fm/station/".$stationName);
		$response = json_decode($raw);
		return $response->{'current_playlist'}->{'id'};
	}
	
	// Alle Playlists, ohne Tracks
	function getPlaylists($stationName) {
		$path = $this->getStationPath($stationName, "/playlists");
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		
		$result = array();
		
		for($i = 0; $i < count($response->{'playlists'}); $i++) {
			$playlist = new Playlist();
			$playlist->id = $response->{'playlists'}[$i]->{'id'};
			$playlist->name = $response->{'playlists'}[$i]->{'title'};
			$playlist->description = $response->{'playlists'}[$i]->{'description'};
			$playlist->color = $response->{'playlists'}[$i]->{'color'};
			$playlist->shuffled = $response->{'playlists'}[$i]->{'shuffled'};
			$playlist->size = $response->{'playlists'}[$i]->{'size'};
			$playlist->duration = $response->{'playlists'}[$i]->{'duration'};
			$playlist->createdAt = strtotime($response->{'playlists'}[$i]->{'created_at'});
			$playlist->updatedAt = strtotime($response->{'playlists'}[$i]->{'updated_at'});
			array_push($result, $playlist);
		}
		
		return $result;
	}
	
	function getPlaylist($stationName, $playlistId, $includeTracks) {
		$path = $this->getStationPath($stationName, "/playlists/".$playlistId);
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
				
		$playlist = new Playlist();
		$playlist->id = $response->{'id'};
		$playlist->name = $response->{'title'};
		$playlist->description = $response->{'description'};
		$playlist->color = $response->{'color'};
		$playlist->shuffled = $response->{'shuffled'};
		$playlist->size = $response->{'size'};
		$playlist->duration = $response->{'duration'};
		$playlist->createdAt = strtotime($response->{'created_at'});
		$playlist->updatedAt = strtotime($response->{'updated_at'});
		
		if($includeTracks > 0) {
			$tracksById = $this->getPlaylistTracks($stationName, $playlistId);
			$playlist->tracks = array();
			for($i = 0; $i < count($response->{'entries'}); $i++) {
				$trackId = $response->{'entries'}[$i]->{'track_id'};
				$playlist->tracks[$i] = $tracksById[$trackId];
			}
		}
		
		return $playlist;
	}

	function getPlaylistTracks($stationName, $playlistId) {
		$path = $this->getStationPath($stationName, "/playlists/".$playlistId."/tracks");
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		
		$tracks = array();
		
		for($i = 0; $i < count($response->{'tracks'}); $i++) {
			$track = new Track();
			$track->id =  $response->{'tracks'}[$i]->{'id'};
			$track->title =  $response->{'tracks'}[$i]->{'title'};
			$track->artist =  $response->{'tracks'}[$i]->{'artist'};
			$track->album =  $response->{'tracks'}[$i]->{'album'};
			$track->year =  $response->{'tracks'}[$i]->{'release_year'};
			$track->duration =  $response->{'tracks'}[$i]->{'duration'};
			$track->type =  $response->{'tracks'}[$i]->{'type'};
			$track->isPrivate =  $response->{'tracks'}[$i]->{'private'};
			$track->isOwn =  $response->{'tracks'}[$i]->{'own'};
			$track->createdAt =  strtotime($response->{'tracks'}[$i]->{'created_at'});
			$track->updatedAt =  strtotime($response->{'tracks'}[$i]->{'updated_at'});
			$tracks[$track->id] = $track;
		}
		
		return $tracks;
	}


	// Aktualisiert die Tracks einer Playlist
	// Returns: Track IDs wie sie tatsaechlich gesetzt wurden (normalerweise identisch mit den ids der uebergebenen Tracks)
	function setPlaylistTracks($stationName, $playlistId, $tracks) {
		$trackIds = array();
		for($i = 0; $i < count($tracks); $i++) {
			$trackIds[$i] = $tracks[$i]->id;
		}
		return $this->setPlaylistTrackIds($stationName, $playlistId, $trackIds);
	}
	
	// Aktualisiert die Tracks einer Playlist
	// Returns: Track IDs wie sie tatsaechlich gesetzt wurden (normalerweise identisch mit den uebergebenen IDs)
	function setPlaylistTrackIds($stationName, $playlistId, $trackIds) {
		$content = "{\"entries\":[";
		for($i = 0; $i < count($trackIds); $i++) {
			if($i > 0) $content.= ",";
			$content.=$trackIds[$i];
		}
		$content .="]}";
						
		$path = $this->getStationPath($stationName, "/playlists/".$playlistId);
		$raw = laut_patch($this->origin, $this->token, $path, $content);
		
		$response = json_decode($raw);

		$newTrackIds = array();
		for($i = 0; $i < count($response->{'entries'}); $i++) {
			$newTrackIds[$i] =  $response->{'entries'}[$i]->{'track_id'};
		}
		
		return $newTrackIds;
	}
	
	function getTrackStatistics($stationName, $days) {
		if($days > 7) {
			$days = 7;
		}
		$path = $this->getStationPath($stationName, "/tracks/stats?days=".$days);
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		
		$entries = array();
		for($i = 0; $i < count($response); $i++) {
			$entry = new TrackStatisticsEntry();
			
			if(!isset($response[$i])) {
				continue;
			}

			$entry->id =  $response[$i]->{'id'};
			$entry->title =  $response[$i]->{'title'};
			$entry->artist =  $response[$i]->{'artist'}->{'name'};
			$entry->album =  $response[$i]->{'album'};
			$entry->year =  $response[$i]->{'release_year'};
			$entry->duration =  $response[$i]->{'length'};
			$entry->type =  $response[$i]->{'type'};

			$track->createdAt =  strtotime($response->{'created_at'});
			$entry->start =  strtotime($response[$i]->{'started_at'});
			$entry->end =  strtotime($response[$i]->{'ends_at'});
			$entry->listeners =  $response[$i]->{'listeners'};
			
			$entries[$i] = $entry;
			
		}
		
		return $entries;
	}
	
	// Sendeplan (ohne Events)
	function getSchedule($stationName) {
		$path = $this->getStationPath($stationName, "/schedule");
		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		
				
		$entries = array();
		for($i = 0; $i < count($response->{'entries'}); $i++) {
			$entry = new ScheduleEntry();
			$entry->playlistId = $response->{'entries'}[$i]->{'playlist_id'};
			$entry->slot = $response->{'entries'}[$i]->{'slot'};
			$entry->duration = $response->{'entries'}[$i]->{'duration'};
			$entries[$i] = $entry;
		}
		
		return $entries;
	}
	
	// Track Ids fuer ein Tag
	function getTag($stationName, $tag) {
		$tagEnc = urlencode($tag);
		$tagEnc = str_replace("+", "%20", $tagEnc);
		$path = $this->getStationPath($stationName, "/tracks/tags/".$tagEnc);

		$raw = laut_get($this->origin, $this->token, $path);
		$response = json_decode($raw);
		return $response;
	}
	
}

class Playlist {
	public $id;
	public $name;
	public $description;
	public $color;
	public $shuffled;
	public $size;
	public $duration;
	public $createdAt;
	public $updatedAt;	
	
	public $tracks;
	
	function getCreatedAtAsString() {
		return date("d.m.Y G:i", $this->createdAt);
	}
	
	function getUpdateddAtAsString() {
		return date("d.m.Y G:i", $this->updatedAt);
	}

}

class Track {
	public $id;
	public $title;
	public $artist;
	public $album;
	public $year;
	public $duration;
	public $type;
	public $isPrivate;
	public $isOwn;

	public $createdAt;
	public $updatedAt;	

	function getCreatedAtAsString() {
		return date("d.m.Y G:i", $this->createdAt);
	}
	
	function getUpdateddAtAsString() {
		return date("d.m.Y G:i", $this->updatedAt);
	}
	
}

class TrackStatisticsEntry {
	public $id;
	public $title;
	public $artist;
	public $album;
	public $year;
	public $duration;
	public $type;

	public $createdAt;
	public $updatedAt;	

	public $start;
	public $end;
	public $listeners;

	function getCreatedAtAsString() {
		return date("d.m.Y G:i", $this->createdAt);
	}
	
	function getUpdateddAtAsString() {
		return date("d.m.Y G:i", $this->updatedAt);
	}

	function getStartDateAsString() {
		return date("d.m.Y G:i", $this->start);
	}

	function getEndDateAsString() {
		return date("d.m.Y G:i", $this->end);
	}
	
  function getDayAsString() {
		return date("d.m.Y", $this->start);
	}

	function getStartTimeAsString() {
		return date("G:i", $this->start);
	}

	function getEndTimeAsString() {
		return date("G:i", $this->end);
	}

	
	function getDurationAsString() {
		return gmdate("H:i:s", $this->getDuration());
	}
	
}


class Statistics {
	public $listeners;
	public $position;
	public $switchOns = array();
	public $listeningHours = array();
}

class LiveSession {
	public $start;
	public $end;
	public $ip;
	
	function getDuration() {
		return $this->end - $this->start;
	}
	
	function getStartDateAsString() {
		return date("d.m.Y G:i", $this->start);
	}

	function getEndDateAsString() {
		return date("d.m.Y G:i", $this->end);
	}
	
  function getDayAsString() {
		return date("d.m.Y", $this->start);
	}

	function getStartTimeAsString() {
		return date("G:i", $this->start);
	}

	function getEndTimeAsString() {
		return date("G:i", $this->end);
	}

	
	function getDurationAsString() {
		return gmdate("H:i:s", $this->getDuration());
	}
}

class ScheduleEntry {
	public $playlistId;
	public $slot;
	public $duration;

  // Wochentag: Montag = 0, Dienstag = 1 etc	
	function getWeekday() {
		return $this->slot / 24;
	}

  // Wochentag, gekuerzt auf 3 Buchstaben - entpricht date('D')
	function getWeekdayAsShortString() {
		$day = floor($this->slot / 24);
		switch($day) {
			case 0:
			  return "Mon";
			case 1:
			  return "Tue";
			case 2:
			  return "Wed";
			case 3:
			  return "Thu";
			case 4:
			  return "Fri";
			case 5:
			  return "Sat";
			case 6:
			  return "Sun";
		}
	}

  // Startzeit (0-24)	
	function getStartHour() {
		return $this->slot % 24;
	}
	
}

?>