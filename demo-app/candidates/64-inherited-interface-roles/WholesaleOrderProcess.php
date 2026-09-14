<?php

declare(strict_types=1);

namespace DemoApp\Orders;

final class WholesaleOrderProcess extends OrderProcess implements Exportable, Prioritized, Reconciled {}
