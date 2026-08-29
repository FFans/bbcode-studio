<?php

namespace FFans\BbcodeStudio\Api\Controller;

use FFans\BbcodeStudio\Api\RuleSerializer;
use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\Frontend\ForumStyleAssets;
use FFans\BbcodeStudio\Validation\RuleValidator;
use Flarum\Formatter\Formatter;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UpdateRuleController extends AbstractRuleController implements RequestHandlerInterface
{
    public function __construct(
        protected RuleValidator $validator,
        protected RuleSerializer $serializer,
        protected Formatter $formatter,
        protected ForumStyleAssets $styleAssets,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);
        $id = $this->routeId($request);
        $rule = BbcodeRule::query()->findOrFail($id);
        $rule->forceFill($this->validator->validate($this->attributes($request), $rule));
        $rule->save();
        $this->formatter->flush();
        $this->styleAssets->markDirty();

        return new JsonResponse(['data' => $this->serializer->serialize($rule)]);
    }
}
