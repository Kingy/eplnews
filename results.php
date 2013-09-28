<?php

require 'src/facebook.php';
require 'config.php';
require 'functions.php';

$db = new mysqli($conf['db_hostname'], $conf['db_username'], $conf['db_password'], $conf['db_name']);
$URL = "http://www.premierleague.com/en-gb/matchday/results.html?paramClubId=ALL&paramComp_8=true&paramSeason=2013-2014&view=.dateSeason";

if($db->connect_errno > 0){
    die('Unable to connect to database [' . $db->connect_error . ']');
}

$ch = curl_init();
if($ch) {
	curl_setopt($ch, CURLOPT_URL, $URL);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$content = curl_exec($ch);
	$headers = curl_getinfo($ch);				

	curl_close($ch);

	if ($conf['debug'] == 1) {
		echo "<pre>";
			print_r($headers);
		echo "</pre>";
	}
   
	if($headers['http_code'] == 200) { 
      
		$dom = new DOMDocument();  
		@$dom->loadHTML($content);  
		$tempDom = new DOMDocument();  
              
		$xpath = new DOMXPath($dom); 
		$site = $xpath->query("//div[@widget='fixturelistbroadcasters']");
		foreach ( $site as $item ) {  
			$tempDom->appendChild($tempDom->importNode($item,true));  
		} 
		$tempDom->saveHTML(); 
		$scoresXpath = new DOMXPath($tempDom);
      
		$scoresTable = $scoresXpath->query("table[@class='contentTable']"); 
		
		if ($conf['debug'] == 1) {
			echo "Score container div length: " . $scoresTable->length . "<br /><br />"; 
		}		
      
		$results = array(); 
      
		foreach ($scoresTable as $table) { 
			$newDom = new DOMDocument;  
			$newDom->appendChild($newDom->importNode($table,true));  
			$scoreXpath = new DOMXPath( $newDom );  
                      
			$date = trim($scoreXpath->query("tr/th/text()")->item(0)->nodeValue); 
           
			$resultsTable = $scoreXpath->query("tr[position()>1]");
			
			if ($conf['debug'] == 1) {
				echo "Results found: " . $resultsTable->length . "<br /><br />"; 
			}
           
			foreach ($resultsTable as $result) {
				$rDom = new DOMDocument;
				$rDom->appendChild($rDom->importNode($result,true));  
				$resultXpath = new DOMXPath($rDom);  	
           	           	
				$home = trim($resultXpath->query("td[2]/a/text()")->item(0)->nodeValue);
				$away = trim($resultXpath->query("td[4]/a/text()")->item(0)->nodeValue);
				$score = trim($resultXpath->query("td[3]/a/text()")->item(0)->nodeValue);
				$location = trim($resultXpath->query("td[5]/a/text()")->item(0)->nodeValue);
           	
				$query = "SELECT matchID FROM `matches` WHERE `matchDate`='$date' AND `matchHome`='$home' AND `matchAway`='$away'";
				$result = $db->query($query) or die($db->error.__LINE__);

				if ($result->num_rows == 0) {
					if ($conf['debug'] == 1) {
						echo "Found new result: " . $home . " vs " . $away . "<br /><br />"; 
					}
					$results[] = array(  
                    		'date' => $date,  
							'home' => $home,  
                    		'away' => $away,  
                    		'score' => $score, 
                    		'location' => $location,            
                    );			          	      	 
				}
			}
		}
	
		foreach ($results as $result) {
			
			if ($conf['debug'] == 1) {
				echo "Adding " . $result['home'] . " vs " . $result['away'] . " to database.<br /><br />"; 
			}
			
			$now = date('Y-m-d H:i:s');
			$title = $result['home'] . " vs " . $result['away'];
			$post_name = strtolower(str_replace("-", " ", $title));
				
			if ($stmt = $db->prepare("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")) {
				$stmt->bind_param("isssssssssssssssisissi", $author = 1, $now, $now, $content = '', $title, $excerpt = '', $status = 'publish', $comment_status = 'closed', $ping_status = 'closed', $post_password = '', $post_name, $to_ping = '', $pinged = '', $now, $now, $post_content_filtered = '', $parent = 0, $guid = '', $menu_order = 0, $post_type = 'scoreboard', $post_mime_type = '', $comment_count = 0);
				$stmt->execute();
				$stmt->close();	      
			}
				
			$lastRecord = $db->insert_id;		
			
			$timestamp = strtotime("now");
			$edit_lock = $timestamp . ":1";
			$edit_last = "1";
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = '_edit_lock', $edit_lock);
				$stmt->execute();
				$stmt->close();	      
			}
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = '_edit_last', $edit_last);
				$stmt->execute();
				$stmt->close();	      
			}
			
			$gd_status = "Final";
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_status', $gd_status);
				$stmt->execute();
				$stmt->close();	      
			}
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_away_team', team_name_to_short_name($result['home']));
				$stmt->execute();
				$stmt->close();	      
			}
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_home_team', team_name_to_short_name($result['away']));
				$stmt->execute();
				$stmt->close();	      
			}
			
			$rawScores = explode(" - ", $result['score']);
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_away_team_score', $rawScore[0]);
				$stmt->execute();
				$stmt->close();	      
			}
			
			if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
				$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_home_team_score', $rawScore[1]);
				$stmt->execute();
				$stmt->close();	      
			}
			
			if ($stmt = $db->prepare("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES(?,?,?)")) {
				$stmt->bind_param("iii", $lastRecord, $term_id = 3, $order = 0);
				$stmt->execute();
				$stmt->close();	      
			}
			
			if ($stmt = $db->prepare("INSERT INTO matches (matchDate, matchHome, matchAway, matchScore, matchLocation) VALUES(?,?,?,?,?)")) {
				$stmt->bind_param("sssss", $result['date'], $result['home'], $result['away'], $result['score'], $result['location']);
				$stmt->execute();
				$stmt->close();	      
			}
		
			if ($conf['debug'] == 1) {
				echo "Posting " . $result['home'] . " vs " . $result['away'] . " to facebook.<br /><br />"; 
			}
			addToFacebook($result['home'], $result['away'], $result['score'], $result['location']);				
		}    
	
		echo count($results) . " result(s) added to the database/facebook page";         
	}
}

?>
