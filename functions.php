<?php
	
function team_name_to_short_name($team) {
	$shortname = '';
	
	if( $team == 'Arsenal' ) $shortname = 'ARS';
	if( $team == 'Aston Villa' ) $shortname = 'AVL';
	if( $team == 'Cardiff' ) $shortname = 'CAR';
	if( $team == 'Chelsea' ) $shortname = 'CHE';
	if( $team == 'Crystal Palace' ) $shortname = 'CRY';
	if( $team == 'Everton' ) $shortname = 'EVE';
	if( $team == 'Fulham' ) $shortname = 'FUL';
	if( $team == 'Hull' ) $shortname = 'HUL';
	if( $team == 'Liverpool' ) $shortname = 'LIV';
	if( $team == 'Man City' ) $shortname = 'MCI';
	if( $team == 'Man Utd' ) $shortname = 'MUN';
	if( $team == 'Newcastle' ) $shortname = 'NEW';
	if( $team == 'Norwich' ) $shortname = 'NOR';
	if( $team == 'Southampton' ) $shortname = 'SOU';
	if( $team == 'Stoke' ) $shortname = 'STK';
	if( $team == 'Sunderland' ) $shortname = 'SUN';
	if( $team == 'Swansea' ) $shortname = 'SWA';
	if( $team == 'Tottenham' ) $shortname = 'TOT';
	if( $team == 'West Brom' ) $shortname = 'WBA';
	if( $team == 'West Ham' ) $shortname = 'WHU';
	
	return $shortname;
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

?>