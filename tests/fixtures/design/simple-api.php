<?php

namespace Fixture\SimpleApi;

class Synchronizer
{
    public function first(array $keys): mixed
    {
        return $keys[0];
    }

    public function apply(): void
    {
        $this->prepare();
        $this->copy();
    }

    public function reset(): void
    {
        $this->prepare();
        $this->copy();
    }

    private function copy(): void {}

    private function prepare(): void {}
}
