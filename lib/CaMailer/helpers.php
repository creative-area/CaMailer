<?php

/**
 * Send an email
 *
 * @param string $from
 * @param string $to
 * @param string $html_message
 * @param string $txt_alternative
 * @param string|array $cc_email
 * @param string|array $bcc_email
 * @param array  $arr_pj
 *
 * @return booleen
 *
 */
function sendMail($from, $to, $subject, $html_message, $txt_alternative = null, $cc_email = null, $bcc_email = null, $arr_pj = array()) {

	global $mail_config;
		
	$mail_options = array(
		'host' => setConfigType($mail_config['smtp_host']),
		'port' => setConfigType($mail_config['smtp_port'], 'int'),
		'auth' => setConfigType($mail_config['smtp_auth'], 'bool'),
		'username' => setConfigType($mail_config['smtp_username']),
		'password' => setConfigType($mail_config['smtp_password']),
		'localhost' => setConfigType($mail_config['smtp_localhost']),
		'timeout' => setConfigType($mail_config['smtp_timeout'], 'int'),
		'persist' => setConfigType($mail_config['smtp_persist'], 'bool'),
		'pipelining' => setConfigType($mail_config['smtp_pipelining'], 'bool'),
		'debug' => setConfigType($mail_config['smtp_debug'], 'bool')
	);	

	$mail = Mail::factory($mail_config['driver'], $mail_options);

	$recipients = array();
	if (is_array($to)) {
		foreach($to as $to_item) {
			$recipients[] = $to_item;
		}
	} else {
		$recipients[] = $to;
	}
	if (!empty($cc_email)) {
		if (is_array($cc_email)) {
			foreach($cc_email as $cc) {
				$recipients[] = $cc;
			}
		} else {
			$recipients[] = $cc_email;
		}
	}
	if (!empty($bcc_email)) {
		if (is_array($bcc_email)) {
			foreach($bcc_email as $bcc) {
				$recipients[] = $bcc;
			}
		} else {
			$recipients[] = $bcc_email;
		}
	}

	$mail_mime = getMailMime($from, $to, $subject, $html_message, $txt_alternative, $cc_email, $bcc_email, $arr_pj);
	if (PEAR::isError($mail_mime)) {
		return false;
	} else {
		$test = $mail->send($recipients, $mail_mime['headers'], $mail_mime['body']);
		if (PEAR::isError($test)) {
			return false;
		} else {
			return true;
		}
	}

}

/**
 * Send Mail To Database Pool
 *
 * @param string $from
 * @param string $to
 * @param string $subject
 * @param string $html_message
 * @param string $txt_alternative
 * @param string|array $cc_email
 * @param string|array $bcc_email
 * @param array  $arr_pj
 *
 * @return boolean
 */
function sendMailToPool($from, $to, $subject, $html_message, $txt_alternative = null, $cc_email = null, $bcc_email = null, $arr_pj = array()) {

	global $mail_config, $db_config;
	
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
		'pipelining' => setConfigType($mail_config['smtp_pipelining'], 'bool'),
		'debug' => setConfigType($mail_config['smtp_debug'], 'bool')
	);
	
	$db_options = array(
		'type' => $mail_config['mail_queue_driver'],
		'dsn' => $db_config['phptype'].'://'.$db_config['username'].':'.$db_config['password'].'@'.$db_config['hostspec'].'/'.$db_config['database'],
		'mail_table' => $mail_config['mail_queue_table'],
	);
	
	$mail_queue = Mail_Queue::factory($db_options, $mail_options);
	if (PEAR::isError($mail_queue)) {
		return false;
	}

	$recipients = array();
	if (is_array($to)) {
		foreach($to as $to_item) {
			$recipients[] = strtolower($to_item);
		}
	} else {
		$recipients[] = strtolower($to);
	}
	if (!empty($cc_email)) {
		if (is_array($cc_email)) {
			foreach($cc_email as $cc) {
				$recipients[] = strtolower($cc);
			}
		} else {
			$recipients[] = strtolower($cc_email);
		}
	}
	if (!empty($bcc_email)) {
		if (is_array($bcc_email)) {
			foreach($bcc_email as $bcc) {
				$recipients[] = strtolower($bcc);
			}
		} else {
			$recipients[] = strtolower($bcc_email);
		}
	}

	$mail_mime = getMailMime($from, $to, $subject, $html_message, $txt_alternative, $cc_email, $bcc_email, $arr_pj);
	if (PEAR::isError($mail_mime)) {
		return false;
	} else {
		$test = $mail_queue->put($from, $recipients, $mail_mime['headers'], $mail_mime['body']);
		if (PEAR::isError($test)) {
			return false;
		} else {
			return true;
		}
	}

}

/**
 * Get Mail Mime
 *
 * @param string $from
 * @param string $to
 * @param string $subject
 * @param string $html_message
 * @param string $txt_alternative
 * @param string|array $cc_email
 * @param string|array $bcc_email
 * @param array  $arr_pj
 *
 * @return array|Pear_error
 */
function getMailMime($from, $to, $subject, $html_message, $txt_alternative = null, $cc_email = null, $bcc_email = null, $arr_pj = array()) {

	global $mail_config;

	$headers["From"] = $from;
	if (is_array($to)) {
		$headers["To"] = implode(",", $to);
	} else {
		$headers["To"] = $to;
	}
	if (!empty($cc_email)) {
		if (is_array($cc_email)) {
			$headers["Cc"] = implode(",", $cc_email);
		} else {
			$headers["Cc"] = $cc_email;
		}
	}
	$headers["Subject"] = $subject;
	$headers["Return-Path"] = $mail_config['mail_returnpath'];
	$headers["Error-To"] = $mail_config['mail_returnpath'];
	$headers["X-Mailer"] = "CaMailer";

	$mime = new Mail_mime;

	$test = $mime->setHTMLBody($html_message);
	if (PEAR::isError($test)) {
		return $test;
	}
	
	if (empty($txt_alternative)) {
		$txt_alternative = getAltMessage($html_message);
	}
	$test = $mime->setTXTBody($txt_alternative);
	if (PEAR::isError($test)) {
		return $test;
	}

	if (!empty($arr_pj)) {
		foreach ($arr_pj as $pj) {
			if ( is_array($pj) ) {
				$test = $mime->addAttachment($pj[0], $pj[1], $pj[2], $pj[3], $pj[4]);
			} else {
				$test = $mime->addAttachment($pj);
			}
			if (PEAR::isError($test)) {
				return $test;
			}
		}
	}

	$body = $mime->get(
		array(
			'html_charset' => $mail_config['mail_charset'],
			'text_charset' => $mail_config['mail_charset'],
			'head_charset' => $mail_config['mail_charset']
		)
	);
	$hdrs = $mime->headers($headers);

	return array('body' => $body, 'headers' => $hdrs);

}

/**
 * Build alternative plain text message
 *
 * @param $message
 *
 * @return string
 */
function getAltMessage( $message ) {

	if (preg_match('/\<body.*?\>(.*)\<\/body\>/si', $message, $match)) {
		$body = $match['1'];
	}
	else {
		$body = $message;
	}

	$body = trim(strip_tags( $body ));
	$body = preg_replace( '#<!--(.*)--\>#', "", $body );
	$body = str_replace( "\t", "", $body );

	for ($i = 20; $i >= 3; $i--) {
		$n = "";

		for ($x = 1; $x <= $i; $x ++) {
			$n .= "\n";
		}

		$body = str_replace( $n, "\n\n", $body );
	}

	return getWordWrap( $body, '76' );
	
}

/**
 * Word Wrap
 *
 * @param	string	$str
 * @param	integer	$charlim
 *
 * @return	string
 */
function getWordWrap( $str, $charlim = '76' ) {
	// Se the character limit
	if ( $charlim == '' ) {
		$charlim = "76";
	}

	// Reduce multiple spaces
	$str = preg_replace( "| +|", " ", $str );

	// Standardize newlines
	if ( strpos($str, "\r" ) !== FALSE ) {
		$str = str_replace( array("\r\n", "\r"), "\n", $str );
	}

	// If the current word is surrounded by {unwrap} tags we'll
	// strip the entire chunk and replace it with a marker.
	$unwrap = array();
	if ( preg_match_all( "|(\{unwrap\}.+?\{/unwrap\})|s", $str, $matches ) ) {
		for ( $i = 0; $i < count( $matches['0'] ); $i++ ) {
			$unwrap[] = $matches['1'][$i];
			$str = str_replace( $matches['1'][$i], "{{unwrapped".$i."}}", $str );
		}
	}

	// Use PHP's native public function to do the initial wordwrap.
	// We set the cut flag to FALSE so that any individual words that are
	// too long get left alone.  In the next step we'll deal with them.
	$str = wordwrap($str, $charlim, "\n", FALSE);

	// Split the string into individual lines of text and cycle through them
	$output = "";
	foreach (explode("\n", $str) as $line) {
		// Is the line within the allowed character count?
		// If so we'll join it to the output and continue
		if (strlen($line) <= $charlim) {
			$output .= $line."\n";
			continue;
		}

		$temp = '';
		while ((strlen($line)) > $charlim) {
			// If the over-length word is a URL we won't wrap it
			if (preg_match("!\[url.+\]|://|wwww.!", $line)) {
				break;
			}

			// Trim the word down
			$temp .= substr($line, 0, $charlim-1);
			$line = substr($line, $charlim-1);
		}

		// If $temp contains data it means we had to split up an over-length
		// word into smaller chunks so we'll add it back to our current line
		if ($temp != '') {
			$output .= $temp."\n".$line;
		} else {
			$output .= $line;
		}

		$output .= "\n";
	}

	// Put our markers back
	if (count($unwrap) > 0) {
		foreach ($unwrap as $key => $val) {
			$output = str_replace("{{unwrapped".$key."}}", $val, $output);
		}
	}

	return $output;
}

/**
 * setConfigType
 *
 * @param	string	$value
 * @param	string	$type
 *
 * @return	mixed
 */
function setConfigType( $value, $type = "string" ) {

	switch( $type ) {
	
		case "int" :
			return (int) $value;
			break;
		
		case "bool" :
			return ( $value == "true" ) ? true : false;
			break;
		
		case "float" :
			return (float) $value;
			break;
		
		case "unset" :
			return null;
			break;
		
		default:
			return (string) $value;
			break;
		
	}
	
}