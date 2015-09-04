<?php
	// Retrieves Youtube video, convert it into mp3 and returns a retrieve url to the front page

	require_once("VideoRetriever.php");
	// TODO: Can you create VideoRetriever once?
	$retriever = new VideoRetriever();


	if(isset($_REQUEST["op"])) {
		if($_REQUEST["op"] == "convert") {
			return $retriever->convert();			
		} else if($_REQUEST["op"] == "download") {
			error_log("pushing the video to client.");
			return $retriever->get();
		}
	} else {
		return json_encode(
			array(
				"return" => "0",
				"output" => "No operation is provided."
				));
	}


	



