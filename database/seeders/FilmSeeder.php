<?php

namespace Database\Seeders;

use App\Models\Film;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Xylis\FakerCinema\Provider\Movie;
use Xylis\FakerCinema\Provider\Person;

class FilmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new Movie($faker));
        $faker->addProvider(new Person($faker));

        Film::factory(8)->create(
            [
                'title' => $faker->movie,
                'summary' => $faker->overview,
                'year' => $faker->year,
                'director' => $faker->director,
                'starring' => implode(',', $faker->actors),
                'genre' => $faker->movieGenre,
                'duration' => $faker->runtime,
                'cover_photo' => '/'.Storage::disk('cover-photos')->getConfig()['relative_path'].'/cover_'.$faker->numberBetween(1, 8).'_photo.jpeg',
                'rating' => $faker->numberBetween(1, 5),
            ]
        );
    }
}
