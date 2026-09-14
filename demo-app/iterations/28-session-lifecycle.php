<?php

declare(strict_types=1);

final class ConversationalCheckoutManager
{
    /**
     * Open conversational state before continuing the checkout workflow.
     */
    public function begin(string $order): string
    {
        session_start();
        session_write_close();

        return 'started:'.$order;
    }
}

echo (new ConversationalCheckoutManager())->begin('order-42').PHP_EOL;
