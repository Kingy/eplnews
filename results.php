<?php

require 'src/facebook.php';
require 'config.php';

$db = new mysqli($conf['db_hostname'], $conf['db_username'], $conf['db_password'], $conf['db_name']);
$URL = "http://www.premierleague.com/en-gb/matchday/results.html?paramClubId=ALL&paramComp_8=true&paramSeason=2013-2014&view=.dateSeason";

if($db->connect_errno > 0){
    die('Unable to connect to database [' . $db->connect_error . ']');
}

function addToFacebook($home, $away, $score, $location) {

	global $conf;
	
	$PAGE_TOKEN = "";

	$facebook = new Facebook(array(  
  		'appId'  => $conf['facebook_app_id'], 
  		'secret' => $conf['facebook_secret'],  
  		'cookie' => true,  
	));  
  
	$post = array('access_token' => $conf['access_token']); 
  
	try {
		$res = $facebook->api('/me/accounts','GET',$post);
	
		if (isset($res['data'])) {
        	foreach ($res['data'] as $account) {
        		if ($conf['page_id'] == $account['id']) {
           			$PAGE_TOKEN = $account['access_token'];
           			break;
        		}
        	}
    	}	
	} catch (Exception $e) {
		echo $e->getMessage();
	}

	$post = array('access_token' => $PAGE_TOKEN, 'message' => "Final Result: $home $score $away @ $location");  
  
	try {  
		$res = $facebook->api("/EPLNewsInfo/feed","POST",$post); 		  
	} catch (Exception $e){  
		echo $e->getMessage();  
	}  
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
