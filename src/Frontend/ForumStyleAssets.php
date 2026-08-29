<?php

namespace FFans\BbcodeStudio\Frontend;

use Flarum\Frontend\RecompileFrontendAssets;
use Flarum\Locale\LocaleManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class ForumStyleAssets
{
    public function __construct(
        protected Container $container,
        protected LocaleManager $locales,
        protected Dispatcher $events,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function markDirty(): void
    {
        (new RecompileFrontendAssets(
            $this->container->make('flarum.assets.forum'),
            $this->locales,
            $this->events,
            $this->settings,
        ))->markDirty();
    }
}
