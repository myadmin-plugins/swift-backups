#!/usr/bin/php -q
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
$db = get_module_db($module);
$repos = [
	['name' => 'OpenVZ', 'username' => SWIFT_OPENVZ_USER, 'password' => SWIFT_OPENVZ_PASS],
	['name' => 'KVM', 'username' => SWIFT_KVM_USER, 'password' => SWIFT_KVM_PASS]
];
$quick = true;
if ($_SERVER['argv'][1] == 'no')
	$quick = false;
$backups = [];
foreach ($repos as $repo) {
	echo "Processing repo {$repo['name']}\n";
	$repo_backup = $new_dir;
	$repo_backup['name'] = $repo['name'];
	$repo_backup['path'] = $repo['name'];
	$response = $sw->authenticate($repo['username'], $repo['password']);
	$usage = $sw->usage();
	$repo_backup['value'] = (int)$usage['X-Account-Bytes-Used'];
	$host_backup = $new_dir;
	$client_backup = $new_dir;
	$host_backup['name'] = 'Hosts';
	$host_backup['path'] = $repo['name'].'/Hosts';
	$client_backup['name'] = 'Clients';
	$client_backup['path'] = $repo['name'].'/Clients';
	$ls_output = explode("\n", trim($sw->ls()));
	foreach ($ls_output as $container) {
		$container_backup = $quick == FALSE ? $new_dir : $new_file;
		$container_backup['name'] = $container;
		$type = preg_match('/^vps/', $container) ? 'Clients' : 'Hosts';
		echo "Processing {$repo['name']} {$type} container '$container'\n";
		$container_backup['path'] = $repo['name'].'/'.$type.'/'.$container;
		$usage = $sw->usage($container);
		$container_backup['value'] = (int)$usage['X-Container-Bytes-Used'];
		if ($quick == FALSE) {
			$ls_vps_output = explode("\n", trim($sw->ls($container)));
			foreach ($ls_vps_output as $filedir) {
				if (preg_match('/\//', $filedir)) {
					echo "Processing '$filedir' in $container\n";
					$file_backup = $new_file;
					$file_backup['name'] = $filedir;
					$file_backup['path'] = $repo['name'].'/'.$type.'/'.$container.'/'.$filedir;
					$usage = $sw->usage($container.'/'.$filedir);
					$file_backup['value'] = (int)$usage['Content-Length'];
					$container_backup['children'][] = $file_backup;
				}
			}
		}
		if ($type == 'Hosts')
			$client_backup['children'][] = $container_backup;
		else
			$host_backup['children'][] = $container_backup;
	}
	$usage = 0;
	foreach ($client_backup['children'] as $cid => $cdata)
		$usage += $cdata['value'];
	$client_backup['value'] = (int)$usage;
	$usage = 0;
	foreach ($host_backup['children'] as $cid => $cdata)
		$usage += $cdata['value'];
	$host_backup['value'] = (int)$usage;
	echo "{$repo['name']} Got {$host_backup['value']} bytes in Host backups and {$client_backup['value']} bytes in Client backups\n";
	$repo_backup['children'][] = $host_backup;
	$repo_backup['children'][] = $client_backup;
	$backups[] = $repo_backup;
}
$file = $quick == TRUE ? 'swift_quick_usage.json' : 'swift_usage.json';
file_put_contents($file, str_replace("\\/", '/', json_encode($backups, JSON_PRETTY_PRINT)));
echo "Wrote {$file}\n";
