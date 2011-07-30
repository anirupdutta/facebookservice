<?php
/**
 * Site administration settings.
 */

$instructions = elgg_echo('facebookservice:settings:instructions');

$api_key_string = elgg_echo('facebookservice:settings:api_key');
$api_key_view = elgg_view('input/text', array(
	'internalname' => 'params[api_key]',
	'value' => $vars['entity']->api_key,
	'class' => 'text_input',
));

$api_secret_string = elgg_echo('facebookservice:settings:api_secret');
$api_secret_view = elgg_view('input/text', array(
	'internalname' => 'params[api_secret]',
	'value' => $vars['entity']->api_secret,
	'class' => 'text_input',
));

$sign_on_string = elgg_echo('facebookservice:settings:sign_on');
$sign_on_view = elgg_view('input/pulldown', array(
	'internalname' => 'params[sign_on]',
	'options_values' => array(
		'yes' => elgg_echo('option:yes'),
		'no' => elgg_echo('option:no'),
	),
	'value' => $vars['entity']->sign_on ? $vars['entity']->sign_on : 'no',
));

$settings = <<<__HTML
<div id="facebookservice_site_settings">
	<p>$instructions</p>
	<div>$api_key_string $api_key_view</div>
	<div>$api_secret_string $api_secret_view</div>
	<div>$sign_on_string $sign_on_view</div>
</div>
__HTML;

echo $settings;
