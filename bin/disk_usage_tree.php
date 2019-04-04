#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
$verbose = 0;
if ($_SERVER['argc'] == 1) {
	$verbose = -1;
}
for ($x = 1; $x < $_SERVER['argc']; $x++) {
	if (in_array($_SERVER['argv'][$x], ['repo','container','file'])) {
		$detail = $_SERVER['argv'][$x];
	} elseif (in_array($_SERVER['argv'][$x], ['-v', '-vv', '-vvv', '-vvvv'])) {
		$verbose += strlen($_SERVER['argv'][$x]) - 1;
	} elseif (in_array($_SERVER['argv'][$x], ['-h'])) {
		$verbose = -1;
	}
}
if ($verbose == -1) {
	die("Syntax {$_SERVER['argv'][0]} [-v] <detail level>\n	<detail level> can be any of: repo, container, or file\n	[-v] increase verbosity level (can be repeated)");
}
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
echo "Loading Accounts...";
$json = $sw->list_accounts();
echo count($json['accounts']).' Account Names Loaded'.PHP_EOL;
foreach ($json['accounts'] as $account_idx => $account_data) {
	$repo = [];
	if ($account_data['name'] == 'openvz') {
		$repo['name'] = 'OpenVZ';
	} elseif ($account_data['name'] == 'kvm') {
		$repo['name'] = 'KVM';
	} else {
		$repo['name'] = trim(ucwords($account_data['name']));
	}
	if ($verbose >= 1) {
		echo 'Loading Account '.$account_data['name'];
	}
	$users_data = $sw->list_account($account_data['name']);
	$repo['username'] = $account_data['name'].':'.$users_data['users'][0]['name'];
	if ($verbose >= 1) {
		echo '   Loading User '.$users_data['users'][0]['name'];
	}
	$user_data = $sw->list_user($account_data['name'], $users_data['users'][0]['name']);
	$repo['password'] = str_replace('plaintext:', '', $user_data['auth']);
	$repos[] = $repo;
	if ($verbose >= 1) {
		echo PHP_EOL;
	}
}
$backups = [];
$repo_total = 0;
$container_total = 0;
$file_total = 0;
foreach ($repos as $repo) {
	if ($verbose >= 1) {
		echo "Processing repo {$repo['name']}\n";
	}
	$repo_backup = $new_dir;
	$repo_backup['name'] = $repo['name'];
	$repo_backup['path'] = $repo['name'];
	$response = $sw->authenticate($repo['username'], $repo['password']);
	$usage = $sw->usage();
	$repo_backup['value'] = (int)$usage['X-Account-Bytes-Used'];
	echo "{$repo['name']} Got ".Scale($repo_backup['value'])." in backups\n";
	$repo_total = bcadd($repo_backup['value'], $repo_total);
	if ($detail != 'repo') {
		$ls_output = explode("\n", trim($sw->ls()));
		foreach ($ls_output as $container) {
			$container_backup = $detail != 'file' ? $new_dir : $new_file;
			$container_backup['name'] = $container;
			if ($verbose >= 2) {
				echo "Processing {$repo['name']} container '$container'\n";
			}
			$container_backup['path'] = $repo['name'].'/'.$container;
			$usage = $sw->usage($container);
			$container_backup['value'] = (int)$usage['X-Container-Bytes-Used'];
			$container_total = bcadd($container_backup['value'], $container_total);
			if ($detail == 'file') {
				$ls_vps_output = explode("\n", trim($sw->ls($container)));
				foreach ($ls_vps_output as $filedir) {
					if (preg_match('/\//', $filedir)) {
						echo "Processing '$filedir' in $container\n";
						$file_backup = $new_file;
						$file_backup['name'] = $filedir;
						$file_backup['path'] = $repo['name'].'/'.$container.'/'.$filedir;
						$usage = $sw->usage($container.'/'.$filedir);
						$file_backup['value'] = (int)$usage['Content-Length'];
						$file_total = bcadd($file_backup['value'], $file_total);
						$container_backup['children'][] = $file_backup;
					}
				}
			}
			$repo_backup['children'][] = $container_backup;
		}
	}
	$backups[] = $repo_backup;
}
file_put_contents(__DIR__.'/../../../../public_html/admin/swift_usage.json', str_replace("\\/", '/', json_encode($backups, JSON_PRETTY_PRINT)));
echo "Got Total Size ".Scale($repo_total)."\n";
if ($detail != 'repo') {
	echo "Got Total Containers Size ".Scale($container_total)."\n";
}
if ($detail == 'file') {
	echo "Got Total Files Size ".Scale($file_total)."\n";
}
echo "Wrote swift_usage.json\n";
