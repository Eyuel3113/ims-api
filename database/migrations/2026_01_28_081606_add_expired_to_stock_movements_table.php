<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock', 'expired') NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check");
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock', 'expired'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock') NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check");
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock'))");
        }
    }
};
