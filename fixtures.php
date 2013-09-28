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
						$fixtures[] = array(  
								'date' => $date,  
                   		        'time' => $time,  
                    		    'home' => $rawTeams[0],
                                'away' => $rawTeams[1], 
                    		    'location' => $location,            
                    	);
                        
                    }			          	      	 
				}
			}
		}
	
		foreach ($fixtures as $fixture)
		{
			
							
		}    
	}
}

?>
