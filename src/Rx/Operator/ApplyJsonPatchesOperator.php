<?php
declare(strict_types = 1);

namespace Rx\Operator;

use gamringer\JSONPatch\Patch;

class ApplyJsonPatchesOperator extends Operator
{
    private $classType;
    private $isArray;
    private $mapper;

    public function __construct(JsonMapper $mapper, string $classType, bool $isArray = false)
    {
        $this->classType = $classType;
        $this->isArray = $isArray;
        $this->mapper = $mapper;
    }

    /**
     * @param ObservableInterface $observable
     * @param ObserverInterface $observer
     * @param SchedulerInterface|null $scheduler
     * @return \Rx\DisposableInterface
     */
    public function __invoke(ObservableInterface $observable, ObserverInterface $observer, SchedulerInterface $scheduler = null)
    {
        $result = null;
        $pointer = new Pointer($result);

        return $observable->subscribe(new CallbackObserver(
            function (JsonPatch $patch) use ($pointer) {
                $patch->apply($pointer);
                
                if ($this->isArray) {
                    $result = [];
                } else {
                    $classType = $this->classType;
                    $result = new $classType;
                }
            }),
            [$observer, 'onError'],
            function () use (&$result) {
                $observer->onNext($result);
                $observer->onComplete();
            }
        );
    }
}
