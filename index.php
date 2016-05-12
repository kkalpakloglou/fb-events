<?php

require 'fbData.php';

echo "Getting all regions from db...!<br/>";
$regions = new Regions();
$all_regions = $regions->retrieve();

// var_dump($all_regions);


$stores = new Stores();
$stores_fbApiUrls = $stores->fbApiUrls($all_regions);


var_dump($stores_fbApiUrls);



$all_stores = $stores->getData($stores_fbApiUrls);


$athens_stores = $stores->save_store_info_to_db($all_stores);

$events = new Events();

$events->save_events_info_to_db($athens_stores); 