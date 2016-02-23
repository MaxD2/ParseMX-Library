<?php
/**
 * ParseMX - fast web data retrieval functions
 *
 * @author: MaxD - max@bukrek.net
 * @version: 1.08
 */

$replace_file = 'replace.txt';


// ======================== MySQL
// Connect and work with MySQL databases with less code possible. Databases are considered UTF-8.
// If database doesn't exist, it is created automatically

$q_database = "parsemx";
$q_user = "root";
$q_password = "root";
$q_server = "localhost";
$q_port = 3306;

// Execute query, returns array of result rows. Connects to database, if not connected.
// If result rows consist of only one column, you will get just an array of this column values.
function qq($query) {
    global $q_connection;
    if (!$q_connection) q_connect();

    $result = $q_connection->query($query);

    if (!$q_connection->errno) {
        if ($result instanceof \mysqli_result) {
            $data = array();

            while ($row = $result->fetch_assoc()) {
                if (count($row)==1) $row = reset($row);
                $data[] = $row;
            }

            $result->close();

            re($data);
            return $data;
        } else {
            return re(true);
        }
    } else {
        if ($q_connection->errno == 2006) {
            q_disconnect();
            $data = qq($query);
            re($data);
            return $data;
        }
        xwarn("SQL Error: ".$q_connection->error);
        xnotice("<font color='grey'>SQL Query:</font> ".$query);
        die;
    }
}

// Execute query, returns first row from result. Connects to database, if not connected.
// If result row consist of only one column, you will get just value of this column.
function q($query) {
    $r = qq($query);
    if (is_array($r)) $r = reset($r);
    else $r = false;
    re($r); return $r;
}

// Escapes text and adds '' to it.
function q_escape($text) {
    global $q_connection;
    if (!$q_connection) return "'".str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $text)."'";
    return "'".$q_connection->real_escape_string($text)."'";
}

// Returns number of last query affected rows
function q_affected() {
    global $q_connection;
    return re($q_connection->affected_rows);
}


// Returns id of the entity inserted by last query
function q_last_id() {
    global $q_connection;
    return re($q_connection->insert_id);
}

// q_connect and q_disconnect are not meant be called directly, for internal use

function q_connect() {
    global $q_connection;
    if ($q_connection) return;

    global $q_database, $q_user, $q_password, $q_server, $q_port;

    $q_connection = new mysqli($q_server, $q_user, $q_password, $q_database, $q_port);
    if ($q_connection->connect_errno) {
        if ($q_connection->connect_errno == 1049) {
            // Database doesn't exists, trying to create.
            xnotice("Creating database <b>$q_database</b>...");
            $q_connection = new mysqli($q_server, $q_user, $q_password, false, $q_port);
            q("CREATE DATABASE `$q_database` CHARACTER SET utf8 COLLATE utf8_general_ci");
            q("USE `$q_database`");
        } else die;
    }

    $q_connection->set_charset("utf8");
    q("SET SQL_MODE = ''");

    re();
}

function q_disconnect() {
    global $q_connection;
    if (!$q_connection) return;

    $q_connection->close();
    $q_connection = null;
    re();
}



// ======================== UTF-8 String Functions
// TODO: Fallback when MB_STRING not installed

function upcase($text) {
    $text = mb_strtoupper($text, 'UTF-8');
    re($text); return $text;
}

function lowcase($text) {
    $text = mb_strtolower($text, 'UTF-8');
    re($text); return $text;
}

// This functions are equivalents of regular PHP functions

function stripos8($haystack, $needle, $offset = 0) {
    return mb_stripos($haystack, $needle, $offset, "UTF-8");
}

function strlen8($text) {
    return mb_strlen($text, 'UTF-8');
}

function substr8($string, $start, $length) {
    return mb_substr($string, $start, $length, 'UTF-8');
}



// ======================== Script Execution Control

// Indicates that your script is live and gives it another 5 minutes (by default) to execute.
// Call it from some long-running cycles
function script_live() {
    static $last_live;
    if ($last_live>time()-60) return;
    $last_live = time();

    global $mx_check_script_duplicate_name, $mx_script_timeout_mins;

    if (!$mx_check_script_duplicate_name) $mx_script_timeout_mins = 5;
    else mx_config_set(g('mx_check_script_duplicate_name') . "_runtime", time());
    set_time_limit(g('mx_script_timeout_mins')*60-20);
}

// If another instance of your script is already working, this Function will finish current script.
// Call it at the beginning of your script, if you are invoking it with CRON.
function script_check_duplicate($name = false, $timeout_mins = 5 ) {
    $timeout = $timeout_mins * 60;
    if (!$name) $name = "parsemx";
    $pid = mx_config_get($name . "_pid");
    if ($pid)
        if (!posix_getsid($pid)) $pid = false;
    if ($pid and $timeout) {
        $time = mx_config_get($name . "_runtime");
        if ($time < time() - $timeout) {
            // Kill old copy
            posix_kill($pid,9);
            $pid = false;
        }
    }
    if ($pid) die("Script _$name already working, exiting.");
    mx_config_set($name . "_pid", getmypid());
    if ($timeout) mx_config_set($name . "_runtime", time());
    global $mx_check_script_duplicate_name, $mx_script_timeout_mins;
    $mx_check_script_duplicate_name = $name;
    $mx_script_timeout_mins = $timeout_mins;
    ignore_user_abort(true);
}



// ======================== Config Values Storage

$mx_config_file = 'config.mx';


// Set config value, $value may be any type of variable
function mx_config_set($key, $value = false) {
    if ($value===false) $value = 0;
    elseif ($value===true) $value = 1;
    if (is_string($value)) $value = '"'.$value.'"';
    elseif (!is_numeric($value)) $value = 'unserialize("'.serialize($value).'")';

    global $mx_config_file;
    $lines = @file($mx_config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    if ($lines)
    foreach ($lines as $i=>$line) {
        $name = inside("$", "=",$line);
        if ($name!=$key) continue;
        $found = true;
        if ($value) $lines[$i] = '$'.$name.' = '.$value.';';
        else unset($lines[$i]);
    }
    if (!$found and $value) $lines[] = '$'.$key.' = '.$value.';';
    file_put_contents($mx_config_file, implode("\n",$lines));
    re();
}

// Get config value
function mx_config_get($key) {
    global $mx_config_file;
    $lines = @file($mx_config_file, FILE_IGNORE_NEW_LINES & FILE_SKIP_EMPTY_LINES);
    if ($lines)
        foreach ($lines as $i=>$line) {
            $name = inside("$", "=",$line);
            if ($name!=$key) continue;
            $value = inside("=","",$line);
            if (substr($value,-1)==";") $value = substr($value,0,-1);
            if (substr($value,0,13)=='unserialize("') $value = unserialize(substr($value,13,-2));
            elseif (substr($value,0,1)=='"') $value = substr($value,1,-1);
            re($value); return $value;
        }
    re(false); return false;
}

// ======================== Hash Cache

$hash_cache_folder = "cache";
$hash_cache_maxtime = 2 /* Hours */ * 60 * 60;
define('hash_cache_delimeter','@#$hash@@*%');

function save_hash_cache($key, $data) {
    global $hash_cache_folder;
    if (!file_exists($hash_cache_folder)) mkdir($hash_cache_folder);

    $free_space = @disk_free_space($hash_cache_folder);
    if ($free_space>1 && $free_space<500*1024*1024) return; // Do not save cache if free space is less then 500 Mb

    $hash = md5($key);
    $folder = $hash_cache_folder."/".substr($hash,0,3);
    $file = $folder."/c".substr($hash,3);
    if (!file_exists($folder)) mkdir($folder);
    file_put_contents($file,$key.hash_cache_delimeter.$data);
}

function load_hash_cache($key) {
    global $hash_cache_folder, $hash_cache_maxtime;
    if (!file_exists($hash_cache_folder)) mkdir($hash_cache_folder);
    $hash = md5($key);
    $folder = $hash_cache_folder."/".substr($hash,0,3);
    $file = $folder."/c".substr($hash,3);
    if (!file_exists($folder)) mkdir($folder);

    $time = microtime(true);
    if (filemtime($folder)<$time-10*60) {
        // Lets delete old files
        $time -= $hash_cache_maxtime;
        foreach (glob($folder."/c*") as $ofile)
            if (filemtime($ofile) < $time) @unlink($ofile);
        touch($folder);
    }

    if (!file_exists($file)) return false;
    $data = file_get_contents($file);
    $p = strpos($data,hash_cache_delimeter);
    $key2 = substr($data,0,$p);
    if ($key!==$key2) return false;
    $data = substr($data,$p+strlen(hash_cache_delimeter));
    return $data;
}

function clear_hash_cache() {
    global $hash_cache_folder;
    foreach (glob($hash_cache_folder."/*") as $folder) {
        foreach (glob($folder . "/c*") as $ofile) @unlink($ofile);
        rmdir($folder);
    }
}

// ======================== MX_HTTP

$http_proxies_file = 'proxies.mx'; // File to save proxies list
$http_cookies_file = 'cookies.txt';

$http_curl_timeout = 20;

// Global result vars
$http_code = 200; // Last HTTP operation code
$http_html = "";

$http_user_agent = false; // FALSE = Google bot  TRUE = latest Chrome
$http_headers = array();
$http_cookies = false;
$http_referer = false;
$http_url_base = false;
$http_auth = false;
$http_proxy = false;
$http_content_type = false;
$http_encoding = false;

$mx_http_debug_proxies = false;
$http_use_proxies = false;

$http_cache = false;

// Get URL contents and return it
function http_get($url)
{
    global $http_code, $http_ohtml, $http_html;

    // Local file code
    $http_code = 200;
    if (!$url) { re(false); return false; }
    if (strpos($url,'://')===false) { // Local file specified
        if (file_exists($url)) {
            $http_html = file_get_contents($url);
            $http_ohtml = $http_html;
            set_source($http_html);
            re($http_html); return $http_html;
        }
        $http_code = 404;
        re(false); return false;
    }

    $url = if_inside('', '#', $url);

    // Retry and cache code
    if (substr($url,0,1)!="%") {
        if (g('http_cache')) {
            $data = load_hash_cache($url);
            if ($data) {
                $http_code = 200;
                $http_ohtml = $data;
                $http_html = flatty_html($data);

                global $http_url_base;
                $base = inside("href=","",inside("<base", ">", $http_html));
                if ($base) $base = substr($base,1,-1);
                else $base = $url;
                $base = if_inside("", "?",$base);
                if ($p=strrpos($base, '/')) $base = substr($base, 0, $p);
                if ($base) $http_url_base = $base."/";

                xlog("Fetching <a rel='nofollow' href='$url' target='_blank'>$url</a> (cache)");
                if (g('debug_nesting_level') and ini_get('display_errors')) { // Write result to output
                    mx_debug_fetch_result($http_html);
                }
                set_source($http_html);
                re($http_html); return $http_html;
            }
        }
        $n=3;
        if (strpos($url,'yandex.ru')) $n=10;
        for ($i=0;$i<$n;$i++) {
            $http_html = http_get("%".$url);
            if ($http_html || $http_code==404) { re($http_html); return $http_html; }
        }
        re(false); return false;
    }
    $url = substr($url,1); // Remove % marker

    // Proxy code
    global $http_proxy, $http_proxies_file;
    if (g('http_use_proxies') and !$http_proxy)
        if (! ($http_proxy = mx_config_get("proxy")) ) {
            clearstatcache();
            if (!file_exists($http_proxies_file) or (filemtime($http_proxies_file)<microtime(true)-24*60*60)) {
                xlog('Receiving fresh proxies list...');
                $proxies = @http_curl("http://parsemx.com/getmxp.php","url=http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",$http_proxies_file);
                if ($proxies) $proxies = file_get_contents($http_proxies_file);
                if ($proxies) xsuccess("Received ".round(strlen($proxies)/6). " proxies.");
            }
            $proxies = file_get_contents($http_proxies_file);
            $n = (int) (strlen($proxies)/6);

            // Testproxy URL
            $proxy_url = $url;
            if (strpos($url,'aliexpress.com')) $proxy_url = 'http://www.aliexpress.com/activities/';
            elseif (strpos($url,'translate.yandex.net/api/v1')) $proxy_url = 'http://translate.yandex.net/api/v1/tr.json';
            elseif ($p=strpos($url,'/',8)) $proxy_url=substr($url,0,$p+1);

            while (!$http_proxy) {
                xlog("Checking proxies...");
                // Get 20 proxies
                $xproxies = array();
                for ($i=0;$i<20;$i++) {
                    $p = rand(0,$n-1)*6;
                    $proxy = ord($proxies[$p]).".".ord($proxies[$p+1]).".".ord($proxies[$p+2]).".".ord($proxies[$p+3]).":".(ord($proxies[$p+4])+ord($proxies[$p+5])*256);
                    $xproxies[] = $proxy;
                }
                $http_proxy = testproxies($xproxies,$proxy_url);
                if ($http_proxy) {
                    xlog("Using proxy: ".$http_proxy);
                    mx_config_set("proxy", $http_proxy);
                    break;
                }
            }
        }

    $http_html = http_curl($url);
    $http_ohtml = $http_html;
    if (!$http_html) { re(false); return false; }
    if (g('http_cache')) {
        save_hash_cache($url,$http_html);
    }
    $http_html=flatty_html($http_html);
    set_source($http_html);
    re($http_html); return $http_html;
}

function http_post($url, $data) {
    global $http_html, $http_ohtml;
    $http_html = http_curl($url,$data);
    $http_ohtml = $http_html;
    if (!$http_html) { re(false); return false; }
    $http_html=flatty_html($http_html);
    set_source($http_html);
    re($http_html); return $http_html;
}

function http_ajax($url, $data) {
    global $http_html, $http_ohtml, $http_headers;
    $save_headers = $http_headers;
    if (empty($http_headers["X-Requested-With"]))
        $http_headers["X-Requested-With"] = "XMLHttpRequest";
    $http_html = http_curl($url,$data);
    $http_headers = $save_headers;

    $http_ohtml = $http_html;
    if (!$http_html) { re(false); return false; }

    if ($http_html[0] == "{" || $http_html[0] == "[")
        return re(json_decode($http_html));

    $http_html=flatty_html($http_html);
    set_source($http_html);
    re($http_html); return $http_html;
}

// Fetch file into folder and return its name if successful. $access_path is resulting path addition
function http_get_file($url, $save_path= '.', $access_path = false)
{
    if (substr($save_path,-1)!="/") {
        $path_parts = pathinfo($save_path);
        $save_path = $path_parts['dirname'] . '/';
        $name = $path_parts['filename'];
        $ext = @$path_parts['extension'];
    } else {
        $name = false;
        $ext = false;
    }

    if (!file_exists($save_path)) mkdir($save_path, 0777, true);

    if (!$name) {
        $l = parse_url($url);
        $name = stristr($l['path'], "/");
        $path_parts = pathinfo($name);
        $name = $path_parts['filename'];
        $ext = $path_parts['extension'];
    }
    if (!$name) $name = 'file';
    if (!$ext) $ext = 'jpg';

    $name = translit($name);


    $file = $save_path."file".random(10000000).".tmp";

    if (g('http_cache')) {
        global $http_code, $http_content_type;
        $data = load_hash_cache($url);
        if ($data) {
            $http_code = 200;
            $data = explode(hash_cache_delimeter,$data);
            $http_content_type = $data[0];
            file_put_contents($file, $data[1]);
            xlog("Fetching <a rel='nofollow' href='$url' target='_blank'>$url</a> (cache)");
            if (g('debug_nesting_level') and ini_get('display_errors')) { // Write result to output
                mx_debug_fetch_result($url);
            }
        } else {
            http_curl($url, '', $file);
            if (file_exists($file))
                save_hash_cache($url,$http_content_type.hash_cache_delimeter.file_get_contents($file));
        }
    } else http_curl($url, '', $file);

    if (!file_exists($file)) { re(false); return false; }
    $cext = if_inside("",";", inside("/","",g('http_content_type')));
    if ($cext) $ext = $cext;

    $add = ''; $count = 2;
    while (file_exists($file2 = $save_path.$name.$add.'.'.$ext))
        $add = '-'.$count++;
    rename($file,$file2);
    if ($access_path===false) $access_path = $save_path;

    $r = $access_path . $name . $add . '.' . $ext;
    re($r); return $r;
}

function domain($url, $top = false)
{
    $url = strtolower(trim($url));
    if (strpos($url, '://') !== false) {
        $url = parse_url($url);
        @$url = $url['host'];
    }
    if ($url[strlen($url) - 1] == '/')
        $url = substr($url, 0, -1);
    if (substr($url, 0, 4) == "www.")
        $url = substr($url, 4);

    if ($top) { // Get top domain
        $pos = strrpos($url,".");
        if ($pos!==false && $pos==strlen($url)-3) {
            $pos2 = strrpos($url,".", $pos-strlen($url)-1);
            if ($pos-$pos2<5) $pos = $pos2;
        }
        if ($pos!==false) {
            $pos = strrpos($url, ".", $pos-strlen($url)-1);
            if ($pos!==false) $url = substr($url, $pos+1);
        }
    }

    re($url); return $url;
}

$mx_http_code_messages = array(
    100 => "Continue",
    101 => "Switching Protocols",

    200 => "OK",
    201 => "Created",
    202 => "Accepted",
    203 => "Non-Authoritative Information",
    204 => "No Content",
    205 => "Reset Content",
    206 => "Partial Content",

    300 => "Multiple Choices",
    301 => "Moved Permanently",
    302 => "Found",
    303 => "See Other",
    304 => "Not Modified",
    305 => "Use Proxy",
    306 => "(Unused)",
    307 => "Temporary Redirect",

    400 => "Bad Request",
    401 => "Unauthorized",
    402 => "Payment Required",
    403 => "Forbidden",
    404 => "Not Found",
    405 => "Method Not Allowed",
    406 => "Not Acceptable",
    407 => "Proxy Authentication Required",
    408 => "Request Timeout",
    409 => "Conflict",
    410 => "Gone",
    411 => "Length Required",
    412 => "Precondition Failed",
    413 => "Request Entity Too Large",
    414 => "Request-URI Too Long",
    415 => "Unsupported Media Type",
    416 => "Requested Range Not Satisfiable",
    417 => "Expectation Failed",

    500 => "Internal Server Error",
    501 => "Not Implemented",
    502 => "Bad Gateway",
    503 => "Service Unavailable",
    504 => "Gateway Timeout",
    505 => "HTTP Version Not Supported",
    555 => "Download interrupted");

$mx_http_code_messages_groups = array(
    1 => "Informational",
    2 => "Successful",
    3 => "Redirection",
    4 => "Client Error",
    5 => "Server Error",
);

$http_user_agents = array(
    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/601.4.4 (KHTML, like Gecko) Version/9.0.3 Safari/601.4.4",
    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9",
    "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko",
    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.103 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.103 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:44.0) Gecko/20100101 Firefox/44.0",
    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.3; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.103 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/601.4.4 (KHTML, like Gecko) Version/9.0.3 Safari/601.4.4",
    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:44.0) Gecko/20100101 Firefox/44.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:44.0) Gecko/20100101 Firefox/44.0",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2486.0 Safari/537.36 Edge/13.10586",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; rv:43.0) Gecko/20100101 Firefox/43.0",
    "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.103 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.7 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.7",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.82 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:44.0) Gecko/20100101 Firefox/44.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
    "Mozilla/5.0 (iPad; CPU OS 9_2_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13D15 Safari/601.1",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0)"
);

function http_code_message($http_code = false) {
    global $mx_http_code_messages, $mx_http_code_messages_groups;
    if (!$http_code) $http_code = g('http_code');
    $group = (int) ($http_code / 100);
    if (empty($mx_http_code_messages_groups[$group])) $r = 'Unknown';
    elseif (empty($mx_http_code_messages[$http_code])) return $r = $mx_http_code_messages_groups[$group];
    else $r = $mx_http_code_messages_groups[$group].': '.$mx_http_code_messages[$http_code];

    re($r); return $r;
}

function http_getq($url) { // Quiet get
    $display = ini_get('display_errors');
    $log = ini_get('log_errors');
    ini_set('display_errors',0);
    ini_set('log_errors',0);
    $res = http_get($url);
    ini_set('display_errors',$display);
    ini_set('log_errors',$log);
    return $res;
}

function flatty_html($http_html) {
    // TAB
    $http_html=str_replace(chr(9)," ",$http_html);
    // ---- Flattening
    $http_html=replace("<!-*->","",$http_html);
    $http_html = preg_replace('/^\s+|\n|\r|\s+$/m', ' ', $http_html);
    // No double spaces
    $double=true;
    while ($double) $http_html=str_replace("  "," ",$http_html,$double);

    $http_html = preg_replace('/>\s*</', '><', $http_html);
    $http_html = preg_replace('/\s*>/', '>', $http_html);
    $http_html = preg_replace('/<\s*/', '<', $http_html);

    if (g('auto_replace')) $http_html = replace("","",$http_html);
    re($http_html); return $http_html;
}

// Test an array of proxies with MultiCURL
function testproxies($proxies,$url) {
    $timeout = 10;

    $chs=array();
    $mh = curl_multi_init();

    foreach ($proxies as $proxy) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.104 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, "https://www.google.com/search?q=new+goods+for+family&oq=new+goods+for+family&aqs=chrome.0.69i59.6188j0&sourceid=chrome&ie=UTF-8");
        curl_multi_add_handle($mh,$ch);
        $chs[]=$ch;
    }
    $active = 0;
    $start_time = microtime(true);
    do {
        if (microtime(true)-$start_time>$timeout) break;
        $status = curl_multi_exec($mh, $active);
        $info = curl_multi_info_read($mh);
        if (false !== $info) {
            $i = array_search($info['handle'],$chs);
            $proxy = $proxies[$i];
            $res = curl_getinfo($info['handle']);
            if (g('mx_http_debug_proxies'))
                xlog("Proxy: $proxy Result: <b>".$res['http_code']."</b>");
            if (($res['http_code']==200) or ($res['http_code']==302) or ($res['http_code']==404)) {
                // Test for blocked message
                $h=curl_multi_getcontent($info['handle']);
                if (g('mx_http_debug_proxies')) xlog("Content: ".htmlentities($h));
                if (find("detected unusual traffic",$h)) $h ="";
                $h=inside("<title>","</title>",$h);
                if ($h=='Ой!') $h='';
                if (strpos(strtoupper($h),'BLOCK')!==false) $h='';
                if (strpos(strtoupper($h),'CONFIGURATION')!==false) $h='';
                if (strpos($h,'403')!==false) $h='';
                $h2 = iconv('Windows-1251', 'UTF-8//IGNORE', $h);
                if ($h or strpos($url,'banggood.com')) return $proxy;
            }
        }
        sleep(0.1);
    } while ($status === CURLM_CALL_MULTI_PERFORM || $active);
    foreach ($chs as $ch) curl_close($ch);
    return false;
}


function mx_url_path_encode_callback($matches) {
    return urlencode($matches[0]);
}

function url_path_encode($url) { // Prepare URL for CURL fetch
    $url = str_replace('&amp;', '&', $url); $url = str_replace('&amp;', '&', $url);
    $url = str_replace(array(' '), array('%20'), $url);
    $chars = '$-_.+!*\'(),{}|\\^~[]`<>#%";/?:@&=';
    $pattern = '~[^a-z0-9' . preg_quote($chars, '~') . ']+~iu';
    $url = preg_replace_callback($pattern, 'mx_url_path_encode_callback', $url);
    re($url); return $url;
}

// Use CURL to get URL [ used by get() ] If file is specified, that contents are written to the file (and nothing returned).
function http_curl($url, $post = false, $file = false)
{

    $url = url_path_encode($url);
    xlog("Fetching <a rel='nofollow' href='$url' target='_blank'>$url</a>...");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_ENCODING , "");
    $user_agent = g('http_user_agent');
    if (!$user_agent) $user_agent = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
    elseif ($user_agent===true) $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36';
    elseif ($user_agent==="random")
        $user_agent = random($GLOBALS['http_user_agents']);

    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

    global $http_headers;
    if ($http_headers) {
        $hheader = array();
        foreach ($http_headers as $hname=>$hval)
            $hheader[] = $hname.': '.$hval;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hheader);
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, g('http_curl_timeout'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, g('http_curl_timeout'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    global $http_cookies;
    if (($http_cookies!==false) and ($http_cookies!==NULL)) curl_setopt($ch, CURLOPT_COOKIE, $http_cookies);
    else {
        curl_setopt($ch, CURLOPT_COOKIEFILE, g('http_cookies_file'));
        curl_setopt($ch, CURLOPT_COOKIEJAR, g('http_cookies_file'));
    }

    if (g('http_referer')) curl_setopt($ch, CURLOPT_REFERER, g('http_referer'));
    else if (g('http_url_base')) curl_setopt($ch, CURLOPT_REFERER, g('http_url_base'));

    $f = false;
    if ($file) { //save output to file
        $f = fopen($file, 'w');
        curl_setopt($ch, CURLOPT_FILE, $f);
    }

    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }

    if (g('http_auth')) curl_setopt($ch, CURLOPT_USERPWD, g('http_auth'));

    global $http_proxy;
    if ($http_proxy) curl_setopt($ch, CURLOPT_PROXY, $http_proxy);

    $http_html = curl_redir_exec($ch,$file,$f);

    if ($file) @fclose($f);
    if (@!filesize($file)) @unlink($file);

    global $http_code, $http_content_type;
    $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $http_content_type = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);

    if ($http_code==200 && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) > curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD)) {
        $http_code = 555;
        if ($file) @unlink($file);
    }

    curl_close($ch);

    if (($http_code != 200) and ($http_code != 404) and ($http_code != 410)) {
        $http_proxy = false;
        mx_config_set("proxy",false);
    }

    if ($http_code != 200 ) xnotice("HTTP Result ".$http_code." - ". http_code_message());

    if ($http_html) {
        // Detect charset
        $encoding = g('http_encoding');
        if (!$encoding) $encoding = inside("charset=","", $http_content_type);
        if (!$encoding) {
            preg_match('/charset\s*="?[\w-]*/i',$http_html,$find);
            if (!$find) preg_match('/encoding\s*="?[\w-]*/i',$http_html,$find);
            if ($find) {
                $find=$find[0];
                $find = trim(substr($find,strpos($find,'=')+1));
                if (substr($find,0,1)=='"') $find = substr($find,1);
                $encoding = $find;
            }
            if (!$encoding) $encoding = "UTF-8";
        }
        if (strtoupper($encoding)!="UTF-8") dmsg('Converting from encoding: '.$encoding);
        $http_html = @iconv($encoding, 'UTF-8//IGNORE', $http_html);
        $http_html = str_replace($encoding,'UTF-8',$http_html);
    }

    if (!$file) {
        global $http_url_base;
        $base = inside("href=", "", inside("<base", ">", $http_html));
        if ($base) $base = substr($base, 1, -1);
        else $base = $url;
        $base = if_inside("", "?", $base);
        if ($p = strrpos($base, '/')) $base = substr($base, 0, $p);
        if ($base)
            $http_url_base = $base . "/";
    }

    if (g('debug_nesting_level') and ini_get('display_errors')) { // Write result to output
        if (!$file) mx_debug_fetch_result($http_html);
        else mx_debug_fetch_result($url);
    }

    if ($file and file_exists($file)) { re(true); return true; }
    re($http_html); return $http_html;
}

function mx_debug_fetch_result($http_html) {
    static $id;
    if (!trim($http_html)) return;
    $id++;
    $m = "<a style='background-color:yellow' href='javascript: document.getElementById(\"fetch$id\").hidden = ! document.getElementById(\"fetch$id\").hidden'>
        [ Fetch Result ]</a>
        <div id='fetch$id' hidden='hidden'>";
    if (substr($http_html,0,7)=="http://")
        $m .= "<img src='$http_html' />";
    else $m .= "<code>" . debug_highlight(htmlspecialchars($http_html)) . "</code>";
    $m .= "</div><br/>";
    echo $m;
}

// Exec CURL with redirects helper

function curl_header_callback($curl_handler, $header_line) {
    global $mx_curl_save_header;
    $mx_curl_save_header .= $header_line;
    return strlen($header_line);
}

function curl_redir_exec($curl_handler, $file_name = false, $file_handler = false)
{
    static $curl_loops = 0;
    static $curl_max_loops = 20;
    if ($curl_loops++>$curl_max_loops) {
        $curl_loops = 0;
        return false;
    }
    global $mx_curl_save_header;
    $mx_curl_save_header = '';
    curl_setopt($curl_handler, CURLOPT_HEADERFUNCTION, 'curl_header_callback');
    $data = curl_exec($curl_handler);
    $header = $mx_curl_save_header;
    $http_code = curl_getinfo($curl_handler, CURLINFO_HTTP_CODE);
    if ($http_code == 301 || $http_code == 302)
    {
        if ($file_name) {
            fclose($file_handler);
            unlink($file_name);
            $file_handler = fopen($file_name, 'w');
            curl_setopt($curl_handler, CURLOPT_FILE, $file_handler);
        }
        $matches = array();
        $header = str_replace('-Location', '', $header);
        preg_match('/Location:(.*?)\n/', $header, $matches);
        if (empty($matches[1]))
        {
            //couldn't process the url to redirect to
            $curl_loops = 0;
            return $data;
        }
        $new_url = trim($matches[1]);
        if ($new_url) $new_url = url($new_url);
        curl_setopt($curl_handler, CURLOPT_URL, $new_url);
        dmsg("Redirected to <a rel='nofollow' target='_blank' href='$new_url'>$new_url</a>...");
        if (strpos($new_url,'showcaptcha')) {
            global $http_proxy, $http_code;
            $http_proxy = false;
            mx_config_set("proxy",false);
            $http_code = 503;
            return false;
        }
        return curl_redir_exec($curl_handler,$file_name,$file_handler);
    } else {
        $curl_loops=0;
        return $data;
    }
}

// ======================== Simple Data Retrieval

// Find the text inside the $start and $end.
//    If $start="" then start is start of content.
//    If $end="" then end is end of content.
function inside($start, $end="", $source = false)
{
    if ($source === false) { global $http_html; $source = &$http_html; } // Use set_source() default

    $r = '';
    if ($start) $s = stripos($source, $start);
    else $s=0;
    if ($s !== false) {
        $s += strlen($start);
        if ($end) $e = stripos($source, $end, $s);
        else $e = strlen($source);
        if ($e !== false)
            $r = trim(substr($source, $s, $e - $s));
    }

    re($r); return $r;
}

// Find inside, if not found - return original string

function if_inside ($start, $end="", $source) {
    $res = inside ($start,$end,$source);
    if ($res) { re($res); return $res; }
    else { re($source); return $source; }
}

// Find the texts inside the $start and $end
function insides($start, $end="", $source = false)
{
    if ($source === false) { global $http_html; $source = &$http_html; } // Use set_source() default

    $r = array(); $s=0;

    if (is_array($source)) {
        foreach ($source as $line)
            if ($start and $end) array_push($r,insides($start,$end,$line));
            else {
                $i = inside($start,$end,$line);
                if ($i) $r[] = $i;
            }
        re($r); return $r;
    }

    while ( ($s = strpos($source, $start,$s) ) !== false ) {
        $s += strlen($start);
        $e = strpos($source, $end, $s);
        if ($e !== false) {
            $r[] = trim(substr($source, $s, $e - $s));
            $s = $e + strlen($end);
        }
    }

    re($r); return $r;
}

// Reverse inside, first searches for $end
function rev_inside($start, $end, $source = false)
{
    if ($source === false) { global $http_html; $source = &$http_html; } // Use set_source() default

    $r = '';
    $e = strripos($source, $end);

    if ($e !== false) {
        $tail = substr($source, 0, $e);
        if ($start) $s = strripos($tail, $start);
        else $s = 0;
        if ($s !== false) {
            $s += strlen($start);
            $r = trim(substr($source, $s, $e - $s));
        }
    }
    re($r); return $r;
}

// Tells if $text is present at source. Supports comma-separated keywords, case insensitive
function find($text, $content = "-#default")
{
    if ($content=="-#default")
        $content = g('http_html'); // !!!!
    if (is_string($text) and strpos($text,",")) {
        $text = explode(",", $text);
        foreach ($text as $i=>&$xword) {
            $xword = trim($xword);
            if (!$xword) unset($text[$i]);
        }
    }

    if (is_array($content)) {
        foreach ($content as $i=>$elem)
            if (find($text,$elem)) { re(true); return true; }
        re(false); return false;
    }

    if (is_array($text)) {
        $nots = false;
        foreach ($text as $word) if (strpos($word,'~')!==false) {
            $nots = true;
            break;
        }

        if ($nots) {
            $r = false;
            $nots_good = true;
            foreach ($text as $word) {
                if (strpos($word,'+')) {

                    $subs = explode('+',$word);
                    $sr = true;
                    $s_nots = true;
                    $s_nots_result = true;
                    foreach ($subs as $sub) {
                        $sub = trim($sub);
                        if (!$sub) continue;
                        if (substr($sub, 0, 1) == '~') {
                            if (stripos8($content, substr($sub, 1)) !== false) {
                                $sr = false;
                            } else $s_nots_result = false;
                        } else {
                            $s_nots = false;
                            if (stripos8($content, $sub) === false) {
                                $sr = false;
                                break;
                            }
                        }
                    }

                    if ($s_nots and $s_nots_result) {
                        $r = false;
                        $nots_good = false;
                        break;
                    }
                    if (!$s_nots) {
                        $nots_good = false;
                        if ($sr) $r=true;
                    }

                } else
                    if (substr($word, 0, 1) == '~') {
                        if (stripos8($content, substr($word, 1)) !== false) {
                            $r = false;
                            $nots_good = false;
                            break;
                        }
                    } else {
                        $nots_good = false;
                        if (!$r) if (stripos8($content, $word) !== false) $r = true;
                    }
            }
            if ($nots_good) $r = true;
            re($r); return $r;

        } else {
            foreach ($text as $word) {
                if (strpos($word,'+')) {
                    $subs = explode('+', $word);
                    $good = true;
                    foreach ($subs as $sub) {
                        $sub = trim($sub);
                        if (!$sub) continue;
                        if (stripos8($content, $sub) === false) {
                            $good = false;
                            break;
                        }
                    }
                    if ($good) {
                        re(true);
                        return true;
                    }
                } else
                    if (stripos8($content, $word) !== false) {
                        re(true);
                        return true;
                    }
            }
        }
        re(false); return false;
    }

    $not = false;
    if (substr($text,0,1)=='~') {
        $not = true;
        $text = substr($text,1);
    }
    $x=stripos8($content,$text);
    $r=($x!==false);
    if ($not) $r = !$r;

    re($r); return $r;
}

/**
 * Replace $search with $replace. Supports '*' - any number of chars.
 * If $search is false, replace.txt is used (or another file specified at global $replace_file
 * Non case-sentensive
 * @param string $search
 * @param string $replace
 * @param $source
 * @return array|bool|mixed|string
 */

function replace($search=false, $replace='', $source=false)
{
    if ($source === false) { global $http_html; $source = $http_html; } // Use set_source() default

    if (is_array($search)) {
        foreach ($search as $i=>$s) {
            if (is_array($replace)) $r = $replace[$i];
            else $r = $replace;
            $source = replace($s, $r, $source);
        }
        re($source);
        return $source;
    }

    if (is_array($source)) {
        foreach ($source as &$line) $line=replace($search,$replace,$line);
        $r = $source;
    } else

        if (!$search) {
            // Use replace.txt
            $search_list=array();
            $replace_list=array();
            global $replace_file;
            if (file_exists($replace_file)) {
                $lines = file($replace_file);
                foreach ($lines as $line)
                    if (($line=trim($line)) and ($p=strpos($line,'='))) {
                        if (strpos($line,'==')) $p=strpos($line,'==');
                        $search=trim(substr($line,0,$p));
                        if (strpos($line,'==')) $p++;
                        $replace = trim(substr($line,$p+1));
                        if (strpos($search,'*')!==false) $source = replace($search,$replace,$source);
                        else {
                            $search_list[]=$search;
                            $replace_list[] = $replace;
                        }
                    }
                $r = str_ireplace($search_list,$replace_list,$source);
                re($r); return $r;
            }
            //trigger_error("File $replace_file not found at replace()");
            return re($source);
        } else
            if (($p=strpos($search,'*'))!==false) {
                $before = substr($search,0,$p);
                $after = substr($search,$p+1);
                $r = replace_inside("","",$before,$after,$source);
                $r = str_ireplace($before.$after,$replace,$r);
            } else
                $r = str_ireplace($search,$replace,$source);

    re($r); return $r;
}

// Replace with REGEXP
function rreplace($search, $replace='', $source=false, $options = 'iu')
{
    if ($source === false) { global $http_html; $source = $http_html; } // Use set_source() default

    if (is_array($source)) {
        $r=array();
        foreach ($source as $line) $r[]=replace($search,$replace,$line);
    } else {

        $search = str_replace('/','\/',$search);
        $replace = str_replace('/','\/',$replace);

        $r = preg_replace('/' . $search . '/' . $options, $replace, $source);

    }

    re($r); return $r;
}

// Replaces something inside $start and $end
function replace_inside($find, $replace, $start, $end, $source = false) {

    if ($source === false) { global $http_html; $source = $http_html; } // Use set_source() default

    $s=0;
    while (($s = stripos($source,$start,$s)) !==false) {

        $s += strlen($start);
        if ($end) $e = stripos($source,$end,$s);
        else $e = strlen($source);
        if ($e) {

            $left = substr($source,0,$s);
            $right = substr($source,$e);
            $mid = substr($source,$s,$e-$s);
            $midlen = strlen($mid);

            if ($find) $mid = str_ireplace($find,$replace,$mid);
            else $mid = $replace;
            $source=$left.$mid.$right;
            $e = $e + strlen($mid) - $midlen;
            $s = $e + strlen($end);
            if ($s>strlen($source)) break;
        }
    }

    re($source); return $source;
}

/**
 * Converts inches at html to centimeters.
 * @param $source
 * @return string
 */
function inch_to_cm($source=false) {

    if ($source === false) { global $http_html; $source = $http_html; } // Use set_source() default

    $source2 = replace_inside('"',"'","<",">",$source);
    $source = $source2;
    preg_match_all('/\d+[\.\,]?\d*"/',$source2,$inches,PREG_OFFSET_CAPTURE);
    $offset = 0;
    foreach ($inches[0] as $inch) {
        $cm = str_replace (",",".",$inch[0]);
        $cm = str_replace ('"',"",$cm);
        $cm = (int) ($cm * 2.54);
        $cm .= " cm";
        $pos = $inch[1] + $offset;
        $source = substr($source,0,$pos). $cm . substr($source,$pos+strlen($inch[0]));
        $offset = $offset - strlen($inch[0]) + strlen($cm);
    }
    re($source); return $source;
}

// ======================== CSS Selectors Data Retrieval

// Return links from tags
function tags_link($selector, $source = false)
{

    $hts = tags_html($selector,$source);
    if (!$hts) return re(array());
    // Get parent tag

    $links = tags_href('a',implode('',$hts));
    if (!$links) {
        $hts = noko($source)->get_parents($selector);
        $links = tags_href('a',implode('',$hts));
    }

    $links = array_unique($links);
    re($links);

    return $links;
}

function tag_link($selector, $source = false)
{
    $r = tags_link($selector, $source);
    if ($r) $r = reset($r);
    re($r); return $r;
}

// Return href attribute from tags
function tags_href($selector, $source = false)
{
    $r = urls(tags_attr($selector, 'href', $source));
    re($r); return $r;
}

// Return link from tag
function tag_href($selector, $source = false)
{
    $t = tags_href($selector, $source);
    if ($t) $r = reset($t);
    else $r=false;

    re($r); return $r;
}

// Return text from tags
function tags_text($selector, $source = false)
{
    $r = array();
    foreach (nodes($selector, $source) as $node) {
        $text = trim(node_text($node));
        if (!$text) $text = $node->GetAttribute('content');
        if ($text) $r[] = $text;
    }

    re($r); return $r;
}

function tag_image($selector, $source = false) {
    $images = tags_image($selector,$source);
    $image = reset($images);
    re($image); return $image;
}

function tags_image($selector, $source = false) {
    $hts = tags_html($selector,$source);
    if (!$hts) return array();
    // Get parent tag
    $images = array();
    if (substr($hts[0],0,4)=='<img')
        $hts = noko($source)->get_parents($selector);

    foreach ($hts as $ht) {
        $img = tags_href('a',$ht);
        $links = false;
        foreach ($img as $im)
            if (is_imagelink($im)) {
                $images[] = $im;
                $links = true;
            }
        if (!$links) {
            $img = tags_attr('img','src',$ht);
            foreach ($img as $im) {
                $im = url($im);
                if (is_imagelink($im))
                    $images[] = $im;
            }
        }
    }
    re($images); return $images;
}

function is_imagelink($img) {
    if (!strpos($img,'//')) { re(false); return false; }
    $img = strtolower($img);
    $is_image = strpos($img,'.jpg') || strpos($img,'.jpeg') || strpos($img,'.png') || strpos($img,'.gif');
    re($is_image); return $is_image;
}

// Return text from tag
function tag_text($selector, $source = false)
{
    $texts = tags_text($selector, $source);
    $text = reset($texts);
    re($text); return $text;
}

// Find all tags with selector on page and get an array of their attribute
function tags_attr($selector, $attr, $source = false)
{

    $result = array();
    foreach (noko($source)->get($selector)->toArray() as $tag)
            if (!empty($tag[$attr])) $result[] = trim($tag[$attr]);
    $r = $result;

    re($r); return $r;
}

// Find first tag with selector on page and get its attribute
function tag_attr($selector, $attr, $source = false)
{
    $r = tags_attr($selector, $attr, $source);
    $r = reset($r);
    re($r); return $r;
}

// Get inner html of all entries of some tag
function tags_html($selector, $source = false)
{

    $r=array();
    $noko = noko($source);
    $doc = false;
    foreach ($noko->get($selector)->getNodes() as $tag) {
        if (!$doc) $doc = $tag->ownerDocument;
        $html = $doc->saveXML($tag);
        $html = str_replace(array("\n", "\t"), '', $html);
        if ($html=='<root/>') $html='';
        /*
        $_html=close_tags($html);
        if ($html) {
            $html = substr($html, strpos($html,">")+1);
            $html = substr($html, 0,strrpos($html,"<"));
            $html = trim($html);
        }
        */
        $r[] = $html;
    }

    re($r); return $r;
}

// Get inner html of first entry of some tag
function tag_html($selector, $source = false) {
    /*$r = noko($source)->get($selector)->toHtml();
    $r = str_replace(array("\n", "\t"), '', $r);
    if ($r=='<root/>') $r='';
    $r=close_tags($r);
    if ($r) {
        $r = substr($r, strpos($r,">")+1);
        $r = substr($r, 0,strrpos($r,"<"));
        $r = trim($r);
    }*/
    $r = tags_html($selector, $source);
    if ($r) $r = reset($r);
    else $r = false;
    re($r); return $r;
}

function url($link) {
    $t= urls ( array ( $link ) );
    if ($t) $r = reset($t);
    else $r=false;

    re($r); return $r;
}

// !!! Create array of urls from any data
function urls($data)
{
    $result = array();
    if (is_array($data)) {
        if (isset($data['href']))
            $result[] = $data['href']; else
            foreach ($data as $d)
                if (is_array($d))
                    $result[] = $d['href']; else
                    $result[] = $d;
    } else $result[] = $data;

    // Make URLs full
    $result2 = array();
    global $http_url_base;
    $domain = substr($http_url_base, 0, @strpos($http_url_base, '/', 10));

    foreach ($result as $url) {
        $url = trim($url);
        if (!$url)
            continue;
        $url = str_replace('./','',$url);
        if (substr($url,0,2)=='//') $url = 'http:'.$url;
        if (strpos($url, '://') === false)
            if ($url[0] == '/')
                $url = $domain . $url;
            elseif ($url[0] == '?') $url = $http_url_base . $url;
            else $url = $http_url_base . $url;
        $result2[] = $url;
    }
    $r = $result2;

    re($r); return $r;
}

/**
 * Set the default source for data retrieval operations.
 * @param $source
 */
function set_source($source) {
    global $http_html, $mx_html_noko;
    $http_html = $source;
    $mx_html_noko = new nokogiri2($http_html);
    re();
}

// Return Nokogiri object from source
function noko($source=false)
{
    global $mx_html_noko;
    if ($source===false)
    { re($mx_html_noko); return $mx_html_noko; }

    $r = new nokogiri2($source);
    re($r); return $r;
}

// DOM Nodes

function nodes($selector, $source = false)
{
    $r = noko($source)->get($selector)->getNodes();
    re($r); return $r;
}

/**
 * Returns text representation of DOM Node.
 * @param $Node
 * @return string
 */
function node_text($Node, $Text = "") {
    if (empty($Node->tagName))
        return $Text.$Node->textContent;

    $Node = $Node->firstChild;
    if ($Node != null)
        $Text = node_text($Node, $Text);

    while(!empty($Node->nextSibling)) {
        $Text = node_text($Node->nextSibling, $Text);
        $Node = $Node->nextSibling;
    }
    re($Text); return $Text;
}

/**
 * Nokogiri2 class for all CSS data retrieval operations. Is not usable directly.
 *
 * Based on Nokogiri by olamedia <olamedia@gmail.com>
 *
 */

class nokogiri2 implements IteratorAggregate{
    const
        regexp =
        "/(?P<tag>[a-z0-9]+)?(\[(?P<attr>\S+)=(?P<value>[^\]]+)\])?(#(?P<id>[^\s:>#\.]+))?(\.(?P<class>[^\s:>#\.]+))?(:(?P<pseudo>(first|last|nth)-child)(\((?P<expr>[^\)]+)\))?)?\s*(?P<rel>>)?/isS"
    ;
    protected $_source = '';
    /**
     * @var DOMDocument
     */
    protected $_dom = null;
    /**
     * @var DOMDocument
     */
    protected $_tempDom = null;
    /**
     * @var DOMXpath
     * */
    protected $_xpath = null;
    protected static $_compiledXpath = array();
    public function __construct($source = ''){
        if (is_object($source)) $source = $source->ownerDocument->saveXML( $source );
        $this->loadHtml($source);
    }
    public function getRegexp(){
        $tag = "(?P<tag>[a-z0-9]+)?";
        $attr = "(\[(?P<attr>\S+)=(?P<value>[^\]]+)\])?";
        $id = "(#(?P<id>[^\s:>#\.]+))?";
        $class = "(\.(?P<class>[^\s:>#\.]+))?";
        $child = "(first|last|nth)-child";
        $expr = "(\((?P<expr>[^\)]+)\))";
        $pseudo = "(:(?P<pseudo>".$child.")".$expr."?)?";
        $rel = "\s*(?P<rel>>)?";
        $regexp = "/".$tag.$attr.$id.$class.$pseudo.$rel."/isS";
        return $regexp;
    }
    public static function fromHtml($http_htmlString){
        $me = new self();
        $me->loadHtml($http_htmlString);
        return $me;
    }
    public static function fromHtmlNoCharset($http_htmlString){
        $me = new self();
        $me->loadHtmlNoCharset($http_htmlString);
        return $me;
    }
    public static function fromDom($dom){
        $me = new self();
        $me->loadDom($dom);
        return $me;
    }
    public function loadDom($dom){
        $this->_dom = $dom;
    }
    public function loadHtmlNoCharset($http_htmlString = ''){
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (strlen($http_htmlString)){
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$http_htmlString);
            // dirty fix
            foreach ($dom->childNodes as $item){
                if ($item->nodeType == XML_PI_NODE){
                    $dom->removeChild($item); // remove hack
                    break;
                }
            }
            $dom->encoding = 'UTF-8'; // insert proper
            libxml_clear_errors();
        }
        $this->loadDom($dom);
    }
    public function loadHtml($http_htmlString = ''){
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (strlen($http_htmlString)){
            libxml_use_internal_errors(true);
            $dom->loadHTML('<meta http-equiv="content-type" content="text/html; charset=utf-8">'.$http_htmlString); //MX
            libxml_clear_errors();
        }
        $this->loadDom($dom);
    }
    function __invoke($expression){
        return $this->get($expression);
    }
    public function get($expression, $compile = true){
        /*if (strpos($expression, ' ') !== false){
            $a = explode(' ', $expression);
            foreach ($a as $k=>$sub){
                $a[$k] = $this->getXpathSubquery($sub);
            }
            return $this->getElements(implode('', $a));
        }*/
        if (strpos($expression,'//')===false)
            $expression = $this->getXpathSubquery($expression, false, $compile);
        return $this->getElements($expression);
    }
    public function get_parents($expression, $compile = true){
        if (strpos($expression,'//')===false)
            $expression = $this->getXpathSubquery($expression, false, $compile);
        return $this->getParentElements($expression);
    }
    public function getNodes(){
        return $this->getDom()->firstChild->childNodes; //MX
    }
    public function getDom(){
        if ($this->_dom instanceof DOMDocument){
            return $this->_dom;
        }elseif ($this->_dom instanceof DOMNodeList){
            if ($this->_tempDom === null){
                $this->_tempDom = new DOMDocument('1.0', 'UTF-8');
                $root = $this->_tempDom->createElement('root');
                $this->_tempDom->appendChild($root);
                foreach ($this->_dom as $domElement){
                    $domNode = $this->_tempDom->importNode($domElement, true);
                    $root->appendChild($domNode);
                }
            }
            return $this->_tempDom;
        }
    }
    protected function getXpath(){
        if ($this->_xpath === null){
            $this->_xpath = new DOMXpath($this->getDom());
        }
        return $this->_xpath;
    }
    public function getXpathSubquery($expression, $rel = false, $compile = true){
        if ($compile){
            $key = $expression.($rel?'>':'*');
            if (isset(self::$_compiledXpath[$key])){
                return self::$_compiledXpath[$key];
            }
        }
        $query = '';
        if (strpos($expression,",") | strpos($expression,":") | strpos($expression,">"))
            $query = $this->CSStoXpath($expression);
        else { // Original xPath code

            if (preg_match(self::regexp, $expression, $subs)){
                $brackets = array();
                if (isset($subs['id']) && '' !== $subs['id']){
                    $brackets[] = "@id='".$subs['id']."'";
                }
                if (isset($subs['attr']) && '' !== $subs['attr']){
                    $attrValue = isset($subs['value']) && !empty($subs['value'])?$subs['value']:'';
                    $brackets[] = "@".$subs['attr']."='".$attrValue."'";
                }
                if (isset($subs['class']) && '' !== $subs['class']){
                    $brackets[] = 'contains(concat(" ", normalize-space(@class), " "), " '.$subs['class'].' ")';
                }
                if (isset($subs['pseudo']) && '' !== $subs['pseudo']){
                    if ('first-child' === $subs['pseudo']){
                        $brackets[] = '1';
                    }elseif ('last-child' === $subs['pseudo']){
                        $brackets[] = 'last()';
                    }elseif ('nth-child' === $subs['pseudo']){
                        if (isset($subs['expr']) && '' !== $subs['expr']){
                            $e = $subs['expr'];
                            if('odd' === $e){
                                $brackets[] = '(position() -1) mod 2 = 0 and position() >= 1';
                            }elseif('even' === $e){
                                $brackets[] = 'position() mod 2 = 0 and position() >= 0';
                            }elseif(preg_match("/^((?P<mul>[0-9]+)n\+)(?P<pos>[0-9]+)$/is", $e, $esubs)){
                                if (isset($esubs['mul'])){
                                    $brackets[] = '(position() -'.$esubs['pos'].') mod '.$esubs['mul'].' = 0 and position() >= '.$esubs['pos'].'';
                                }else{
                                    $brackets[] = ''.$e.'';
                                }
                            }
                        }
                    }
                }
                $query = ($rel?'/':'//').
                    ((isset($subs['tag']) && '' !== $subs['tag'])?$subs['tag']:'*').
                    (($c = count($brackets))?
                        ($c>1?'[('.implode(') and (', $brackets).')]':'['.implode(' and ', $brackets).']')
                        :'')
                ;
                $left = trim(substr($expression, strlen($subs[0])));
                if ('' !== $left){
                    $query .= $this->getXpathSubquery($left, isset($subs['rel'])?'>'===$subs['rel']:false, $compile);
                }
            }

        }
        if ($compile){
            self::$_compiledXpath[$key] = $query;
        }
        return $query;
    }
    protected function getElements($xpathQuery){
        if (strlen($xpathQuery)){
            $nodeList = $this->getXpath()->query($xpathQuery);
            if ($nodeList === false){
                throw new Exception('Malformed xpath');
            }
            return self::fromDom($nodeList);
        }
    }

    protected function getParentElements($xpathQuery){
        $res=array();
        if (strlen($xpathQuery)){
            $nodeList = $this->getXpath()->query($xpathQuery);

            for($i=0;$i<$nodeList->length;$i++) {
                $node = $nodeList->item($i);
                $res[] = $node->ownerDocument->saveXML( $node->parentNode );
            }
        }
        return $res;
    }

    public function toXml(){
        return $this->getDom()->saveXML();
    }

    public function toHtml(){ //MX
        $innerHTML= '';
        $children = $this->getDom()->firstChild->childNodes;
        if (!is_array($children)) $children = $this->getDom()->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML( $child );
        }
        $innerHTML = str_replace('<root>','',$innerHTML);
        $innerHTML = str_replace('</root>','',$innerHTML);
        return $innerHTML;
    }


    public function toText(){ //MX
        return node_text($this->getDom()->lastChild);
    }

    public function toArray($xnode = null){
        $array = array();
        if ($xnode === null){
            if ($this->_dom instanceof DOMNodeList){
                foreach ($this->_dom as $node){
                    $array[] = $this->toArray($node);
                }
                return $array;
            }
            $node = $this->getDom();
        }else{
            $node = $xnode;
        }
        if (in_array($node->nodeType, array(XML_TEXT_NODE,XML_COMMENT_NODE))){
            return $node->nodeValue;
        }
        if ($node->hasAttributes()){
            foreach ($node->attributes as $attr){
                $array[$attr->nodeName] = $attr->nodeValue;
            }
        }
        if ($node->hasChildNodes()){
            foreach ($node->childNodes as $childNode){
                $array[$childNode->nodeName][] = $this->toArray($childNode);
            }
        }
        if ($xnode === null){
            return reset(reset($array)); // first child
        }
        return $array;
    }
    public function getIterator(){
        $a = $this->toArray();
        return new ArrayIterator($a);
    }

    function CSStoXpath( $rule ) {
        $rule = str_replace(' ,',',',$rule);
        $reg['element'] 		= "/^([#.]?)([a-z0-9\\*_-]*)((\|)([a-z0-9\\*_-]*))?/i";
        $reg['attr1']  		= "/^\[([^\]]*)\]/i";
        $reg['attr2']   		= '/^\[\s*([^~=\s]+)\s*(~?=)\s*"([^"]+)"\s*\]/i';
        $reg['attrN']   		= "/^:not\((.*?)\)/i";
        $reg['psuedo']  		= "/^:([a-z_-])+/i";
        $reg['gtlt']			= "/^:([g|l])t\(([0-9])\)/i";
        $reg['last']			= "/^:(last |last\([-]([0-9]+)\))/i";
        $reg['first']			= "/^:(first\([+]([0-9]+)\)|first)/i";
        //$reg['first']			= "/^:(first |first\([+]([0-9]+)\))/i";
        $reg['psuedoN']		= "/^:nth-child\(([0-9])\)/i";
        $reg['combinator'] 	= "/^(\s*[>+\s])?/i";
        $reg['comma']			= "/^\s*,/i";

        $index = 1;
        $parts = array("//");
        $lastRule = NULL;

        while( strlen($rule) > 0 && $rule != $lastRule ) {
            $lastRule = $rule;
            $rule = trim($rule);
            if( strlen($rule) > 0) {
                // Match the Element identifier
                $a = preg_match( $reg['element'], $rule, $m );
                if( $a ) {

                    if( !isset($m[1]) )
                    {

                        if( isset( $m[5] ) ) {
                            $parts[$index] = $m[5];
                        } else {
                            $parts[$index] = $m[2];
                        }  }
                    else if( $m[1] == '#') {
                        array_push($parts, "[@id='".$m[2]."']");
                    } else if ( $m[1] == '.' ) {
                        array_push($parts, "[contains(@class, '".$m[2]."')]");
                    }else {
                        array_push( $parts, $m[0] );
                    }
                    $rule = substr($rule, strlen($m[0]) );
                }

                // Match attribute selectors.
                $a = preg_match( $reg['attr2'], $rule, $m );
                if( $a ) {
                    if( $m[2] == "~=" ) {
                        array_push($parts, "[contains(@".$m[1].", '".$m[3]."')]");
                    } else {
                        array_push($parts, "[@".$m[1]."='".$m[3]."']");
                    }
                    $rule = substr($rule, strlen($m[0]) );
                } else {
                    $a = preg_match( $reg['attr1'], $rule, $m );
                    if( $a ) {

                        if (($xp=strpos($m[1],'=')) && !strpos($m[1],"'") && !strpos($m[1],'"')) { // MaxD
                            $m[1] = substr($m[1],0, $xp)."='".substr($m[1],$xp+1)."'";
                        }

                        array_push( $parts, "[@".$m[1]."]");
                        $rule = substr($rule, strlen($m[0]) );
                    }
                }

                // register nth-child
                $a = preg_match( $reg['psuedoN'], $rule, $m );
                if( $a ) {
                    array_push( $parts, "[".$m[1]."]" );
                    $rule = substr($rule, strlen($m[0]));
                }

                // gt and lt commands
                $a = preg_match( $reg['gtlt'], $rule, $m );
                if( $a ) {
                    if( $m[1] == "g" ) {
                        $c = ">";
                    } else {
                        $c = "<";
                    }

                    array_push( $parts, "[position()".$c.$m[2]."]" );
                    $rule = substr($rule, strlen($m[0]));
                }

                // last and last(-n) command
                $a = preg_match( $reg['last'], $rule, $m );
                if( $a ) {
                    if( isset( $m[2] ) ) {
                        $m[2] = "-".$m[2];
                    }
                    array_push( $parts, "[last()".$m[2]."]" );
                    //print_r($m);
                    $rule = substr($rule, strlen($m[0]));
                }

                // first and first(+n) command
                $a = preg_match( $reg['first'], $rule, $m );
                if( $a ) {
                    $n = 0;
                    if( isset( $m[2] ) ) {
                        $n = $m[2];
                    }
                    array_push( $parts, "[$n]" );

                    $rule = substr($rule, strlen($m[0]));

                }


                // skip over psuedo classes and psuedo elements
                $a = preg_match( $reg['psuedo'], $rule, $m );
                while( $m ) {
                    // loop???
                    $rule = substr( $rule, strlen( $m[0]) );
                    $a = preg_match( $reg['psuedo'], $rule, $m );
                }

                // Match combinators
                $a = preg_match( $reg['combinator'], $rule, $m );
                if( $a && strlen($m[0]) > 0 ) {
                    if( strpos($m[0], ">") ) {
                        array_push( $parts, "/");
                    } else if( strpos( $m[0], "+") ) {
                        array_push( $parts, "/following-sibling::");
                    } else {
                        array_push( $parts, "//" );
                    }

                    $index = count($parts);
                    //array_push( $parts, "*" );
                    $rule = substr( $rule, strlen( $m[0] ) );
                }

                $a = preg_match( $reg['comma'], $rule, $m );
                if( $a ) {
                    array_push( $parts, " | ", "//" );
                    $index = count($parts) -1;
                    $rule = substr( $rule, strlen($m[0]) );
                }
            }
        }
        $xpath = implode("",$parts);
        return str_replace('//[','//*[',$xpath);
    }
}

/**
 * MX_Debug library: debug HTML output for functions.
 *
 * @author: MaxD - max@bukrek.net
 * @version: 1.0
 */

/**
 * Reporting function, that reports calling function parameters and return value.
 *
 * Works after begin_debug() call and reports function calls only at that nesting level -
 * to prevent sub-calls reports.
 *
 * MX_Debug should be included before any library that uses it (like parsemx).
 */
function re($return = '[no return value]') {
    global $debug_nesting_level, $debug_last_time;
    if (!$debug_nesting_level) return $return;
    $trace = debug_backtrace();
    if (count($trace)!=$debug_nesting_level) return $return;

    $trace = $trace[1];

    echo "<div class='debug_p'>";

    $file = $trace['file'];
    if ($p=strrpos($file,'/'))
        $file = substr($file,$p+1); // Strip the file path
    echo "<span class='debug_file'>$file</span>";
    echo "<span class='debug_line'>:".$trace['line']."</span> ";

    $time = microtime(true);
    $delta = $time - $debug_last_time;
    $debug_last_time = $time;
    if ($delta>=1) {
        echo "<span class='debug_sec'>".(int)$delta." sec</span> ";
        $delta -= (int) $delta;
    }
    $delta = intval($delta * 1000);
    if ($delta>50)
        echo "<span class='debug_msec'>".$delta." msec</span> ";

    echo "<span class='debug_function'>".$trace['function']."</span>(";
    $comma = false;
    foreach ($trace['args'] as $arg) {
        if ($comma) echo ", ";
        else $comma = true;
        echo logvar($arg);
    }
    echo ")";
    if ($return !== '[no return value]') {
        echo " = " . logvar($return);
    }
    echo "</div>\n";
    @ob_flush(); flush();
    return $return;
}

/**
 * Turns on HTML debug output (via re() function).
 * @param bool $echo_css - echoes debug styles CSS for better look
 */

function begin_debug($echo_css = true) {
    global $debug_nesting_level, $debug_last_time;
    $debug_last_time = microtime(true);
    $debug_nesting_level = count(debug_backtrace())+1;
    if ($echo_css) {
        ini_set('display_errors',1);
        @header('Content-Type: text/html; charset=utf-8');
        echo "
<style type='text/css'>

     .debug_p {
        margin-top: 7px;
        font-size: 14px;
        font-family: Helvetica, Arial, sans-serif;
     }

     .debug_tag {
        color: #2a4e84;
        padding-left:2px;
        padding-right:2px;
     }
     .debug_text {
        color: #7c3673;
     }

     .debug_file {
        font-size: 12px;
        color: grey;
     }

     .debug_line {
        font-size: 12px;
        color: #AAA;
     }

     .debug_sec {
        background: #f2dede;
        border-radius: 3px;
        font-size: 12px;
        color: red;
        padding: 2px;
     }

     .debug_msec {
        background: #fff4c7;
        border-radius: 3px;
        font-size: 12px;
        color: orange;
        padding: 2px;
     }

     .debug_function {
        color: green;
     }

     .debug_var {
        background: #EEE;
        color: #555;
        border-radius: 3px;
        padding: 2px;
     }

     .debug_var_trunc {
        background: #fffbea;
        cursor: pointer;
     }

     .debug_var_trunc:hover {
        background: #EEE;
     }

     .debug_var_full {
        cursor: pointer;
     }

     .debug_var_full:hover {
        background: #F3F3F3;
     }

     .debug_index {
        font-size: 12px;
        color: #AAA;
     }

</style>
";
    }
}

/**
 * Turns off HTML debug output (via re() function)
 */

function end_debug() {
    global $debug_nesting_level;
    $debug_nesting_level = false;
}

function str_replace_once($search, $replace, $subject, $offset) {
    $p = strpos($subject,$search,$offset);
    if ($p==false) { re(false); return false; }
    $r = substr($subject,0,$p).$replace.substr($subject,$p+strlen($search));
    re($r); return $r;
}


// This function was meant to highlight html, but its too slow, thats why its disabled
function debug_highlight($html) {
    return $html;
    $h = " ".$html;

    $p = 0;
    $state = false;
    while (true) {
        if ($state=="text") {
            if ($p2=strpos($h,"'",$p)) {
                $h = str_replace_once("'","'".'</span>',$h,$p);
                $p = $p2 + 13;
                $state = false;
            } else break;
        } else {
            if ($p2=strpos($h,"'",$p)) {
                $h = str_replace_once("'","<span class='debug_text'>"."'",$h,$p);
                $p = $p2 + 26;
                $state = "text";
            } else break;
        }
    }
    if ($state) $h.= '</span>';

    $p = 0;
    $state = false;
    while (true) {
        if ($state=="text") {
            if ($p2=strpos($h,'&#39;',$p)) {
                $h = str_replace_once('&#39;','&#39;</span>',$h,$p);
                $p = $p2 + 13;
                $state = false;
            } else break;
        } else {
            if ($p2=strpos($h,'&#39;',$p)) {
                $h = str_replace_once('&#39;',"<span class='debug_text'>".'&#39;',$h,$p);
                $p = $p2 + 26;
                $state = "text";
            } else break;
        }
    }
    if ($state) $h.= '</span>';

    $p = 0;
    $state = false;
    while (true) {
        if ($state=="text") {
            if ($p2=strpos($h,'&quot;',$p)) {
                $h = str_replace_once('&quot;','&quot;</span>',$h,$p);
                $p = $p2 + 13;
                $state = false;
            } else break;
        } else {
            if ($p2=strpos($h,'&quot;',$p)) {
                $h = str_replace_once('&quot;',"<span class='debug_text'>".'&quot;',$h,$p);
                $p = $p2 + 26;
                $state = "text";
            } else break;
        }
    }
    if ($state) $h.= '</span>';

    $p = 0;
    $state = false;
    while (true) {
        if ($state=="tag") {
            if (($p2=strpos($h,"&gt;",$p))!==false) {
                $h = str_replace_once("&gt;","&gt;</span>",$h,$p);
                $p = $p2 + 11;
                $state = false;
            } else break;
        } else {
            if (($p2=strpos($h,"&lt;",$p))!==false) {
                $h = str_replace_once("&lt;","<span class='debug_tag'>&lt;",$h,$p);
                $p = $p2 + 28;
                $state = "tag";
            } else break;
        }
    }
    if ($state) $h.= '</span>';

    $h = substr($h,1);
    re($h); return $h;
}

/**
 * Returns debug output representation of any variable
 * @param $v - variable
 * @return string
 */
function logvar($v)
{
    global $logval_level;
    $logval_level++;
    $highlight = false;
    if (is_array($v)) {
        $m = "[ ";
        $first = true;
        foreach ($v as $i=>$a) {
            if ($first)
                $first = false; else $m .= ", ";
            $m .= "[|$i:|]".logvar($a);
        }
        $m .= " ]";

    } elseif (is_object($v)) $m = (get_class($v));
    elseif (is_numeric($v)) $m = $v;
    elseif ($v===false) $m = 'false';
    elseif ($v===true) $m = 'true';
    else {
                $m = '"' . htmlspecialchars($v) . '"';
                $m = debug_highlight($m);
    }

    $logval_level--;
    if (!$logval_level)
        if (strlen(strip_tags($m))>32) { // Let's hide it
            $r=rand(1,1000000);
            //$trim = substr8($m,0,26);
            $trim = shorten_text($m,26);
            // Fix &; break
            if ($p=strrpos($trim,'&'))
                if (strrpos($trim,';')<$p) {
                    $p = strpos($m,';',$p);
                    $trim = substr($m,0,$p+1);
                }
            // Fix <<>> break
            if ($p=strrpos($trim,'[|'))
                if (strrpos($trim,'|]')<$p) {
                    $p = strpos($m,'\]',$p);
                    $trim = substr($m,0,$p+2);
                }
            $trim = str_replace(array('[|','|]'),array("<span class='debug_index'>",'</span>'),$trim);
            $m = str_replace(array('[|','|]'),array("<span class='debug_index'>",'</span>'),$m);
            $m = "<span class='debug_var_trunc' id='s$r' onclick='document.getElementById(\"s$r\").style.display=\"none\";document.getElementById(\"f$r\").style.display=\"inline\"'>".$trim."</span><span class='debug_var_full' id='f$r' style='display:none' onclick='document.getElementById(\"s$r\").style.display=\"inline\";document.getElementById(\"f$r\").style.display=\"none\"'>$m</span>";
        }

    if (!$logval_level) return "<span class='debug_var'>$m</span>";
    else return $m;
}

function dmsg($message) {
    global $debug_nesting_level, $debug_last_time;
    if (!$debug_nesting_level) return $message;
    $trace = debug_backtrace();
    //if (count($trace)!=$debug_nesting_level) return $return;

    $trace = $trace[0];

    echo "<div class='debug_p'>";

    $file = $trace['file'];
    if ($p=strrpos($file,'/'))
        $file = substr($file,$p+1); // Strip the file path
    echo "<span class='debug_file'>$file</span>";
    echo "<span class='debug_line'>:".$trace['line']."</span> ";

    /*
    $time = microtime(true);
    $delta = $time - $debug_last_time;
    $debug_last_time = $time;
    if ($delta>=1) {
        echo "<span class='debug_sec'>".(int)$delta." sec</span> ";
        $delta -= (int) $delta;
    }
    $delta = intval($delta * 1000);
    if ($delta)
        echo "<span class='debug_msec'>".$delta." msec</span> ";
    */
    if (is_string($message)) echo $message;
    else echo logvar($message);
    echo "</div>";

    return $message;
}

/**
 * MX_Log library: HTML logging.
 *
 * @author: MaxD - max@bukrek.net
 * @version: 1.0
 */

$log_file = 'logs.html';
$feed_file = false;

// error handler function
function mxErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }

    $start_dir = g('mx_save_start_dir').'/';
    if (substr($errfile, 0, strlen($start_dir)) == $start_dir)
        $errfile = substr($errfile, strlen($start_dir));

    switch ($errno) {

        case E_USER_WARNING:
            xwarn("$errstr at <b>$errfile</b>:$errline");
            break;

        default:
            xnotice("$errstr at <b>$errfile</b>:$errline");
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}

function xwarn($message) {
    xlog('WARNING: '.$message);
}

function xnotice($message) {
    xlog('NOTICE: '.$message);
}

function xsuccess($message) {
    xlog('SUCCESS: '.$message);
}

function mxshutdown() {
    global $mx_save_start_dir, $mx_check_script_duplicate_name, $debug_nesting_level;
    $debug_nesting_level = 0;
    chdir($mx_save_start_dir);
    if ($mx_check_script_duplicate_name) {
        mx_config_set($mx_check_script_duplicate_name."_pid");
    }
    $error = error_get_last();
    if($error !== NULL)
        if (($error['type']!=2) and ($error['type']!=8) and ($error['line'])) {
            ini_set('display_errors',1);
            $error['file'] = if_inside($mx_save_start_dir.'/',"",$error['file']);
            if (!strpos($error['message'],'deprecated'))  xwarn($error['message'] . " at <b>" . $error['file'] . "</b>:" . $error['line']);
        }
}

$mx_save_start_dir = getcwd();
set_error_handler("mxErrorHandler");
register_shutdown_function('mxshutdown');

// Write message to log
function xlog($xmessage) {

    if (!error_reporting()) return $xmessage;

    global $log_file, $feed_file;

    if (!is_string($xmessage)) {
        $message = xlogvar($xmessage);
    } else $message =$xmessage;

    $message = str_replace("\n","<br/>",$message);

    $color = '';
    if (strpos($message, 'WARNING:') === 0) {
        $color = 'red';
        $message = str_replace('WARNING: ','',$message);
    }
    if (strpos($message, 'NOTICE:') === 0) {
        $color = 'purple';
        $message = str_replace('NOTICE: ','',$message);
    }
    if (strpos($message, 'SUCCESS:') === 0) {
        $color = 'green';
        $message = str_replace('SUCCESS: ','',$message);
    }

    if ($color) $message = '<span style="color:' . $color . '">' . $message . '</span>';

    if (ini_get('display_errors')) {
        echo "<div class='debug_p'><span class='debug_line' style='color:grey'>[" . date('H:i:s') . "]</span> $message</div>\n";
        @ob_flush(); flush();
    }

    if (!ini_get('log_errors')) return $xmessage;

    clearstatcache();
    if (!file_exists($log_file) || filesize($log_file)>3*1024*1024) file_put_contents($log_file,'<?xml version="1.0" encoding="UTF-8"?><body style="font-family: Helvetica, Arial, sans-serif">'."\n");

    file_put_contents($log_file, '<p><span style="color:grey">[' . date('m-d H:i:s') . ']</span> ' . $message . "</p>\n", FILE_APPEND);

    if ($feed_file) file_put_contents($feed_file, $message."<br/>\n", FILE_APPEND);

    return $xmessage;
}

/**
 * Returns debug output representation of any variable
 * @param $v - variable
 * @return string
 */
function xlogvar($v)
{
    global $logval_level;
    $logval_level++;
    if (is_array($v)) {
        $m = "[ ";
        $first = true;
        foreach ($v as $i=>$a) {
            if ($first)
                $first = false; else $m .= ", ";
            $m .= "<<$i:>>".logvar($a);
        }
        $m .= " ]";

    } else
        if (is_object($v))
            $m = (get_class($v)); else
            if (is_numeric($v))
                $m = $v;
            else $m = '"' . htmlspecialchars($v) . '"';

    $logval_level--;
    if (!$logval_level)
        if (strlen(strip_tags($m))>32) { // Let's hide it
            $r=rand(1,1000000);
            $trim = substr($m,0,26);
            // Fix &; break
            if ($p=strrpos($trim,'&'))
                if (strrpos($trim,';')<$p) {
                    $p = strpos($m,';',$p);
                    $trim = substr($m,0,$p+1);
                }
            // Fix <<>> break
            if ($p=strrpos($trim,'<<'))
                if (strrpos($trim,'>>')<$p) {
                    $p = strpos($m,'>>',$p);
                    $trim = substr($m,0,$p+2);
                }
            $trim = str_replace(array('<<','>>'),array("<span class='debug_index'>",'</span>'),$trim);
            $m = str_replace(array('<<','>>'),array("<span class='debug_index'>",'</span>'),$m);
            $m= "<span class='debug_var_trunc' id='s$r' onclick='document.getElementById(\"s$r\").style.display=\"none\";document.getElementById(\"f$r\").style.display=\"inline\"'>".$trim."...</span><span class='debug_var_full' id='f$r' style='display:none' onclick='document.getElementById(\"s$r\").style.display=\"inline\";document.getElementById(\"f$r\").style.display=\"none\"'>$m</span>";
        }

    if (!$logval_level) return "<span class='debug_var'>$m</span>";
    else return $m;
}

ini_set('log_errors',1);


// ======================== Other functions

function filesize_string($size) {
    $measure = 0;
    while ($size>=1024) {
        $size /= 1024;
        $measure++;
    }
    if ($size<10) $size = round($size,2);
    else $size = round($size);

    $measures = array("bytes", "Kb", "Mb", "Gb", "Tb");
    $r = $size." ".$measures[$measure];
    re($r); return $r;
}

function money($text) {
    $prices = explode('-',$text);
    foreach ($prices as $price) {
        $x = preg_replace('/[^0-9.,]/i', '', $price);
        if ((int) $x > 0) break;
    }
    if (substr($x,-3,1)==",") {
        $x = substr($x,0,-3).".".substr($x,-2);
        $x = str_replace(",","",$x);
    }
    $x = str_replace(",","",$x);
    $price = round( abs($x),2 );
    re($price); return $price;
}

function remove_if($needle, $source) {
    if (is_array($source)) {
        foreach ($source as $i=>&$elem) {
            $elem = remove_if($elem,$needle);
            if (!$elem) unset($source[$i]);
        }
    } else
        if (find($needle,$source)) $source = false;
    re($source); return $source;
}

function shorten_text($text, $maxlen = 200) {
    $html_text = $text;
    $text = replace('<*>','',$text);
    $tags = ($html_text!=$text);
    $shorten = false;

    if (strlen8($text) > $maxlen) {
        $shorten = true;
        $otext = $text;
        $text = substr8($text,0,$maxlen);
        if (!trim($text)) $text = $otext;
        if ($p=strrpos($text, " ") and ($p*2>strlen($text)))
            $text = substr($text,0,$p);
    }

    if ($tags and $shorten) {
        $p=0; $hp=0;
        while ($p<strlen($text)) {
            if ($text[$p] == $html_text[$hp]) {
                $p++; $hp++; continue;
            }
            $hp2 = strpos($html_text,'>',$hp)+1;
            $tag = substr($html_text,$hp,$hp2-$hp);
            $hp = $hp2;
            $text = substr($text,0,$p).$tag.substr($text,$p);
            $p += strlen($tag);
        }
        $text = close_tags($text);
    }

    if ($shorten) $text .= " ...";
    re($text); return $text;
}

/**
 * Uzip ZIP file to spefied path. If it is external one, it is downloaded
 * @param $file
 * @param bool $path
 * @return bool - unzip result
 */
function unzip($file, $path = ".") {
    $external = strpos($file,'//');
    if ($external) {
        $file = http_get_file($file);
        if (!$file) return re(false);
    }
    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        $zip->extractTo($path);
        $zip->close();
        if ($external) @unlink($file);
        re(true); return true;
    }
    return re(false);
}

/**
 * Check matching tags at html and fix unclosed ones
 * @param $http_html
 * @return string - fixed html
 */
function close_tags($http_html) {
    if (!trim($http_html)) { re(false); return false; }

    $tags=insides('<','>',$http_html);
    foreach ($tags as &$t)
        if ($p=strpos($t,' ')) $t=substr($t,0,$p);
    $before='';
    $stack=array();$p=-1;
    foreach ($tags as $tag) {
        if ($tag[0]=='/') {
            // Closing tag
            $tag=substr($tag,1);
            while (($p>=0) and ($stack[$p]!=$tag)) $p--;
            if ($p==-1) $before="<$tag>".$before; // Closing tag not found
            else $p--;
        } else if (!strpos($tag,'/') and (strpos($tag,'!')===false)) $stack[++$p]=$tag;
    }
    $after='';
    while ($p>=0) $after.='</'.$stack[$p--].'>';
    re($before.$http_html.$after); return $before.$http_html.$after;
}

/**
 * Merge arrays so first element is from first array, second - from second and so on
 * @return array
 */
function shred_arrays() {
    $arrs = func_get_args();
    if (empty($arrs[0])) return array();
    foreach ($arrs as $i=>$arr)
        if (@!count($arr)) unset($arrs[$i]);
    if (!count($arrs)) return array();
    $r = array();
    foreach ($arrs[0] as $i=>$dummy)
        foreach ($arrs as $arr)
            $r [] = @$arr[$i];
    re($r); return $r;
}

/**
 * Random number, element or text
 * If no {data} specified - random float number from 0 to 1
 * If {data} is some number - random integer from 0 to {data}
 * If {data} is array - random element from array
 *
 * Also such a variant is supported:
 * {random("text1", "text2", .. "textN")}
 * This returns random element.
 * @param $data
 * @return mixed
 */
function random($data = false) {
    if (func_num_args()>1) $data=func_get_args();
    if (!$data) $r = (float)rand()/(float)getrandmax();
    elseif (is_array($data)) $r=$data[ array_rand($data) ];
    else $r=rand(0,$data);

    re($r); return $r;
}

// Make translit (for URLs)
function translit($name)
{
    $rus = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');
    $rusUp = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', ' ');
    $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sh', '', 'i', '', 'e', 'u', 'ya', '-');
    $characters = 'abcdefghijklmnopqrstuvwxyz1234567890-_';

    $res = str_replace($rus, $lat, trim($name));
    $res = str_replace($rusUp, $lat, $res);
    $res = iconv("UTF-8", "ASCII//IGNORE", $res); //TODO: Should be TRANSLIT, but PHP7 dies

    $return = '';

    for ($i = 0; $i < strlen($res); $i++) {
        $c = strtolower(substr($res, $i, 1));
        if (strpos($characters, $c) === false)
            $c = '';
        $return .= $c;
    }

    re($return); return $return;
}

// Returns the value of global variable, or $default if it doesn't exist
function g($global_variable_name, $default = false)
{
    if (!$default or isset($GLOBALS[$global_variable_name]))
        return @$GLOBALS[$global_variable_name];

    return $default;
}

function sku_gen($title = '', $code = false) {
    $prefix = '';
    foreach (explode(" ", $title) as $word) {
        if (strlen8($word)<3) continue;
        if (!ctype_upper($word[0])) continue;
        $prefix .= $word[0];
        if (strlen($prefix) > 2) break;
    }

    if (!$code) $code = substr(abs(crc32(upcase($title))), -6);
    if (strlen($prefix)>1) $code = $prefix.'-'.$code;
    return re($code);
}