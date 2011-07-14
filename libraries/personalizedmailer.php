<?php

class personalizedmailer {
	private $CI;
	private $config;
	
	function __construct($config = array()) {
		$this->CI =& get_instance();
		
		global $argv;	
		if (isset($argv)) {
			$config['cli'] = true;
		}
		
		if (!count($config)) {
			if (isset($config['cli'])) {
				echo "ERROR: missing personalized message lib data/config\n";
			}
			else {
				show_error('ERROR: missing personalized message lib data/config');				
			}
		}		
		else if (!isset($config['pmdatadir']) || !is_dir($config['pmdatadir'])) {
			if (isset($config['cli'])) {
				echo "ERROR: missing personalized message lib working directory for staging mailings\n";
			}
			else {
				show_error('ERROR: missing personalized message lib working directory for staging mailings');				
			}		
		}
		else if (!is_writable($config['pmdatadir'])) {			
			if (isset($config['cli'])) {
				echo "ERROR: personalized message lib working directory does not have writable permissions assigned to this user\n";
			}
			else {
				show_error('ERROR: personalized message lib working directory does not have writable permissions assigned to it for the web server user');				
			}			
		}
			
		// add trailing slash to pmdatadir, if necessary
		if (!preg_match('/\/$/', $config['pmdatadir'])) {
			$config['pmdatadir'] .= "/";
		}
		
		// expose to other classes
		$this->config = $config;		
	}
	
	function errorcheck($config) {
		if (!count($config)) {
			show_error('ERROR: missing personalized message lib data/config');
			return true;
		}		
		else if (!isset($config['addresses'])) {
			show_error('ERROR: missing personalized message lib address list');
			return true;
		}
		else if (!isset($config['msgtemplate'])) {
			show_error('ERROR: missing personalized message lib message template');
			return true;
		}
		else if (!isset($config['subject'])) {
			show_error('ERROR: missing personalized message lib message subject');
			return true;
		}
		else if (!isset($config['fromaddr'])) {
			show_error('ERROR: missing personalized message lib message from address');
			return true;
		}		
		else if ($this->queueset()) {
			show_error('ERROR: personalized message lib has already queued a message for delivery. Please wait until your messages have been sent');
		}		
		else if ($this->queuestarted()) {
			// this error handling shouldn't be necessary, GUI should hide submit button
			// when $this->queueset(), but just in case...
			show_error('ERROR: personalized message lib is in the process of sending messages. Please wait until your messages have been sent');
			return true;
		}
		else if (isset($config['varsearch']) || isset($config['varreplace'])) {
			if (!isset($config['varsearch'])) {
				show_error('ERROR: missing personalized message lib search string(s)');
				return true;
			}
			else if (!isset($config['varreplace'])) {
				show_error('ERROR: missing personalized message lib variable replacement string(s)');
				return true;
			}					

			// make sure that there aren't missing variable substitutions
			for ($y=0; $y < count($config['varsearch']); $y++) {	
				$thisreplacementarr = $config['varreplace'][$y];							
				if (count($thisreplacementarr) !== count($config['addresses'])) {
					show_error('ERROR: the number of personalized message lib variable substitutions does not match the number of addresses being mailed to');
					return true;
				}
			}				

		}	
		
		// no errors
		return false;	
	}
	
	function initqueue($msgdata = array()) {		
		if ($this->errorcheck($msgdata)) { return; }	
		
		// write message template to working directory
		file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmtemplate.txt", $msgdata['msgtemplate'], LOCK_EX);
		// remove template from data structure
		unset($msgdata['msgtemplate']);
		
		// set queue lockfile
		file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmqueue.run", "1", LOCK_EX);
		
		if (!isset($msgdata['ciemailconfig'])) {			
			$msgdata['ciemailconfig'] = array();
		}		

		// set some defaults
		if (!isset($msgdata['loopdelay'])) {
			$msgdata['loopdelay'] = 1;
			//$config['loopdelay'] = 60;
		}
	
		// set HTML option
		if ($msgdata['HTML']) {
			$msgdata['ciemailconfig']['mailtype'] = "html";
		}		

		// write config/msgdata to working dir as JSON string
		file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmdata.txt", json_encode($msgdata), LOCK_EX);
		
		// write inital status
		$status = array();
		$status['queueset'] = 1;		
		file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmstatus.tmp", json_encode($status), LOCK_EX);
	}
	
	function resetqueue() {
		unlink($this->config['pmdatadir'] . $this->config['domain'] . "-pmstart.run");	
		unlink($this->config['pmdatadir'] . $this->config['domain'] . "-pmstatus.tmp");
		unlink($this->config['pmdatadir'] . $this->config['domain'] . "-pmqueue.run");		
	}
	
	function queueset() {
		if (file_exists($this->config['pmdatadir'] . $this->config['domain'] . "-pmqueue.run")) {
			return true;
		}
		return false;
	}
	
	function queuestarted() {
		if (file_exists($this->config['pmdatadir'] . $this->config['domain'] . "-pmstart.run")) {
			return true;
		}
		return false;
	}
	
	function sendtolist() {
		global $argv;
		$clioptions = array();
		if (in_array('--silent', $argv)) {	
			$this->config['silent'] = $clioptions['silent'];					
		}
	
		if (!$this->queueset()) {
			if (!isset($this->config['silent'])) {
				print "ERROR: personalized message lib has not queued a message for delivery, please init a queue\n";				
			}			
			exit;
		}
		
		// retrieve msgdata/config from working dir
		$msgdata = json_decode(file_get_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmdata.txt"));
		$msgtemplate = file_get_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmtemplate.txt");
		
		$totaladdr = count($msgdata->addresses);

		// init CI email class
		$this->CI->email->initialize($msgdata->ciemailconfig);
			
		// set tmp file to indicate processing has started		
		file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmstart.run", "1", LOCK_EX);
		
		// init status array	
		$status = array(
			'total' => $totaladdr
		);
		
		// start loop
		for ($x=0; $x < count($msgdata->addresses); $x++) {
			if (!isset($this->config['silent'])) {
				echo $msgdata->addresses[$x] . "\n";				
			}
			
			$this->CI->email->clear();						
			$thisaddress = $msgdata->addresses[$x];
			
			// update status
			$status['queueset'] = 1;
			$status['lastaddr'] = $thisaddress;
			$status['messagenum'] = $x;
			$status['progress'] = round(($x / $totaladdr) * 100);
			file_put_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmstatus.tmp", json_encode($status), LOCK_EX);
			
			// process variables
			$thismessage = $msgtemplate;
			if (isset($msgdata->varsearch)) {
				// iterate through each variable											
				for ($y=0; $y < count($msgdata->varsearch); $y++) {
					$thisreplacementarr = $msgdata->varreplace[$y];
					$thisreplacement = $thisreplacementarr[$x];
									
					$thismessage = str_replace($msgdata->varsearch[$y], $thisreplacement, $thismessage);	
				}			
			}
			
			$this->CI->email->to($thisaddress);
			if (isset($msgdata->fromname)) {
				$this->CI->email->from($msgdata->fromaddr, $msgdata->fromname);				
			}
			else {
				$this->CI->email->from($msgdata->fromaddr);				
			}
			if (isset($msgdata->replytoname) && isset($msgdata->replytoaddr)) {
				$this->CI->email->reply_to($msgdata->replytoaddr, $msgdata->replytoname);				
			}
			else if (isset($msgdata->replytoaddr)) {
				$this->CI->email->reply_to($msgdata->replytoaddr);				
			}
			$this->CI->email->subject($msgdata->subject);
			$this->CI->email->message($thismessage);
			$this->CI->email->send();

			sleep($msgdata->loopdelay);			
		}

		$this->resetqueue();
	}
	
	function getstatus() {
		if (file_exists($this->config['pmdatadir'] . $this->config['domain'] . "-pmstatus.tmp")) {
			return file_get_contents($this->config['pmdatadir'] . $this->config['domain'] . "-pmstatus.tmp");			
		}
	}
}


?>