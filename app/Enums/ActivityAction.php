<?php

namespace App\Enums;

enum ActivityAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case FORCE_DELETE = 'force_delete';
    case BULK_DELETE = 'bulk_delete';
    case BULK_FORCE_DELETE = 'bulk_force_delete';
    case BULK_RESTORE = 'bulk_restore';
    case VIEW = 'view';
    case ARCHIVE = 'archive';
    case APPROVE = 'approve';
    case ASSIGN = 'assign';
    case ATTACH = 'attach';
    case IMPORT = 'import';
    case EXPORT = 'export';
    case BACKUP = 'backup';
    case SEND = 'send';
    case PUBLISH = 'publish';
    case DUPLICATE = 'duplicate';
    case IMPERSONATE = 'impersonate';
    case REGISTER = 'register';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case PASSWORD_RESET_REQUEST = 'password_reset_request';
    case PASSWORD_RESET = 'password_reset';
    case PASSWORD_CHANGE = 'password_change';
    case EMAIL_VERIFICATION = 'email_verification';
}
