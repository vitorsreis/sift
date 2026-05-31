<?php

declare(strict_types=1);

namespace Sift\Core;

enum ItemType: string
{
    case TestFailure = 'test_failure';
    case TestError = 'test_error';
    case Coverage = 'coverage';
    case Issue = 'issue';
    case Warning = 'warning';
    case Error = 'error';
    case SyntaxError = 'syntax_error';
    case Diff = 'diff';
    case ChangedFile = 'changed_file';
    case Dependency = 'dependency';
    case UnusedDependency = 'unused_dependency';
    case MissingDependency = 'missing_dependency';
    case Vulnerability = 'vulnerability';
    case License = 'license';
    case Package = 'package';
    case ArchitectureViolation = 'architecture_violation';
    case Mutation = 'mutation';
}
