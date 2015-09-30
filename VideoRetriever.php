<?php
	//require_once("pecl_http");
	putenv('PATH=' . getenv('PATH') . PATH_SEPARATOR . '/usr/local/bin');
	class VideoRetriever {
		public function __construct() {
			$sid = session_id();
			$this->progressFile = "Downloads/".$sid.".txt";
		}
		public function convert() {
			$host = $_SERVER["HTTP_HOST"];
			$url = $_REQUEST["youtube_url"];

			//$vid = "UybVeuP-Lxo";
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
			$pattern = "@^(https://)?www\.youtube\.com\/watch\?v=(.{11})@";
			if(isset($url) && preg_match($pattern, $url, $match) == 1) {
				$vid = $match[2];
			} else {
				error_log("Pattern does not match. Exit");
				$arr = array(
				"return" => "1",
				"output" => "URL is not correct",
				"vid"	 => "undefined"
				);
				echo json_encode($arr);
				exit;
			}

			// Check if the converted file already exists
			if(file_exists("Downloads/".$vid.".mp3")) {
				error_log("Requestd file in the cache: id: " . $vid);
				$meta = $this->getVideoMeta($vid);
				$arr = array(
					"return" => "0",
					"output" => "File in the cache",
					"vid"	 => $vid,
					"meta"	 => $meta
				);
				echo json_encode($arr);
				exit;
			} else {
				// update the file in real time
				//header("Content-type: text/plain");
				// prevent output buffering
				$this->disable_ob();
				error_log("Downloading youtube from youtube.com");
				//exec("youtube-dl -x ".$url." --audio-format mp3 --write-info-json -o 'Downloads/%(id)s.%(ext)s'", $output, $ret);
				//system("youtube-dl -x ".$url." --audio-format mp3 --write-info-json -o 'Downloads/%(id)s.%(ext)s'");
				$ret = $this->convertVideoRealtimeOutput("youtube-dl -x ".$url." --audio-format mp3 --write-info-json -o 'Downloads/%(id)s.%(ext)s'", $url);
				error_log("Converstion has completed");

				// Get meta data from the the meta file
				
				$meta = $this->getVideoMeta($vid);
				$arr = array(
					"return" 	=> $ret,
					"vid" 		=> $vid,
					"meta" 		=> $meta
					);
				echo json_encode($arr);
				
			}	
		}

		public function get() {
			// Push files to client 
			if(isset($_REQUEST["vid"])) {
				$filename = $_REQUEST["vid"].".mp3";

				// get the video title and substitute filename with the title
				$meta_str = file_get_contents("Downloads/".$_REQUEST["vid"].".info.json");
				$meta_json = json_decode($meta_str, true);

				header("Content-type:application/mp3");
				header("Content-Disposition:attachement;filename=".$meta_json["title"].".mp3");
				readfile("Downloads/".$filename);
				flush();
				error_log("File: ".$filename." has been pushed to user.");
			} else {
				error_log("Fail to push file ".$filename." to user.");
			}
		}

		public function getConvertStatus() {
			// server side long poll to reture conversion status to front end.
			$startTime = time();
			$lastChangeTime = isset($_REQUEST["timestamp"]) ? $_REQUEST["timestamp"] : 0;
			clearstatcache();
			if(file_exists($this->progressFile)) {
				$changeTime = filemtime($this->progressFile);
			} else {
				$changeTime = 0;
			}
			
			error_log("lastChangeTime: " . $lastChangeTime . " changeTime: " . $changeTime);
			while($changeTime <= $lastChangeTime && time() - $startTime < 20) {
				// no message so just spin here
				usleep(2000000);
				clearstatcache();
				if(file_exists($this->progressFile)) {
					$changeTime = filemtime($this->progressFile);
				} else {
					$changeTime = 0;
				}
//				error_log("file change time: ". $changeTime . " request timestamp " . $lastChangeTime);
			}
			if($changeTime == 0) {
				// No status since status file does not exist
				$ret = array(
					"timestamp" => $changeTime,
					"content"	=> "0");
			} else {
				$ret = array(
					"timestamp" => $changeTime,
					"content"	=> file_get_contents($this->progressFile));
			}	
			error_log("Return to poll: " . $ret["content"] . "timestamp: " . $ret["timestamp"] . 
					"lastChangeTime " . $lastChangeTime);
			echo json_encode($ret);
			flush();

			// delete the file if completed
			if($ret["content"] == "4") {
				error_log("Delete status file. " . $this->progressFile);
				unlink($this->progressFile);
			}
		}

		private function getVideoMeta($vid) {
			// purpose: read meta data in the json meta file and return them 
			// as an associative array
				$meta_str = file_get_contents("Downloads/".$vid.".info.json");
				$meta_json = json_decode($meta_str, true);
				$meta = array( 
					"title" => $meta_json["title"],
					"duration" => $meta_json["duration"]
					);

				return $meta;
		}

		private function disable_ob() {
		    // Turn off output buffering
		    ini_set('output_buffering', 'off');
		    // Turn off PHP output compression
		    ini_set('zlib.output_compression', false);
		    // Implicitly flush the buffer(s)
		    ini_set('implicit_flush', true);
		    ob_implicit_flush(true);
		    // Clear, and turn off output buffering
		    while (ob_get_level() > 0) {
		        // Get the curent level
		        $level = ob_get_level();
		        // End the buffering
		        ob_end_clean();
		        // If the current level has not changed, abort
		        if (ob_get_level() == $level) break;
		    }
		    // Disable apache output buffering/compression
		    if (function_exists('apache_setenv')) {
		        apache_setenv('no-gzip', '1');
		        apache_setenv('dont-vary', '1');
		    }
		}

		private function convertVideoRealtimeOutput ($cmd, $url) {
			$descriptorspec = array(
			   0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
			   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
			   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
			);
			$pipes = array();
			$file = fopen($this->progressFile, "w+");
    		if($file) {
    			ftruncate($file, 0);
    		}
    		fclose($file);
			$process = proc_open($cmd, $descriptorspec, $pipes);
			$status = 0;
			$sid = session_id();
						
			if (is_resource($process)) {
			    while (!feof($pipes[1])) {
			    	// get the output from youtube-dl output, parse and write to pregress file

			    	$s = fgets($pipes[1]);
			    	if(preg_match("@^\[youtube\]@", $s) && $status == 0) {
			    		// downloading meta data
			    		// truncate the status files in case there is residues.
			    		error_log("Status 1. Session: " . $sid);
			    		file_put_contents($this->progressFile, "1");
			    		$status = 1;
			    	} else if(preg_match("@^\[download\]@", $s) && $status == 1) {
			    		// downloading video
			    		error_log("Status 2. Session: " . $sid);
			    		file_put_contents($this->progressFile, "2");
			    		$status = 2;
			    	} else if (preg_match("@^\[ffmpeg\]@", $s) && $status == 2) {
			    		// converting video to audio
			    		error_log("Status 3. Session: " . $sid);
			    		file_put_contents($this->progressFile, "3");
			    		$status = 3;
			    	} else if (preg_match("@^Deleting@", $s) && $status == 3) {
			    		// deleting video file, complete
			    		error_log("Status 4. Session: " . $sid);
			    		file_put_contents($this->progressFile, "4");
			    		$status = 4;
			    	}  else {
			    		usleep(851000);
			    	}
//			    	clearstatcache();
//			    	error_log("File change time: " . filemtime($this->progressFile) . " Status: " . $status);
				}
			}
			proc_close($process);

			if($status == 4) {
				return 0;
			} else {
				return 1;
			}
		}
	}