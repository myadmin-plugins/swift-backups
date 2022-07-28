#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift();
if ($_SERVER['argc'] < 3) {
    die("Syntax {$_SERVER['argv'][0]} <account> <user>\n");
}
print_r($sw->list_user($_SERVER['argv'][1], $_SERVER['argv'][2]));
