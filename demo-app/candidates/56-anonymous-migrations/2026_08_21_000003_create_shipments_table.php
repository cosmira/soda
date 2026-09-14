<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->record('create:shipments');
    }

    public function down(): void
    {
        $this->record('drop:shipments');
    }
};
