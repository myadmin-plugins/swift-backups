#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift;
$module = 'vps';
$settings = \get_module_settings($module);
$new_file = [
	'value' => 0,
	'name' => '',
	'path' => ''
];
$new_dir = [
	'value' => 0,
	'name' => '',
	'path' => '',
	'children' => []
];
$repos = [];
$db = get_module_db($module);
$json = $sw->list_accounts();
foreach ($json['accounts'] as $account_idx => $account_data) {
	$repo = [];
	if ($account_data['name'] == 'openvz') {
		$repo['name'] = 'OpenVZ';
	} elseif ($account_data['name'] == 'kvm') {
		$repo['name'] = 'KVM';
	} else {
		$repo['name'] = trim(ucwords($account_data['name']));
	}
	$users_data = $sw->list_account($account_data['name']);
	$repo['username'] = $account_data['name'].':'.$users_data['users'][0]['name'];
	$user_data = $sw->list_user($account_data['name'], $users_data['users'][0]['name']);
	$repo['password'] = str_replace('plaintext:', '', $user_data['auth']);
	$repos[] = $repo;
}
$quick = true;
if ($_SERVER['argv'][1] == 'no') {
	$quick = false;
}
$backups = [];
foreach ($repos as $repo) {
	echo "Processing repo {$repo['name']}\n";
	$repo_backup = $new_dir;
	$repo_backup['name'] = $repo['name'];
	$repo_backup['path'] = $repo['name'];
	$response = $sw->authenticate($repo['username'], $repo['password']);
	$usage = $sw->usage();
	$repo_backup['value'] = (int)$usage['X-Account-Bytes-Used'];
	echo "{$repo['name']} Got {$repo_backup['value']} bytes in backups\n";
	$ls_output = explode("\n", trim($sw->ls()));
	foreach ($ls_output as $container) {
		$container_backup = $quick == false ? $new_dir : $new_file;
		$container_backup['name'] = $container;
		echo "Processing {$repo['name']} container '$container'\n";
		$container_backup['path'] = $repo['name'].'/'.$container;
		$usage = $sw->usage($container);
		$container_backup['value'] = (int)$usage['X-Container-Bytes-Used'];
		if ($quick == false) {
			$ls_vps_output = explode("\n", trim($sw->ls($container)));
			foreach ($ls_vps_output as $filedir) {
				if (preg_match('/\//', $filedir)) {
					echo "Processing '$filedir' in $container\n";
					$file_backup = $new_file;
					$file_backup['name'] = $filedir;
					$file_backup['path'] = $repo['name'].'/'.$container.'/'.$filedir;
					$usage = $sw->usage($container.'/'.$filedir);
					$file_backup['value'] = (int)$usage['Content-Length'];
					$container_backup['children'][] = $file_backup;
				}
			}
		}
		$repo_backup['children'][] = $container_backup;
	}
	$backups[] = $repo_backup;
}
$file = 'swift_usage.json';
file_put_contents(__DIR__.'/../../../../public_html/admin/'.$file, str_replace("\\/", '/', json_encode($backups, JSON_PRETTY_PRINT)));
echo "Wrote {$file}\n";
