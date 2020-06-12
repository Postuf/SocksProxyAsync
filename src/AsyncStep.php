<?php

declare(strict_types=1);

namespace SocksProxyAsync;

use Exception;
use RuntimeException;

class AsyncStep
{
    /**
     * @var int
     */
    private $step = 0;
    /**
     * @var int
     */
    private $stepTries = 0;
    /**
     * @var float
     */
    private $stepDuration = 0.0;
    /**
     * @var int
     */
    private $stepStart = 0;
    /**
     * @var string
     */
    private $stepName;
    /**
     * @var int
     */
    private $criticalTimeSeconds;
    /**
     * @var bool
     */
    private $finished = false;
    /**
     * @var bool
     */
    private $neverRun = true;

    public function __construct(string $stepName, float $criticalTimeSeconds = Constants::ASYNC_STEP_MAX_SEC)
    {
        $this->stepName = $stepName;
        $this->criticalTimeSeconds = $criticalTimeSeconds;
    }

    public function setStep(int $stepIdentity): void
    {
        $this->step = $stepIdentity;
        $this->resetStep();
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function finish(): void
    {
        $this->step = -1;
        $this->finished = true;
        $this->resetStep();
    }

    /**
     * @return bool
     * @noinspection PhpUnused
     */
    public function finished(): bool
    {
        return $this->finished;
    }

    private function resetStep(): void
    {
        $this->stepStart = microtime(true);
        $this->stepTries = 0;
        $this->stepDuration = 0.0;
    }

    protected function isStable(int $step): bool
    {
        return $step === -1;
    }

    /**
     * @throws Exception
     */
    public function checkIfStepStuck(): void
    {
        if ($this->isStable($this->step)) {
            return;
        }

        if ($this->finished) {
            throw new RuntimeException(Constants::ERR_SOCKET_ASYNC_STEP_FINISHED);
        }
        if ($this->neverRun) {
            $this->neverRun = false;
            $this->resetStep();
        }

        $this->stepTries++;
        $this->stepDuration = microtime(true) - $this->stepStart;

        if ((microtime(true) - $this->stepStart) > $this->criticalTimeSeconds) {
            throw new RuntimeException(
                Constants::ERR_SOCKET_ASYNC_STEP_TOO_LONG.' '.
                'Step stucked: '.$this->stepName.
                ', stepNo: '.$this->step.
                ', tries: '.$this->stepTries.
                ', durations: '."{$this->stepTries}: {$this->stepDuration}"
            );
        }
    }
}
