<h1>Voice Vault Accounts</h1>
<table>
	<tr>
		<th>Caller ID</th>
		<th>Account ID</th>
		<th>External ID</th>
	</tr>
	<?php foreach ($accounts as $account): ?>
	<tr>
		<td><?php echo $account->caller_id; ?></td>
		<td><?php echo $account->account_id; ?></td>
		<td><?php echo $account->external_id; ?></td>
	</tr>
	<?php endforeach; ?>
</table>
