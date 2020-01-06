<?php

declare(strict_types=1);

namespace SocksProxyAsync;

use Exception;

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
     * @var array
     */
    private $stepDurations = [];
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
        $this->neverRun = true;
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

    private function resetStep()
    {
        $this->stepStart = microtime(true);
        $this->stepTries = 0;
        $this->stepDurations = [];
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
            throw new Exception(Constants::ERR_SOCKET_ASYNC_STEP_FINISHED);
        }
        if ($this->neverRun) {
            $this->neverRun = false;
            $this->resetStep();
        }

        $this->stepTries++;
        $this->stepDurations[] = microtime(true) - $this->stepStart;

        if ((microtime(true) - $this->stepStart) > $this->criticalTimeSeconds) {
            throw new Exception(
                Constants::ERR_SOCKET_ASYNC_STEP_TOO_LONG.' '.
                'Step stucked: '.$this->stepName.
                ', stepNo: '.$this->step.
                ', tries: '.$this->stepTries.
                ', durations: '.print_r($this->stepDurations, true)
            );
        }
    }
}
