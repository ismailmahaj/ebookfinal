<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Romance', 'description' => 'Romans d\'amour et histoires sentimentales'],
            ['name' => 'Thriller', 'description' => 'Suspense, enquêtes et frissons'],
            ['name' => 'Science-Fiction', 'description' => 'Futur, espace et mondes imaginaires'],
            ['name' => 'Fantasy', 'description' => 'Heroic fantasy et mondes magiques'],
            ['name' => 'Polar', 'description' => 'Enquêtes criminelles et détectives'],
            ['name' => 'Biographie', 'description' => 'Vies de personnalités et mémoires'],
            ['name' => 'Histoire', 'description' => 'Histoire et documentaires'],
            ['name' => 'Développement personnel', 'description' => 'Motivation et bien-être'],
            ['name' => 'Jeunesse', 'description' => 'Romans et albums pour les plus jeunes'],
            ['name' => 'BD & Manga', 'description' => 'Bandes dessinées et mangas'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($cat['name'])],
                array_merge($cat, ['slug' => \Illuminate\Support\Str::slug($cat['name'])])
            );
        }
    }
}
