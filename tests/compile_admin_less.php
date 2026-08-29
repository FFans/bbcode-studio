<?php

use Flarum\Frontend\Compiler\LessCompiler;
use Flarum\Frontend\Compiler\Source\FileSource;
use Flarum\Settings\SettingsRepositoryInterface;

require __DIR__.'/../vendor/autoload.php';

$reflection = new ReflectionClass(LessCompiler::class);
$compiler = $reflection->newInstanceWithoutConstructor();
$compiler->setCacheDir(sys_get_temp_dir());
$settings = new class implements SettingsRepositoryInterface {
    public function all(): array
    {
        return [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value): void
    {
    }

    public function delete(string $keyLike): void
    {
    }
};

$reflection->getParentClass()->getProperty('settings')->setValue($compiler, $settings);
$css = $reflection->getMethod('compile')->invoke(
    $compiler,
    [
        new FileSource(__DIR__.'/../vendor/flarum/core/less/common/variables.less'),
        new FileSource(__DIR__.'/../less/admin.less', 'ffans-bbcode-studio'),
    ]
);

if (! str_contains($css, '.Form-group.BbcodeStudioSourceRules>.BbcodeStudioSourceRules-heading')) {
    throw new RuntimeException('The media source heading rule was not compiled.');
}

if (! str_contains($css, '.BbcodeStudioRuleTest-result')) {
    throw new RuntimeException('The media URL test result rule was not compiled.');
}

if (! str_contains($css, '.BbcodeStudioSourceRule-number')) {
    throw new RuntimeException('The media source rule number was not compiled.');
}

echo "Flarum admin Less compilation passed.\n";
