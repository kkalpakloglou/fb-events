<?php



class Regions {

	public function retrieve()
	{

		require 'db.php';

		$query = "SELECT latitude as lat, longitude as lng FROM regions";
		$statement = $db->prepare($query);
		$statement->execute();
		$regions = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $regions;

	}

}


class Stores {


	public $access_token = "1737943446491742|Jeyv-A5dC7QCHe59DVBLnsj57NY";
	public $user_access_token = "CAAYspn3Ekl4BACFaCdGlRWiowZCkMGV9jZBQpKOLtsq4msokkcZC6GMWFHiHV2XbrY7qK5Hpv3ZBzLywrKE017bmZAJDdqBxSH1trWf9iwMoRNgU6vzr1Tneqt6DCAhhMzSmYao6Srm0gyyUsaiuCZC9eZC75RIwNZB41N0WSl6ZAQ0l7VwtxfMM77KfwzjXYKi4ZD";
	public $distance = 5000;
	private $urls = array();
	private $stores = array();
	protected $counter = 0;


	public function fbApiUrls($regions) {


		foreach ($regions  as $region) {


			$this->urls[] ="https://graph.facebook.com/v2.5/search?type=place&q=*&center=".$region["lat"].",".$region["lng"]."&distance=".$this->distance."&fields=id,name,picture.type(large),location,events.fields(id,name,cover.fields(id,source),picture.type(large),description,start_time,attending_count,declined_count,maybe_count,noreply_count)&limit=1000000000&access_token=".$this->access_token;
		} 

		return $this->urls;

	}


	public function getData($urls) {

		$urls_chucks = array_chunk($urls,3);

		foreach($urls_chucks as $urls_chuck) {
			//var_dump($urls_chuck);
			$count_urls = count($urls_chuck);
			$r = $this->multiRequest($urls_chuck);
			//	var_dump($r);

			for($i=0;$i<$count_urls;$i++) {
				$temp  = $r[$i]["data"];
				$this->stores =array_merge($temp,$this->stores);
			}
		}

		return $this->stores;
		//return "the end";

	}



	public function multiRequest($data, $options = array()) {

 		 // array of curl handles
		$curly = array();
  		// data to be returned
		$result = array();

  		// multi handle
		$mh = curl_multi_init();

 		 // loop through $data and create curl handles
 		 // then add them to the multi-handle
		foreach ($data as $id => $d) {

			$curly[$id] = curl_init();

			$url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
			curl_setopt($curly[$id], CURLOPT_URL,            $url);
			curl_setopt($curly[$id], CURLOPT_HEADER,         0);
			curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);

   			 // post?
			if (is_array($d)) {
				if (!empty($d['post'])) {
					curl_setopt($curly[$id], CURLOPT_POST,       1);
					curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
				}
			}

   			 // extra options?
			if (!empty($options)) {
				curl_setopt_array($curly[$id], $options);
			}

			curl_multi_add_handle($mh, $curly[$id]);
		}

  		// execute the handles
		$running = null;
		do {
			curl_multi_exec($mh, $running);
		} while($running > 0);


  		// get content and remove handles
		foreach($curly as $id => $c) {
			$result[$id] = json_decode(curl_multi_getcontent($c),true);
			curl_multi_remove_handle($mh, $c);
		}

  		// all done
		curl_multi_close($mh);

		return $result;
	}





	public function distance($lat2, $lon2) {

		$lat_athens_center  = 37.993427;
		$long_athens_center  =  23.764363;
		$unit = "K";


		$theta = $long_athens_center - $lon2;
		$dist = sin(deg2rad($lat_athens_center)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat_athens_center)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);

		if ($unit == "K") {
			return ($miles * 1.609344);
		} else if ($unit == "N") {
			return ($miles * 0.8684);
		} else {
			return $miles;
		}
	}





	public function save_store_info_to_db($stores) {


		require 'db.php';

		$rowsToInsert = array();
		$athens_stores = array();

		forEach($stores  as $store) {

			$country = $store["location"]["country"];
			$distance_from_Athens  = $this->distance( $store["location"]["latitude"], $store["location"]["longitude"]);

			if(  ($country=="Greece" || $country ==null) && isset($store['events']) && $distance_from_Athens<=45 ) {

				$rowsToInsert[] = array(
					'place_id' =>$store["id"],
					'name' => $store["name"],
					'picture' => $store["picture"]["data"]["url"],
					'city' => $store["location"]["city"],
					'country' => $store["location"]["country"],
					'latitude' =>$store["location"]["latitude"],
					'longitude' => $store["location"]["longitude"],
					'street' => $store["location"]["street"],
					'zip' => $store["location"]["zip"]
					);

				$athens_stores[] = $store;

			}

		}

		// var_dump($athens_stores);

	//	$this->save_events_info_to_db($athens_stores);

		$this->pdoMultiInsert('places', $rowsToInsert);
		$db = null;

		return $athens_stores;

	}


	public function pdoMultiInsert($tableName, $data){
		require 'db.php';
    //Will contain SQL snippets.
		$rowsSQL = array();

    //Will contain the values that we need to bind.
		$toBind = array();

    //Get a list of column names to use in the SQL statement.
		$columnNames = array_keys($data[0]);

    //Loop through our $data array.
		foreach($data as $arrayIndex => $row){
			$params = array();
			foreach($row as $columnName => $columnValue){
				$param = ":" . $columnName . $arrayIndex;
				$params[] = $param;
				$toBind[$param] = $columnValue; 
			}
			$rowsSQL[] = "(" . implode(", ", $params) . ")";
		}

    //Construct our SQL statement
		$sql = "REPLACE INTO `$tableName` (" . implode(", ", $columnNames) . ") VALUES " . implode(", ", $rowsSQL);

		$pdoStatement = $db->prepare($sql);
		foreach($toBind as $param => $val){
			$pdoStatement->bindValue($param, $val);
		}

		return $pdoStatement->execute();

	}

}

class Events {


	function save_events_info_to_db($stores) {

		require 'db.php';

		$rowsToInsert = array();
		echo "<br/>stores: ".count($stores)."<br/>";

		forEach($stores  as $store) {

			$country = $store["location"]["country"];
			$events = $store["events"]["data"];

			if(  ($country=="Greece" || $country ==null) && isset($events) ) {

				$all_events = $all_events + count($events);
				forEach($events as $event) {

					if(strtotime($event["start_time"])>time()) {

						$rowsToInsert[] = array(
							'event_id' => $event["id"],
							'name' => $event["name"],
							'picture' => $event["picture"]["data"]["url"],
							'cover' => $event["cover"]["source"],
							'description' => $event["description"],
							'start_time' => $event["start_time"],
							'attending_count' => $event["attending_count"],
							'declined_count' => $event["declined_count"],
							'maybe_count' => $event["maybe_count"],
							'noreply_count' => $event["noreply_count"],
							'place_id' => $store["id"],
							'place_name' => $store["name"]				 
							);
					}

					echo "event: ".$event["name"]." ::: ".$event["id"]."<br/>";
				}
			}
		}

		// echo "count: ".count($rowsToInsert);
	// var_dump($rowsToInsert);
		$this->pdoMultiInsert('events', $rowsToInsert);
		$db = null;
	}




	public function pdoMultiInsert($tableName, $data){
		require 'db.php';
    //Will contain SQL snippets.
		$rowsSQL = array();

    //Will contain the values that we need to bind.
		$toBind = array();

    //Get a list of column names to use in the SQL statement.
		$columnNames = array_keys($data[0]);

    //Loop through our $data array.
		foreach($data as $arrayIndex => $row){
			$params = array();
			foreach($row as $columnName => $columnValue){
				$param = ":" . $columnName . $arrayIndex;
				$params[] = $param;
				$toBind[$param] = $columnValue; 
			}
			$rowsSQL[] = "(" . implode(", ", $params) . ")";
		}

    //Construct our SQL statement
		$sql = "REPLACE INTO `$tableName` (" . implode(", ", $columnNames) . ") VALUES " . implode(", ", $rowsSQL);

		$pdoStatement = $db->prepare($sql);
		foreach($toBind as $param => $val){
			$pdoStatement->bindValue($param, $val);
		}

		return $pdoStatement->execute();

	}


}



?>