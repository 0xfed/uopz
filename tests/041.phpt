--TEST--
uopz_set_hook on undefined function and method
--EXTENSIONS--
uopz
--INI--
uopz.disable=0
--FILE--
<?php
// Hook a function that doesn't exist yet
var_dump(uopz_set_hook("not_yet_defined", function() {
	var_dump("hook on late-defined function");
}));

// Define it, then call it
function not_yet_defined() {
	var_dump("original function");
}

not_yet_defined();

// Hook a method that doesn't exist yet, then add it
class Qux {
}

var_dump(uopz_set_hook(Qux::class, "lateMethod", function() {
	var_dump("hook on late-defined mtd");
}));

uopz_add_function(Qux::class, "lateMethod", function() {
	var_dump("original method");
});

(new Qux)->lateMethod();

// Hook on a class that doesn't exist yet
var_dump(uopz_set_hook("LateClass", "run", function() {
	var_dump("hook on late class");
}));

// Now define the class and call
eval('class LateClass { public function run() { var_dump("late class run"); } }');

(new LateClass)->run();
?>
--EXPECT--
bool(true)
string(29) "hook on late-defined function"
string(17) "original function"
bool(true)
string(24) "hook on late-defined mtd"
string(15) "original method"
bool(true)
string(18) "hook on late class"
string(14) "late class run"
