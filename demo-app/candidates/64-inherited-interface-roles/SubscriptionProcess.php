<?php

declare(strict_types=1);

namespace DemoApp\Subscriptions;

abstract class Process implements Billable, Notifiable, Renewable {}
