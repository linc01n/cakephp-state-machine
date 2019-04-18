<?php
/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

define('ROOT', dirname(__DIR__));
define('APP_DIR', 'src');
define('APP', ROOT . DS . APP_DIR . DS);
define('CONFIG', ROOT . DS . 'config' . DS);
define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
define('TESTS', ROOT . DS . 'tests' . DS);
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', ROOT . DS . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);
require CORE_PATH . 'config' . DS . 'bootstrap.php';

$_SERVER['PHP_SELF'] = '/';

define('DATABASE_TEST_SQLITE', sys_get_temp_dir().DS.'statemachine-test.sqlite');
try {
    $datasourceExists = (bool)Cake\Datasource\ConnectionManager::get('test');
} catch (Exception $e) {
    $datasourceExists = false;
}
if (!$datasourceExists && extension_loaded('sqlite3')) {
    Cake\Datasource\ConnectionManager::setConfig('test', [
        'className' => Cake\Database\Connection::class,
        'driver' => Cake\Database\Driver\Sqlite::class,
        'database' => DATABASE_TEST_SQLITE,
    ]);
}
