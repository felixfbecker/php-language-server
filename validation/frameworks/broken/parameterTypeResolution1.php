<?php

class A {
    public function setAccount(AccountInterface $account)
    {
        // If the passed account is already proxied, use the actual account instead
        // to prevent loops.
        if ($account instanceof static) {
            $account = $account->getAccount();
        }
    }
}
