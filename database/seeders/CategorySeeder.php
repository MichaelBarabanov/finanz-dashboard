<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Idempotent (firstOrCreate): kann bei jedem Start mitlaufen,
     * ohne Duplikate zu erzeugen.
     */
    public function run(): void
    {
        // [name, color, is_fixed, parent]
        $categories = [
            ['Einkommen',      '#16a34a', false, null],
            ['Auto',           '#0ea5e9', false, null],
            ['Sprit',          '#38bdf8', false, 'Auto'],
            ['Versicherung',   '#6366f1', true,  null],
            ['Kredit / Raten', '#dc2626', true,  null],
            ['PayPal',         '#2563eb', false, null],
            ['Essen',          '#f59e0b', false, null],
            ['Freizeit',       '#ec4899', false, null],
            ['Motorrad',       '#7c3aed', false, null],
            ['Gym',            '#10b981', true,  null],
            ['Gesundheit',     '#14b8a6', false, null],
            ['Altersvorsorge', '#0d9488', true,  null],
            ['Wohnen',         '#9333ea', true,  null],
            ['Abos',           '#f43f5e', true,  null],
            ['Sonstiges',      '#94a3b8', false, null],
        ];

        // Erst die Eltern, dann die Kinder.
        foreach ($categories as [$name, $color, $isFixed, $parent]) {
            if ($parent !== null) {
                continue;
            }
            Category::firstOrCreate(
                ['name' => $name],
                ['color' => $color, 'is_fixed' => $isFixed, 'is_system' => true],
            );
        }

        foreach ($categories as [$name, $color, $isFixed, $parent]) {
            if ($parent === null) {
                continue;
            }
            $parentId = Category::where('name', $parent)->value('id');
            Category::firstOrCreate(
                ['name' => $name],
                ['color' => $color, 'is_fixed' => $isFixed, 'is_system' => true, 'parent_id' => $parentId],
            );
        }
    }
}
