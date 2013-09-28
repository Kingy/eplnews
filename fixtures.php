<?php

require 'src/facebook.php';
require 'config.php';

$db = new mysqli($conf['db_hostname'], $conf['db_username'], $conf['db_password'], $conf['db_name']);
$URL = "http://www.premierleague.com/en-gb/matchday/matches.html?paramClubId=ALL&paramComp_8=true&view=.dateSeason";

if($db->connect_errno > 0){
	die('Unable to connect to database [' . $db->connect_error . ']');
}

$ch = curl_init();
if($ch){
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
	
	if($headers['http_code'] == 200){ 
      
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
      
		$fixtures = array();
		$nextWeek = strtotime("+7 day");
      
		foreach ($scoresTable as $table) { 
			$newDom = new DOMDocument;  
			$newDom->appendChild($newDom->importNode($table,true));  
			$scoreXpath = new DOMXPath( $newDom );  
                      
			$date = trim($scoreXpath->query("tr/th/text()")->item(0)->nodeValue); 
           
			$fixturesTable = $scoreXpath->query("tr[position()>1]");
			
			if ($conf['debug'] == 1) {
				echo "Fixtures found: " . $fixturesTable->length . "<br /><br />"; 
			}
           
			foreach ($fixturesTable as $fixture) {
				$rDom = new DOMDocument;
				$rDom->appendChild($rDom->importNode($fixture,true));  
				$fixtureXpath = new DOMXPath($rDom);  	
           	           	
				$time = trim($fixtureXpath->query("td[2]/text()")->item(0)->nodeValue);
				$teams = trim($fixtureXpath->query("td[3]/a/text()")->item(0)->nodeValue);
				$location = trim($fixtureXpath->query("td[4]/a/text()")->item(0)->nodeValue);
           	
                $rawTeams = explode(" v ", $teams);

				if ($teams != "") {
                    $tsDate = strtotime($date);
                    if ($tsDate <= $nextWeek) {
						if ($conf['debug'] == 1) {
							echo "Found new fixture: " . $rawTeams[0] . " vs " . $rawTeams[1] . "<br /><br />"; 
						}
						
						$now = date('Y-m-d H:i:s');
						$title = $rawTeams[0] . " vs " . $rawTeams[1];
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
						
						$matchTime = strtotime($date . " " . $time);
						$gd_status = date("d/m H:i", $matchTime);
						
						if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
							$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_status', $gd_status);
							$stmt->execute();
							$stmt->close();	      
						}
						
						if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
							$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_away_team', $rawTeams[0]);
							$stmt->execute();
							$stmt->close();	      
						}
						
						if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
							$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_home_team', $rawTeams[1]);
							$stmt->execute();
							$stmt->close();	      
						}
						
						if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
							$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_away_team_score', $score = '0');
							$stmt->execute();
							$stmt->close();	      
						}
						
						if ($stmt = $db->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES(?,?,?)")) {
							$stmt->bind_param("iss", $lastRecord, $meta_key = 'gd_home_team_score', $score = '0');
							$stmt->execute();
							$stmt->close();	      
						}
						
						if ($stmt = $db->prepare("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES(?,?,?)")) {
							$stmt->bind_param("iii", $lastRecord, $term_id = 3, $order = 0);
							$stmt->execute();
							$stmt->close();	      
						}
						
						$fixtures[] = array(  
								'date' => $date,  
                   		        'time' => $time,  
                    		    'home' => $rawTeams[0],
                                'away' => $rawTeams[1], 
                    		    'location' => $location,            
                    	);
                        
                    }			          	      	 
				}
				sleep(1);
			}
		}
	
		foreach ($fixtures as $fixture)
		{
							
		}    
	}
}

?>
