<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Product::class;
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 5, 500),
            'image_path' => 'images/' . $this->faker->randomElement($this->getSampleImages()),
            'video_path' => 'videos/' . $this->faker->randomElement($this->getSampleVideos()),
        ];
    }
        /**
     * Sample image paths for testing.
     *
     * @return array
     */
    public function getSampleImages()
    {
        return [
            'a1.jpg',
            'a2.jpg',
            'a3.jpg',
            'a4.jpg',
            'a5.jpg',
        ];
    }

    /**
     * Sample video paths for testing.
     *
     * @return array
     */
    public function getSampleVideos()
    {
        return [
            'a1.mp4',
            'a2.mp4',
            'a3.mp4',
            'a4.mp4',
            'a5.mp4',
        ];
    }
}
