--TEST--
uopz overrides survive a second request in the same process (opcache + built-in server)
--SKIPIF--
<?php
	include(__DIR__ . '/../skipif.inc');
	uopz_allow_exit(true);
	if (ini_get("opcache.enable_cli") === false) die("skip opcache required");
	if (!getenv('TEST_PHP_EXECUTABLE')) die("skip TEST_PHP_EXECUTABLE not set");
	if (!function_exists('proc_open')) die("skip proc_open required");
	if (!function_exists('fsockopen')) die("skip fsockopen required");
	if (!extension_loaded('Zend OPcache')) die("skip opcache required");
?>
--INI--
uopz.disable=0
opcache.enable=1
opcache.enable_cli=1
--FILE--
<?php
$php = getenv('TEST_PHP_EXECUTABLE');
$docroot = sys_get_temp_dir() . '/uopz-server-' . getmypid();
@mkdir($docroot, 0700, true);
copy(__DIR__ . '/0008-uopz-opcache-persistent.inc', $docroot . '/index.php');

$uopz = dirname(__DIR__, 2) . '/modules/uopz.so';
if (!is_file($uopz)) {
	$uopz = PHP_EXTENSION_DIR . '/uopz.so';
}
$opcache = PHP_EXTENSION_DIR . '/opcache.so';
if (!is_file($opcache)) {
	$matches = glob('/usr/lib/php/*/opcache.so');
	$opcache = $matches ? $matches[0] : $opcache;
}
if (!is_file($uopz) || !is_file($opcache)) {
	echo "missing extension binaries uopz=$uopz opcache=$opcache\n";
	exit(1);
}
$port = 20000 + (getmypid() % 10000);

$args = [
	$php,
	'-n',
	'-d', "zend_extension=$opcache",
	'-d', "extension=$uopz",
	'-d', 'opcache.enable=1',
	'-d', 'opcache.enable_cli=1',
	'-d', 'opcache.optimization_level=0x7FFFFFFF',
	'-d', 'opcache.validate_timestamps=0',
	'-d', 'opcache.revalidate_freq=0',
	'-d', 'uopz.disable=0',
	'-d', 'uopz.exit=1',
	'-S', "127.0.0.1:$port",
	'-t', $docroot,
];

$descriptors = [
	0 => ['pipe', 'r'],
	1 => ['file', $docroot . '/stdout.log', 'w'],
	2 => ['file', $docroot . '/stderr.log', 'w'],
];
$proc = proc_open($args, $descriptors, $pipes, $docroot);
if (!is_resource($proc)) {
	echo "failed to start server\n";
	exit(1);
}
fclose($pipes[0]);

$ready = false;
for ($i = 0; $i < 50; $i++) {
	usleep(100000);
	$fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
	if (is_resource($fp)) {
		fclose($fp);
		$ready = true;
		break;
	}
}
if (!$ready) {
	echo "server did not start\n";
	echo file_get_contents($docroot . '/stderr.log');
	proc_terminate($proc);
	proc_close($proc);
	exit(1);
}

function uopz_http_get($port, $label) {
	$fp = fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
	if (!is_resource($fp)) {
		echo "$label: connect failed $errstr\n";
		return;
	}
	fwrite($fp, "GET /index.php HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
	$response = stream_get_contents($fp);
	fclose($fp);
	$parts = explode("\r\n\r\n", $response, 2);
	$body = isset($parts[1]) ? $parts[1] : $response;
	echo "$label:\n";
	echo $body;
	if ($body !== '' && substr($body, -1) !== "\n") {
		echo "\n";
	}
}

uopz_http_get($port, 'req1');
uopz_http_get($port, 'req2');
uopz_http_get($port, 'req3');

proc_terminate($proc);
proc_close($proc);

@unlink($docroot . '/index.php');
@unlink($docroot . '/stdout.log');
@unlink($docroot . '/stderr.log');
@rmdir($docroot);
?>
--EXPECT--
req1:
wrap-x
wrap-init
7
fn-ok
class-ok
method-ok
req2:
wrap-x
wrap-init
7
fn-ok
class-ok
method-ok
req3:
wrap-x
wrap-init
7
fn-ok
class-ok
method-ok
