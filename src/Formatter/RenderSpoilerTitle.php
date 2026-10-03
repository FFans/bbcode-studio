<?php

namespace FFans\BbcodeStudio\Formatter;

use Flarum\Locale\TranslatorInterface;
use s9e\TextFormatter\Renderer;

class RenderSpoilerTitle
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function __invoke(Renderer $renderer, mixed $context, string $xml): string
    {
        $renderer->setParameter(
            'L_BBCODE_STUDIO_SPOILER',
            $this->translator->trans('ffans-bbcode-studio.forum.spoiler.title')
        );

        return $xml;
    }
}
