<?php

namespace Crwlr\Crawler\Steps;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Generator;
use Psr\Log\LoggerInterface;

final class Loop implements StepInterface
{
    private int $maxIterations = 1000;

    private null|Closure|StepInterface $withInput = null;

    private bool $callWithInputOnlyOnce = false;

    private bool $callWithInputWithoutOutput = false;

    private null|Closure $stopIf = null;

    private bool $cascadeWhenFinished = false;

    /**
     * Use when cascadeWhenFinished() is used.
     *
     * @var mixed[]
     */
    private array $deferredOutputs = [];

    public function __construct(private readonly StepInterface $step)
    {
    }

    public function invokeStep(Input $input): Generator
    {
        for ($i = 0; $i < $this->maxIterations; $i++) {
            $anyOutput = false;
            $inputForNextIteration = null;

            foreach ($this->step->invokeStep($input) as $output) {
                if ($this->stopIf && $this->stopIf->call($this->step, $input->get(), $output->get()) === true) {
                    break 2;
                }

                $anyOutput = true;

                yield from $this->yieldOrDefer($output);

                if (!$this->callWithInputOnlyOnce) {
                    $inputForNextIteration = $this->nextIterationInput($input, $output) ?? $inputForNextIteration;
                }
            }

            if ($this->callWithInputOnlyOnce && $anyOutput && isset($output)) {
                $inputForNextIteration = $this->nextIterationInput($input, $output);
            }

            if (!$inputForNextIteration && $anyOutput === false && $this->callWithInputWithoutOutput) {
                $inputForNextIteration = $this->nextIterationInput($input, null);
            }

            if (!$inputForNextIteration) {
                break;
            }

            $input = $inputForNextIteration;
        }

        yield from $this->yieldDeferredOutputs();
    }

    public function maxIterations(int $count): self
    {
        $this->maxIterations = $count;

        return $this;
    }

    public function withInput(Closure|StepInterface $closure): self
    {
        $this->withInput = $closure;

        return $this;
    }

    public function callWithInputOnlyOnce(): self
    {
        $this->callWithInputOnlyOnce = true;

        return $this;
    }

    public function keepLoopingWithoutOutput(): self
    {
        $this->callWithInputWithoutOutput = true;

        return $this;
    }

    public function stopIf(Closure $closure): self
    {
        $this->stopIf = $closure;

        return $this;
    }

    public function setResultKey(string $key): static
    {
        $this->step->setResultKey($key);

        return $this;
    }

    public function getResultKey(): ?string
    {
        return $this->step->getResultKey();
    }

    public function addKeysToResult(?array $keys = null): static
    {
        $this->step->addKeysToResult($keys);

        return $this;
    }

    public function uniqueInputs(?string $key = null): static
    {
        $this->step->uniqueInputs($key);

        return $this;
    }

    public function uniqueOutputs(?string $key = null): static
    {
        $this->step->uniqueOutputs($key);

        return $this;
    }

    public function addsToOrCreatesResult(): bool
    {
        return $this->step->addsToOrCreatesResult();
    }

    public function useInputKey(string $key): static
    {
        $this->step->useInputKey($key);

        return $this;
    }

    public function dontCascade(): static
    {
        $this->step->dontCascade();

        return $this;
    }

    public function cascadeWhenFinished(): static
    {
        $this->cascadeWhenFinished = true;

        return $this;
    }

    public function cascades(): bool
    {
        return $this->step->cascades();
    }

    public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        $this->step->where($keyOrFilter, $filter);

        return $this;
    }

    public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        $this->step->orWhere($keyOrFilter, $filter);

        return $this;
    }

    /**
     * Callback that is called in a step group to adapt the input for further steps
     *
     * In groups all the steps are called with the same Input, but with this callback it's possible to adjust the input
     * for the following steps.
     */
    public function updateInputUsingOutput(Closure $closure): static
    {
        if (method_exists($this->step, 'updateInputUsingOutput')) {
            $this->step->updateInputUsingOutput($closure);
        }

        return $this;
    }

    /**
     * If the user set a callback to update the input (see above) => call it.
     */
    public function callUpdateInputUsingOutput(Input $input, Output $output): Input
    {
        if (method_exists($this->step, 'callUpdateInputUsingOutput')) {
            return $this->step->callUpdateInputUsingOutput($input, $output);
        }

        return $input;
    }

    public function addLogger(LoggerInterface $logger): static
    {
        $this->step->addLogger($logger);

        return $this;
    }

    public function addLoader(LoaderInterface $loader): self
    {
        if (method_exists($this->step, 'addLoader')) {
            $this->step->addLoader($loader);
        }

        return $this;
    }

    public function resetAfterRun(): void
    {
        $this->step->resetAfterRun();
    }

    private function yieldOrDefer(Output $output): Generator
    {
        if (!$this->step->cascades()) {
            return;
        }

        if ($this->cascadeWhenFinished) {
            $this->deferredOutputs[] = $output;
        } else {
            yield $output;
        }
    }

    private function nextIterationInput(Input $input, ?Output $output): ?Input
    {
        if ($this->withInput) {
            $newInputValue = null;

            if ($this->withInput instanceof Closure) {
                $newInputValue = $this->withInput->call($this->step, $input->get(), $output?->get());
            } elseif ($output) {
                foreach ($this->withInput->invokeStep(new Input($output)) as $output) {
                    $newInputValue = $output;
                }
            }

            return $newInputValue !== null ? new Input($newInputValue, $input->result) : null;
        }

        return new Input($output);
    }

    private function yieldDeferredOutputs(): Generator
    {
        if (!empty($this->deferredOutputs)) {
            foreach ($this->deferredOutputs as $deferredOutput) {
                yield $deferredOutput;
            }

            $this->deferredOutputs = [];
        }
    }
}
