<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Temporary MySQL-based TaskLog model for testing
 * This is a fallback when MongoDB is not available
 */
class TaskLogMySQL extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',          // ID da tarefa relacionada
        'action',           // Ação realizada (created, updated, deleted)
        'old_data',         // Dados anteriores (para update e delete)
        'new_data',         // Novos dados (para create e update)
        'user_id',          // ID do usuário que executou a ação
        'user_name',        // Nome do usuário para referência
        'description',      // Description for the log entry
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];
}