<?php

/**
 * Checks if this a valid call.
 *
 * Only does a simple plaintext check of the "pass" GET parameter. This function
 * might be improved in the future.
 */
function validCall() {
	$pass = isset($_GET['pass']) ? $_GET['pass'] : null;
	$type = isset($_GET['type']) ? $_GET['type'] : null;
	if (!$pass)
	{
		// Check in session for password
		if (isset(Yii::app()->session['pass']))
			$pass = Yii::app()->session['pass'];
	}
	else
	{
		// Set session values, for easier redirects
		// Set session password
		Yii::app()->session['pass'] = $pass;
		// Set session type
		Yii::app()->session['type'] = $type;
	}
	return $pass == CALL_PASSWORD;
}

class CallController extends Controller
{
	/**
	 * @var CallHandlerComponent $_handler The call handler.
	 */
	private $_handler;

	private $_vault;
	
	/**
	 * Access filters for controller
	 *
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl -recordfinish', // perform basic access control
		);
	}

	/**
	 * Access rules.
	 *
	 * @global function $validCall Checks if a call is a valid tropo call
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',
					'users'=>array('*'),
					'expression'=>'validCall()'
			),
			array('deny',  // deny all users
					'users'=>array('*'),
			),
		);
	}

	/**
	 * Prepare the calls for processing. Sets the call handler.
	 */
	public function beforeAction($action)
	{
		$this->_handler = new TropoCallHandlerComponent;

		$this->_vault = new VoiceVaultComponent(
			'DevMichaelMackus',
			'u458N3a6FF9eX2qs',
			'ad93d03d-c28b-4ca3-9af7-6a8fe18e9695',
			'f8e6f876-54da-4bc1-ae4c-2129cfd5b6e8',
			true
		);

		return parent::beforeAction($action);
	}

	public function actionInbound()
	{
		// Debugging
		if (isset($_GET['caller_id'])) {
			$callerID = $_GET['caller_id'];
		// Normal execution (from Tropo)
		} else {
			$callerID = $this->_handler->getCallerID();
		}
		Yii::app()->session['caller_id'] = $callerID;
		
		// Redirect the user to either enrollment, or verification
		
		// Debugging
		if (isset($_GET['phone_number'])) {
			$phoneNumberToCall = $_GET['phone_number'];
		}
		// Normal execution (from Tropo)
		else {
			$phoneNumberToCall = @$this->_handler->getParameter('phone_number');
		}
		
		if ($phoneNumberToCall) {
			// Initiated via rest API
			
			// Set the caller ID
			Yii::app()->session['caller_id'] = $phoneNumberToCall;
			
			// Call and send to enrollment
			$this->_handler->call("+1$phoneNumberToCall");
			$this->_handler->registerEvents(array('continue'=>$this->createUrl('/VoiceVault/enroll/inbound')));
		} else {
			// Inbound call
			$this->_handler->registerEvents(array('continue'=>$this->createUrl('/VoiceVault/verify/inbound')));
		}

		$this->_handler->output();
	}
}
