<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class HandleCrudExceptions
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFound($request, $e);
        } catch (ValidationException $e) {
            // Let Laravel handle validation exceptions normally
            throw $e;
        } catch (Exception $e) {
            return $this->handleGenericException($request, $e);
        }
    }

    /**
     * Handle ModelNotFoundException.
     */
    protected function handleNotFound(Request $request, ModelNotFoundException $e): Response
    {
        Log::warning('Resource not found', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'exception' => $e->getMessage(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.',
            ], 404);
        }

        return back()
            ->with('error', 'The requested item could not be found.');
    }

    /**
     * Handle generic exceptions.
     */
    protected function handleGenericException(Request $request, Exception $e): Response
    {
        Log::error('CRUD Operation Failed', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // User-friendly message - never expose technical details
        $message = 'An error occurred while processing your request. Please try again.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 500);
        }

        return back()
            ->withInput()
            ->with('error', $message);
    }
}
