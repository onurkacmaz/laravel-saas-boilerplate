<?php

namespace Features\Subscription\Subscribe\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[Group('Subscriptions')]
class SubscribeController
{
    /**
     * Subscribe to a plan.
     */
    public function __invoke(SubscribeRequest $request, SubscribeHandler $handler): JsonResponse
    {
        try {
            $session = $handler(
                command: new Subscribe(
                    planId: (string) $request->validated('plan_id'),
                    // The plan's trial applies unless the client opts out with use_trial=false.
                    useTrial: $request->boolean('use_trial', true),
                    quantity: $request->filled('quantity') ? $request->integer('quantity') : null,
                    organization: TenantContext::getOrganization()
                )
            );
        } catch (ValidationException $exception) {
            $response = [
                'status' => 'error',
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ];

            return response()->json($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => __($exception->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Throwable $e) {
            // Never leak payment provider / infrastructure error details to clients.
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => __('Unable to create the checkout session. Please try again later.'),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Checkout session created'),
            'data' => $session->toArray(),
        ], Response::HTTP_CREATED);
    }
}
