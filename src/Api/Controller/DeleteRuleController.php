<?php

namespace FFans\BbcodeStudio\Api\Controller;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\Frontend\ForumStyleAssets;
use Flarum\Formatter\Formatter;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteRuleController extends AbstractRuleController implements RequestHandlerInterface
{
    public function __construct(
        protected Formatter $formatter,
        protected ForumStyleAssets $styleAssets,
        protected TranslatorInterface $translator,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);
        $id = $this->routeId($request);
        $rule = BbcodeRule::query()->findOrFail($id);

        if ($rule->builtin_key !== null) {
            throw new PermissionDeniedException(
                $this->translator->trans('ffans-bbcode-studio.api.validation.built_in_delete')
            );
        }

        $rule->delete();
        $this->formatter->flush();
        $this->styleAssets->markDirty();

        return new EmptyResponse(204);
    }
}
