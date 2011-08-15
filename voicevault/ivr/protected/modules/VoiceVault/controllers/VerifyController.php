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

class VerifyController extends Controller
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
		$callerID = $this->_handler->getCallerID();
		Yii::app()->session['caller_id'] = $callerID;

		$account = VoiceVaultAccounts::model()->find('caller_id = ?', 
			array($callerID)
		);

		if (!$account) {
			try {
				$result = $this->_handler->getResult();
			} catch (TropoException $e) {
				$result = null;
			}

			if (!$result) {
				// Ask the user for their pin
				$this->_handler->question(
					"Please enter your account I.D.",
					"[4-5 DIGITS]",
					array('continue'=>$this->createUrl('inbound'))
				);
			} else {
				$account = VoiceVaultAccounts::model()->find('account_id = ?',
					array($result)
				);
			}
		}

		if ($account) {
			// Get the claimant ID
			$claimantID = $account->external_id;
			Yii::app()->session['claimant_id'] = $claimantID;

			// Start the dialogue
			$dialogueResult = $this->_vault->StartDialogue($claimantID,
				$callerID
			);

			if ($dialogueResult->status_code <> 0) {
				$this->_handler->say("Could not start the dialogue request.");
				$this->_handler->output();
				return;
			} else {
				// $this->_handler->say("Dialogue successfully started.");
				// $this->_handler->output();
				// return;
				$this->_handler->registerEvents(
					array('continue'=>$this->createUrl('prompt'))
				);
			}

			Yii::app()->session['prompt'] = strval($dialogueResult->prompt_hint);
			Yii::app()->session['dialogue_id'] = strval($dialogueResult->dialogue_id);
		}

		Yii::app()->session['account_id'] = $account->account_id;

		$this->_handler->output();
	}

	public function actionPrompt()
	{
		// Ask for verification phrase
		$this->_handler->record(
				"<speak>Verify your account by saying the following prompt, "
				."followed by the pound sign: "
				."<say-as interpret-as='vxml:digits'>"
					.Yii::app()->session['prompt']
				."</say-as></speak>",
				$this->createAbsoluteUrl('recordfinish').'&sessionID='.session_id(),
				array('continue'=>$this->createUrl('processprompt'))
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

		$f = fopen('process.log', 'a');
		fwrite($f, var_export($submitResult, true));
		fwrite($f, "\n\n");
		fclose($f);

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
			} else if ($submitResult->dialogue_status == 'Failed') {
				// Enrollment failed
				$this->_handler->registerEvents(
					array('continue'=>$this->createUrl('failed'))
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

		$f = fopen('summary.log', 'a');
		fwrite($f, var_export($dialogueStatus, true));
		fwrite($f, "\n\n");
		fclose($f);

		$this->log($dialogueStatus);

		if ($dialogueStatus->status_code == 0) {
			switch ($dialogueStatus->dialogue_status) 
			{
				case 'Started':
					if ($dialogueStatus->request_status == 'OK') {
						Yii::app()->session['prompt'] = strval($dialogueStatus->prompt_hint);

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

				case 'Failed':
					// Verification failed
					$this->_handler->registerEvents(
						array('continue'=>$this->createUrl('failed'))
					);
				
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
		/** Generate a random password **/
		$passwordLength = 10;
		// Word
		$dictionary = explode("\n", trim(file_get_contents('../dictionary.txt')));
		$randomKey = array_rand($dictionary);
		$passwordWord = $dictionary[$randomKey];
		// Number
		$numberLength = rand(1, 4);
		$passwordNumber = '';
		for ($i=0; $i<$numberLength; $i++) {
			$passwordNumber .= rand(0, 9);
		}
		// Final password
		$newPassword = $passwordWord.$passwordNumber;

		/** Connect to WP DB **/
		mysql_connect(Yii::app()->params['wordpress_db']['host'], 
			Yii::app()->params['wordpress_db']['user'],
			Yii::app()->params['wordpress_db']['pass']);
		mysql_select_db(Yii::app()->params['wordpress_db']['name']);
		$table_prefix = Yii::app()->params['wordpress_db']['prefix'];

		/** Grab the user's ID **/
		$callerID = Yii::app()->session['caller_id'];
		$query = mysql_query(
			"SELECT ID FROM {$table_prefix}users
			JOIN {$table_prefix}usermeta 
				ON {$table_prefix}usermeta.user_id = {$table_prefix}users.ID
			WHERE {$table_prefix}usermeta.meta_key = 'vault_phone' 
				AND {$table_prefix}usermeta.meta_value='$callerID'"
		);
		$userRow = mysql_fetch_row($query);
		$userID = $userRow[0];

		/** Update the user's password **/
		$query = mysql_query(
			"UPDATE {$table_prefix}users 
			SET user_pass = MD5('$newPassword')
			WHERE ID = $userID"
		);

		$passwordLetters = implode('<break time=\'.5s\' />', str_split($passwordWord));

		$this->_handler->say(
			"<speak>You have been assigned a new password. "
			."Your new password is $passwordWord "
			."<say-as interpret-as='vxml:digits'>"
				.$passwordNumber
			."</say-as> "
			."<break time='1s' />"
			."Your new password is $passwordLetters "
			."<say-as interpret-as='vxml:digits'>"
				.$passwordNumber
			."</say-as> "
			."<break time='1s' />"
			."Your new password is $passwordLetters "
			."<say-as interpret-as='vxml:digits'>"
				.$passwordNumber
			."</say-as> "
			."<break time='1s' />"
			."Your new password is $passwordLetters "
			."<say-as interpret-as='vxml:digits'>"
				.$passwordNumber
			."</say-as></speak>"
		);
		$this->_handler->output();
	}

	public function actionFailed()
	{
		$this->_handler->say("Verification failed.");
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
		// 	$callerID = $this->_handler->getCallerID();
		// 	Yii::app()->session['log_caller'] = $callerID;
		// 	$msg = "New enrollment for $callerID";
		// 	Yii::log($msg);
		// 	return;
		// }
		// $msg = "\t".Yii::app()->session['log_caller'];
		// if (is_string($inputMsg)) {
		// 	$msg .= ": ".$inputMsg;
		// } else {
		// 	$msg .= ": ".var_export($inputMsg, true);
		// }
		// Yii::log($msg);
	}
}
