<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyWebhookSecret;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api/v1',
            'middleware' => ['auth:sanctum', 'active'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'verify_webhook_secret' => VerifyWebhookSecret::class,
        ]);
        $middleware->api(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::error('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return ApiResponse::error($e->getMessage() ?: 'This action is unauthorized.', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            $model = class_basename($e->getModel());

            return ApiResponse::error("{$model} not found.", 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return ApiResponse::error('The requested endpoint does not exist.', 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::error(__('messages.validation_failed'), 422, $e->errors());
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            return ApiResponse::error($e->getMessage() ?: 'An error occurred.', $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (app()->hasDebugModeEnabled()) {
                return ApiResponse::error($e->getMessage(), 500, [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            return ApiResponse::error('Server error.', 500);
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // 15-minute google:sync-leads polling removed — leads now come in
        // live via the webhook (app/Services/GoogleSheetLeadImporter::
        // importFromWebhookPayload()), with the super_admin-only "Sync from
        // Google Sheets" button (LeadImportController::syncFromGoogleSheets())
        // for a one-time baseline seed instead of continuous polling.
        $schedule->command('appointments:send-today-reminders')->dailyAt('07:00');
        $schedule->command('appointments:send-due-reminders')->everyMinute()->withoutOverlapping();
    })
    ->create();
