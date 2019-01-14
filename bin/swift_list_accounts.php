#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift;
$accounts = $sw->list_accounts();
foreach ($accounts['accounts'] as $idx => $accountData) {
	echo $accountData['name'].PHP_EOL;
}
