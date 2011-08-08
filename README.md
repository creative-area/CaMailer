#CaMailer

__CaMailer__ is a small but powerful PHP command line based program for sending electronic mail. It's written in PHP and make use of some Pear packages. 

__CaMailer__ is mainly inspired by unix tools such as Mailx or Mutt.


## Main Features

-	Send emails directly or in database queue
-	Send HTML (with text fallback) or simple text message
-	Single/Multiple To, CC, BCC recipients
-	Support single/multiple attachments


## Requirements

-	Unix system at this time (Linux, OSX, etc...)
-	You need PHP 5.2.0 or newer \* with PHP CLI binary. 
-	MySQL 5.0 or newer (but should also work with previous versions).
-	A local or external SMTP server (Gmail for example).

\* CaMailer make use of many Pear libraries. Most of them are not PHP 5.3 ready yet. That's why we prevent strict and deprecated errors form displaying.


## Install

1.	Rename/copy _etc/camailercfg.sample.xml_ to _etc/camailercfg.xml_
2.	Fill _etc/camailercfg.xml_ with your own data		
3.	Init the queuing table in the database of your choice with the following command :
		
		{YOUR_PHP_BINARY_PREFIX}/php ./camailer.php initdb

	
## Usage

3 commands are available at this time. Here is the result of the main help call :

	{YOUR_PHP_BINARY_PREFIX}/php ./camailer.php --help
	
	./camailer.php [options]
	./camailer.php [options] <command> [options] [args]
	
	Options:
	  -h, --help     show this help message and exit
	  -v, --version  show the program version and exit
	
	Commands:
	  send       Send an email
	  queuesend  Send emails from queue
	  initdb     Create the necessary table in database for mail queuing
	  
	  
### "initdb" command :

	see installation above
	  

### "send" command :

	{YOUR_PHP_BINARY_PREFIX}/php ./camailer.php send --help

	Send an email

	./camailer.php send [options] <recipient mail to> <message file> <file attachment(s)...>

	Options:
	  -f from, --from=from           Specify the sender (From).
	  -s subject, --subject=subject  Specify the subject of the message.
	  -c cc, --cc-addr=cc            Send blind carbon copies to cc-addr list
	                                 of users. The cc-addr argument should be a
	                                 comma-separated list of names.
	  -b bcc, --bcc-addr=bcc         Send blind carbon copies to bcc-addr list
	                                 of users. The bcc-addr argument should be
	                                 a comma-separated list of names.
	  -H, --ishtml                   Specify if the message file is HTML
	                                 formatted
	  -q, --queue                    Queue the message in database.
	  -h, --help                     show this help message and exit
	
	Arguments:
	  recipient mail to   Specify the main recipient (To).
	  message file        Specify the path to the message file. Text format by
	                      default but could be HTML (see --ishtml option) 
	                      file attachment(s)  Attach file(s) to your message.


### "queuesend" command :

	{YOUR_PHP_BINARY_PREFIX}/php ./camailer.php queuesend --help

	Send emails from queue
	
	Usage:
	  ./camailer.php queuesend [options]
	
	Options:
	  -l limit, --limit=limit  Maximum number of mails to send.
	  -h, --help               show this help message and exit


## Examples

Send the content of test.txt to recipient@example.com

	./camailer.php send recipient@example.com ../test/test.txt
	
Send the content of test.html to recipient@example.com who's name is Chuck Norris with a "test" subject

	./camailer.php send -H -s test "Chuck Norris <recipient@example.com>" ../test/test.html

OK same as above but specify a sender (From) and attach 2 files to our email

	./camailer.php send -H -f "Florent Bourgeois <florent@example.com>" -s test "Chuck Norris <recipient@example.com>" ../test/test.html ../test/att_1.png ../test/att_2.png
	
But you maybe prefer queuing the mail in databasze. Simply add the "-q" or "--queue" option

	./camailer.php send -q -H -f "Florent Bourgeois <florent@example.com>" -s test "Chuck Norris <recipient@example.com>" ../test/test.html ../test/att_1.png ../test/att_2.png