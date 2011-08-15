<?php

define('UPLOADS_DIR', dirname(__FILE__).'/../../../data');

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

class EnrollController extends Controller
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
		try {
			$this->_handler = new TropoCallHandlerComponent;
		} catch (TropoException $e) {
			//
		}

		$this->_vault = new VoiceVaultComponent(
			Yii::app()->params['username'],
			Yii::app()->params['password'],
			Yii::app()->params['organisation_id'],
			Yii::app()->params['config_id'],
			true
		);

		return parent::beforeAction($action);
	}

	public function actionInbound()
	{
		$callerID = Yii::app()->session['caller_id'];

		$account = VoiceVaultAccounts::model()->find('caller_id = ?', 
			array($callerID)
		);

		if (!$account) {
			$this->log('new');

			$claimantResult = $this->_vault->RegisterClaimant();

			if ($claimantResult->status_code == 0) {
				$this->log('Claimant success.');
				
				$claimantID = strval($claimantResult->claimant_id);

				Yii::app()->session['claimant_id'] = $claimantID;

				// Start dialog
				$dialogueResult = $this->_vault->StartDialogue($claimantID,
					$callerID
				);

				if ($dialogueResult->status_code <> 0) {
					$this->log('Dialogue Failed');
					$this->log($dialogueResult);

					$this->_handler->say("Could not start the dialogue request.");
					$this->_handler->say(strval($dialogueResult->message));
					$this->_handler->output();
					return;
				}

				Yii::app()->session['prompt'] = strval($dialogueResult->prompt_hint);
				Yii::app()->session['dialogue_id'] = strval($dialogueResult->dialogue_id);

				$this->_handler->registerEvents(
					array('continue'=>$this->createUrl('prompt'))
				);

			} else {
				$this->log('Claimant Failed');
				$this->log($claimantResult);

				$this->_handler->say("Could not register a claimant ID.");
			}
		} else {
			$this->_handler->say("Welcome back. You are already enrolled in the system.");
		}

		$this->_handler->output();
	}

	public function actionPrompt()
	{
		// Ask for verification phrase
		$this->_handler->record(
				"<speak>Hello and welcome to Voice Vault enrollment. "
				."Please say the following prompt, followed by the pound sign: "
				."<say-as interpret-as='vxml:digits'>"
					.Yii::app()->session['prompt']
				."</say-as></speak>",
				$this->createAbsoluteUrl('recordfinish').'&sessionID='.session_id(),
				array(
					'continue'=>$this->createUrl('processprompt')
					)
				);

		$this->_handler->output();
	}

	public function actionProcessPrompt() 
	{
		// Upload file to vault
		$submitResult = $this->_vault->SubmitPhrase(
			UPLOADS_DIR.'/'.session_id().'.wav',
			Yii::app()->session['prompt'],
			Yii::app()->session['dialogue_id']
		);

		// Remove file
		unlink(UPLOADS_DIR.'/'.session_id().'.wav');

		if ($submitResult->status_code == 0) {
			Yii::app()->session['prompt'] = strval($submitResult->prompt_hint);

			$this->log('Phrase submitted');
			$this->log($submitResult);

			if ($submitResult->dialogue_status == 'Started') {
				switch ($submitResult->request_status) 
				{
					case 'OK':
						$this->_handler->registerEvents(
							array('continue'=>$this->createUrl('prompt'))
						);
						break;
					case 'TooManyUnprocessedPhrases':
						// App is currently processing, call GetDialogueSummary
						$this->_handler->registerEvents(
							array('continue'=>$this->createUrl('dialoguesummary'))
						);
						break;
					
					default:
						// ERROR
						die(var_dump($submitResult));
						$this->_handler->say("Invalid request status.");
						$this->_handler->output();
						return;
						break;
				}
			} else if ($submitResult->dialogue_status == 'Succeeded') {
				$this->log('Dialogue done!');

				// Enrollment succeeded
				$this->_handler->registerEvents(
					array('continue'=>$this->createUrl('done'))
				);
			}
			$this->_handler->say("Prompt upload successful.");
		} else {
			$this->log('Phrase Submission Failed');
			$this->log($submitResult);

			$this->_handler->say("Invalid result.");
			$this->_handler->output();
			return;
		}

		$this->_handler->output();
	}

	public function actionDialogueSummary()
	{
		$dialogueStatus = $this->_vault->GetDialogueSummary(
			Yii::app()->session['dialogue_id']
		);

		$this->log($dialogueStatus);

		if ($dialogueStatus->status_code == 0) {
			switch ($dialogueStatus->dialogue_status) 
			{
				case 'Started':
					if ($dialogueStatus->request_status == 'OK') {
						Yii::app()->session['prompt'] =
							strval($dialogueStatus->prompt_hint);

						// More phrases are needed
						$this->log('More phrases needed.');
						$this->_handler->registerEvents(
							array('continue'=>$this->createUrl('prompt'))
						);
					} else if ($dialogueStatus->request_status ==
							'TooManyUnprocessedPhrases') {
						// Continue to poll dialogue summary
						$this->_handler->registerEvents(
							array('continue'=>$this->createUrl('dialoguesummary'))
						);
					} else {
						// Error
						// $this->log('Invalid Dialogue Summary');
						// $this->log($dialogueStatus);
					}
					break;

				case 'Succeeded':
					// Enrollment complete
					$this->_handler->registerEvents(
						array('continue'=>$this->createUrl('done'))
					);
					break;
				
				default:
					// Error
					// $this->log('Invalid Dialogue Summary');
					// $this->log($dialogueStatus);
					break;
			}
		} else {
			// $this->log('Invalid Dialogue Summary');
			// $this->log($dialogueStatus);
		}
		$this->_handler->output();
	}

	public function actionDone()
	{
		// Store the claimant in our system
		$account = new VoiceVaultAccounts;
		$account->caller_id = Yii::app()->session['caller_id'];
		$account->external_id = Yii::app()->session['claimant_id'];

		// Generate a unique account ID
		$accountID = rand(1000, 10000);
		while (($accountVerify =
				VoiceVaultAccounts::model()->find('account_id = ?',
					array($accountID))
				)) {
			$accountID = rand(1000, 10000);
		}

		$account->account_id = $accountID;
		
		if (!$account->save()) {
			die(var_dump($account->attributes));
		}

		$this->_handler->say("<speak>Finished with Voice Vault enrollment. "
			."Your account ID is: "
			."<say-as interpret-as='vxml:digits'>"
				.$account->account_id
			."</say-as></speak>");
		$this->_handler->output();
	}

	public function actionRecordFinish()
	{
		$sessionID = $_GET['sessionID'];
		move_uploaded_file($_FILES['filename']['tmp_name'], UPLOADS_DIR."/$sessionID.wav");
	}

	/**
	 * helper function for yii logs
	 */
	private function log($inputMsg)
	{
		// if (is_string($inputMsg) && $inputMsg == 'new') {
			// $callerID = $this->_handler->getCallerID();
			// Yii::app()->session['log_caller'] = $callerID;
			// $msg = "New enrollment for $callerID";
			// Yii::log($msg);
			// return;
		// }
		// $msg = "\t".Yii::app()->session['log_caller'];
		// if (is_string($inputMsg)) {
			// $msg .= ": ".$inputMsg;
		// } else {
			// $msg .= ": ".var_export($inputMsg, true);
		// }
		// Yii::log($msg);
	}
}
