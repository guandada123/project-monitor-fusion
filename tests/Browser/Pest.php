<?php

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
    Tests\Browser\BrowserTestCase::class,
)->in('Browser');
