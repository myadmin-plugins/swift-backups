#!/usr/bin/env php -q
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$swift_backup_free_gb=50;
$swift_backup_cost_gb=0.15;
$sw = new Swift;
$module = 'vps';
$settings = \get_module_settings($module);
$db = get_module_db($module);
$ids = [];
$data = [];
$db->query("select {$settings['PREFIX']}_id,{$settings['PREFIX']}_hostname from " . $settings['TABLE'] . " where {$settings['PREFIX']}_status != 'pending' and {$settings['PREFIX']}_server_status != 'deleted'", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
	$data[$db->Record[$settings['PREFIX'].'_id']] = $db->Record;
	$ids[] = $db->Record[$settings['PREFIX'].'_id'];
}
$repos = [
	['username' => SWIFT_OPENVZ_USER, 'password' => SWIFT_OPENVZ_PASS],
	['username' => SWIFT_KVM_USER, 'password' => SWIFT_KVM_PASS]
];
$backups = [];
$sum_used_gb = 0;
$sum_chargable_gb = 0;
$sum_chargable_amount = 0;
foreach ($repos as $repo)
{
	$response = $sw->authenticate($repo['username'], $repo['password']);
	$ls_output = explode("\n", $sw->ls());
	foreach ($ids as $id)
	{
		if (in_array($settings['PREFIX'] . $id, $ls_output))
		{
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

echo "Summary:
Total Used GB {$sum_used_gb}
Total Chargable GB {$sum_chargable_gb}
Total Chargable Amount {$sum_chargable_amount}
";
