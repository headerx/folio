<?php

namespace Laravel\Folio;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Pipeline;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Laravel\Folio\Pipeline\MatchedView;

class RequestHandler
{
    /**
     * Create a new request handler instance.
     */
    public function __construct(protected MountPath $mountPath,
                                protected ?Closure $renderUsing = null)
    {
    }

    /**
     * Handle the incoming request using Folio.
     */
    public function __invoke(Request $request, string $uri): Response
    {
        $matchedView = (new Router(
            $this->mountPath->path
        ))->resolve($uri) ?? abort(404);

        return (new Pipeline(app()))
            ->send($request)
            ->through($this->middleware($matchedView))
            ->then(function ($request) use ($matchedView) {
                if ($this->renderUsing) {
                    return call_user_func($this->renderUsing, $request, $matchedView);
                }

                return new Response(
                    View::file($matchedView->path, $matchedView->data),
                    200,
                    [
                        'Content-Type' => 'text/html',
                        'X-Folio' => 'True',
                    ]
                );
            });
    }

    /**
     * Get the middleware that should be applied to the matched view.
     */
    protected function middleware(MatchedView $matchedView): array
    {
        return Route::resolveMiddleware(
            $this->mountPath
                ->middleware
                ->match($matchedView)
                ->prepend('web')
                ->merge($matchedView->inlineMiddleware())
                ->unique()
                ->values()
                ->all()
        );
    }
}
