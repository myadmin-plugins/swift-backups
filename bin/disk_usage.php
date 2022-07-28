#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift();
$module = 'vps';
$settings = \get_module_settings($module);
$db = get_module_db($module);
        $ids = [];
        $data = [];
        $db->query("select {$settings['PREFIX']}_id,{$settings['PREFIX']}_hostname from " . $settings['TABLE'] . " where {$settings['PREFIX']}_status != 'pending' and {$settings['PREFIX']}_server_status != 'deleted'", __LINE__, __FILE__);
        while ($db->next_record(MYSQL_ASSOC)) {
            $data[$db->Record[$settings['PREFIX'].'_id']] = $db->Record;
            $ids[] = $db->Record[$settings['PREFIX'].'_id'];
        }
        $repos = [
            ['username' => SWIFT_OPENVZ_USER, 'password' => SWIFT_OPENVZ_PASS],
            ['username' => SWIFT_KVM_USER, 'password' => SWIFT_KVM_PASS]
        ];
        $backups = [];
        foreach ($repos as $repo) {
            $response = $sw->authenticate($repo['username'], $repo['password']);
            $ls_output = explode("\n", $sw->ls());
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
                    if (count($matches) > 0) {
                        foreach ($matches as $match) {
                            $usage = $sw->usage($settings['PREFIX'] . $id.'/'.$match);
                            $backups[] = $vps[$settings['PREFIX'].'_id'].':'.$match;
                            $sizes[$id.':'.$match] = $usage['Content-Length'];
                            if (!is_numeric($usage['Content-Length'])) {
                                myadmin_log('scripts', 'info', var_export($usage, true), __LINE__, __FILE__, $module);
                            }
                            echo $vps['vps_hostname'] . '	' . $match . '	' . (isset($usage['Content-Length']) ? Scale($usage['Content-Length'], 'bytes', 1) : '').PHP_EOL;
                        }
                    }
                }
            }
        }
