<?php

namespace Database\Factories;

use App\Models\ErrorOccurrence;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorOccurrence>
 */
class ErrorOccurrenceFactory extends Factory
{
    public function definition(): array
    {
        $class = fake()->randomElement([
            'Illuminate\\Database\\Eloquent\\ModelNotFoundException',
            'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
            'RuntimeException',
        ]);
        $file = 'app/Http/Controllers/UserController.php';
        $line = fake()->numberBetween(10, 200);

        return [
            'project_id' => Project::factory(),
            'exception_class' => $class,
            'message' => fake()->sentence(),
            'stacktrace' => [
                ['function' => 'handle', 'file' => $file, 'line' => $line, 'class' => 'App\\Http\\Controllers\\UserController'],
            ],
            'fingerprint' => hash('sha256', $class.'|'.$file.'|'.$line),
            'file' => $file,
            'line' => $line,
            'environment' => 'production',
            'occurred_at' => now(),
        ];
    }
}
