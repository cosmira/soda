<?php

namespace Fixture\ReviewableApi;

class Importer
{
    /** @param array{string, int} $row */
    public function label(array $row): string
    {
        return $row[0].': '.$row[1];
    }

    public function run(): void
    {
        $this->prepare();
        $this->copy();
    }

    private function copy(): void {}

    private function prepare(): void {}
}
