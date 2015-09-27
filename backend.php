<?php
	// Retrieves Youtube video, convert it into mp3 and returns a retrieve url to the front page

	require_once("VideoRetriever.php");

	// start a user session and prevents others to access the session
	ini_set("session.use_only_cookies", true);
	session_start();

/*
	// add a token
	$salt = "banana";
	$tokenstr = strval(date('W')) . $salt;
	$token = md5($tokenstr);

	// check if token is present in the request
	if(!isset($_REQUEST["token"]) || $_REQUEST["token"] != $token) {
		error_log("User session is not correct and access has been rejected.");
		exit;
	}

	$_SESSION["token"] = $token;
	output_add_rewrite_var("token", $token);
*/
	// TODO: Can you create VideoRetriever once?
	if(!isset($_SESSION["retriever"])) {
		$retriever = new VideoRetriever();
		$_SESSION["retriever"] = $retriever;
	} else {
		$retriever = $_SESSION["retriever"];
	}

	// prevent session block
	session_write_close();

	if(isset($_REQUEST["op"])) {
		if($_REQUEST["op"] == "convert") {
			return $retriever->convert();			
		} else if($_REQUEST["op"] == "download") {
			error_log("pushing the video to client.");
			return $retriever->get();
		} else if($_REQUEST["op"] == "pollStatus") {
			return $retriever->getConvertStatus();
		}
	} else {
		return json_encode(
			array(
				"return" => "0",
				"output" => "No operation is provided."
				));
	}


	



