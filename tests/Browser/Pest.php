<?php

use Laravel\Dusk\Browser;
use Tests\Browser\BrowserTestCase;

/*
|--------------------------------------------------------------------------
| Browser Test Case
|--------------------------------------------------------------------------
|
| All tests in the `tests/Browser` directory will automatically inherit
| the `Tests\Browser\BrowserTestCase` base class, which extends
| `Tests\DuskTestCase` and adds `DatabaseTruncation` for database setup.
|
*/

uses(
    BrowserTestCase::class,
)->in('Browser');

// Suppress JS dialogs before each browser interaction to prevent
// UnexpectedAlertOpenException in headless CI environments.
beforeEach(function () {
    Browser::macro('suppressDialogs', function () {
        $this->script(
            'window.alert = function(){};'.
            'window.confirm = function(){return true;};'.
            'window.prompt = function(){return null;};'.
            'window.onbeforeunload = null;'
        );
        return $this;
    });

    Browser::macro('dismissAlert', function () {
        try {
            $this->driver->switchTo()->alert()->dismiss();
        } catch (\Exception $e) {
            // No alert present
        }
        return $this;
    });
});
