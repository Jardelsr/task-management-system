<?php

namespace Database\Factories;

use App\Models\TaskLog;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class TaskLogFactory extends Factory
{
    protected $model = TaskLog::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted', 'restored', 'completed']),
            'details' => [
                'field' => $this->faker->randomElement(['status', 'priority', 'assigned_to', 'due_date']),
                'old_value' => $this->faker->word,
                'new_value' => $this->faker->word,
            ],
            'user_id' => $this->faker->optional()->email,
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Create a log for task creation
     */
    public function created()
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'created',
                'details' => [
                    'task_data' => [
                        'title' => $this->faker->sentence,
                        'status' => 'pending',
                        'priority' => 'medium',
                    ]
                ],
            ];
        });
    }

    /**
     * Create a log for task update
     */
    public function updated()
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'updated',
                'details' => [
                    'field' => 'status',
                    'old_value' => 'pending',
                    'new_value' => 'in_progress',
                ],
            ];
        });
    }

    /**
     * Create a log for task deletion
     */
    public function deleted()
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'deleted',
                'details' => [
                    'soft_delete' => true,
                    'deleted_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Create a log for task completion
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'completed',
                'details' => [
                    'field' => 'status',
                    'old_value' => 'in_progress',
                    'new_value' => 'completed',
                    'completed_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Create a log with specific task ID
     */
    public function forTask($taskId)
    {
        return $this->state(function (array $attributes) use ($taskId) {
            return [
                'task_id' => $taskId,
            ];
        });
    }

    /**
     * Create a log with specific user
     */
    public function byUser($userId)
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'user_id' => $userId,
            ];
        });
    }
}