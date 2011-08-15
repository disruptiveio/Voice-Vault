<div class="wrap">
	<h2>Voice Vault Widget Options</h2>
	<form method="post" action="options.php">
		<?php
		settings_fields('voicevault_options');

		$phone = get_option('voicevault_phone');
		?>
		<h3>Settings</h3>
		<fieldset>
			<p>
				<label>Tropo Phone Number</label>
				<input type="text" name="voicevault_phone" value="<?php echo $phone; ?>" />
			</p>
		</fieldset>
		<p>
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
