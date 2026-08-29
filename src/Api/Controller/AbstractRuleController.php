<?php

namespace FFans\BbcodeStudio\Api\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractRuleController
{
    protected function assertAdmin(ServerRequestInterface $request): void
    {
        RequestUtil::getActor($request)->assertAdmin();
    }

    /** @return array<string, mixed> */
    protected function attributes(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) && is_array($body['data']['attributes'] ?? null)
            ? $body['data']['attributes']
            : [];
    }

    protected function routeId(ServerRequestInterface $request): int
    {
        return (int) ($request->getQueryParams()['id'] ?? 0);
    }
}
