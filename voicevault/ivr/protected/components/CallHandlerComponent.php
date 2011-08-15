<?php

if (!defined('DEFAULT_VOICE_TIMEOUT'))
	define('DEFAULT_VOICE_TIMEOUT', 5); 
if (!defined('DEFAULT_VOICE_ATTEMPTS'))
	define('DEFAULT_VOICE_ATTEMPTS', 3); 

/**
 * Call handler component. Extend this class to implement another communications
 * provider. All class methods are standardized, so that the call controller
 * can call the same methods no matter what provider is used.
 */
abstract class CallHandlerComponent extends CComponent
{
	
	/**
	 * @var array $_events Array of events. The following events should be
	 * 	included as keys (with the base link as value e.g. /index.php):
	 * 	- continue
	 * 	- hangup
	 * 	- timeout
	 * 	- error
	 */
	protected $_events = array("continue"=>null, 
		"incomplete"=>null,
		"error"=>null);
	
	/**
	 * @var File $_logger File object to log data to.
	 */
	private $_logger;
	
	/**
	 * Simple say method to say text to the caller.
	 *
	 * @param string $say_text Say text, either as a string or compatible
	 * 	voice file.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	abstract public function say($say_text, $callbacks=array());
	
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
	abstract public function hold($say_text, $callbacks=array(), $signals=array());
	
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
	abstract public function menu($menu_text, $callbacks=array(), $timeout=DEFAULT_VOICE_TIMEOUT,
								  $attempts=DEFAULT_VOICE_ATTEMPTS);
	
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
	abstract public function question($question, $choices, $callbacks=array(), 
		$attempts=DEFAULT_VOICE_ATTEMPTS, $timeout=DEFAULT_VOICE_TIMEOUT, $mode='dtmf',
		$terminator='#');
	
	/**
	 * Transfers to the specified transfer to number.
	 *
	 * @param string $transfer_to Valid number to transfer to. Could potentially
	 * 	be anything, for instance a US phone number or SIP address.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	abstract public function transfer($transfer_to, $callbacks=array());
	
	/**
	 * Connects the caller to a conference.
	 *
	 * @param string $conference_id Conference ID to connect to.
	 * @param array $callbacks Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	abstract public function conference($conference_id, $callbacks=array());
	
	/**
	 * Registers events.
	 *
	 * @param array $events Callbacks with specific events.
	 * 	{@see self::$_events}
	 */
	abstract public function registerEvents($events);

	/**
	 * Renders output.
	 *
	 * @return null
	 */
	abstract public function output();
	
	/**
	 * Returns the caller ID from the person calling.
	 *
	 * @return string Caller's caller id.
	 */
	abstract public function getCallerID();

	/**
	 * Returns the external session ID.
	 *
	 * @return string Session id.
	 */
	abstract public function getSessionID();

	/**
	 * Returns a list of headers.
	 *
	 * @return array Headers.
	 */
	abstract public function getHeaders();

	/**
	 * Gets the last result inputted from the user. (Used on callback of
	 * 	ask command).
	 *
	 * @return mixed Last inputted data.
	 */
	abstract public function getResult();

}
