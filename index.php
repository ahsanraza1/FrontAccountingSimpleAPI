<?php

use FAAPI\Account;
use FAAPI\Deposit;
use FAAPI\Customers;
use FAAPI\Dimensions;
use FAAPI\Payment;

ini_set('html_errors', false);
ini_set('xdebug.show_exception_trace', 0);

include_once('config_api.php');

global $security_areas, $security_groups, $security_headings, $path_to_root, $db, $db_connections;

$page_security = 'SA_API';

include_once(API_ROOT . "/session-custom.inc");
include_once(API_ROOT . "/vendor/autoload.php");

include_once(API_ROOT . "/util.php");

include_once(FA_ROOT . "/includes/date_functions.inc");
include_once(FA_ROOT . "/includes/data_checks.inc");

$rest = new \Slim\Slim(array(
    'log.enabled' => true,
    'mode' => 'debug',
    'debug' => true
));
$rest->setName('SASYS');

// API Login Hook
api_login();

$req = $rest->request();

define("RESULTS_PER_PAGE", 2);

class JsonToFormData extends \Slim\Middleware
{
    public function call()
    {
        $env = $this->app->environment();
        if (is_array($env['slim.input'])) {
            $env['slim.request.form_hash'] = $env['slim.input'];
        }
        $this->next->call();
    }
}

$rest->add(new JsonToFormData());
$rest->add(new \Slim\Middleware\ContentTypes());

// API Routes

$rest->container->singleton('customers', function () {
    return new Customers();
});
$rest->group('/customers', function () use ($rest) {
    // Get Customer General Info
    $rest->get('/:id', function ($id) use ($rest) {
        $rest->customers->getById($rest, $id);
    });
    // All Customers
    $rest->get('/', function () use ($rest) {
        $rest->customers->get($rest);
    });
});
// --------------------------------- Customers --------------------------------

// ----------------------------- Dimensions -----------------------------
$rest->container->singleton('dimensions', function () {
    return new Dimensions();
});
$rest->group('/dimensions', function () use ($rest) {
    // All Dimensions
    $rest->get('/', function () use ($rest) {
        $rest->dimensions->get($rest);
    });
});
// ----------------------------- Dimensions -----------------------------

// ------------------------------ Payment -------------------------------
$rest->container->singleton('payment', function () {
    return new Payment();
});
$rest->group('/payment', function () use ($rest) {
    // Insert Journal Entry
    $rest->post('/', function () use ($rest) {
        $rest->payment->post($rest);
    });
    // Update Journal Entry
    $rest->put('/:trans', function ($trans) use ($rest) {
        $rest->payment->put($rest, 1, $trans);
    });
    // // Delete Journal Entry
    $rest->delete('/:trans', function ( $trans) use ($rest) {
        $rest->payment->delete($rest, 1, $trans);
    });
});
// ------------------------------ Payment -------------------------------
// ------------------------------ Deposit -------------------------------
$rest->container->singleton('deposit', function () use ($rest) {
    return new Deposit();
});
$rest->group('/deposit', function () use ($rest) {
    $rest->post('/', function () use ($rest) {
        $rest->deposit->post($rest);
    });
    $rest->put('/:trans', function ( $trans) use ($rest) {
        $rest->deposit->put($rest, 2, $trans);
    });
    $rest->delete('/:trans', function ( $trans) use ($rest) {
        $rest->deposit->delete($rest, 2, $trans);
    });
});
// ------------------------------ Deposit -------------------------------
// ------------------------------ Account -------------------------------
$rest->container->singleton('account', function () use ($rest) {
    return new Account();
});
$rest->group('/account', function () use ($rest) {
    $rest->get('/:type', function ($type) use ($rest) {
       $rest->account->get($rest, $type);
    });
    $rest->get('/balance/:id', function ($id) use ($rest) {
       $rest->account->getBalance($rest, $id);
    });
});
// ------------------------------ Account -------------------------------
// Init API
$rest->run();
