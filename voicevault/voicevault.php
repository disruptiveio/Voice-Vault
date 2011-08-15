<?php
/*
Plugin Name: Voice Vault
Plugin URI: http://disruptive.io/
Description: Adds voice verification features to your Wordpress site. 
 * When a user requests a new password, the system calls the user and 
 * asks them to "verify" their account using their voice. 
 * Once verified, it gives them a new password to login to their account.
Version: 0.1
Author: Disruptive Technologies
Author URI: http://disruptive.io/
*/

define('TROPO_CALL_TOKEN', '');

/**
 * 
 * Copyright 2011 Disruptive Technologies, Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 */

/**
 * Actions
 */

function init_plugin()
{
	if (!session_id())
		session_start();
}

/**
 * Add extra user profile fields. Currently adds the "phone number" field to the wordpress profile.
 */
function user_profile_fields($user)
{
	require('templates/profile.php');
}

/**
 * Updates the extra user profile fields. Only field to be updated is the "phone number" field.
 */
function save_user_profile_fields($user_id)
{
	if (!current_user_can('edit_user', $user_id)) {
		return false;
	}
	
	$phoneNumber = $_POST['vault_phone'];
	
	// Strip all formatting from the phone number
	$rawPhoneNumber = str_replace(array('+', '(', ')', '-', ' '),
		'',
		$phoneNumber);
		
	// Blank - do nothing
	if (empty($rawPhoneNumber))
		return false;
		
	// Strip out leading "1" for US phone numbers
	if (strlen($rawPhoneNumber) > 10 && $rawPhoneNumber[0] == 1) 
		$rawPhoneNumber = substr($rawPhoneNumber, 1);
	// Limit phone number to 10 characters (we only want US phone numbers)
	// We also need the length to be exactly 10 digits, and all integers
	if (strlen($rawPhoneNumber) > 10 || strlen($rawPhoneNumber) < 10 || !is_numeric($rawPhoneNumber)) {
		$_SESSION['vault_errors'] = 'Invalid US phone number.';
		return false;
	}

	$oldPhone = get_usermeta($user_id, 'vault_phone');
	
	//if ($oldPhone <> $rawPhoneNumber) {
		initiate_call($rawPhoneNumber);
		
		update_usermeta($user_id, 'vault_phone', $rawPhoneNumber);
	//}
}

function initiate_call($phoneNumber)
{
	$callParams = 'phone_number='.$phoneNumber;
	$initaiteCallUrl = "https://api.tropo.com/1.0/sessions?action=create&token=".TROPO_CALL_TOKEN."&".$callParams;
	
	$ch = curl_init($initaiteCallUrl);
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	
	$result = curl_exec($ch);
	curl_close($ch);
}

function user_profile_validation_error() 
{
	$errorMessage = isset($_SESSION['vault_errors']) ? $_SESSION['vault_errors'] : '';

	if ($errorMessage) {
		echo "<script>alert('$errorMessage');</script>";
		unset($_SESSION['vault_errors']);
	}
}

function admin_options_init()
{
	register_setting('voicevault_options', 'voicevault_phone');
}

function admin_menu()
{
	add_options_page('Voice Vault', 'Voice Vault', 'manage_options', 'voicevault', 'admin_menu_view');
}

function admin_menu_view()
{
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	require('templates/admin_options.php');
}

function admin_password() 
{
	echo "<p>Or, call ".get_option('voicevault_phone')." for phone verification.</p>";
	echo "<p>&nbsp;</p>";
}

/**
 * Add actions
 */
// Sessions
add_action('init', 'init_plugin');
// Template actions
add_action('show_user_profile', 'user_profile_fields');
add_action('edit_user_profile', 'user_profile_fields');
// Save actions
add_action('personal_options_update', 'save_user_profile_fields');
add_action('edit_user_profile_update', 'save_user_profile_fields');
// Errors
add_action('all_admin_notices', 'user_profile_validation_error');
// Admin
add_action('admin_init', 'admin_options_init');
add_action('admin_menu', 'admin_menu');
add_action('lostpassword_form', 'admin_password');
