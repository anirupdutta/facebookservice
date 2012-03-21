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
					
					$email_users = get_user_by_email($data['email']);
					if(is_array($email_users) && count($email_users) == 1)
					{
						$user = $email_users[0];
						// register user's access tokens
						set_plugin_usersetting('access_token', $session['access_token'], $user->getGUID(), 'facebookservice');
						set_plugin_usersetting('uid', $session['uid'], $user->getGUID(), 'facebookservice');
						login($user);	
						system_message(elgg_echo('facebookservice:authorize:success'));
						forward();
					}
					else
					{
						register_error(elgg_echo('registerbad'));
						forward();
					}

				}
			} catch (RegistrationException $r) {
				register_error($r->getMessage());
				forward();
			}
			
			$user = new ElggUser($user_id);
			$message = $user->name . ' registered on ' . $CONFIG->sitename;
			
			$site = get_entity(datalist_get('default_site'));


			$params = array(
				'message' => $message,
				'name' => $site->name,
				'link' => $site->url,
				'description' => $site->description,
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
	
	$topbar = get_resized_image_from_existing_file($file_location,16,16, true, true);
	$tiny = get_resized_image_from_existing_file($file_location,25,25, true, true);
	$small = get_resized_image_from_existing_file($file_location,40,40, true, true);
	$medium = get_resized_image_from_existing_file($file_location,100,100, true, true);
	$large = get_resized_image_from_existing_file($file_location,200,200);
	$master = get_resized_image_from_existing_file($file_location,550,550);
				
	if ($small !== false && $medium !== false && $large !== false && $tiny !== false) {
				
			$filehandler = new ElggFile();
			$filehandler->owner_guid = $user->getGUID();
			$filehandler->setFilename("profile/" . $user->guid . "large.jpg");
			$filehandler->open("write");
			$filehandler->write($large);
			$filehandler->close();
			$filehandler->setFilename("profile/" . $user->guid . "medium.jpg");
			$filehandler->open("write");
			$filehandler->write($medium);
			$filehandler->close();
			$filehandler->setFilename("profile/" . $user->guid . "small.jpg");
			$filehandler->open("write");
			$filehandler->write($small);
			$filehandler->close();
			$filehandler->setFilename("profile/" . $user->guid . "tiny.jpg");
			$filehandler->open("write");
			$filehandler->write($tiny);
			$filehandler->close();
			$filehandler->setFilename("profile/" . $user->guid . "topbar.jpg");
			$filehandler->open("write");
			$filehandler->write($topbar);
			$filehandler->close();
			$filehandler->setFilename("profile/" . $user->guid . "master.jpg");
			$filehandler->open("write");
			$filehandler->write($master);
			$filehandler->close();
					
			$user->icontime = time();
	}
	return TRUE;
}