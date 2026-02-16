<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Tools\Concerns\DecodesJsonOutput;
use Sift\Tools\Concerns\DetectsCliOptions;
use Sift\Tools\Concerns\ResolvesToolCandidates;

it('detects long cli options and option families consistently', function (): void {
    $helper = new class
    {
        use DetectsCliOptions;

        /**
         * @param  list<string>  $arguments
         */
        public function has(array $arguments, string $option): bool
        {
            return $this->hasOption($arguments, $option);
        }

        /**
         * @param  list<string>  $arguments
         * @param  list<string>  $options
         */
        public function hasAny(array $arguments, array $options): bool
        {
            return $this->hasAnyOption($arguments, $options);
        }

        /**
         * @param  list<string>  $arguments
         */
        public function option(array $arguments, string $option): ?string
        {
            return $this->optionValue($arguments, $option);
        }

        /**
         * @param  list<string>  $arguments
         */
        public function floatOption(array $arguments, string $option): ?float
        {
            return $this->floatOptionValue($arguments, $option);
        }
    };

    $arguments = ['--coverage-text', '--filter=FocusedTest', '--min', '82.5'];

    expect($helper->has($arguments, '--filter'))->toBeTrue()
        ->and($helper->has($arguments, '--filter=FocusedTest'))->toBeTrue()
        ->and($helper->has($arguments, '--testsuite'))->toBeFalse()
        ->and($helper->hasAny($arguments, ['--coverage', '--coverage-text']))->toBeTrue()
        ->and($helper->hasAny($arguments, ['--coverage-html', '--log-junit']))->toBeFalse()
        ->and($helper->option($arguments, '--min'))->toBe('82.5')
        ->and($helper->floatOption($arguments, '--min'))->toBe(82.5)
        ->and($helper->floatOption(['--min=75'], '--min'))->toBe(75.0)
        ->and($helper->floatOption(['--min', 'bogus'], '--min'))->toBeNull();
});

it('prefers configured tool binaries over discovery candidates', function (): void {
    $helper = new class
    {
        use ResolvesToolCandidates;

        /**
         * @param  array<string, mixed>  $context
         * @param  list<string>  $fallbackCandidates
         * @return list<string>
         */
        public function resolve(array $context, array $fallbackCandidates): array
        {
            return $this->resolveCandidates($context, $fallbackCandidates);
        }
    };

    expect($helper->resolve(['tool_binary' => 'vendor/bin/custom-tool'], ['vendor/bin/default-tool', 'tool']))
        ->toBe(['vendor/bin/custom-tool'])
        ->and($helper->resolve([], ['vendor/bin/default-tool', 'tool']))
        ->toBe(['vendor/bin/default-tool', 'tool']);
});

it('decodes clean and noisy json streams from command output', function (): void {
    $helper = new class
    {
        use DecodesJsonOutput;

        /**
         * @return array<string, mixed>|null
         */
        public function decode(ExecutionResult $result, bool $allowNoisy = false): ?array
        {
            return $this->decodeJsonOutput($result, $allowNoisy);
        }
    };

    $clean = $helper->decode(new ExecutionResult(0, '{"status":"ok"}', '', 5));
    $noisy = $helper->decode(new ExecutionResult(1, "warning\n{\"status\":\"failed\"}\n", '', 7), true);
    $stderr = $helper->decode(new ExecutionResult(1, 'not-json', '{"status":"error"}', 9));

    expect($clean)->toBe(['status' => 'ok'])
        ->and($noisy)->toBe(['status' => 'failed'])
        ->and($stderr)->toBe(['status' => 'error'])
        ->and($helper->decode(new ExecutionResult(1, 'noise', 'still-noise', 9), true))->toBeNull();
});
