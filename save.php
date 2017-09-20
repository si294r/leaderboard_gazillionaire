<?php

include("config.php");

//$input = file_get_contents("php://input");
$json = json_decode($input);

$data['facebook_id'] = isset($json->facebook_id) ? $json->facebook_id : "";
$data['country'] = isset($json->country) ? $json->country : "";
$data['score'] = isset($json->score) ? $json->score : 0;
$data['display_name'] = isset($json->display_name) ? $json->display_name : "";

$connection = new PDO(
    "mysql:dbname=$mydatabase;host=$myhost;port=$myport",
    $myuser, $mypass
);

$result = file_get_contents('https://graph.facebook.com/v2.9/'.$data['facebook_id'].'?fields=id,name,currency', null, stream_context_create(
        array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Authorization:OAuth '. ($IS_DEVELOPMENT ? FACEBOOK_APP_TOKEN_DEV : FACEBOOK_APP_TOKEN)
            )
        )
    )
);
$array_json = json_decode($result, TRUE);

if (trim($data['display_name']) == "" && isset($array_json['name'])) {
    $data['display_name'] = $array_json['name'];
}
if (trim($data['country']) == "" && isset($array_json['currency']['user_currency'])) {
    $data['country'] = substr($array_json['currency']['user_currency'], 0, 2);
}

// create record if not exists
$sql1 = "INSERT INTO leaderboard (facebook_id, world, country, score, display_name, last_update)
    VALUES (:facebook_id, $current_world, :country, :score, :display_name, NOW())
    ON DUPLICATE KEY UPDATE
    country = :country2, score = :score2, display_name = :display_name2, last_update = NOW()
";
$statement1 = $connection->prepare($sql1);
$statement1->bindParam(":facebook_id", $data['facebook_id']);
$statement1->bindParam(":country", $data['country']);
$statement1->bindParam(":score", $data['score']);
$statement1->bindParam(":display_name", $data['display_name']);
$statement1->bindParam(":country2", $data['country']);
$statement1->bindParam(":score2", $data['score']);
$statement1->bindParam(":display_name2", $data['display_name']);
$statement1->execute();

$data['affected_row'] = $statement1->rowCount();
$data['error'] = 0;
$data['message'] = 'Success';

//header('Content-Type: application/json');
//echo json_encode($data);   
return $data;
