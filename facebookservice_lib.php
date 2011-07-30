<?php
/**
 * 
 */

require_once "{$CONFIG->pluginspath}facebookservice/vendors/facebook-php-sdk/src/facebook.php";
global $CONFIG;

function facebookservice_use_fbconnect() {
	if (!$key = get_plugin_setting('api_key', 'facebookservice')) {
		return FALSE;
	}
	if (!$secret = get_plugin_setting('api_secret', 'facebookservice')) {
		return FALSE;
	}
	return get_plugin_setting('sign_on', 'facebookservice') == 'yes';
}

function facebookservice_authorize() {
	$facebook = facebookservice_api();
	if (!$session = $facebook->getSession()) {
		register_error(elgg_echo('facebookservice:authorize:error'));
		forward('pg/settings/plugins');
	}
	// only one user to be authorized per account
	$values = array(
		'plugin:settings:facebookservice:access_token' => $session['access_token'],
		'plugin:settings:facebookservice:uid' => $session['uid'],
	);
	if ($users = get_entities_from_private_setting_multi($values, 'user', '', 0, '', 0)) {
		foreach ($users as $user) {
			// revoke access
			set_plugin_usersetting('access_token', NULL , $user->getGUID() , 'facebookservice');
			set_plugin_usersetting('uid', NULL , $user->getGUID() , 'facebookservice');
		}
	}
	
	// register user's access tokens
	set_plugin_usersetting('access_token', $session['access_token'], 0, 'facebookservice');
	set_plugin_usersetting('uid', $session['uid'], 0, 'facebookservice');
	
	system_message(elgg_echo('facebookservice:authorize:success'));
	forward('pg/settings/plugins');
}

function facebookservice_revoke() {

	set_plugin_usersetting('access_token', NULL , 0 , 'facebookservice');
	set_plugin_usersetting('uid', NULL , 0 , 'facebookservice');
	system_message(elgg_echo('facebookservice:revoke:success'));
	forward('pg/settings/plugins');
}

function facebookservice_api() {
	return new Facebook(array(
		'appId' => get_plugin_setting('api_key', 'facebookservice'),
		'secret' => get_plugin_setting('api_secret', 'facebookservice'),
	));
}

function facebookservice_get_authorize_url($next='') {
	global $CONFIG;
	
	if (!$next) {
		// default to login page
		$next = "{$CONFIG->site->url}pg/facebookservice/login";
	}
	
	$facebook = facebookservice_api();
	return $facebook->getLoginUrl(array(
		'next' => $next,
		'req_perms' => 'offline_access,email,user_status,publish_stream,read_stream,read_requests ',
	));
}

function facebookservice_login() {
	global $CONFIG;
	
	// sanity check
	if (!facebookservice_use_fbconnect()) {
		forward();
	}
	$facebook = facebookservice_api();
	if (!$session = $facebook->getSession()) {
		forward();
	}
	// attempt to find user
	$values = array(
		'plugin:settings:facebookservice:uid' => $session['uid'],
	);
	
	if (!$users = get_entities_from_private_setting_multi($values, 'user', '', 0, '', 0)) {

		$facebook = facebookservice_api();
		if (!$session = $facebook->getSession()) {
		      forward();
		}

		$data = $facebook->api('/me');
		
		// backward compatibility for stalled-development FBConnect plugin
		$user = FALSE;
		$facebook_users = elgg_get_entities_from_metadata(array(
			'type' => 'user',
			'metadata_name_value_pairs' => array(
				'name' => 'facebook_uid',
				'value' => $session['uid'],
			),
		));
		
		if (is_array($facebook_users) && count($facebook_users) == 1) {
			// convert existing account
			$user = $facebook_users[0];
			login($user);
			
			// remove unused metadata
			remove_metadata($user->getGUID(), 'facebook_uid');
			remove_metadata($user->getGUID(), 'facebook_controlled_profile');
		}
		
		if (!$user) {
			
			// trigger a hook for plugin authors to intercept
			if (!trigger_plugin_hook('new_facebook_user', 'facebook_service', array('account' => $data), TRUE)) {
				// halt execution
				register_error(elgg_echo('facebookservice:login:error'));
				forward();
			}
			
			$username = str_replace(' ', '', strtolower($data['name']));
			while (get_user_by_username($username)) {
				$username = str_replace(' ', '', strtolower($data['name'])) . '_' . rand(1000, 9999);
			}
			$password = generate_random_cleartext_password();
			
			try {
				// create new account
				if (!$user_id = register_user($username, $password, $data['name'], $data['email'])) {
					register_error(elgg_echo('registerbad'));
					forward();
				}
			} catch (RegistrationException $r) {
				register_error($r->getMessage());
				forward();
			}
			
			$user = new ElggUser($user_id);
			$message = $user->name . ' just synched his/her facebook account with ' . $CONFIG->sitename;
			
			$params = array(
				'message' => $message,
			);

		        $status = $facebook->api('/me/feed', 'POST', $params);
			// pull in Facebook icon
			facebookservice_update_user_avatar($user, "https://graph.facebook.com/{$data['id']}/picture?type=large");
			
			system_message(elgg_echo('facebookservice:login:new'));
			login($user);
		}
		
		// register user's access tokens
		set_plugin_usersetting('access_token', $session['access_token'], $user->getGUID(), 'facebookservice');
		set_plugin_usersetting('uid', $session['uid'], $user->getGUID(), 'facebookservice');
		system_message(elgg_echo('facebookservice:login:success'));
		forward();
	} 
	else if (count($users) == 1) {
		login($users[0]);
		set_plugin_usersetting('access_token', $session['access_token'], $users[0]->getGUID(), 'facebookservice');
		system_message(elgg_echo('facebookservice:login:success'));
		forward();
	}
	
	// register login error
	register_error(elgg_echo('facebookservice:login:error'));
	forward();
}

function facebookservice_update_user_avatar($user, $file_location) {
	
	$sizes = array(
		'topbar' => array(16, 16, TRUE),
		'tiny' => array(25, 25, TRUE),
		'small' => array(40, 40, TRUE),
		'medium' => array(100, 100, TRUE),
		'large' => array(200, 200, FALSE),
		'master' => array(550, 550, FALSE),
	);

	$filehandler = new ElggFile();
	$filehandler->owner_guid = $user->getGUID();
	foreach ($sizes as $size => $dimensions) {
		$image = get_resized_image_from_existing_file(
			$file_location,
			$dimensions[0],
			$dimensions[1],
			$dimensions[2]
		);

		$filehandler->setFilename("profile/$user->guid$size.jpg");
		$filehandler->open('write');
		$filehandler->write($image);
		$filehandler->close();
	}

	return TRUE;
}