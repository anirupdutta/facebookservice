<?php
/**
 * Elgg Facebook Services
 * This service plugin allows users to authenticate their Elgg account with Facebook.
 * 
 * @package FacebookService
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @copyright anirupdutta
 */

require_once "{$CONFIG->pluginspath}facebookservice/facebookservice_lib.php";
global $CONFIG;

register_elgg_event_handler('init', 'system', 'facebookservice_init');
function facebookservice_init() {
	elgg_extend_view('css', 'facebookservice/css');
	
	register_page_handler('facebookservice', 'facebookservice_pagehandler');
	

	register_plugin_hook('public_pages', 'walled_garden', 'facebookservice_public_pages');
	register_plugin_hook('post', 'facebook_service', 'facebookservice_post');
	register_plugin_hook('viewwall', 'facebook_service', 'facebookservice_viewwall');
	register_plugin_hook('viewfeed', 'facebook_service', 'facebookservice_viewfeed');
	register_plugin_hook('viewcomment', 'facebook_service', 'facebookservice_viewcomment');
	register_plugin_hook('viewusername', 'facebook_service', 'facebookservice_viewusername');
	register_plugin_hook('viewlike', 'facebook_service', 'facebookservice_viewlike');
	register_plugin_hook('postcomment', 'facebook_service', 'facebookservice_postcomment');
	register_plugin_hook('postlike', 'facebook_service', 'facebookservice_postlike');
	register_plugin_hook('friendrequest','facebook_service','facebookservice_friendrequest');	
	
	if (facebookservice_use_fbconnect()) {
		elgg_extend_view('login/extend', 'facebookservice/login');
	}
}

function facebookservice_pagehandler($page) {
	global $CONFIG;
	
	if (!isset($page[0])) {
		forward();
	}
	
	$_GET['session'] = $CONFIG->input['session'];
	
	switch ($page[0]) {
		case 'authorize':
			facebookservice_authorize();
			break;
		case 'revoke':
			facebookservice_revoke();
			break;
		case 'login':
			facebookservice_login();
			break;
		default:
			forward();
			break;
	}
}

function facebookservice_public_pages($hook, $type, $return_value, $params) {
	$return_value[] = 'pg/facebookservice/login';
	return $return_value;
}


function facebookservice_post($hook, $entity_type, $returnvalue, $params) {

	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');


	$attachment =  array(
		'access_token' => $access_token,
		'message' => $params['message'],
		'name' => $params['name'],
		'link' => $params['link'],
		'description' => $params['description'],
		'picture' => $params['picture'],
	);
		
		if (!($access_token && $target)) {
		return NULL;
	}

	$facebook = facebookservice_api();
	$ret_code=$facebook->api('/me/feed', 'POST', $attachment);
	
	return TRUE;
}


function facebookservice_viewwall($hook, $entity_type, $returnvalue, $params) {

	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
		'limit' => 10,
	);

	if (!($access_token && $target)) {
		return NULL;
	}

	$facebook = facebookservice_api();
	$fbfeed=$facebook->api('/me/feed', 'GET', $attachment);
	
	return $fbfeed;
}


function facebookservice_viewfeed($hook, $entity_type, $returnvalue, $params) {

	
	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}


	$facebook = facebookservice_api();
	$filter = $params['choice'];
     
	switch ($filter) {

		case "network":
		$fbhome = $facebook->api(
					array(	'method'=>'fql.query',
							'query'=> "SELECT post_id,viewer_id,source_id ,created_time,attachment,likes,comments,actor_id, target_id, message FROM stream WHERE filter_key in (SELECT filter_key FROM stream_filter WHERE uid = $target AND type = 'network')",'access_token' => $access_token,
  			
		));
		break;
		case "friendlist":
		$fbhome = $facebook->api(
					array(	'method'=>'fql.query',
							'query'=> "SELECT post_id,viewer_id,source_id ,created_time,attachment,likes,comments,actor_id, target_id, message FROM stream WHERE filter_key in (SELECT filter_key FROM stream_filter WHERE uid = $target AND type = 'application')",'access_token' => $access_token,
  			
		));
		break;
		case "newsfeed":
		default:
		$fbhome = $facebook->api(
					array(	'method'=>'fql.query',
							'query'=> "SELECT post_id,viewer_id,source_id ,created_time,attachment,likes,comments,actor_id, target_id, message FROM stream WHERE filter_key in (SELECT filter_key FROM stream_filter WHERE uid = $target AND type = 'newsfeed')",'access_token' => $access_token,
  			
		));
		break;
		}

	return $fbhome;
}

function facebookservice_viewcomment($hook, $entity_type, $returnvalue, $params) {


	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}
	$id = $params['id'];
	$facebook = facebookservice_api();
	$fbcomments=$facebook->api('/' .$id . '/comments', 'GET', $attachment);

	return $fbcomments;
}

function facebookservice_postcomment($hook, $entity_type, $returnvalue, $params) {

	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
		'message' => $params['message'],
  	);

	if (!($access_token && $target)) {
		return NULL;
	}
	$id = $params['id'];
	$facebook = facebookservice_api();
	$returncomments=$facebook->api('/' .$id . '/comments', 'POST', $attachment);

	return $returncomments;
}

function facebookservice_viewlike($hook, $entity_type, $returnvalue, $params) {

	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}
	$id = $params['id'];
	$facebook = facebookservice_api();
	$fblikes=$facebook->api('/' .$id . '/likes', 'GET', $attachment);

	return $fblikes;
}


function facebookservice_postlike($hook, $entity_type, $returnvalue, $params) {

	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}
	$id = $params['id'];

	$facebook = facebookservice_api();
	$returnlikes=$facebook->api('/' .$id . '/likes', 'POST', $attachment);


	return $returnlikes;
}


function facebookservice_viewusername($hook, $entity_type, $returnvalue, $params) {

	
	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}
	$id = $params['id'];

	$facebook = facebookservice_api();
	$fbuser = $facebook->api(
					array(	'method'=>'fql.query',
							'query'=> "SELECT name FROM profile WHERE id = $id",'access_token' => $access_token,
  			
	));

	return $fbuser;

}

function facebookservice_friendrequest($hook, $entity_type, $returnvalue, $params) {

	
	$access_token = get_plugin_usersetting('access_token', 0, 'facebookservice');
	$target = get_plugin_usersetting('uid', 0, 'facebookservice');

	$attachment =  array(
		'access_token' => $access_token,
  	);

	if (!($access_token && $target)) {
		return NULL;
	}

	$facebook = facebookservice_api();
	$fbrequest = $facebook->api(
					array(	'method'=>'fql.query',
							'query'=> "SELECT uid_from FROM friend_request WHERE uid_to=$target ",'access_token' => $access_token,
  			
	));
	return $fbrequest;
}