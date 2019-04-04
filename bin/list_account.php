#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift;
if ($_SERVER['argc'] < 2) {
	die("Syntax {$_SERVER['argv'][0]} <name>\n	where <name> is one of the account names\n");
}
print_r($sw->list_account($_SERVER['argv'][1]));
