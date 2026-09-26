<?php

namespace Tests;

use App\Models\Item;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Dusk;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

/**
 * Browser tests run against `php artisan serve` on 127.0.0.1:8449 with
 * `.env.dusk.local` swapped in (see CLAUDE.md → Browser tests).
 *
 * `@name` selectors resolve to `data-test="name"` — the attribute this app
 * already used for its hooks — rather than Dusk's default `dusk="…"`.
 */
abstract class DuskTestCase extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * Browsers this test put a device-metrics override on, so {@see tearDown()} can lift it.
     *
     * @var array<int, Browser>
     */
    protected array $viewportOverriddenBrowsers = [];

    #[BeforeClass]
    public static function prepare(): void
    {
        Dusk::selectorHtmlAttribute('data-test');

        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /** Selector for one item's row in the vault list. */
    protected function row(Item $item): string
    {
        return "[data-test=\"item-row\"][data-item-id=\"{$item->id}\"]";
    }

    /**
     * Wait until Vue has mounted and every CSS animation has finished.
     *
     * The sheet slides in over ~500ms and the Inertia app mounts after load;
     * input typed or clicks landed before then are silently lost.
     */
    protected function settle(Browser $browser): Browser
    {
        return $browser
            ->waitUntil('!! document.querySelector("#app")?.__vue_app__')
            ->waitUntil('document.getAnimations().every(a => a.playState !== "running")');
    }

    /** Open an item's sheet and wait until its secrets have loaded. */
    protected function openItem(Browser $browser, Item $item): Browser
    {
        $this->settle($browser)
            ->click($this->row($item).' [data-test="item-open"]')
            ->waitFor('@item-edit')
            ->waitUntilEnabled('@item-edit');

        return $this->settle($browser);
    }

    /** Open the new-item sheet, ready to type into. */
    protected function openNewItem(Browser $browser): Browser
    {
        $this->settle($browser->visit('/vault'))
            ->click('@add-item')
            ->waitFor('@item-name');

        return $this->settle($browser);
    }

    /**
     * Emulate a phone viewport — and actually get one.
     *
     * Windows enforces a ~500px minimum window width that WebDriver's resize
     * silently honours, so `resize(375, 812)` proves nothing about a 375px
     * layout. CDP's device-metrics override is not subject to that minimum.
     * (Same helper as Reminders' DuskTestCase.)
     */
    protected function emulateMobileViewport(Browser $browser, int $width = 375, int $height = 812): void
    {
        (new ChromeDevToolsDriver($browser->driver))->execute(
            'Emulation.setDeviceMetricsOverride',
            [
                'width' => $width,
                'height' => $height,
                'deviceScaleFactor' => 1,
                'mobile' => true,
            ],
        );

        $this->viewportOverriddenBrowsers[] = $browser;
    }

    /**
     * Fail if the page scrolls sideways — after first proving the viewport
     * really is phone-width, so this can't go quietly green at desktop size.
     */
    protected function assertNoHorizontalOverflow(Browser $browser, string $where): void
    {
        [$scrollWidth, $clientWidth] = $browser->script([
            'return document.documentElement.scrollWidth;',
            'return document.documentElement.clientWidth;',
        ]);

        $this->assertLessThanOrEqual(
            375,
            $clientWidth,
            "The viewport is {$clientWidth}px, not 375px — mobile emulation is not in effect.",
        );

        $this->assertLessThanOrEqual(
            $clientWidth + 1,
            $scrollWidth,
            "{$where} scrolls horizontally at 375px: content is {$scrollWidth}px inside a {$clientWidth}px viewport.",
        );
    }

    /**
     * Lift any device-metrics override before the next test — Dusk reuses the
     * browser session across tests and a CDP override outlives its test.
     */
    protected function tearDown(): void
    {
        foreach ($this->viewportOverriddenBrowsers as $browser) {
            try {
                (new ChromeDevToolsDriver($browser->driver))
                    ->execute('Emulation.clearDeviceMetricsOverride', []);
            } catch (\Throwable) {
                // The browser may already be gone if the test failed hard.
            }
        }

        $this->viewportOverriddenBrowsers = [];

        parent::tearDown();
    }

    /**
     * Leave dusk.sqlite freshly migrated, so a failed run can't leave stray
     * rows for the next one to trip over.
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        Artisan::call('migrate:fresh');
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            '--window-size=1280,1400',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED'])
            || isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }
}
