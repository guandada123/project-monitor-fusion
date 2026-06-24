<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    use DatabaseTruncation;

    /**
     * The seeder to run after each database truncation/migration.
     * Ensures roles and permissions are always available for browser tests.
     */
    protected $seeder = RolePermissionSeeder::class;

    /**
     * Prepare for Dusk test execution.
     * Starts the ChromeDriver for browser automation.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Set up the test — truncate database tables and run seeder
     * to ensure a clean state with seeded roles/permissions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateDatabaseTables();
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
            '--disable-extensions',
            '--disable-notifications',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()
                ->setCapability(
                    ChromeOptions::CAPABILITY, $options
                )
                ->setCapability(
                    'unhandledPromptBehavior',
                    ['default' => 'dismiss']
                )
        );
    }
}
