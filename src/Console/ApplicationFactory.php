<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\AddService;
use Sift\Runtime\BlockedArgumentsPolicy;
use Sift\Runtime\ComposerCommandPolicy;
use Sift\Runtime\ConfigDocumentManager;
use Sift\Runtime\ConfigLoader;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\InitService;
use Sift\Runtime\PolicyRunner;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ProjectInspector;
use Sift\Runtime\RectorCommandPolicy;
use Sift\Runtime\ResultMetaStamper;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolEnabledPolicy;
use Sift\Runtime\ToolInstalledPolicy;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ValidateService;
use Sift\Runtime\ViewService;
use Sift\Tools\ComposerAuditToolAdapter;
use Sift\Tools\ComposerToolAdapter;
use Sift\Tools\ParatestToolAdapter;
use Sift\Tools\PestToolAdapter;
use Sift\Tools\PhpcsToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PhpunitToolAdapter;
use Sift\Tools\PintToolAdapter;
use Sift\Tools\PsalmToolAdapter;
use Sift\Tools\RectorToolAdapter;

final class ApplicationFactory
{
    public function createDefault(): Application
    {
        $toolLocator = new ToolLocator;
        $configLoader = new ConfigLoader;
        $configDocumentManager = new ConfigDocumentManager($configLoader);
        $runStore = new FileRunStore;

        return new Application(
            new OptionParser,
            new ToolRegistry([
                new ComposerAuditToolAdapter($toolLocator),
                new ComposerToolAdapter($toolLocator),
                new ParatestToolAdapter($toolLocator),
                new PestToolAdapter($toolLocator),
                new PhpcsToolAdapter($toolLocator),
                new PintToolAdapter($toolLocator),
                new RectorToolAdapter($toolLocator),
                new PsalmToolAdapter($toolLocator),
                new PhpstanToolAdapter($toolLocator),
                new PhpunitToolAdapter($toolLocator),
            ]),
            new JsonRenderer,
            new MarkdownRenderer,
            new ProcessExecutor,
            new ResultPayloadFactory,
            new ResultMetaStamper,
            $configLoader,
            $runStore,
            new InitService($toolLocator, $configDocumentManager),
            new AddService($toolLocator, $configDocumentManager),
            new PolicyRunner([
                new ToolEnabledPolicy,
                new BlockedArgumentsPolicy,
                new ToolInstalledPolicy($toolLocator),
                new ComposerCommandPolicy,
                new RectorCommandPolicy,
            ]),
            $toolLocator,
            new ProjectInspector($toolLocator),
            new ValidateService($configLoader),
            new ViewService($runStore),
        );
    }
}
