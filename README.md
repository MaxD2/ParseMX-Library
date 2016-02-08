# ParseMX Library
PHP Library for HTTP data retrieval and manipulation. Tailored for fast simple scripts creating - like CRON jobs or micro-services.
All the code is stored into single `parsemx.php` file. Library includes everything you may need for common tasks:
- HTTP requests, including images and other files download
- automatic proxy servers usage
- CSS selectors data retrieval
- quick MySQL functions
- debug output and logging
- script execution control
- config values storage
- hash cache

Here is the shortest script sample, that receives all IMDB Top 250 films titles:
```
require 'parsemx.php';
http_get('http://www.imdb.com/chart/top/');
$titles = tags_text('.titleColumn a');
```

Here is more complicated [sample](https://github.com/MaxD2/ParseMX-Library/blob/master/samples/imdb.php) and its [result](http://devs.mx/parsemx-lib/imdb.php).

## HTTP Requests
Requests params and their default values:
```
$http_curl_timeout = 20;
$http_user_agent = false; // FALSE = Google bot  TRUE = latest Chrome
$http_headers = array(); // headers array, index is header name
$http_cookies = false;
$http_referer = false;
$http_auth = false;
$http_encoding = false;
$http_use_proxies = false;
$http_cache = false;
```
All this functions return flatted HTML and fill global result vars:

`http_get($url)`

`http_post($url, $data)`

`http_ajax($url, $data)`

`http_get_file($url, $save_path= '.', $access_path = false)` - fetch file into folder and return its name if successful. 
`$access_path` is resulting path addition

Global result vars:
```
$http_code // HTTP operation code (200 is OK)
$http_html // Flatted HTML
$http_ohtml // Original HTML
```

# Data Retrieval

Data retrieval functions use "source" concept. All this functions has "source" param (the last one), that can be omitted.
In this case current source will be used. HTTP functions `http_get`, `http_post` & `http_ajax` set the retrieved page as default
source.

`set_source($source)` - set default source for data retrieval functions


## Simple Data Retrieval

`inside($start, $end="")` - first entry between `$start` and `$end`. If `$start` is empty, returns text from the beginning of
 the source till `$end`. If `$end` is empty, returns text from `$end` till the end of the source.
 Returns `false`, if no entry was found.

There are several inside function variations:

`if_inside` - `inside` result, or original source if no entry was found

`insides` - array of all entries

`find($text)` - `true`, if `$text` is found in source. Case insensitive. `$text` may be a list of comma-separated words.
Trailing "~" serves as not-sign, "+" as AND.

Example: `find("~car, bike, horse + ride")` - `true`, if in the default source there is
no "car", there is "bike", or "horse" and "ride" simultaneously.

`replace($search=false, $replace='')` - replace `$search` with `$replace`. Case insensitive.
If `$replace` is empty, `$search` entries are removed.
If `$search` is empty, `$replace_file` ("replace.txt" by default) entries are used.

`rreplace($search, $replace='')` - replace with REGEXP. Case insensitive.

`replace_inside($find, $replace, $start, $end)` - replaces `$find` with `$replace` in the places starting with `$start` and ending with `$end`

`inch_to_cm` - replaces inches to centimeters at source

## CSS Selectors Data Retrieval

All `tag_...` functions take CSS `$selector` as param and return first value or `false` if nothing found.
They all have `tags_...` variation, that returns array of all values.

`tag_href` - `href` attr of the tag

`tag_link` - link from the tag. If specified tag doesn't have one, its contents and parent tag will be searched for links

`tag_text` - plain text of tag content

`tag_image` - image from the tag. If specified tag doesn't have one, its contents and parent tag will be searched for images.
Tends to find big image instead of thumb.

`tag_html` - html contents of the tag (including the tag itself)

`tag_attr($selector, $attr)` - get attribute from the tag

`url($url)` - full url, in case of relative url it is transformed to full

`urls($urls)` - make array of urls full


## Quick MySQL Functions
Connect and work with MySQL databases with less code possible. Databases are considered UTF-8.
If database doesn't exist, it is created automatically.
```
$q_database = "parsemx";
$q_user = "root";
$q_password = "root";
$q_server = "localhost";
```
`qq($query)` - execute query, returns array of result rows. Connects to database, if not connected.
If result rows consist of only one column, you will get just an array of this column values.

`q($query)` - execute query, returns first row from result. Connects to database, if not connected.
If result row consist of only one column, you will get just value of this column.

`q_escape($text)` - escapes text and adds '' to it.

`q_affected()` - returns number of last query affected rows

`q_last_id()` - returns id of the entity inserted by last query

## Debug Output and Logging

```
$log_file = 'logs.html';
```

`begin_debug()` - start debug output

`end_debug()` - ends debug output

`dmsg($message)` - show `$message`, if debug output is on

`xlog($message)` - write `$message` to log, and show it if debug output is on

`xwarn($message)` - log warning message (red)

`xnotice($message)` - log notice message (purple)

`xsuccess($message)` - log success message (green)


## Script Execution Control

`script_live()` - indicates that your script is live and gives it another 5 minutes (by default) to execute.
                Call it from some long-running cycles

`script_check_duplicate($name=false, $timeout_mins = 5)` - If another instance of your script is already working, this function will finish current script.
Call it at the beginning of your script, if you are invoking it with CRON.


## Config Values Storage
```
$mx_config_file = 'config.mx';
```
`mx_config_set($key, $value = false)` - set config value, $value may be any type of variable

`mx_config_get($key)` - get config value


## Hash Cache
```
$hash_cache_folder = "cache";
$hash_cache_maxtime = 2 /* Hours */ * 60 * 60;
```
`save_hash_cache($key, $data)`

`load_hash_cache($key)`

`clear_hash_cache()`

## UTF-8 String Functions
`upcase($text)`
`lowcase($text)`

This functions are equivalents of regular PHP functions:
`stripos8`
`strlen8`
`substr8`

## Other functions

`filesize_string($size)` - nicely formatted file size
`money($text)` - float money value from any text, automatically detects cents delimeters to obtain correct value

`remove_if($needle, $source_array)` - remove all entries that contain `$needle` from $source array.
`$needle` takes all the params find function takes.

`shorten_text($text, $maxlen = 200)` - brakes the text at word and adds "..." if the text was shortened
`unzip($file, $path = ".")` - extract ZIP file, supports URL
`close_tags($http_html)` - closes unmatched tags
`shred_arrays($array1, $array2, ...)` - returns array, that contains first elem from $array1, second elem from $array2 and so on.

`random($param = false)` - universal random function:
 - without param returns float between 0 and 1
 - if param is number, returns integer between 0 and param number
 - if param is array, returns random value from array
 - if there are several params, returns random param

`translit($name)`

`g($var_name, $default = false)` - returns the value of global variable, or $default if it doesn't exist
