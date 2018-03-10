#!/usr/bin/env php -q
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift;
//$response = $sw->authenticate(SWIFT_MY_USER,SWIFT_MY_PASS);
$response = $sw->authenticate(SWIFT_OPENVZ_USER, SWIFT_OPENVZ_PASS);
if ($response === false)
{
	echo "Problems\n";
	exit;
}
echo $sw->ls('vps647');
