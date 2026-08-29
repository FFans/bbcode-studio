<?php

namespace FFans\BbcodeStudio\Api\Controller;

use FFans\BbcodeStudio\Formatter\MediaRuleTester;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TestMediaRuleController extends AbstractRuleController implements RequestHandlerInterface
{
    public function __construct(private MediaRuleTester $tester)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);
        $attributes = $this->attributes($request);
        $sourceRules = is_array($attributes['sourceRules'] ?? null) ? $attributes['sourceRules'] : [];

        return new JsonResponse([
            'data' => $this->tester->test(
                (string) ($attributes['url'] ?? ''),
                $sourceRules,
                (string) ($attributes['embedUrl'] ?? '')
            ),
        ]);
    }
}
