<?php

// CORS
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept');

// AWS SDK
require 'vendor/autoload.php';
use Aws\Credentials\CredentialProvider;

// MySQL CONFIG
$db_endpoint_file = fopen("../db_endpoint.txt", "r") or die("Server Error: DB Endpoint Not Found");
$db_endpoint = trim(fread($db_endpoint_file, filesize("../db_endpoint.txt")));
fclose($db_endpoint_file);

$db_port = 3306;
$db_region = "us-east-2";
$db_user = 'server_ec2';
$db_name = 'portfolio';

// IAM DB Authentication
$provider = CredentialProvider::defaultProvider();
$rds_auth_generator = new Aws\Rds\AuthTokenGenerator($provider);

$token = $rds_auth_generator->createToken($db_endpoint . ":" . $db_port, $db_region, $db_user);

// DB Connection
$conn = mysqli_init();
mysqli_options($conn, MYSQLI_READ_DEFAULT_FILE, "./enable_clrtxt_pswd.cnf");
$conn->real_connect($db_endpoint, $db_user, $token, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));

if (!$conn) {
	die("Connection failed: " . mysqli_connect_error());
}

// REST
$prop_type = $_GET['prop_type'];
if ($prop_type === 'blogs') {
	$sql = "select * from Blogs";
	$result = mysqli_query($conn, $sql);
	if (!$result) {
		http_response_code(404);
		die(mysqli_error($conn));
	}
	echo '[';
	for ($i = 0; $i < mysqli_num_rows($result); $i++) {
		if ($i > 0) {
			echo ',' . json_encode(mysqli_fetch_object($result));
		} else {
			echo json_encode(mysqli_fetch_object($result));
		}
		// echo($i > 0 ? ',' : '') . json_encode(mysqli_fetch_object($result));
	}
	echo ']';
} elseif ($prop_type === 'projects') {
	$sql = 'select * from Projects';
	$result = mysqli_query($conn, $sql);
	if (!$result) {
		http_response_code(404);
		die(mysqli_error($conn));
	}
	$rows = array();
	while ($r = mysqli_fetch_assoc($result)) {
		$project_id = $r['id'];
		$sql = "select img_url, link_url from ProjectImages where project_id=$project_id";
		$img_result = mysqli_query($conn, $sql);
		if (!$img_result) {
			http_response_code(404);
			die(mysqli_error($conn));
		}
		$img_rows = array();
		$link_rows = array();
		while ($img_r = mysqli_fetch_assoc($img_result)) {
			$img_rows[] = $img_r['img_url'];
			$link_rows[] = $img_r['link_url'];
		}
		$r['img_urls'] = $img_rows;
		$r['link_urls'] = $link_rows;
		$rows[] = $r;
	}
	$json = json_encode($rows);
	echo $json;
} elseif ($prop_type === 'programming') {
	$sql = 'select * from ProgrammingExperience;';
	$result = mysqli_query($conn, $sql);
	$sql = 'select pe_id, subtitle, text, style from ProgrammingExperiencesInfo';
	$info_result = mysqli_query($conn, $sql);
	$info = array();
	for ($i = 0; $i < mysqli_num_rows($info_result); $i++) {
		array_push($info, mysqli_fetch_assoc($info_result));
	}
	$info_size = sizeof($info);
	if (!$result || !$info_result) {
		http_response_code(404);
		die(mysqli_error($conn));
	}
	echo '[';
	for ($i = 0; $i < mysqli_num_rows($result); $i++) {
		$row = mysqli_fetch_assoc($result);
		$row['content'] = array();
		for ($j = 0; $j < $info_size; $j++) {
			if ($info[$j]['pe_id'] == $row['pe_id']) {
				array_push($row['content'], $info[$j]);
			}
		}
		echo ($i > 0 ? ',' : '') . json_encode($row);
	}
	echo ']';
} elseif ($prop_type === 'tools') {
	$sql = 'select * from Tools';
	$result = mysqli_query($conn, $sql);
	if (!$result) {
		http_response_code(404);
		die(mysqli_error($conn));
	}
	$rows = array();
	while ($r = mysqli_fetch_assoc($result)) {
		$rows[] = $r;
	}
	echo json_encode($rows);
}

$conn->close();

?>