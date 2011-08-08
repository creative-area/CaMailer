<?php
// fatal run-time errors only
// These indicate errors that can not be recovered from, such as 
// a memory allocation problem. Execution of the script is halted.
error_reporting(1);

// path vars
$lib_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib';
$config_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'etc';

// set the include path
set_include_path(get_include_path() . PATH_SEPARATOR . $lib_path);

// pear dependencies
require_once 'Config.php';
require_once 'MDB2.php';
require_once 'Mail.php';
require_once 'Mail/mime.php';
require_once 'Mail/Queue.php';
require_once 'Mail/RFC822.php';
require_once 'Console/CommandLine.php';
require_once 'Console/CommandLine/Action.php';

// app dependencies
require_once 'CaMailer/helpers.php';

// custom commandline action class
class ActionParseAddressList extends Console_CommandLine_Action
{
	public function execute( $value = false, $params = array() )
	{
		$addresslist = Mail_RFC822::parseAddressList( $value );
		if ( PEAR::isError( $addresslist ) ) {
			throw new Exception( sprintf(
				'The given address string option "%s" is not RFC822 compliant',
				$this->option->name
			));
		}
		$this->setResult( $addresslist );
	}
}

// register the action
Console_CommandLine::registerAction( 'ParseAddressList', 'ActionParseAddressList' );

// get the configuration
$conf = new Config;
$xml =& $conf->parseConfig($config_path . DIRECTORY_SEPARATOR . 'camailercfg.xml', 'XML');
if (PEAR::isError($xml)) {
    die('Error while reading configuration: ' . $xml->getMessage());
}
$settings = $xml->toArray();
$db_config = $settings['root']['config']['db_config'];
$mail_config = $settings['root']['config']['mail_config'];

// create the parser from xml file
$xmlfile = $lib_path . DIRECTORY_SEPARATOR . 'CaMailer' . DIRECTORY_SEPARATOR . 'cmd.xml';
$parser  = Console_CommandLine::fromXmlFile($xmlfile);

// run the parser
try {

	$result = $parser->parse();
	
	// find which command was entered
	switch ( $result->command_name ) {
	
	// send command
	case 'send' :
	
		// mail from
		if ( !empty( $result->command->options['from'] ) ) {
			$from_adr = Mail_RFC822::parseAddressList( $result->command->options['from'] );
			if ( PEAR::isError( $from_adr ) ) {
				throw new Exception( 'The given address string option "from" is not RFC822 compliant' );
			}
			$from = trim( $from_adr[0]->personal . ' <' . $from_adr[0]->mailbox . '@' . $from_adr[0]->host . '>' );
		} else {
			$processuser = posix_getpwuid(posix_geteuid());
			$from = $processuser['name'] . ' <' . $processuser['name'] . '@' . php_uname('n') . '>';
		}
		
		// mail subject
		if ( !empty( $result->command->options['subject'] ) ) {
			$subject = $result->command->options['subject'];
		} else {
			$subject = "";
		}
		
		// mail cc
		if ( !empty( $result->command->options['cc'] ) ) {
			$cc_list = $result->command->options['cc'];
			$cc = array();
			foreach ( $cc_list as $email ) {
				$cc[] = trim( $email->personal . ' <' . $email->mailbox . '@' . $email->host . '>' );
			}
		} else {
			$cc = null;
		}
		
		// mail bcc
		if ( !empty( $result->command->options['bcc'] ) ) {
			$bcc_list = $result->command->options['bcc'];
			$bcc = array();
			foreach ( $bcc_list as $email ) {
				$bcc[] = trim( $email->personal . ' <' . $email->mailbox . '@' . $email->host . '>' );
			}
		} else {
			$bcc = null;
		}
		
		// html message ?
		if ( !empty($result->command->options['ishtml']) && $result->command->options['ishtml'] === true ) {
			$ishtml = true;
		} else {
			$ishtml = false;
		}
		
		// send mail in queue
		if ( !empty($result->command->options['queue']) && $result->command->options['queue'] === true ) {
			$queue = true;
		} else {
			$queue = false;
		}
		
		// mail to
		$to_list = Mail_RFC822::parseAddressList( $result->command->args['to'] );
		if ( PEAR::isError( $to_list ) ) {
			throw new Exception( 'The given address string option "to" is not RFC822 compliant' );
		}
		$to = array();
		foreach ( $to_list as $email ) {
			$to[] = trim( $email->personal . ' <' . $email->mailbox . '@' . $email->host . '>' );
		}
		
		// message file
		$message = $result->command->args['message'];
		if ( !file_exists( $message ) ) {
			throw new Exception( "Message file not found ($message)" );
		} else {
			$message_content = file_get_contents($message);
			if ( $ishtml ) {
				$html_message = $message_content;
				$txt_alternative = null;
			} else {
				$html_message = nl2br($message_content);
				$txt_alternative = $message_content;
			}
		}
		
		// attachments
		if ( !empty($result->command->args['attachments']) ) {
			$attachments = $result->command->args['attachments'];
			foreach ( $attachments as $file ) {
				if ( !file_exists( $file ) ) {
					throw new Exception( "Attachment not found ($file)" );
				}
			}
		} else {
			$attachments = array();
		}
		
		if ( $queue ) {
			sendMailToPool($from, $to, $subject, $html_message, $txt_alternative, $cc, $bcc, $attachments);
		} else {
			sendMail($from, $to, $subject, $html_message, $txt_alternative, $cc, $bcc, $attachments);
		}
		
		exit(0);
	
	// 	send mails in queue
	case 'queuesend' :
	
		$mail_options = array(
			'driver' => setConfigType($mail_config['driver']),
			'host' => setConfigType($mail_config['smtp_host']),
			'port' => setConfigType($mail_config['smtp_port'], 'int'),
			'auth' => setConfigType($mail_config['smtp_auth'], 'bool'),
			'username' => setConfigType($mail_config['smtp_username']),
			'password' => setConfigType($mail_config['smtp_password']),
			'localhost' => setConfigType($mail_config['smtp_localhost']),
			'timeout' => setConfigType($mail_config['smtp_timeout'], 'int'),
			'persist' => setConfigType($mail_config['smtp_persist'], 'bool'),
			'pipelining' => setConfigType($mail_config['smtp_pipelining'], 'bool')
		);
		
		$db_options = array(
			'type' => $mail_config['mail_queue_driver'],
			'dsn' => $db_config['phptype'].'://'.$db_config['username'].':'.$db_config['password'].'@'.$db_config['hostspec'].'/'.$db_config['database'],
			'mail_table' => $mail_config['mail_queue_table'],
		);
		
		$mail_queue = Mail_Queue::factory( $db_options, $mail_options );
		if ( PEAR::isError( $mail_queue ) ) {
			throw new Exception( $mail_queue->getMessage() );
		}
		
		$send_queue = $mail_queue->sendMailsInQueue( (int)$result->command->options['limit'] );
		if ( PEAR::isError( $send_queue ) ) {
			throw new Exception( $send_queue->getMessage() );
		}	
		
		exit(0);
	
	// create the mail_queue table	
	case "initdb" :
	
		// create MDB2 instance
		$mdb2 = MDB2::factory($db_config);
		if (PEAR::isError($mdb2)) {
		    throw new Exception( $mdb2->getMessage() );
		}
		
		// loading the Manager module
		$mdb2->loadModule('Manager');
		
		$fields = array(
			'id' => array(
				'type'     => 'integer',
				'length'   => 20,
				'unsigned' => true,
				'notnull'  => true,
			),
			'create_time' => array(
				'type'     => 'timestamp',
				'notnull'  => true,
				'default'  => '0000-00-00 00:00:00',
			),
			'time_to_send'  => array(
				'type'     => 'timestamp',
				'notnull'  => true,
				'default'  => '0000-00-00 00:00:00',
			),
			'sent_time'  => array(
				'type'     => 'timestamp',
			),
		    'id_user'  => array(
				'type'     => 'integer',
				'unsigned' => true,
				'notnull'  => true,
				'default'  => 0,
			),
			'ip'  => array(
				'type'     => 'text',
				'length'   => 20,
				'notnull'  => true,
				'default'  => 'unknown',
			),
			'sender'  => array(
				'type'     => 'text',
				'length'   => 255,
				'notnull'  => true,
			),
			'recipient'  => array(
				'type'     => 'clob',
				'notnull'  => true,
			),
			'headers'  => array(
				'type'     => 'clob',
			),
			'body'  => array(
				'type'     => 'clob',
			),
			'try_sent'  => array(
				'type'     => 'integer',
				'unsigned' => true,
				'notnull'  => true,
				'default'  => 0,
			),
			'delete_after_send'  => array(
				'type'     => 'boolean',
				'unsigned' => true,
				'notnull'  => true,
				'default'  => 0,
			)
		);
		
		$constraint = array (
			'primary' => true,
			'fields' => array (
				'id' => array()
			)
		);
		
		$time_to_send_index = array(
			'fields' => array(
		        'time_to_send' => array()
    		)
		);
		
		$id_user_index = array(
			'fields' => array(
		        'id_user' => array()
    		)
		);
		
		// create a table
		$mail_queue_table = $mdb2->createTable( $mail_config['mail_queue_table'], $fields );
		if ( PEAR::isError( $mail_queue_table) ) {
			throw new Exception( $mail_queue_table->getMessage() );
		}
		$mdb2->createConstraint( $mail_config['mail_queue_table'], 'id', $constraint);
		$mdb2->createIndex( $mail_config['mail_queue_table'], 'time_to_send', $time_to_send_index);
		$mdb2->createIndex( $mail_config['mail_queue_table'], 'id_user', $id_user_index);
		$mdb2->createSequence( $mail_config['mail_queue_table'], 1);
		
		exit(0);
	
	break;
	
	// no command entered
	default:
		exit(0);
	
	}
	
} catch ( Exception $exc ) {
	$parser->displayError( $exc->getMessage() );
}