<?php
/**
 * 
 */

$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
$facebook_id = get_plugin_usersetting('uid', 0, 'facebookservice');

echo '<p>' . elgg_echo('facebookservice:usersettings:description') . '</p>';

if (!$access_token || !$facebook_id) {
	// authorize
	$authorize = facebookservice_get_authorize_url("{$vars['url']}pg/facebookservice/authorize");
	echo '<p>' . sprintf(elgg_echo('facebookservice:usersettings:authorize'), $authorize) . '</p>';
} else {
	$facebook = facebookservice_api();
	$user = $facebook->api('/me', 'GET', array('access_token' => $access_token));
	echo '<p>' . sprintf(elgg_echo('facebookservice:usersettings:authorized'), $user['name'], $user['link']) . '</p>';
	
	$revoke = "{$vars['url']}pg/facebookservice/revoke";
	echo '<p>' . sprintf(elgg_echo('facebookservice:usersettings:revoke'), $revoke) . '</p>';
}
