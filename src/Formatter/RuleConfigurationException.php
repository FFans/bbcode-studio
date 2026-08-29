<?php

namespace FFans\BbcodeStudio\Formatter;

use InvalidArgumentException;
use Throwable;

final class RuleConfigurationException extends InvalidArgumentException
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $parameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($translationKey, 0, $previous);
    }
}
