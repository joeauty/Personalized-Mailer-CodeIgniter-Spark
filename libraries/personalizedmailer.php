<?php

class personalizedmailer {
	private $CI;
	private $config;
	
	function __construct($config = array()) {
		$this->CI =& get_instance();
				
		if (!isset($config['ciemailconfig'])) {			
			$config['ciemailconfig'] = array();
		}		
		
		// set some defaults
		if (!isset($config['loopdelay'])) {
			$config['loopdelay'] = 1;
			//$config['loopdelay'] = 60;
		}
		if (!isset($config['ciroot'])) {
			$config['ciroot'] = '../../../../..';
		}
		
		// expose config to rest of class
		$this->config = $config;		
	}
	
	function errorcheck($msgdata) {
		if (!count($msgdata)) {
			show_error('ERROR: missing personalized message lib data/config');
			return true;
		}
		else if (!isset($msgdata['addresses'])) {
			show_error('ERROR: missing personalized message lib address list');
			return true;
		}
		else if (!isset($msgdata['msgtemplate'])) {
			show_error('ERROR: missing personalized message lib message template');
			return true;
		}
		else if (!isset($msgdata['subject'])) {
			show_error('ERROR: missing personalized message lib message subject');
			return true;
		}
		else if (!isset($msgdata['fromaddr'])) {
			show_error('ERROR: missing personalized message lib message from address');
			return true;
		}	
		else if (!is_writable(sys_get_temp_dir())) {
			show_error('ERROR: tmp path is not writable, required for personalized message lib');
			return true;
		}
		else if ($this->queueset()) {
			show_error('ERROR: personalized message lib has already queued a message for delivery, please try again once your message has been sent');
			return true;
		}
		else if (isset($msgdata['varsearch']) || isset($msgdata['varreplace'])) {
			if (!isset($msgdata['varsearch'])) {
				show_error('ERROR: missing personalized message lib search string(s)');
				return true;
			}
			else if (!isset($msgdata['varreplace'])) {
				show_error('ERROR: missing personalized message lib variable replacement string(s)');
				return true;
			}					

			// make sure that there aren't missing variable substitutions
			for ($y=0; $y < count($msgdata['varsearch']); $y++) {	
				$thisreplacementarr = $msgdata['varreplace'][$y];							
				if (count($thisreplacementarr) !== count($msgdata['addresses'])) {
					show_error('ERROR: the number of personalized message lib variable substitutions does not match the number of addresses being mailed to');
					return true;
				}
			}				

		}	
		
		// no errors
		return false;	
	}
	
	function initqueue() {
		$handle = fopen(sys_get_temp_dir() . "personalizedmailerqueue.run", "w");
		fwrite($handle, "1");
	}
	
	function resetqueue() {
		unlink(sys_get_temp_dir() . "personalizedmailerstart.run");		
	//	unlink(sys_get_temp_dir() . "personalizedmailerqueue.run");		
	}
	
	function queueset() {
		if (file_exists(sys_get_temp_dir() . "personalizedmailerqueue.run")) {
			return true;
		}
		return false;
	}
	
	function sendtolist($msgdata = array()) {
		if ($this->errorcheck($msgdata)) { return; }
	
		if ($msgdata['HTML']) {
			$this->config['ciemailconfig']['mailtype'] = "html";
		}

		// init CI email class
		$this->CI->email->initialize($this->config['ciemailconfig']);
			
		// set tmp file to indicate processing has started
		$handle = fopen(sys_get_temp_dir() . "personalizedmailerstart.run", "w");
		fwrite($handle, "1");
		
		// start loop
		for ($x=0; $x < count($msgdata['addresses']); $x++) {
			$this->CI->email->clear();						
			
			// process variables
			$thismessage = $msgdata['msgtemplate'];
			if (isset($msgdata['varsearch'])) {
				// iterate through each variable											
				for ($y=0; $y < count($msgdata['varsearch']); $y++) {
					$thisreplacementarr = $msgdata['varreplace'][$y];
					$thisreplacement = $thisreplacementarr[$x];
									
					$thismessage = str_replace($msgdata['varsearch'][$y], $thisreplacement, $thismessage);	
				}			
			}
			
			$this->CI->email->to($msgdata['addresses'][$x]);
			if (isset($msgdata['fromname'])) {
				$this->CI->email->from($msgdata['fromaddr'], $msgdata['fromname']);				
			}
			else {
				$this->CI->email->from($msgdata['fromaddr']);				
			}
			if (isset($msgdata['replytoname']) && isset($msgdata['replytoaddr'])) {
				$this->CI->email->reply_to($msgdata['replytoaddr'], $msgdata['replytoname']);				
			}
			else if (isset($msgdata['replytoaddr'])) {
				$this->CI->email->reply_to($msgdata['replytoaddr']);				
			}
			$this->CI->email->subject($msgdata['subject']);
			$this->CI->email->message($thismessage);
			$this->CI->email->send();

			sleep($this->config['loopdelay']);			
		}

		$this->resetqueue();
	}
}

?>