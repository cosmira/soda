<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->record('create:orders');
    }

    public function down(): void
    {
        $this->record('drop:orders');
    }
};
