<?php

/**
 * Bunch of test functions.
 */
class DefaultController extends Controller
{
	public function actionIndex()
	{
		$this->render('index');
	}
	
	/**
	 * List of all Voice Vault accounts.
	 */
	public function actionAccounts()
	{
		$accounts = VoiceVaultAccounts::model()->findAll();
		
		$this->render('accounts', array('accounts'=>$accounts));
	}
	
	public function actionInsert($callerid, $externalid, $accountid)
	{
		$account = new VoiceVaultAccounts;
		
		$account->caller_id = $callerid;
		$account->account_id = $accountid;
		$account->external_id = $externalid;
		
		if ($account->save()) {
			echo "Account inserted successfully.";
		} else {
			echo "Error saving account.";
		}
	}

	public function actionRegister()
	{
		if (Yii::app()->session['claimant_id'])
			die('Claimant ID already fetched.');

		$vault = new VoiceVaultComponent(
			'DevMichaelMackus',
			'u458N3a6FF9eX2qs',
			'ad93d03d-c28b-4ca3-9af7-6a8fe18e9695',
			'f8e6f876-54da-4bc1-ae4c-2129cfd5b6e8',
			true
		);

		$claimantResult = $vault->RegisterClaimant();

		if ($claimantResult->status_code == 0) {
			Yii::app()->session['claimant_id'] = strval($claimantResult->claimant_id);
			echo "Claimant ID successfully fetched.";
		} else {
			die("Error fetching claimant ID: ".strval($claimantResult->message));
		}
	}

	public function actionDialogue()
	{
		$vault = new VoiceVaultComponent(
			'DevMichaelMackus',
			'u458N3a6FF9eX2qs',
			'ad93d03d-c28b-4ca3-9af7-6a8fe18e9695',
			'f8e6f876-54da-4bc1-ae4c-2129cfd5b6e8',
			true
		);

		$claimantID = Yii::app()->session['claimant_id'];

		$dialogueResult = $vault->StartDialogue($claimantID, 
			'1231231234'
		);

		if ($dialogueResult->status_code == 0) {
			Yii::app()->session['dialogue_id'] = strval($dialogueResult->dialogue_id);
			Yii::app()->session['prompt'] = strval($dialogueResult->prompt_hint);
			echo "Dialogue started.";
		} else {
			die("Error starting dialogue: ".$dialogueResult->message);
		}
	}

	public function actionSubmit()
	{
		$vault = new VoiceVaultComponent(
			'DevMichaelMackus',
			'u458N3a6FF9eX2qs',
			'ad93d03d-c28b-4ca3-9af7-6a8fe18e9695',
			'f8e6f876-54da-4bc1-ae4c-2129cfd5b6e8',
			true
		);

		$result = $vault->SubmitPhrase('C:\\Users\\Michael Mackus\\Projects\\SendShorty\\b7\\uploads\\vault\\e50s9gegr350c7ni1jalmcj2b1.wav',
			Yii::app()->session['prompt'],
			Yii::app()->session['dialogue_id']);

		die(var_dump($result));
	}

	public function actionSave()
	{
		// Store the claimant in our system
		$account = new VoiceVaultAccounts;
		$account->caller_id = '6617186462';
		$account->external_id = 'random-claimant-id';

		// Generate a unique account ID
		$accountID = rand(1000, 10000);
		while (($accountVerify =
				VoiceVaultAccounts::model()->find('account_id = ?',
					array($accountID))
				)) {
			$accountID = rand(1000, 10000);
		}

		$account->account_id = $accountID;

		die(var_dump($account->save()));
	}

	public function actionLog()
	{
		$var = array('oh', 'yea');
		$f = fopen('test.txt', 'w');
		fwrite($f, var_export($var, true));
		fclose($f);
	}
}
