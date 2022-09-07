<?php

/**
 * BOOTSTRAP FOR REALTIME THREAT
 *
 *
 *
 */

require_once '/var/www/html/bootstrap/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';

ob_start();

try {

    // remove setLocale PHP function -> cannot be used (is not usefull) in PHP threat
    runkit7_function_redefine('setLocale','$category,$locales','');
    /*
    runkit7_function_redefine('setLocale',function($category,$locales) {
        // do nothing
    },'');
    */

    // start app kernel with request
    $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );

} catch (\Exception $err) {

    echo "Exception: ".$err->getMessage();

}

$errtxt = ob_get_flush ();

return $errtxt;
