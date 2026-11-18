--TEST--
Each opcode PHP# adds is emitted where its job is and nowhere else: each op array of the Opcodes fixture lists the PHP# opcodes it holds
--FILE--
<?php
foreach (glob(__DIR__ . '/Opcodes/*.sharp') as $file) {
    // Opcache's dump after optimization prints every op array that runs, property hooks included. -l compiles the file
    // without running it, so its classes need no other class to link against.
    $dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
        . ' -n -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x20000 -l ' . escapeshellarg($file) . ' 2>&1');
    $lines = explode("\n", $dump);
    $held = [];
    $op_array = null;
    foreach ($lines as $index => $line) {
        if (preg_match('~^(\S.*):$~', $line, $name) && str_starts_with($lines[$index + 1] ?? '', '     ; (lines=')) {
            $op_array = $name[1];
            $held[$op_array] = [];
        } elseif ($op_array !== null && preg_match('~\bSHARP_[A-Z_]+~', $line, $opcode)) {
            $held[$op_array][$opcode[0]] = true;
        }
    }
    echo basename($file), "\n";
    foreach ($held as $op_array => $opcodes) {
        echo '  ', $op_array, ': ', $opcodes ? implode(', ', array_keys($opcodes)) : '-', "\n";
    }
}
?>
--EXPECT--
Box.sharp
  $_main: -
  Opcodes\Box::#fields: -
  Opcodes\Box::parse: SHARP_RECV_TYPE_ARGS, SHARP_TYPE_ARGS, SHARP_TYPE_OF
  Opcodes\Box::tryParse: SHARP_RECV_TYPE_ARGS, SHARP_TYPE_ARGS
  Opcodes\Box::#json: -
Sites.sharp
  $_main: -
  Opcodes\Sites::newGeneric: SHARP_TYPE_ARGS
  Opcodes\Sites::newPlain: -
  Opcodes\Sites::genericMethod: SHARP_RECV_TYPE_ARGS, SHARP_RETURN_ARRAY
  Opcodes\Sites::listParameter: SHARP_RECV_TYPE_ARGS
  Opcodes\Sites::intParameter: -
  Opcodes\Sites::isTypeParameter: SHARP_RECV_TYPE_ARGS, SHARP_TYPE_ARGS, SHARP_IS_TYPE
  Opcodes\Sites::isClass: -
  Opcodes\Sites::typeOfParameter: SHARP_RECV_TYPE_ARGS, SHARP_TYPE_ARGS, SHARP_TYPE_OF
  Opcodes\Sites::typeOfClass: -
  Opcodes\Sites::leaveOnNull: SHARP_LEAVE
  Opcodes\Sites::defaultOnNull: -
  Opcodes\Sites::returnsMap: SHARP_TYPE_ARGS, SHARP_RETURN_ARRAY
  Opcodes\Sites::returnsList: -
  Opcodes\Sites::callsInterface: SHARP_RETURNED, SHARP_RETURN_ARRAY
  Opcodes\Sites::staticMap: SHARP_TYPE_ARGS, SHARP_RETURN_ARRAY
  Opcodes\Sites::callsStaticMethod: SHARP_RETURN_ARRAY
  Opcodes\Sites::callsGenericInterface: SHARP_TYPE_ARGS, SHARP_RETURNED, SHARP_VERIFY_TYPE_ARGS
  Opcodes\Sites::callsOwnGeneric: SHARP_TYPE_ARGS, SHARP_VERIFY_TYPE_ARGS
  Opcodes\Sites::callsOwnMethod: -
  Opcodes\Sites::#fields: -
  Opcodes\Sites::parse: -
  Opcodes\Sites::tryParse: -
  Opcodes\Sites::#json: -
Source.sharp
  $_main: -
