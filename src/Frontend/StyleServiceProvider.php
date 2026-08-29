<?php

namespace FFans\BbcodeStudio\Frontend;

use FFans\BbcodeStudio\Formatter\BbcodeRuleDefinition;
use FFans\BbcodeStudio\RuleRepository;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use Illuminate\Contracts\Container\Container;

class StyleServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->resolving('flarum.assets.forum', function (Assets $assets, Container $container) {
            $assets->css(function (SourceCollector $sources) use ($container) {
                $sources->addString(
                    fn () => BbcodeRuleDefinition::stylesheet($container->make(RuleRepository::class)->enabled()),
                    'ffans_bbcode_studio_styles'
                );
            });
        });
    }
}
