<?php

use React\HttpClient\Client;
use React\HttpClient\Response;

require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$swift_backup_free_gb=50;
$swift_backup_cost_gb=0.15;
$sw = new Swift;
$sw->set_v1_auth_url('http://storage-nj.interserver.net/auth/v1.0');
$module = 'vps';
$settings = \get_module_settings($module);
$db = get_module_db($module);
$ids = [];
$data = [];

function new_client_request($type, $repo_name, $container = '') {
	global $repos, $retry, $client;
	if (!isset($retry["{$type}{$repo_name}{$container}"]))
		$retry["{$type}{$repo_name}{$container}"] = 0;
	$request = $client->request('GET', $repos[$type][$repo_name]['url'].'/'.$container, ['X-Auth-Token' => $repos[$type][$repo_name]['token']]);
	$request->on('response', function (Response $response) use ($type, $repo_name, $container) {
		global $repos, $retry, $client;
		$response->on('data', function ($chunk) use ($type, $repo_name, $container, $response) {
			global $repos, $retry, $client;
			$headers = $response->getHeaders();
			if (!isset($headers['X-Trans-Id'])) {
				if ($retry["{$type}{$repo_name}{$container}"] < 5) {
					echo "Retrying {$type} {$repo_name} {$container}\n";
					$retry["{$type}{$repo_name}{$container}"] = $retry["{$type}{$repo_name}{$container}"] + 1;
					//new_client_request($type, $repo_name, $container);
				}
			} else
				echo $type.' '.$repo_name.' '.$container.' Attempt #'.$retry["{$type}{$repo_name}{$container}"].' DATA Length:'.strlen($chunk).PHP_EOL;
			$repos[$type][$repo_name]['usage'][$container] .= $chunk;
		});
		$response->on('end', function () use ($type, $repo_name, $container) {
			global $repos, $retry, $client;
			//echo $type.' '.$repo_name.' Attempt #'.$retry["{$type}{$repo_name}{$container}"].' '.$container.' DONE' . PHP_EOL;
		});
	});
	$request->on('error', function (\Exception $e) use ($type, $repo_name, $container) {
		global $repos, $retry, $client;
		echo 'Error Occurred Attempt #'.$retry["{$type}{$repo_name}{$container}"].' with '.$type.' '.$repo_name.' '.$container.':'.$e->getMessage().PHP_EOL;
		if ($retry["{$type}{$repo_name}{$container}"] < 5) {
			$retry["{$type}{$repo_name}{$container}"] = $retry["{$type}{$repo_name}{$container}"] + 1;
			//new_client_request($type, $repo_name, $container);
		}
	});
	$request->end();
	return $request;
}

/*
$db->query("select {$settings['PREFIX']}_id,{$settings['PREFIX']}_hostname from " . $settings['TABLE'] . " where {$settings['PREFIX']}_status != 'pending' and {$settings['PREFIX']}_server_status != 'deleted'", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
	$data[$db->Record[$settings['PREFIX'].'_id']] = $db->Record;
	$ids[] = $db->Record[$settings['PREFIX'].'_id'];
}
*/
global $repos, $client, $retry, $loop;
$repos = [
	'other' => [
		'my' => ['username' => SWIFT_MY_USER, 'password' => SWIFT_MY_PASS],
	],
	'webhosting' => [
		'webhosting' => ['username' => SWIFT_WEBHOSTING_USER, 'password' => SWIFT_WEBHOSTING_PASS],
	],
	'vps' => [
		'openvz' => ['username' => SWIFT_OPENVZ_USER, 'password' => SWIFT_OPENVZ_PASS],
		//'kvm' => ['username' => SWIFT_KVM_USER, 'password' => SWIFT_KVM_PASS]
	],
];
$backups = [];
$sum_used_gb = 0;
$sum_chargable_gb = 0;
$sum_chargable_amount = 0;
$loop = React\EventLoop\Factory::create();
$client = new Client($loop);
$start = time();
ini_set('default_socket_timeout', 30);
echo 'Starting to Authenticate and build Requests at '.$start.PHP_EOL;
foreach ($repos as $type => $type_repos) {
	foreach ($type_repos as $repo_name => $repo) {
		$storage_url = '';
		$sw = new Swift;
		$sw->set_v1_auth_url('http://storage-nj.interserver.net/auth/v1.0');
		$response = $sw->authenticate($repo['username'], $repo['password'], 10);
		list($storage_url, $storage_token) = $response;
		$storage_url = str_replace('https://storage-nj.interserver.net:8080/', 'http://storage-nj.interserver.net/', $storage_url);
		echo $type.' '.$repo_name.' Storage URL:'.$storage_url.' and Token:'.$storage_token.PHP_EOL;
		$repos[$type][$repo_name]['sw'] = $sw;
		$repos[$type][$repo_name]['url'] = $storage_url;
		$repos[$type][$repo_name]['token'] = $storage_token;
		$retry["{$type}{$repo_name}"] = 0;
		$repos[$type][$repo_name]['ls'] = '';
		while ($repos[$type][$repo_name]['ls'] == '' && $retry["{$type}{$repo_name}"] < 5) {
			$retry["{$type}{$repo_name}"] = $retry["{$type}{$repo_name}"] + 1;
			$sw = $repos[$type][$repo_name]['sw'];
			$response = $sw->ls();
			if ($response === FALSE)
				echo 'Got odd response:'.var_export($response, true).PHP_EOL;
			else
				$repos[$type][$repo_name]['ls'] = $response;
		}
		if ($response !== FALSE) {
			$repos[$type][$repo_name]['ls'] = explode("\n", trim($response));
			echo "Loaded ".sizeof($repos[$type][$repo_name]['ls'])." Entries for ".$type.' '.$repo_name.PHP_EOL;
			$repos[$type][$repo_name]['usage'] = [];
			foreach ($repos[$type][$repo_name]['ls'] as $container) {
				$repos[$type][$repo_name]['usage'][$container] = '';
				$retry["{$type}{$repo_name}{$container}"] = 1;
				echo "Looking up {$repo_name}({$type}) container {$container}\n";
				new_client_request($type, $repo_name, $container);
			}
		} else {
			echo "Couldnt load {$type} {$repo_name} Giving up".PHP_EOL;
		}
	}
}
$end = time();
echo 'Ended at '.$end.PHP_EOL;
echo 'Requests took '.($end - $start).' seconds'.PHP_EOL;
$start = time();
echo 'Starting to run requests at '.$start.PHP_EOL;
$loop->run();
$end = time();
echo 'Ended at '.$end.PHP_EOL;
echo 'Requests took '.($end - $start).' seconds'.PHP_EOL;
exit;
/*
		foreach ($ids as $id) {
			if (in_array($settings['PREFIX'] . $id, $ls_output)) {
				$vps = $data[$id];
				$ls_vps_output = $sw->ls($settings['PREFIX'] . $id);
				//add_output('<pre>'.$ls_vps_output.'</pre>');
				// this regex here matches all backup entries both dir split and regular files
				preg_match_all("/^(?P<backups>[^\/\s]+)$/m", $ls_vps_output, $matches);
				$matches = array_unique($matches['backups']);
				// this regex here matches only dir split backup entries
				//preg_match_all('/^(?P<backups>.*)\/fly\-.*$/m', $ls_vps_output, $matches);
				//$matches = array_unique($matches['backups']);
				$total_used_gb = 0;
				if (count($matches) > 0)
				{
					foreach ($matches as $match)
					{
						$usage = $sw->usage($settings['PREFIX'] . $id.'/'.$match);
						$backups[] = $vps[$settings['PREFIX'].'_id'].':'.$match;
						$sizes[$id.':'.$match] = $usage['Content-Length'];
						if (!is_numeric($usage['Content-Length']))
						{
							myadmin_log('scripts', 'info', "{$vps['vps_hostname']} {$match} invalid content length " .var_export($usage, true), __LINE__, __FILE__);
						} else {
							$used_gb = round((isset($usage['Content-Length']) ? $usage['Content-Length'] : 0) / 1024 / 1024 / 1024, 2);
							//echo "{$vps['vps_hostname']} {$match} {$used_gb} GB\n";
							$total_used_gb = bcadd($used_gb, $total_used_gb, 2);
						}
					}
				}
				if ($total_used_gb > $swift_backup_free_gb)
				{
					echo sprintf("%30s  total backups %4d Gb has %4d Gb overage, cost would be $%02.02f\n", $vps['vps_hostname'], $total_used_gb, $chargable_gb, $chargable_amount);
					$chargable_gb = bcsub($total_used_gb, $swift_backup_free_gb, 0);
					$chargable_amount = $chargable_gb * $swift_backup_cost_gb;
					$sum_used_gb = bcadd($sum_used_gb, $total_used_gb, 2);
					$sum_chargable_gb = bcadd($sum_chargable_gb, $chargable_gb, 2);
					$sum_chargable_amount = bcadd($sum_chargable_amount, $chargable_amount, 2);
				}
			}
		}
		echo "Currnet totals: Total Used GB {$sum_used_gb}    Total Chargable GB {$sum_chargable_gb}    Total Chargable Amount $ {$sum_chargable_amount}\n";
	}
}

echo "Summary:
Total Used GB {$sum_used_gb}
Total Chargable GB {$sum_chargable_gb}
Total Chargable Amount {$sum_chargable_amount}
";
*/
