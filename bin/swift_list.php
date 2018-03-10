#!/usr/bin/env php -q
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift;
print_r($sw->list_accounts());
