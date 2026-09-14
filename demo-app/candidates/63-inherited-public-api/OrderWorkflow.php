<?php

declare(strict_types=1);

namespace DemoApp\Orders;

abstract class OrderWorkflow
{
    public function quote(): string
    {
        return 'quote';
    }

    public function reserve(): string
    {
        return 'reserve';
    }

    public function release(): string
    {
        return 'release';
    }

    public function charge(): string
    {
        return 'charge';
    }

    public function refund(): string
    {
        return 'refund';
    }

    public function invoice(): string
    {
        return 'invoice';
    }

    public function notify(): string
    {
        return 'notify';
    }

    public function audit(): string
    {
        return 'audit';
    }

    public function schedule(): string
    {
        return 'schedule';
    }

    public function cancel(): string
    {
        return 'cancel';
    }

    public function status(): string
    {
        return 'status';
    }

    public function address(): string
    {
        return 'address';
    }

    public function discount(): string
    {
        return 'discount';
    }

    public function tax(): string
    {
        return 'tax';
    }

    public function capture(): string
    {
        return 'capture';
    }

    public function authorize(): string
    {
        return 'authorize';
    }

    public function fulfill(): string
    {
        return 'fulfill';
    }

    public function archive(): string
    {
        return 'archive';
    }
}
