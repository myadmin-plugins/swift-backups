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

    /*

    //headers can verify creation
    //HTTP/1.1 201 Created will show if created
    public function makedir() {

    if [ "$1" = "" ]; then
    echo 'Usage ./ismkdir container';
    else
    CONTAINER="${1}";
    URL=`urlencode "${CONTAINER}"`;
    ${curl} $CURLOPTS -X PUT -D - -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/""${URL}"
    fi

    }

    public function delete_after() {
    if [ "$1" = "" -o "$2" = "" -o "$3" = ""  ]; then
    echo 'Usage ./deleteafter container file XX (number of days)';
    else
    CONTAINER="${1}";
    FILE="${2}";

    TIME=`echo "${3} * 86400" | $bc -l`;
    if [ "$TIME" = "" ]; then
    echo "Did not get a proper value for time (non integer used) at $LINENO";
    exit;
    fi

    URL=`urlencode "${CONTAINER}/${FILE}"`;
    ${curl} $CURLOPTS -X PUT -H "X-Delete-After: $TIME" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/$URL" --data-binary ''
    fi

    }



    // test size, if we are 5GB or over display error to use split
    public function check_size() {
    // 5368709120 is 5gb in bytes, we are under by 1 byte for swift
    size=5368709119;
    //size=100;

    FILE="${1}";

    if [ "$FILE" = "" ]; then
    echo "File name missing in function check_size at $LINENO";
    exit;
    fi

    if [ ! -f $FILE ]; then
    echo "File $FILE does not exist in function check_size at $LINENO";
    exit;
    fi

    // ls is quicker
    if [ -x /bin/ls ]; then
    file_size=`/bin/ls -l $FILE | awk '{print $5}'`;
    elif [ -x /usr/bin/du ]; then
    file_size=`/usr/bin/du $FILE | awk '{print $1}'`;

    else
    echo "No programs to check disk space in check_size at $LINENO";
    exit;
    fi

    if [ "$file_size" -gt "$size" ]; then
    echo "$FILE size is $file_size which is over 5GB. Must use the split function";
    split $2 $FILE $3
    else
    if [ "$debug" = "1" ]; then
    echo "Found file size $file_size for file $FILE in function check_size";
    fi
    fi

    }

    public function display_storage_url() {
    echo
    echo "Storage URL (pub/private): ${blue}$STORAGE_URL${normal}"
    echo
    }

    public function makepublic() {
    if [ "$1" = "" ]; then
    echo 'Usage ./mkpub container [optional --remove (to make private)]';
    echo '[optional --dirlist (to allow directory listings)]';
    exit;
    fi


    CONTAINER="${1}";
    if [ "$2" = "--remove" ]; then
    ${curl} $CURLOPTS -X PUT -H 'X-Container-Read: \n*' -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/""${CONTAINER}"
    display_storage_url
    exit;
    elif [ "$2" = "--dirlist" ]; then
    ${curl} $CURLOPTS -X PUT -H 'X-Container-Read: .r:*,.rlistings' -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/""${CONTAINER}"
    display_storage_url
    exit;
    // default call
    elif [ "$2" = "" ]; then
    ${curl} $CURLOPTS -X PUT -H 'X-Container-Read: .r:*' -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/""${CONTAINER}"
    display_storage_url
    else
    echo 'Invalid call to mkpub';
    display_storage_url
    exit;
    fi

    }

    public function getstats() {
    ${curl} -v $CURLOPTS -X HEAD -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL" 2>&1 | grep X-Account | cut -d" " -f2-
    cat $file
    }

    public function rcopy
    {

    if [ "$1" = "" ]; then
    echo 'Usage ./iscp container file newfile';
    else
    CONTAINER=$1;
    FILE=$2;
    NEWFILE=$3;

    ${curl} -v $CURLOPTS -X COPY -H "Destination: ${CONTAINER}/${NEWFILE}" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${FILE}"
    fi
    }


    public function rmove
    {

    if [ "$1" = "" ]; then
    echo 'Usage ./ismv container newcontainer file';
    else
    CONTAINER=$1;
    NEWCONTAINER=$2;
    FILE=$3;

    ${curl} -v $CURLOPTS -X COPY -H "Destination: ${NEWCONTAINER}/${FILE}" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${FILE}"
    sleep 1s;
    echo
    echo -n 'Delete old file? [y/n]';
    read check
    if [ "$check" = "y" ]; then
    delete ${CONTAINER} ${FILE} else
    echo 'Skipping deletion';
    fi
    fi
    }


    public function download() {
    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./isrm container file [optional -f]';
    else
    FILE=$2;
    CONTAINER=$1;

    if [ -d "${FILE}" ]; then
    echo "Directory with same name exists, can not download";
    return;
    fi

    if [ -f "${FILE}" ]; then
    if [ "$3" = "-f" ]; then
    if [ "$debug" = "1" ]; then
    echo "Downloading file ${FILE} with force";
    fi

    else
    echo "File with same name exists, can not download with out -f";
    return;
    fi
    fi

    ${curl} $CURLOPTS -o ${FILE} -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${FILE}"
    fi

    }

    //delete file, supports email
    public function delete() {

    // curl -D . -X DELETE -H "X-Auth-Token: yourAuthToken" -H "X-Purge-Email: your@email.address"  https://cdn1.clouddrive.com/v1/yourAccountHash/bar/foo.txt
    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./isrm container file';
    else
    FILE=$2;
    CONTAINER=$1;
    ${curl} -v $CURLOPTS -o/dev/null -X DELETE -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${FILE}"
    fi
    }

    public function rrmdir() {

    if [ "$1" = "" ]; then
    echo 'Usage ./isdir container [ container must be empty ]';
    else
    CONTAINER=$1;
    ${curl} $CURLOPTS -v -o/dev/null -X DELETE -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}"
    fi

    }

    public function getcontenttype() {
    FILE=$1;
    if [ ! -e "${FILE}" ]; then
    echo "Failed on getting content type of file ${FILE} at line $LINENO file does not exist";
    exit;
    fi
    ${FILECOMMAND} -bi ${FILE}
    }

    public function getmd5() {
    FILE=$1;
    if [ ! -f "${FILE}" ]; then
    echo "Ran into a problem in getmd5 function, file not found or is a directory";
    exit;
    fi
    $md5prog "${FILE}" | awk '{print $1}'
    }

    public function urlencode() {

    python -c "import urllib; print urllib.quote('$*')"

    }

    public function getremotemd5() {
    FILE=$2;
    CONTAINER=$1;
    // remove final /r
    //encoded_value=$(python -c "import urllib; print urllib.quote('''{$value}''')")
    URL=`urlencode "${CONTAINER}/${FILE}"`;
    ${curl} -s $CURLOPTS -I -H "X-Auth-Token: ${APIKEY}" "${STORAGE_URL}/${URL}" | grep ^Etag: | awk '{print $2}' | tr -d '\r'

    }

    public function rsyncfile() {
    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./rsync container file [args]';
    echo 'optional args: --put (upload file) add new file name after --put for rename';
    echo 'optional args: --get (download file)';
    echo 'optional args: --check (checks md5sum for remote / localfile) add new file name after --check for a renamed file';
    echo 'optional args: --dirput (switch file to directory, and sync directory to remote system - does not support sub directories yet )';
    echo 'optional args: --dirget (switch file to directory, and sync directory to local system) - does not support sub directories yet';

    // end help / function here
    exit;
    fi

    // check file size
    // 2 is file, 1 is container 3 is optional
    check_size $2 $1 $3

    // download
    if [ "$3" = "--get" ]; then
    FILE=$2;
    CONTAINER=$1;
    if [ -d "${FILE}" ]; then
    echo "File: ${FILE} is a directory, use --dirget";
    exit;
    //if file does not exist, download and exit
    elif [ ! -f "${FILE}" ]; then
    echo "File: ${FILE} does not exist, downloading";
    download ${CONTAINER} ${FILE}
    exit;
    fi
    localetag=`getmd5 ${FILE}`;
    remoteetag=`getremotemd5 ${CONTAINER} ${FILE}`;
    if [ "$debug" = "1" ]; then
    echo "Found etagremote: ${remoteetag} and found etaglocal $localetag";
    fi

    if [ "$localetag" = "$remoteetag" ]; then
    echo "Checksums match for container: ${CONTAINER} file ${FILE}";
    else
    download ${CONTAINER} ${FILE} -f
    fi
    // md5 verify
    elif [ "$3" = "--check" ]; then
    FILE=$2;
    CONTAINER=$1;
    if [ ! "$4" = "" ]; then
    REMOTEFILE=$4;
    else
    REMOTEFILE=$2;
    fi
    localetag=`getmd5 ${FILE}`;
    remoteetag=`getremotemd5 ${CONTAINER} ${REMOTEFILE}`;

    if [ "$localetag" = "$remoteetag" ]; then
    echo "Checksum $localetag matches for ${REMOTEFILE} ";
    else
    echo "No match found on ${REMOTEFILE}. Local: $localetag and Remote $remoteetag";
    fi

    // fix me add support for directories
    elif [ "$3" = "--dirput" ]; then
    CONTAINER=$1;
    FILE=$2;

    if [ ! -d "${FILE}" ]; then
    echo "Error: ${FILE} is not a directory or does not exist";
    fi

    check_container_exists ${CONTAINER}

    cd ${FILE}
    for filenames in *; do
    if [ -f "$filenames" ]; then
    // escaped to allow spaces
    rsyncfile ${CONTAINER} "$filenames" --put
    else
    echo "Skipping $filenames directories are not supported yet";
    fi
    done



    elif [ "$3" = "--dirget" ]; then
    echo
    elif [ "$3" = "--put" ]; then
    // default upload function
    FILE=$2;
    CONTAINER=$1;
    if [ ! -f "${FILE}" ]; then
    echo "File: ${FILE} does not exist or is a directory";
    return;
    fi

    if [ ! "$4" = "" ]; then
    REMOTEFILE=$4;
    else
    REMOTEFILE=$2;
    fi

    localetag=`getmd5 "${FILE}"`;
    remoteetag=`getremotemd5 ${CONTAINER} "${REMOTEFILE}"`;
    //eval remoteetag=$(${curl} -s $CURLOPTS -I -H "X-Auth-Token: ${APIKEY}" $STORAGE_URL/${CONTAINER}/${FILE} | grep "^Etag:" | awk '{print $2}');

    if [ "$debug" = "1" ]; then
    echo "Found etagremote: ${remoteetag} and found etaglocal $localetag";
    fi

    if [ "$localetag" = "$remoteetag" ]; then
    echo "Checksums match for container: ${CONTAINER} file ${REMOTEFILE}";
    else
    upload ${CONTAINER} "${FILE}" $4
    fi
    else
    rsyncfile
    fi
    }

    //upload file, we need to remove slases in the future
    public function upload() {
    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./isput container file [newfilename]';
    echo 'newfilename is optional';
    else
    //FILE=$2;
    FILE=`basename ${2}`;
    CONTAINER=$1;
    check_size ${FILE}
    etag=`getmd5 ${FILE}`;
    contenttype=`getcontenttype ${FILE}`;
    check_container_exists ${CONTAINER}
    if [ "$debug" = "1" ]; then
    echo "Returned MD5checksum $etag of ${FILE}";
    fi

    if [ ! "$3" = "" ]; then
    REMOTEFILE=$3;
    else
    REMOTEFILE=$2;
    fi

    ${curl} -v $CURLOPTS -o/dev/null -f -X PUT -T "${FILE}" -H "ETag: ${etag}" -H "Content-Type: ${contenttype}" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${REMOTEFILE}"
    fi
    }

    public function delete_splits() {

    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./isrmsplit container filename (must be full path)';
    else

    FILENAME=$2;
    CONTAINER=$1;
    check_container_exists ${CONTAINER}
    listsplits=`get_splits ${FILENAME}`;
    if [ "$listsplits" = "" ]; then
    echo "No split files found for ${FILENAME}";
    return;
    fi

    for SPLITNAME in $listsplits; do
    echo "Removing ${SPLITNAME} in container ${CONTAINER}":
    /admin/swift/isrm ${CONTAINER} ${SPLITNAME}
    done
    // delete from dq
    sqdelete ${FILENAME}

    fi
    }

    //split into smaller files, default 1000M
    // call FILE FILENAME because split filter uses FILE
    public function mb_split() {
    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./split container file (optional size in MB, default 1000 (1GB)';
    else

    if [ ! -x ${splitprog} ]; then
    echo "Missing split binary at $LINENO";
    exit;
    fi

    FILENAME=$2;
    CONTAINER=$1;
    check_container_exists ${CONTAINER}

    if [ "$3" = "" ]; then
    bytes=1000;
    else
    bytes=$3;
    fi

    // just check feature, don't upload
    if [ "$3" = "--check" -o "$4" = "--justcheck" ]; then
    justcheck=1;
    fi

    if [ ! -f ${FILENAME} ]; then
    echo "File ${FILENAME} does not exist at $LINENO";
    exit;
    fi

    // check db md5sums
    md5check=`check ${FILENAME}`;

    // we conocate all md5's into a new string
    buildmd5='';
    // if something returns we have uploaded this in the past
    if [ ! "$md5check" = "" ]; then
    for md5 in $md5check; do
    addmd5=`echo $md5 | cut -d"|" -f2`;
    buildmd5="${buildmd5}${addmd5}";
    done
    finalmd5=`echo -n $buildmd5 | ${md5prog} | awk '{print $1}'`;
    STRIPFILE=`basename ${FILENAME}`;
    rmd5=`getremotemd5 ${CONTAINER} ${STRIPFILE}`;
    //remotemd5 returns in " "
    if [ \"$finalmd5\" = $rmd5 ]; then
    echo "MD5sum for container $CONTAINER and large object file $FILENAME matches, confirming local checksum";

    localsum=`getmd5 ${FILENAME}`;
    localdbsum=`checkmain ${FILENAME}`;
    if [ "$localsum" = "$localdbsum" ]; then
    echo "Local md5sum for ${FILENAME} matches record in db";
    exit;
    else
    echo -n "Local md5sum for ${FILENAME} as $localsum did not match record in the DB $localdbsum, ";
    if [ "$justcheck" = "1" ]; then
    echo "exiting";
    exit;
    else
    echo "upload will continue";
    fi
    fi
    else
    echo "MD5sum for container $CONTAINER and large object file $FILENAME do not match, upload will continue";
    // delete remotely and from db
    delete_splits ${CONTAINER} ${FILENAME}
    fi
    fi

    cat ${FILENAME} | ${splitprog} -d --bytes=${bytes}M --filter="${curl} -v $CURLOPTS -o/dev/null -f -X PUT -T - -H \"X-Auth-Token: ${APIKEY}\" \"$STORAGE_URL/${CONTAINER}/${FILENAME}/split-\${FILE}\""
    ${curl} -v $CURLOPTS -o/dev/null -X PUT -H "X-Object-Manifest: ${CONTAINER}/${FILENAME}/" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${FILENAME}" --data-binary ''

    build_split_db ${CONTAINER} ${FILENAME}



    fi

    }

    public function onthefly() {

    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./fly container directory [delete|auto]';
    echo 'All / in the file name will be stripped out of the remotely stored filename';
    exit;
    fi

    FILENAME=$2;
    CONTAINER=$1;
    SAVENAME=`basename ${FILENAME}`;
    //SAVENAME=`echo ${FILENAME} | tr -d '/'`;

    check_container_exists ${CONTAINER}

    if [ "$3" = "delete" ]; then
    for flyfiles in `${curl} $CURLOPTS -s -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL"/"${CONTAINER}" 2>&1 | grep "${SAVENAME}/" | grep ".*\/.*-.*"`; do
    if [ "$flyfiles" = "" ]; then
    echo "No on the fly files returned";
    else
    echo /admin/swift/isrm ${CONTAINER} $flyfiles
    fi
    done
    else

    if [ -d $FILENAME ]; then
    echo "$FILENAME is a directory, we will be tar/gziping this on the fly to swift";

    // if $3 = auto we will remove conflicting files (for cron)
    checkif_fly__exists ${CONTAINER} ${SAVENAME} $3
    sleep 4s;

    ${tar} -c ${FILENAME} | ${gzip} -1c | ${splitprog} -d --bytes=1000M --filter="${curl} -v $CURLOPTS -o/dev/null -f -X PUT -T - -H \"X-Auth-Token: ${APIKEY}\" \"$STORAGE_URL/${CONTAINER}/${SAVENAME}/fly-\${FILE}\""
    ${curl} -v $CURLOPTS -o/dev/null -X PUT -H "X-Object-Manifest: ${CONTAINER}/${SAVENAME}/" -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL/${CONTAINER}/${SAVENAME}" --data-binary ''
    else
    echo "$FILENAME is not a directory";
    fi
    fi

    }

    public function build_split_db() {

    if [ "$1" = "" -o "$2" = "" ]; then
    echo 'Usage ./build_split_db container file';
    exit;
    fi

    FILENAME=$2;
    CONTAINER=$1;

    localmd5=`getmd5 ${FILENAME}`;
    for splitfiles in `${curl} $CURLOPTS -s -H "X-Auth-Token: ${APIKEY}" "$STORAGE_URL"/"${CONTAINER}" 2>&1 | grep "${FILENAME}/" | grep "/split-"`; do
    if [ "$splitfiles" = "" ]; then
    echo "function build_split_db returned blank vlue at $LINENO";
    exit;
    else
    splitsum=`getremotemd5 ${CONTAINER} ${splitfiles}`;
    insert ${FILENAME} $splitfiles $splitsum
    fi
    done

    // main checksum
    insertmain ${FILENAME} $localmd5
    }

    //
    public function check_container_exists() {
    // quick check to see if we exist
    check=`${curl} $CURLOPTS -s -H "X-Auth-Token: ${APIKEY}" $STORAGE_URL/$1 | grep "The resource could not be found."`;
    if [ ! "$check" = "" ]; then

    if [ "$2" = "--force" ]; then
    echo "Container $1 does not exist, creating";
    makedir $1
    else
    echo "Container $1 does not exist. Use ismkdir to create or run with --force";
    exit;
    fi
    fi


    }

    public function listcontainers() {
    if [ "$1" = "" ]; then
    for dir in `${curl} $CURLOPTS -s -H "X-Auth-Token: ${APIKEY}" $STORAGE_URL | sort`; do
    printf "${green}$dir${normal}\n";
    done
    else
    if [ "$debug" = "1" ]; then
    echo "Listing container $1"
    fi
    ${curl} $CURLOPTS -s -H "X-Auth-Token: ${APIKEY}" $STORAGE_URL/$1
    fi
    }
    */
}
