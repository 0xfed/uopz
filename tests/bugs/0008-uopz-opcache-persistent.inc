<?php
function instrumented($value) {
	return "orig-$value";
}

class Plugin {
	public static function add_action($hook) {
		return "orig-$hook";
	}
}

if (!uopz_set_return('instrumented', function ($value) {
	return "wrap-$value";
}, true)) {
	fwrite(STDERR, "set_return instrumented failed\n");
	exit(1);
}
if (!uopz_set_return(Plugin::class, 'add_action', function ($hook) {
	return "wrap-$hook";
}, true)) {
	fwrite(STDERR, "set_return add_action failed\n");
	exit(1);
}
if (!uopz_set_return('strlen', function ($s) {
	return 7;
}, true)) {
	fwrite(STDERR, "set_return strlen failed\n");
	exit(1);
}

echo instrumented('x'), "\n";
echo Plugin::add_action('init'), "\n";
echo strlen('abc'), "\n";
echo function_exists('instrumented') ? "fn-ok\n" : "fn-missing\n";
echo class_exists('Plugin') ? "class-ok\n" : "class-missing\n";
echo method_exists('Plugin', 'add_action') ? "method-ok\n" : "method-missing\n";
