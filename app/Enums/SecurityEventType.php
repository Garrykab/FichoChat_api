<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case Register = 'register';
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case LogoutAll = 'logout_all';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case DeviceRegistered = 'device_registered';
    case DeviceApproved = 'device_approved';
    case DeviceRevoked = 'device_revoked';
    case DeviceRecoveryOtpSent = 'device_recovery_otp_sent';
    case DeviceRecovered = 'device_recovered';
    case DeviceRecoveryBackupUpdated = 'device_recovery_backup_updated';
    case DeviceRecoveryBackupDeleted = 'device_recovery_backup_deleted';
    case UnauthorizedAccess = 'unauthorized_access';
    case LogsViewed = 'logs_viewed';
    case UserSuspended = 'user_suspended';
    case UserActivated = 'user_activated';
    case AdminDeviceRevoked = 'admin_device_revoked';
}
