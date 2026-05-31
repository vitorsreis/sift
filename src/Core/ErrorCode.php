<?php

declare(strict_types=1);

namespace Sift\Core;

enum ErrorCode: string
{
    case InvalidUsage = 'invalid_usage';
    case InvalidConfig = 'invalid_config';
    case ConfigSchemaUnsupported = 'config_schema_unsupported';
    case PhpVersionUnsupported = 'php_version_unsupported';
    case ExtensionMissing = 'extension_missing';
    case ToolNotFound = 'tool_not_found';
    case ToolDisabled = 'tool_disabled';
    case BlockedArgument = 'blocked_argument';
    case PolicyBlocked = 'policy_blocked';
    case UnsupportedToolVersion = 'unsupported_tool_version';
    case ProcessFailed = 'process_failed';
    case ProcessTimeout = 'process_timeout';
    case ProcessInterrupted = 'process_interrupted';
    case ParseFailure = 'parse_failure';
    case FilesystemError = 'filesystem_error';
    case HistoryWriteFailed = 'history_write_failed';
    case RunNotFound = 'run_not_found';
    case SkillCatalogUnavailable = 'skill_catalog_unavailable';
    case SkillSourceNotFound = 'skill_source_not_found';
    case SkillSelectionRequired = 'skill_selection_required';
    case UnsupportedTarget = 'unsupported_target';
    case GithubCloneFailed = 'github_clone_failed';
    case PharResourceMissing = 'phar_resource_missing';
}
