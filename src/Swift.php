<?php

/**
 * Class Swift
 */
class Swift
{
    /**
     * @var string
     */
    private $auth_url = SWIFT_AUTH_URL;
    /**
     * @var string
     */
    private $v1_auth_url = SWIFT_AUTH_V1_URL;
    /**
     * @var string
     */
    private $admin_user = SWIFT_ADMIN_USER;
    /**
     * @var string
     */
    private $auth_key = SWIFT_ADMIN_KEY;
    /**
     * @var string
     */
    private $storage_url;
    /**
     * @var string
     */
    private $storage_token;
    /**
     * @var array
     */
    private $options;
    /**
     * @var array
     */
    private $args;

    /**
     *
     */
    public function __construct()
    {
        $this->options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Admin-User:'.$this->admin_user, 'X-Auth-Admin-Key:'.$this->auth_key],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        $this->args = [];
    }

    /**
     * @param bool|string $username
     * @param bool|string $password
     * @return array|bool
     */
    public function authenticate($username = false, $password = false, $retry = 10)
    {
        if ($username === false) {
            $username = $this->admin_user.':'.$this->admin_user;
        }
        if ($password === false) {
            $password = $this->auth_key;
        }
        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['X-Storage-User:'.$username, 'X-Storage-Pass:'.$password],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        $response = '';
        $try = 0;
        while (!preg_match_all('/^X\-Storage\-\w+: .*$/m', $response) && $try < $retry) {
            $try++;
            //echo "{$this->v1_auth_url} Authenticate Attempt {$try}\n";
            $response = getcurlpage($this->v1_auth_url, '', $options);
            if (preg_match_all('/^X\-Storage\-\w+: .*$/m', $response)) {
                break;
            }
            //echo "Response: $response\n";
        }
        $lines = explode("\n", $response);
        $this->storage_url = false;
        $this->storage_token = false;
        foreach ($lines as $line) {
            if (preg_match('/^X\-Storage\-Url\: (.*)$/', $line, $matches)) {
                $this->storage_url = trim($matches[1]);
            }
            if (preg_match('/^X-Storage-Token: (.*)$/', $line, $matches)) {
                $this->storage_token = trim($matches[1]);
            }
            if ($this->storage_token !== false && $this->storage_url !== false) {
                break;
            }
        }
        if ($this->storage_token === false && $this->storage_url === false) {
            return false;
        }
        return [$this->storage_url, $this->storage_token];
    }

    /**
     * @return string
     */
    public function get_url()
    {
        return $this->storage_url;
    }

    /**
     * @return string
     */
    public function get_token()
    {
        return $this->storage_token;
    }

    public function set_v1_auth_url($url)
    {
        $this->v1_auth_url = $url;
    }

    /**
     * @param string $container
     * @param string $read
     * @param string $write
     * @return array|string
     */
    public function acl($container = '', $read = '', $write = '')
    {
        $container = trim($container);
        if ($container == '') {
            return false;
        }
        $options = [
            //CURLOPT_HEADER => true,
            //CURLOPT_NOBODY => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'PUT'
        ];
        if ($read != '') {
            $options[CURLOPT_HTTPHEADER][] = 'X-Container-Read: '.$read;
        }
        if ($write != '') {
            $options[CURLOPT_HTTPHEADER][] = 'X-Container-Write: '.$write;
        }
        //print_r($options);echo '<br>';
        $response = getcurlpage($this->storage_url.'/'.$container, '', $options);
        myadmin_log('backups', 'info', $response, __LINE__, __FILE__);
        //echo 'Response:<br>'.$response  . "<br>";
        preg_match_all('/^(.*): (.*)$/m', $response, $matches);
        $response = [];
        foreach ($matches[1] as $idx => $key) {
            if (!in_array(
                $key,
                [
                'Accept-Ranges',
                'X-Trans-Id',
                'Date'
            ]
            )) {
                $response[$key] = trim($matches[2][$idx]);
            }
        }
        //print_r($response);
        return $response;
    }

    /**
     * @param string $container
     * @return array|string
     */
    public function usage($container = '')
    {
        $options = [
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        $response = getcurlpage($this->storage_url.'/'.$container, '', $options);
        preg_match_all('/^(.*): (.*)$/m', $response, $matches);
        $response = [];
        foreach ($matches[1] as $idx => $key) {
            if (!in_array(
                $key,
                [
                'Accept-Ranges',
                'X-Trans-Id',
                'Date'
            ]
            )) {
                $response[$key] = trim($matches[2][$idx]);
            }
        }
        //print_r($response);
        return $response;
    }

    /**
     * @param string $container
     */
    public function download_passthrough($container = '')
    {
        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        getcurlpage($this->storage_url.'/'.$container, '', $options);
    }

    /**
     * @param $container
     * @param $filename
     * @return string
     */
    public function upload($container, $filename)
    {
        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'X-Auth-Token:'.$this->storage_token,
                'ETag:'.md5_file($filename),
                'Content-Type:'.mime_content_type($filename)
            ],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'PUT'
        ];
        if (isset($_SERVER['HTTP_X_FILE_NAME'])) {
            $dest_filename = $_SERVER['HTTP_X_FILE_NAME'];
        } else {
            $dest_filename = $filename;
        }
        $postfields = '@'.$filename;
        $response = getcurlpage($this->storage_url.'/'.$container.'/'.$dest_filename, '', $options);
        return $response;
    }

    /**
     * @param $container
     * @return string
     */
    public function delete($container)
    {
        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'DELETE'
        ];
        //echo "URL: {$this->storage_url}/{$container}\n".var_export($options, TRUE).PHP_EOL;
        $response = getcurlpage($this->storage_url.'/'.$container, '', $options);
        return $response;
    }

    /**
     * @param string $container
     * @return string
     */
    public function ls($container = '')
    {
        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        $response = trim(getcurlpage($this->storage_url.'/'.$container, '', $options));
        $return = $response;
        $lines = explode("\n", $response);
        while (count($lines) == 10000) {
            $response = trim(getcurlpage($this->storage_url.'/'.$container.'?marker='.$lines[9999], '', $options));
            $return .= "\n".$response;
            $lines = explode("\n", $response);
        }
        return $return;
    }

    /**
     * @param string $container
     * @return string
     */
    public function ls_header($container = '')
    {
        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['X-Auth-Token:'.$this->storage_token],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        $response = getcurlpage($this->storage_url.'/'.$container, '', $options);
        return $response;
    }

    /**
     * @return mixed
     */
    public function list_accounts()
    {
        $response = getcurlpage($this->auth_url, $this->args, $this->options);
        return json_decode($response, true);
    }

    /**
     * @param $account
     * @return mixed
     */
    public function list_account($account)
    {
        $response = getcurlpage($this->auth_url . $account, $this->args, $this->options);
        return json_decode($response, true);
    }

    /**
     * @param $account
     * @param $user
     * @return mixed
     */
    public function list_user($account, $user)
    {
        $response = getcurlpage($this->auth_url . $account.'/'.$user, $this->args, $this->options);
        return json_decode($response, true);
    }

}
