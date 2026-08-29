<?php

namespace FFans\BbcodeStudio\Formatter;

use Flarum\User\User;
use s9e\TextFormatter\Parser;

class RegisterShortLinkResolver
{
    public function __construct(protected ShortLinkResolver $resolver)
    {
    }

    public function __invoke(Parser $parser, mixed $context, string $text, ?User $actor = null): string
    {
        $parser->registeredVars['bbcodeStudio.shortLinkResolver'] = $this->resolver;

        return $text;
    }
}
