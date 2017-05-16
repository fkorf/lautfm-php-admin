<?php

// Gewichtungen fuer Tags
class Weight {
	const LOW_MAX = -3;
	const LOW_MEDIUM = -2;
	const LOW_MIN = -1;
	const NORMAL = 0;
	const HIGH_MIN = 1;
	const HIGH_MEDIUM = 2;
	const HIGH_MAX = 3;
}

// Strategie fuer Wortbeitraege
class WordDistribution {
	const RANDOM = 0;  				// Beitraege shuffeln
	const PROTECT = 1; 				// Beitraege auf urspruenglicher Position belassen
	const LINK_NEXT = 2;			// Beitraege an nachfolgenden Titel koppeln
	const LINK_PREVIOUS = 3;  // Beitraege an vorhergehenden Titel koppeln
}

// Default- oder Playlisteinstellungen
class ShuffleSettings {
	public $shuffleJingles = 0;
	public $jingleInterval = 0;
	public $protectFirstJingle = 0;
	public $wordDistributionStrategy = WordDistribution::RANDOM;
	
	public $maxTracksPerArtist = 0; // 0 = Abhaengig von Laenge
	
	public $weights = array(); // Schluessel = Tagname, Wert = Weight
	
	function copy() {
		$c = new ShuffleSettings();
		$c->shuffleJingles = $this->shuffleJingles;
		$c->jingleInterval = $this->jingleInterval;
		$c->protectFirstJingle = $this->protectFirstJingle;
		$c->wordDistributionStrategy = $this->wordDistributionStrategy;
		$c->maxTracksPerArtist = $this->maxTracksPerArtist;
		$c->weights = $this->weights;
		return $c;
	}
}

// Normalisiert einen Artist
// - Kleinbuchstaben
// - Abeschneiden nach definierten Trennern wie " feat"
class ArtistNormalizer {
	public $separators = array(" feat");
	
	function normalize($artist) {
		$artist = strtolower($artist);
		for($i = 0; $i < count($this->separators); $i++) {
			$pos = strpos($artist, $this->separators[$i]);
			if($pos > 0) {
				return trim(substr($artist, 0, $pos));
			}
		}
		return $artist;
	}
}

// Interface fuer Trackfilterung
interface TrackFilter {
	// Track-IDs die dieser Filter akzeptiert
	public function getTrackIds($client, $stationName);
}

// Tracks einer Playlist
class PlaylistTrackFilter implements TrackFilter {
	  public $playlistName;

	  function __construct($playlistName) { 
	  	$this->playlistName = $playlistName;
	  }
	
		public function getTrackIds($client, $stationName) {
			$trackIds = array();
			
			$playlist = $client->getPlaylistByName($stationName, $this->playlistName);
			if(!is_null($playlist)) {
				// Wir gehen direkt runter auf die Radioadmin-API - ist effizienter
				$path = $client->getStationPath($stationName, "/playlists/".$playlist->id);
				$raw = laut_get($client->origin, $client->token, $path);
				$response = json_decode($raw);
				
				for($i = 0; $i < count($response->{'entries'}); $i++) {
					array_push($trackIds, $response->{'entries'}[$i]->{'track_id'});
				}
			}
			return $trackIds;
		}
}

// Filter: Gespielte Tracks
class PlayedTracksFilter implements TrackFilter {
	public $days = 0; 			// Zahl der Tage fuer die die Trackstatistik abgerufen wird
	public $minPlays = 1;		// Mindestanzahl der Plays fuer einen Track
	public $minHour = 0;		// Startzeit (min)
	public $maxHour = 23;   // Startzeit (max)

	function __construct($days, $minHour = 0, $maxHour = 23, $minPlays = 1) { 
	  $this->days = $days;
	  $this->minHour = $minHour;
	  $this->maxHour = $maxHour;
	  $this->minPlays = $minPlays;
	}
	
	public function getTrackIds($client, $stationName) {
		$trackIds = array();
		$cache = TrackStatisticsCache::Instance();
		$entries = $cache->getTrackStatistics($client, $stationName, $this->days);
		$trackCounts = array();
		for($i = 0; $i < count($entries); $i++) {
			if($entries[$i]->id > 0) {
				$hour = date("G", $entries[$i]->start);
				if($hour >= $this->minHour && $hour <= $this->maxHour) {
					if($this->minPlays < 2) {
						array_push($trackIds, $entries[$i]->id);
					}
					else {
						$trackCounts[$entries[$i]->id]++;
					}
				}
			}
		}
		if($this->minPlays > 1) {
			foreach ($trackCounts as $trackId => $count) {
				if($count >= $this->minPlays) {
					array_push($trackIds, $trackId);
				}
			}
		}
		return $trackIds;
	}

}

class PlaylistShuffler {
	public $defaultSettings;
	public $artistNormalizer;
	
	public $trackFilters = array(); // key = name, value = TrackFilter
	
	private $client; 
	private $station;
 
  private $playlistSettings = array();
  private $tags = array();

  // Konstructor: LautfmAdmin, Stationsname
  function __construct($client, $station) {
  	$this->defaultSettings = new ShuffleSettings();
  	$this->artistNormalizer = new ArtistNormalizer();
  	$this->client = $client;
  	$this->station = $station;
  }
  
  // public
  
  function registerPlaylistSettings($playlistName, $settings) {
  	$this->playlistSettings[$playlistName] = $settings;
  }
	
	function shuffle($playlist, $targetLength = 0) {
		$settings = $this->defaultSettings;
		if(array_key_exists($playlist->name, $this->playlistSettings)) {
			$settings = $this->playlistSettings[$playlist->name];
		}
		
		$job = $this->initializeJob($playlist, $settings);

    $targetLengthSec = $playlist->duration;
		if($targetLength > 0 && $targetLength * 60 < $playlist->duration) {
			$targetLengthMin = $targetLength * 60;
			$targetLengthSec = $targetLengthMin * 60;
			$this->preselectTracks($job, $settings, $targetLengthMin);
		}
		else {
			$job->useAllTracks();
		}
		
		$numSegments = $job->getMaxArtistTracksToUse() * 2;
		$segements = array();
		for($i = 0; $i < $numSegments; $i++) {
			$segments[$i] = new Segment();
		}

		for($i = 0; $i < count($job->artists); $i++) {
			$numArtistTracks = $job->artists[$i]->getNumTracksToUse();
			if($numArtistTracks == 0) {
				continue;
			}
			$artistSegments = floor($numSegments / $numArtistTracks);
			
      // find least filled segment that can act as first segment for this artist
      $currentSegment = 0;
      $minLength = $segments[0]->length;
			for($s = 0; $s < $artistSegments; $s++) {
				if($segments[$s]->length < $minLength) {
					$currentSegment = $s;
					$minLength = $segments[$s]->length;
				}
			}
			
			// assign tracks of artist to segment
			$tracksToUse = $job->artists[$i]->getTracksToUse();
			for($t = 0; $t < count($tracksToUse); $t++) {
				$segments[$currentSegment]->add($tracksToUse[$t]->track);
				$currentSegment = $currentSegment + $artistSegments;
			}
		}

    // build final playlist
		$tracks = array();
		for($s = 0; $s < count($segments); $s++) {
			$segmentTracks = $segments[$s]->tracks;
			$segmentTracks = shuffleArray($segmentTracks); // avoid having artists in same order within segments
			for($t = 0; $t < count($segmentTracks); $t++) {
				// TODO check for coupled tracks / successor coupling
				array_push($tracks, $segmentTracks[$t]);
				// TODO check for coupled tracks / predecessor coupling
			}
		}

		$tracks = $this->insertJingles($job, $tracks, $settings, $targetLengthSec);
		$tracks = $this->appendUnused($job, $tracks);
		$tracks = $this->insertLinkedTracks($job, $tracks,$settings);
		$tracks = $this->insertProtectedTracks($job, $tracks);
				
		return $tracks;
	}
	
	// private
	
	private function initializeJob($playlist, $settings) {
		$artists = array();
		$artist = null;
		$job = new ShuffleJob();
		
		$knownJingles = array();
				
		for($i = 0; $i < count($playlist->tracks); $i++) {
			$track = $playlist->tracks[$i];
			
			if($track->type == "jingle") {
			  $key = "J".$track->id;
				if($i == 0 && $settings->protectFirstJingle == 1) {
					$job->startJingle = $track;
					$knownJingles[$key] = 1;
				}
				else {
					if(!array_key_exists($key, $knownJingles)) {
						array_push($job->jingles, $track);
						$knownJingles[$key] = 1;
					}
				}
			}
			else if($track->type == "moderation" && $settings->wordDistributionStrategy != WordDistribution::RANDOM) {
				if($settings->wordDistributionStrategy == WordDistribution::PROTECT) {
					$job->protectedTracks[$i] = $track;
				}
				else if($settings->wordDistributionStrategy == WordDistribution::LINK_NEXT && $i < count($playlist->tracks) - 1) {
					$nextTrack = $playlist->tracks[$i + 1];
					$job->linkedTracks[$nextTrack->id] = $track;				
				}
				else if($settings->wordDistributionStrategy == WordDistribution::LINK_PREVIOUS && $i > 0) {
					$prevTrack = $playlist->tracks[$i - 1];
					$job->linkedTracks[$prevTrack->id] = $track;				
				}
			}
			else {
				$artistName = $this->artistNormalizer->normalize($track->artist);
			  if(array_key_exists($artistName, $artists)) {
			  	$artist = $artists[$artistName];
			  }
			  else {
			  	$artist = new ShuffleArtist();
			  	$artists[$artistName] = $artist;
			  	array_push($job->artists, $artist);
			  }
			  $candidate = new ShuffleCandidate();
			  $candidate->track = $track;
			  $candidate->score = rand(100, 600);
			  array_push($artist->tracks, $candidate);
			}
		}
		
		if(count($job->jingles) > 0 && $settings->shuffleJingles == 1) {
			$job->jingles = shuffleArray($job->jingles);
		}
		
		$this->applyTagWeights($job, $settings);
		
		// sort artist track lists
		for($i = 0; $i < count($job->artists); $i++) {
			usort($job->artists[$i]->tracks, "cmpScore");
			$job->artists[$i]->score = $job->artists[$i]->tracks[0]->score;
		}
		usort($job->artists, "cmpScore");
				
		return $job;
	}
	
	private function applyTagWeights($job, $settings) {
		// initialize associative array with track weights
		$weights = array();
		foreach ($settings->weights as $tagName => $weight) {
			$trackIds = $this->getTag($tagName);
			for($i = 0; $i < count($trackIds); $i++) {
				if(array_key_exists($trackIds[$i], $weights)) {
					$old = $weights[$trackIds[$i]];
					if(($weight > 0 && $old > 0 && $weight > $old) || ($weight < 0 && $old < 0 && $weight < $old)) {
						$weights[$trackIds[$i]] = $weight;
					}
					else if(($old > 0 && $weight < 0) || ($weight > 0 && $old < 0)) {
						$weights[$trackIds[$i]] = $weight + $old;
					}
				}
				else {
					$weights[$trackIds[$i]] = $weight;
				}
			}
		}
		
		// increase / decrease track scores
		for($i = 0; $i < count($job->artists); $i++) {
			for($j = 0; $j < count($job->artists[$i]->tracks); $j++) {
				$candidate = $job->artists[$i]->tracks[$j];
				if(array_key_exists($candidate->track->id, $weights)) {
					$weight = $weights[$candidate->track->id];
					if($weight > 0) {
		        $p = (4 - $weight) / 4;
		        $candidate->score = $candidate->score * $p;
					}
					else {
						$weight = abs($weight);
        		$p = 1 + $weight / 4;
		        $candidate->score = $candidate->score * $p;
					}
				}
			}
		}
	}
	
	private function preselectTracks($job, $settings, $targetLengthMin) {
		$maxTracksPerArtist = floor($targetLengthMin / 60);
		if($settings->maxTracksPerArtist > 0 && $settings->maxTracksPerArtist < $maxTracksPerArtist) {
			$maxTracksPerArtist = $settings->maxTracksPerArtist;
		}

		$length = 0;
		
		$candidates = array();
		for($i = 0; $i < count($job->artists); $i++) {
			for($j = 0; $j < $maxTracksPerArtist && $j < count($job->artists[$i]->tracks); $j++) {
				array_push($candidates, $job->artists[$i]->tracks[$j]);
			}
		}
		usort($candidates, "cmpScore");
		$i = 0;
		while($i < count($candidates) && $length < $targetLengthMin * 60) {
			$candidates[$i]->use = 1;
			$length += $candidates[$i]->track->duration;
			$i++;
		}
		
	}
	
	private function insertProtectedTracks($job, $tracks) {
		if(count($job->protectedTracks) == 0) {
			return $tracks;
		}
		$newTracks = array();
		for($i = 0; $i < count($tracks); $i++) {
			$key = count($newTracks);
			if(array_key_exists($key, $job->protectedTracks)) {
				array_push($newTracks, $job->protectedTracks[$key]);
			}
			array_push($newTracks, $tracks[$i]);
		}
		
		return $newTracks;
	}
	
	private function insertLinkedTracks($job, $tracks, $settings) {
		if(count($job->linkedTracks) == 0) {
			return $tracks;
		}
		$newTracks = array();
		for($i = 0; $i < count($tracks); $i++) {
			$key = $tracks[$i]->id;
			if($settings->wordDistributionStrategy == WordDistribution::LINK_NEXT && array_key_exists($key, $job->linkedTracks)) {
				array_push($newTracks, $job->linkedTracks[$key]);
			}
			array_push($newTracks, $tracks[$i]);
			if($settings->wordDistributionStrategy == WordDistribution::LINK_PREVIOUS && array_key_exists($key, $job->linkedTracks)) {
				array_push($newTracks, $job->linkedTracks[$key]);
			}
		}
		
		return $newTracks;
	}
	
	private function insertJingles($job, $tracks, $settings, $playlistLength) {
		if(count($job->jingles) == 0) {
			if(isset($job->startJingle)) {
				array_unshift($tracks, $job->startJingle);
			}
			return $tracks;
		}
		
		$timeNextJingle = 0;
		$jingleOffset = 0;
		$jingleInterval = $settings->jingleInterval;
		if($jingleInterval == 0) {
			$numJingles = isset($job->startJingle) ? count($job->jingles) + 1 : count($job->jingles);
			$jingleInterval = floor(($playlistLength / $numJingles) / 60);
		}
		
		
		$newTracks = array();
		
		if(isset($job->startJingle)) {
			array_push($newTracks, $job->startJingle);
			$timeNextJingle = $jingleInterval;
			$jingleOffset = $jingleInterval;
		}
		else {
			$jingleOffset = rand(0, $jingleInterval);
			$timeNextJingle = $jingleOffset;
		}
		
		$jingleIdx = 0;
    $jingleCnt = 0;
    $currentTimeSec = 0;
        
    for($i = 0; $i < count($tracks); $i++) {
    	if($currentTimeSec / 60 >= $timeNextJingle) {
	    	array_push($newTracks, $job->jingles[$jingleIdx]);
	    	$usedJingles[$jingleIdx] = 1;
	      $currentTimeSec += $job->jingles[$jingleIdx]->duration;
	      $jingleIdx = ($jingleIdx + 1) % count($job->jingles);
	      $jingleCnt++;
	      $timeNextJingle = $jingleCnt * $jingleInterval + $jingleOffset;
    	}
    	array_push($newTracks, $tracks[$i]);
      $currentTimeSec += $tracks[$i]->duration;
    }
    
    // echo "$jingleCnt ".count($job->jingles)."\n";
    if($jingleCnt >= count($job->jingles)) {
    	// all jingles used - clear array
    	$job->jingles = array();
    }
    else {
    	// remove used jingles
    	array_splice($job->jingles, 0, $jingleCnt);
    }
		
		return $newTracks;
	}

	private function appendUnused($job, $tracks) {
		
		// append unused tracks
		$offsets = array();
		
		for($i = 0; $i < count($job->artists); $i++) {
			$offsets[$i] = 999;
			for($j = 0; $j < count($job->artists[$i]->tracks) && $offsets[$i] == 999; $j++) {
				if($job->artists[$i]->tracks[$j]->use == 0) {
					$offsets[$i] = $j;
				}
			}
		}
		
		$added = 1;
		while($added > 0) {
			$added = 0;
			for($i = 0; $i < count($job->artists); $i++) {
				if($offsets[$i] < count($job->artists[$i]->tracks)) {
				  array_push($tracks, $job->artists[$i]->tracks[$offsets[$i]]->track);
				  $offsets[$i]++;
					$added++;
				}
			}
		}
		
		// append unused jingles
		for($i = 0; $i < count($job->jingles); $i++) {
			array_push($tracks, $job->jingles[$i]);
		}
		
		return $tracks;
	}
	
	// Get track ids for tag or filter. Uses caching.
	private function getTag($tagName) {
		if(array_key_exists($tagName, $this->tags)) {
			return $this->tags[$tagName];
		}
		
		$trackIds = NULL;
		if(array_key_exists($tagName, $this->trackFilters)) {
			$trackIds = $this->trackFilters[$tagName]->getTrackIds($this->client, $this->station);
		}
		else {
			$trackIds = $this->client->getTag($this->station, $tagName);
		}
		$this->tags[$tagName] = $trackIds;
		return $trackIds;
	}
	
}


// Internal stuff

class ShuffleJob {
	public $artists = array();
	public $jingles = array();
	public $startJingle;
	
	public $protectedTracks = array();
	public $linkedTracks = array();
		
	function getMaxArtistTracksToUse() {
		$max = 0;
		for($i = 0; $i < count($this->artists); $i++) {
			$artistMax = $this->artists[$i]->getNumTracksToUse();
			if($artistMax > $max) $max = $artistMax;
		}
		return $max;
	}
	
	function useAllTracks() {
		for($i = 0; $i < count($this->artists); $i++) {
			for($j = 0; $j < count($this->artists[$i]->tracks); $j++) {
				$this->artists[$i]->tracks[$j]->use = 1;
			}
		}
	}
}

class Segment {
	public $tracks = array(); // as array of Track
	public $length = 0;
	
	function add($track) {
		array_push($this->tracks, $track);
		$this->length += $track->duration;
	}
}

class ShuffleArtist {
	public $tracks = array(); // as array of ShuffleCandiate
	public $score;  // score of best track
	 
  private $numTracksToUse = -1;
  private $tracksToUse = array();
	
	function getTracksToUse() {
		if(count($this->tracksToUse) == 0) {
			for($i = 0; $i < count($this->tracks); $i++) {
				if($this->tracks[$i]->use == 1) {
					array_push($this->tracksToUse, $this->tracks[$i]);
				}
			}
		}
		return $this->tracksToUse;
	}
	
	function getNumTracksToUse() {
		return count($this->getTracksToUse());
	}
}

class ShuffleCandidate {
	public $track;
	public $score;
	public $use = 0; // whether or not to use this track in the start section of the playlist
}

// Simple cache for track statistics
class TrackStatisticsCache {
	  private $entries = array();
	  private $daysRetrieved = array();
	
    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new TrackStatisticsCache();
        }
        return $inst;
    }

    private function __construct()
    {
    }	
    
    function getTrackStatistics($client, $station, $days) {
    	if(!$this->daysRetrieved[$station] || $days > $this->daysRetrieved[$station]) {
    		$this->entries[$station] = $client->getTrackStatistics($station, $days);
    		$this->daysRetrieved[$station] = $days;
    	}
    	return $this->entries[$station];
    }
}

function cmpScore($a, $b) {
  if($a->score == $b->score) return 0;	
	return $a->score < $b->score ? -1 : 1;
}

// for some reason shuffle does not work on arrays of objects - use this as replacement
function shuffleArray($array) {
	$newArray = array();
	$keys = range(0, count($array) - 1);
	shuffle($keys);
	for($i = 0; $i < count($keys); $i++) {
		array_push($newArray, $array[$keys[$i]]);
	}
	return $newArray;
}

?>