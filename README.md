EPLNews
=======

EPL Fixtures/Results scraper

This small PHP script scrapes www.premierleague.com for all fixtures and results. 

The data scraped is added to several wordpress database tables in order to appear on www.epl-news.com. Also using the PHP & the Facebook API the fixtures and results are added to https://www.facebook.com/EPLNewsInfo.

These are run using a simple cron job:

*/15	*	*	*	1,2,3,0,6	php -q eplnews/results.php
0	0	*	*	4	php -q eplnews/fixtures.php	

Authors:
Jamie Gracie
