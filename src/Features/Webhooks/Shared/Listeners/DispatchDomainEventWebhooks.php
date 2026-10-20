<?php

namespace Features\Webhooks\Shared\Listeners;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Tenant\AcceptInvitation\Events\InvitationAccepted;
use Features\Tenant\AddUserToOrganization\Events\UserAddedToOrganization;
use Features\Tenant\CancelInvitation\Events\InvitationCanceled;
use Features\Tenant\DeleteOrganization\Events\OrganizationDeleted;
use Features\Tenant\RejectInvitation\Events\InvitationRejected;
use Features\Tenant\RemoveUserFromOrganization\Events\UserRemovedFromOrganization;
use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\UpdateOrganization\Events\OrganizationUpdated;
use Features\Tenant\UpdateUserRoleOnOrganization\Events\UserRoleUpdated;
use Features\Webhooks\Shared\Support\WebhookDispatcher;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates internal domain events into outgoing webhook events.
 */
class DispatchDomainEventWebhooks
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrganizationUpdated::class => 'handle',
            OrganizationDeleted::class => 'handle',
            UserAddedToOrganization::class => 'handle',
            UserRemovedFromOrganization::class => 'handle',
            UserRoleUpdated::class => 'handle',
            InvitationSent::class => 'handle',
            InvitationAccepted::class => 'handle',
            InvitationRejected::class => 'handle',
            InvitationCanceled::class => 'handle',
            SubscriptionCreated::class => 'handle',
            SubscriptionUpdated::class => 'handle',
            SubscriptionCanceled::class => 'handle',
            PaymentSucceeded::class => 'handle',
            PaymentFailed::class => 'handle',
        ];
    }

    public function handle(object $event): void
    {
        $mapped = $this->map($event);

        if ($mapped === null) {
            return;
        }

        [$organizationId, $name, $data] = $mapped;

        // Webhooks are a side effect: they must never break the operation that
        // triggered them.
        try {
            $this->dispatcher->dispatch($organizationId, $name, $data);
        } catch (Throwable $exception) {
            Log::warning('Unable to dispatch webhooks.', ['event' => $name, 'exception' => $exception->getMessage()]);
        }
    }

    /**
     * @return array{0: string|null, 1: string, 2: array}|null
     */
    private function map(object $event): ?array
    {
        return match (true) {
            $event instanceof OrganizationUpdated => [$event->organization->id, 'organization.updated', $this->organization($event->organization)],
            $event instanceof OrganizationDeleted => [$event->organization->id, 'organization.deleted', $this->organization($event->organization)],
            $event instanceof UserAddedToOrganization => [$event->organization->id, 'member.added', $this->member($event->user, $event->roleId)],
            $event instanceof UserRemovedFromOrganization => [$event->organization->id, 'member.removed', $this->member($event->user)],
            $event instanceof UserRoleUpdated => [$event->organization->id, 'member.role_updated', $this->member($event->user, $event->roleId)],
            $event instanceof InvitationSent => [$event->invitation->organization_id, 'invitation.sent', $this->invitation($event->invitation)],
            $event instanceof InvitationAccepted => [$event->invitation->organization_id, 'invitation.accepted', $this->invitation($event->invitation)],
            $event instanceof InvitationRejected => [$event->invitation->organization_id, 'invitation.rejected', $this->invitation($event->invitation)],
            $event instanceof InvitationCanceled => [$event->invitation->organization_id, 'invitation.canceled', $this->invitation($event->invitation)],
            $event instanceof SubscriptionCreated => [$event->subscription->organization_id, 'subscription.created', $this->subscription($event->subscription)],
            $event instanceof SubscriptionUpdated => [$event->subscription->organization_id, 'subscription.updated', $this->subscription($event->subscription)],
            $event instanceof SubscriptionCanceled => [$event->subscription->organization_id ?? null, 'subscription.canceled', $this->subscription($event->subscription)],
            $event instanceof PaymentSucceeded => [$event->organization->id, 'payment.succeeded', $this->payment($event->paymentData)],
            $event instanceof PaymentFailed => [$event->organization->id, 'payment.failed', $this->payment($event->paymentData)],
            default => null,
        };
    }

    private function organization(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'is_active' => (bool) $organization->is_active,
        ];
    }

    private function member(User $user, ?string $roleId = null): array
    {
        return array_filter([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $roleId,
        ], fn ($value) => $value !== null);
    }

    private function invitation(Invitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role_id' => $invitation->role_id,
            'status' => $invitation->status,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ];
    }

    private function subscription(object $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id ?? null,
            'status' => $subscription->stripe_status,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
    }

    private function payment(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'amount' => $data['amount_paid'] ?? $data['amount_due'] ?? $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }
}
