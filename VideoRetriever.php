<?php
	//require_once("pecl_http");
	putenv('PATH=' . getenv('PATH') . PATH_SEPARATOR . '/usr/local/bin');
	class VideoRetriever {
		public function convert() {
			$host = $_SERVER["HTTP_HOST"];
			$url = $_REQUEST["youtube_url"];
			$vid = "UybVeuP-Lxo";
			//$url = http_build_url(); 	// default is current page
			// Or use this:
			// $url = $_SERVER["REQUEST_URI"];
			error_log("Current URL: " . $url);

			// TODO: need to revisit the validation part
			// need to validate that the domain is www.youtube.com
			// need to validate the input field
			/*
			if(!isset($vid) || empty($vid)) {
				error_log("No Video Id has been passed!");
				exit;
			}
			*/

			error_log("Downloading youtube from youtube.com");
			exec("youtube-dl -x ".$url." --audio-format mp3 -o 'Downloads/%(id)s.%(ext)s'", $output, $ret);
			error_log("Converstion has completed");

			$arr = array(
				"return" => $ret,
				"output" => $output,
				"vid"	 => $vid
				);
			echo json_encode($arr);
		}

		public function get() {
			// Push files to client 
			if(isset($_REQUEST["vid"])) {
				$filename = $_REQUEST["vid"].".mp3";
				header("Content-type:application/mp3");
				header("Content-Disposition:attachement;filename=".$filename);
				readfile("Downloads/".$filename);
				flush();
				error_log("File: ".$filename." has been pushed to user.");
			} else {
				error_log("Fail to push file ".$filename." to user.");
			}
		}
	}