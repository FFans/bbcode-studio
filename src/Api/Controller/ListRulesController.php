<?php

namespace FFans\BbcodeStudio\Api\Controller;

use FFans\BbcodeStudio\Api\RuleSerializer;
use FFans\BbcodeStudio\RuleRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListRulesController extends AbstractRuleController implements RequestHandlerInterface
{
    public function __construct(protected RuleRepository $rules, protected RuleSerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        return new JsonResponse([
            'data' => array_map($this->serializer->serialize(...), $this->rules->all()),
        ]);
    }
}
