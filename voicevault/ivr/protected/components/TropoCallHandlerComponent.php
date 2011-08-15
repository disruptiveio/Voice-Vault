<?php

Yii::import('application.extensions.tropo.*');
require_once 'tropo.class.php';

class TropoCallHandlerComponent extends CallHandlerComponent
{
	/**
	 * @var Tropo Tropo WebAPI class 
	 *  https://www.tropo.com/docs/webapi/
	 */
	private $_tropo;
	
	/**
	 * @var say text
	 */
	private $_say_text;
	
	/**
	 * Tropo session
	 *
	 * @var Session
	 **/
	private $_session;
	
	/**
	 * Tropo signal URL.
	 */
	private $_signal_url = "https://api.tropo.com/1.0/sessions/<session_id>/signals";

	public function __construct()
	{
		$this->_tropo = new Tropo;
		// Error handling (for Debugging)
		try {
		try {
			// If there is not a session object in the POST body,
			// then this isn't a new session. Tropo will throw
			// an exception, so check for that.
			$this->_session = new Session();
			Yii::app()->session['tropo-session'] = file_get_contents("php://input");
		} catch (TropoException $e) {
			// This is a normal case, so we don't really need to 
			// do anything if we catch this.
			$this->_session = new Session(
				Yii::app()->session['tropo-session']);
		}
		} catch (TropoException $e) {
			// nothing
		}
	}

	/**
	 * Simple say method to say text to the caller.
	 *
	 * @param string $say_text Say text, either as a string or compatible
	 * 	voice file.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	public function say($say_text, $callbacks=array())
	{
		$this->registerEvents($callbacks);
		if (!$this->_say_text)
			$this->_say_text = $say_text;
		else
			$this->_say_text .= ' '.$say_text;
	}
	
	/**
	 * Simple hold method to say text to the caller, and put them on hold
	 * 	indefinitely until a signal in $signals is hit.
	 *
	 * @param string $say_text Say text, either as a string or compatible
	 * 	voice file.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 * @param array $signals List of signals to recognize. This is used in
	 * 	Tropo's REST API {@link https://www.tropo.com/docs/rest/redirecting_app_based_on_signal.htm}
	 */
	public function hold($say_text, $callbacks=array(), $signals=array())
	{
		$this->registerEvents($callbacks);
		if ($this->_say_text) {
			$say_text = $this->_say_text . ' ' . $say_text;
			$this->_say_text = null;
		}
		$options = array(
			'allowSignals'=>$signals,
		);
		$this->_tropo->say($say_text, $options);
	}
	
	/**
	 * Simple menu, allowing 1 digit of user input.
	 *
	 * @param string $say_text Say text, either as a string or compatible
	 * 	voice file.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 * @param integer $timeout Timeout in seconds.
	 * @param integer $attempts Number of attempts until ask command fails.
	 */
	public function menu($menu_text, $callbacks=array(), $timeout=DEFAULT_VOICE_TIMEOUT,
						 $attempts=DEFAULT_VOICE_ATTEMPTS)
	{
		$this->registerEvents($callbacks);
		// Combine the say text with the menu text, so a user can 
		// "barge in" on the menu at any time.
		if ($this->_say_text) {
			$menu_text = $this->_say_text . ' ' . $menu_text;
			$this->_say_text = null;
		}
		$options = array(
			'choices'=>'[1 DIGIT]',
			'mode'=>'dtmf',
			'bargein'=>true,
			'timeout'=>$timeout,
			'attempts'=>$attempts,
			'minConfidence'=>50
		);
		$this->_tropo->ask($menu_text, $options);
	}
	

	/**
	 * Input menu, allowing specified user input.
	 *
	 * @param string $question Say text, either as a string or compatible
	 * 	voice file.
	 * @param array|string $choices A compatible choices object, usually an array
	 * 	or string.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 * @param integer $attempts Number of attempts until ask command fails.
	 * @param integer $timeout Timeout in seconds.
	 * @param string $mode Mode. For tropo, this is either dtmf or voice or any.
	 * @param string $terminator Terminator string.
	 */
	public function question($question, $choices, $callbacks=array(), 
		$attempts=DEFAULT_VOICE_ATTEMPTS, $timeout=DEFAULT_VOICE_TIMEOUT, $mode='dtmf',
		$terminator='#')
	{
		$this->registerEvents($callbacks);
		// Combine the say text with the menu text, so a user can 
		// "barge in" on the menu at any time.
		if ($this->_say_text) {
			$question = $this->_say_text . ' ' . $question;
			$this->_say_text = null;
		}
		$options = array(
			'choices'=>$choices,
			'bargein'=>true,
			'timeout'=>$timeout,
			'attempts'=>$attempts,
			'terminator'=>$terminator,
			'mode'=>$mode,
			'minConfidence'=>50
		);
		$this->_tropo->ask($question, $options);
	}
	
	/**
	 * Transfers to the specified transfer to number.
	 *
	 * @param string $transfer_to Valid number to transfer to. Could potentially
	 * 	be anything, for instance a US phone number or SIP address.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	public function transfer($transfer_to, $callbacks=array())
	{
		$this->registerEvents($callbacks);
		// Say any text in the queue
		if ($this->_say_text)
		{
			$this->_tropo->say($this->_say_text);
			$this->_say_text = null;
		}

		$this->_tropo->transfer($transfer_to);
	}
	
	/**
	 * Connects the caller to a conference.
	 *
	 * @param string $conference_id Conference ID to connect to.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	public function conference($conference_id, $callbacks=array())
	{
		$this->registerEvents($callbacks);
		
		$this->_tropo->conference($conference_id,
					  array('id'=>$conference_id));
	}
	
	/*
	 * Records a message for the caller.
	 *
	 * @param string $say_text Say text, either as a string or compatible
	 * 	voice file.
	 * @param string $url The URL to upload the recording to.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 * @param array|string $terminator A compatible choices object, usually an array
	 * 	or string, that is specified as the terminator for the record method.
	 * @param string $format The format of the recording. Either audio/wav or audio/mp3
	 * @param string $method The HTTP method to upload the recording.
	 */
	public function record($say_text, $url, $callbacks, $terminator='',
						   $format='audio/wav', $method='POST')
	{
		$this->registerEvents($callbacks);
		
		return $this->_tropo->record(array(
			'say'=>$say_text,
			'as'=>null,
			'voice'=>null,
			'format'=>$format,
			'url'=>$url,
			'method'=>$method,
			'choices'=>$terminator,
			"attempts"=>null,
			"allowSignals"=> null,
			"bargein"=> null,
			"beep"=> null,
			"maxSilence"=> null,
			"maxTime"=> null,
			'minConfidence'=>50,
			"name"=> null,
			"required"=> null,
			"transcription"=> null,
			"password"=> null,
			"username"=> null
		));
	}
	
	/**
	 * Send a signal (interrupt).
	 *
	 * @param string $sessionID Tropo sessionID to interrupt.
	 * @param string $signal Signal to send.
	 */
	public function sendSignal($sessionID, $signal)
	{
		$signal_url = str_replace("<session_id>", $sessionID, $this->_signal_url);
		$signal_params = "action=signal&value=$signal";
		$signal_uri = "$signal_url?$signal_params";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $signal_uri);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		return curl_exec($ch);
	}
	
	/**
	 * Registers events.
	 *
	 * @param array $events Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	public function registerEvents($events)
	{
		if (!empty($events))
			$this->_events = array_merge($this->_events, $events);
	}

	/**
	 * Renders output.
	 *
	 * @return null
	 */
	public function output()
	{
		if ($this->_say_text)
			$this->_tropo->say($this->_say_text);

		// Process events
		if (!empty($this->_events)) {
			foreach ($this->_events 
				as $eventName => $eventNext) {
				$this->_tropo->on(array('event'=>$eventName,
					'next'=>$eventNext));
			}
		}

		$this->_tropo->renderJSON();
	}
	
	/**
	 * Call a US phone number, and format it if it isn't already.
	 */
	public function call($phoneNumber)
	{
		if (strpos($phoneNumber, '@') === false)
		{
			if (strlen($phoneNumber) == 10)
				$phoneNumber = '1'.$phoneNumber;
			if (strpos($phoneNumber, '+') === false)
				$phoneNumber = '+'.$phoneNumber;
		}
		else
		{
			if (strpos($phoneNumber, 'sip:') === false)
				$phoneNumber = 'sip:'.$phoneNumber;
		}
		
		$this->_tropo->call($phoneNumber);
	}

	/**
	 * Returns the caller ID from the person calling.
	 *
	 * @return string Caller's caller id.
	 */
	public function getCallerID()
	{
		$caller = $this->_session->getFrom();
		return $caller['id'];
	}

	/**
	 * Returns the external session ID.
	 *
	 * @return string Session id.
	 */
	public function getSessionID()
	{
		return $this->_session->getId();
	}
	
	/**
	 * Gets a parameter from the call.
	 *
	 * @param string $param The parameter name.
	 * @return string The parameter value.
	 */
	public function getParameter($param)
	{
		return $this->_session->getParameters($param);
	}

	/**
	 * Returns a list of headers.
	 *
	 * @return array Headers.
	 */
	public function getHeaders()
	{
		return $this->_session->getHeaders();
	}

	/**
	 * Gets the last result inputted from the user. (Used on callback of
	 * 	ask command).
	 *
	 * @return mixed Last inputted data.
	 */
	public function getResult()
	{
		$result = @new Result();
		return @$result->getValue();
	}

}
