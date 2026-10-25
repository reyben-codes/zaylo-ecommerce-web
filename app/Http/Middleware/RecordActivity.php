<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records the routes listed in config/activity.php once they succeed: no error status,
 * and no validation errors or "error" message flashed by this request.
 */
class RecordActivity
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Kept for sign-out, when nobody is signed in any more by the time the response is ready.
        $signedInBefore = $request->user();

        $response = $next($request);

        try {
            $this->record($request, $response, $signedInBefore);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function record(Request $request, Response $response, $signedInBefore): void
    {
        $route = $request->route();
        // Route names contain dots, so they are looked up in the array rather than as a config path.
        $entry = $route?->getName() ? (config('activity.routes')[$route->getName()] ?? null) : null;
        if (! $entry || $response->getStatusCode() >= 400) {
            return;
        }

        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];        if (array_intersect($flashed, ['errors', 'error'])) {
            return;
        }

        $actor = $request->user() ?? $signedInBefore;
        if (! $actor) {
            return;
        }

        [$category, $description, $fields] = $entry + [2 => []];
        $subject = null;
        $properties = [];

        foreach ($route->parameters() as $key => $value) {
            if ($value instanceof Model) {
                $subject ??= $value;
            } elseif (is_scalar($value)) {
                $properties[$key] = Str::limit((string) $value, 120);
            }
        }

        foreach ($fields as $field) {
            $value = $request->input($field);
            if (is_bool($value) || is_scalar($value)) {
                $properties[$field] = is_bool($value) ? $value : Str::limit((string) $value, 200);
            }
        }

        if (in_array('status', $flashed, true) && is_string($result = $request->session()->get('status'))) {
            $properties['result'] = Str::limit($result, 250);
        } elseif ($response instanceof JsonResponse && is_string($result = $response->getData(true)['message'] ?? null)) {
            // Mobile API answers carry their confirmation in "message".
            $properties['result'] = Str::limit($result, 250);
        }

        $this->logger->record($route->getName(), $category, $description, $actor, $subject, $properties);
    }
}
