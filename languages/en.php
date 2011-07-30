<?php
/**
 * An english language definition file
 */

$english = array(
	'facebookservice' => 'Facebook Services',
	
	'facebookservice:settings:instructions' => 'You must obtain an API key and secret from <a href="http://www.facebook.com/developers/createapp.php">Facebook</a>.',
	'facebookservice:settings:api_key' => 'API Key',
	'facebookservice:settings:api_secret' => 'API Secret',
	'facebookservice:settings:sign_on' => 'Allow users to sign on with Facebook?',
	
	'facebookservice:login:success' => 'You have signed in with Facebook.',
	'facebookservice:login:error' => 'Unable to sign on with Facebook.',
	'facebookservice:login:new' => "A new {$CONFIG->site->name} account has been created from your Facebook account.",
	
	'facebookservice:usersettings:description' => "Link your {$CONFIG->site->name} account with Facebook.",
	'facebookservice:usersettings:authorize' => "You must first <a href=\"%s\">authorize</a> {$CONFIG->site->name} to access Facebook.",
	'facebookservice:authorize:error' => "Could not authorize {$CONFIG->site->name} to access Facebook.",
	'facebookservice:authorize:success' => "Authorized {$CONFIG->site->name} to access Facebook.",
	'facebookservice:usersettings:authorized' => "You have authorized {$CONFIG->site->name} to access your Facebook account: <a href=\"%2\$s\">%1\$s</a>.",
	'facebookservice:usersettings:revoke' => 'Click <a href="%s">here</a> to revoke access.',
	'facebookservice:revoke:success' => 'Facebook access has been revoked.',
);

add_translation('en', $english);
