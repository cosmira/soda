<?php

declare(strict_types=1);

trait ReservesInventory
{
    /**
     * Reserve sellable inventory for the order.
     */
    public function reserveInventory(string $order): string
    {
        return 'reserved:'.$order;
    }

    /**
     * Release inventory after cancellation.
     */
    public function releaseInventory(string $order): string
    {
        return 'released:'.$order;
    }

    /**
     * Confirm that a reservation can be fulfilled.
     */
    public function confirmInventory(string $order): string
    {
        return 'confirmed:'.$order;
    }

    /**
     * Move a reservation to an alternate warehouse.
     */
    public function transferInventory(string $order): string
    {
        return 'transferred:'.$order;
    }

    /**
     * Extend a reservation while payment settles.
     */
    public function extendReservation(string $order): string
    {
        return 'extended:'.$order;
    }

    /**
     * Split a reservation between available warehouses.
     */
    public function splitReservation(string $order): string
    {
        return 'split:'.$order;
    }

    /**
     * Return the current reservation status.
     */
    public function inventoryStatus(string $order): string
    {
        return 'available:'.$order;
    }
}
