Personalized Mailer
===================

Personalized Mailer is a mass emailing CodeIgniter Spark with support for generating personalized messages to each user in your list. This Spark is designed to send to high volume lists, with support for proper SMTP server friendly throttling...

Features
--------

- Personalized emails including support for variable substitutions (e.g. Hello [firstname] [lastname]...) 
- SMTP server friendly system for running on the command line with intentional and configurable throttles, minimizing problems when sending mail to extremely high volume lists
- Support for both plain text and HTML messages, as well as all other mail configuration parameters found in CodeIgniter's email class
- AJAX progress bar/status support

How it Works
------------

Message queues are initialized via a CodeIgniter-based web interface, and the mail sending job is initialized via the command line (or cronjob). Being processed on the command line the job can take as long as necessary. Generally speaking, it is problematic to invoke heavy jobs directly through a web interface because they are subject to timeouts, or a lack of feedback confusing the user and prompting them to resubmit their request. While mail is being sent, AJAX calls can be made to check on the progress of the queue. 

Installation of the Web-based Interface
---------------------------------------

The web-based interface is used for initializing queues containing all of the necessary details about the job that are picked up on the command line. To establish a queue:

	$this->load->spark('PersonalizedMailer/1.0.0');
	$this->load->library('personalizedmailer', array(
		'pmdatadir' => '/path/to/working/dir',
		'domain' => 'sometoken'
	));

Set *pmdatadir* to some directory your web server can write to, and be sure to secure this directory so that it cannot be accessed by unwanted individuals by setting it with appropriate Unix permissions (e.g. chmod 600). Set *domain* to some sort of identifying token used to differentiate your site from others that might be using this Spark and sharing the same pmdatadir. These two commands will load the Spark and instantiate it with some basic environmental settings.

Initialize a queue:

	$this->personalizedmailer->initqueue(array(
		'addresses' => $addresses,
		'msgtemplate' => $msgtemplate,
		'subject' => 'some subject',
		'fromname' => 'Test Person',
		'fromaddr' => 'test@test.com',
		'HTML' => true,
		'loopdelay' => 0.75,
		'varsearch' => array('[name]', '[group]'),
		'varreplace' => array($names, $groups)
	));
	
*$addresses* can be pulled from a database, flat file, or provided to this function as a hard-coded array - this is up to you. The same applies for *$msgtemplate* - this is your basic message template plain text or HTML. *$loopdelay* should be set to an appropriate value (in seconds) to delay each loop iteration, creating an appropriate throttle. If you set this value to 0 you are going to spit out messages as fast as your server can handle, which SMTP server operators generally do not appreciate. Even if your SMTP server is capable at processing messages at this rate, the receiving server may not be able to and may black/blocklist your server as a consequence. 

*$varsearch* and *$varreplace* are used as search and replace lists to find variables in your template and replace them with appropriate values. The *$varsearch* list is a simple listing of the variables to search for, and the *$varreplace* arrays are respective listings of the replacements for these variables. **There should be the same number of variable replacements as there are addresses in your list**. If there aren't, an error message will be generated preventing your queue from being initialized.

Other Possible Initialization Variables
---------------------------------------

The above initqueue() function also supports all email preferences supported by the CodeIgniter email class (see [Email Preferences](http://codeigniter.com/user_guide/libraries/email.html)). To provide these configuration arguments, simply pass them on in your initqueue() call with key *ciemailconfig*. For example:

	$this->personalizedmailer->initqueue(array(
		'addresses' => $addresses,
		[snipped]...
		'ciemailconfig' => array(
			'smtp_port' => 587,
			'useragent' => 'My Company Mailer'
		)
	));

Example usage of variable replacements
--------------------------------------

	$addresses = array(
		'fred@test.com',
		'john@test.com',
		'jane@test.com',
		'kim@test.com'
	);

	$msgtemplate = "<p>Hello [name],

	This is an <strong>HTML</strong> message. You have been assigned to group: [group]</p>";
	
	$names = array(
		'Test Person 1',
		'Test Person 2',
		'Test Person 3',
		'Test Person 4'
	);
	
	$groups = array(
		'A',
		'C',
		'B',
		'A'
	);
	
	$this->personalizedmailer->initqueue(array(
		'addresses' => $addresses,
		'msgtemplate' => $msgtemplate,
		'subject' => 'Test Message',
		'fromname' => 'Test Person',
		'fromaddr' => 'support@test.com',
		'HTML' => true,
		'loopdelay' => 0.75,
		'varsearch' => array('[name]', '[group]'),
		'varreplace' => array($names, $groups)
	));

This will generate mailings as follows:

message 1 to fred@test.com:

	<p>Hello Test Person 1,

	This is an <strong>HTML</strong> message. You have been assigned to group: A</p>
	
message 2 to john@test.com:

	<p>Hello Test Person 2,

	This is an <strong>HTML</strong> message. You have been assigned to group: C</p>

**It is important that your variables are listed in the same order as your addresses. In other words, the first item in *$names* will be applied to the first address in *$addresses*, the second item in *$names* will be applied to the second address in *$addresses*, etc.**

Verifying Your Messages Are Being Queued
----------------------------------------

Check your *$pmdatadir* directory, and you will see the following files:

- *[domain]-pmdata.txt*: contains the init settings set via the initqueue() call
- *[domain]-pmtemplate.txt*: contains your message template (with unsubstituted variables)
- *[domain]-pmqueue.run*: lock file used to indicate that a queue has been initialized
- *[domain]-pmstatus.tmp*: JSON string containing info about the status of the queue, used for AJAX calls (see below)

If you are seeing your *[domain]-pmqueue.run* file, your queue has been successfully initialized!

Processing Your Queues
----------------------

In order to process your queues you will need to create a function in a controller of your choice as follows:

	public function sendtolist() {
		if ( !isset($_SERVER['argv'])) exit('CLI access only');				

		$this->personalizedmailer->sendtolist();
	}
	
(you will need to have loaded your Spark using the *$this->load->spark* and *$this->load->library* commands provided in the *Installation of the Web-based Interface* section, above)

If you wish to see the output of some of your queued messages without actually sending any mail or flushing/resetting your queue you can view some of these messages either within a web browser or via the command line by invoking the *sendtolisttest()* function *$this->personalizedmailer->sendtolisttest($cli, $limit)*. This function accepts two arguments, the first a boolean variable indicating whether output should be displayed in the command line (where newlines are rendered as \n) - setting this to false renders newlines as HTML break tags, and the second argument indicates how many queued messages should be displayed. If the second argument is omitted all messages will be displayed.

If you wish to fetch the status of your mail sending job useful for generating progress bars by making AJAX calls via a Javascript *setInterval* command, create the following function in this same controller:

 	public function pmcheck() {
		$status = $this->personalizedmailer->getstatus();
		if (!$status) {
			$this->session->set_flashdata('alert', 'Your message has been sent!');
		}
		else {
			print $status;
		}
	}
	
Let's say that your controller is called *admin*. If your AJAX calls to */index.php/admin/pmcheck* do not yield any output (i.e. *$status* has no value), we assume that the job is done. Your Javascript function can act accordingly, for example reload the page and display the flash data "Your message has been sent!"

On the command line, as a user with writable access to your *$pmdatadir* (such as root) run:

	php /path/to/your/codeigniter/root/index.php admin sendtolist
	
where *admin* is the name of your controller and *sendtolist* is the function inside this controller, set above. If you will be invoking this via a cronjob and don't want the verbose output and/or PHP alerts and notifications, you can run the command as follows:

	php /path/to/your/codeigniter/root/index.php admin sendtolist --silent
	
AJAX Progress Bars/Status
-------------------------

With the *pmcheck* function configured, above, your AJAX calls to this URL will return a JSON string which you can parse with any JSON parser. The resulting properties in this JSON object will include:

- *total*: total number of messages in the list
- *queueset*: set to 1 if the queue has been set
- *lastaddr*: the last email address sent to at the time of this AJAX call
- *messagenum*: the message number
- *progress*: the progress expressed as a percentage

You can combine the *messagenum* and *total* variables into a string to generate your own outputs such as "processed 3845 of 12042"

ChangeLog
---------

1.0.2

- added sendtolisttest() function for showing the message contents of each personalized message that has been queued without actually sending mail or flushing the queue

1.0.3

- bumped CI compatability to 2.0.3, added hr tag to sendtolisttest for greater readability
