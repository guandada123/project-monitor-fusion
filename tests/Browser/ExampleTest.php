<?php

use Laravel\Dusk\Browser;

test('basic example — welcome page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/welcome')
            ->assertSee('Project Monitor');
    });
});
