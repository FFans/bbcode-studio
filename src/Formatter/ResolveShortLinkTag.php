<?php

namespace FFans\BbcodeStudio\Formatter;

use s9e\TextFormatter\Parser\Tag;

final class ResolveShortLinkTag
{
    /** @param list<string> $sourceHosts @param list<string> $targetHosts @param list<string> $patterns @param list<string> $requiredCaptures */
    public static function resolve(
        Tag $tag,
        ?ShortLinkResolver $resolver,
        array $sourceHosts,
        array $targetHosts,
        array $patterns,
        array $requiredCaptures
    ): bool {
        if (! $tag->hasAttribute('short_url')) {
            return true;
        }

        if ($resolver === null) {
            return false;
        }

        $captures = $resolver->resolve(
            (string) $tag->getAttribute('short_url'),
            $sourceHosts,
            $targetHosts,
            $patterns,
            $requiredCaptures
        );

        if ($captures === null) {
            return false;
        }

        foreach ($captures as $name => $value) {
            $tag->setAttribute($name, $value);
        }

        return true;
    }
}
