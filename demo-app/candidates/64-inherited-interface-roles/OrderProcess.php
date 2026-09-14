<?php

declare(strict_types=1);

namespace DemoApp\Orders;

abstract class OrderProcess implements Auditable, Billable, Reservable {}
