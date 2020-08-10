<?php

// Database connection file
require_once('db_connect.php');

// Receive uploaded image file
$data = file_get_contents('php://input');

if(isset($data))
{
	// convert json to array
	$data_arr = json_decode($data, true);
	
	// The image we receive will be encoded in base64.
	// So, convert it to actual image file.
	$img = $data_arr['image'];
	$img = str_replace('data:image/jpeg;base64,', '', $img);
	$img = str_replace(' ', '+', $img);
	$file_data = base64_decode($img);
	$file_name = 'images/'.time().'-'.mt_rand(100000,999999).'.jpg';
	file_put_contents($file_name, $file_data);
	
	$created_time = date('Y-m-d H:i:s');
	
	// SQL query to insert image file path in database.
	$query = "INSERT INTO detected_faces (image, created_time) VALUES ('$file_name', '$created_time')";
	
	if(mysqli_query($conn, $query))
    {
		$response = array(
			'status' => true,
			'message' => "Image Saved Successfully."
		);
    }
    else
    {
		$response = array(
			'status' => true,
			'message' => "Unable to save image."
		);
    }
}
else
{
	$response = array(
		'status' => false,
		'message' => "Failed"
	);
}

// return the response
header('Content-type: application/json');
echo json_encode($response);

?>