<?php

namespace Features\Tenant\SendInvitation\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public Invitation $invitation
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $acceptUrl = $baseUrl.'/invitations/'.$this->invitation->token.'/accept';
        $rejectUrl = $baseUrl.'/invitations/'.$this->invitation->token.'/reject';

        $inviterName = $this->invitation->invitedBy?->name ?? 'Someone';
        $orgName = $this->invitation->organization?->name ?? config('app.name');
        $roleName = $this->invitation->role?->name ?? 'Member';
        $expiresAt = $this->invitation->expires_at?->format('F j, Y');

        return (new MailMessage)
            ->subject("You're invited to join {$orgName}")
            ->greeting('Hello!')
            ->line("{$inviterName} has invited you to join **{$orgName}** as a **{$roleName}**.")
            ->when($expiresAt, fn (MailMessage $message) => $message->line("This invitation will expire on {$expiresAt}."))
            ->action('Accept Invitation', $acceptUrl)
            ->line('If you do not wish to join this organization, you can decline the invitation:')
            ->line("[Decline Invitation]({$rejectUrl})")
            ->line('If you did not expect this invitation, please ignore this email.')
            ->salutation('Best regards, '.config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization?->name,
            'role' => $this->invitation->role?->name,
            'expires_at' => $this->invitation->expires_at,
        ];
    }
}
