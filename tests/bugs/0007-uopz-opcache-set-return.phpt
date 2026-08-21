--TEST--
uopz_set_return and uopz_set_hook remain effective with opcache enabled
--SKIPIF--
<?php
	include(__DIR__ . '/../skipif.inc');
	uopz_allow_exit(true);
	$opcache = ini_get("opcache.enable_cli");
	if ($opcache === false) die("skip opcache required");
?>
--INI--
uopz.disable=0
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=0x7FFFFFFF
opcache.validate_timestamps=0
opcache.revalidate_freq=0
--FILE--
<?php
function sink($value) {
	return "orig:$value";
}

class Target {
	public static function add_action($hook) {
		return "orig:$hook";
	}

	public function run($value) {
		return "orig:$value";
	}
}

var_dump(uopz_set_return('sink', function ($value) {
	return "wrap:$value";
}, true));
var_dump(uopz_set_return(Target::class, 'add_action', function ($hook) {
	return "wrap:$hook";
}, true));
var_dump(uopz_set_return(Target::class, 'run', function ($value) {
	return "wrap:$value";
}, true));
var_dump(uopz_set_return('strlen', function ($s) {
	return 42;
}, true));

$target = new Target;
var_dump(sink('a'));
var_dump(Target::add_action('init'));
var_dump($target->run('b'));
var_dump(strlen('abc'));

$hooked = 0;
var_dump(uopz_set_hook('sink', function ($value) use (&$hooked) {
	$hooked++;
}));
var_dump(sink('c'));
var_dump($hooked);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
string(6) "wrap:a"
string(9) "wrap:init"
string(6) "wrap:b"
int(42)
bool(true)
string(6) "wrap:c"
int(1)
