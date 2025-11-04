<?php

namespace Features\Subscription\Subscribe\Application;

use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

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
                    planId: $request->input('plan_id'),
                    useTrial: $request->input('use_trial', false),
                    quantity: $request->input('quantity')
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
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?? Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout session created',
            'data' => $session->toArray(),
        ], Response::HTTP_CREATED);
    }
}
