<?php
// Temporarily enable error reporting
error_reporting(E_ALL);
?>

<h3><?php _e("Extra profile information", "VoiceVault"); ?></h3>

<table class="form-table">
	<tr>
		<th><label for="vault_phone"><?php _e("Phone Number", "VoiceVault"); ?></label></th>
		<td>
			<input type="text" name="vault_phone" id="vault_phone" value="<?php echo esc_attr( get_the_author_meta( 'vault_phone', $user->ID ) ); ?>" class="regular-text" /><br/>
			<span class="description">
				<?php _e("Please enter your phone number. Used for password retrieval, using our voice biometrics system. You will receive an automated phone call after registration.", ""); ?>
			</span>
		</td>
	</tr>
</table>