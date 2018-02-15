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
		$backups = [];
		foreach ($repos as $repo) {
			$repo_backup = $new_dir;
			$repo_backup['name'] = $repo['name'];
			$response = $sw->authenticate($repo['username'], $repo['password']);
			$usage = $sw->usage();
			$repo_backup['value'] = $usage['X-Account-Bytes-Used'];
			$ls_output = explode("\n", trim($sw->ls()));
			foreach ($ls_output as $container) {
				$container_backup = $new_dir;
				$container_backup['name'] = $container;
				$usage = $sw->usage($container);
				$container_backup['value'] = $usage['X-Container-Bytes-Used'];
				$ls_vps_output = explode("\n", trim($sw->ls($container)));
				echo "Processing container '$container'\n";
				foreach ($ls_vps_output as $filedir) {
					echo "Processing '$filedir' in $container\n";
					if (preg_match('/\//', $filedir)) {
						$file_backup = $new_file;
						$file_backup['name'] = $filedir;
						$file_backup['path'] = $repo['name'].'/'.$container.'/'.$filedir;
						$usage = $sw->usage($container.'/'.$filedir);
						$file_backup['value'] = $usage['Content-Length'];
						$container_backup['children'][] = $file_backup;
					}
				}
				$repo_backup['children'][] = $container_backup;
			}
			$backups[] = $repo_backup;
		}
file_put_contents('swift_usage.json', json_encode($backups, JSON_PRETTY_PRINT));
print_r($backups);
