<?php
declare(strict_types = 1);

namespace Rx\Operator;

use gamringer\JSONPatch\Patch;

class ApplyJsonPatchesOperator extends Operator
{
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
            }),
            [$observer, 'onError'],
            function () use (&$result) {
                $observer->onNext($result);
                $observer->onComplete();
            }
        );
    }
}
