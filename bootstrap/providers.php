<?php

use Features\Acl\AssignToUser\Providers\AssignToUserServiceProvider;
use Features\Acl\AttachPermission\Providers\AttachPermissionServiceProvider;
use Features\Acl\CreatePermission\Providers\CreatePermissionServiceProvider;
use Features\Acl\CreateRole\Providers\CreateRoleServiceProvider;
use Features\Acl\DeletePermission\Providers\DeletePermissionServiceProvider;
use Features\Acl\DeleteRole\Providers\DeleteRoleServiceProvider;
use Features\Acl\DetachPermission\Providers\DetachPermissionServiceProvider;
use Features\Acl\GetPermission\Providers\GetPermissionServiceProvider;
use Features\Acl\GetPermissions\Providers\GetPermissionsServiceProvider;
use Features\Acl\GetRole\Providers\GetRoleServiceProvider;
use Features\Acl\GetRoles\Providers\GetRolesServiceProvider;
use Features\Acl\RemoveFromUser\Providers\RemoveFromUserServiceProvider;
use Features\Acl\UpdatePermission\Providers\UpdatePermissionServiceProvider;
use Features\Acl\UpdateRole\Providers\UpdateRoleServiceProvider;
use Features\Auth\ChangePassword\Providers\ChangePasswordServiceProvider;
use Features\Auth\EmailVerification\Providers\EmailVerificationServiceProvider;
use Features\Auth\GetUser\Providers\GetUserServiceProvider;
use Features\Auth\Login\Providers\LoginServiceProvider;
use Features\Auth\Logout\Providers\LogoutServiceProvider;
use Features\Auth\PasswordReset\Providers\PasswordResetServiceProvider;
use Features\Auth\Register\Providers\RegisterServiceProvider;
use Features\Auth\TwoFactorAuth\Providers\TwoFactorAuthServiceProvider;
use Features\Compliance\GdprCancelDeletion\Providers\GdprCancelDeletionServiceProvider;
use Features\Compliance\GdprDelete\Providers\GdprDeleteServiceProvider;
use Features\Compliance\GdprDownload\Providers\GdprDownloadServiceProvider;
use Features\Compliance\GdprExport\Providers\GdprExportServiceProvider;
use Features\Compliance\GdprRequestDeletion\Providers\GdprRequestDeletionServiceProvider;
use Features\Subscription\SubscriptionServiceProvider;
use Features\Tenant\AcceptInvitation\Providers\AcceptInvitationServiceProvider;
use Features\Tenant\AddUserToOrganization\Providers\AddUserToOrganizationServiceProvider;
use Features\Tenant\CancelInvitation\Providers\CancelInvitationServiceProvider;
use Features\Tenant\CreateOrganization\Providers\CreateOrganizationServiceProvider;
use Features\Tenant\DeleteOrganization\Providers\DeleteOrganizationServiceProvider;
use Features\Tenant\GetInvitationByToken\Providers\GetInvitationByTokenServiceProvider;
use Features\Tenant\GetInvitations\Providers\GetInvitationsServiceProvider;
use Features\Tenant\GetOrganization\Providers\GetOrganizationServiceProvider;
use Features\Tenant\GetOrganizations\Providers\GetOrganizationsServiceProvider;
use Features\Tenant\GetOrganizationSettings\Provider\GetOrganizationSettingsServiceProvider;
use Features\Tenant\RejectInvitation\Providers\RejectInvitationServiceProvider;
use Features\Tenant\RemoveUserFromOrganization\Providers\RemoveUserFromOrganizationServiceProvider;
use Features\Tenant\SendInvitation\Providers\SendInvitationServiceProvider;
use Features\Tenant\UpdateOrganization\Providers\UpdateOrganizationServiceProvider;
use Features\Tenant\UpdateOrganizationSettings\Provider\UpdateOrganizationSettingsServiceProvider;
use Features\Tenant\UpdateUserRoleOnOrganization\Providers\UpdateUserRoleOnOrganizationServiceProvider;
use Features\Webhooks\CreateWebhook\Providers\CreateWebhookServiceProvider;
use Features\Webhooks\DeleteWebhook\Providers\DeleteWebhookServiceProvider;
use Features\Webhooks\GetWebhook\Providers\GetWebhookServiceProvider;
use Features\Webhooks\GetWebhookDeliveries\Providers\GetWebhookDeliveriesServiceProvider;
use Features\Webhooks\GetWebhooks\Providers\GetWebhooksServiceProvider;
use Features\Webhooks\UpdateWebhook\Providers\UpdateWebhookServiceProvider;
use Features\Webhooks\WebhookServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    LoginServiceProvider::class,
    RegisterServiceProvider::class,
    TwoFactorAuthServiceProvider::class,
    GetUserServiceProvider::class,
    LogoutServiceProvider::class,
    PasswordResetServiceProvider::class,
    EmailVerificationServiceProvider::class,
    ChangePasswordServiceProvider::class,
    CreateWebhookServiceProvider::class,
    UpdateWebhookServiceProvider::class,
    DeleteWebhookServiceProvider::class,
    GetWebhookServiceProvider::class,
    GetWebhooksServiceProvider::class,
    GetWebhookDeliveriesServiceProvider::class,
    GetOrganizationsServiceProvider::class,
    CreateOrganizationServiceProvider::class,
    GetOrganizationServiceProvider::class,
    UpdateOrganizationServiceProvider::class,
    DeleteOrganizationServiceProvider::class,
    AddUserToOrganizationServiceProvider::class,
    RemoveUserFromOrganizationServiceProvider::class,
    UpdateUserRoleOnOrganizationServiceProvider::class,
    GetOrganizationSettingsServiceProvider::class,
    UpdateOrganizationSettingsServiceProvider::class,
    AcceptInvitationServiceProvider::class,
    CancelInvitationServiceProvider::class,
    GetInvitationByTokenServiceProvider::class,
    GetInvitationsServiceProvider::class,
    RejectInvitationServiceProvider::class,
    SendInvitationServiceProvider::class,
    GdprExportServiceProvider::class,
    GdprDeleteServiceProvider::class,
    GdprDownloadServiceProvider::class,
    GdprCancelDeletionServiceProvider::class,
    GdprRequestDeletionServiceProvider::class,
    Features\Audit\AuditServiceProvider::class,
    GetPermissionsServiceProvider::class,
    GetPermissionServiceProvider::class,
    CreatePermissionServiceProvider::class,
    DeletePermissionServiceProvider::class,
    UpdatePermissionServiceProvider::class,
    GetRolesServiceProvider::class,
    GetRoleServiceProvider::class,
    CreateRoleServiceProvider::class,
    UpdateRoleServiceProvider::class,
    DeleteRoleServiceProvider::class,
    AttachPermissionServiceProvider::class,
    DetachPermissionServiceProvider::class,
    AssignToUserServiceProvider::class,
    WebhookServiceProvider::class,
    RemoveFromUserServiceProvider::class,
    SubscriptionServiceProvider::class,
];
