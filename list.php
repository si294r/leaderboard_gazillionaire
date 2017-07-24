<?php

include("config.php");

$json = json_decode($input);
$facebook_id = isset($json->facebook_id) ? $json->facebook_id : "";
$limit = isset($json->limit) ? $json->limit : 20;
$country = isset($json->country) ? $json->country : "";
$renew_cache = isset($json->renew_cache) ? $json->renew_cache : "0";

$connection = new PDO(
    "mysql:dbname=$mydatabase;host=$myhost;port=$myport", $myuser, $mypass
);

function get_file_cache($param) {
    global $IS_DEVELOPMENT, $current_world;
    return $IS_DEVELOPMENT ? "cache/".$param."_{$current_world}.tmpdev" : "cache/".$param."_{$current_world}.tmp";
}

function read_cache($param) {
    if (is_file(get_file_cache($param))) {
        return json_decode(file_get_contents(get_file_cache($param)), true);
    } else {
        return [];
    }
}

function cmp_row($a, $b) {
    if (intval($a['score']) == intval($b['score'])) {
        return 0;
    }
    return (intval($a['score']) < intval($b['score'])) ? -1 : 1;
}

if ($renew_cache == "1") {
    // To Do - Get Country Code from table leaderboard
    $CountryCodes;
    $sql_country = "
        SELECT DISTINCT country
        FROM leaderboard where world = $current_world
        UNION
        SELECT 'global' country
        ";
    $statement_country = $connection->prepare($sql_country);
    $statement_country->execute();
    $rows_country = $statement_country->fetchAll(PDO::FETCH_ASSOC);
    $CountryCodes = array_column($rows_country, "country");
    
    foreach ($CountryCodes as $v) {
        if ($v == "global") {
            $sql = "
    SELECT 
	*
    FROM leaderboard
    ORDER BY score DESC, facebook_id
    LIMIT $limit;
            ";            
        } else {
            $sql = "
    SELECT 
	*
    FROM leaderboard WHERE country = '$v'
    ORDER BY score DESC, facebook_id
    LIMIT $limit;
            ";            
        }
        
        $statement = $connection->prepare($sql);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        file_put_contents(get_file_cache($v), json_encode($rows));
    }
}


$sql1 = "select * from leaderboard where facebook_id = :facebook_id and world = $current_world";
$statement1 = $connection->prepare($sql1);
$statement1->execute(
        array(':facebook_id' => $facebook_id)
        );
$rows1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

/* Leaderboard Global */
$row_global = read_cache("global");
if (array_search($facebook_id, array_column($row_global, "facebook_id")) === FALSE) {
    if (isset($rows1[0])) $rows1[0]['country'] = "global";
    $row_global[] = $rows1[0];
}
usort($row_global, 'cmp_row');

/* Leaderboard Region */
$row_region = read_cache($country);
if (array_search($facebook_id, array_column($row_region, "facebook_id")) === FALSE) {
    if (isset($rows1[0])) $rows1[0]['country'] = $country;
    $row_region[] = $rows1[0];
}
usort($row_region, 'cmp_row');

/* Leaderboard Friend */
$result = file_get_contents('https://graph.facebook.com/v2.9/'.$facebook_id.'/friends', null, stream_context_create(
        array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Authorization:OAuth '.FACEBOOK_APP_TOKEN
            )
        )
    )
);
$array_json = json_decode($result, TRUE);
$array_friend_id = array_column($array_json["data"], "id");
$array_friend_id[] = $facebook_id;

$sql2 = "SELECT * FROM leaderboard WHERE facebook_id IN ('".implode("', '", $array_friend_id)."') and world = $current_world";
$statement2 = $connection->prepare($sql2);
$statement2->execute();
$row_friend = $statement2->fetchAll(PDO::FETCH_ASSOC);


/* Finish */
$data["facebook_id"] = $facebook_id;
$data["country"] = $country;
$data["limit"] = $limit;
$data["global"] = $row_global;
$data["region"] = $row_region;
$data["friend"] = $row_friend;

return $data;
